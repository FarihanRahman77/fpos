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

Colors come from Settings > Theme Settings (`admin.settings.theme`). `SettingController::THEME_FIELDS`
lists every editable color token with its light + dark default (= the "Default theme: Midnight" block at the
end of tokens.css; keep the two in sync) and a derive rule (hover/soft/readable-text shades worked out from
the color they follow). `settings.theme_colors` = `{"light": {key: hex}, "dark": {...}}`, only changed values
(`themeStored()` still reads the old flat map). `themeCss()` prints only tokens that differ, for
`html:not([data-theme="dark"])` and `html[data-theme="dark"]`, plus `--radius-button` (settings.button_radius:
5/10/15px); shared as `$themeCss` (AppServiceProvider), printed by the admin header and the login page.
Presets: `THEME_PRESETS` (by sidebar + top bar family; expanded by `themePresetsFull()`).
When adding a new colored element, use a semantic token (`--color-accent`, `--color-button`, `--color-surface`,
`--color-text-muted`, `--color-success`, `--color-topbar*`, `--color-input-bg`, `--color-table-head*`,
`--color-hero-*`, ...) so it follows Settings too; a new themable token = a THEME_FIELDS entry + tokens.css.
Text on a filled accent uses `--color-button` + `--color-button-text`. Every button uses
`border-radius: var(--radius-button)` (circles by design excepted); pills (`--radius-pill`) are for badges/tags.

PDFs: every PDF is built with `SettingController::pdf($view, $data, $orientation, $paper)` (renders + draws
"Page X of Y") and its view `@extends('pdf.layout')` with a `$doc` array (title, number, date, status, meta,
info, signatures; see the comment in resources/views/pdf/layout.blade.php) and the `pdf.partials.*` blocks
(party, totals `['box' => [...]]`, words, notes, signatures). The design follows Theme Settings > PDF Template
(`settings.pdf_template`: `PDF_TEMPLATES` classic (default, black & white) / modern / minimal / corporate /
elegant; optional `settings.pdf_accent`): views get `$pdfT` (template hex palette + fonts, `pdfTheme()`) and
`$pdfCo` (company, logo/signature/watermark data URIs, `pdfCompany()`). dompdf has no CSS variables/flex/grid:
use the classes of pdf/partials/styles.blade.php and `$pdfT` hex values only. The employee ID card keeps its own
card format (PDF::loadView with `$pdfT`/`$pdfCo`). Excel exports and e-mails take hex values from
`SettingController::pdfColors()` (`$pc`); in ReportController use `self::xl('key')`.

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
- theme + PDFs — `SettingController` (`THEME_FIELDS`, `themeResolve()`, `themeCss()`, `pdf()`, `pdfTheme()`,
  `pdfCompany()`, `pdfColors()`)
- reports — private `build*()` methods in `ReportController`; `ReportController::buildSalarySheet()` is
  public static because Pay Salary uses it. Accounting statements + year-end closing — `AccountingReportController`
  (`isClosedDate()`; the `BlockClosedPeriod` middleware refuses saves dated in a closed year).
- attendance — `AttendanceController` (`evaluate()`, `monthSummary()`, `salaryLines()` → salary_adjustments rows
  with remarks prefix `Attendance:`); ZKTeco devices — `AttendanceDeviceController` + `Admin\Zk\ZkClient`;
  payslips — `PayslipController`.
- stock — `StockController` (`onHand()`, `allocate()` = batches earliest-expiry-first, `move()`, `batchFor()`);
  sales and purchases call it. Transfers write explicit-branch stock rows ('Transfer Out' / 'Transfer In').
- suspense / investor capital — `SuspenseController` (`ledgers()`, `totals()`, `statement()`); see "Suspense & investors".
- VAT, loyalty points, credit limits — `CustomerProgramController` (`vat()`, `redeemValue()`, `earnPoints()`,
  `creditCheck()`, `addPoints()`), called from `SaleController::store()`.
- money safety — `ReportController::cashAvailable()/ensureCash()`: every money-out save from a Cash-method ledger
  calls `ensureCash($accountId, $needed, $previousAmount)` inside its transaction (locks the ledger row; bank and
  mobile banking may go negative). Saves retry on deadlock / lock-wait via `Controller::retryOnDeadlock()` or
  `DB::transaction(fn, self::SAVE_ATTEMPTS)`; user-facing errors go through `Controller::safeError()`. The MySQL
  connection runs at READ COMMITTED (config/database.php) so a waiting save sees the balance the other one wrote.
- Chart of Accounts balances are shown from `AccountingReportController::balances()`, never `current_balance`.
- system settings (key/value, secrets encrypted) — `SystemSettingController::get()/put()`; SMS (BulkSMSBD) —
  `SmsController::send()/render()/enabled()`; backups — `BackupController` (`php artisan backup:run`);
  activity log — `LogActivity` middleware + `ActivityLogController`.

## Suspense & investors
Parties type 'Investor' = the owners (CRM › Investors, `party.index` 'Investor', permissions `investors.*`): never a
customer / supplier (no dues, pickers, POS); opening capital lives in `parties.opening_capital` (main branch), so their
`opening_balance` stays 0. Accounts › Suspense = `SuspenseController` (permissions `suspense.*`), table
`suspense_transactions`: each row debits `dr_account_id` / credits `cr_account_id` (`gl()` reads exactly that; the
Cash & Bank Book reads `account_id`, types `SuspenseController::CASH_BOOK_TYPES`). Paid / Withdraw = Dr Suspense Account,
Cr cash; Return = Dr cash, Cr Suspense; Adjustment = Dr expense (investor: or drawings), Cr Suspense; employee Deposit =
Dr cash, Cr Suspense (negative outstanding = owed to the employee); Investment = Dr cash, Cr Owner's Capital; Capital
Withdrawal = Dr Owner's Capital / Drawings, Cr cash. Return / Adjustment never beyond the working branch's outstanding
(`totals($type, $id, true)`); ledgers via `SuspenseController::ledgers()` (adds missing ones with `true`).

## AJAX in views
Every request is written as `$.ajax({ url: "{{ route('...') }}", type: 'GET'|'POST', dataType: 'json', data: ...,
success: function(response) { // alert(JSON.stringify(response)); ... }, error: function(xhr) { // alert(JSON.stringify(xhr)); ... } })`
— never `$.post` / `$.get` / `fetch` / `.done().fail()` chains. Keep both debug alerts, commented, as the first line of
`success` and `error` (uncomment them to debug). DataTables `ajax:` option objects are the only exception.

## Branches
Branch-wise login: the login form requires a branch; `BranchController::loginProblem()` allows only branches the
user may use. That WORKING branch is stored once in the session as `loggedInBranchId` (`BranchController::SESSION_KEY`)
and cannot be changed while signed in (no switcher). `SetBranch` signs out any session without a valid working branch.

Visibility: a user permitted in one branch sees only it; a user permitted in several (or `all_branches` = Yes = all
active branches) sees all of them TOGETHER in every list, report, dashboard and export (`BranchController::visibleIds()`,
`multiBranch()`). The working branch decides where new rows are saved, which stock is sold (`StockController::onHand`
for the working branch), which cash ledgers are paid from / closed, and which records may be changed.
- Reads: `BranchController::scope($query, 'alias.branch_id')` = visible branches.
- Changes (edit, delete, cancel, return, deliver, receive, pay, reverse) and working-branch forms/pickers:
  `BranchController::scopeOwn($q, 'alias.branch_id')` = working branch; call `BranchController::guardOwn($table, $id)`
  first (outside a catch-all try) so another visible branch's record is refused 403 "Sign in to <Branch> to change it".
  Viewing / printing a visible branch's record is allowed.
- Lists: `branch_tag($row->branch_id)` labels rows when the user sees several branches; `action_dropdown($items,
  $row->branch_id)` (JS: `actionDropdown(items, lockedTip)`) disables change items of other-branch rows.
- Party dues = payments of the visible branches; the opening balance (main branch's) counts when the main branch is
  visible (`mainVisible()`); checks before booking use the working branch (`partyDue($id, true)`, `isMainBranch()`).
- Cash & bank: the Cash & Bank Book engine (ReportController, `$ctx->branches` overrides the visible set) reads the
  visible branches' movements + their own ledgers; never `chart_of_accounts.current_balance`. Paying-account lists and
  rules (`getCoaChilds`, `ledgers()`, `coaExists`, `BranchController::ownLedgerRule()`) = shared + working branch's.
- Daily cash closing, attendance marking, payroll processing / pay salary, loans and leave changes: working branch only.
Every transactional table has `branch_id`; a BEFORE INSERT trigger per table fills it from `@fpos_branch`, which
`SetBranch` sets each request to the working branch — insert code does not set branch_id (stock transfers do).
Company-wide screens (Trial Balance, Balance Sheet, year-end closing, Chart of Accounts setup, branches, settings,
users/roles, payroll setup, backups, email/SMS) open only when the working branch is the main branch
(`isMainBranch()`): route middleware `main.branch`, or `MainBranchOnly::HEAD_OFFICE` checked in the web group.
When adding a new transactional table, add `branch_id` and a trigger like the ones in the create_branches migration.

## Business reports
`BusinessReportController` (Reports Center): every report is a `build<Name>()` method returning one shape
(cards, columns with types, rows or groups, totals, notes) so one page, one PDF and one Excel writer serve all
of them. Register a new report in `REPORTS` (and `GROUP_BY` if it groups); the sidebar Reports menu lists them from it.
Net sales are always without VAT. Daily cash closing (`cash_day_closings`, one row per branch per day) is built from
the Cash & Bank Book engine (`ReportController::liquidAssetMovements()`): all Cash-method ledgers form the cash ledger,
other methods are shown alongside. Opening = last saved day's closing_amount + net cash of unsaved days in between
(the books before the first save), closing = opening + today. Save/clear re-flow every later saved day.

## Environment
The config is cached (`bootstrap/cache/config.php`): on Windows/Apache, threaded PHP can lose `.env` values between
concurrent requests (random 500s: "production", user "forge", no app key). After editing `.env` run
`php artisan config:cache`. Never call `env()` outside `config/` — it returns null once the config is cached.

## Scheduler
`app/Console/Kernel.php`: daily backup, activity-log pruning, ZKTeco sync every 30 min. On Windows run
`php artisan schedule:run` every minute from Task Scheduler (start in the project folder).
