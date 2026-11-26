<?php

namespace Ispgrid\HotspotVouchers\Support;

use App\Models\AppSetting;

/**
 * Money as the panel shows it, from the App settings currency symbol, decimals and grouping (Indian 1,00,000 or
 * international 100,000).
 */
final class Amount
{
    public static function format(string $amount): string
    {
        $decimals = max(0, min(3, (int) AppSetting::valueOf('currency_decimals', '2')));
        $fixed = number_format(abs((float) $amount), $decimals, '.', '');
        [$whole, $fraction] = array_pad(explode('.', $fixed, 2), 2, null);

        if (AppSetting::valueOf('number_grouping', 'international') === 'indian' && strlen($whole) > 3) {
            $whole = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($whole, 0, -3)).','.substr($whole, -3);
        } else {
            $whole = (string) preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole);
        }

        return ((float) $amount < 0 ? '-' : '').AppSetting::valueOf('currency_symbol', '').$whole.($fraction === null ? '' : '.'.$fraction);
    }

    /**
     * A stored amount as staff type it: "20.00", without grouping or symbol, so the form field can be prefilled.
     */
    public static function plain(string $amount): string
    {
        return number_format((float) $amount, max(0, min(3, (int) AppSetting::valueOf('currency_decimals', '2'))), '.', '');
    }

    /**
     * The pattern a typed amount must match: digits with up to the currency's decimals.
     */
    public static function pattern(): string
    {
        $decimals = max(0, min(3, (int) AppSetting::valueOf('currency_decimals', '2')));

        return $decimals === 0 ? '/^\d{1,9}$/' : '/^\d{1,9}(\.\d{1,'.$decimals.'})?$/';
    }
}
