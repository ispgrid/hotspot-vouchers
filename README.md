# Hotspot vouchers for ISPGrid

Batches of prepaid Hotspot login cards for [ISPGrid](https://github.com/ispgrid/ispgrid): make a batch, print the
cards, hand the batch to a shop or keep it for counter sale, and see which cards were sold and used.

Needs ISPGrid 0.1.27 or newer, with Hotspot switched on in App settings (while it is off, FreeRADIUS refuses every
Hotspot login, cards included). On an older core it only adds its permission boxes.

## How it works

- **A card is a Hotspot customer.** Each card is a normal ISPGrid Hotspot customer made by core, so device limits,
  usage limits, the login log and Disconnect work as for any customer. Cards stay out of the Customers list and the
  dashboard counts; pick "hotspot-vouchers" in the list's Source filter to see them.
- **Plans:** a batch uses one Hotspot plan whose validity starts at the first login (Plans, "Validity starts at first
  login"). A card's clock starts when it first logs in, so unused cards never expire. A time limit on the plan makes
  time vouchers (hours of use).
- **Codes:** capital letters and digits without 0, O, 1, I and L, 6 to 12 long, with an optional prefix. A card logs
  in with one PIN (username and password the same) or with a username and a separate password.
- **New batches do not work** until they are handed out: to a shop (every card counts as sold and the shop is charged
  its price per card) or for counter sale (staff mark cards sold one by one). A batch can be switched off and on
  again; switched off, its cards stop logging in and online ones are disconnected.
- **Shops:** a list with what each was charged, what it paid and what it still owes. Staff record payments. Shops do
  not log in.
- **Printing:** Print cards on the batch page lists the card layouts, the default first: A4 with 21 cards, A4 with
  40 small cards, and thermal rolls of 58 mm and 80 mm (one card after another, with a cut line). Each card has the
  name on the cards, the code (or username and password) and, as set below, the logo, plan, price, validity, footer
  and a QR code that opens the Hotspot login page with the card's username and password filled in. Printing is from
  the browser. CSV export for printing elsewhere.
- **Deleting:** a batch none of whose cards was used can be deleted with its cards (and the shop's charge for it).
- Up to 200 cards in one batch.

## Settings

System, then Add-ons, then Hotspot vouchers:

- **Name on the cards** (empty: the panel's name).
- **Hotspot login address**, for example `http://hotspot.example/login`. The QR code opens it with `username` and
  `password` in the address. The MikroTik Hotspot server profile must allow HTTP PAP login for that to work.
- **Logo on the cards:** PNG or JPG, 1 MB at most (checked by its content; SVG is refused). A new logo replaces the
  old one; Remove logo deletes it. The A4 40 layout has no room for it.
- **Footer:** two lines of up to 40 characters, such as a helpline or "Ask the shop for help".
- **Band colour:** a colour like `#166534` for the top of each card, with black or white text, whichever reads
  better. Empty: no band. Thermal paper prints no band.
- **Show the price, the validity, the plan name, the QR code:** all on until switched off.
- **Default layout:** the one listed first under Print cards.

The settings apply to every batch. A panel that changes none of them prints the same card as before.

**Thermal printers:** set the printer's paper size to the roll (58 mm or 80 mm) in its driver, and in the browser's
print dialog turn off headers and footers and set the margins to none. Thermal printers print the logo in black and
white.

## Permissions

| Box | Allows |
|---|---|
| Generate and hand out vouchers | New batches, hand out, switch off and on, mark sold, shops and payments, settings |
| Print and export vouchers | The print sheet and CSV (they show the codes) |
| Delete voucher batches | Deleting a batch none of whose cards was used (ticks Generate too) |

Every batch made, handed out, switched, deleted, printed or exported and every shop payment is in the Activity log.

## Install

```bash
sudo ispgrid addon add ispgrid/hotspot-vouchers
```

Its data lives in its own database, `ispgrid_vouchers`, and the card logo under `vouchers/` on the add-ons disk;
the nightly backup keeps both. `sudo ispgrid addon remove ispgrid/hotspot-vouchers --delete-data` removes both; the
card customers stay in ISPGrid (delete the batches first to remove them).

## Write a card layout

Another add-on can add its own layout to Print cards (a sticker sheet, another language, another shape) without
changing core or this add-on. Register it from that add-on's service provider:

```php
use Ispgrid\HotspotVouchers\Support\CardLayouts;

public function boot(): void
{
    $this->loadViewsFrom(__DIR__.'/../resources/views', 'my-addon');

    if (class_exists(CardLayouts::class)) {
        app(CardLayouts::class)->register('my-sticker', fn (): string => __('Stickers'), 'my-addon::sticker');
    }
}
```

- The key is lowercase letters, digits and `-`. A key that is taken (the built-ins are `a4-21`, `a4-40`,
  `thermal-58` and `thermal-80`) is ignored and logged: the first one registered wins.
- The view gets one variable, `$sheet` (`Ispgrid\HotspotVouchers\Support\CardSheet`): `batchId`, `title`, `logo` (a
  `data:` URI or null), `footer` (the lines that are set), `band` and `bandText` (colours or null), `showPrice`,
  `showValidity`, `showPlan`, `showQr`, `planName`, `price`, `validity`, `pin` (true for one-code cards) and `cards`,
  each with `code`, `password` (null on PIN cards) and `qr` (an SVG, or null).
- Print everything with `{{ }}`. The only exception is `{!! $card['qr'] !!}`, which this add-on makes itself.
- A layout is a whole HTML page with its own `<style>`: core's CSS does not cover add-on views. Put the logo in the
  page once, as a CSS class with `background-image`, not once per card.
- If the view is missing, or its add-on is removed while it is the default, printing falls back to `a4-21`.

```blade
<!DOCTYPE html>
<html>
<head>
    <style>
        .card { width: 50mm; border: 1px dashed #999; padding: 3mm; }
        @if ($sheet->logo)
            .logo { height: 8mm; background: url("{{ $sheet->logo }}") no-repeat left / contain; }
        @endif
    </style>
</head>
<body>
    @foreach ($sheet->cards as $card)
        <div class="card">
            @if ($sheet->logo) <div class="logo"></div> @endif
            <strong>{{ $sheet->title }}</strong>
            <div>{{ $card['code'] }}</div>
            @if ($card['qr']) {!! $card['qr'] !!} @endif
        </div>
    @endforeach
</body>
</html>
```

## Development

From an ISPGrid checkout next to this repository:

```bash
cp composer.json composer.lock ../addon-dev/      # restore them when you are done
composer config repositories.ispgrid-hotspot-vouchers '{"type": "path", "url": "../hotspot-vouchers", "options": {"symlink": true}}'
composer require ispgrid/hotspot-vouchers:@dev
ISPGRID_ADDONS=on php vendor/bin/pest --test-directory=vendor/ispgrid/hotspot-vouchers/tests vendor/ispgrid/hotspot-vouchers/tests
```

The tests use `radius_test` and `ispgrid_vouchers_test` on the local MySQL. Never commit the checkout's changed
`composer.json` and `composer.lock`.

## License

MIT
