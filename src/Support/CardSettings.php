<?php

namespace Ispgrid\HotspotVouchers\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Storage;

/**
 * The card design saved on System, then Add-ons: it applies to every batch. A switch that was never saved is on, so
 * a panel that changes nothing prints the card it always printed.
 */
final readonly class CardSettings
{
    /**
     * @param  list<string>  $footer  the footer lines that are not empty
     */
    public function __construct(
        public string $title,
        public ?string $logoPath,
        public array $footer,
        public ?string $band,
        public bool $showPrice,
        public bool $showValidity,
        public bool $showPlan,
        public bool $showQr,
        public string $layout,
    ) {}

    public static function current(): self
    {
        $band = strtolower((string) AppSetting::valueOf('vouchers.band_colour'));
        $footer = array_map(fn (string $key): string => trim((string) AppSetting::valueOf($key)), ['vouchers.footer_1', 'vouchers.footer_2']);

        return new self(
            title: (string) (AppSetting::valueOf('vouchers.card_title') ?: config('app.name')),
            logoPath: AppSetting::valueOf('vouchers.logo') ?: null,
            footer: array_values(array_filter($footer, fn (string $line): bool => $line !== '')),
            // Checked again here: the colour goes into the page's styles, where escaping does not help.
            band: preg_match('/^#[0-9a-f]{6}$/', $band) === 1 ? $band : null,
            showPrice: AppSetting::valueOf('vouchers.show_price') !== '0',
            showValidity: AppSetting::valueOf('vouchers.show_validity') !== '0',
            showPlan: AppSetting::valueOf('vouchers.show_plan') !== '0',
            showQr: AppSetting::valueOf('vouchers.show_qr') !== '0',
            layout: (string) (AppSetting::valueOf('vouchers.layout') ?: CardLayouts::DEFAULT),
        );
    }

    /**
     * The logo as a data URI for the page, or null when none is set or its file is gone (a database restored without
     * its files). Only PNG and JPEG are ever returned.
     */
    public function logoDataUri(): ?string
    {
        $disk = Storage::disk('addons');

        if ($this->logoPath === null || ! $disk->exists($this->logoPath)) {
            return null;
        }

        $bytes = (string) $disk->get($this->logoPath);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

        return in_array($mime, ['image/png', 'image/jpeg'], true) ? 'data:'.$mime.';base64,'.base64_encode($bytes) : null;
    }

    /**
     * Black or white, whichever reads better on the colour (WCAG contrast).
     */
    public static function textOn(string $hex): string
    {
        $channels = array_map(function (string $pair): float {
            $value = hexdec($pair) / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, str_split(ltrim($hex, '#'), 2));
        $luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];

        return ($luminance + 0.05) / 0.05 >= 1.05 / ($luminance + 0.05) ? '#000000' : '#ffffff';
    }
}
