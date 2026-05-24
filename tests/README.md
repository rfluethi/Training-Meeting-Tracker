# Tests

Lightweight integration tests for `TMT_Fetcher` (schema + normalization) and
`TMT_Renderer` (HTML output). They run without `wp-phpunit`, against
hand-written stubs for the few WordPress functions the source files touch
(`esc_*`, `__`, `wp_date`, `get_option`, transients, …).

## Run locally

```bash
php tests/run-tests.php
```

Exit code 0 means all tests passed, 1 means at least one failed.

## What the tests cover

- JSON fixture validates against the schema validator.
- `session_time` outside `HH:MM` is dropped.
- The "Next meeting" card renders with `<time datetime="…">`, the
  `aria-labelledby` link to its heading, and the `aria-hidden` separator dot.
- The "Minutes" list is grouped by year, descending, and the `years="1"`
  attribute trims to a single year.
- The empty state renders `role="status"` and the localized notice.
- The stale flag renders the fallback notice with `role="status"`.
- Rendered HTML carries neither `target="_blank"` nor `rel="noopener"`
  (kept in sync with the Training Translation Tracker plugin convention).

## Extending the suite

Add new fixtures under `tests/fixtures/`. Add new test cases at the bottom of
`tests/run-tests.php` using `tmt_test( $name, callable )` and the
`tmt_assert_*` helpers defined in `tests/bootstrap.php`.

The intent is to keep this suite installable without composer or a WordPress
install. `php tests/run-tests.php` from a fresh checkout has to work.
