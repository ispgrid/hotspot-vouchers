<?php

namespace Ispgrid\HotspotVouchers\Http;

use App\Models\AppSetting;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Ispgrid\HotspotVouchers\Models\Batch;
use Ispgrid\HotspotVouchers\Support\Amount;
use Ispgrid\HotspotVouchers\Support\CardLayouts;
use Ispgrid\HotspotVouchers\Support\Cards;
use Ispgrid\HotspotVouchers\Support\CardSettings;
use Ispgrid\HotspotVouchers\Support\CardSheet;
use Ispgrid\HotspotVouchers\Support\CardStatus;
use Ispgrid\HotspotVouchers\Support\CsvCell;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The print page (printed from the browser, in the layout picked under Print cards) and the CSV export of a batch.
 * Both show the codes, so both need "Print and export vouchers", and both are recorded in the Activity log.
 */
final class CardsController
{
    public function print(Request $request, CardLayouts $layouts, int $batch): View
    {
        Gate::authorize('print-vouchers');
        $model = Batch::query()->with('shop')->findOrFail($batch);
        $only = $request->query('cards') === 'unused' ? ['new', 'unused', 'sold'] : null;
        $cards = array_values(array_filter(Cards::of($model), fn (array $card): bool => $card['status'] !== 'deleted'
            && ($only === null || in_array($card['status'], $only, true))));
        $design = CardSettings::current();
        $asked = $request->query('layout');
        $layout = $layouts->resolve(is_string($asked) ? $asked : $design->layout);
        $loginUrl = $design->showQr ? (string) AppSetting::valueOf('vouchers.login_url', '') : '';

        activity()->event('voucher-cards-printed')->withProperties(['batch' => $model->id, 'cards' => count($cards), 'layout' => $layout])->log('voucher-cards-printed');

        return view($layouts->view($layout), ['sheet' => new CardSheet(
            batchId: $model->id,
            title: $design->title,
            logo: $design->logoDataUri(),
            footer: $design->footer,
            band: $design->band,
            bandText: $design->band === null ? null : CardSettings::textOn($design->band),
            showPrice: $design->showPrice,
            showValidity: $design->showValidity,
            showPlan: $design->showPlan,
            showQr: $design->showQr,
            planName: (string) $model->plan_name,
            price: Amount::format((string) $model->card_price),
            validity: Cards::validity($model),
            pin: $model->login_style === Batch::PIN,
            cards: array_map(fn (array $card): array => [
                'code' => $card['code'],
                'password' => $card['password'],
                'qr' => self::qr($loginUrl, $card['code'], $card['password'] ?? $card['code']),
            ], $cards),
        )]);
    }

    public function csv(int $batch): StreamedResponse
    {
        Gate::authorize('print-vouchers');
        $model = Batch::query()->findOrFail($batch);
        $cards = Cards::of($model);

        activity()->event('voucher-cards-exported')->withProperties(['batch' => $model->id, 'cards' => count($cards)])->log('voucher-cards-exported');

        $header = [__('Username'), __('Password'), __('Plan'), __('Validity'), __('Price'), __('Batch'), __('Status')];
        $validity = Cards::validity($model);

        return response()->streamDownload(function () use ($cards, $header, $model, $validity): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fputcsv($output, $header, escape: '');

            foreach ($cards as $card) {
                fputcsv($output, array_map(CsvCell::safe(...), [
                    $card['code'], $card['password'] ?? $card['code'], $model->plan_name, $validity,
                    (string) $model->card_price, (string) $model->id, CardStatus::label($card['status']),
                ]), escape: '');
            }

            fclose($output);
        }, 'vouchers-batch-'.$model->id.'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * An SVG QR code that logs the card in on the Hotspot login page (MikroTik reads username and password from the
     * address), or null without a login address or without the QR library core ships for two-factor login.
     */
    private static function qr(string $loginUrl, string $username, string $password): ?string
    {
        if ($loginUrl === '' || ! class_exists(Writer::class)) {
            return null;
        }

        $url = $loginUrl.(str_contains($loginUrl, '?') ? '&' : '?').http_build_query(['username' => $username, 'password' => $password]);
        $svg = (new Writer(new ImageRenderer(new RendererStyle(120, 0), new SvgImageBackEnd)))->writeString($url);

        // Drop the XML declaration so the SVG can sit inside the page.
        return (string) preg_replace('/^<\?xml[^>]*>\s*/', '', $svg);
    }
}
