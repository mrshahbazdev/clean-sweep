=== Clean Sweep ===
Contributors: mrshahbaznns
Donate link: https://github.com/mrshahbazdev/clean-sweep
Tags: cleanup, optimization, delete revisions, remove transients, delete unused themes
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bulk clean unused themes, plugins, orphan thumbnails, post revisions, transients, auto-drafts and spam comments with automatic backups.

== Description ==

Clean Sweep helps WordPress site owners reclaim disk space and improve performance from a single dashboard. It scans your site and lets you safely remove:

* Unused/inactive themes and plugins
* Orphan image thumbnails and attachments
* Post revisions
* Auto-drafts
* Trashed posts
* Spam comments
* Orphan post meta
* Expired (or all) transients

Before anything is deleted, Clean Sweep automatically creates a backup. Themes and plugins are zipped; database items are exported to SQL files. Backups are stored in `wp-content/clean-sweep-backups/`.

== Installation ==

1. Upload the `clean-sweep` folder to `wp-content/plugins/`.
2. Activate the plugin.
3. Go to **Tools > Clean Sweep**.
4. Review the items, select what to remove, and click **Backup & Delete Selected**.

== Frequently Asked Questions ==

= Can I restore deleted items? =
Backups are stored in `wp-content/clean-sweep-backups/`. Themes, plugins, and media are zipped. Database changes are saved as SQL files.

= Is it safe to delete active themes or plugins? =
No. Clean Sweep disables the checkbox for active themes and active plugins so they cannot be deleted.

== Changelog ==

= 1.0.0 =
* Initial release.
