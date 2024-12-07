=== 410 Response Manager ===
Contributors: harrylaurel
Donate link: https://rathly.com/donate
Tags: seo, url-management, http-status, redirect, gone
Requires at least: 5.6
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Efficiently manage HTTP 410 (Gone) responses with manual entries, regex patterns, and CSV import functionality.

== Description ==

410 Response Manager provides a robust solution for managing HTTP 410 (Gone) responses in WordPress. Perfect for SEO and proper handling of permanently removed content.

= Key Features =

* Manual URL pattern entry with validation
* Regular expression support for dynamic matching
* Bulk CSV import/export functionality
* Option to convert 404s to 410s automatically
* Custom, SEO-friendly 410 error page

= Use Cases =

* Properly handle removed content for SEO
* Manage discontinued products in eCommerce
* Handle legacy URLs after site restructuring
* Improve crawler efficiency
* Maintain clean site structure

= Pro Tips =

1. Use regex patterns for managing multiple similar URLs
2. Regularly export your patterns for backup
3. Monitor 404s to identify candidates for 410s
4. Use bulk import for large-scale URL management

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/410-response-manager` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the '410 Manager' menu item to configure the plugin
4. Optional: Configure the 404 to 410 conversion setting
5. Add URL patterns manually or via CSV import

== Frequently Asked Questions ==

= What is a 410 Gone response? =

A 410 Gone response indicates that the requested resource has been intentionally and permanently removed. Unlike a 404 Not Found, it explicitly tells search engines that the content will never return, helping with faster index cleanup.

= Can I use regular expressions? =

Yes! Enable the "Use as Regular Expression" option when adding URL patterns. This allows for flexible pattern matching like:
* `^/old-products/.*` - Match all URLs starting with /old-products/
* `/discontinued-\d+` - Match URLs containing 'discontinued-' followed by numbers

= How do I bulk import URLs? =

1. Prepare a CSV file with two columns: URL pattern and is_regex (0 or 1)
2. Use the CSV Upload tab in the admin interface
3. Select your file and click Upload
4. The plugin will validate and import valid patterns

= Is it cache-friendly? =

Yes, the plugin implements WordPress caching for optimal performance. URL patterns are cached and only refreshed when necessary.

== Screenshots ==

1. Main admin interface
2. URL pattern management screen
3. CSV import interface
4. Settings configuration
5. Custom 410 error page

== Changelog ==

= 1.0.0 =
* Initial release
* Manual URL pattern management
* Regular expression support
* CSV import functionality
* Custom 410 error page
* Caching implementation
* Security enhancements

== Upgrade Notice ==

= 1.0.0 =
Initial release of 410 Response Manager

== Privacy Policy ==

410 Response Manager does not collect, store, or share any personal data. It only manages URL patterns and their corresponding HTTP response codes.

= Technical Details =

* The plugin stores URL patterns in a custom database table
* No personal user data is collected or stored
* All data is managed locally on your WordPress installation
* CSV imports are processed securely and validated

== Additional Information ==

* For support, visit: https://rathly.com/support
* Documentation: https://rathly.com/docs/410-response-manager
* GitHub repository: https://github.com/harrylaurel/410-response-manager

The plugin is actively maintained and tested with the latest versions of WordPress and PHP.