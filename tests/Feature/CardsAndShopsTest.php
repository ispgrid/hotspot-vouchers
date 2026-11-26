<?php

use App\Models\Account;
use App\Models\AppSetting;
use App\Models\User;
use Ispgrid\HotspotVouchers\Actions\HandOutBatch;
use Ispgrid\HotspotVouchers\Models\Batch;
use Ispgrid\HotspotVouchers\Models\Shop;
use Ispgrid\HotspotVouchers\Models\ShopEntry;
use Ispgrid\HotspotVouchers\Models\Voucher;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('prints every card with its code, plan, validity and price, and logs it', function () {
    AppSetting::put('currency_symbol', '₹');
    AppSetting::put('vouchers.card_title', 'Sharma WiFi');
    $batch = voucherBatch(2);
    $codes = Voucher::query()->pluck('code');

    $this->get(route('vouchers.batches.print', $batch->id))
        ->assertOk()
        ->assertSee('Sharma WiFi')
        ->assertSee('Hotspot 3h')
        ->assertSee('₹20.00')
        ->assertSee('3 hours from first login')
        ->assertSee($codes[0])
        ->assertSee($codes[1])
        ->assertDontSee('<svg', false);

    expect(Activity::query()->where('event', 'voucher-cards-printed')->sole()->properties['cards'])->toBe(2);
});

it('puts a QR code that logs the card in on each card once the login address is set', function () {
    AppSetting::put('vouchers.login_url', 'http://hotspot.example/login');
    $batch = voucherBatch(1);

    $this->get(route('vouchers.batches.print', $batch->id))->assertOk()->assertSee('<svg', false);
});

it('prints the password of username and password cards', function () {
    $batch = voucherBatch(1, ['login_style' => Batch::PASSWORD]);
    $password = Account::query()->findOrFail(Voucher::query()->sole()->account_id)->password;

    $this->get(route('vouchers.batches.print', $batch->id))->assertSee(__('Password'))->assertSee($password);
});

it('exports the cards as CSV a spreadsheet will not run', function () {
    $batch = voucherBatch(1, ['note' => null]);
    $batch->update(['plan_name' => '=HYPERLINK("x")']);
    $code = Voucher::query()->sole()->code;

    $csv = $this->get(route('vouchers.batches.csv', $batch->id))->assertOk()->streamedContent();

    expect($csv)->toContain($code)->toContain("'=HYPERLINK")
        ->and(Activity::query()->where('event', 'voucher-cards-exported')->count())->toBe(1);
});

it('keeps the codes from staff without Print and export vouchers', function () {
    $batch = voucherBatch(1);
    $this->actingAs(staffWithVoucherBoxes('generate-vouchers'));

    $this->get(route('vouchers.batches.print', $batch->id))->assertForbidden();
    $this->get(route('vouchers.batches.csv', $batch->id))->assertForbidden();
});

it('adds a shop and refuses a second one with the same name', function () {
    Livewire::test('vouchers::shops')->set('name', 'Gupta Mobiles')->set('phone', '9876543210')->call('add')->assertHasNoErrors();
    Livewire::test('vouchers::shops')->set('name', 'Gupta Mobiles')->call('add')->assertHasErrors(['name']);

    expect(Shop::query()->sole())->name->toBe('Gupta Mobiles')->phone->toBe('9876543210');
});

it('records a shop payment and shows what it still owes', function () {
    AppSetting::put('currency_symbol', '₹');
    $shop = Shop::query()->create(['name' => 'Gupta Mobiles']);
    app(HandOutBatch::class)->handle(voucherBatch(4)->id, $shop->id, '15', null);

    Livewire::test('vouchers::shop', ['shop' => $shop->id])
        ->set('amount', '50')
        ->set('payment_note', 'Cash')
        ->call('recordPayment')
        ->assertHasNoErrors()
        ->assertSee('₹60.00')
        ->assertSee('₹50.00')
        ->assertSee('₹10.00');

    Livewire::test('vouchers::shops')->assertSee('Gupta Mobiles')->assertSee('₹10.00');

    expect(ShopEntry::query()->where('kind', 'payment')->sole())->amount->toBe('50.000')->note->toBe('Cash')
        ->and(Activity::query()->where('event', 'voucher-shop-payment')->count())->toBe(1);
});

it('refuses a payment of nothing or one dated in the future', function (string $field, Closure|string $value) {
    $value = $value instanceof Closure ? $value() : $value;
    $shop = Shop::query()->create(['name' => 'Gupta Mobiles']);

    Livewire::test('vouchers::shop', ['shop' => $shop->id])->set('amount', '10')->set($field, $value)->call('recordPayment')->assertHasErrors([$field]);
})->with([
    'zero' => ['amount', '0'],
    'letters' => ['amount', 'ten'],
    'tomorrow' => ['paid_on', fn (): string => now()->addDay()->toDateString()],
]);

it('saves the card name and the Hotspot login address', function () {
    Livewire::test('vouchers::settings')->set('card_title', 'Sharma WiFi')->set('login_url', 'http://hotspot.example/login')->call('save')->assertHasNoErrors();

    expect(AppSetting::valueOf('vouchers.card_title'))->toBe('Sharma WiFi')
        ->and(AppSetting::valueOf('vouchers.login_url'))->toBe('http://hotspot.example/login');

    Livewire::test('vouchers::settings')->set('login_url', 'javascript:alert(1)')->call('save')->assertHasErrors(['login_url']);
});
