# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

This is the first release under the new name. The plugin is the functional successor of `learn-wp-dach-sitzungen` ("Learn DACH Sitzungen") at version 0.3.3. Identifiers were renamed (plugin slug, text domain, class prefix `TMT_`, CSS prefix `tmt-`, shortcode, settings page slug, options keys). German source code comments were translated to English. The data source URL (`learn-wp-dach-team`, branch `data`) and the underlying behaviour stay the same.

Users of the predecessor plugin need to deactivate and remove it before installing this one. The shortcode changes from `[learn_wp_dach_sitzungen]` to `[training_meeting_tracker]` and has to be replaced on every page that uses it. Settings need to be re-entered because the options key prefix changed.

[Unreleased]: https://github.com/rfluethi/Training-Meeting-Tracker/compare/v0.1.2...HEAD
[0.1.2]: https://github.com/rfluethi/Training-Meeting-Tracker/releases/tag/v0.1.2
[0.1.1]: https://github.com/rfluethi/Training-Meeting-Tracker/releases/tag/v0.1.1
[0.1.0]: https://github.com/rfluethi/Training-Meeting-Tracker/releases/tag/v0.1.0
