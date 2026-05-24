# User guide for team members

For everyone who organises, leads or writes minutes for meetings, without a code background. What do I need to do so that a meeting shows up correctly on the website?

## 1. The most important thing in one sentence

When you create a meeting issue in the team GitHub repository, set or change the `Erledigt` label, the meeting overview on the website updates automatically.

## 2. The three blocks on the website

| Block on the website | Which issues land here? |
|---|---|
| **Upcoming meetings** | Issues without the `Erledigt` label, date in the future. |
| **Meetings in progress** | Issues without the `Erledigt` label, date already passed (typically waiting for the minutes). |
| **Minutes** | Issues with the `Erledigt` label, grouped by year. |

The name shown for each entry (for example "Sitzung" or "Workshop") comes from the body field `**Veranstaltung:**` in the issue.

## 3. How does a new meeting get onto the website?

### Step 1: Create the meeting issue

1. In the repository [`learn-wp-dach-team`](https://github.com/rfluethi/learn-wp-dach-team/issues/new/choose) click **New issue**.
2. Choose the template **Sitzung**.
3. Fill in the fields:
   - **Title:** must contain a date in `YYYY-MM-DD` format. Recommended: `Sitzung 2026-05-26`. Other words (for example `Workshop 2026-07-10`) work too, as long as the date is included.
   - **Veranstaltung:** the display name on the website. Usually `Sitzung`. For other formats use `Workshop`, `Vortrag`, and so on.
   - **Datum:** the meeting date, always in `YYYY-MM-DD` format.
   - **Uhrzeit:** by default `20:00 Uhr`. Adjust if the meeting takes place at a different time.
   - **Moderation:** name of the moderator.
   - **Protokollführung:** name of the person who writes the minutes.
4. Click **Submit new issue**.

That is it. The meeting shows up automatically under Upcoming meetings.

### Important: what you do NOT need to do

- You do NOT need to edit the README in the team repository.
- You do NOT need to touch the WordPress site.
- You do NOT need to tell anyone with code knowledge.

## 4. How does a meeting become minutes?

After the meeting, the issue passes through two phases.

**Phase 1 (automatic): Meetings in progress.**
As soon as the meeting date is past, the issue moves from Upcoming meetings into Meetings in progress, just because time passed. You do not need to do anything. This signals to everyone that the minutes are still pending.

**Phase 2 (you set the label): Minutes.**

1. Open the meeting issue.
2. Fill in the sections **Beschlüsse**, **Aufgaben** and **Notizen** under `## Protokoll` in the issue body.
3. In the sidebar on the right, set the label **`Erledigt`**.
4. Optional: close the issue (common, but not required).

As soon as the `Erledigt` label is set:

- The issue moves into the Minutes list.
- The date next to it ("Protokoll vom ...") is set to the day the issue was closed (if it was closed).

## 5. What if the meeting is moved?

**Moved within the same month:**

1. Open the issue.
2. In the body field **Datum** enter the new date.
3. In the title, change the date accordingly.
4. Save.

The website shows the new date within a few minutes.

**Meeting is cancelled (no replacement):**

1. Open the issue.
2. Write a short comment ("Sitzung ausgefallen, kein Protokoll").
3. Set the label `Erledigt` and close the issue.

The meeting then lands as Minutes in the list, but without content, and the next upcoming meeting moves up.

## 6. When does my change appear on the website?

Between saving the issue and the change being visible on the website, typically anything between 1 minute and several hours passes:

- The GitHub automation takes a few seconds to regenerate the JSON file.
- The website caches the data for **12 hours** by default.

**When it has to be faster** (for example shortly before a meeting), someone with WordPress admin rights can bypass the cache:

- Settings, Training Meeting Tracker, **Refresh now** (loads immediately).
- Alternative: **Clear cache**, then the next anonymous page view triggers the refresh.

## 7. What if something is not right?

### My meeting does not appear on the website

Common causes:

- **Label missing:** the issue must carry the `sitzung` label. When created via the template, it is set automatically. For manually created issues it may be missing: add it via Labels in the issue sidebar.
- **No date in the title:** the title must contain `YYYY-MM-DD`. For example `Sitzung 2026-05-26` is fine, `Mai-Sitzung` is not.
- **Date not propagated yet:** wait up to a few hours or ask an admin for Refresh now.

### Time or name is not displayed

The issue body must contain a line with `**Uhrzeit:** 20:00 Uhr` and a line with `**Veranstaltung:** Sitzung` (or the desired name). Issues from the template have both automatically. If one is missing: edit the issue and add it.

### Meeting stays in "Meetings in progress" although the minutes are done

The `Erledigt` label is missing. In the issue sidebar on the right click Labels and set `Erledigt`. This moves the issue into the Minutes list.

### In an emergency, ask someone

If something basic is broken (the website shows nothing at all, error messages, and so on), ask the person responsible for the plugin. They can look in the `Training-Meeting-Tracker` repository for what is going on (see Operations.md for details).

## 8. Convention: what does a good meeting issue look like?

The template provides the structure. Three additional recommendations:

- **Meeting date in the title in exactly `YYYY-MM-DD` format.** This enables automatic sorting. Examples: `Sitzung 2026-05-26`, NOT `Sitzung 26.5.26`.
- **Set the `Veranstaltung:` field.** Even if it always reads "Sitzung", otherwise the plugin falls back to the issue title and shows the date twice.
- **Fill in everything in the body that the template provides.** This also helps later readers, and the meeting overview.

## 9. Cheat sheet

| What I do | What happens on the website |
|---|---|
| Create a new meeting issue with a date in the future | Appears under Upcoming meetings |
| Meeting date passes (time passes) | Moves automatically to Meetings in progress |
| Set label `Erledigt` | Moves to Minutes |
| Remove label `Erledigt` while date is still in the future | Back to Upcoming meetings |
| Change body field `Veranstaltung:` | The displayed name changes |
| Edit the issue (date, time, and so on) | Display is updated automatically |
| Remove the `sitzung` label | Meeting disappears from the website entirely |

## 10. Further reading

If you want to go deeper:

- [Architecture.md](Architecture.md): how the system works technically.
- [Operations.md](Operations.md): for the person who maintains the system.

If you want to contribute to the plugin code:

- [Developer.md](Developer.md): development documentation.
