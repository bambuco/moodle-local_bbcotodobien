# LOCAL PLUGIN ToDo good

ToDo good is an course-quality audit engine. Site administrators define audit types and rules; teachers and managers see compliance in the course, a site dashboard, and optional header alerts.

Package tested in: moodle 5.1+.

## QUICK INSTALL
Download zip package, extract the bbcotodobien folder and upload this folder into public/local/.

## ABOUT
* **Developed by:** David Herney - david dot herney at bambuco dot co
* **GIT:** https://github.com/bambuco/moodle-local_bbcotodobien
* **Powered by:** [BambuCo](https://bambuco.co/)

## IN VERSION

### 2026091602:
* New gradecategoryidnumbers field for activities start/end dates.

### 2026091404:
* Audit types scoped by course category (empty = whole site; parent categories include descendants).
* Rule catalogue RF-R01 to RF-R08:
  * Activity content contains / does not contain text (literal or regex).
  * Activity HTML contains a CSS class (literal or regex).
  * Forum started by a course contact.
  * Section summary contains a dated label.
  * Section activities have start and end dates.
  * Grade category contains activity items.
  * Grade category weights sum to 100%.
* Mandatory rules drive the compliance percentage; optional rules are reported only. N=0 yields 100% / not applicable.
* Course report (More menu): current results, history, guidance, diagnostic detail modal, re-evaluate one rule or all types.
* Course header alert when the lowest applicable type is below 100%.
* Site dashboard: one row per course and audit type, limited to courses where the user has `local/bbcotodobien:viewreport`.
* Scheduled tasks: batch audit and history cleanup (hidden courses, unchanged courses, already complete types, retention days, max reports per course/type).
* JSON export/import of rule configurations between audit types.
* Institutional snapshots of the latest audit per course (downloadable dataformats, stored per audit type).
* CLI runner: `php local/bbcotodobien/cli/audit.php --courseid=2 --audittypeid=1`
* Privacy API: export and anonymise `userid` on institutional audit rows (rows are kept).
* English and Spanish language packs.
* Compatibility with moodle 5.1 / 5.2
