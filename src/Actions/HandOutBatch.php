<?php

namespace Ispgrid\HotspotVouchers\Actions;

use App\Actions\Customers\RechargeAccount;
use App\Models\Account;
use Illuminate\Support\Facades\DB;
use Ispgrid\HotspotVouchers\Models\Batch;
use Ispgrid\HotspotVouchers\Models\Shop;
use Ispgrid\HotspotVouchers\Models\ShopEntry;
use Ispgrid\HotspotVouchers\Models\Voucher;
use Ispgrid\HotspotVouchers\Support\CannotChangeBatch;

/**
 * Hands a batch to a shop, or keeps it for counter sale: every card is recharged with the batch's plan (core's
 * RechargeAccount; its validity waits for the first login), so it works from now on. Handed to a shop, every card
 * counts as sold and the shop is charged its price per card.
 * The batch row stays locked while the cards are recharged, so a second click waits and then finds it handed out.
 * A card whose customer already has a plan (an earlier attempt that failed half way) is not recharged again.
 */
final class HandOutBatch
{
    public function __construct(private RechargeAccount $recharge) {}

    /**
     * @param  ?string  $shopPrice  the shop's price per card; ignored for counter sale
     *
     * @throws CannotChangeBatch when the batch was handed out meanwhile
     */
    public function handle(int $batchId, ?int $shopId, ?string $shopPrice, ?int $userId): void
    {
        DB::connection('vouchers')->transaction(function () use ($batchId, $shopId, $shopPrice, $userId): void {
            $batch = Batch::query()->lockForUpdate()->findOrFail($batchId);

            if ($batch->handed_out_at !== null) {
                throw new CannotChangeBatch(__('This batch was handed out meanwhile.'));
            }

            $shop = $shopId === null ? null : Shop::query()->findOrFail($shopId);
            $vouchers = $batch->vouchers()->get();
            $accounts = Account::query()->whereIn('id', $vouchers->pluck('account_id'))->get()->keyBy('id');

            foreach ($vouchers as $voucher) {
                $account = $accounts->get($voucher->account_id);

                if ($account !== null && $account->plan_id === null) {
                    $this->recharge->handle($account->id, $batch->plan_id, null, '0', 'Voucher batch '.$batch->id);
                }
            }

            $batch->update(['handed_out_at' => now(), 'shop_id' => $shop?->id, 'shop_price' => $shop === null ? null : $shopPrice]);

            if ($shop !== null) {
                Voucher::query()->where('batch_id', $batch->id)->whereNull('sold_at')->update(['sold_at' => now()]);
                ShopEntry::query()->create([
                    'shop_id' => $shop->id,
                    'kind' => ShopEntry::CHARGE,
                    'amount' => bcmul(is_numeric($shopPrice) ? $shopPrice : '0', (string) $vouchers->count(), 3),
                    'batch_id' => $batch->id,
                    'entry_date' => now()->toDateString(),
                    'note' => null,
                    'user_id' => $userId,
                ]);
            }

            activity()->event('voucher-batch-handed-out')
                ->withProperties(['batch' => $batch->id, 'shop' => $shop?->name, 'cards' => $vouchers->count()])
                ->log('voucher-batch-handed-out');
        });
    }
}
