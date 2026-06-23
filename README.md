# Revision Reaper

[![CI](https://github.com/thisismyurl/thisismyurl-revision-reaper/actions/workflows/ci.yml/badge.svg)](https://github.com/thisismyurl/thisismyurl-revision-reaper/actions/workflows/ci.yml) [![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-blue)](https://wordpress.org/) [![License](https://img.shields.io/badge/License-GPL--2.0-blue)](LICENSE)

Removes old post revisions, trashed items, spam comments, and stale transients from your WordPress database on a schedule you set.

## What it does

- Removes old post revisions past a keep count you choose
- Empties trashed posts and pages on schedule
- Clears spam and unapproved comments
- Deletes expired transients from the options table
- Runs on WP-Cron at an interval you configure
- Shows a dry-run preview so you can see what would go before anything is deleted
- Reports how much the database shrank after a cleanup

## Requirements

- WordPress 6.4 or later
- PHP 8.1 or later

## Installation

1. Upload the plugin to `/wp-content/plugins/thisismyurl-revision-reaper/`.
2. Activate it from the WordPress Plugins screen.
3. Open **Tools > Revision Reaper**.
4. Set your cleanup thresholds and schedule.
5. Run a dry-run preview first, then turn on the scheduled cleanup.

## What it won't touch

Revision Reaper only deletes data WordPress already treats as redundant or temporary:

- Post revisions beyond the keep count you set
- Items already sitting in the Trash, which a user flagged for deletion
- Spam and unapproved comments
- Expired transients

Published content, media, and user data are never removed.

## Versioning

Versions follow `X.Yjjj.hhmm` — year, Julian day, 24-hour time of the build.

## About

Revision Reaper is built and maintained by [Christopher Ross](https://thisismyurl.com/). I build focused WordPress tools for problems that keep showing up across real sites. No tracking, no ads, no upsells.

**WordPress.org:** [profiles.wordpress.org/thisismyurl](https://profiles.wordpress.org/thisismyurl/) · **GitHub:** [github.com/thisismyurl](https://github.com/thisismyurl) · **LinkedIn:** [linkedin.com/in/thisismyurl](https://linkedin.com/in/thisismyurl)

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
