=== InnFlow Manager ===
Contributors: innflow
Tags: hotel, booking, reservations, hospitality, rooms
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Hotel operations, reservations, billing, and staff management.

== Description ==

InnFlow Manager provides a React admin dashboard and frontend booking search for hotel operations:

* Room types, amenities, availability slots
* Guest and booking management with soft-delete trash
* Room, restaurant, laundry, and damage billing
* Staff, roles, and salaries
* REST API under `/wp-json/innflow-manager/v1/`

Add the shortcode `[innflow_manager_search]` to any page for the guest-facing booking UI.

== Installation ==

1. Upload the `innflow-manager` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu
3. If building from source, run `npm install && npm run build` inside the plugin directory
4. Open InnFlow Manager in the admin menu to configure settings

== Frequently Asked Questions ==

= Does this replace a theme? =

No. It adds admin screens and a shortcode for booking search.

== Changelog ==

= 1.0.0 =
* Initial release
