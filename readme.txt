=== Picot Schedule Suite ===
Contributors: picot
Tags: timetable, schedule, calendar, business hours, school
Requires at least: 6.3
Tested up to: 7.2
Stable tag: 1.1.0
Requires PHP: 8.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

時間割・週間スケジュール・営業日カレンダーを1つのプラグインにまとめたスケジュール管理スイートです。

== Description ==

Picot Schedule Suite combines three schedule tools:

* **Timetable** – Create school / cram-school style timetables (`[timetable id="ID"]`)
* **Weekly Schedule** – Manage weekly availability grids with symbols (`[weekly_schedule id="ID"]`)
* **Business Day Calendar** – Mark business days and holidays (`[business_calendar id="ID"]`)

All features share one admin menu under **スケジュール**.

Each tool is also available as a block (時間割 / 週間スケジュール / 営業日カレンダー) in the block editor. Existing shortcode blocks can be transformed into these blocks.

The business day calendar can automatically apply a status to Japanese national holidays (calculated locally for 2007–2099, including substitute and citizens' holidays — no external service is contacted). Developers can adjust the holiday list with the `pss_japan_holidays` filter.

If you previously used the standalone plugins (Timetable Creator, Weekly Business Schedule, Business Day Calendar), activating this suite will deactivate them. Existing data (custom posts and timetable tables) is reused.

== Installation ==

1. Upload the `picot-schedule-suite` folder to `/wp-content/plugins/`.
2. Activate **Picot Schedule Suite** from the Plugins screen.
3. Open **スケジュール** in the admin menu.

== Changelog ==

= 1.1.0 =
* New: Blocks for timetable, weekly schedule and business day calendar (server-rendered; transform from shortcodes).
* New: Business day calendar – Japanese national holidays (auto status, optional holiday names, popup).
* New: Business day calendar – week start (Sunday / Monday) and "today" highlight.
* New: Dashboard shows item counts, "new" buttons and recently updated items.
* Fix: Calendar editor now uses the same default status as the front end.
* Requires WordPress 6.3 or later (block API v3).

= 1.0.1 =
* Fix: Custom CSS child selectors (">") were broken by HTML escaping.
* Fix: Business day calendar now uses the site timezone (previous month was shown early on the 1st in JST).
* Fix: Business hours popup no longer interprets HTML.
* Performance: Timetable tables are only checked when the schema version changes, not on every request.
* Performance: Front-end CSS/JS load only on pages that use the shortcodes.
* Timetable posts are no longer publicly queryable (display via shortcode only).
* Missing-data messages are shown to editors only.
* Added optional data removal on uninstall (dashboard setting, off by default).
* Weekday / month labels follow the site language; weekly & calendar strings are translatable.

= 1.0.0 =
* Initial combined release (timetable + weekly schedule + business day calendar).
