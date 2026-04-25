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

## Support and Contribute

### Ways to Support

I'm building these tools because WordPress developers and site owners deserve straightforward, practical solutions. There's no tracking, no ads, and you don't need to pay to use these plugins.

If you find them helpful, here are some genuine ways to support the work:

- **Sponsor if it fits your budget:** You can sponsor the project through [GitHub Sponsors](https://github.com/sponsors/thisismyurl). Sponsorship helps, but it's always optional.
- **Contribute code or ideas:** Opening a pull request, reporting an issue, or testing edge cases is just as valuable as sponsorship. Helping me improve these plugins is a great way to contribute.
- **Share your experience:** A review on [my Google My Business profile]([Your Google Business Profile URL - to be updated]) or a follow on [WordPress.org](https://profiles.wordpress.org/thisismyurl/), [GitHub](https://github.com/thisismyurl), or [LinkedIn](https://linkedin.com/in/thisismyurl) helps others find this work.

### Report Issues and Questions

Found a bug? Want to suggest a feature? Just curious how something works?

- **File an issue:** Use the [Issues](../../issues) tab. Include your WordPress and PHP version, and steps to reproduce.
- **Start a discussion:** Use the [Discussions](../../discussions) tab for questions, ideas, or general conversation about the plugin.

### Contributing Code

Code contributions are welcome and genuinely valuable. Here's the workflow:

1. **Fork this repository** and clone it locally.
2. **Create a feature branch** with a clear name (e.g., `feature/improve-safety-check`).
3. **Make your changes** and test thoroughly on edge cases.
4. **Follow WordPress coding standards** — run `composer run lint:phpcs` before opening a PR.
5. **Open a pull request** with a clear description of what changed and why.

I review PRs thoughtfully and appreciate well-tested contributions. Contributing is never required, but it's genuinely helpful.

---


## About This Is My URL

This plugin supports the work I do at [This Is My URL](https://thisismyurl.com/wordpress-website-maintenance/), where I help WordPress teams build secure, performant, and maintainable sites.

This plugin is built and maintained by [This Is My URL](https://thisismyurl.com/), a WordPress development and technical SEO practice. I'm Christopher Ross, a WordPress developer and technical SEO specialist with 25+ years of experience in software development, training, and digital learning.

### My Background

- **25+ years** in software development, technical training, and digital systems design
- **WordPress contributor since 2007** with a strong track record helping organizations build practical, maintainable web systems
- **Technical SEO practitioner** helping sites improve performance, security, and search visibility
- **Training specialist** focused on practical outcomes and helping teams adopt technology with confidence

I believe in straightforward solutions that work. No hype. No unnecessary complexity.

### Ways to Connect

- **WordPress.org profile:** [profiles.wordpress.org/thisismyurl](https://profiles.wordpress.org/thisismyurl/)
- **GitHub:** [github.com/thisismyurl](https://github.com/thisismyurl)
- **Website:** [thisismyurl.com](https://thisismyurl.com/)
- **LinkedIn:** [linkedin.com/in/thisismyurl](https://linkedin.com/in/thisismyurl)


## License

GPL-2.0-or-later — see [LICENSE](LICENSE) or [gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html).
