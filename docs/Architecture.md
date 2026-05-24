# Architecture

System overview for anyone who later picks up the plugin to maintain or extend it. Read this first.

## 1. Purpose

The plugin shows an always up to date list of the DACH training team's monthly meetings on the team's WordPress site: the next upcoming meeting, meetings whose minutes are still pending, and past minutes grouped by year. The data lives in the GitHub issues of the team repository, so there is no extra place to maintain. Whoever creates, closes or edits a meeting issue automatically changes what appears on the website.

## 2. Three components, three responsibilities

The system has three clearly separated building blocks.

```text
+---------------------------------------------------------------+
| 1. GitHub issues (source)                                     |
|    Repository: rfluethi/learn-wp-dach-team                    |
|    Label: "sitzung"                                           |
|    Maintained by the team member who leads the meeting        |
+---------------------------------------------------------------+
                              |
                              | Issue events
                              v
+---------------------------------------------------------------+
| 2. GitHub Action that builds sitzungen.json                   |
|    Path: .github/workflows/sitzungen-json.yml                 |
|    Script: .github/scripts/build-sitzungen-json.py            |
|    Writes sitzungen.json to the data branch                   |
+---------------------------------------------------------------+
                              |
                              | HTTPS (raw.githubusercontent.com)
                              v
+---------------------------------------------------------------+
| 3. WordPress plugin "Training Meeting Tracker"                |
|    Repository: rfluethi/Training-Meeting-Tracker              |
|    Fetches JSON, caches it, renders via shortcode             |
+---------------------------------------------------------------+
                              |
                              v
                   Meeting list on the WordPress site
```

Each block is understandable in isolation and replaceable. If the action fails, the plugin keeps showing the last good state. If the plugin is replaced, the JSON source stays. If the issue convention changes, only the build script has to adapt.

## 3. Data flow in detail

**Trigger.** A team member creates a new meeting issue, edits one, closes one (which means minutes are ready), reopens one, or labels/unlabels one. Each of these events fires the GitHub Action.

**Inside the action.**

1. `gh issue list --label sitzung --state all --json ...,labels` fetches all meeting issues including their labels as a raw JSON list.
2. `build-sitzungen-json.py` parses each issue:
   - Date as the first `YYYY-MM-DD` in the title, matched by regex (any title prefix works: `Sitzung 2026-05-26`, `Workshop 2026-07-10`, and so on).
   - Display name from the body field `**Veranstaltung:**` or `### Veranstaltung` (falls back to the issue title minus the date).
   - Time from the body (`**Uhrzeit:** XX:XX Uhr` or `### Uhrzeit\nXX:XX`), matched by regex.
   - Labels (in particular `Erledigt`, case insensitive) decide which bucket the issue lands in.
3. Bucket logic (schema v2):
   - Label `Erledigt` present, the issue goes to `past_sessions` with `minutes_date = closedAt`.
   - Otherwise, `session_date >= today`, the issue goes to `upcoming_sessions` with `session_time`.
   - Otherwise, the issue goes to `in_progress_sessions` with `session_time` (date passed, minutes still missing).
4. Sorting: `upcoming` ascending (the next meeting at the top), `in_progress` descending (youngest at the top), `past` descending.
5. The resulting JSON is committed to the `data` branch, but only if something actually changed.

**Inside the plugin.**

1. When the shortcode is invoked, `TMTracker_Fetcher` first checks the transient cache (default 12 hours).
2. Cache empty, `wp_remote_get` is called against the JSON URL (`https://raw.githubusercontent.com/.../data/sitzungen.json`).
3. Success: the JSON is validated against the schema, cached in the transient, and additionally stored in `tmtracker_last_good_data` as a persistent fallback.
4. Error: the last good fallback is rendered with a subtle notice.
5. `TMTracker_Renderer` builds the HTML, `TMTracker_Shortcode` enqueues the stylesheet and outputs the result.

## 4. Data model

Schema version v2 (introduced with the predecessor plugin at version 0.3.0). The JSON is flat and has three parallel lists.

```json
{
  "schema_version": 2,
  "generated_at": "2026-05-23T08:00:00Z",
  "source_repo": "rfluethi/learn-wp-dach-team",
  "upcoming_sessions": [
    {
      "title": "Sitzung",
      "session_date": "2026-06-12",
      "session_time": "20:00",
      "url": "https://github.com/.../issues/30"
    }
  ],
  "in_progress_sessions": [
    {
      "title": "Sitzung",
      "session_date": "2026-05-10",
      "session_time": "20:00",
      "url": "https://github.com/.../issues/28"
    }
  ],
  "past_sessions": [
    {
      "title": "Sitzung",
      "session_date": "2026-04-15",
      "minutes_date": "2026-04-17",
      "url": "https://github.com/.../issues/25"
    }
  ]
}
```

Notes:

- All date values are stored internally as `YYYY-MM-DD`. Conversion to `DD.MM.YYYY` happens only in the renderer.
- Each of the three lists is optional and may be empty.
- `title` comes from the body field `**Veranstaltung:**`, not from the issue title. This way the frontend shows a clean display name without the date.
- `upcoming_sessions` is sorted ascending, `in_progress_sessions` and `past_sessions` descending by `session_date`.
- `minutes_date` may be empty (label `Erledigt` is set but the issue is not closed, so no `closedAt`).
- `schema_version`: the plugin accepts only the version hardcoded in `TMTracker_Fetcher::SUPPORTED_SCHEMA_VERSION`. Bump the major version on breaking changes.

## 5. Where does what live?

| Responsibility | Repository | Path |
|---|---|---|
| Meeting issues and their maintenance | `rfluethi/learn-wp-dach-team` | Issues with label `sitzung` |
| Action (workflow plus script) | `rfluethi/learn-wp-dach-team` | `.github/workflows/sitzungen-json.yml` and `.github/scripts/build-sitzungen-json.py` |
| Generated JSON | `rfluethi/learn-wp-dach-team` | Branch `data`, file `sitzungen.json` |
| Plugin code | `rfluethi/Training-Meeting-Tracker` | See `Developer.md` |
| WordPress install | DACH website | `wp-content/plugins/training-meeting-tracker/` |

## 6. Design decisions: why this way?

**Why a JSON on a `data` branch instead of querying the GitHub API directly from the plugin?**

Three reasons. First, when GitHub is unreachable, the plugin still has the last good response. Second, no API rate limit, because the plugin only fetches a static file. Third, the JSON can also be consumed outside WordPress (for example to generate the README).

**Why a dedicated `data` branch and not `main`?**

So that each meeting event does not produce a bot commit on `main`. The history of `main` stays human readable, and the existing `protokoll-index.yml` action, which also commits to `main`, does not race with this new action.

**Why a standalone plugin and not JCI?**

This single use case is small and specific enough that a third party plugin like JCI would be overkill. Fifty lines of clear PHP are easier to maintain than a generic template renderer with hundreds of options.

**Why does the label, not the date, decide what counts as "minutes"?**

A meeting only counts as "minutes" once the minutes themselves exist. The team makes this visible by setting the `Erledigt` label. The date alone is not enough. A meeting can take place on day X, but the minutes are filled in days later. As long as the label is not set, the meeting still counts as in progress.

## 7. What is intentionally NOT in the plugin

- **No write access:** the plugin only reads, it never writes back.
- **No authentication code:** since the team repository is public, the plugin does not need an API token.
- **No Markdown parser:** the plugin never parses the full issue body. Only two clearly defined regex patterns on title and time line.
- **No generic template engine:** the layout is hardcoded because the structure does not change. Customisation goes through CSS, not through templates.

## 8. Further documentation

- [Developer.md](Developer.md): code structure, local development, release process.
- [Operations.md](Operations.md): maintenance, troubleshooting, cache behaviour.
- [User-Guide.md](User-Guide.md): for team members who maintain meeting issues.
- [../CHANGELOG.md](../CHANGELOG.md): version history.
