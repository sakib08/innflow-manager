=== StayNexus Hotel Manager ===
Contributors: sakibbd08
Tags: hotel, booking, reservations, hospitality, rooms
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
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
* REST API under `/wp-json/staynexushm/v1/`

Add the shortcode `[staynexus_hotel_manager_search]` to any page for the booking UI.

This plugin stores hotel and guest data in custom database tables on your WordPress site. It does not send that data to third-party analytics services.

== Installation ==

1. Upload the `staynexus-hotel-manager` folder to `/wp-content/plugins/`, or install the ZIP via Plugins → Add New → Upload Plugin.
2. Activate **StayNexus Hotel Manager** through the Plugins screen.
3. Open **StayNexus Hotel Manager** in the admin menu to configure settings.
4. Add `[staynexus_hotel_manager_search]` to a page for the frontend booking form.

== Frequently Asked Questions ==

= Does this replace a theme? =

No. It adds admin screens and a shortcode. Your theme still controls the public site layout around the shortcode.

= Where is guest and booking data stored? =

In custom tables in your WordPress database (prefixed `shmpp_`). Soft-deleted items go to the plugin Trash screen before permanent removal.

= Do I need Node.js to use the plugin? =

No. Production assets are included under `assets/dist/`.

== Screenshots ==

1. Admin dashboard with guest and billing overview.
2. Room types and availability management.
3. Frontend booking search shortcode.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release of StayNexus Hotel Manager.

== Source code ==

https://github.com/sakib08/innflow-manager
