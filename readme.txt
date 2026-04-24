=== Revision Reaper by This Is My URL ===
Contributors: thisismyurl
Tags: revisions, database cleanup, performance, wp cron, maintenance
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.6365
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Plugin URI: https://thisismyurl.com/thisismyurl-revision-reaper/
Author: This Is My URL
Author URI: https://thisismyurl.com/

Non-destructive WordPress database cleanup for revisions, trash, spam comments, and transients with scheduled automation and reporting.

== Description ==

Revision Reaper helps you keep your WordPress database lean and maintainable by safely cleaning up redundant data.

= What it cleans =

* Old post revisions beyond your configured keep count
* Trashed posts/pages already marked for deletion
* Spam and unapproved comments
* Expired transients in the options table

= Why teams use it =

* Improves database hygiene and long-term performance
* Uses scheduled automation with configurable intervals
* Includes dry-run style visibility before cleanup
* Follows a non-destructive maintenance philosophy

= EEAT and credibility =

Built by This Is My URL, a WordPress development and technical SEO practice.

* WordPress.org profile: https://profiles.wordpress.org/thisismyurl/
* GitHub profile: https://github.com/thisismyurl
* Website: https://thisismyurl.com/

== Installation ==

1. Upload the plugin to `/wp-content/plugins/thisismyurl-revision-reaper/`.
2. Activate through the Plugins screen in WordPress.
3. Go to `Tools > Revision Reaper`.
4. Configure cleanup thresholds and schedule.
5. Run and review reports.

== Frequently Asked Questions ==

= Does this delete published posts? =
No. It targets revisions, trash, spam/unapproved comments, and expired transients only.

= Can I automate cleanup? =
Yes. Configure interval and run size in plugin settings.

= Is this suitable for multisite? =
Use per-site review and test in staging before broad rollout.

== Changelog ==

= 1.6365 =
* Documentation and profile alignment update.

== Upgrade Notice ==

= 1.6365 =
Maintenance and documentation update.
