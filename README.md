# Christopher Ross - Revision Reaper

[![CI](https://github.com/thisismyurl/thisismyurl-revision-reaper/actions/workflows/ci.yml/badge.svg)](https://github.com/thisismyurl/thisismyurl-revision-reaper/actions/workflows/ci.yml) [![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-blue)](https://wordpress.org/) [![License](https://img.shields.io/badge/License-GPL--2.0-blue)](LICENSE)

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

- WordPress 6.4+
- PHP 8.1+

## Installation

1. Upload the plugin to `/wp-content/plugins/thisismyurl-revision-reaper/`.
2. Activate through the WordPress Plugins screen.
3. Go to **Tools > Revision Reaper**.
4. Configure cleanup thresholds and scheduling.
5. Run a dry-run preview, then enable scheduled automation.

## Safety philosophy

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

- Direct-access protection with `ABSPATH` checks.
- Capability checks for all admin actions.
- Escaping and sanitisation aligned with WordPress coding standards.

## Changelog

See [releases](../../releases) or [readme.txt](readme.txt).

## Documentation

- [readme.txt](readme.txt)
- [CONTRIBUTING.md](CONTRIBUTING.md)
- [SECURITY.md](SECURITY.md)
- [SUPPORT.md](SUPPORT.md)
- [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md)

---

## Support and donations

I build these tools because WordPress sites in the wild keep hitting the same problems, and a small, focused plugin is usually the right fix. They're free to use, with no tracking and no ads.

If one of them saves you time, here are the genuine ways to help:

- **Sponsor the work.** [GitHub Sponsors](https://github.com/sponsors/thisismyurl) is the simplest way, and the Sponsor button at the top of this repo lists it alongside Bitcoin, Dogecoin, PayPal, and Interac e-transfer. Any amount helps, and none of it is expected.
- **Contribute code or ideas.** A pull request, a bug report, or a tested edge case is worth as much as a donation. See [CONTRIBUTING.md](CONTRIBUTING.md) to get started.
- **Share it.** A note on [WordPress.org](https://profiles.wordpress.org/thisismyurl/), [GitHub](https://github.com/thisismyurl), or [LinkedIn](https://linkedin.com/in/thisismyurl) helps other people find work that might save them the same afternoon.

### Report issues and questions

- **Found a bug or want a feature?** Open an issue on the [Issues](../../issues) tab. Include your WordPress and PHP versions and the steps to reproduce it.
- **Have a question?** Start a thread on the [Discussions](../../discussions) tab.

### Contributing code

Code contributions are welcome. The short version:

1. Fork the repository and clone your fork.
2. Create a branch with a clear name, like `feature/short-descriptive-name`.
3. Make your change and test it against the edge cases.
4. Run the coding-standards check before you open the pull request.
5. Open a pull request that explains what changed and why.

The full workflow and standards live in [CONTRIBUTING.md](CONTRIBUTING.md). Contributing is never required, but it is always appreciated.

## About Christopher Ross

This plugin is built and maintained by [Christopher Ross](https://thisismyurl.com/), the WordPress development and technical SEO practice of Christopher Ross. I help teams build WordPress sites that stay secure, fast, and maintainable, and I write small, focused plugins like this one for the problems those sites keep running into.

### My background

- On the web since 1996, and in WordPress since 2007
- WordPress.org plugin developer with 19 plugins published since 2009
- Technical SEO practitioner focused on performance, security, and search visibility
- Lead instructor and curriculum architect at the M.L. Campbell Training Center, the Sherwin-Williams® international training facility for its industrial wood division

### Ways to connect

- **Website:** [thisismyurl.com](https://thisismyurl.com/)
- **WordPress.org:** [profiles.wordpress.org/thisismyurl](https://profiles.wordpress.org/thisismyurl/)
- **GitHub:** [github.com/thisismyurl](https://github.com/thisismyurl)
- **LinkedIn:** [linkedin.com/in/thisismyurl](https://linkedin.com/in/thisismyurl)

## Contributors

- **Christopher Ross** ([@thisismyurl](https://github.com/thisismyurl)) — author and maintainer
- Thanks to everyone who has reported issues, tested edge cases, and contributed code

## License

GPL-2.0-or-later — see [LICENSE](LICENSE) or [gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html).

---
*This project follows the [10 Core Pillars](PILLARS.md). Support quality work [here](https://github.com/sponsors/thisismyurl).*
