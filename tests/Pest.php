<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
| Run from the core checkout:
|   ISPGRID_ADDONS=on php vendor/bin/pest --test-directory=vendor/ispgrid/hotspot-vouchers/tests vendor/ispgrid/hotspot-vouchers/tests
| The vouchers database is its own connection, outside RefreshDatabase's transaction: it is emptied before each test.
*/
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => vouchersReset())
    ->in(__DIR__);

function vouchersReset(): void
{
    foreach (['vouchers', 'voucher_batches', 'voucher_shops', 'voucher_shop_entries'] as $table) {
        DB::connection('vouchers')->table($table)->truncate();
    }
}
