=== This Is My URL - Revision Reaper ===
Contributors: thisismyurl
Donate link: https://github.com/sponsors/thisismyurl
Tags: revisions, database cleanup, performance, wp cron, maintenance
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.6147
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
The plugin loads and runs per-site on multisite (not network-activated as a single switch). Each site keeps its own settings, schedule, and pre-delete export directory under its own `wp-content/uploads/`. Multisite is not part of the formal test matrix yet — please test in a staging network before rolling to production. Network-wide reaping (one cron pass that walks every site) is on the roadmap and tracked on the GitHub issue list.

== Support, Contributing & Sponsorship ==

= I want to support you =

I'm building these tools because WordPress developers and site owners deserve straightforward, practical solutions. There's no tracking, no ads, and you don't need to pay to use these plugins.

If they're helpful, here are genuine ways to support the work:

* **Sponsor this project:** Visit https://github.com/sponsors/thisismyurl if sponsorship fits your budget. Sponsorship helps, but it's always optional.
* **Contribute code or ideas:** Opening a pull request, reporting an issue, or testing edge cases is just as valuable as sponsorship. Helping me improve these plugins is a great way to contribute.
* **Share your experience:** A review on my [Google My Business profile](https://business.google.com/refer) or a follow on [WordPress.org](https://profiles.wordpress.org/thisismyurl/), [GitHub](https://github.com/thisismyurl), or [LinkedIn](https://linkedin.com/in/thisismyurl) helps others find this work.

= I found a bug or have a feature idea =

* **File an issue on GitHub:** Visit https://github.com/thisismyurl/thisismyurl-revision-reaper/issues and include your WordPress and PHP version.
* **Start a discussion:** Use the Discussions tab on GitHub for questions or ideas.

= I want to contribute code =

Code contributions are welcome and genuinely valuable:

1. Fork the repository on GitHub.
2. Create a feature branch (e.g., `feature/improve-safety`).
3. Make your changes and test thoroughly.
4. Follow WordPress coding standards.
5. Open a pull request with a clear description of what changed and why.

I review PRs thoughtfully and appreciate well-tested contributions. Contributing is never required, but it's genuinely helpful.


== Changelog ==

= 1.6147 =
* Unified plugin versioning to the x.Yddd calendar-version scheme.
* Confirmed compatibility with WordPress 7.0.


= 1.6143 =
* First full release (class 1). The 0.6xxx line was pre-release on the `x.Yddd` scheme.
* Standardized the donation link to GitHub Sponsors.

= 0.6123 =
* Security: added nonce + capability checks on the settings POST handler.
* Security: whitelist `schedule_recurrence` against `wp_get_schedules()` before passing to `wp_schedule_event`.
* Security: validate `schedule_date` strictly as ISO date before passing to scheduler.
* Security: replaced GET-trigger run with admin-post POST + run-intent transient.
* Safety: trash (don't force-delete) trashed posts; respect EMPTY_TRASH_DAYS.
* Safety: trash (don't force-delete) spam comments; recoverable from comment trash.
* Safety: pre-delete JSON snapshot to `uploads/revision-reaper/exports/` with deny-all .htaccess and 30-day retention.
* Safety: separate Akismet auto-spam from manually-marked spam; only auto-spam is reaped.
* Performance: chunked WP_Query (batch 200, cap 1000) replacing `posts_per_page=-1, post_type=any`.
* Feature: `wp revision-reaper run` WP-CLI command with `--dry-run`, `--limit`, `--include`.
* Feature: Site Health card surfacing revision/trash/spam/transient counts.
* Feature: implemented expired-transient cleanup that the readme had advertised.
* Quality: OPTIMIZE TABLE restricted to MyISAM/Aria, identifiers via `%i` placeholder.
* Quality: text-domain coverage on every user-facing string; per-arg escape on printf.
* Quality: dropped dead `wp_send_json_error()` after switch.
* Quality: enqueued admin script properly instead of inline `<script>`.
* Maintenance: bumped Requires at least to 6.4, Requires PHP to 8.1, Tested up to 6.8.
* Maintenance: aligned `@package` annotation with sibling plugins (`Thisismyurl_*`).
* Docs: created CHANGELOG.md referenced by SECURITY.md.
* Docs: filled `[plugin-name]` placeholder in readme.txt.

= 1.6365 =
* Documentation and profile alignment update.

== Upgrade Notice ==

= 0.6123 =
Security and safety release. Adds nonce/capability checks to settings, switches force-delete to trash, adds pre-delete JSON snapshots, and ships the previously-advertised transient cleanup. Recommended for all installs.

= 1.6365 =
Maintenance and documentation update.
