<?php

use App\Models\Account;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Ispgrid\HotspotVouchers\Support\CardLayouts;
use Ispgrid\HotspotVouchers\Models\Batch;
use Ispgrid\HotspotVouchers\Models\Voucher;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Storage::fake('addons');
    $this->actingAs(User::factory()->create());
});

/**
 * A small real PNG saved as the card logo; returns its base64 bytes.
 */
function cardLogo(): string
{
    $path = UploadedFile::fake()->image('logo.png', 40, 20)->storeAs('vouchers', 'logo.png', 'addons');
    AppSetting::put('vouchers.logo', $path);

    return base64_encode((string) Storage::disk('addons')->get($path));
}

function testLayout(string $key = 'x-test'): void
{
    View::addNamespace('vtest', __DIR__.'/../views');
    app(CardLayouts::class)->register($key, fn (): string => 'Test stickers', 'vtest::test-layout');
}

function printedLayout(): ?string
{
    return Activity::query()->where('event', 'voucher-cards-printed')->latest('id')->first()?->properties['layout'];
}

it('prints the logo, the band and the footer on the A4 21 sheet', function () {
    cardLogo();
    AppSetting::put('vouchers.band_colour', '#7c2d12');
    AppSetting::put('vouchers.footer_1', 'Help 98765');
    $batch = voucherBatch(2);

    $this->get(route('vouchers.batches.print', $batch->id))
        ->assertOk()
        ->assertSee('data:image/png;base64,', false)
        ->assertSee('#7c2d12', false)
        ->assertSee('Help 98765');

    expect(printedLayout())->toBe('a4-21');
});

it('puts the logo bytes in the page once', function () {
    $logo = cardLogo();
    $batch = voucherBatch(21);

    $html = $this->get(route('vouchers.batches.print', $batch->id))->assertOk()->getContent();

    expect(substr_count($html, $logo))->toBe(1);
});

it('leaves out the fields switched off', function () {
    AppSetting::put('vouchers.login_url', 'http://hotspot.example/login');
    AppSetting::put('currency_symbol', '₹');

    foreach (['show_price', 'show_validity', 'show_plan', 'show_qr'] as $flag) {
        AppSetting::put('vouchers.'.$flag, '0');
    }

    $batch = voucherBatch(2);
    $codes = Voucher::query()->pluck('code');

    $this->get(route('vouchers.batches.print', $batch->id))
        ->assertOk()
        ->assertDontSee('₹20.00')
        ->assertDontSee('3 hours from first login')
        ->assertDontSee('Hotspot 3h, ')
        ->assertDontSee('<svg', false)
        ->assertSee($codes[0])
        ->assertSee($codes[1]);
});

it('escapes the footer and the name', function () {
    AppSetting::put('vouchers.footer_1', '<b>x</b>');
    AppSetting::put('vouchers.card_title', '<i>Sharma</i>');
    $batch = voucherBatch(1);

    $this->get(route('vouchers.batches.print', $batch->id))
        ->assertSee('&lt;b&gt;x&lt;/b&gt;', false)
        ->assertDontSee('<b>x</b>', false)
        ->assertDontSee('<i>Sharma</i>', false);
});

it('prints without a logo when none is set or its file is gone', function () {
    $batch = voucherBatch(1);

    $this->get(route('vouchers.batches.print', $batch->id))->assertOk()->assertDontSee('data:image', false);

    AppSetting::put('vouchers.logo', 'vouchers/missing.png');

    $this->get(route('vouchers.batches.print', $batch->id))->assertOk()->assertDontSee('data:image', false);
});

it('falls back to A4 21 for an unknown layout', function () {
    $batch = voucherBatch(1);

    $this->get(route('vouchers.batches.print', [$batch->id, 'layout' => 'nope']))
        ->assertOk()
        ->assertSee(Voucher::query()->sole()->code);

    expect(printedLayout())->toBe('a4-21');
});

it('prints with a layout another add-on registered', function () {
    testLayout();
    $batch = voucherBatch(2);
    $codes = Voucher::query()->pluck('code');

    $this->get(route('vouchers.batches.print', [$batch->id, 'layout' => 'x-test']))
        ->assertOk()
        ->assertSee('TEST LAYOUT')
        ->assertSee($codes[0])
        ->assertSee($codes[1]);

    expect(printedLayout())->toBe('x-test');
});

it('uses the saved default layout', function () {
    testLayout();
    AppSetting::put('vouchers.layout', 'x-test');
    $batch = voucherBatch(1);

    $this->get(route('vouchers.batches.print', $batch->id))->assertOk()->assertSee('TEST LAYOUT');
});

it('prints only unused cards with any layout', function () {
    testLayout();
    $batch = voucherBatch(2);
    [$used, $unused] = Voucher::query()->orderBy('id')->get()->all();
    Account::query()->findOrFail($used->account_id)->forceFill(['hotspot_first_login_at' => now(), 'expiry' => now()->addHours(3), 'hotspot_pending_validity' => null])->saveQuietly();

    $this->get(route('vouchers.batches.print', [$batch->id, 'cards' => 'unused', 'layout' => 'x-test']))
        ->assertOk()
        ->assertSee('TEST LAYOUT')
        ->assertSee($unused->code)
        ->assertDontSee($used->code);
});

it('prints every card on each built-in layout', function (string $layout) {
    AppSetting::put('vouchers.login_url', 'http://hotspot.example/login');
    $batch = voucherBatch(2, ['login_style' => Batch::PASSWORD]);
    $vouchers = Voucher::query()->orderBy('id')->get();
    $password = Account::query()->findOrFail($vouchers[0]->account_id)->password;

    $this->get(route('vouchers.batches.print', [$batch->id, 'layout' => $layout]))
        ->assertOk()
        ->assertSee($vouchers[0]->code)
        ->assertSee($vouchers[1]->code)
        ->assertSee($password)
        ->assertSee('<svg', false);

    expect(printedLayout())->toBe($layout);
})->with(['a4-21', 'a4-40', 'thermal-58', 'thermal-80']);

it('prints no band colour on thermal paper', function (string $layout) {
    AppSetting::put('vouchers.band_colour', '#7c2d12');
    $batch = voucherBatch(1);

    $this->get(route('vouchers.batches.print', [$batch->id, 'layout' => $layout]))->assertOk()->assertDontSee('#7c2d12', false);
})->with(['thermal-58', 'thermal-80']);

it('leaves the logo off the A4 40 sheet', function () {
    cardLogo();
    $batch = voucherBatch(1);

    $this->get(route('vouchers.batches.print', [$batch->id, 'layout' => 'a4-40']))->assertOk()->assertDontSee('data:image', false);
});
