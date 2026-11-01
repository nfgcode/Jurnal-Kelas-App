# Academic workflow update

## Approved scope

- Shared source/destination period context, compact roster summary, sequential preparation and simulation.
- Historical inactive periods are not eligible destinations; server-side validation also protects preparation, preview, and execution.
- Student statistics belong to the selected source period.
- Diagnostics are secondary; true blockers remain visible outside the collapsed details.
- New academic years are prepared automatically as inactive. Dates remain admin-editable. Activation is explicit; student promotions/graduation are never scheduled.

## Verification in progress

Focused transition tests use SQLite in-memory. No promotion/graduation tests run against the primary database.

Visual checks render real authenticated Blade responses generated inside the SQLite test environment, then load actual built CSS in headless Edge. This is fixture-based rendering, not an authenticated primary-database browser session. Browser harness navigation to localhost was unavailable.

Evidence: docs/qa-screenshots/workflow/*.png and results.json. Both missing-destination and configured-destination states are exercised at desktop, laptop, tablet, mobile, and 200%-equivalent reflow widths. Checks include horizontal overflow, exactly two period selectors, simulated action availability, and native details toggling.

Verified focused checks: 23 UI/readiness/workflow tests (88 assertions), 7 engine/integration tests (65 assertions), plus updated redirect-following integration test (24 assertions). Calendar tests: 8 passed (55 assertions) on the final source; Full regression finished: 435 passed, 1 failed (1847 assertions). The failure is pre-existing: IkonTest reports missing `clipboard` SVG registration used by the import page. Confirmed both usages and the absent registry entry in HEAD; no icon/import files were changed by this work. Focused IkonTest reproduction: 4 passed, 1 failed. This unrelated defect was left unchanged.

Build: Docker Bun Vite build passed. Existing Bootstrap/Sass deprecation warnings remain.

Scheduler: `tahun-ajaran:siapkan-berikutnya` daily at 00:15, without overlapping. The existing app supervisor runs Laravel schedule:run each minute; no extra OS cron was added. Manual generation was executed and read back on the primary DB: 2027/2028 exists inactive, 2027-07-13 through 2028-06-19, derived from the current active year's dates. 2026/2027 remains active; transition record count remains zero.

Fallback calendar pattern is deployment-configurable through TAHUN_AJARAN_MULAI and TAHUN_AJARAN_SELESAI (MM-DD). Existing source dates take precedence and their successor dates may be edited by administrators. Creating/editing dates never activates a year.
