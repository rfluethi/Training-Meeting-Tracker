# Tests

Lightweight tests for `TMTracker_Fetcher`, `TMTracker_Renderer`, `TMTracker_Shortcode`
and `TMTracker_Settings`. They run without `wp-phpunit`, against hand-written
stubs for the few WordPress functions the source files touch (`esc_*`, `__`,
options, transients, `wp_safe_remote_get`, `shortcode_atts`, ...).

## Run locally

```bash
php tests/run-tests.php
```

Exit code 0 means all tests passed, 1 means at least one failed. CI runs the
same command on every supported PHP version (`.github/workflows/tests.yml`).

## What the tests cover

- Schema: the JSON fixture validates, the retired schema v1 and unknown
  versions are rejected.
- Normalisation: records without a real calendar date are dropped, an invalid
  `session_time` or `minutes_date` becomes empty, only `http` and `https` URLs
  survive.
- Cache: a successful fetch is cached, a failed one falls back to the last
  good data and pauses further requests, a forced refresh ignores the pause.
- Rendering: the three sections with `<time datetime="...">`, the
  `aria-labelledby` link to each heading, the `aria-hidden` separator dot,
  year grouping and `years="1"` trimming.
- Accessibility details: unique heading IDs for a second list on the same
  page, the `heading_level` attribute, the date in the accessible name of
  each link, a fallback for an empty title.
- Empty states: no data at all, no upcoming meeting ("No date set yet."),
  valid data without visible meetings, and the notice for fallback data,
  which only administrators see.
- Rendered HTML carries neither `target="_blank"` nor `rel="noopener"`
  (kept in sync with the Training Translation Tracker plugin convention).
- Shortcode: the callback from fetch to HTML, attribute defaults, the legacy
  `show_next` alias, clamping.
- Settings: the sanitize callback for URL and cache duration.
- Consistency: `uninstall.php` removes every key the plugin defines, and the
  translation files of all five locales contain every string used in the
  code.

The tests do not cover the settings page markup, the hooks, or anything that
needs a running WordPress. For those, test manually on a local site.

## Extending the suite

Add new fixtures under `tests/fixtures/`. Add new test cases to
`tests/run-tests.php` using `tmtracker_test( $name, callable )` and the
`tmtracker_assert_*` helpers defined in `tests/bootstrap.php`. Every test
starts with empty options and transients. `tmtracker_test_http( $code, $body )`
sets the answer of the HTTP stub.

The intent is to keep this suite installable without composer or a WordPress
install. `php tests/run-tests.php` from a fresh checkout has to work.
