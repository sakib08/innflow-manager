=== StayNexus Hotel Manager ===
Contributors: sakibbd08
Tags: hotel, booking, reservations, hospitality, rooms
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.4
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

Add the shortcode `[shmpp_search]` to any page for the booking UI.

This plugin stores hotel and guest data in custom database tables on your WordPress site. It does not send that data to third-party analytics services.

== Installation ==

1. Upload the `staynexus-hotel-manager` folder to `/wp-content/plugins/`, or install the ZIP via Plugins → Add New → Upload Plugin.
2. Activate **StayNexus Hotel Manager** through the Plugins screen.
3. Open **StayNexus Hotel Manager** in the admin menu to configure settings.
4. Add `[shmpp_search]` to a page for the frontend booking form.

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
