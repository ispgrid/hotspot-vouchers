<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * The vouchers database and its tables, created only when missing (down() never drops them: they hold what shops
 * owe), and the add-on's permission boxes. No role gets the boxes here: Admin has every box, and operators tick the
 * rest on Roles.
 */
return new class extends Migration
{
    public function up(): void
    {
        $database = config('database.connections.vouchers.database');
        DB::statement("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        DB::purge('vouchers');
        $schema = Schema::connection('vouchers');

        if (! $schema->hasTable('voucher_shops')) {
            $schema->create('voucher_shops', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique();
                $table->string('phone', 20)->nullable();
                $table->string('note', 255)->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('voucher_batches')) {
            $schema->create('voucher_batches', function (Blueprint $table) {
                $table->id();
                // Core's plans.id; the name is kept so the batch still reads right if the plan is renamed or deleted.
                $table->unsignedBigInteger('plan_id');
                $table->string('plan_name', 100);
                $table->decimal('card_price', 12, 3);
                $table->string('login_style', 10);
                $table->unsignedTinyInteger('code_length');
                $table->string('prefix', 6)->nullable();
                $table->unsignedTinyInteger('devices');
                $table->string('note', 255)->nullable();
                $table->unsignedBigInteger('shop_id')->nullable()->index();
                $table->decimal('shop_price', 12, 3)->nullable();
                $table->timestamp('handed_out_at')->nullable();
                $table->timestamp('switched_off_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('vouchers')) {
            $schema->create('vouchers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('batch_id')->index();
                // Core's accounts.id: every card is a Hotspot customer.
                $table->unsignedBigInteger('account_id')->unique();
                $table->string('code', 64)->unique();
                $table->timestamp('sold_at')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('voucher_shop_entries')) {
            $schema->create('voucher_shop_entries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('shop_id')->index();
                // charge (a batch handed out) or payment (money the shop paid).
                $table->string('kind', 10);
                $table->decimal('amount', 12, 3);
                $table->unsignedBigInteger('batch_id')->nullable()->index();
                $table->date('entry_date');
                $table->string('note', 255)->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
        }

        $now = now();

        foreach (['generate-vouchers', 'print-vouchers', 'delete-voucher-batches'] as $name) {
            DB::table('permissions')->insertOrIgnore(['name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Never: the tables hold what shops owe. --delete-data drops the database instead.
    }
};
