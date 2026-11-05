=== Deindicizzazione Automatica Articoli ===
Contributors: icreativi
Tags: seo, noindex, rank math, yoast, archive
Requires at least: 5.7
Tested up to: 7.1
Requires PHP: 7.2
Stable tag: 2.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically noindex and archive old posts from selected categories after a set number of days. Works with Rank Math, Yoast SEO or no SEO plugin.

== Description ==

Deindicizzazione Automatica Articoli keeps search engines focused on your fresh content. Posts in the categories you choose are set to **noindex** after X days and, optionally, **archived** (moved to draft or private) after Y days.

The admin interface is in Italian.

= Features =

* Multiple categories, subcategories included
* Noindex after X days, with "noindex, follow" by default (optional nofollow)
* Works with **Rank Math**, **Yoast SEO**, or WordPress core robots when no SEO plugin is active
* Archive mode: change post status to draft or private after Y days
* Each post is processed once: no repeated emails, accurate counters
* Email notifications with placeholders (`{post_titles}`, `{category}`, `{days}`), sent only when there is something new
* Activity log stored in the database (last 200 runs), downloadable from the admin
* Dashboard widget for administrators with stats and next scheduled run
* Author filter: include or exclude specific authors
* Dry-run mode to test the configuration without changing posts
* Configurable schedule: hourly, daily or weekly
* Optional "scaduto" (expired) tag on old posts
* Sitemap cache refresh for Rank Math and Yoast SEO
* Large sites friendly: posts are processed in batches

= Developer hooks =

* `icrea_deindex_post_types` – post types to process (default `post`)
* `icrea_deindex_batch_size` – posts processed per run for each action (default 200)
* `icrea_deindex_query_args` – filter the WP_Query arguments
* `icrea_deindex_log_limit` – number of log entries kept (default 200)
* `icrea_deindex_after_run` – action fired after each run with a summary

== Installation ==

1. Upload the plugin to `/wp-content/plugins/` or install it from the Plugins screen.
2. Activate it.
3. Go to **Deindex Articoli** in the admin menu.
4. Select the categories to monitor and set the number of days.
5. Optional: enable dry-run first to check what would be processed.

== Frequently Asked Questions ==

= Which SEO plugins are supported? =

Rank Math and Yoast SEO. The plugin writes the noindex setting for both, so it stays in place if you switch SEO plugin. Without an SEO plugin, the noindex is added through WordPress core robots meta tag.

= Why "noindex, follow" instead of "noindex, nofollow"? =

With "follow" search engines keep following the internal links of old posts, so link equity still flows to the rest of the site. You can enable nofollow in the settings if you need it.

= What does archive mode do? =

After the configured number of days, published posts in the selected categories are moved to draft or private, so they are no longer publicly visible.

= How does dry-run work? =

The plugin finds the posts it would process, writes the log and sends the email, but does not change any post.

= I updated from 2.3 or earlier: what changes? =

Versions up to 2.3.1 saved the Rank Math robots setting in a format Rank Math could not read, so posts stayed indexed. Version 2.4.0 cleans those values up on update and applies the noindex correctly at the next run. Your settings are migrated automatically, and the old log file in the uploads folder is moved into the database and deleted.

== Screenshots ==

1. Settings page with multi-category support
2. Activity log
3. Dashboard widget

== Changelog ==

= 2.4.0 =
* Fix: the Rank Math noindex was saved double-serialized and ignored by Rank Math, so posts were never actually noindexed. Broken values are cleaned up on update.
* Fix: each post is now processed only once. Before, every run reprocessed all old posts, sending the same email every hour and inflating the counter.
* Fix: possible fatal error with PHP 8 during the sitemap refresh.
* Fix: the activity log was a public file in the uploads folder. It is now stored in the database (last 200 runs) and the old file is removed.
* Fix: the dashboard widget was visible to every logged-in user. It is now shown to administrators only.
* Fix: settings are validated (schedule, archive status, author mode, emails, days).
* New: Yoast SEO noindex support, plus WordPress core robots fallback when no SEO plugin is active.
* New: "noindex, follow" by default, with an optional nofollow setting.
* New: subcategories are included; categories are stored by ID, so renaming a slug no longer breaks the configuration.
* New: batch processing for large sites, uninstall cleanup, "Settings" link in the plugins list.
* Improved: translatable strings, admin CSS enqueued properly, settings saved through admin-post with nonce and capability checks.
* Requires WordPress 5.7 or later.

= 2.3.1 =
* First WordPress.org release of the 2.3 features
* Tested up to WordPress 7.1

= 2.3 =
* Multi-category support
* Archive mode (post status change)
* Admin log page
* Customizable emails with placeholders
* Sitemap regeneration option
* Dry-run mode
* Author filter
* Dashboard widget
* Configurable cron schedule
* "Scaduto" tag option

= 2.2 =
* Full refactoring for WordPress.org approval

== Upgrade Notice ==

= 2.4.0 =
Important fix: up to 2.3.1 the Rank Math noindex was not applied. After updating, the next run noindexes the old posts correctly.
