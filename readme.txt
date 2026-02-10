=== OnRoute Courier Booking ===
Contributors: <a href="https://saidur-it.vercel.app" target="_blank">Md. Saidur Rahman</a>
Tags: courier, booking, delivery, shipping, logistics
Requires at least: 5.6
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional courier booking system with multi-step quote and booking flow.

== Description ==

OnRoute Courier Booking is a complete courier booking solution for WordPress websites. 

**Features:**

* 3-step booking flow (Quote → Details → Confirm)
* Multiple vehicle types (Bike, Van, Large Van, XL Van)
* Three service levels (Dedicated, Timed, Same Day)
* UK postcode validation
* Google Maps distance calculation
* Promo code support
* Admin dashboard with booking management
* Responsive design for all devices

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/onroute-courier-booking/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to **Courier Booking → Settings** to configure your Google Maps API key
4. Create pages with shortcodes:
   - Quick Quote: `[onroute_quick_quote]`
   - Full Booking: `[onroute_multi_booking]`

== Configuration ==

1. **Google Maps API Key**: Get from Google Cloud Console, enable Distance Matrix API
2. **VAT Rate**: Set your VAT percentage (default 20%)
3. **Services & Vehicles**: Configure pricing in Settings

== Shortcodes ==

* `[onroute_quick_quote]` - Homepage quote widget
* `[onroute_multi_booking]` - Full 3-step booking form

== Changelog ==

= 1.0.0 =
* Initial release
* 3-step CitySprint-style booking flow
* UK postcode validation
* Google Maps distance calculation
* Admin dashboard

== Upgrade Notice ==

= 1.0.0 =
Initial release
