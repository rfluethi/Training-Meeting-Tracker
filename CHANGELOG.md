# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.0] - 2026-11-13

### Added

- Shortcode attribute `heading_level` (2 to 5). The block headings use that level, the year headings sit one level below. Default is 2, as before.
- Settings page: status section with the time of the last successful fetch, the time the source generated the data, and the reason of the last failed fetch. All times use the date and time format of the site.
- Settings page: a failed "Refresh now" shows the reason. An invalid JSON URL is reported and the previous URL is kept.
- "Settings" link in the plugin row on the Plugins screen.
- The Upcoming meetings block says "No date set yet." when no meeting is scheduled, instead of disappearing.
- Notice "There are currently no meetings to show." when the data is valid but no visible section has an entry.
- German translations for `de_DE_formal`, `de_CH`, `de_CH_informal` and `de_AT`. Before, these locales showed the English texts.
- The link to a meeting carries the date in its accessible name, so links with the same title stay distinguishable for screen reader users.
- Plugin header `Update URI`, so WordPress never offers an update from a plugin with the same slug on wordpress.org.
- `readme.txt` and `README.md` describe the external request to GitHub.
- Tests for the cache and fallback logic, the shortcode attributes, the settings and the translation files. A new CI workflow runs the tests on PHP 7.4 to 8.5.
- `bin/check-version.sh`, `SECURITY.md`, `CONTRIBUTING.md`, `.gitattributes`, Dependabot configuration for the GitHub Actions.

### Changed

- Requires WordPress 6.7 or newer (was 6.4). Older versions do not load the bundled German translation on their own.
- Tested up to WordPress 7.1. "Tested up to" is kept in `readme.txt` only, the plugin header no longer carries it.
- The notice that the source is unreachable and stale data is shown is visible to logged-in administrators only. Visitors see the last good data without the notice.
- Minutes entries show only the meeting date. The prefix "Meeting from" (German: "Sitzung vom") repeated the title.
- Default JSON URL and Author URI point to the team repository in the `learn-wp-dach` organisation (was `rfluethi/learn-wp-dach-team`, which only works through the GitHub redirect).
- After a failed fetch the source is not contacted again for ten minutes. Before, every page view triggered a new request while the source was down.
- The JSON is fetched with `wp_safe_remote_get()` and only `http` and `https` URLs are accepted, in the settings and for the meeting links. The request timeout is 5 seconds, the WordPress default (was 15).
- The cache is cleared when the saved settings change, not on every save.
- Frontend colors are derived from the text color of the surrounding block instead of theme presets with a fixed fallback. The muted text keeps its contrast on dark backgrounds and in themes whose presets are light.
- A second shortcode on the same page gets its own heading IDs.
- A meeting without a title is shown as "Meeting" instead of an empty link.
- English interface strings use "meeting" throughout (was a mix of "session" and "meeting"). The German translation is updated.
- Settings page: both cache buttons are secondary buttons in one form, the usage example shows the current attributes.
- Meeting dates and times have to exist: `2026-02-30` or `25:00` are rejected.
- `uninstall.php` cleans every site of a multisite network, not only the first 100.
- The release workflow runs lint, tests and Plugin Check before it publishes, checks that the tag matches the version numbers, and takes the release notes from this file. `bin/build-zip.sh` is the single place that decides which files ship, and it works from an allow list.
- Plugin Check runs against the unpacked ZIP, not against the repository. The category `internationalization` is dropped from the workflow: Plugin Check 2.x has no such category, its i18n checks run under `general`.
- GitHub Actions are updated and pinned to commits.
- `LICENSE` contains the full text of the GPL version 2.

### Fixed

- The test for past meetings still expected the closing date of the issue and failed since 0.1.2.
- `bin/build-zip.sh` and the release workflow used different file lists.
- User guide: the date next to a minutes entry is the meeting date, not the day the issue was closed.

### Removed

- Support for the JSON schema version 1. The source writes version 2 since May 2026, a version 1 file now counts as a failed fetch.
- Unused CSS rule `.tmtracker-generated-at` and the unused custom property `--tmtracker-font-size-base`.
- `readme.txt`: section "Screenshots", there are no screenshot files.

## [0.1.2] - 2026-05-24

### Changed

- Past meetings now display the meeting date (`session_date`) instead of the closing date of the issue (`minutes_date`). The label changes from "Protokoll vom" to "Sitzung vom" so the displayed date matches the linked issue.
- The footer line "Stand: ..." ("Updated: ...") has been removed from the rendered output. The `render_generated_at` method was deleted from the renderer along with the unused translation strings.

### Fixed

- CI lint: `phpcs.xml` updated with the new prefixes (`tmtracker`, `TMTRACKER`, `TMTracker`).
- CI lint: array alignment in `TMTracker_Settings::handle_clear_cache` and `handle_refresh_now`.
- CI plugin-check: upgrade notice for 0.1.0 shortened from 344 to 235 characters (300 character limit).

## [0.1.1] - 2026-05-24

### Changed

- Settings menu and settings page heading now read "Training Meeting Tracker" instead of the historical "DACH Sessions List" / "DACH-Sitzungsliste". Source string, German translation and the compiled `.mo` file have been updated in lockstep.
- WordPress Coding Standards compliance. All global identifiers renamed to use a 4 plus character prefix as required by WPCS.
  - Classes: `TMTracker_Plugin`, `TMTracker_Fetcher`, `TMTracker_Renderer`, `TMTracker_Shortcode`, `TMTracker_Settings` (was `TMT_*`).
  - Constants: `TMTRACKER_VERSION`, `TMTRACKER_PLUGIN_FILE`, `TMTRACKER_PLUGIN_DIR`, `TMTRACKER_PLUGIN_URL`, `TMTRACKER_PLUGIN_BASENAME`, `TMTRACKER_DEFAULT_JSON_URL`, `TMTRACKER_DEFAULT_CACHE_HOURS`, `TMTRACKER_OPTION_SETTINGS`, `TMTRACKER_OPTION_LAST_GOOD`, `TMTRACKER_TRANSIENT_DATA` (was `TMT_*`).
  - Hooks, options, transients and local variables: `tmtracker_*` (was `tmt_*`).
  - CSS classes: `tmtracker-*` (was `tmt-*`).
  - Class file names: `class-tmtracker-*.php` (was `class-tmt-*.php`).
- Plugin Check workflow stages the plugin under its correct lowercase slug `training-meeting-tracker/` so the text domain check passes regardless of the GitHub repository name (which is `Training-Meeting-Tracker` with CamelCase).

### Migration note

Users upgrading from 0.1.0 are very unlikely to be affected because 0.1.0 was the initial release and probably not yet installed widely. If 0.1.0 was installed, the upgrade resets cached data because the transient key changed. The settings page values stay because the option key prefix change is handled gracefully (existing options under the old key remain readable but are unused; a fresh settings save writes to the new key).

## [0.1.0] - 2026-05-24

### Added

- Initial release of Training Meeting Tracker.
- Shortcode `[training_meeting_tracker]` with attributes `show_upcoming`, `show_in_progress`, `show_past`, `years`.
- Settings page under Settings, Training Meeting Tracker, with JSON URL, cache duration (1 to 168 hours), Clear cache button, Refresh now button.
- Transient cache with fallback to last good response on fetch error.
- Schema validation for the incoming `sitzungen.json` (accepted schema version: 2).
- Full i18n: source strings in English, German translation (`de_DE`) included.
- Cleanup of own options and transients on uninstall.

### Background

This is the first release under the new name. The plugin is the functional successor of an earlier internal plugin ("Learn DACH Sitzungen", since retired) at version 0.3.3. Identifiers were renamed (plugin slug, text domain, class prefix `TMT_`, CSS prefix `tmt-`, shortcode, settings page slug, options keys). German source code comments were translated to English. The data source URL (`learn-wp-dach-team`, branch `data`) and the underlying behaviour stay the same.

Users of the predecessor plugin need to deactivate and remove it before installing this one. The shortcode changes from `[learn_wp_dach_sitzungen]` to `[training_meeting_tracker]` and has to be replaced on every page that uses it. Settings need to be re-entered because the options key prefix changed.

[Unreleased]: https://github.com/rfluethi/Training-Meeting-Tracker/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/rfluethi/Training-Meeting-Tracker/compare/v0.1.2...v0.2.0
[0.1.2]: https://github.com/rfluethi/Training-Meeting-Tracker/releases/tag/v0.1.2
[0.1.1]: https://github.com/rfluethi/Training-Meeting-Tracker/releases/tag/v0.1.1
[0.1.0]: https://github.com/rfluethi/Training-Meeting-Tracker/releases/tag/v0.1.0
