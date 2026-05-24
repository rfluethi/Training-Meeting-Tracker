# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/rfluethi/Training-Meeting-Tracker/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/rfluethi/Training-Meeting-Tracker/releases/tag/v0.1.0
