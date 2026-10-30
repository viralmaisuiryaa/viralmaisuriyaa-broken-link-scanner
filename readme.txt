=== Viralmaisuriyaa Broken Link Scanner ===
Contributors: viralmaisuriyaa
Tags: broken links, 404, redirects, images, link checker
Requires at least: 6.4
Tested up to: 7.1.1
Requires PHP: 8.0
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Scan published WordPress content for broken links, redirects, and broken images with live progress and CSV export.

== Description ==
Viralmaisuriyaa Broken Link Scanner scans rendered public content from pages, posts, products, and other public post types. It checks internal and external links and images, follows redirects, records HTTP response codes, and shows exactly where each URL was found.

Features:
* Pages, posts, products, and other public post types.
* Rendered front-end HTML scanning for page-builder and WooCommerce output.
* Broken internal and external links.
* Broken images.
* Redirect detection and final destination reporting.
* Source page and anchor/alt text reporting.
* HTTP status codes and request errors.
* AJAX batch scanning with live progress.
* CSV export with UTF-8 BOM for spreadsheet compatibility.
* Translation-ready using the viralmaisuriyaa-broken-link-scanner text domain.
* Multisite and network-activation support with per-site scan data.
* No external API, SaaS account, or third-party PHP dependency required.

== Installation ==
1. Upload the plugin ZIP in Plugins > Add New > Upload Plugin.
2. Activate it on a single site, or Network Activate it on multisite.
3. Open Tools > Broken Link Checker.
4. Click Start New Scan.

== Frequently Asked Questions ==
= Does it need an API key? =
No. It uses the WordPress HTTP API from your server.

= Does it scan WooCommerce products? =
Yes, when WooCommerce products are public. It also supports other public custom post types.

= Is it multisite compatible? =
Yes. Each subsite stores and displays its own scan history and results.

= Are redirects reported as broken? =
No. Redirects are reported separately so you can decide whether to update old URLs.

== Changelog ==

= 1.2.0 =
* Renamed the plugin to Viralmaisuriyaa Broken Link Scanner with a distinctive WordPress.org-ready slug and identifiers.


= 1.1.1 =
* Fixed final Plugin Check database preparation and cache warnings.
= 1.1.0 =
* Redesigned admin dashboard and scanner settings.
* Added Plugins-screen Settings and View Report links.
* Added searchable, sortable, filterable, paginated report table.
* Improved WordPress coding standards and Plugin Check compatibility.

= 1.0.0 =
* Initial production release.
