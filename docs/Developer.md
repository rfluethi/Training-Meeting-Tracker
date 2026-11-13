# Developer guide

For anyone working on the plugin code: new features, bug fixes, refactorings, the release process. Assumes basic PHP and WordPress knowledge.

## 1. Requirements

- PHP 7.4 or newer (CI runs the tests on 7.4 to 8.5)
- WordPress 6.7 or newer
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
  CHANGELOG.md                   Version history
  CONTRIBUTING.md                Short guide for contributors
  SECURITY.md                    How to report a vulnerability
  LICENSE                        Full text of the GPL version 2
  composer.json / phpcs.xml      Dev toolchain
  .gitignore / .gitattributes    Git settings
  .editorconfig                  Editor settings
  .github/
    workflows/
      lint.yml                   PHPCS in CI
      tests.yml                  Tests, version check, ZIP build in CI
      plugin-check.yml           WP Plugin Check in CI
      release.yml                Tag, checks, ZIP build, release
    ISSUE_TEMPLATE/
      bug_report.yml
      feature_request.yml
      config.yml
    dependabot.yml               Monthly updates for the GitHub Actions
    pull_request_template.md
  includes/
    class-tmtracker-plugin.php         Bootstrap class, loads sub classes
    class-tmtracker-fetcher.php        HTTP plus transient cache plus schema validation
    class-tmtracker-renderer.php       HTML rendering, date formatting
    class-tmtracker-shortcode.php      Shortcode [training_meeting_tracker]
    class-tmtracker-settings.php       Settings API, status, cache actions
  assets/
    css/frontend.css             Frontend styles, prefix tmtracker-
  languages/
    training-meeting-tracker.pot
    training-meeting-tracker-<locale>.po / .mo   de_DE, de_DE_formal, de_CH, de_CH_informal, de_AT
  tests/
    bootstrap.php                WP function stubs (esc_*, get_option, ...)
    run-tests.php                Test runner, executed with `php tests/run-tests.php`
    fixtures/sitzungen.json      JSON fixture for renderer and fetcher tests
    README.md                    How the tests run
  bin/
    build-zip.sh                 ZIP build, used locally and by the workflows
    check-version.sh             Checks that all version numbers agree
  docs/
    Architecture.md
    Developer.md                 This document
    Operations.md
    User-Guide.md
```

## 4. Class architecture

```text
                 +------------------+
                 | TMTracker_Plugin |  Singleton, creates and wires the other classes
                 +--------+---------+
                          |
           +--------------+---------------+
           |                              |
           v                              v
+---------------------+         +--------------------+
| TMTracker_Shortcode |         | TMTracker_Settings |
+-----+----------+----+         +---------+----------+
      |          |                        |
      |          +-----------+   +--------+
      v                      v   v
+--------------------+  +-------------------+
| TMTracker_Renderer |  | TMTracker_Fetcher |
+--------------------+  +-------------------+
```

**`TMTracker_Plugin`** (`includes/class-tmtracker-plugin.php`)
Singleton bootstrap. On `plugins_loaded` it instantiates the other classes, hands the fetcher to shortcode and settings, and calls `register()` on both. Also registers the stylesheet (not enqueued, the shortcode enqueues on demand).

**`TMTracker_Fetcher`** (`includes/class-tmtracker-fetcher.php`)
HTTP layer with caching and fallback. Reads URL and cache TTL from plugin options, fetches JSON via `wp_safe_remote_get`, validates against the schema version and stores in two places: a transient (short term) and an option `tmtracker_last_good_data` (persistent fallback). After a failed fetch a second transient pauses further requests for `TMTRACKER_BACKOFF_MINUTES` minutes. The outcome of every attempt is kept in the option `tmtracker_fetch_status`.

Important methods:

- `get_data( $force_refresh = false )`: main entry point. Returns an array `['data' => ..., 'stale' => bool, 'error' => ?string]`. With `$force_refresh` the cache and the pause are ignored.
- `clear_cache()`: deletes the cache transient and the pause, the option stays as fallback.
- `get_status()`: time of the last attempt, time of the last success and the last error, for the settings page.
- `validate_schema()` and `normalize_session()`: private, check the schema version and drop invalid entries.

**`TMTracker_Renderer`** (`includes/class-tmtracker-renderer.php`)
Pure output class, no data operations. Receives validated data and returns HTML. The format function `format_date()` converts `YYYY-MM-DD` into `DD.MM.YYYY`. All strings are run through `esc_html()`, `esc_url()`, `esc_attr()`. The heading level comes from the shortcode attribute `heading_level`. A second list rendered on the same page gets a numeric suffix on its heading IDs.

**`TMTracker_Shortcode`** (`includes/class-tmtracker-shortcode.php`)
Glue: parses shortcode attributes (`normalize_atts()`), calls fetcher and renderer, enqueues the stylesheet only when the shortcode is actually used.

**`TMTracker_Settings`** (`includes/class-tmtracker-settings.php`)
Settings API wrapper. Registers the options group, builds the admin page under Settings, Training Meeting Tracker, shows the fetch status, and handles the Refresh now and Clear cache now buttons with a nonce check. Clears the cache when the saved settings change. Uses the fetcher for all of this and never touches the cache keys itself.

## 5. Data model and schema versioning

The schema version is defined in two parallel places: `TMTracker_Fetcher::SUPPORTED_SCHEMA_VERSION` (PHP, plugin side) and `SCHEMA_VERSION` (Python, build script). Bump both on breaking changes. The older version 1 is rejected since 0.2.0.

Current version: v2 (inherited from the predecessor plugin at 0.3.0). Accepted fields:

| Top level | Sub fields | Required |
|---|---|---|
| `schema_version` | none | yes, must be `2` |
| `generated_at` | none | recommended (ISO 8601 UTC) |
| `source_repo` | none | recommended (`owner/name`) |
| `upcoming_sessions` | `title`, `session_date`, `session_time`, `url` per entry | optional, array may be empty |
| `in_progress_sessions` | `title`, `session_date`, `session_time`, `url` per entry | optional, array may be empty |
| `past_sessions` | `title`, `session_date`, `minutes_date`, `url` per entry | optional, array may be empty |

Unknown fields are ignored. `session_date` must be an existing calendar date in `YYYY-MM-DD` format, otherwise the entry is dropped. An invalid `minutes_date` or `session_time` (24 hour `HH:MM`) is replaced by an empty value. `url` is kept only when it starts with `http` or `https`. `title` comes from the body field `**Veranstaltung:**` (fallback: issue title minus the date). For an empty title the renderer shows the generic name "Meeting".

Bucket routing in the build script:

- Issue with label `Erledigt` (case insensitive) goes to `past_sessions` with `minutes_date = closedAt`.
- Otherwise `session_date >= today` goes to `upcoming_sessions`.
- Otherwise goes to `in_progress_sessions` (date passed, minutes still missing).

## 6. Constants and options

```php
TMTRACKER_VERSION              // Plugin version, synchronised with header
TMTRACKER_PLUGIN_FILE          // __FILE__ of the main file
TMTRACKER_PLUGIN_DIR / _URL    // path and URL of the plugin folder
TMTRACKER_PLUGIN_BASENAME      // plugin_basename(), used for the Settings link
TMTRACKER_DEFAULT_JSON_URL     // Default URL for sitzungen.json
TMTRACKER_DEFAULT_CACHE_HOURS  // Default cache TTL (12)
TMTRACKER_BACKOFF_MINUTES      // Pause after a failed fetch (10)
TMTRACKER_OPTION_SETTINGS      // Option key for user settings
TMTRACKER_OPTION_LAST_GOOD     // Option key for fallback data
TMTRACKER_OPTION_FETCH_STATUS  // Option key for the fetch status
TMTRACKER_TRANSIENT_DATA       // Transient key for cached data
TMTRACKER_TRANSIENT_BACKOFF    // Transient key for the pause after a failed fetch
```

`uninstall.php` removes these options and transients. It lists the keys as plain strings because the main file is not loaded during uninstall. A test compares both lists.

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
bash bin/build-zip.sh             # read version from plugin header
bash bin/build-zip.sh 0.1.0-rc1   # override version explicitly
```

The script writes to `dist/training-meeting-tracker-<version>.zip`. It is the only place that decides which files ship: the release workflow and the Plugin Check workflow call the same script, so a locally built ZIP contains the same files as the CI artefact.

The ZIP is built from an allow list (`SHIPPED` in the script): `training-meeting-tracker.php`, `uninstall.php`, `readme.txt`, `LICENSE`, `includes/`, `assets/`, `languages/`. A new file in the repository does not ship unless it is added to that list.

Note: the script is called with `bash`, so it does not need the executable bit.

### 8.2 Official release via Git tag

Three steps for a new version:

1. **Update `CHANGELOG.md`.** Rename the `[Unreleased]` entry to `[X.Y.Z] - YYYY-MM-DD`, open a new `[Unreleased]` block on top, append compare links at the bottom.
2. **Bump the version everywhere in sync:**
   - Plugin header in `training-meeting-tracker.php` (the `Version:` line)
   - Constant `TMTRACKER_VERSION` in the same file
   - `readme.txt` (the `Stable tag:` line, plus an entry under `== Changelog ==` and `== Upgrade Notice ==`)

   `bash bin/check-version.sh` confirms that all of them agree.
3. **Tag and push:**
   ```bash
   git add -A && git commit -m "Release X.Y.Z"
   git tag -a vX.Y.Z -m "Version X.Y.Z"
   git push && git push origin vX.Y.Z
   ```

The release workflow picks up the tag and first runs lint, tests and Plugin Check on the tagged commit. Then it checks that the tag matches the version numbers, builds the ZIP with `bin/build-zip.sh`, creates a GitHub release with the section of `CHANGELOG.md` as release notes and attaches the ZIP as an asset. If one check fails, nothing is published. The asset can be uploaded directly through the WP admin.

## 9. CI workflows

| Workflow | Trigger | What it does |
|---|---|---|
| `lint.yml` | Push and PR on `main` | Composer install plus `composer lint` (PHPCS against WPCS, including the PHP compatibility rules for 7.4 and newer) |
| `tests.yml` | Push and PR on `main` | PHP syntax check and `php tests/run-tests.php` on PHP 7.4 to 8.5, `bin/check-version.sh`, test build of the ZIP |
| `plugin-check.yml` | PR on `main`, manual, called by the release | WordPress Plugin Check (official action) on the unpacked ZIP, without the `plugin_repo` category (no wp.org listing) |
| `release.yml` | Tag push `v*` | Calls the three workflows above, then tag check, ZIP build, GitHub release creation |

Every action is pinned to a commit, with the version as a comment behind it. Dependabot proposes updates once a month (`.github/dependabot.yml`).

Before pushing, run `composer lint` and `php tests/run-tests.php` locally once. Saves a red CI build.

## 10. Code style notes

- WordPress Coding Standards 3.x (`phpcs.xml`)
- Tabs for indentation in PHP, spaces in JSON / YAML / Markdown (`.editorconfig`)
- Class names: `TMTracker_Component`, file names `class-tmtracker-component.php` (WPCS convention)
- Avoid global variables. If unavoidable (for example in `uninstall.php`), use the `tmtracker_` prefix.
- Strings always routed through `__()` / `esc_html__()` / `esc_attr__()` for i18n.
- Output always escaped with `esc_html`, `esc_url`, `esc_attr`. Never directly echo variables.
- Capability checks and nonces wherever user input is processed (see `TMTracker_Settings::handle_cache_action()`).
- No closing `?>` tags at the end of files.
- ABSPATH guard in every PHP file (except `uninstall.php`, which has the `WP_UNINSTALL_PLUGIN` guard).

## 11. Tests

Lightweight tests live under `tests/`. They work without `wp-phpunit` and use hand written stubs for the WP functions the source code touches (`esc_*`, `__`, `get_option`, transients, `wp_safe_remote_get`). CI runs them on every supported PHP version.

Run:

```bash
php tests/run-tests.php
```

Exit code 0 means all passed, 1 means at least one failed. Details in [tests/README.md](../tests/README.md).

Covered tests include schema validation against the fixture, cache, fallback and the pause after a failed fetch, renderer HTML (including `aria-labelledby`, `aria-hidden`, `<time datetime="...">`), year sorting and `years="1"` trimming, shortcode attributes, the settings sanitize callback, and two consistency checks (keys in `uninstall.php`, strings in the translation files). For deeper WordPress integration, [`wp-env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) together with [WP_UnitTestCase](https://developer.wordpress.org/cli/commands/scaffold/plugin-tests/) is the way to go, but no need so far.

## 12. Known gotchas

- WPCS is strict about column alignment in DocBlocks. Convention: longest type plus one space. Example with types `array|null` (10), `array` (5), `bool` (4), `string|null` (11): the `$param` column starts at 11+1=12 characters.
- Array alignment in `wp_safe_remote_get` arguments: the longest key drives the `=>` column. Pad with spaces before each `=>` so all of them line up.
- `load_plugin_textdomain()` is not needed: since WordPress 6.7 core finds the `.mo` files in the plugin's `languages/` subfolder through the `Text Domain` and `Domain Path` headers. Older versions only load translations from `wp-content/languages/plugins/` on their own, which is why the plugin requires 6.7. Plugin Check warns about the call, it was deliberately removed.
- The translation files are maintained by hand. When a string changes, update `.pot` and all five `.po` files and recompile the `.mo` files (for example with `msgfmt` or `wp i18n make-mo languages`). A test fails when a string is missing.
- `wp_safe_remote_get()` rejects private and loopback addresses. To test against a JSON file on `localhost`, allow the host with the core filter `http_request_host_is_external`.

## 13. Further reading

- [Architecture.md](Architecture.md): system level overview.
- [Operations.md](Operations.md): what to do when something breaks.
- [User-Guide.md](User-Guide.md): for team members who maintain meeting issues day to day.
- [../CHANGELOG.md](../CHANGELOG.md): version history.
