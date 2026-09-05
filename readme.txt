=== StayNexus Hotel Manager ===
Contributors: sakibbd08
Tags: hotel, booking, reservations, hospitality, rooms
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Hotel operations, reservations, billing, and staff management for WordPress.

== Description ==

StayNexus Hotel Manager is a hotel operations plugin with a React admin dashboard and a guest-facing booking search shortcode.

Features:

* Room types, amenities, and date-based availability slots
* Guest and booking management with soft-delete trash
* Room, restaurant, laundry, and damage billing
* Staff, roles, and salaries
* Dedicated **Payment settings** admin screen for online, manual, and offline payment options
* Configurable frontend primary color so the booking form can match your site theme
* Optional online card payments on the public booking form using Stripe Checkout (requires your own Stripe account)
* Optional manual / bank-transfer payment on checkout with instructions you define
* Pay-at-hotel option so guests can reserve without paying online
* Payment reference numbers for paid bookings and bills (cheque number, card transaction ID, bank transfer reference, and similar)
* Staff can mark bookings paid under Bookings and record the payment method plus reference
* Optional channel manager connection using Channex to sync availability and rates and pull OTA bookings (requires your own Channex account)
* In-admin Channels setup and Channex help guide
* REST API under `/wp-json/staynexushm/v1/`

Add the shortcode `[shmpp_search]` to any page for the booking UI.

= Data storage and third-party services =

By default, hotel and guest data is stored in custom database tables on your WordPress site. The plugin does not send that data to third-party analytics services.

If you enable card payments, guests who choose to pay online are redirected to Stripe Checkout. In that case, payment-related details (such as booking total, currency, and guest email) are sent to Stripe so the payment can be processed. You must provide your own Stripe API keys. This plugin is not affiliated with, endorsed by, or sponsored by Stripe, Inc.

* Stripe terms: https://stripe.com/legal
* Stripe privacy policy: https://stripe.com/privacy

If you enable the channel manager connection, StayNexus exchanges availability, rates, inventory (ARI), and booking data with Channex so Online Travel Agency (OTA) channels can stay in sync. You must provide your own Channex API credentials. This plugin is not affiliated with, endorsed by, or sponsored by Channex.io LTD.

* Channex terms and policies: https://channex.io/policy
* Channex documentation: https://docs.channex.io/

Online card payment, manual payment, and the Channex channel connection are all optional. Guests can still reserve with “Pay at hotel” when you prefer not to collect payment online.

== Installation ==

1. Upload the `staynexus-hotel-manager` folder to `/wp-content/plugins/`, or install the ZIP via Plugins → Add New → Upload Plugin.
2. Activate **StayNexus Hotel Manager** through the Plugins screen.
3. Open **StayNexus Hotel Manager** in the admin menu to configure hotel settings.
4. Add `[shmpp_search]` to a page for the frontend booking form.
5. (Optional) Open **Payment settings** to enable Stripe and/or manual payment:
   * Stripe: enter publishable and secret keys (use test keys while verifying, then live keys for production).
   * Manual payment: enable the option and add bank-transfer or other payment instructions shown to guests after booking.
   * Offline methods: manage labels such as Cash, Card, Bank Transfer, or Cheque for staff when marking payments received.
6. (Optional) Open **Channels** to connect Channex: add your API key and property ID, map room types, then push availability/rates and pull OTA bookings. See **Channex help** in the admin menu for a step-by-step guide.

== Frequently Asked Questions ==

= Does this replace a theme? =

No. It adds admin screens and a shortcode. Your theme still controls the public site layout around the shortcode.

= Where is guest and booking data stored? =

In custom tables in your WordPress database (prefixed `shmpp_`). Soft-deleted items go to the plugin Trash screen before permanent removal.

= Do I need Node.js to use the plugin? =

No. Production assets are included under `assets/dist/`.

= Do I need a Stripe account for bookings? =

No. Bookings work without Stripe. Guests can pay at the hotel, or you can enable manual / bank-transfer instructions. Stripe is only required if you enable optional online card payment in Payment settings.

= How do I test card payments safely? =

In the Stripe Dashboard, use Test mode and paste the `pk_test_` / `sk_test_` keys into Payment settings. Live keys (`pk_live_` / `sk_live_`) should be used only when you are ready to accept real payments. This plugin supports both; it is not limited to a sandbox-only API.

= What guest data is shared with Stripe? =

Only when Stripe is enabled and the guest chooses card payment: booking amount, currency, booking reference metadata, and guest email (when provided) are sent to Stripe to create the Checkout Session. Card details are entered on Stripe’s hosted Checkout page, not stored by this plugin.

= What is a payment reference? =

When a booking or bill is marked paid, staff must enter a payment reference such as a cheque number, card terminal transaction ID, or bank transfer reference. Stripe payments store the Stripe payment identifier automatically as the reference.

= Where do I configure payments? =

Use the **Payment settings** menu item (not the general Settings screen). There you manage Stripe keys, manual payment instructions, and offline payment method labels.

= Do I need Channex? =

No. Direct website bookings work without Channex. Connect Channex only if you want to sync inventory with OTAs (for example Booking.com or Agoda) through a channel manager account you manage yourself.

= What data is shared with Channex? =

When Channels is configured and active, StayNexus sends mapped room availability and rates to Channex and may receive OTA bookings from Channex into your Bookings list. Use your own Channex API key and property ID. Review Channex terms and privacy policies before enabling the connection.

== Screenshots ==

1. Admin dashboard with guest and billing overview.
2. Room types and availability management.
3. Frontend booking search shortcode.

== Changelog ==

= 1.1.1 =
* Removed accidental junk files with invalid names from the plugin root.
* Hardened channel sync log prune queries with `$wpdb->prepare()` and `%i` placeholders.

= 1.1.0 =
* Optional online card payment on the public booking form via Stripe Checkout.
* Optional manual / bank-transfer payment with configurable instructions.
* Dedicated Payment settings admin screen (Stripe, manual payment, offline methods).
* Payment reference numbers required when marking bookings or bills as paid.
* Mark paid action on Bookings with payment method and reference.
* Public checkout REST route for guest reservations (pay at hotel, manual, or pay online).
* Documented optional Channex channel manager connection (Channels + Channex help).

= 1.0.4 =
* Hardened database queries with allow-listed table identifiers and prepared placeholders.

= 1.0.3 =
* Prefixed all PHP class filenames with `class-shmpp-` and updated the autoloader map.
* Confirmed REST routes use `permission_callback`, text domain matches the plugin slug, and SQL identifiers are allow-listed.

= 1.0.2 =
* Fixed text domain to match the plugin slug (staynexus-hotel-manager).
* Renamed the frontend shortcode to the plugin-prefixed `[shmpp_search]` (old `[staynexus_hotel_manager_search]` and `[hotel_booking_search]` shortcodes in existing content are migrated automatically).
* Hardened legacy-table migration queries and the public booking endpoint.

= 1.0.1 =
Initial release of StayNexus Hotel Manager.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.1.1 =
Plugin Check cleanup: invalid filenames removed and channel sync log SQL prepared.

= 1.1.0 =
Adds Payment settings, optional Stripe Checkout, optional manual payment, payment reference tracking, and documents the optional Channex channel connection. Existing pay-at-hotel bookings are unchanged.

= 1.0.4 =
Requires WordPress 6.2+ for prepared identifier placeholders in database queries.

= 1.0.3 =
Class files renamed to class-shmpp-*; no user-facing shortcode or data changes.

= 1.0.2 =
Shortcode renamed to [shmpp_search]; existing content is migrated automatically on upgrade.

= 1.0.1 =
Initial release of StayNexus Hotel Manager.

== Source code ==

https://github.com/sakib08/innflow-manager
