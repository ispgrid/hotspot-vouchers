<?php

namespace Ispgrid\HotspotVouchers\Support;

/**
 * Random card codes from capital letters and digits, without the ones people misread on a printed card
 * (0 and O, 1 and I, and L).
 */
final class Codes
{
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const MIN_LENGTH = 6;

    public const MAX_LENGTH = 12;

    public static function make(int $length): string
    {
        $last = strlen(self::ALPHABET) - 1;
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= self::ALPHABET[random_int(0, $last)];
        }

        return $code;
    }
}
