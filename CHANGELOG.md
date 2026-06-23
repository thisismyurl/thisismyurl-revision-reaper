# Changelog

All notable changes to **Revision Reaper** are documented here. The plugin
follows a `x.Yddd` version scheme — `x` is the release class (`0` =
pre-release, `1` = full), `Y` is the last digit of the year, `ddd` is the
Julian day of the year (001–366).

## 0.6174.1642 — 2026-06-23

### Fixes (WP.org gate remediation)

- `updater.php`: added `defined( 'ABSPATH' ) || exit` guard (direct file access blocked).
- Main plugin header: removed `GitHub Plugin URI`, `Primary Branch`, and `Update URI` fields;
  added `License URI` field. Strips GitHub-updater metadata required before WP.org submission.
- `.distignore`: added `updater.php` and `assets/*.png` so the GitHub updater and SVN banner/
  icon images are excluded from the distributed zip.
- `readme.txt`: corrected `Tested up to` from `6.9` (unreleased) to `6.8`.
- `uninstall.php`: added `timu_rr_post_type_limits` to the options cleanup list so the new
  per-type limits option is fully removed on plugin deletion.
- `ajax_purge_item()`: revision-keep limit is now resolved server-side (per-type from
  `timu_rr_post_type_limits`, falling back to `timu_rr_limit`) instead of trusting the
  client-supplied `$_POST['limit']` value.
- `class-cli.php` `run` command: loads and passes `timu_rr_post_type_limits` in the
  `$settings` array so WP-CLI runs honour per-post-type limits.

### Features

- Per-post-type revision limits. A new "Per-Post-Type Revision Limits" section
  on the settings screen shows one number input per registered public post type
  (posts, pages, and any active CPTs). Values override the global limit for
  that post type only; empty inputs fall back to the global limit.
- New option `timu_rr_post_type_limits` (serialized array keyed by post-type
  slug) stores per-type limits. Saved alongside all existing settings via the
  same nonce-gated form; input sanitized with `sanitize_key()` + `absint()`,
  and whitelisted against `get_target_post_types()` before storage.
- `get_eligible_items()` resolves the effective keep limit per-post before
  calling `wp_get_post_revisions()`: per-type value is used when set and > 0,
  otherwise falls back to the global `timu_rr_limit`.
- `do_scheduled_cleanup()` applies the same per-type resolution when slicing
  revisions to remove during scheduled runs.
- `ajax_pre_run_export()` and `enqueue_admin_assets()` both forward
  `post_type_limits` in the settings array so preview/export passes use the
  same effective limits as the live run.

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
