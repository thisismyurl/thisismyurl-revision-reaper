# Changelog

All notable changes to **Revision Reaper** are documented here. The plugin
follows a `x.Yddd` version scheme — `x` is the release class (`0` =
pre-release, `1` = full), `Y` is the last digit of the year, `ddd` is the
Julian day of the year (001–366).

## 0.6123 — 2026-05-03

### Security

- Settings POST handler now requires a `wp_nonce_field()` + `check_admin_referer()`
  pair plus an explicit `current_user_can( 'manage_options' )` check. Previous
  versions accepted unauthenticated POSTs to the settings route.
- `schedule_recurrence` is whitelisted against `array_keys( wp_get_schedules() )`
  before being passed to `wp_schedule_event()`. Previously any string flowed
  straight through.
- `schedule_date` is validated by strict regex + `checkdate()` rather than
  passed raw into `strtotime()`.
- The "Run Now" trigger moved from `tools.php?page=revision-reaper&reap=1` (GET)
  to an `admin-post.php` POST gated by nonce + capability. Run intent (dry vs
  live) is carried via a 5-minute transient instead of a URL flag, so a
  refresh or copy/paste cannot replay a destructive run.

### Safety

- `wp_delete_post( $id, true )` (force-delete) on trashed posts replaced with
  `wp_delete_post( $id, false )`, gated by an `EMPTY_TRASH_DAYS` check that
  mirrors WP core's `wp_scheduled_delete()`.
- `wp_delete_comment( $id, true )` on spam comments replaced with
  `wp_trash_comment( $id )` so the operator can recover from the Comments >
  Trash list.
- Before any live run (scheduled or admin-triggered), a JSON snapshot of
  every affected post / revision / comment is written to
  `wp-content/uploads/revision-reaper/exports/`. The directory is created
  with a deny-all `.htaccess` and an `index.php` silence file. Snapshots
  older than 30 days are pruned at the start of each run.
- The admin "Run Now (Live)" button is HTML-disabled until the operator
  ticks an "I have backups" acknowledgement; the submit handler also
  re-checks before allowing POST.
- `is_auto_spam()` separates Akismet auto-spam (purgeable) from
  manually-marked spam (kept) by looking at `akismet_result` /
  `akismet_as_submitted` comment meta.

### Performance

- `get_eligible_items()` now uses paged `WP_Query` (default `batch_size=200`,
  `max_items=1000`) instead of `posts_per_page=-1, post_type=any`.
- Post-type allowlist excludes `attachment`, `revision`, and `nav_menu_item`
  rather than scanning everything.
- `update_post_meta_cache` and `update_post_term_cache` are disabled on
  scan loops.

### Features

- `wp revision-reaper run [--dry-run] [--limit=N] [--include=revisions,trash,spam] [--max=N]`
  WP-CLI command for ops use.
- Site Health "Info" card surfacing current revision-bloat, trash, spam,
  and expired-transient counts.
- Expired transient cleanup (delegated to WP core's
  `delete_expired_transients()`) — previously advertised in the readme but
  never implemented.
- Honest ROI metric: bytes-freed on revision rows reported in the run log.

### Code quality

- `OPTIMIZE TABLE` restricted to MyISAM and Aria tables only (InnoDB
  rebuilds on `OPTIMIZE` and is wasteful as a maintenance pass). Table
  identifiers go through `wpdb::prepare( '%i', ... )`.
- Every user-facing string carries a text domain. Daily/Weekly/Monthly
  hardcoded options replaced with a dynamic `wp_get_schedules()` render.
- Per-arg `esc_html()` on `printf()` arguments; `wp_date()` instead of
  `date_i18n()` for site-timezone correctness.
- Inline jQuery moved to a properly-enqueued admin script with localized
  data via `wp_localize_script()`.
- Dead `wp_send_json_error()` after the AJAX switch removed.
- `@package` annotation aligned with sibling plugins (`Thisismyurl_*`).
- `.distignore` added so `.git/`, `.github/`, `README.md`, etc. don't
  ship to the .org SVN trunk.

### Compatibility

- `Requires at least:` bumped to **6.4** (block bindings + `%i` placeholder
  are load-bearing).
- `Requires PHP:` bumped to **8.1** (typed properties, readonly,
  match expressions all in use).
- `Tested up to:` set to **6.8** (current stable).

## 1.6365

- Documentation and profile alignment update.
