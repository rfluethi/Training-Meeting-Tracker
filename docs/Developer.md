# Developer guide

For anyone working on the plugin code: new features, bug fixes, refactorings, the release process. Assumes basic PHP and WordPress knowledge.

## 1. Requirements

- PHP 7.4 or newer (CI tests against 7.4 and 8.2)
- WordPress 6.4 or newer
- Composer for dev dependencies (PHPCS and WPCS)
- Git and a GitHub account with write access to the repository

## 2. Local setup

```bash
git clone https://github.com/rfluethi/Training-Meeting-Tracker.git
cd Training-Meeting-Tracker
composer install
```

`composer install` pulls in WPCS 3.x and PHP_CodeSniffer. After that the linter is available.

```bash
composer lint        # check for violations
composer lint-fix    # auto fix what phpcbf can
```

Any standard WordPress development environment works for local testing (Local, DDEV, Docker WP, and so on). Symlink the repo into `wp-content/plugins/` and activate the plugin.

## 3. Repository layout

```text
Training-Meeting-Tracker/
  training-meeting-tracker.php   Plugin header, bootstrap, constants
  uninstall.php                  Cleanup on uninstall
  readme.txt                     wp.org format (even though the plugin is not distributed there)
  README.md                      GitHub facing readme
  LICENSE                        GPL-2.0-or-later
  composer.json / phpcs.xml      Dev toolchain
  .gitignore / .editorconfig     Workspace settings
  .github/
    workflows/
      lint.yml                   PHPCS in CI
      plugin-check.yml           WP Plugin Check in CI
      release.yml                Tag -> ZIP build -> release
    ISSUE_TEMPLATE/
      bug_report.yml
      feature_request.yml
      config.yml
    pull_request_template.md
  includes/
    class-tmtracker-plugin.php         Bootstrap class, loads sub classes
    class-tmtracker-fetcher.php        HTTP plus transient cache plus schema validation
    class-tmtracker-renderer.php       HTML rendering, date formatting
    class-tmtracker-shortcode.php      Shortcode [training_meeting_tracker]
    class-tmtracker-settings.php       Settings API, clear cache
  assets/
    css/frontend.css             Frontend styles, prefix tmtracker-
  languages/
    training-meeting-tracker.pot
    training-meeting-tracker-de_DE.po
    training-meeting-tracker-de_DE.mo
  tests/
    bootstrap.php                WP function stubs (esc_*, get_option, ...)
    run-tests.php                Test runner, executed with `php tests/run-tests.php`
    fixtures/sitzungen.json      JSON fixture for renderer and fetcher tests
    README.md                    How the tests run
  bin/
    build-zip.sh                 Local ZIP build, same rules as release.yml
  docs/
    Architecture.md
    Developer.md                 This document
    Operations.md
    User-Guide.md
```

## 4. Class architecture

```text
              +--------------------+
              |    TMTracker_Plugin      |  Singleton, loads and wires sub classes
              +---------+----------+
                        |
       +----------------+----------------+
       |                |                |
       v                v                v
+--------------+ +--------------+ +--------------+
| TMTracker_Fetcher  | | TMTracker_Renderer | | TMTracker_Settings |
+------+-------+ +--------------+ +--------------+
       |                ^
       |                |
       |        +-------+-------+
       +------->| TMTracker_Shortcode |
                +---------------+
```

**`TMTracker_Plugin`** (`includes/class-tmtracker-plugin.php`)
Singleton bootstrap. On `plugins_loaded` it instantiates the other classes and calls `register()` on shortcode and settings. Also registers the stylesheet (not enqueued, the shortcode enqueues on demand).

**`TMTracker_Fetcher`** (`includes/class-tmtracker-fetcher.php`)
HTTP layer with caching and fallback. Reads URL and cache TTL from plugin options, fetches JSON via `wp_remote_get`, validates against the schema version and stores in two places: a transient (short term) and an option `tmtracker_last_good_data` (persistent fallback).

Important methods:

- `get_data( $force_refresh = false )`: main entry point. Returns an array `['data' => ..., 'stale' => bool, 'error' => ?string]`.
- `clear_cache()`: deletes the transient, the option stays as fallback.
- `validate_schema()` and `normalize_session()`: private, check the schema version and drop invalid entries.

**`TMTracker_Renderer`** (`includes/class-tmtracker-renderer.php`)
Pure output class, no data operations. Receives validated data and returns HTML. The format function `format_date()` converts `YYYY-MM-DD` into `DD.MM.YYYY`. All strings are run through `esc_html()`, `esc_url()`, `esc_attr()`.

**`TMTracker_Shortcode`** (`includes/class-tmtracker-shortcode.php`)
Glue: parses shortcode attributes, calls fetcher and renderer, enqueues the stylesheet only when the shortcode is actually used.

**`TMTracker_Settings`** (`includes/class-tmtracker-settings.php`)
Settings API wrapper. Registers the options group, builds the admin page under Settings, Training Meeting Tracker, handles the Clear cache and Refresh now POSTs with nonce checks.

## 5. Data model and schema versioning

The schema version is defined in two parallel places: `TMTracker_Fetcher::SUPPORTED_SCHEMA_VERSION` (PHP, plugin side) and `SCHEMA_VERSION` (Python, build script). Bump both on breaking changes.

Current version: v2 (inherited from the predecessor plugin at 0.3.0). Accepted fields:

| Top level | Sub fields | Required |
|---|---|---|
| `schema_version` | none | yes, must be `2` |
| `generated_at` | none | recommended (ISO 8601 UTC) |
| `source_repo` | none | recommended (`owner/name`) |
| `upcoming_sessions` | `title`, `session_date`, `session_time`, `url` per entry | optional, array may be empty |
| `in_progress_sessions` | `title`, `session_date`, `session_time`, `url` per entry | optional, array may be empty |
| `past_sessions` | `title`, `session_date`, `minutes_date`, `url` per entry | optional, array may be empty |

Unknown fields are ignored. `session_date` and `minutes_date` must be in `YYYY-MM-DD` format, otherwise the entry is dropped. `title` comes from the body field `**Veranstaltung:**` (fallback: issue title minus the date).

Bucket routing in the build script:

- Issue with label `Erledigt` (case insensitive) goes to `past_sessions` with `minutes_date = closedAt`.
- Otherwise `session_date >= today` goes to `upcoming_sessions`.
- Otherwise goes to `in_progress_sessions` (date passed, minutes still missing).

## 6. Constants and options

```php
TMTRACKER_VERSION              // Plugin version, synchronised with header
TMTRACKER_PLUGIN_FILE          // __FILE__ of the main file
TMTRACKER_PLUGIN_DIR / _URL    // path and URL of the plugin folder
TMTRACKER_PLUGIN_BASENAME      // for plugin_basename()
TMTRACKER_DEFAULT_JSON_URL     // Default URL for sitzungen.json
TMTRACKER_DEFAULT_CACHE_HOURS  // Default cache TTL (12)
TMTRACKER_OPTION_SETTINGS      // Option key for user settings
TMTRACKER_OPTION_LAST_GOOD     // Option key for fallback data
TMTRACKER_TRANSIENT_DATA       // Transient key for cached data
```

## 7. Extension points

The plugin does not currently expose any filters or actions. Kept intentionally small. If extensions arrive, filters belong in `TMTracker_Renderer` (HTML manipulation) and in `TMTracker_Fetcher` (data manipulation before caching). Filter prefix convention: `tmtracker_`.

Possible future filters:

```php
apply_filters( 'tmtracker_sessions_data', $data );                  // after schema validation
apply_filters( 'tmtracker_render_next_session', $html, $session );
apply_filters( 'tmtracker_render_past_session', $html, $session );
```

## 8. Release process

### 8.1 Local ZIP build (for tests and manual releases)

```bash
bin/build-zip.sh                  # read version from plugin header
bin/build-zip.sh 0.1.0-rc1        # override version explicitly
```

The script writes to `dist/training-meeting-tracker-<version>.zip`. It uses exactly the include and exclude rules from `.github/workflows/release.yml`, so the locally built ZIP is identical to the CI artefact.

Excluded paths: `.git`, `.github`, `vendor`, `node_modules`, `tests`, `docs`, `dist`, `bin`, `*.zip`, `.gitignore`, `.editorconfig`, `phpcs.xml`, `composer.json`, `composer.lock`, `CHANGELOG.md`, `README.md`. If a file is missing from or wrongly included in the ZIP, adjust here and keep the release workflow filter in sync.

Note: on a fresh clone you need to run `chmod +x bin/build-zip.sh` once.

### 8.2 Official release via Git tag

Three steps for a new version:

1. **Update `CHANGELOG.md`.** Rename the `[Unreleased]` entry to `[X.Y.Z] - YYYY-MM-DD`, open a new `[Unreleased]` block on top, append compare links at the bottom.
2. **Bump the version everywhere in sync:**
   - Plugin header in `training-meeting-tracker.php` (the `Version:` line)
   - Constant `TMTRACKER_VERSION` in the same file
   - `readme.txt` (the `Stable tag:` line)
3. **Tag and push:**
   ```bash
   git add -A && git commit -m "Release X.Y.Z"
   git tag -a vX.Y.Z -m "Version X.Y.Z"
   git push && git push origin vX.Y.Z
   ```

The release workflow picks up the tag, builds a ZIP (same excludes as `bin/build-zip.sh`), creates a GitHub release and attaches the ZIP as an asset. The asset can be uploaded directly through the WP admin.

## 9. CI workflows

| Workflow | Trigger | What it does |
|---|---|---|
| `lint.yml` | Push and PR on `main` | Composer install plus `composer lint` (PHPCS against WPCS) on PHP 7.4 and 8.2 |
| `plugin-check.yml` | PR on `main`, tag pushes, manual | WordPress Plugin Check (official action), without the `plugin_repo` category (no wp.org listing) |
| `release.yml` | Tag push `v*` | ZIP build, GitHub release creation |

Before pushing, run `composer lint` locally once. Saves a red CI build.

## 10. Code style notes

- WordPress Coding Standards 3.x (`phpcs.xml`)
- Tabs for indentation in PHP, spaces in JSON / YAML / Markdown (`.editorconfig`)
- Class names: `TMTracker_Component`, file names `class-tmtracker-component.php` (WPCS convention)
- Avoid global variables. If unavoidable (for example in `uninstall.php`), use the `tmtracker_` prefix.
- Strings always routed through `__()` / `esc_html__()` / `esc_attr__()` for i18n.
- Output always escaped with `esc_html`, `esc_url`, `esc_attr`. Never directly echo variables.
- Capability checks and nonces wherever user input is processed (see `TMTracker_Settings::handle_clear_cache()`).
- No closing `?>` tags at the end of files.
- ABSPATH guard in every PHP file (except `uninstall.php`, which has the `WP_UNINSTALL_PLUGIN` guard).

## 11. Tests

Lightweight integration tests live under `tests/`. They work without `wp-phpunit` and use hand written stubs for the WP functions the source code touches (`esc_*`, `__`, `wp_date`, `get_option`, transients).

Run:

```bash
php tests/run-tests.php
```

Exit code 0 means all passed, 1 means at least one failed. Details in [tests/README.md](../tests/README.md).

Covered tests include schema validation against the fixture, renderer HTML (including `aria-labelledby`, `aria-hidden`, `<time datetime="...">`), year sorting and `years="1"` trimming. For deeper WordPress integration, [`wp-env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) together with [WP_UnitTestCase](https://developer.wordpress.org/cli/commands/scaffold/plugin-tests/) is the way to go, but no need so far.

## 12. Known gotchas

- WPCS is strict about column alignment in DocBlocks. Convention: longest type plus one space. Example with types `array|null` (10), `array` (5), `bool` (4), `string|null` (11): the `$param` column starts at 11+1=12 characters.
- Array alignment in `wp_remote_get` arguments: the longest key drives the `=>` column. Pad with spaces before each `=>` so all of them line up.
- `load_plugin_textdomain()` is no longer needed since WP 4.6 when the `.mo` files live in the plugin's `languages/` subfolder. Plugin Check warns otherwise. The call was deliberately removed.

## 13. Further reading

- [Architecture.md](Architecture.md): system level overview.
- [Operations.md](Operations.md): what to do when something breaks.
- [../CHANGELOG.md](../CHANGELOG.md): version history.
