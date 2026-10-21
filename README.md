# SuperCalPlan

**Automatic task scheduling for Nextcloud: a fork of CalPlan with fixes and
more features.**

SuperCalPlan places your Nextcloud tasks into free time in your calendar. Turn on
**Auto-schedule** for a task (or give it the `calplan` tag from any CalDAV
client) and SuperCalPlan adds a `🔒 Task title` block in your working hours, then
moves, updates or removes it as the task and your calendar change. Everything
happens inside Nextcloud: no task or calendar data leaves your server, and the
Tasks and Calendar apps are not patched.

SuperCalPlan is a fork of [CalPlan](https://github.com/corbin-auriti/calplan) by
Corbin Auriti, based on CalPlan 0.9.12 (commit `75e05b2`, 2026-08-25). It keeps
CalPlan's data formats, so you can switch from CalPlan without losing your plan
(see [Switching from CalPlan](#switching-from-calplan)).

![SuperCalPlan blocks in Nextcloud Calendar](img/screenshots/calplan-hero.png)

The screenshots are CalPlan's: the interface looks the same, with the name
SuperCalPlan and the additions listed below.

## Differences from CalPlan

The fixes and features below were also proposed to CalPlan as
[corbin-auriti/calplan#1](https://github.com/corbin-auriti/calplan/pull/1)
(fixes) and [corbin-auriti/calplan#2](https://github.com/corbin-auriti/calplan/pull/2)
(features). Each one is a separate commit in this repository.

### Fixes

- **Recurring events**: every occurrence of a recurring event counts as busy
  time. CalPlan read only the first one, so it placed blocks on top of the later
  ones.
- **One plan across calendars**: all calendars are planned in one pass with
  shared daily limits. In CalPlan, blocks in different calendars could overlap
  and each calendar had its own daily limits.
- **Time zones**: days, daily limits and due dates use the user's time zone,
  also across daylight saving changes.
- **Started blocks**: a block that has started stays where it is until the
  expired-block review, unless an event put over it afterwards: then its task is
  planned again from now.
- **Whose calendars**: only the user's own calendars are planned and written;
  calendars shared with the user, subscriptions and federated calendars count as
  busy time.
- **Writes**: blocks are written in UTC and deleted without going through the
  trash bin; one reconcile per user runs at a time; a task change re-plans once
  per request, outside the CalDAV write transaction.
- **Working hours fallback**: without saved working hours, the Nextcloud
  availability (Personal settings → Availability) is used. CalPlan read Talk's
  `working_hours`, which Talk never writes.
- **Scheduling history** (optional, off by default): nothing is computed while
  it is off, the retention is applied on save and export, and the history is
  deleted with the account.
- **Settings page**: translatable strings, the server's limits in the form, one
  save per section, values left out of a save are kept, subscriptions in the
  exclusions list, API URLs that work without URL rewriting.
- **Tasks and Calendar pages**: the script loads only for logged-in users the
  app is enabled for; no endless MutationObserver loop (the Tasks page could
  freeze); read-only tasks are respected; tasks rewritten on the server are
  reloaded.
- Three broken unit tests.

### Features

- **Duration field** in the Tasks sidebar: hours and minutes with − / +
  buttons, saved as the task's `ESTIMATED-DURATION`.
- **Working hours** in Personal settings, one window per weekday, and a
  **planning window** of 7 to 90 days; saving re-plans at once.
- **Re-planning on calendar changes**: creating, moving or deleting an ordinary
  event re-plans at once (from any CalDAV client), and the Calendar view
  refreshes when blocks moved. CalPlan picked such changes up only at the next
  background job or manual Reconcile.
- **Task trees**: a parent's Auto-schedule switch applies to its subtasks; a
  parent with subtasks to schedule gets no block itself; a tree is planned as a
  whole, subtasks one after another in Tasks' manual order, ruled by the top
  task's start, due date and priority.
- **Not scheduled** warnings in the Tasks list and sidebar, with the reason.
- **Daily limit** per task: at most so much time and/or so many blocks a day for
  a task and its subtasks together.
- **Split if needed**: a task may be placed in parts ("Title (1/2)",
  "Title (2/2)"…) to fill each day up to its limits; minimum part length in the
  settings.
- **Planned times in Tasks**: the time of each task's next block in the list,
  all its blocks in the sidebar.

### Only in SuperCalPlan

- The app id `supercalplan`, its own settings section and API
  (`/ocs/v2.php/apps/supercalplan/api/v1/`), version numbers from 1.0.0.
- On install, each user's CalPlan settings are copied (see below).
- SuperCalPlan stays idle for a user CalPlan is enabled for, since both apps
  manage the same blocks.

## Requirements

- Nextcloud 32–34 (tested on 34.0.4)
- PHP 8.2 or newer
- Nextcloud Tasks and Calendar
- Nextcloud background jobs (cron) for the timed checks

## Install

SuperCalPlan is not in the Nextcloud App Store yet. Until it is, install a
release by hand:

1. Download `supercalplan-<version>.tar.gz` from the
   [releases](https://github.com/6801318d8d/supercalplan/releases).
2. Extract it into your Nextcloud `custom_apps` folder (it contains one folder,
   `supercalplan`).
3. Enable it: `php occ app:enable supercalplan` (or in **Apps**).

## Switching from CalPlan

SuperCalPlan uses the same data as CalPlan: the task tag `calplan`, the review
tag `calplan-review`, the block category `calplan-slot`, block UIDs
`calplan-<task UID>` and the `X-CALPLAN-*` task properties. Tasks you scheduled
with CalPlan and their blocks are taken over as they are.

1. Disable CalPlan: `php occ app:disable calplan` (or in **Apps**).
2. Install and enable SuperCalPlan as above. On install it copies each user's
   CalPlan settings (working hours, limits, exclusions…), unless SuperCalPlan
   already has settings for that user.
3. The next reconcile (any task change, the **Reconcile SuperCalPlan** button,
   or the background job) plans with SuperCalPlan.

Not carried over: the optional scheduling history (kept in CalPlan's app data)
and the admin setting `performance_logging`. While CalPlan is enabled for a user,
SuperCalPlan does nothing for them and its settings page says so; disable CalPlan
first.

## Important setup

SuperCalPlan works with Nextcloud calendars that contain both:

- `VTODO` objects for tasks; and
- `VEVENT` objects for calendar blocks.

A task/event calendar is the safest choice. A Tasks-only list (what the Tasks
app creates) cannot show an event in any calendar, so SuperCalPlan gives its tasks
no block and flags them as not scheduled. Some third-party clients do not
display mixed task/event calendars reliably; if a client does not show the
generated blocks, try the same calendar in Nextcloud Calendar.

SuperCalPlan plans and writes only in your own calendars. Tasks in a calendar someone
shared with you are planned by its owner's SuperCalPlan; its events, like those of
subscriptions and federated calendars, count as your busy time.

Nextcloud background jobs should also be configured. Task and calendar-event
edits are handled immediately, but timed review checks depend on the background
job or a manual **Reconcile SuperCalPlan** run.

## Using SuperCalPlan

1. Create or open a task in Nextcloud Tasks.
2. Turn on **Auto-schedule**, or add the standard `calplan` category from another
   CalDAV client.
3. Optionally set a start date, due date, priority, duration, or notes.
4. SuperCalPlan creates a `🔒 Task title` event in the same calendar.

The task is always the source of truth. The calendar event is generated from it.
Editing or dragging the event does not edit the task; a later reconcile can
overwrite that change.

Completing, cancelling, or untagging the task removes its generated event.
`PERCENT-COMPLETE:100` also counts as complete.

### Task duration

SuperCalPlan uses the first available value:

1. the **Duration** field in the Tasks sidebar (`ESTIMATED-DURATION`);
2. the task's standard iCalendar `DURATION`;
3. a `Duration:` line in the notes; or
4. your default duration setting, initially 30 minutes.

Examples:

```text
Duration: 15min
Duration: 1hr
Duration: 1 hour 30 minutes
```

### First step

This optional notes line is copied into the calendar block:

```text
First step: open the document and write the heading
```

It is display-only and does not affect scheduling. The older `Next:` form is
still accepted.

### Task options in the Tasks sidebar

Below **Auto-schedule**, SuperCalPlan adds:

- **Duration** — the block length, saved as the task's `ESTIMATED-DURATION`
  (draft-ietf-calext-ical-tasks). Empty means your default duration.
- **Split if needed** (`X-CALPLAN-SPLITTABLE:TRUE`) — the task fills the
  earliest free time: one whole block when it fits where it can first start,
  otherwise parts named “Title (1/2)”, “Title (2/2)”…, so each day is used up
  to its limits before the next. Parts are at least the **Minimum part when
  splitting** (Personal settings: 15, 30, 45 or 60 minutes, default 30; 15
  fills days exactly). A task that does not fit even in parts stays
  unscheduled. Each part counts as a block for the daily limits; the review
  starts after the last part.
- **Daily limit** (tasks with subtasks; `X-CALPLAN-DAILY-MAX-TIME`, a
  duration, and `X-CALPLAN-DAILY-MAX-BLOCKS`) — at most this much block time
  and/or this many blocks per day for the task and all its subtasks together.
  Blocks of the day that have already started or ended count too.

Split and Daily limit set on a task apply to all its subtasks; nested limits
all apply at once. Turning **Auto-schedule** on or off for a task with
subtasks does the same for all of them. While some subtasks (at any depth) are
still to be scheduled or awaiting review, they get the blocks and the parent
gets none; once all are done, or none is flagged, the parent is planned like any
task. A completed parent no longer rules or limits the subtasks left open under
it.

## How scheduling works

SuperCalPlan plans ahead on a 15-minute grid, within a planning window of 14 days
by default (7–90, **Personal settings → SuperCalPlan → Planning window**).

- A task tree is planned as a whole: its top task's due date, start and
  priority rule all its subtasks, which are placed one after another in the
  tree's depth-first order, siblings in Tasks' manual order ("Sort by:
  Manually", `X-APPLE-SORT-ORDER`, else creation time). Subtasks' own due dates
  and priorities are ignored for scheduling.
- Tasks shows when each task is planned: the time of its next block beside its
  SuperCalPlan icon in the list, and all its blocks in a "Planned:" line in the
  sidebar. A parent whose subtasks get the blocks (a course, a module) shows
  their next one, and the sidebar names that subtask. Nothing is written to
  the task for this.
- Tasks that cannot be placed are flagged in Tasks: a warning icon in the
  list and a "Not scheduled" line with the reason in the sidebar (a parent
  shows how many of its subtasks are not scheduled).
- Busy time comes from all of your active calendars, including calendars
  shared with you, subscriptions and federated calendars.
- `TRANSPARENT` events do not block time.
- Ignored calendars are not read or changed at all.
- Selected ordinary event titles can be ignored as busy time.
- A task is never placed before its start date, nor a subtask before its top
  task's.
- A future due date (a subtask's: its top task's) is a hard latest finish. If
  the task cannot fit before it, the task is left unscheduled.
- An overdue task, or a task without a due date, may roll forward and is tried in
  the earliest available slot.
- A start time outside working hours, on a working day, may open time for that
  task from its start time through the end of that day; so does a start time
  too close to the end of the working hours for the task.
- A block that has started stays where it is until the expired-block review
  has looked at it, unless an event now overlaps it: then it did not happen,
  and its task is planned again from now.
- Daily task minutes and daily block count are both enforced, counting the
  day's blocks that have already started or ended.
- Task gaps and buffers around ordinary events are applied before placement.

Tasks with a missed due date are considered first, followed by tasks with a
missed start date, then other tasks. Within those groups, due date and priority
(high 1 … low 9; none counts as medium) affect order, with shorter tasks used as
a tiebreaker. Tasks without a due date come after dated ones.

The scheduler is informed by established scheduling rules: earliest-deadline-
first, shortest-processing-time, and Moore–Hodgson late-job scheduling. SuperCalPlan
combines those ideas in a practical greedy heuristic; it is not an exact
implementation of any one textbook algorithm. See [ENGINEERING.md](ENGINEERING.md)
for the model, references, and current trade-offs.

Running Reconcile twice with unchanged input should produce no additional
writes.

## When SuperCalPlan updates

- **Task changes:** task create, edit, completion, deletion, or trash triggers an
  immediate reconcile, once per save request, before the request returns.
- **Calendar-event changes:** creating, editing, moving, deleting, trashing, or
  restoring an ordinary event triggers an immediate reconcile at the end of the
  save request, from any CalDAV client, for the calendar's owner and for the
  user who saved it. SuperCalPlan's own blocks never trigger one, nor do transparent
  events or events outside the planning window, and users whose last reconcile
  had no tasks or blocks skip it entirely. In Calendar, the view refreshes at
  once when such a reconcile moved blocks.
- **Manual:** **Reconcile SuperCalPlan** is available in Tasks and Calendar.
- **Background:** the registered job is eligible every five minutes. Actual run
  time depends on Nextcloud's background-job runner.

Calendar clients refresh on their own schedule, so a newly written block may not
appear until Calendar syncs or is refreshed.

## Personal settings

Open **Personal settings → SuperCalPlan** to configure:

- working hours: one window per weekday, unchecked days get no blocks
  (saving re-plans existing blocks at once);
- Compact, Balanced, Relaxed, or Custom pacing;
- maximum task minutes per day;
- maximum task blocks per day (`0` means unlimited);
- fallback task duration;
- recovery time between SuperCalPlan tasks;
- buffer before and after ordinary events;
- expired-task review and automatic rescheduling;
- calendars SuperCalPlan must completely ignore;
- ordinary event titles that should not count as busy time;
- optional scheduling-history collection; and
- recent privacy-safe Reconcile diagnostics.

Until you save working hours there, SuperCalPlan uses your Nextcloud availability
(**Personal settings → Availability**, one window per day from its first to its
last slot) when set, otherwise 09:00–17:00 on every day of the week.

## Expired blocks

After a block ends and its grace period passes, an incomplete task receives the
standard `calplan-review` category.

- With automatic rescheduling enabled, SuperCalPlan keeps the task scheduled and
  tries another time.
- With automatic rescheduling off, SuperCalPlan removes `calplan`, deletes the block,
  and pauses the task. Turn **Auto-schedule** on again (or add `calplan` again
  from any client) to restart it.

## Privacy

Scheduling happens inside Nextcloud. SuperCalPlan does not send task or calendar data
to an external service.

Reconcile diagnostics store aggregate counts and timings, not task titles,
notes, URLs, UIDs, or raw iCalendar data.

Optional scheduling history is off by default. It stores pseudonymous task keys
and coarse scheduling observations in Nextcloud appdata. It can be exported to
Files or deleted from Personal settings.

## Current limitations

- Recurring tasks are not expanded into separate scheduled occurrences.
- Cancelled meetings and invitations you declined can still count as busy unless
  they are transparent or excluded by title.
- Moving a generated block does not pin it.
- A Tasks-only list cannot show generated blocks, so its tasks are not
  scheduled.

## Development

```sh
composer install
composer lint
composer test:unit
composer benchmark:scheduler
scripts/appstore-tarball.sh   # build/supercalplan-<version>.tar.gz for a release
```

The unit tests cover scheduling, reconciliation, iCalendar, exclusions, review,
history and scale; `sabre/vobject` (`composer require --dev sabre/vobject`) is
needed for the iCalendar tests, which skip themselves without it. Behaviour in a
running Nextcloud has been checked on Nextcloud 34.

## Credits

- **CalPlan** was developed by **Corbin Auriti** with help from
  [Cline](https://cline.bot), using GLM-5.2 and other models.
- The changes in SuperCalPlan are by **6801318d8d**, written with the help of
  [Claude Code](https://claude.com/claude-code).

## License

AGPL-3.0-or-later. See [LICENSE](LICENSE).

SuperCalPlan is a modified version of CalPlan, changed since 2026-10-16; the
changes are listed above and in the commit history.

SuperCalPlan is independent of Nextcloud GmbH and of the CalPlan project.
Nextcloud and other product names are used only to identify compatible software
and services.
