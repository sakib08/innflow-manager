# InnFlow Manager

React + Tailwind hotel management and booking plugin.

## Features

- React admin dashboard (day / week / month guest & billing overview)
- React frontend search & booking via shortcode `[innflow_manager_search]`
- Custom database tables for rooms, amenities, date slots, guests, bookings, check-in/out, billing (room, restaurant, walk-in, laundry, damage), discounts, payment types, restaurants, employees, roles, salaries
- REST API under `/wp-json/innflow-manager/v1/`

## Setup

1. Activate **InnFlow Manager** in WordPress admin
2. Build assets:

```bash
cd wp-content/plugins/innflow-manager
npm install
npm run build
```

3. Add shortcode to any page: `[innflow_manager_search]`

Legacy shortcode `[hotel_booking_search]` still works.

## Build / release

| Command | Purpose |
| --- | --- |
| `npm run build` | Production JS + CSS into `assets/dist/` |
| `npm run watch:js` | Rebuild JS on change |
| `npm run watch:css` | Rebuild CSS on change |
| `npm run plugin-zip` | Build assets and create `dist/innflow-manager-VERSION.zip` |
| `npm run dist` | Alias for `plugin-zip` |

Release ZIP includes PHP, `includes/`, built `assets/dist/`, and readme files. It excludes `node_modules`, `src`, and tooling configs.

```bash
npm run plugin-zip
# → dist/innflow-manager-1.0.0.zip
```

Upload that ZIP via **Plugins → Add New → Upload Plugin**, or unzip into `wp-content/plugins/`.

## Admin pages

InnFlow Manager → Dashboard, Rooms, Bookings, Guests, Billing, Staff, Restaurants, Trash, Settings

## License

GPLv2 or later
