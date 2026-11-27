<?php

use Illuminate\Support\Facades\Log;
use Ispgrid\HotspotVouchers\Support\CardLayouts;

it('lists registered layouts in order with their labels', function () {
    $layouts = new CardLayouts;
    $layouts->register('a4-21', fn (): string => 'A', 'vouchers::layouts.a4-21');
    $layouts->register('x-sticker', fn (): string => 'B', 'vouchers::layouts.a4-21');

    expect($layouts->labels())->toBe(['a4-21' => 'A', 'x-sticker' => 'B'])
        ->and($layouts->has('x-sticker'))->toBeTrue()
        ->and($layouts->has('nope'))->toBeFalse();
});

it('keeps the first layout when a key is registered twice', function () {
    Log::spy();
    $layouts = new CardLayouts;
    $layouts->register('a4-21', fn (): string => 'A', 'vouchers::layouts.a4-21');
    $layouts->register('a4-21', fn (): string => 'Other', 'other::view');

    expect($layouts->view('a4-21'))->toBe('vouchers::layouts.a4-21')
        ->and($layouts->labels())->toBe(['a4-21' => 'A']);
    Log::shouldHaveReceived('warning')->once();
});

it('ignores a key with capitals, spaces or slashes', function () {
    Log::spy();
    $layouts = new CardLayouts;

    foreach (['A4', 'a 4', 'a/4', ''] as $key) {
        $layouts->register($key, fn (): string => 'X', 'vouchers::layouts.a4-21');
    }

    expect($layouts->labels())->toBe([]);
    Log::shouldHaveReceived('warning')->times(4);
});

it('shows the key when a label cannot be made', function () {
    Log::spy();
    $layouts = new CardLayouts;
    $layouts->register('x-bad', fn (): string => throw new RuntimeException('broken add-on'), 'vouchers::layouts.a4-21');

    expect($layouts->labels()['x-bad'])->toBe('x-bad');
    Log::shouldHaveReceived('warning')->once();
});

it('falls back to a4-21 for an unknown key, no key, or a view that does not exist', function () {
    $layouts = new CardLayouts;
    $layouts->register('x-gone', fn (): string => 'Gone', 'nothing::here');

    expect($layouts->resolve('nope'))->toBe('a4-21')
        ->and($layouts->resolve(null))->toBe('a4-21')
        ->and($layouts->resolve('x-gone'))->toBe('a4-21')
        ->and(CardLayouts::DEFAULT)->toBe('a4-21');
});
