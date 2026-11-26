<?php

namespace Ispgrid\HotspotVouchers\Actions;

use App\Actions\Customers\SetAccountStatus;
use App\Enums\AccountStatus;
use App\Enums\DisconnectReason;
use App\Jobs\DisconnectAccount;
use App\Models\Account;
use Illuminate\Support\Facades\DB;
use Ispgrid\HotspotVouchers\Models\Batch;
use Ispgrid\HotspotVouchers\Support\CannotChangeBatch;

/**
 * Switches a handed-out batch off (every card Suspended, and disconnected if online) or on again (Active). Cards
 * already used switch with it: a card someone is using stops working while the batch is off.
 */
final class SwitchBatch
{
    public function __construct(private SetAccountStatus $setStatus) {}

    /**
     * @throws CannotChangeBatch when the batch is not handed out, or already in that state
     */
    public function handle(int $batchId, bool $on): void
    {
        $disconnect = DB::connection('vouchers')->transaction(function () use ($batchId, $on): array {
            $batch = Batch::query()->lockForUpdate()->findOrFail($batchId);

            if ($batch->handed_out_at === null || ($batch->switched_off_at === null) === $on) {
                throw new CannotChangeBatch(__('This batch changed meanwhile. Reload the page.'));
            }

            $status = $on ? AccountStatus::Active : AccountStatus::Suspended;
            $remark = $on ? 'Voucher batch '.$batch->id.' switched on' : 'Voucher batch '.$batch->id.' switched off';
            $disconnect = [];

            foreach (Account::query()->whereIn('id', $batch->vouchers()->pluck('account_id'))->get() as $account) {
                if ($this->setStatus->handle($account->id, $status, $remark)) {
                    $disconnect[] = $account->username;
                }
            }

            $batch->update(['switched_off_at' => $on ? null : now()]);

            activity()->event($on ? 'voucher-batch-switched-on' : 'voucher-batch-switched-off')
                ->withProperties(['batch' => $batch->id])
                ->log($on ? 'voucher-batch-switched-on' : 'voucher-batch-switched-off');

            return $disconnect;
        });

        foreach ($disconnect as $username) {
            DisconnectAccount::dispatchIfOnline($username, DisconnectReason::StatusChanged);
        }
    }
}
