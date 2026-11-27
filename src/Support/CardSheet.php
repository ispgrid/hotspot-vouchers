<?php

namespace Ispgrid\HotspotVouchers\Support;

/**
 * Everything a card layout draws, passed to its view as `$sheet`. All of it is plain text to print with `{{ }}`,
 * except each card's `qr`, an SVG this add-on makes itself (null when QR codes are off or there is no login address).
 * The logo is a data URI or null; put it in the page once (a CSS class), not once per card.
 */
final readonly class CardSheet
{
    /**
     * @param  list<string>  $footer
     * @param  list<array{code: string, password: ?string, qr: ?string}>  $cards
     */
    public function __construct(
        public int $batchId,
        public string $title,
        public ?string $logo,
        public array $footer,
        public ?string $band,
        public ?string $bandText,
        public bool $showPrice,
        public bool $showValidity,
        public bool $showPlan,
        public bool $showQr,
        public string $planName,
        public string $price,
        public string $validity,
        public bool $pin,
        public array $cards,
    ) {}
}
