<?php

namespace Ispgrid\HotspotVouchers\Support;

use App\Models\Account;
use Ispgrid\HotspotVouchers\Models\Batch;
use Ispgrid\HotspotVouchers\Models\Voucher;

/**
 * What a card is now, read from its batch and its Hotspot customer: new (its batch is not handed out yet), unused,
 * sold, used (logged in once), expired, off (its batch is switched off) or deleted.
 */
final class CardStatus
{
    public const KEYS = ['new', 'unused', 'sold', 'used', 'expired', 'off', 'deleted'];

    public static function of(Voucher $voucher, Batch $batch, ?Account $account): string
    {
        return match (true) {
            $account === null || $account->trashed() => 'deleted',
            $account->expiry !== null && $account->expiry->isPast() => 'expired',
            $account->hotspot_first_login_at !== null => 'used',
            $batch->state() === 'new' => 'new',
            $batch->state() === 'off' => 'off',
            $voucher->sold_at !== null => 'sold',
            default => 'unused',
        };
    }

    public static function label(string $status): string
    {
        return match ($status) {
            'new' => __('Not handed out'),
            'unused' => __('Unused'),
            'sold' => __('Sold'),
            'used' => __('Used'),
            'expired' => __('Expired'),
            'off' => __('Switched off'),
            default => __('Deleted'),
        };
    }

    public static function color(string $status): string
    {
        return match ($status) {
            'unused' => 'green',
            'sold' => 'blue',
            'used' => 'amber',
            'expired', 'off', 'deleted' => 'red',
            default => 'zinc',
        };
    }
}
