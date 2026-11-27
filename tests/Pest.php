<?php

use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Ispgrid\HotspotVouchers\Actions\GenerateBatch;
use Ispgrid\HotspotVouchers\Models\Batch;
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

/**
 * A batch made the way the New batch form makes it.
 *
 * @param  array<string, mixed>  $input
 */
function voucherBatch(int $count = 3, array $input = []): Batch
{
    $plan = Plan::factory()->hours(3)->firstLogin()->create(['name' => 'Hotspot 3h', 'price' => 20]);

    return app(GenerateBatch::class)->handle([
        'plan_id' => $plan->id, 'count' => $count, 'card_price' => '20', 'login_style' => Batch::PIN,
        'code_length' => 8, 'prefix' => null, 'devices' => 1, 'note' => null, ...$input,
    ], auth()->id());
}

function staffWithVoucherBoxes(string ...$boxes): User
{
    $role = Role::create(['name' => 'Desk '.count($boxes).random_int(1, 99999), 'guard_name' => 'web']);
    $role->givePermissionTo($boxes);

    return User::factory()->role($role->name)->create();
}
