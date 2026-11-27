<?php

namespace Ispgrid\HotspotVouchers;

use App\Actions\Customers\CreateAccount;
use App\Models\User;
use App\Support\ActivityFormatter;
use App\Support\Addons\AddonSettings;
use App\Support\Navigation\Navigation;
use App\Support\Navigation\NavItem;
use App\Support\Permissions;
use Illuminate\Support\ServiceProvider;
use Ispgrid\HotspotVouchers\Support\CardLayouts;
use Livewire\Livewire;

/**
 * Hotspot vouchers: batches of prepaid Hotspot cards (connection "vouchers" for its own tables), printed, handed to
 * shops or sold over the counter. Needs core 0.1.27 (CreateAccount and friends); on an older core it only registers
 * its permission boxes, so roles holding them still save.
 */
class HotspotVouchersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/vouchers.php', 'vouchers');

        /** @var array<string, mixed> $base */
        $base = config('database.connections.mysql');
        $own = array_filter((array) config('vouchers.database', []), fn (mixed $value): bool => $value !== null && $value !== '');
        $default = $this->app->environment('testing') ? 'ispgrid_vouchers_test' : 'ispgrid_vouchers';

        config(['database.connections.vouchers' => array_merge($base, ['database' => $default], $own)]);

        $this->app->singleton(CardLayouts::class);
    }

    public function boot(): void
    {
        // The database and the permission rows come on every core, so the boxes below can be ticked on Roles.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');

        $permissions = $this->app->make(Permissions::class);
        $permissions->register('generate-vouchers', fn (): string => __('Generate and hand out vouchers'), fn (): string => __('Vouchers'));
        $permissions->register('print-vouchers', fn (): string => __('Print and export vouchers'), fn (): string => __('Vouchers'));
        $permissions->register('delete-voucher-batches', fn (): string => __('Delete voucher batches'), fn (): string => __('Vouchers'), 'generate-vouchers');

        if (! class_exists(CreateAccount::class) || ! class_exists(AddonSettings::class)) {
            return;
        }

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'vouchers');
        Livewire::addNamespace('vouchers', viewPath: __DIR__.'/../resources/views/livewire');

        $this->app->make(AddonSettings::class)->register('ispgrid/hotspot-vouchers', 'vouchers::settings', 'generate-vouchers');

        $this->app->make(Navigation::class)->add(new NavItem(
            group: 'addons',
            label: fn (): string => __('Vouchers'),
            route: 'vouchers.index',
            icon: 'qr-code',
            active: ['vouchers.*'],
            visible: fn (User $user): bool => $user->canAny(['generate-vouchers', 'print-vouchers']),
            order: 20,
        ));

        foreach ([
            'voucher-batch-created' => fn (): string => __('Voucher batch created'),
            'voucher-batch-handed-out' => fn (): string => __('Voucher batch handed out'),
            'voucher-batch-switched-off' => fn (): string => __('Voucher batch switched off'),
            'voucher-batch-switched-on' => fn (): string => __('Voucher batch switched on'),
            'voucher-batch-deleted' => fn (): string => __('Voucher batch deleted'),
            'voucher-cards-printed' => fn (): string => __('Voucher cards printed'),
            'voucher-cards-exported' => fn (): string => __('Voucher cards exported'),
            'voucher-shop-payment' => fn (): string => __('Voucher shop payment recorded'),
        ] as $event => $label) {
            ActivityFormatter::addEventLabel($event, $label);
        }
    }
}
