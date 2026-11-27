<?php

namespace Ispgrid\HotspotVouchers\Support;

use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The card layouts the batch page offers under Print cards. A layout is a Blade view that gets one CardSheet as
 * `$sheet` and draws a whole printable page. The built-in layouts register here like any other add-on's would, so a
 * broken add-on layout can only break its own print page: a label that throws shows its key, a view that is gone
 * prints the default layout instead.
 */
final class CardLayouts
{
    public const DEFAULT = 'a4-21';

    /** @var array<string, array{label: Closure, view: string}> */
    private array $layouts = [];

    /**
     * Adds a layout. The key is lowercase letters, digits and "-"; the label is a closure so it is translated per
     * request; the view is a Blade view name such as "my-addon::sticker". A key that is taken is ignored (first wins).
     */
    public function register(string $key, Closure $label, string $view): void
    {
        if (preg_match('/^[a-z0-9-]+$/', $key) !== 1) {
            Log::warning('Voucher card layout ignored: the key must be lowercase letters, digits and "-".', ['key' => $key]);

            return;
        }

        if (isset($this->layouts[$key])) {
            Log::warning('Voucher card layout ignored: the key is already taken.', ['key' => $key, 'view' => $view]);

            return;
        }

        $this->layouts[$key] = ['label' => $label, 'view' => $view];
    }

    /**
     * Every layout's translated label, in the order they were registered.
     *
     * @return array<string, string>
     */
    public function labels(): array
    {
        $labels = [];

        foreach ($this->layouts as $key => $layout) {
            try {
                $labels[$key] = (string) ($layout['label'])();
            } catch (Throwable $e) {
                Log::warning('Voucher card layout label failed.', ['key' => $key, 'error' => $e->getMessage()]);
                $labels[$key] = $key;
            }
        }

        return $labels;
    }

    public function has(string $key): bool
    {
        return isset($this->layouts[$key]);
    }

    /**
     * The key to print with: the one asked for when it is registered and its view exists, otherwise the default.
     */
    public function resolve(?string $key): string
    {
        if ($key !== null && isset($this->layouts[$key]) && view()->exists($this->layouts[$key]['view'])) {
            return $key;
        }

        return self::DEFAULT;
    }

    /**
     * The view of a key that resolve() returned.
     */
    public function view(string $key): string
    {
        return $this->layouts[$key]['view'] ?? 'vouchers::layouts.'.self::DEFAULT;
    }
}
