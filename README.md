# Revision Reaper by This Is My URL

A WordPress database cleanup plugin that removes post revisions, trash, spam comments, and transients on a safe, configurable schedule — keeping your database lean without risking content loss.

## Features

- **Revision cleanup:** Remove old post revisions beyond a configurable threshold.
- **Trash cleanup:** Empty trashed posts and pages on schedule.
- **Spam cleanup:** Automatically clear spam and unapproved comments.
- **Transient cleanup:** Remove stale transients from the options table.
- **Scheduled automation:** WP-Cron integration with configurable intervals.
- **Non-destructive:** Only removes content that WordPress itself considers safe to delete.
- **ROI reporting:** Shows database size reduction and estimated performance impact.
- **Dry-run preview:** See what would be removed before committing to cleanup.

## Requirements

- WordPress 6.0+
- PHP 7.4+

## Installation

1. Upload the plugin to `/wp-content/plugins/thisismyurl-revision-reaper/`.
2. Activate through the WordPress Plugins screen.
3. Go to **Tools > Revision Reaper**.
4. Configure cleanup thresholds and scheduling.
5. Run a dry-run preview, then enable scheduled automation.

## Safety Philosophy

Revision Reaper only removes data WordPress considers transient or redundant:
- Post revisions beyond your configured keep count.
- Items in WordPress Trash (already flagged for deletion by users).
- Spam and unapproved comments.
- Expired transients from the database.

No published content, media, or user data is ever touched.

## Versioning

This plugin uses the format `1.Yddd`:
- `Y` = last digit of the year
- `ddd` = Julian day number

## Standards

- Direct access protection with ABSPATH checks.
- Capability checks for all admin actions.
- Escaping and sanitization aligned with WordPress coding standards.

---

## About This Is My URL

This plugin is built and maintained by [This Is My URL](https://thisismyurl.com/), a WordPress development and technical SEO practice with more than 25 years of experience helping organizations build practical, maintainable web systems.

Christopher Ross ([@thisismyurl](https://profiles.wordpress.org/thisismyurl/)) is a WordCamp speaker, plugin developer, and WordPress practitioner based in Fort Erie, Ontario, Canada. Member of the WordPress community since 2007.

### More Resources

- **Plugin page:** [https://thisismyurl.com/thisismyurl-revision-reaper/](https://thisismyurl.com/thisismyurl-revision-reaper/)
- **WordPress.org profile:** [profiles.wordpress.org/thisismyurl](https://profiles.wordpress.org/thisismyurl/)
- **Other plugins:** [github.com/thisismyurl](https://github.com/thisismyurl)
- **Website:** [thisismyurl.com](https://thisismyurl.com/)

## License

GPL-2.0-or-later — see [LICENSE](LICENSE) or [gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html).
