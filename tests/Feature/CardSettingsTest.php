<?php

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Ispgrid\HotspotVouchers\Support\CardSettings;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('addons');
    $this->actingAs(User::factory()->create());
});

it('saves and reloads every card setting', function () {
    Livewire::test('vouchers::settings')
        ->set('footer_1', 'Help: 98765 43210')
        ->set('footer_2', 'Ask the shop')
        ->set('band_colour', '#166534')
        ->set('show_price', false)
        ->set('show_validity', false)
        ->set('show_plan', false)
        ->set('show_qr', false)
        ->set('layout', 'a4-21')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test('vouchers::settings')
        ->assertSet('footer_1', 'Help: 98765 43210')
        ->assertSet('footer_2', 'Ask the shop')
        ->assertSet('band_colour', '#166534')
        ->assertSet('show_price', false)
        ->assertSet('show_validity', false)
        ->assertSet('show_plan', false)
        ->assertSet('show_qr', false)
        ->assertSet('layout', 'a4-21');

    $card = CardSettings::current();
    expect($card->band)->toBe('#166534')
        ->and($card->footer)->toBe(['Help: 98765 43210', 'Ask the shop'])
        ->and($card->showPrice)->toBeFalse()
        ->and($card->showQr)->toBeFalse();
});

it('turns every field on for a panel that never saved them', function () {
    $card = CardSettings::current();

    expect($card->showPrice)->toBeTrue()
        ->and($card->showValidity)->toBeTrue()
        ->and($card->showPlan)->toBeTrue()
        ->and($card->showQr)->toBeTrue()
        ->and($card->layout)->toBe('a4-21')
        ->and($card->footer)->toBe([])
        ->and($card->band)->toBeNull()
        ->and($card->logoDataUri())->toBeNull();
});

it('keeps a PNG or a JPEG logo', function (string $name, string $mime) {
    Livewire::test('vouchers::settings')->set('logo', UploadedFile::fake()->image($name, 120, 60))->call('save')->assertHasNoErrors();

    expect(Storage::disk('addons')->files('vouchers'))->toHaveCount(1)
        ->and(CardSettings::current()->logoDataUri())->toStartWith('data:'.$mime.';base64,');
})->with([
    'png' => ['l.png', 'image/png'],
    'jpeg' => ['l.jpg', 'image/jpeg'],
]);

it('refuses an SVG, an HTML page named .png and a file over 1 MB', function (Closure $file) {
    Livewire::test('vouchers::settings')->set('logo', $file())->call('save')->assertHasErrors(['logo']);

    expect(Storage::disk('addons')->files('vouchers'))->toBe([])
        ->and(AppSetting::valueOf('vouchers.logo'))->toBeNull();
})->with([
    'svg' => [fn () => UploadedFile::fake()->createWithContent('l.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')],
    'html named png' => [fn () => UploadedFile::fake()->createWithContent('l.png', '<html><script>x</script></html>')],
    'over 1 MB' => [fn () => UploadedFile::fake()->image('big.png')->size(1025)],
]);

it('deletes the old logo when a new one is saved, and keeps it when the new one is refused', function () {
    Livewire::test('vouchers::settings')->set('logo', UploadedFile::fake()->image('a.png'))->call('save')->assertHasNoErrors();
    $first = AppSetting::valueOf('vouchers.logo');

    Livewire::test('vouchers::settings')->set('logo', UploadedFile::fake()->image('b.png'))->call('save')->assertHasNoErrors();
    $second = AppSetting::valueOf('vouchers.logo');

    expect($second)->not->toBe($first)
        ->and(Storage::disk('addons')->exists($first))->toBeFalse()
        ->and(Storage::disk('addons')->files('vouchers'))->toBe([$second]);

    Livewire::test('vouchers::settings')->set('logo', UploadedFile::fake()->createWithContent('c.png', '<html></html>'))->call('save')->assertHasErrors(['logo']);

    expect(AppSetting::valueOf('vouchers.logo'))->toBe($second)
        ->and(Storage::disk('addons')->exists($second))->toBeTrue();
});

it('removes the logo and its file', function () {
    Livewire::test('vouchers::settings')->set('logo', UploadedFile::fake()->image('a.png'))->call('save');
    $path = AppSetting::valueOf('vouchers.logo');

    Livewire::test('vouchers::settings')->call('removeLogo');

    expect(Storage::disk('addons')->exists($path))->toBeFalse()
        ->and(AppSetting::valueOf('vouchers.logo'))->toBeNull()
        ->and(CardSettings::current()->logoDataUri())->toBeNull();
});

it('shows no logo when its file is gone', function () {
    AppSetting::put('vouchers.logo', 'vouchers/missing.png');

    expect(CardSettings::current()->logoDataUri())->toBeNull();
    Livewire::test('vouchers::settings')->assertOk()->assertDontSee(__('Remove logo'));
});

it('refuses a colour that is not #rrggbb', function (string $colour) {
    Livewire::test('vouchers::settings')->set('band_colour', $colour)->call('save')->assertHasErrors(['band_colour']);
})->with(['red', '#12345', '#166534;}</style><script>']);

it('refuses a footer line over 40 characters', function (string $field) {
    Livewire::test('vouchers::settings')->set($field, str_repeat('a', 41))->call('save')->assertHasErrors([$field]);
})->with(['footer_1', 'footer_2']);

it('refuses a default layout that is not in the list', function () {
    Livewire::test('vouchers::settings')->set('layout', 'nope')->call('save')->assertHasErrors(['layout']);
});

it('lets only Generate and hand out vouchers save', function () {
    $this->actingAs(staffWithVoucherBoxes('print-vouchers'));

    Livewire::test('vouchers::settings')->assertForbidden();
});

it('picks black text on a light band and white on a dark one', function () {
    expect(CardSettings::textOn('#ffff00'))->toBe('#000000')
        ->and(CardSettings::textOn('#166534'))->toBe('#ffffff');
});
