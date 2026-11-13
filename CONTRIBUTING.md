# Contributing

Thank you for your interest in this plugin. This page is the short version. The details are in [docs/Developer.md](docs/Developer.md).

## Before you start

- For a bug or an idea, open an issue first, so the change can be discussed before work goes into it.
- Security problems are reported privately, see [SECURITY.md](SECURITY.md).
- Everything in this repository is written in English: code, comments, documentation, commit messages.

## Local setup

```bash
git clone https://github.com/rfluethi/Training-Meeting-Tracker.git
cd Training-Meeting-Tracker
composer install
```

## Checks that have to pass

```bash
composer lint               # WordPress Coding Standards
php tests/run-tests.php     # tests, no WordPress install needed
bash bin/check-version.sh   # version numbers agree everywhere
```

CI runs the same checks on every pull request, plus the WordPress Plugin Check.

## What a pull request should contain

- One topic per pull request.
- A test for new behaviour or for the bug that was fixed.
- An entry under `[Unreleased]` in `CHANGELOG.md`.
- Updated documentation when behaviour changes: `README.md`, `readme.txt` and the matching file in `docs/`.
- Updated translation files when a user facing string changes: `.pot`, `.po` and the compiled `.mo` in `languages/`.

## Writing style

The project keeps its texts plain. In code comments and documentation:

- no em dashes and no en dashes, use a colon, a comma or a new sentence instead,
- no emojis, no icons and no arrow symbols,
- no horizontal rules in Markdown.

## License

By contributing you agree that your contribution is licensed under [GPL-2.0-or-later](LICENSE), like the rest of the plugin.
