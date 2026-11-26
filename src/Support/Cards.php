<?php

namespace Ispgrid\HotspotVouchers\Support;

use App\Models\Account;
use App\Models\Plan;
use Ispgrid\HotspotVouchers\Models\Batch;
use Ispgrid\HotspotVouchers\Models\Voucher;

/**
 * A batch's cards as staff see them, print them and export them: login, password (only for username plus password
 * cards), status and the customer behind each.
 */
final class Cards
{
    /**
     * @return list<array{id: int, code: string, password: ?string, status: string, account_id: int, sold_at: ?string, first_login: ?string, expiry: ?string}>
     */
    public static function of(Batch $batch): array
    {
        $vouchers = Voucher::query()->where('batch_id', $batch->id)->orderBy('id')->get();
        $accounts = Account::withTrashed()->whereIn('id', $vouchers->pluck('account_id'))->get()->keyBy('id');
        $rows = [];

        foreach ($vouchers as $voucher) {
            $account = $accounts->get($voucher->account_id);
            $rows[] = [
                'id' => $voucher->id,
                'code' => $voucher->code,
                'password' => $batch->login_style === Batch::PASSWORD ? $account?->password : null,
                'status' => CardStatus::of($voucher, $batch, $account),
                'account_id' => $voucher->account_id,
                'sold_at' => $voucher->sold_at?->format('Y-m-d H:i'),
                'first_login' => $account?->hotspot_first_login_at?->format('Y-m-d H:i'),
                'expiry' => $account?->expiry?->format('Y-m-d H:i'),
            ];
        }

        return $rows;
    }

    /**
     * How long a card lasts, as printed on it: "3 hours from first login", plus the time limit when the plan has one.
     */
    public static function validity(Batch $batch): string
    {
        $plan = Plan::withTrashed()->find($batch->plan_id);

        if ($plan === null) {
            return '';
        }

        $text = __(':validity from first login', ['validity' => $plan->validity_unit->count($plan->validity)]);

        return $plan->time_limit_hours === null ? $text : $text.', '.__(':hours hours of use', ['hours' => $plan->time_limit_hours]);
    }
}
