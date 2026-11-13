# Security policy

## Supported versions

Only the latest release receives security fixes. Please update before reporting a problem.

## Reporting a vulnerability

Please do not open a public issue for a security problem.

Report it privately through GitHub: open the [Security tab](https://github.com/rfluethi/Training-Meeting-Tracker/security) of this repository and choose "Report a vulnerability", or go directly to the [advisory form](https://github.com/rfluethi/Training-Meeting-Tracker/security/advisories/new).

A useful report contains:

- the plugin version, the WordPress version and the PHP version,
- the steps to reproduce the problem,
- what an attacker could achieve with it.

This plugin is maintained by volunteers. Reports are answered as soon as possible, and a fix is published as a new release together with a note in `CHANGELOG.md`.

## Scope

The plugin reads one JSON file over HTTPS and renders it. It stores its settings, the last valid response and a fetch status in the WordPress options table. It has no write access to GitHub and uses no API token.

Problems in the team repository that generates the JSON file are out of scope here and belong to that repository.
