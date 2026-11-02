# FPOS

## UI / Design
Follow the design system in DESIGN.md/stripe/DESIGN.md for all UI work.
Use only its color tokens, typography scale, spacing, radii, and shadows.
Never hardcode colors or invent new fonts. Use the design tokens.
Respect the Do's and Don'ts section.

How the styling is wired:
- `public/backend/css/tokens.css` — the tokens (`--color-*`, `--font-*`, `--text-*`, `--space-*`,
  `--radius-*`, `--shadow-*`, `--control-height`), light and dark. Values marked "app addition" are
  agreed extensions where DESIGN.md is silent (status colors, dark mode, body weight 400, 44px targets).
- `public/backend/css/style.css` — legacy layout; its `--admin-*` variables point at the tokens.
- `public/backend/css/components.css` — buttons, inputs, cards, modals, tables, tabs, layout and
  responsive rules from DESIGN.md. Loaded after style.css.
- Page `<style>` blocks may only use tokens (`var(--color-...)`), never hex/rgb values.

Colors come from Settings: General Settings > Theme Colors saves `settings.theme_colors`;
`SettingController::themeCss()` builds the token overrides, shared to every view as `$themeCss`
(AppServiceProvider) and printed after the stylesheets by the admin header and the login page.
When adding a new colored element, use a semantic token (`--color-accent`, `--color-button`,
`--color-surface`, `--color-text-muted`, `--color-success`, ...) so it follows Settings too.
Text on a filled accent uses `--color-button` + `--color-button-text`.

PDF templates (dompdf) and Excel exports cannot use CSS variables: they take hex values from
`SettingController::pdfColors()`, shared to views as `$pc` (`$pc['brand']`, `$pc['ink']`, ...);
in ReportController use `self::xl('key')`.

## Code structure
No service classes: business logic lives in the controllers (app/Services was removed on purpose).
Shared rules are public static methods of the controller that owns them:
- salary rules — `PayrollSetupController` (`salarySettings()`, `salaryBreakdown()`, `recalculateSalaries()`)
- monthly payroll — `PayrollRunController::monthLines()` is the one place a month's salary line is computed
  (gross + bonuses + additions − deductions − unpaid leave − PF − loan installments). Processing a month
  freezes it into `payroll_run_items` and posts Dr expense / Cr salary payable, PF payable, employee loans;
  `SalaryPaymentController` then pays it (partial, any Liquid Asset account): Dr salary payable / Cr cash-bank.
  Bonus types, monthly bonus grants and additions/deductions — `SalaryAdjustmentController`;
  advance salary / loans — `EmployeeLoanController` (`recover()`, `reverseRun()` used by the run).
- leave rules — `LeaveController` (`workingDates()`, `balances()`, `leaveLedger()`, `unpaidDays()`)
- theme colors — `SettingController` (`themeCss()`, `pdfColors()`, `THEME_FIELDS`)
- reports — private `build*()` methods in `ReportController`; `ReportController::buildSalarySheet()` is
  public static because Pay Salary uses it. Accounting statements + year-end closing — `AccountingReportController`
  (`isClosedDate()`; the `BlockClosedPeriod` middleware refuses saves dated in a closed year).
- attendance — `AttendanceController` (`evaluate()`, `monthSummary()`, `salaryLines()` → salary_adjustments rows
  with remarks prefix `Attendance:`); ZKTeco devices — `AttendanceDeviceController` + `Admin\Zk\ZkClient`;
  payslips — `PayslipController`.
- stock — `StockController` (`onHand()`, `allocate()` = batches earliest-expiry-first, `move()`, `batchFor()`);
  sales and purchases call it. Transfers write explicit-branch stock rows ('Transfer Out' / 'Transfer In').
- VAT, loyalty points, credit limits — `CustomerProgramController` (`vat()`, `redeemValue()`, `earnPoints()`,
  `creditCheck()`, `addPoints()`), called from `SaleController::store()`.
- system settings (key/value, secrets encrypted) — `SystemSettingController::get()/put()`; SMS (BulkSMSBD) —
  `SmsController::send()/render()/enabled()`; backups — `BackupController` (`php artisan backup:run`);
  activity log — `LogActivity` middleware + `ActivityLogController`.

## AJAX in views
Every request is written as `$.ajax({ url: "{{ route('...') }}", type: 'GET'|'POST', dataType: 'json', data: ...,
success: function(response) { // alert(JSON.stringify(response)); ... }, error: function(xhr) { // alert(JSON.stringify(xhr)); ... } })`
— never `$.post` / `$.get` / `fetch` / `.done().fail()` chains. Keep both debug alerts, commented, as the first line of
`success` and `error` (uncomment them to debug). DataTables `ajax:` option objects are the only exception.

## Branches
Branch-wise login: the login form requires a branch; `BranchController::loginProblem()` allows only branches the
user may use. The choice is stored once in the session as `loggedInBranchId` (`BranchController::SESSION_KEY`) and
cannot be changed while signed in (no switcher, no "all branches"); to see another branch the user signs out and in
again. `SetBranch` signs out any session without a valid logged-in branch.

Every transactional table has `branch_id`. A BEFORE INSERT trigger per table fills it from the MySQL variable
`@fpos_branch`, which `SetBranch` sets each request to the logged-in branch — so insert code does not set branch_id
(stock transfers do, explicitly). Reads: wrap every list/report query in `BranchController::scope($query,
'alias.branch_id')`. Party dues count only the logged-in branch's payments; a party's opening balance belongs to the
main branch (`BranchController::isMainBranch()`). Cash & bank figures come from the branch's movements (the Cash &
Bank Book engine in ReportController), never from company-wide `chart_of_accounts.current_balance`.
Company-wide screens (Trial Balance, Balance Sheet, year-end closing, Chart of Accounts setup, branches, settings,
users/roles, payroll setup, backups, email/SMS) open only in the main branch: route middleware `main.branch`, or the
route-name list `MainBranchOnly::HEAD_OFFICE` checked in the web group.
When adding a new transactional table, add `branch_id` and a trigger like the ones in the create_branches migration.

## Business reports
`BusinessReportController` (Reports Center): every report is a `build<Name>()` method returning one shape
(cards, columns with types, rows or groups, totals, notes) so one page, one PDF and one Excel writer serve all
of them. Register a new report in `REPORTS` (and `GROUP_BY` if it groups); the sidebar Reports menu lists them from it.
Net sales are always without VAT. Daily cash closing (`cash_day_closings`, one row per branch per day) is built from
the Cash & Bank Book engine (`ReportController::liquidAssetMovements()`): all Cash-method ledgers form the cash ledger,
other methods are shown alongside. Opening = last saved day's closing_amount + net cash of unsaved days in between
(the books before the first save), closing = opening + today. Save/clear re-flow every later saved day.

## Scheduler
`app/Console/Kernel.php`: daily backup, activity-log pruning, ZKTeco sync every 30 min. On Windows run
`php artisan schedule:run` every minute from Task Scheduler (start in the project folder).
