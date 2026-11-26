# Hotspot vouchers for ISPGrid

Batches of prepaid Hotspot login cards for [ISPGrid](https://github.com/ispgrid/ispgrid): make a batch, print the
cards, hand the batch to a shop or keep it for counter sale, and see which cards were sold and used.

Needs ISPGrid 0.1.27 or newer. On an older core it only adds its permission boxes.

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
- **Printing:** an A4 sheet, 21 cards a page, printed from the browser, with the name on the cards, plan, price,
  validity, code and, when a Hotspot login address is set, a QR code that opens the login page with the card's
  username and password filled in. CSV export for printing elsewhere.
- **Deleting:** a batch none of whose cards was used can be deleted with its cards (and the shop's charge for it).
- Up to 200 cards in one batch.

## Settings

System, then Add-ons, then Hotspot vouchers:

- **Name on the cards** (empty: the panel's name).
- **Hotspot login address**, for example `http://hotspot.example/login`. The QR code opens it with `username` and
  `password` in the address. The MikroTik Hotspot server profile must allow HTTP PAP login for that to work.

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

Its data lives in its own database, `ispgrid_vouchers`, kept by the nightly backup.
`sudo ispgrid addon remove ispgrid/hotspot-vouchers --delete-data` drops it; the card customers stay in ISPGrid
(delete the batches first to remove them).

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
