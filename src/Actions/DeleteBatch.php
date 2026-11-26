<?php

namespace Ispgrid\HotspotVouchers\Actions;

use App\Actions\Customers\DeleteAccount;
use App\Models\Account;
use Illuminate\Support\Facades\DB;
use Ispgrid\HotspotVouchers\Models\Batch;
use Ispgrid\HotspotVouchers\Models\ShopEntry;
use Ispgrid\HotspotVouchers\Models\Voucher;
use Ispgrid\HotspotVouchers\Support\CannotChangeBatch;

/**
 * Deletes a batch none of whose cards was used: its customers (core's DeleteAccount), its cards and, when it was
 * handed to a shop, the shop's charge for it. A batch with a used card stays, as a record of what was sold.
 */
final class DeleteBatch
{
    public function __construct(private DeleteAccount $deleteAccount) {}

    /**
     * @throws CannotChangeBatch when a card was used
     */
    public function handle(int $batchId): void
    {
        DB::connection('vouchers')->transaction(function () use ($batchId): void {
            $batch = Batch::query()->lockForUpdate()->findOrFail($batchId);
            $accountIds = $batch->vouchers()->pluck('account_id');

            if (Account::withTrashed()->whereIn('id', $accountIds)->whereNotNull('hotspot_first_login_at')->exists()) {
                throw new CannotChangeBatch(__('A card of this batch was used, so the batch stays.'));
            }

            foreach (Account::query()->whereIn('id', $accountIds)->pluck('id') as $accountId) {
                $this->deleteAccount->handle($accountId);
            }

            Voucher::query()->where('batch_id', $batch->id)->delete();
            ShopEntry::query()->where('batch_id', $batch->id)->delete();
            $batch->delete();

            activity()->event('voucher-batch-deleted')
                ->withProperties(['batch' => $batch->id, 'cards' => $accountIds->count(), 'plan' => $batch->plan_name])
                ->log('voucher-batch-deleted');
        });
    }
}
