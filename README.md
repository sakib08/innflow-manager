# StayNexus Hotel Manager

React + Tailwind hotel management and booking plugin.

## Features

- React admin dashboard (day / week / month guest & billing overview)
- React frontend search & booking via shortcode `[shmpp_search]`
- Custom database tables for rooms, amenities, date slots, guests, bookings, check-in/out, billing (room, restaurant, walk-in, laundry, damage), discounts, payment types, restaurants, employees, roles, salaries
- Configurable frontend primary color under Settings (matches booking header, buttons, accents to your theme)
- **Payment settings** admin screen for Stripe, manual payment, and offline payment methods
- Optional online card payments on the public booking form using [Stripe Checkout](https://stripe.com) (site owner’s own Stripe account; not affiliated with Stripe)
- Optional manual / bank-transfer payment with instructions shown after booking; mark paid from Bookings
- Pay-at-hotel checkout option without online payment
- Payment reference numbers on paid bookings and bills (cheque #, card transaction ID, transfer ref, etc.; Stripe IDs stored automatically)
- Optional channel manager connection using [Channex](https://channex.io) to sync ARI and pull OTA bookings (site owner’s own Channex account; not affiliated with Channex)
- Channels admin screen and Channex help guide
- REST API under `/wp-json/staynexushm/v1/`

## Setup

1. Activate **StayNexus Hotel Manager** in WordPress admin
2. Build assets (only needed when changing JS/CSS source):

```bash
cd wp-content/plugins/staynexus-hotel-manager
npm install
npm run build
```

3. Add shortcode to any page: `[shmpp_search]`
4. (Optional) Open **Payment settings**:
   - Enable Stripe and add publishable + secret keys (test keys for verification, live keys for production)
   - Enable manual payment and enter bank-transfer / payment instructions
   - Manage offline payment method labels used when staff mark payments received
5. (Optional) Open **Channels** to connect Channex (API key, property ID, room mapping). See **Channex help** for setup steps.

### Stripe / privacy notes

- Online card payment is optional; guests can still choose “Pay at hotel” or manual payment (when enabled).
- When a guest pays online, payment-related data (amount, currency, booking metadata, email) is sent to Stripe. See [Stripe terms](https://stripe.com/legal) and [privacy policy](https://stripe.com/privacy).
- Card numbers are entered on Stripe’s hosted Checkout page and are not stored by this plugin.

### Channex / privacy notes

- Channel sync is optional; direct site bookings work without Channex.
- When connected, availability/rates and booking data may be exchanged with Channex. See [Channex terms and policies](https://channex.io/policy) and [documentation](https://docs.channex.io/).
- This plugin is not affiliated with, endorsed by, or sponsored by Channex.io LTD.

### Payment references

When a booking or bill is marked **paid**, a payment reference is required (cheque number, card terminal transaction ID, bank transfer reference, and similar). Stripe payments use the Stripe payment identifier as the reference automatically.

## Build / release

| Command | Purpose |
| --- | --- |
| `npm run build` | Production JS + CSS into `assets/dist/` |
| `npm run watch:js` | Rebuild JS on change |
| `npm run watch:css` | Rebuild CSS on change |
| `npm run plugin-zip` | Build assets and create a WordPress.org–ready ZIP |
| `npm run dist` | Alias for `plugin-zip` |

```bash
npm run plugin-zip
# → ../staynexus-hotel-manager-builds/staynexus-hotel-manager-1.1.0.zip
```

The ZIP contains PHP, `includes/`, built `assets/dist/`, and `readme.txt` (no `node_modules`, no `.git`). Source for minified assets: https://github.com/sakib08/innflow-manager

## Admin pages

StayNexus Hotel Manager → Dashboard, Rooms, Bookings, Guests, Billing, Staff, Restaurants, Channels, Channex help, Trash, Payment settings, Settings

## License

GPLv2 or later
