# Operations: maintenance and troubleshooting

For the person who keeps the running system alive. What to do when something is wrong. How to maintain cache, JSON URL, schema.

## 1. Where to look first

When something is not working, check in this order:

1. **Does the `data` branch look normal?**
   - Direct link: https://github.com/rfluethi/learn-wp-dach-team/blob/data/sitzungen.json
   - If the expected data is there, the source is fine, the problem is in the plugin.
2. **Are the GitHub Actions green?**
   - https://github.com/rfluethi/learn-wp-dach-team/actions
   - Red runs of the JSON build workflow point to data or format problems.
3. **What does the plugin settings page say?**
   - Settings, Training Meeting Tracker, shows the last good fetch timestamp. If it is old, the cache has not refreshed.

## 2. Common tasks

### 2.1 Refresh data immediately or clear the cache (plugin)

On the settings page (Settings, Training Meeting Tracker) there are two buttons:

- **Refresh now** (primary): forces a fresh fetch from the source right away and shows the result as an admin notice. Fastest path when an issue has just been changed and the effect should be visible immediately.
- **Clear cache** (secondary): only drops the transient. The next frontend request fetches the JSON anew. Use this when the next anonymous visitor should pay for the fetch instead of the admin.

### 2.2 Change the JSON URL

Settings, Training Meeting Tracker, JSON URL. Saving automatically clears the cache so that the new URL applies on the next request.

Use cases: alternative source (for example a fork), temporary test URL, or when the team repository moves.

### 2.3 Adjust the cache duration

Same page. Values between 1 and 168 hours. Default is 12 hours, the same cadence as the GitHub Action that rewrites `sitzungen.json`. Combined with two polling cycles, the worst case lag is 24 hours. A very short value (1h) makes sense only when the team is very active right now. A very long value (24h plus) reduces API calls further but can lead to visibly stale data.

### 2.4 Trigger the action manually

If the JSON should be regenerated immediately (without changing an issue):

1. https://github.com/rfluethi/learn-wp-dach-team/actions
2. JSON build workflow, Run workflow (button top right), branch `main`, Run workflow.

The action also runs every 12 hours (`schedule: '17 3,15 * * *'` UTC) as a safety net in case an issue event is lost.

## 3. Troubleshooting

### 3.1 "Session data is being prepared." on the website

The plugin finds neither cache nor persistent fallback. Possible causes:

- **Plugin freshly installed**, has never received a successful response yet. Check the URL in settings, then Clear cache and reload the page.
- **JSON URL is misconfigured** (for example a typo after manual editing). Reset to default: `https://raw.githubusercontent.com/rfluethi/learn-wp-dach-team/data/sitzungen.json`.
- **Repository or branch no longer exists.** Open the URL in a browser: if it returns 404, the source is gone.

### 3.2 "The source is currently unreachable ..."

The plugin shows the last good state with a notice. This is the success message of the fallback mechanism: the website stays functional even while GitHub is unreachable.

If this persists for days:

- Check GitHub Status: https://www.githubstatus.com/
- Open the JSON URL in a browser: does it respond?
- Check on the WordPress server whether outbound HTTPS connections to `raw.githubusercontent.com` are allowed (some hosters block this).

### 3.3 The JSON build action is red

Open the action log and look at which step failed.

| Step | Common cause |
|---|---|
| **Read issues** | GH CLI has no token or the token expired (rare: `secrets.GITHUB_TOKEN` is automatic). |
| **Build JSON** | The Python parser stumbled over unexpected data in one issue. The stack trace in the log shows which issue. |
| **Checkout or create data branch** | Permissions problem: GitHub Actions need write access. Settings, Actions, General, Workflow permissions, "Read and write". |
| **Commit sitzungen.json** | Conflict on the `data` branch (very rare). Fix: clean up the `data` branch manually or force push from the action. |

### 3.4 The JSON looks wrong content wise

**Symptom:** `upcoming_sessions` or `in_progress_sessions` are empty although a matching issue exists.

- Check the title format. A `YYYY-MM-DD` must appear somewhere in the title.
- Check the label. Does the issue carry the `sitzung` label?
- Check the `Erledigt` label. Was it set by accident? Then the issue lands in `past_sessions` instead of `in_progress` or `upcoming`.

**Symptom:** A meeting is missing entirely.

- Does the issue carry the `sitzung` label? The build script ignores everything without it.
- Does the title carry a valid date? Issues without a recognisable `YYYY-MM-DD` are skipped.

**Symptom:** Display name is empty or shows the date.

- The body field `**Veranstaltung:**` must be set. If it is missing, the script falls back to the issue title minus the date, and that is often empty.

**Symptom:** `session_time` is missing in `upcoming_sessions` or `in_progress_sessions`.

- Check the body format. A line with `**Uhrzeit:** XX:XX Uhr` must be present. The issue template generates it automatically. Manually created issues sometimes miss it.

**Symptom:** Plugin shows only "Session data is being prepared." although the JSON exists.

- Schema version mismatch. The plugin accepts only the version in `TMT_Fetcher::SUPPORTED_SCHEMA_VERSION`. If the `data` branch still carries an older version, trigger the action manually once (see 2.4). The current build script writes the JSON in the supported schema version.

### 3.5 Plugin Check warnings about "WP" in plugin names

Not relevant to this plugin. The previous plugin (Learn WP DACH Sitzungen) carried that warning because of the "WP" in its name. The new name Training Meeting Tracker does not contain "WP", so the warning is gone.

## 4. Schema migration

When the data schema needs breaking changes (for example renaming a required field, changing the top level structure):

1. Bump `SCHEMA_VERSION` in the build script.
2. Bump `TMT_Fetcher::SUPPORTED_SCHEMA_VERSION` in the plugin in parallel.
3. Tag both repositories in coordinated releases.
4. Plan a transition window. The old plugin version will reject the new JSON (schema mismatch, falls back to last good version). The new state only kicks in once the plugin is updated.

Rule of thumb: for additions (new optional field) no schema version bump is needed because the plugin ignores unknown fields. Bump the schema version only on removals or renames.

### Previous migrations

- **v1 to v2 (predecessor plugin 0.3.0, May 2026):** three lists instead of one (`upcoming_sessions`, `in_progress_sessions`, `past_sessions`). Bucket routing via label `Erledigt` and meeting date instead of issue state. Display name from body field `**Veranstaltung:**` instead of the title. For the migration: bring the plugin to 0.3.0 first, then trigger the action manually. It writes a v2 JSON right away.

## 5. Maintenance routines

The system is mostly maintenance free. Still useful as an occasional check:

- **Every six months:** compare the WordPress core version against `Tested up to` in `readme.txt` and the plugin header. Bump as a patch release if needed.
- **Every six months:** check the PHP version matrix in `lint.yml` and the `Requires PHP` header for currency.
- **As needed:** update GitHub action versions (`actions/checkout@v4`, `shivammathur/setup-php@v2`, and so on). Dependabot can automate this.
- **Quarterly:** run the Plugin Check action and see whether the WP community added new rules.

## 6. Emergency rollback

When a release actively causes problems:

**Plugin:**

- Deactivate the plugin in the WP admin (data stays in the DB) or replace the plugin files via FTP / SFTP with the previous release ZIP.
- Alternative: download an older ZIP from the GitHub releases page and reinstall.
- Locally a ZIP can be built from any commit: `bin/build-zip.sh <version>`. This allows a hotfix release without waiting for CI.

**JSON generation:**

- Reset the `data` branch in the team repo to an earlier commit if necessary:

```bash
git checkout data
git reset --hard <old-commit>
git push --force origin data
```

This restores `sitzungen.json` to a known good state until the next action run regenerates it.

## 7. What if the team repository disappears?

The team repository could theoretically vanish (account change, reorganisation). Plan:

1. The plugin automatically keeps showing the last good state with a notice. The website is not broken.
2. Set up a new JSON source (fork elsewhere, self hosted, and so on).
3. Enter the new URL in Settings, Training Meeting Tracker, Clear cache.

## 8. Further reading

- [Architecture.md](Architecture.md): how the system is built.
- [Developer.md](Developer.md): when a fix requires code changes.
- [User-Guide.md](User-Guide.md): for team members who maintain meeting issues day to day.
