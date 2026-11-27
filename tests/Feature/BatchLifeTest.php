<?php

use App\Actions\Customers\RechargeAccount;
use App\Enums\AccountStatus;
use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Ispgrid\HotspotVouchers\Actions\HandOutBatch;
use Ispgrid\HotspotVouchers\Models\Batch;
use Ispgrid\HotspotVouchers\Models\Shop;
use Ispgrid\HotspotVouchers\Models\ShopEntry;
use Ispgrid\HotspotVouchers\Models\Voucher;
use Ispgrid\HotspotVouchers\Support\CannotChangeBatch;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function batchPage(Batch $batch): Testable
{
    return Livewire::test('vouchers::batch', ['batch' => $batch->id]);
}

/**
 * @return Collection<int, Account>
 */
function batchAccounts(Batch $batch): Collection
{
    return Account::withTrashed()->whereIn('id', Voucher::query()->where('batch_id', $batch->id)->pluck('account_id'))->get();
}

it('hands a batch to a shop: the cards work, count as sold, and the shop is charged', function () {
    $batch = voucherBatch(3);
    $shop = Shop::query()->create(['name' => 'Gupta Mobiles']);

    batchPage($batch)->set('shop_id', (string) $shop->id)->set('shop_price', '15')->call('handOut')
        ->assertHasNoErrors()->assertDispatched('toast', variant: 'success');

    $accounts = batchAccounts($batch);

    expect($batch->fresh()->state())->toBe('on')
        ->and($accounts->every(fn (Account $account): bool => $account->status_id === AccountStatus::Active
            && $account->hotspot_pending_validity === 180 && $account->expiry === null))->toBeTrue()
        ->and(DB::table('radcheck')->where(['username' => $accounts->first()->username, 'attribute' => 'Hotspot-First-Login-Validity'])->value('value'))->toBe('180')
        ->and(Voucher::query()->whereNull('sold_at')->count())->toBe(0)
        ->and(ShopEntry::query()->sole())->kind->toBe('charge')->amount->toBe('45.000')->batch_id->toBe($batch->id)
        ->and($shop->balance())->toBe(['charged' => '45.000', 'paid' => '0.000', 'due' => '45.000'])
        ->and(Activity::query()->where('event', 'voucher-batch-handed-out')->count())->toBe(1);
});

it('keeps a batch for counter sale without charging anyone', function () {
    $batch = voucherBatch(2);

    batchPage($batch)->set('shop_id', '')->call('handOut')->assertHasNoErrors();

    expect($batch->fresh()->soldThrough())->toBe(__('Counter sale'))
        ->and(ShopEntry::query()->count())->toBe(0)
        ->and(Voucher::query()->whereNotNull('sold_at')->count())->toBe(0)
        ->and(batchAccounts($batch)->every(fn (Account $account): bool => $account->status_id === AccountStatus::Active))->toBeTrue();
});

it('hands a batch out only once', function () {
    $batch = voucherBatch(1);
    app(HandOutBatch::class)->handle($batch->id, null, null, null);

    expect(fn () => app(HandOutBatch::class)->handle($batch->id, null, null, null))->toThrow(CannotChangeBatch::class)
        ->and(batchAccounts($batch)->first()->hotspot_pending_validity)->toBe(180);
});

it('does not recharge a card again when a hand out is retried', function () {
    $batch = voucherBatch(2);
    $first = batchAccounts($batch)->first();
    // An earlier attempt recharged one card before it failed.
    app(RechargeAccount::class)->handle($first->id, $batch->plan_id, null, '0', null);

    app(HandOutBatch::class)->handle($batch->id, null, null, null);

    expect($first->fresh()->hotspot_pending_validity)->toBe(180);
});

it('switches a batch off and on again', function () {
    $batch = voucherBatch(2);
    app(HandOutBatch::class)->handle($batch->id, null, null, null);

    batchPage($batch)->call('switchOff')->assertDispatched('toast', variant: 'success');

    expect($batch->fresh()->state())->toBe('off')
        ->and(batchAccounts($batch)->every(fn (Account $account): bool => $account->status_id === AccountStatus::Suspended))->toBeTrue();

    batchPage($batch)->call('switchOn');

    expect($batch->fresh()->state())->toBe('on')
        ->and(batchAccounts($batch)->every(fn (Account $account): bool => $account->status_id === AccountStatus::Active))->toBeTrue();
});

it('cannot switch a batch that is not handed out', function () {
    $batch = voucherBatch(1);

    batchPage($batch)->call('switchOff')->assertDispatched('toast', variant: 'danger');

    expect(batchAccounts($batch)->first()->status_id)->toBe(AccountStatus::Inactive);
});

it('marks counter-sale cards sold one by one', function () {
    $batch = voucherBatch(2);
    app(HandOutBatch::class)->handle($batch->id, null, null, null);
    $voucher = Voucher::query()->where('batch_id', $batch->id)->first();

    batchPage($batch)->call('markSold', $voucher->id);

    expect($voucher->fresh()->sold_at)->not->toBeNull();

    batchPage($batch)->call('markSold', $voucher->id, false);

    expect($voucher->fresh()->sold_at)->toBeNull();
});

it('counts a used counter-sale card as sold, in the takings and in the batch list', function () {
    $batch = voucherBatch(2);
    app(HandOutBatch::class)->handle($batch->id, null, null, null);
    batchAccounts($batch)->first()->forceFill(['hotspot_first_login_at' => now(), 'expiry' => now()->addHours(3), 'hotspot_pending_validity' => null])->saveQuietly();

    expect(batchPage($batch)->instance()->takings)->toBe('20.00')
        ->and(Livewire::test('vouchers::batches')->instance()->cardCounts)->toBe(['sold' => [$batch->id => 1], 'used' => [$batch->id => 1]]);
});

it('shows a used card as used', function () {
    $batch = voucherBatch(1);
    app(HandOutBatch::class)->handle($batch->id, null, null, null);
    batchAccounts($batch)->first()->forceFill(['hotspot_first_login_at' => now(), 'expiry' => now()->addHours(3), 'hotspot_pending_validity' => null])->saveQuietly();

    batchPage($batch)->assertSee(__('Used'))->set('status', 'used')->assertSee(Voucher::query()->sole()->code);
});

it('deletes an unused batch with its cards and its charge', function () {
    $batch = voucherBatch(2);
    $shop = Shop::query()->create(['name' => 'Gupta Mobiles']);
    app(HandOutBatch::class)->handle($batch->id, $shop->id, '15', null);

    batchPage($batch)->call('delete')->assertRedirect(route('vouchers.index'));

    expect(Batch::query()->count())->toBe(0)
        ->and(Voucher::query()->count())->toBe(0)
        ->and(ShopEntry::query()->count())->toBe(0)
        ->and(batchAccounts($batch))->toHaveCount(0)
        ->and(Account::onlyTrashed()->where('source', 'hotspot-vouchers')->count())->toBe(2)
        ->and(DB::table('radcheck')->whereIn('username', Account::onlyTrashed()->pluck('username'))->count())->toBe(0);
});

it('keeps a batch one of whose cards was used', function () {
    $batch = voucherBatch(2);
    app(HandOutBatch::class)->handle($batch->id, null, null, null);
    batchAccounts($batch)->first()->forceFill(['hotspot_first_login_at' => now()])->saveQuietly();

    batchPage($batch)->assertDontSee(__('Delete batch'))->call('delete')->assertDispatched('toast', variant: 'danger');

    expect(Batch::query()->count())->toBe(1)->and(Account::query()->where('source', 'hotspot-vouchers')->count())->toBe(2);
});

it('lets only Delete voucher batches delete', function () {
    $batch = voucherBatch(1);
    $this->actingAs(staffWithVoucherBoxes('generate-vouchers'));

    batchPage($batch)->assertDontSee(__('Delete batch'))->call('delete')->assertForbidden();
});

it('lets only Generate and hand out vouchers hand out', function () {
    $batch = voucherBatch(1);
    $this->actingAs(staffWithVoucherBoxes('print-vouchers'));

    batchPage($batch)->assertDontSee(__('Hand out'))->call('handOut')->assertForbidden();

    expect($batch->fresh()->handed_out_at)->toBeNull();
});
