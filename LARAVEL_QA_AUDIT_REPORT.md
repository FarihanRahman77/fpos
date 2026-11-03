# FPOS: Laravel QA Audit Report

| | |
|---|---|
| Application | FPOS (POS / inventory / accounting / HR-payroll ERP), `C:\xampp\htdocs\FPOS` |
| Report date | 2026-11-04 (engagement 2026-11-02 to 2026-11-04) |
| Audience | Farhan (owner / developer) and future developers |
| Code state | Working tree on `main`. **Nothing is committed.** All fixes below are uncommitted changes on top of commit `127eb33`. |
| Scratchpad | All QA scripts and logs: `C:\Users\user\AppData\Local\Temp\claude\c--xampp-htdocs-FPOS\43af88cd-5d15-44a6-9e4c-5c92afb4e15b\scratchpad\` (written below as `<SCRATCH>`). It is a temporary folder: copy anything you want to keep. |

**How to read this report.** Every result is marked as one of:

- **VERIFIED**: run or inspected during the engagement, with evidence named (test, script, log, SQL or file line).
- **ASSUMED**: an inference from code reading or from the lead's notes that was not re-run.
- **NOT RUN / BLOCKED**: not tested, with the reason.

All values are final (filled in by the lead on 2026-11-04).

This audit found and fixed many defects. It does **not** show that the application is bug-free. Coverage is good on money paths and branch isolation, thinner on HR edge cases, devices and anything that needs real external services.

---

## 1. Executive summary

**State of the app.** FPOS is a Laravel 10 monolith with 410 routes covering POS and sales, quotations, sale orders and deliveries, purchases, stock (batches, FEFO, transfers, adjustments), parties and payments, expenses and bills, journals, a derived Trial Balance / Balance Sheet / P&L with year-end closing, daily cash closing, a 21-report Reports Center, HR (attendance with ZKTeco, leave, payroll, loans, payslips), and multi-branch operation with branch-wise login.

At the start of the engagement several core money workflows were broken:

- every sale return failed;
- purchase returns raised the supplier's due instead of lowering it;
- the accounting statements ignored sales, purchases, COGS, receivables, payables and inventory;
- sale orders were charged in full on save, and deliveries never became sales;
- stock could be oversold.

There were also basic control gaps:

- role permissions existed in the UI but were never enforced on routes;
- deleted or inactive users could still log in;
- `/register` was public;
- the whole project folder, including `.env` and database backups, could be downloaded through XAMPP;
- `php artisan test` would have wiped the live database.

All of these are now fixed and covered by regression tests or scripts (section 7).

**Current verified evidence.**

- PHPUnit (final run, on a fresh `fpos_testing` built from `database/schema/mysql-schema.sql` plus the later migrations): **OK (233 tests, 1984 assertions), 0 failures, 0 errors, 0 skipped**, time 2:11.
- Branch-isolation script: 2,450 checks, 0 failures (`<SCRATCH>\bv_iso3.log`, `bv_iso4.log`).
- Accounting tie-out, per branch: all checks PASS (`<SCRATCH>\bv_regress2.log`).
- Saved cash-closing chain: 35 of 35 consistent.
- Data integrity sweep: 0 orphans, 0 unbalanced journals and 0 negative stock in current documents. 6 legacy data issues remain open (section 10).
- Browser pass (final, real headless Chrome, 88 pages × 1440×900 and 390×844): 0 JS exceptions, 0 console errors, 0 failed requests other than the intended 403s (head-office pages from a non-main branch, another branch's order), no visible broken images, no real horizontal overflow (flagged items are scroll strips/tables inside scroll containers). Head-office pages re-checked signed in to Main: all load.

**Top open risks.**

1. `APP_DEBUG=true` / `APP_ENV=local` in `.env` (SEC-03). Acceptable on this dev box, unacceptable in production.
2. Secrets may have been exposed while the project root was downloadable (SEC-02). Rotate them if this PC was ever reachable from a LAN or Wi-Fi.
3. Backups have never run on a schedule. Windows Task Scheduler is not configured for `php artisan schedule:run`, and no restore test has been done.
4. Known-vulnerable dependency versions (`composer audit`: laravel/framework, symfony/*, league/commonmark).
5. Legacy data the owner must decide on: a Suspense balance of about ৳8.9 M from early dev documents without ledger rows, plus orphan rows DATA-03 to DATA-07.
6. The database contains test data and test logins (section 13). It must be cleaned, or production must start from a fresh database.
7. No HTTPS, the session cookie is not `secure`, and there was no load test.

**Release recommendation.**

- **Not ready for production until:**
  - `APP_DEBUG=false` / `APP_ENV=production` plus `config:cache`;
  - secrets rotated (if exposure was possible);
  - Apache docroot pointed at `public/`, or the root `.htaccess` kept;
  - `composer update` within 10.x, with the full suite re-run green;
  - scheduler configured, and one backup restored successfully on another machine;
  - the owner's decision on legacy data (DATA-02 to DATA-07), and a clean production database without test users;
  - the manual UAT in section 14 signed off for at least the cashier, purchase/returns and month-end roles.
- **Ready for a controlled pilot** (one branch, real staff, daily backup verified, owner reviewing cash closing and TB daily) after the items above, with HTTPS on any machine reachable over a network.

---

## 2. Architecture & module inventory

**Stack.** PHP 8.2.12 (ZTS) and Laravel 10.50.3 on Apache (XAMPP, Windows), MariaDB 10.4.32, dompdf for PDFs, PhpSpreadsheet for Excel, jQuery / DataTables / toastr / SweetAlert2 in Blade views.

**Design rules** (from `CLAUDE.md`):

- There are no service classes. Shared business rules are public static methods on the owning controller.
- Every transactional table has `branch_id`, filled by a `BEFORE INSERT` trigger from `@fpos_branch`. The `SetBranch` middleware sets that variable per request.
- Reads use `BranchController::scope()` (visible branches). Changes use `scopeOwn()` and `guardOwn()` (working branch only).

**Routes** (VERIFIED, `php artisan route:list --json` → `<SCRATCH>\qa\routes_final.json`):

- 410 routes: 244 GET/HEAD, 163 POST, 1 PUT, 1 PATCH, 2 DELETE.
- 31 routes carry `main.branch` middleware. Further head-office screens are checked through `MainBranchOnly::HEAD_OFFICE` in the web group.
- Permission enforcement is `CheckPermission` + `PermissionController::ROUTE_MAP`, applied in the web group rather than per route.

| Module | Main controllers | Routes (prefix: count) | Main tables | Risk |
|---|---|---|---|---|
| POS & sales, returns | `SaleController`, `CustomerProgramController` (VAT, loyalty, credit limit), `StockController` | admin/sales: 30 | sales, sale_items, sale_returns, payments, stocks, loyalty_transactions | **High**: money, stock, VAT |
| Quotations → sale orders → delivery invoices | `SaleQuotationController`, `SaleController` (orders) | admin/quotations: 16 (orders under admin/sales) | sale_quotations, sale_orders, sales | **High**: order model changed in this audit |
| Purchases & purchase returns | `PurchaseController` | admin/purchases: 18 | purchases, purchase_items, purchase_returns, payments, stocks, batches | **High**: supplier dues, stock in |
| Stock (batches, FEFO, transfers, adjustments) | `StockController`, `BarcodeController`, `ProductController` | admin/stock: 15, products: 10, barcode: 4 | stocks, product_batches, stock_transfers, stock_adjustments | **High**: on-hand, row locks |
| Masters | Attribute, AttributeType, Brand, Category, Unit, Party controllers | 6–7 each | products, product_variants, parties | Low |
| Payments, expenses, bills, transfers, journals | `PaymentController`, `ExpenseController`, `BillController`, `AmountTransferController`, `JournalController` | 5–7 each | payments, expenses, bills, internal_amount_transfers, journals, journal_details | **High**: cash drawer guard, ledgers |
| Accounting statements & year-end | `AccountingReportController`, `ChartOfAccountController`, `BlockClosedPeriod` middleware | admin/accounting: 20, chart-of-accounts: 9 | chart_of_accounts, fiscal_years (derived from operational tables) | **High**: rebuilt in this audit |
| Reports, Reports Center, cash closing | `ReportController`, `BusinessReportController` | admin/reports: 24, business-reports: 8 | cash_day_closings (+ read-only over all) | **High**: cash closing chain, isolation |
| HR: attendance, devices | `AttendanceController`, `AttendanceDeviceController`, `Admin\Zk\ZkClient` | admin/attendance: 23 | attendances, attendance_devices | Medium: real device not tested |
| HR: leave, holidays | `LeaveController`, `LeaveSetupController`, `HolidayCalendarController` | admin/leave: 26 | leave_*, holidays | Medium |
| HR: payroll, loans, payslips | `PayrollSetupController`, `PayrollRunController`, `SalaryAdjustmentController`, `SalaryPaymentController`, `EmployeeLoanController`, `PayslipController`, `EmployeeController` | payroll-setup: 9, payroll-runs: 3, payroll-adjustments: 10, salary-payments: 5, employee-loans: 7, payslips: 6, employee: 10 | employees, payroll_runs, payroll_run_items, salary_payments, employee_loans | **High**: salary money |
| Branches, users, roles, permissions | `BranchController`, `UserController`, `RoleController`, `PermissionController` | branches: 7, users: 7, roles: 8, permissions: 6 | branches, branch_user, users, roles, permissions | **High**: authorization |
| Settings, theme, PDF template, SMS, email, backups, activity log | `SettingController`, `SystemSettingController`, `SmsController`, `BackupController`, `ActivityLogController` | admin/system: 22, settings: 5 | settings, system_settings, sms_logs, activity_logs | Medium: secrets, backups |
| Auth (Breeze, customized) | `Auth\*`, `LoginRequest` | login, forgot/reset-password, verify-email, profile | users, password_reset_tokens | **High**: branch-wise login |

**Critical workflows** (the ones whose failure costs money or exposes data):

1. POS sale: stock check under row lock, VAT and discount, loyalty, payment, cash ledger.
2. Sale and purchase returns: restock or unstock, reverse VAT and loyalty, adjust due or refund.
3. Quotation → order → delivery invoices: the advance is applied once, and the invoices add up to the order total.
4. Purchase with batch, expiry and VAT, then supplier payment.
5. Daily cash closing per branch: opening = last saved closing + unsaved days, plus re-flow of later days.
6. Month-end payroll: process (freeze + post), pay (partial, any liquid account), loans recovered.
7. TB / BS / P&L and year-end closing; closed-period block on back-dated saves.
8. Branch isolation on every read, write, PDF and Excel export.
9. Login with branch, roles and permissions, head-office-only screens.

---

## 3. Testing environment & limitations

**Environment** (VERIFIED):

- Windows 10 Pro, XAMPP Apache with threaded (ZTS) PHP 8.2.12, Laravel 10.50.3, MariaDB 10.4.32.
- Dev database `fpos`, about 10 MB.
- Test database `fpos_testing`, built from `database/schema/mysql-schema.sql` plus migrations.
- The app is served at `http://localhost/FPOS/public/`.

**Data.** The owner asked for realistic test data, and it was seeded into the dev database `fpos`:

- 4 branches: Main (id 1), Dhanmondi (36), Uttara (37), Chattogram (38).
- 245 live products, 214 sales, 148 purchases, 104 employees, an October payroll, and 35 saved cash closings.
- Seed scripts are in `<SCRATCH>\seed\`.
- A backup was taken before seeding: `storage/app/backups/fpos-20261102-162357.sql.gz`.
- **Cash and bank ledgers are negative in some branches.** This comes from seeded salaries and loans, not from an app defect.

**Limitations.**

- Several QA agents worked concurrently on the same machine and database server (migrations, test suites, crawls). Wall-clock timings are noisy by 10–100×. For example, a primary-key lookup took 8–264 ms against a normal ~0.2 ms. **Query counts, not timings, are the reliable performance signal** (section 12).
- Some files changed while they were under test. Results in this report come from the last runs after the code settled, as named per item.
- The MariaDB InnoDB buffer pool is the XAMPP default of 16 MB.

**Not tested (NOT RUN / BLOCKED):**

| Area | Status | Reason |
|---|---|---|
| Real ZKTeco device | NOT RUN | No device available. `ZkClient` was exercised with a fake (`<SCRATCH>\zk_fake.php`, `zk_parse_test.php`), plus CSV/TXT/XLSX punch imports. |
| Real SMTP e-mail, real SMS (BulkSMSBD) | NOT RUN | Faked (`MAIL_MAILER=array` in tests; SMS not sent). Credentials were not used. |
| Payment gateways | N/A | The app has none. |
| Load / stress testing | NOT RUN | No staging server; the dev box was contended. |
| Real multi-user concurrency | Simulated only | Deadlock retry and row locks were tested with a simulated deadlock and sequential requests, not with many parallel cashiers. |
| HTTPS, secure cookies | NOT RUN | No certificate on the dev box. |
| Backup **restore** onto a clean server | NOT RUN | Only the backup itself was timed (PERF-08). |
| `composer update` + retest | NOT RUN | Changing `vendor/` during the multi-agent engagement was avoided. |
| Older browsers, real phones | NOT RUN | Headless Chrome at 1440×900 and 390×844 only. |
| Printers / barcode scanners / cash drawer hardware | NOT RUN | No hardware. |

---

## 4. Tests executed & exact commands

All commands run from `C:\xampp\htdocs\FPOS` unless noted otherwise. `<SCRATCH>` is the scratchpad path given at the top.

**4.1 PHPUnit** (feature, unit, regression):

```bash
# Git Bash
export PATH="/c/xampp/mysql/bin:$PATH"   # the schema dump loader needs the mysql client
php vendor/bin/phpunit
```

```powershell
# PowerShell
$env:Path = "C:\xampp\mysql\bin;$env:Path"; php vendor/bin/phpunit
```

- `phpunit.xml` forces `DB_DATABASE=fpos_testing` and a separate `APP_CONFIG_CACHE=bootstrap/cache/config.phpunit.php` (`force="true"`).
- `tests/CreatesApplication.php` refuses to run against any database whose name does not end in `_testing`. VERIFIED: pointing it at `fpos` was refused.
- Partial suites used during the work are in `<SCRATCH>\qa\phpunit.{unit,sales,acct,branch,payroll}.xml`.
- Run one file with: `php vendor/bin/phpunit tests/Feature/SaleReturnTest.php`.

**Test files and counts** (VERIFIED by counting `function test_`):

| File | Tests | File | Tests |
|---|---|---|---|
| Feature/AccountingDocumentsTest | 10 | Feature/PermissionTest | 10 |
| Feature/AccountingStatementsTest | 6 | Feature/ProfileTest | 7 |
| Feature/Auth/AuthenticationTest | 13 | Feature/PurchaseWorkflowTest | 8 |
| Feature/Auth/EmailVerificationTest | 3 | Feature/RegressionTest | 5 |
| Feature/Auth/PasswordConfirmationTest | 3 | Feature/SaleReturnTest | 8 |
| Feature/Auth/PasswordResetTest | 4 | Feature/SaleWorkflowTest | 10 |
| Feature/Auth/PasswordUpdateTest | 2 | Feature/SecurityTest | 7 |
| Feature/Auth/RegistrationTest | 2 | Feature/StockTransferTest | 5 |
| Feature/BranchIsolationTest | 13 | Unit/AttendanceEvaluateTest | 10 |
| Feature/CashDayClosingTest | 10 | Unit/CashDayClosingChainTest | 10 |
| Feature/CashDrawerGuardTest | 12 | Unit/CustomerProgramTest | 16 |
| Feature/ExampleTest | 1 | Unit/ExampleTest | 1 |
| Feature/FiscalYearClosingTest | 10 | Unit/LeaveWorkingDatesTest | 6 |
| Feature/PayrollRegressionTest | 5 | Unit/PayrollMonthLinesTest | 9 |
| Feature/PayrollWorkflowTest | 13 | Unit/StockAllocationTest | 8 |

- Total: **233 test methods** (173 Feature, 60 Unit; includes `SuspenseTest`, 6, added last).
- Shared helpers: `tests/AppTestCase.php` and `tests/Concerns/{SeedsApp,SalesHelpers,AccountingHelpers,BranchHelpers,PayrollHelpers}.php`.
- `SeedsApp::resetAppStatics()` resets controller static caches between requests, which mirrors php-fpm behaviour.

**4.2 Scenario / regression scripts against the dev database** (writes rolled back):

| Script (in `<SCRATCH>`) | Command | What it checks | Last log |
|---|---|---|---|
| `bv_isolation_test.php` | `php <SCRATCH>\bv_isolation_test.php` | Every GET list / data / report / export, plus id tampering and writes, as single-branch, multi-branch and superadmin users. Codes in each response must belong to the visible branches; xlsx files are unzipped and scanned. | `bv_iso4.log` |
| `bv_p_regress2.php` | `php <SCRATCH>\bv_p_regress2.php` | Runs `branch_strict_test`, `cashday_test`, `sales_fix_test`, `orders_sales_test`, `acc_tieout`, `bv_cash_chain` | `bv_regress2.log` |
| `acc_tieout.php` | `php <SCRATCH>\acc_tieout.php [as_at]` | TB debits = credits; BS balances; P&L = BS profit; cash = Cash & Bank Book; AR/AP = party ledgers; inventory = stock valuation; revenue = net sales. Per branch, plus all branches = sum of branches. | in `bv_regress2.log` |
| `bv_cash_chain.php` | via `bv_p_regress2.php` | Shown opening and closing = stored, for every saved cash closing | in `bv_regress2.log` |
| `jscheck.php` | `php <SCRATCH>\jscheck.php <uri> [...]` | Renders each page, extracts inline `<script>` blocks, runs `node --check` | `reg3.log` ("all clean") |
| `crawl2.mjs` | `node <SCRATCH>\crawl2.mjs <SCRATCH>\pages_final.txt <outdir> 1440 900` (and `390 844`) | Real headless Chrome: logs in through the form, visits 88 pages; records JS exceptions, console errors, HTTP ≥ 400, error toasts, broken images, horizontal overflow, screenshots | `crawl_final_desk.log`, `crawl_final_desk\` |
| `qa\q.php` + `qa\s1.sql`…`s14.sql` | `php <SCRATCH>\qa\q.php` | Data-integrity SQL (section 10) | `data_perf_findings.md` |
| `qa\perf.php`, `qa\detail.php` | `php <SCRATCH>\qa\perf.php` | Every parameterless GET route, per branch, with query counts and EXPLAIN | `qa\perf_b*_{before,after}.tsv` |
| `qa\chain1.php` | `php <SCRATCH>\qa\chain1.php` | Cash-closing chain per branch in fresh processes | `data_perf_findings.md` |
| Security scripts in `<SCRATCH>\qa\` | `php <SCRATCH>\qa\<name>.php` | See section 11 | `security_findings.md` |
| `dues_snap.php` / `dues_compare.php` | before/after snapshots | Party dues before and after the sale-order data migration | `dues_before.json`, `dues_after.json` |

**4.3 Other commands:**

- `php artisan route:list --json`
- `composer audit --locked`
- `npm audit`
- `curl -D - http://localhost/FPOS/public/login` (headers)
- `curl http://localhost/FPOS/.env` (exposure check, now 403)
- `php artisan backup:run --no-uploads` (timing)

---

## 5. Results

**Final PHPUnit run** (VERIFIED by the lead). Command, on a fresh `fpos_testing` built from `database/schema/mysql-schema.sql` plus the later migrations:

```bash
export PATH="/c/xampp/mysql/bin:$PATH"; php vendor/bin/phpunit --colors=never
```

Result: `OK (233 tests, 1984 assertions)`, 0 failures, 0 errors, 0 skipped, time 2:11.

**5.1 Unit tests** (VERIFIED, final run). 60 unit tests, all passing. They cover:

- VAT (exclusive, inclusive, per-product rate, discount spread);
- loyalty earn and redeem;
- credit limit;
- stock `onHand` / `allocate` (FEFO, shortfall, other branches);
- attendance evaluation (grace, half-day, overtime, night shifts across midnight);
- leave working dates (weekend, holidays, year boundary);
- payroll `monthLines()` (PF, unpaid leave, bonuses, loan cap, mid-month joiner and leaver);
- the cash-closing chain.

**5.2 Feature / integration tests** (VERIFIED, same run). 167 feature tests, all passing. They cover:

- full HTTP flows for sales, returns, purchases, transfers, accounting documents, statements, fiscal-year closing, cash closing, the cash-drawer guard, payroll, permissions, branch isolation, CSRF and validation;
- the Breeze auth tests rewritten for branch-wise login and the removed registration.

**Baseline before the work:** 25 stock Breeze tests, 10 pass and 15 fail. The failures were legitimate: the app had deliberately changed login, registration and profile behaviour. A fresh test database could not be built at all, because 16 core tables had no create migration.

**Intermediate result:** a 215-test run had one failure, TEST-01 (purchase delete answered 422 instead of 404). It is fixed (`PurchaseController.php:1177`, `abort_unless(..., 404)`), and the next run was `OK (233 tests, 1984 assertions)`.

**5.3 Regression** (VERIFIED):

- Each P1 workflow defect has a named regression test (section 7).
- Scenario bundle `bv_regress2.log`: 78 PASS lines, 0 FAIL, ending "ALL PASSED". It includes:
  - order → delivery invoice → advance applied once → invoices = order total;
  - over-delivery refused;
  - quotation → sale and quotation → order totals equal;
  - partial and full returns with credit or cash refund;
  - customer credit pay-back;
  - receipt applied to an invoice and reopened on delete;
  - product search;
  - the accounting tie-out;
  - the cash chain (35 saved closings, 0 mismatches).

**5.4 Accounting tie-out** (VERIFIED, `bv_regress2.log`, per branch). For example, in Dhanmondi:

| Check | Value |
|---|---|
| TB debits = credits | 7,922,005.46 |
| BS assets = liabilities + equity | 4,630,170.79 |
| Inventory = Stock Valuation | 4,374,094.00 |
| AR = customer ledger dues | 65,090.05 |
| Cash & bank = Cash & Bank Book (5 ledgers) | 188,992.64 |

- Uttara and Chattogram pass the same checks.
- "All branches = sum of branches" difference is 0.00.
- An earlier tie-out (`reg_tieout.log`, 2026-11-02 00:10) showed a supplier ledger mismatch for "Tonmoy Dalal". It predates the purchase-return sign fix and its data migration (DEF-05), and later runs pass.

**5.5 Branch isolation** (VERIFIED):

- `bv_isolation_test.php`: the first run had 2,439 pass / 10 fail; after the fixes, 2,450 pass / 0 fail (two consecutive runs).
- `BranchIsolationTest` (13 tests) passes.
- The security IDOR script tried 30 GET routes (including PDFs) and 24 POST id-routes across branches: 0 leaks.

**5.6 Browser** (VERIFIED, final pass on 2026-11-04 with all fixes in place):

- Final crawl (`crawl2.mjs`, logs `crawl_final_desk.log`, `crawl_final_phone.log`, `crawl_main_desk`, `crawl_main_phone`): 88 pages at 1440×900 and 390×844, logged in through the real form. Desktop 70/88 and phone 60/88 clean on the first pass; every remaining flag was reviewed: 15 pages were the intended 403 (head-office screens while the crawler was signed in to Chattogram; another branch's order delivery) and all load when signed in to Main; the others were `<img src="">` previews inside hidden modals or scroll strips (POS category chips, dashboard quick actions on phones, setup tab bars, DataTables inside `.dt-scroll`) that the overflow heuristic counts. 0 JS exceptions, 0 console errors, 0 failed requests otherwise.
- Screenshots reviewed by the lead: dashboard (Midnight preset, light) desktop and phone, POS (Chattogram stock, in-stock first), PDF templates (Classic, Modern) rendered via pdf.js.

- Earlier full crawls of all ~88 pages at 1440×900 and 390×844 (`crawl_final_desk`, `crawl_phone`, `crawl_cd_phone`): 0 JS exceptions and 0 failed requests.
- `jscheck.php` over every page's inline scripts: all clean.

**5.7 Security** (VERIFIED, section 11):

- 235 GET routes return 401 without a session.
- `smoke.php`: 144 pages with 0 missing security headers.
- `xss.php`: payload stored in 89 text columns, 301 URLs crawled, 0 unescaped hits.
- `validation.php`: 238 malformed requests all returned 422, after SEC-13 was fixed.

**5.8 Data integrity and performance:** see sections 10 and 12.

---

## 6. Pass / fail / blocked / not-run counts

| Suite / activity | Total | Pass | Fail | Blocked | Not run | Evidence |
|---|---|---|---|---|---|---|
| PHPUnit (final, fresh `fpos_testing`, 2:11) | 233 tests / 1,984 assertions | 233 | 0 (0 errors) | 0 | 0 (0 skipped) | final run (lead), `phpunit --colors=never` |
| PHPUnit (last observed, 2026-11-03 23:46) | 227 tests / 1,864 assertions | 227 | 0 | 0 | 0 | `<SCRATCH>\phpunit.log` |
| PHPUnit baseline (before work) | 25 | 10 | 15 | n/a | n/a | lead notes |
| Branch isolation script | 2,450 | 2,450 | 0 | 0 | 0 | `bv_iso3.log`, `bv_iso4.log` |
| Scenario regression bundle | 78 checks | 78 | 0 | 0 | 0 | `bv_regress2.log` |
| Accounting tie-out (per branch + combined) | all listed checks | all | 0 | 0 | 0 | `bv_regress2.log` |
| Cash-closing chain | 35 saved days | 35 | 0 | 0 | 0 | `bv_regress2.log` |
| Browser crawl (final) | 88 pages × 2 sizes | all (after review) | 0 | 0 | 0 | `crawl_final_*.log`, `crawl_main_*` |
| Inline-JS syntax check | all pages | all | 0 | 0 | 0 | `reg3.log` |
| Security: unauthenticated GET | 235 routes | 235 (401) | 0 | 0 | 0 | `security_findings.md` |
| Security: IDOR id-routes | 54 | 54 | 0 | 0 | 0 | `qa\idor.php` |
| Security: XSS crawl | 301 URLs | 301 | 0 | 0 | 0 | `qa\xss_out.txt` |
| Security: bad-input requests | 238 | 238 (after fix) | 1 before fix (SEC-13) | 0 | 0 | `qa\validation_out.txt` |
| Security: header smoke | 144 pages | 144 | 0 | 0 | 0 | `qa\smoke.php` |
| Data-integrity checks | 20 check groups | 14 clean | 6 with findings (DATA-02…07) | 0 | 0 | section 10 |
| Performance sweep | 216–220 requests × 2 branches | completed | n/a | 0 | 0 | `qa\perf_*.tsv` |
| Load test | n/a | | | | NOT RUN | no staging |
| Real ZKTeco / SMTP / SMS | n/a | | | | NOT RUN | no device / faked |
| HTTPS / secure cookie | n/a | | | | NOT RUN | no certificate |
| Backup restore | n/a | | | | NOT RUN | to do before go-live |

---

## 7. Defects

**Severity scale:**

- **P0**: critical security or data loss.
- **P1**: major workflow, financial or authorization defect.
- **P2**: important, with a workaround.
- **P3**: minor or hardening.

Source IDs (SEC-, DATA-, PERF-, TEST-) refer to `<SCRATCH>\qa\security_findings.md`, `data_perf_findings.md` and `test_findings.md`.

**Status values:**

- **Fixed**: code or data changed and re-verified.
- **Open**: not changed.
- **Owner**: needs the owner's decision or action.

Severity was assigned by the lead or the source agent, except where marked "(sev. assigned here)". Line numbers are given where known; "~" means approximate.

### 7.1 Security and access control

| ID | Sev | Module | File:line | Reproduction | Expected | Actual (before) | Root cause | Fix | Regression test / evidence | Status |
|---|---|---|---|---|---|---|---|---|---|---|
| DEF-01 (SEC-01) | P0 | Deployment | project root (no `.htaccess`) | `curl http://localhost/FPOS/.env` | 403; only `public/` served | 200 for `.env`, `laravel.log` (7.4 MB), `storage/app/backups/` listing (full DB dumps), `.git/config`, `vendor`, `routes/web.php` | XAMPP docroot = `htdocs` with `Options Indexes`; no vhost to `public/` | New root `.htaccess` (deny all, `-Indexes`); `public/.htaccess` re-allows | curl re-check: all 403, `public/` 200 | Fixed |
| DEF-02 (SEC-02) | P0 | Deployment | `.env` | consequence of DEF-01 | secrets never exposed | APP_KEY, DB, SMTP, SMS keys were downloadable while Apache listened on all interfaces | as DEF-01 | Rotate credentials + APP_KEY if the PC was ever reachable on a network | n/a | **Owner** |
| DEF-03 | P0 | Test infrastructure | `phpunit.xml`, `tests/CreatesApplication.php` | `php artisan test` with the cached config | tests use their own database | tests would run `RefreshDatabase` against live `fpos` (no test DB configured; the cached config points at `fpos`) and wipe it (sev. assigned here; the lead called it critical) | no test DB config; config cache ignores phpunit env | `phpunit.xml` forces `DB_DATABASE=fpos_testing` and a separate `APP_CONFIG_CACHE`; `CreatesApplication` refuses non-`*_testing` DBs | VERIFIED: pointing at `fpos` is refused | Fixed |
| DEF-04 | P1 | AuthZ | `app/Http/Middleware/CheckPermission.php` (new), `PermissionController::ROUTE_MAP` | Cashier opens `/admin/purchases`, settings, payroll | 403 | allowed: role permissions were shown in the UI but never checked | no route-level permission middleware | `CheckPermission` + `ROUTE_MAP`; role presets (Cashier, Manager, …); no-lockout guards (last active user, own superadmin); migration `2026_11_08_100000_seed_module_permissions` | `PermissionTest` (10), e.g. `test_cashier_gets_403_on_settings_and_settings_are_unchanged` | Fixed |
| DEF-05a | P1 | Auth | `LoginRequest`, `SetBranch` | Log in as a deleted or inactive user | refused | logged in | status / deleted not checked | refused at login; signed out mid-session | `test_inactive_user_cannot_log_in`, `test_deleted_user_cannot_log_in`, `test_regression_deleted_user_cannot_log_in`, `test_session_is_signed_out_when_user_is_deactivated` | Fixed |
| DEF-06 | P1 (sev. assigned here) | Auth | `routes/auth.php` | `GET /register` | not available on an admin app | anyone could create an account | Breeze default left on | route removed | `test_registration_route_is_not_available`, `test_regression_register_route_removed` | Fixed |
| DEF-07 (SEC-04) | P1 | Password reset | `Auth\PasswordResetLinkController`, `app/Providers/AppServiceProvider.php` | POST forgot-password with `Host: evil.example` | link on the real host | e-mailed link and token pointed at the attacker's host | URL built from the request Host; `TrustHosts` off (`app/Http/Kernel.php:17`) | `ResetPassword::createUrlUsing()` from `APP_URL` + script path | `host_inject.php` | Fixed |
| DEF-08 (SEC-03) | P1 | Debug / Ignition | `.env` | `GET /_ignition/health-check` | disabled in production | `can_execute_commands:true` unauthenticated; 500 JSON includes trace | dev settings | `IGNITION_ENABLE_RUNNABLE_SOLUTIONS=false` set in `.env` (VERIFIED). `APP_DEBUG=true` / `APP_ENV=local` remain (dev box) | env inspection | Partly fixed; **Owner** for production |
| DEF-09 (SEC-07) | P2 | AuthZ | `PermissionController::grantProblem()`, `UserController@store/update` | A non-superadmin with Users access sets `role_id=1` on self | 422 | became Superadmin | no check on the target role | only a Superadmin may grant Superadmin | `escalate.php` (3 attempts → 422; Superadmin still 200) | Fixed |
| DEF-10 (SEC-05) | P2 | Password reset | `PasswordResetLinkController`, `routes/auth.php:25,31` | forgot-password with unknown vs known e-mail | same response | different messages; no throttle; inactive accounts got links | default Breeze | same status always; links only to active users; `throttle:5,1` | `reset_enum.php` | Fixed (P3 remainder: `NewPasswordController` distinguishes user vs token) |
| DEF-11 (SEC-06) | P2 | Uploads | `SettingController.php:213…326`, `UserController.php:146,308`, `ProductController.php:1325` | Upload a PNG named `x.html` | stored as `.png` | stored as `.html` under `public/uploads` (stored XSS) | `getClientOriginalExtension()` | `guessExtension()`; `public/uploads/.htaccess` denies script / HTML / SVG | `upload_poc.php` | Fixed |
| DEF-12 | P2 (sev. assigned here) | HR uploads | `EmployeeController` (document / photo names) | Upload with a crafted file name | safe generated name | client file name used in the stored path | unsafe name handling | generated names | HR journey scripts (`<SCRATCH>\hr_journey_test.php`) | Fixed (ASSUMED from the lead's notes; no dedicated PHPUnit test) |
| DEF-13 (SEC-08) | P2 | XSS (DOM) | toastr usage, e.g. `SaleController.php:604-614`, `StockController.php:634` | Name a product `<img src=x onerror=…>`, trigger the "not enough stock" toast | text shown | script ran | toastr `escapeHtml=false` | `toastr.options.escapeHtml = true` in `resources/views/admin/includes/script.blade.php:19` | `test_regression_product_name_with_quotes_is_escaped_in_list_endpoints`, `test_regression_getcoachilds_escapes_names`, `xss.php` | Fixed |
| DEF-14 (SEC-09) | P2 | XSS (DOM) | `resources/views/admin/accounting/fiscal-years.blade.php:273` | Fiscal year named with HTML → Reopen | text | rendered as HTML in the SweetAlert title | `title:` renders HTML | `titleText:` (VERIFIED in file) | code inspection | Fixed |
| DEF-15 (SEC-10) | P2 | Headers | `app/Http/Middleware/SecurityHeaders.php` (new) | `curl -D -` | XFO, nosniff, Referrer-Policy | none; `X-Powered-By` exposed | not configured | global middleware | `smoke.php` (144 pages, 0 missing) | Fixed |
| DEF-16 (SEC-11) | P2 | Error handling | `SaleController` (16 catches), `PaymentController` (4), others | trigger an SQL error | generic message | raw SQL / paths in toasts | `catch (\Exception $e)` returned `getMessage()` | `Controller::safeError()` + `app/Traits/SafeJsonErrors.php`; Sale / Payment now 0 raw `getMessage()` returns (VERIFIED by grep) | `test_statement_rejects_bad_dates_without_leaking_sql` | Fixed |
| DEF-17 (SEC-12) | P2 | Dependencies | `composer.lock` | `composer audit --locked` | no advisories | laravel/framework (incl. a critical TokenGuard CVE; the app does not use that guard), symfony/http-foundation, symfony/mime, league/commonmark; `npm audit` 5 dev-only | outdated lock | `composer update` within 10.x + full retest | n/a | **Open** |
| DEF-18 (SEC-13) | P3 | Validation | `PermissionController.php:633,697` | `name[a][]=b` | 422 | 500 "Array to string conversion" | string cast of an array | first string value only | `validation.php` | Fixed |
| DEF-19 (SEC-14) | P3 | Auth | `routes/auth.php:20` | spray many e-mails from one IP | throttled | unlimited | throttle per e-mail + IP only | `throttle:30,1` per IP | `csrf_auth.php` | Fixed |
| DEF-20 (SEC-15) | P3 | Front-end supply chain | `script.blade.php:14,16,22` | n/a | pinned + SRI | `toastr.js/latest`, `sweetalert2@11` floating, no SRI (VERIFIED still present) | n/a | pin versions with `integrity`, or vendor them | n/a | **Open** |
| DEF-21 (SEC-16) | P3 | Session / password | `UserController.php:95,276` | n/a | min 8; secure cookie | `min:6`; cookie not `secure`; `SESSION_ENCRYPT=false`; a password change does not end other sessions | defaults | see section 15 | n/a | **Open** |
| DEF-22 (SEC-17) | P3 | Auth | `LoginRequest::authenticate()` | correct password on an inactive account | n/a | the "inactive" message confirms the password | UX trade-off | accepted (throttled) | n/a | Accepted |

### 7.2 Money, stock and workflow

| ID | Sev | Module | File:line | Reproduction | Expected | Actual (before) | Root cause | Fix | Regression test / evidence | Status |
|---|---|---|---|---|---|---|---|---|---|---|
| DEF-23 | P1 | Sale returns | `SaleController` (return store) | Return any item of any sale | return saved | **every** return failed with a SQL error | ambiguous column in a joined query | qualified columns | `test_regression_sale_return_has_no_ambiguous_sql` (+ 7 others in `SaleReturnTest`) | Fixed |
| DEF-24 | P1 | Purchase returns | `PurchaseController` (return); migration `2026_11_06_300000_fix_legacy_purchase_return_and_drop_cash_closings` | Return goods to a supplier | supplier due goes **down** | due went up (return posted as debit) | wrong sign | credit posting; legacy row VO-000026 corrected by migration | `test_regression_purchase_return_lowers_supplier_due`, `test_purchase_return_takes_stock_out_and_lowers_supplier_due` | Fixed |
| DEF-25 | P1 | Accounting statements | `AccountingReportController` | Open TB / BS after sales and purchases | TB balances; BS includes AR / AP / inventory / COGS; one cash figure | TB / BS ignored sales, purchases, COGS, AR, AP, inventory and opening balances; cash showed 3 different figures | statements read only journal / COA balances, not operational tables | statements rebuilt from operational tables (sales, purchases, stocks, payments, payroll, journals, opening balances) | `AccountingStatementsTest` (6), `test_chart_of_accounts_shows_the_trial_balance_figures`, `acc_tieout.php` per branch ALL PASSED | Fixed |
| DEF-26 | P1 | Sale orders | `SaleController` (orders / delivery); migration `2026_11_09_100000_sale_orders_become_sales` | Save an order with an advance, deliver it | due = delivered − advance; delivery becomes a sale invoice | full order value charged to the customer on save; deliveries never became sales (missing from sales, VAT, profit) | order modelled as a charge, not as a future sale | deliveries now create delivery invoices; advance applied once; data migrated (dev backup `pre_order_migration_backup.sql`) with before / after due check (`dues_snap.php`, `dues_compare.php`) | `orders_sales_test` in `bv_regress2.log` (13 PASS) | Fixed |
| DEF-27 | P1 | Stock | `SaleController::store()`, `StockController::onHand()/allocate()` | Sell more than on hand, or two lines of one product | 422, nothing written | sale saved, stock went negative | no stock check; no locking | check under row locks (`lockForUpdate`), lines summed per product | `test_oversell_is_refused_with_422_and_nothing_is_written`, `test_two_lines_of_the_same_product_are_summed_for_the_stock_check`; integrity: 0 negative on-hand | Fixed |
| DEF-28 | P1 | Branch isolation | `BranchController` (`scope`, `scopeOwn`, `guardOwn`), `ReportController` (PF ledger), `BusinessReportController` (cash closing, summaries) | Single-branch user opens the PF ledger by employee id; summaries; cash ledgers; cash closing while the rule was changing | only visible / working branch data | PF ledger by id leaked other branches; unscoped summaries; other branches' cash ledgers offered; cash closing read / cleared / re-flowed **all visible branches** (DATA-01: shown opening 197,246.00 vs stored 836,179.35; Clear deleted other branches' rows) | missing scope; `scope()` (visible) used on writes | `scopeOwn()` on every `cash_day_closings` read and write (`BusinessReportController.php:266,284,295,571,706`); guards on change routes | `BranchIsolationTest` (13), `test_closings_are_per_branch`, `test_each_branch_has_its_own_chain`, `bv_isolation_test.php` 2,450 / 0, `bv_cash_chain` 35 / 0 | Fixed |
| DEF-29 | P1 | Runtime / env | `bootstrap/cache/config.php` | Concurrent requests on Windows / Apache | stable | random 500s ("production", user "forge", no app key) | threaded PHP loses `.env` values between concurrent requests | config cached (`php artisan config:cache`); rule documented in `CLAUDE.md` (never `env()` outside `config/`) | no automated test (environmental); crawls after the fix show 0 failed requests | Fixed (operational rule) |
| DEF-30 | P1 | Payroll | `PayrollRunController::monthLines()` | Employee joins on the 15th | prorated salary and PF | full month paid | no proration | prorate by employed days (also for leavers) | `test_mid_month_joiner_is_prorated_including_pf`, `test_mid_month_leaver_is_prorated` | Fixed |
| DEF-31 | P1 | Payroll / loans | `PayrollRunController`, `EmployeeLoanController::recover()` | Process a month with a loan installment | Cr Employee Loans (receivable) | credited to the wrong ledger | wrong account mapping | posts to the employee loan ledger | `test_process_month_freezes_month_lines_recovers_loan_and_posts`, `test_reverse_run_without_payments_restores_loan_and_postings` | Fixed |
| DEF-32 | P1 | Ops / backups | `app/Console/Kernel.php`, Windows Task Scheduler | check `storage/app/backups` before the engagement | daily backups | **no backup had ever been produced** (scheduler not running) (sev. assigned here) | Task Scheduler not configured | backup command works (PERF-08); scheduler still to be configured | n/a | **Open / Owner** |
| DEF-33 | P2 | Reports / PF | `ReportController` PF ledger | Open the PF ledger | report | SQL column error (500) | wrong column | corrected query | `test_regression_pf_ledger_report_has_no_column_error` | Fixed |
| DEF-34 | P2 | Cash drawer | expense, bill, supplier payment, transfer, journal, purchase payment, refund, salary, loan paths | Pay more cash than the drawer holds | refused (bank may go negative) | cash drawer went negative | no availability check | cash availability from the Cash Book; bank unlimited | `CashDrawerGuardTest` (12) | Fixed |
| DEF-35 | P2 | Concurrency | `Controller::retryOnDeadlock()` (`Controller.php:39`) | Two saves hit an InnoDB deadlock | retried transparently | 500 to the user | no retry | retry helper on saves; READ COMMITTED isolation (`config/database.php:63`) | `test_a_deadlocked_save_is_retried_and_posts_once`, `test_retry_helper_gives_up_after_the_attempts_and_ignores_other_errors` (simulated) | Fixed |
| DEF-36 (DATA-09) | P2 | Time | `config/app.php:73` | Use the app between 00:00 and 06:00 Dhaka | "today" = local date | previous day (UTC) | `timezone => 'UTC'` while the DB is on Asia/Dhaka | `'timezone' => 'Asia/Dhaka'` (VERIFIED) | config inspection; no automated test | Fixed (existing `created_at` values stored in UTC keep that meaning) |
| DEF-37 | P2 | Attendance | `AttendanceController::evaluate()` | Night shift 22:00–06:00 | one attendance day | split across two days | midnight not handled | shift-aware pairing | `test_night_shift_crossing_midnight`, `test_night_shift_check_in_after_midnight_is_late_and_morning_out_is_overtime` | Fixed |
| DEF-38 | P2 | Attendance | `AttendanceController::evaluate()/monthSummary()` | Month with holidays, weekly off, leave | correct Absent count | wrong Absent days | holidays / off-days / leave not excluded | corrected rules | `AttendanceEvaluateTest` (e.g. `test_no_check_in_is_absent`, `test_work_on_holiday_or_weekly_off_is_all_overtime_but_not_on_leave`) | Fixed |
| DEF-39 | P2 | Purchases | `resources/views/admin/inventory/purchase/create.blade.php` | Enter batch, expiry, VAT on a purchase | saved | never saved | JS variables out of scope when building the payload | script scope fixed; supplier invoice no. added (migration `2026_11_09_300000`) | `<SCRATCH>\fb_batch_test.php`, `PurchaseWorkflowTest`, `test_batch_tracked_transfer_keeps_the_batches_fefo` | Fixed |
| DEF-40 | P2 | Sales | `resources/views/admin/inventory/sale/create.blade.php`, `SaleController::store()` | Line discount + VAT on the sale create page | applied | ignored | page did not send / apply them | sent and applied via `CustomerProgramController::vat()` | `test_vat_inclusive_does_not_raise_the_total_and_sale_discount_is_spread`, `<SCRATCH>\sale_vat_return_test.php` | Fixed |
| DEF-41 | P2 | Returns | migration `2026_11_07_100000_add_vat_and_loyalty_to_sale_returns` | Return part of a VAT / loyalty sale | VAT and points reversed proportionally | not reversed | columns and logic missing | added | `test_partial_return_of_partly_paid_sale_restocks_and_reverses_vat_and_loyalty`, `test_returning_everything_gives_back_exactly_the_grand_total_and_all_vat` | Fixed |
| DEF-42 (TEST-01) | P3 | Purchases | `PurchaseController.php:1177` | Delete a purchase of a non-visible branch | 404 | 422 (record unchanged) | plain `Exception` caught as 422 | `abort_unless(..., 404)` | `test_deleting_purchase_of_non_visible_branch_answers_404` | Fixed |
| DEF-43 (DATA-08) | P3 | Schema | migration `2026_11_11_100000_unify_table_collation` | `JOIN products p ON p.barcode = v.barcode` | works | error 1267 (mixed collations) | 4 tables `utf8mb4_general_ci` | converted to `utf8mb4_unicode_ci` | VERIFIED: 0 tables in `fpos` with another collation | Fixed |
| DEF-44 | P2 (sev. assigned here) | Schema / deploy | `database/schema/mysql-schema.sql` | `php artisan migrate` on an empty DB | builds | fails: 16 core tables had no create migration | migrations never covered the original tables | Laravel schema dump (needs the `mysql` client on PATH) | the test DB is built from it on every run | Fixed |
| DEF-45 (DATA-10) | P3 | Schema | migration `2026_11_10_100000_add_performance_indexes` | n/a | payments fit document amounts | `payments.debit/credit decimal(10,2)` (max 99,999,999.99) | narrow type | widened to `decimal(18,2)` | information_schema | Fixed |

### 7.3 Performance

| ID | Sev | Module | File:line | Reproduction | Expected | Actual (before) | Root cause | Fix | Regression test / evidence | Status |
|---|---|---|---|---|---|---|---|---|---|---|
| DEF-46 (PERF-01) | P1 (source) / P2 | Dashboard, aging, ledgers | `DashboardController::dues()`, `BusinessReportController::aging()`, `ReportController::partyLedgerPartyOpening()` | open `/dashboard` | constant queries | 230 queries (164 of them `select id from branches where is_main=?`) | N+1 in `isMainBranch()` | hoist / memo; then dashboard rebuilt | query counts: dashboard 230 → 71 → **40** after rebuild; receivable-aging 112 → 7; customer-ledger 111 → 6 | Fixed |
| DEF-47 (PERF-02, PERF-03) | P2 | Attributes, barcode search | `AttributeController::getAttributes()`, `BarcodeController::search()` | open the list / search | few queries | 53 / 33 queries | N+1 | `pluck` + `variantRows()` | 53 → 4, 33 → 4 | Fixed |
| DEF-48 (PERF-04) | P2 | Indexes | migration `2026_11_10_100000_add_performance_indexes` | EXPLAIN on ledgers and lists | composite index | single `branch_id` index + filesort | missing composite indexes | 9 composite indexes | information_schema (9 present, triggers intact) | Fixed |
| DEF-49 (PERF-05) | P1 (source) / P2 | PDFs | `SettingController::PDF_MAX_ROWS = 2000` (`SettingController.php:924`), `resources/views/admin/pdf-too-large.blade.php` | stock-ledger PDF Sep–Nov | seconds | 62–90 s (2,441 rows, 92 pages) | dompdf time scales with page count (~0.6 s / page) | `SettingController::pdf()` refuses PDFs over 2,000 table rows (`PDF_MAX_ROWS`) and shows a "narrow the range or download Excel" page; bulk payslips are exempt and run with `set_time_limit(300)`; all PDFs moved to one switchable template system (`resources/views/pdf/`) | VERIFIED by the lead: stock ledger with 2,439 rows went from a ~90 s render to the notice in ~6 s; PDF checks in `<SCRATCH>\pdffinal` | Fixed (mitigated) |
| DEF-60 | P3 (sev. assigned here) | PDF template | `resources/views/pdf/partials/styles.blade.php` | print any document with a signature block in the Classic template | signature lines spaced apart | signature lines overlapped | spacing in the template styles | styles corrected | VERIFIED by the lead (PDF render) | Fixed |

### 7.4 Legacy data and residual items

| ID | Sev | Module | File:line | Reproduction | Expected | Actual | Root cause | Fix | Regression test / evidence | Status |
|---|---|---|---|---|---|---|---|---|---|---|
| DEF-50 (DATA-02) | P2 | Legacy data | rows INV-000001…004, PUR-000001…004 | compare sales / purchases with `payments` | every document has ledger rows | 4 sales and 4 purchases without party-ledger rows; shows as Suspense of about ৳8.9 M (PUR-000002 alone 8.52 M) | created before ledger rows were written (early dev / seed) | owner decides: soft-delete the legacy documents, or post the missing rows | SQL in `data_perf_findings.md` | **Owner** |
| DEF-51 (DATA-03) | P3 | Legacy data | VO-000005, -007, -010 | Cash & Bank Book | in the Shop Cash ledger | `payment_method_account_id` NULL (38,600 missing from the cash book) (VERIFIED still NULL 2026-11-04) | legacy | set to the Shop Cash ledger | SQL | **Owner** |
| DEF-52 (DATA-04) | P2 | Legacy data | `purchase_returns.id=9` (PRE-000001) | purchase-return lists | gone with its purchase | live return of a deleted purchase (VERIFIED still live) | early delete path | mark deleted; confirm that purchase delete cascades to returns | SQL | **Owner** |
| DEF-53 (DATA-05) | P2 | Legacy data | `stocks.id=41` | branch 1 on-hand, product 3 | not counted | live stock-out for deleted order SO-000008 (on-hand understated by 2) (VERIFIED still live) | pre-migration order model | soft-delete the row | SQL | **Owner** |
| DEF-54 (DATA-06) | P3 | Legacy data | VO-000014 | cash book | removed with its expense | live 900 payment of a deleted expense (VERIFIED still live) | pre-fix delete path (now `reverseExpense()`) | soft-delete | SQL | **Owner** |
| DEF-55 (DATA-07) | P3 | Data model | `payments.party_id` | `party_type='Employee'` rows VO-000013…15 | employee id kept separately | `party_id=1` = an employee id that collides with customer 1 (VERIFIED) | overloaded column | NULL those, or add `employee_id`; new queries must filter `party_type` | SQL | Open |
| DEF-56 (DATA-11) | P3 | Schema | `chart_of_accounts.account_code`, `product_variants.barcode` | n/a | unique | no unique index (data currently clean) | missing constraint | add once code reuse after soft delete is confirmed | SQL | Open |
| DEF-57 (PERF-06) | P3 | Lists | Sales, products, POS, ledgers | open `sales/list` | paged | full list sent (218 KB; POS 251 KB; stock ledger 1.23 MB) | client-side DataTables | server-side paging when volume grows | sizes measured | Open |
| DEF-58 (N-1) | P3 | Cash engine cache | `BusinessReportController::cashEngine()` `static $engine` | long-running worker | fresh per request | stale under Octane / queue workers (harmless under Apache / php-fpm) | function-level static | reset per request if workers are ever used | test harness note | Open |
| DEF-59 (PERF-08) | P2 | DB server config | `C:\xampp\mysql\bin\my.ini` | backup / heavy reports | fast | `innodb_buffer_pool_size` = 16 MB; `skip-name-resolve` off | XAMPP defaults | set 256M + skip-name-resolve | `dumptime*.php` | **Open** |

**Not a defect, but a behaviour change to be aware of:** 220 AJAX calls were standardised to the owner's `$.ajax({...})` format, with commented debug alerts (see `CLAUDE.md`). This was a convention change; it did not fix a bug.

---

## 8. Root causes & fixes: P0 and P1 narrative

**DEF-01 / DEF-02: the whole project was on the web.**

- XAMPP serves `C:\xampp\htdocs` with directory listing on. FPOS keeps its front controller in `public/`, but nothing stopped Apache from serving the parent folder.
- So `.env`, the 7 MB log, `.git` and the full SQL backups (password hashes included) were one URL away for anyone who could reach port 80.
- A root `.htaccess` now denies everything outside `public/`. The proper fix is a vhost whose DocumentRoot is `FPOS/public` (section 15).
- Because the exposure window is unknown, the owner should treat the secrets as leaked if the PC was ever reachable from another device.

**DEF-03: the test suite could wipe the live database.**

- `phpunit.xml` did not set a test database, and the app's config is cached. The cache is mandatory on this server (DEF-29) and it ignores PHPUnit's environment.
- So `RefreshDatabase` would have run against `fpos`.
- Now three independent safeguards exist:
  - a forced `DB_DATABASE`;
  - a separate config-cache path for tests;
  - a hard refusal in `CreatesApplication` for any database whose name does not end in `_testing`.

**DEF-04 / DEF-05a / DEF-06: authorization was decorative.**

- Roles and permissions could be edited in the UI, but no middleware checked them. Any logged-in user could call any route.
- `/register` let anyone become such a user, and deleted or inactive accounts could still sign in.
- Now:
  - `CheckPermission` maps every route to a permission (`ROUTE_MAP`) and returns 403 (page or JSON);
  - presets give sensible defaults per role;
  - guards prevent locking out the last administrator;
  - login refuses inactive or deleted users, and `SetBranch` signs them out mid-session;
  - registration is removed.

**DEF-07 / DEF-08.**

- Reset links were built from the attacker-controllable Host header; they are now built from `APP_URL`.
- Ignition's runnable solutions are disabled. Debug mode itself is still on, because this is the dev box (owner action for production).

**DEF-23: sale returns never worked.** The return query joined tables that share column names without qualifying them. MariaDB refused it, so every return failed. Columns were qualified, and returns were extended to reverse VAT and loyalty proportionally (DEF-41) and to support credit or cash refund.

**DEF-24: purchase returns increased what the shop owed.**

- The supplier-ledger row for a return was written as a debit, so each return raised the supplier's due.
- The sign is fixed in code, and migration `2026_11_06_300000` flips the one legacy row (VO-000026).
- The tie-out run before the fix (`reg_tieout.log`) shows the resulting supplier mismatch. Later runs pass.

**DEF-25: the financial statements did not describe the business.**

- Trial Balance and Balance Sheet read only journal entries and chart-of-accounts balances. Sales, purchases, cost of goods sold, receivables, payables, inventory and opening balances, which live in operational tables, were missing.
- Cash appeared with three different values in three places.
- The statements are now derived from the operational tables, using the same engines as the operational reports (Cash & Bank Book, party ledgers, Stock Valuation).
- `acc_tieout.php` proves these agree per branch and in total: TB debits = credits, BS balances, AR / AP / inventory / cash / revenue each match their source report, and all branches = sum of branches.

**DEF-26: sale orders.**

- Saving an order charged the full order value to the customer immediately. Deliveries only moved stock and never produced a sale, so revenue, VAT and profit for delivered orders were missing, and dues were overstated for undelivered ones.
- Now each delivery creates a delivery invoice (a normal sale linked to the order). The advance is applied once, over-delivery is refused, and an order with invoices cannot be deleted.
- Existing data was migrated (`2026_11_09_100000_sale_orders_become_sales`) after a SQL backup, and party dues were compared before and after against the expected change (`dues_compare.php`).

**DEF-27: overselling.** `SaleController::store()` did not check stock, so concurrent or careless sales could drive on-hand negative. The sale now locks the relevant stock rows (`lockForUpdate`), sums lines of the same product, allocates batches earliest-expiry-first, and refuses with 422 without writing anything. The integrity sweep shows 0 negative on-hand.

**DEF-28: branch isolation leaks.**

- Some reports took ids or summaries without a branch filter, for example the PF ledger by employee id and several summary cards. Paying-account pickers also offered other branches' cash ledgers.
- During the switch to "multi-branch users see all their branches together", `scope()` (visible branches) was used on cash-closing writes. Clearing a day then deleted other branches' closings, and save / re-flow overwrote them (DATA-01).
- The rule is now uniform:
  - reads use `scope()`;
  - writes and working-branch pickers use `scopeOwn()`;
  - change routes call `guardOwn()` first.
- The 2,450-check isolation script and the per-branch cash chain both pass.

**DEF-29: random 500s.** Threaded PHP on Windows / Apache can lose `.env` values between concurrent requests, so the app occasionally booted as "production" with user "forge" and no key. Caching the config removes the per-request `.env` read. `CLAUDE.md` now requires `php artisan config:cache` after any `.env` edit, and forbids `env()` outside `config/`.

**DEF-30 / DEF-31: payroll money.**

- Joiners and leavers were paid a full month; `monthLines()` now prorates gross and PF by employed days.
- Loan installments recovered through payroll were credited to the wrong ledger; they now reduce the employee-loan receivable, and reversing a run restores them.

**DEF-32: backups.** `backup:run` works, but no Windows task was calling `schedule:run`, so no backup existed before this engagement. This is listed as P1 because a disk failure today would lose everything.

---

## 9. Files changed and why (by area)

**Source of these figures:** `git status` / `git diff --shortstat` on 2026-11-04. Nothing is committed.

- **Tracked files:** 135 modified, 5 deleted. Diff: 140 files, +11,150 / −7,243 lines. The diff includes line-ending (CRLF→LF) warnings on many files, so line counts overstate the logic change.
- **Untracked files:** about 57 new source / test / migration files, plus 166 files under `public/uploads` (product photos and test-data images).

| Area | What changed | Why |
|---|---|---|
| `app/Http/Controllers/Admin/*` (~40 controllers) | Sales, returns, orders, purchases, stock, payments, expenses, bills, journals, accounting statements, reports, business reports / cash closing, payroll, loans, attendance, leave, branches, permissions, users, settings / theme / PDF | DEF-04, DEF-23 to DEF-41, DEF-46 to DEF-49; branch rule (`scope` / `scopeOwn` / `guardOwn`); AJAX format |
| `app/Http/Controllers/Controller.php`, `app/Traits/SafeJsonErrors.php` (new), `app/Traits/PartyDueTrait.php`, `app/Traits/VoucherHelperTrait.php` | `retryOnDeadlock()`, `safeError()`, generic JSON errors, party-due scoping | DEF-16, DEF-35, DEF-28 |
| `app/Http/Middleware/CheckPermission.php` (new), `SecurityHeaders.php` (new), `app/Http/Kernel.php` | permission enforcement, security headers | DEF-04, DEF-15 |
| `app/Providers/AppServiceProvider.php` | reset-link URL from `APP_URL` | DEF-07 |
| `app/Helpers/helpers.php`, `public/backend/js/main.js` | `branch_tag()`, `action_dropdown()` lock tips, shared JS | branch labels; changes to other-branch rows disabled in lists |
| `routes/web.php`, `routes/auth.php`, `routes/admin-stock.php` | throttles, `/register` removed, new routes (delivery invoices, cash closing, theme, PDF template) | DEF-06, DEF-10, DEF-19, DEF-26 |
| `config/app.php`, `config/database.php` | timezone Asia/Dhaka; READ COMMITTED isolation | DEF-36, DEF-35 |
| `database/migrations/` (10 new) | `2026_11_06_300000` (purchase-return sign, drop `cash_closings`), `2026_11_07_100000` (return VAT / loyalty), `2026_11_08_100000` (module permissions), `2026_11_09_100000` (orders → sales), `2026_11_09_200000` (clean units), `2026_11_09_300000` (supplier invoice no.), `2026_11_10_100000` (indexes + decimal widening), `2026_11_11_100000` ×2 (theme / PDF template settings; unify collation), `2026_11_11_110000` (button radius) | data fixes and schema for the items above |
| `database/schema/mysql-schema.sql` (new) | schema dump | DEF-44: fresh databases can be built |
| `resources/views/admin/**` (inventory 22, reports 10, payroll 7, others) | sale / purchase / return / order screens, reports, payroll; `no-permission.blade.php`, `pdf-too-large.blade.php`; `setting/theme.blade.php` | workflow fixes, 403 page, PDF limit, theme settings |
| `resources/views/pdf/**` (new layout + partials); 2 old invoice partials deleted | one switchable PDF template system; signature-line overlap in the Classic template fixed (`pdf/partials/styles.blade.php`) | consistent PDFs following Settings colours (`$pc`); DEF-49, DEF-60 |
| `resources/views/admin/includes/script.blade.php` | toastr `escapeHtml` | DEF-13 |
| `public/backend/css/{tokens,components,style}.css` | design tokens, components, dark mode | UI per `DESIGN.md` (theme colours from Settings) |
| `.htaccess` (new), `public/.htaccess`, `public/uploads/.htaccess` (new) | deny the project root; no scripts in uploads | DEF-01, DEF-11 |
| `phpunit.xml`, `tests/**` (30 new / changed files) | test DB isolation; 233 tests | DEF-03; regression coverage |
| `CLAUDE.md` | architecture, branch rule, AJAX convention, environment, scheduler notes | guidance for future developers |
| `public/uploads/**` | product images: all 245 active products have a real photo (164 + 81 added in two passes; 81 of them 27 exact / 54 similar real product photos; provenance in `scratchpad/product_images/real/sources.csv` and `real2/sources2.csv`); 3 old settings images replaced | test-data / theme assets |

---

## 10. Database integrity findings

**Method.** Read-only SQL against `fpos`: `<SCRATCH>\qa\q.php` with `s1.sql`…`s14.sql`, plus spot checks re-run on 2026-11-04.

| Check | Result |
|---|---|
| Tables without a primary key | 0 of 95 |
| Foreign keys | 170. Gaps by design: `payments.party_id` (also holds employee ids, DEF-55), `stocks.reference_id` (polymorphic) |
| Unique business numbers (invoice_no, payment code, party code, employee_code, sku / barcode, journal_no, …) | unique indexes present. Missing: `chart_of_accounts.account_code`, `product_variants.barcode` (DEF-56; data currently has 0 duplicates) |
| NULL `branch_id` in transactional tables | 0. `chart_of_accounts` 94 / 98 NULL by design (company-wide); `activity_logs` 10 pre-login events |
| Tables with `branch_id` but no trigger | activity_logs, attendances, attendance_devices, chart_of_accounts, loyalty_transactions, sms_logs, stock_adjustments, users, branch_user. Code sets `branch_id` explicitly; 0 NULLs |
| Orphans (sale_items, journal_details, payroll_run_items, payments→parties, stocks→products, batch / variant of another product) | 0 |
| Journals balanced (Σdebit = Σcredit = header) | 0 unbalanced, 0 without lines |
| Sales math (subtotal, VAT, discount, loyalty, paid + due = grand) | 0 mismatches over 214 sales |
| Sales / purchases vs ledger rows | 4 legacy sales + 4 legacy purchases without ledger rows (DEF-50) |
| Returns ≤ sold / bought | 0 violations |
| Payroll runs vs items vs payments | 6 runs, 101 items, 14 payments: 0 differences |
| Expenses vs details vs payments | 0 |
| Negative on-hand per branch / product / variant / batch | 0 |
| Stock rows vs documents (purchase in, sale out, returns, branch) | 0 mismatches, except 1 purchase return (DEF-52) |
| Transfers out = in = items = received | 5 / 5 balanced |
| Adjustments vs stock | 3 / 3 |
| Batches (expiry < manufacture, expired with stock, batches on untracked products) | 0 |
| Cash-closing chain (opening + today = closing; opening = previous closing + unsaved days) | 35 / 35 consistent |
| Collation | all tables `utf8mb4_unicode_ci` (VERIFIED 2026-11-04) |
| Money precision | `payments` widened to `decimal(18,2)`. Consider `chart_of_accounts.opening_balance` `decimal(12,2)` → (18,2) |

**Open data repairs.** DEF-50 to DEF-55 need the owner's approval, because they change historical records. The status of each row was re-checked on 2026-11-04 and is still as described.

**Other notes:**

- A note in `data_perf_findings.md` says another connection was seen writing `<img src=x onerror=…>` probes during sampling. The security agent's post-run scan found 0 rows in `fpos` containing `<img`, `onerror` or `<script`, and 0 leftover QA users or roles.
- All security writes ran inside rolled-back transactions.

---

## 11. Security findings & status

| ID | Sev | Title | Status |
|---|---|---|---|
| SEC-01 | P0 | Project root, `.env`, logs, DB backups, `.git` downloadable | Fixed (DEF-01) |
| SEC-02 | P0 | Secrets possibly exposed | **Owner: rotate** (DEF-02) |
| SEC-03 | P1 | `APP_DEBUG=true`, Ignition runnable solutions | Runnable solutions disabled; debug **must be off in production** (DEF-08) |
| SEC-04 | P1 | Password-reset host poisoning | Fixed (DEF-07) |
| SEC-05 | P2 | Forgot-password user enumeration, no throttle | Fixed (DEF-10) |
| SEC-06 | P2 | Upload extension spoofing → stored XSS | Fixed (DEF-11) |
| SEC-07 | P2 | Non-superadmin can grant Superadmin | Fixed (DEF-09) |
| SEC-08 | P2 | toastr HTML XSS | Fixed (DEF-13) |
| SEC-09 | P2 | SweetAlert title XSS (fiscal years) | Fixed (DEF-14) |
| SEC-10 | P2 | Missing security headers | Fixed (DEF-15) |
| SEC-11 | P2 | Raw exception text to users | Fixed (DEF-16) |
| SEC-12 | P2 | Vulnerable dependencies | **Open** (DEF-17) |
| SEC-13 | P3 | Array input → 500 | Fixed (DEF-18) |
| SEC-14 | P3 | No per-IP login throttle | Fixed (DEF-19) |
| SEC-15 | P3 | CDN scripts unpinned, no SRI | **Open** (DEF-20) |
| SEC-16 | P3 | Weak admin-set password minimum; insecure session cookie | **Open** (DEF-21) |
| SEC-17 | P3 | Account-state message after a correct password | Accepted |
| (lead) | P1 | Permissions never enforced | Fixed (DEF-04) |
| (lead) | P1 | Deleted / inactive users could log in; public `/register` | Fixed (DEF-05a, DEF-06) |
| (lead) | P2 | HR upload names | Fixed (DEF-12) |

**Checked and passing** (VERIFIED in `security_findings.md`):

- Session id regenerated on login; logout invalidates the session.
- bcrypt with 12 rounds; per-account lockout on the 6th attempt.
- CSRF on every POST (419 without a token; no `$except`; no state-changing GET).
- No SQL injection paths found: raw fragments are bound or cast, and there are no user-controlled sort columns.
- No mass-assignment from request input; no open redirects.
- Server-built HTML is escaped; `{!! !!}` is never used with user data.
- Backup download uses a name regex plus a fixed directory.
- 0 IDOR leaks across 54 id-routes.

---

## 12. Performance findings (measured)

**Caveat.** The machine was heavily contended by concurrent agents, and the buffer pool is 16 MB. Wall times are noisy by 10–100×, so **query counts** are the reliable figures. Raw data: `<SCRATCH>\qa\perf_b1_{before,after}.tsv`, `perf_b36_{before,after}.tsv`.

**Coverage.** 216–220 GET requests per branch (Main and Dhanmondi): every parameterless page, list and export, the 21 Reports Center data endpoints, the big PDFs, party / payment / ledger lists, statements, salary sheet and attendance.

| Request | Before (queries / wall) | After (queries / wall) |
|---|---|---|
| `/dashboard` | 230 q / 27.2 s | 71 q / 0.21 s after the N+1 fix; **40 q** after the dashboard rebuild (lead) |
| receivable-aging/data | 112 q / 5.4 s | 7 q / 23 ms |
| payable-aging/data | 60 q / 6.1 s | 7 q / 24 ms |
| customer-ledger/data | 111 q | 6 q |
| supplier-ledger/data | 59 q / 1.6 s | 5 q / 36 ms |
| attribute/list | 53 q / 9.7 s | 4 q / 11 ms |
| barcode/search | 33 q / 5.2 s | 4 q / 13 ms |
| stock-ledger PDF (Sep–Nov, 2,441 rows, 92 pages) | 62.6–73.4 s (~90 s after widening to all visible branches) 2,439 rows: refused with a "narrow the range or download Excel" notice in ~6 s (`PDF_MAX_ROWS` = 2,000; bulk payslips exempt, `set_time_limit(300)`) |
| cash-bank-book PDF | 33–53 s | under the row limit; still dompdf-bound |
| stock-valuation / product-profit / attendance PDFs | 6–13 s | unchanged (dompdf ≈ 0.6 s per page; CSS changes made no difference, `pdfexp.php`) |
| `php artisan backup:run --no-uploads` | 48 s (mysqldump metadata round-trips at 10–250 ms each under contention; raw mysqldump 4.6–14.8 s) | code fine; fix the DB server config (DEF-59) |

**Other measurements:**

- 9 composite indexes were added (branch + date, party + branch, stock lookups, activity log).
- List payload sizes: employee list 393 KB, sales list 218 KB, POS 251 KB, stock-ledger data 1.23 MB, cash-bank-book data 800 KB. These are fine at current volume and will grow with it (DEF-57).
- Minor repeats left in place, each under 10 ms normally: holidays 9× in the salary sheet; company name 11× in SMS due reminders.

**Not measured:** throughput under parallel users (no load test).

---

## 13. Remaining risks & unresolved issues

| # | Risk | Severity | Owner action |
|---|---|---|---|
| 1 | `APP_DEBUG=true`, `APP_ENV=local` in `.env` | P1 in production | Set `APP_DEBUG=false`, `APP_ENV=production`, keep `IGNITION_ENABLE_RUNNABLE_SOLUTIONS=false`; run `php artisan config:cache` |
| 2 | Secrets possibly exposed before DEF-01 was fixed | P0 if the PC was networked | Rotate the DB password, SMTP password, SMS API key, then APP_KEY (logs everyone out; re-save encrypted settings); delete old backup copies from shared places |
| 3 | `composer audit` advisories (laravel/framework, symfony/*, league/commonmark); 5 dev-only npm issues | P2 | `composer update` within 10.x, `npm audit fix`, then the full PHPUnit suite plus a browser pass |
| 4 | CDN scripts without SRI; toastr loaded as `latest` | P3 | Pin versions with `integrity`, or vendor them into `public/backend` |
| 5 | Suspense of about ৳8.9 M from legacy dev documents without party rows (INV-000001–004, PUR-000001–004, early returns) | P2 | Decide: soft-delete the legacy documents, or post the missing rows. Simplest is to start production from a clean database |
| 6 | Orphan / legacy rows DATA-03 to DATA-07 (DEF-51 to DEF-55) | P2–P3 | Approve the repairs listed in section 7.4 |
| 7 | Product photos come from retailer/brand listings (copyright stays with them; 54 are similar, not exact, products — see sources2.csv). Fine for an internal POS; for a public website/shop use own or licensed photos | P3 | Replace with own photos over time; review the 3 loose matches (73, 167, 222) |
| 8 | Cash / bank ledgers negative in test data (seeded salaries and loans) | test data only | none for production; do not read it as an app defect |
| 9 | Large lists not server-side paginated | P3 | Revisit when sales pass a few thousand rows |
| 10 | Session cookie not `secure`; no HTTPS | P2 on any network | HTTPS, then `SESSION_SECURE_COOKIE=true` |
| 11 | Task Scheduler not configured; no backups were ever produced before this engagement; restore never tested | P1 | See section 15 |
| 12 | MariaDB `innodb_buffer_pool_size` 16 MB; `skip-name-resolve` off | P2 | `my.ini`: `innodb_buffer_pool_size=256M`, `skip-name-resolve`; restart MariaDB |
| 13 | READ COMMITTED isolation now set in `config/database.php` | info | Keep it; document it if the DB is ever shared with another app |
| 14 | Test logins exist in `fpos`: `rafiq.test@fpos.test` / `Rafiq@2026`, `nusrat.test@fpos.test` / `Nusrat@2026`; per-branch users `manager.main@`, `cashier.main@`, `manager.dhn@`, `cashier.dhn@`, `manager.utr@`, `cashier.utr@`, `manager.ctg@`, `cashier.ctg@` (all `@fpos.shop`, password `Branch@2026`); full list in `<SCRATCH>\seed\foundation.json` | P1 if they reach production | Remove them, or change their passwords, before go-live |
| 15 | `created_at` values written before the timezone change are UTC | P3 | Accept for dev data; production starts clean |
| 16 | Static per-request caches (e.g. `cashEngine()`) assume one request per process | P3 | Reset them if Octane or queue workers are ever introduced |
| 17 | Untested: real ZKTeco device, real SMTP / SMS, load, real multi-cashier concurrency, hardware | unknown | Cover in UAT (section 14) and the pilot |
| 18 | `NewPasswordController` distinguishes an invalid user from an invalid token | P3 | Same message for both (needs a valid token to matter; throttled) |
| 19 | Large uncommitted working tree (140 tracked files changed, about 220 new) | process risk | Review and commit in logical chunks before deploying; tag the release |

---

## 14. Manual UAT checklist

**Instructions.** Run each workflow on a **copy** of the database, or on a fresh production-like database with real masters. For each step, record:

- pass or fail;
- the document number;
- a screenshot or PDF;
- the figures named under "Evidence".

Any mismatch is a failure, even if the screen looks fine.

### 14.1 Cashier: POS day, including cash closing (role: Cashier, one branch)

| Step | Expected outcome | Evidence to record |
|---|---|---|
| Log in, choose the branch | Lands on POS (the dashboard redirects cashiers); no branch switcher | screenshot |
| Open a page outside the role (Purchases, Settings) | 403 page / toast | screenshot |
| Walk-in sale with 3 items, cash | Invoice saved; stock of each item drops by the qty; the receipt PDF prints | invoice no., stock before / after |
| Sale with line discount + VAT (exclusive and inclusive product) | Totals = subtotal − discount + VAT (exclusive only); VAT report shows it | invoice PDF, VAT report line |
| Customer sale using loyalty points | Total lowered by the redeem value; points balance reduced; new points earned | customer points before / after |
| Credit sale to a customer near the credit limit | Refused if over the limit | message |
| Try to sell more than on hand | 422 "not enough stock"; nothing saved | message; stock unchanged |
| Expense paid in cash larger than the drawer | Refused; a bank payment of the same amount is allowed | message |
| End of day: Reports → Cash closing → today | Opening = yesterday's saved closing (+ unsaved days); today's movements match the receipts; save | opening, in, out, closing |
| Re-open tomorrow | Opening = the closing saved yesterday | figures |
| Cashier tries "Clear" on a saved day | Not allowed (cashier may save, not clear) | message |

### 14.2 Purchasing clerk / manager: purchase & supplier payment

| Step | Expected outcome | Evidence |
|---|---|---|
| Purchase with supplier invoice no., batch, expiry, VAT, partly paid | Saved with batch / expiry / VAT visible on the view and PDF; stock in; supplier due = grand − paid | purchase no., supplier ledger |
| Pay the supplier the rest from the bank | Due = 0; bank ledger shows the outflow | supplier ledger, Cash & Bank Book |
| Sell some of that batch, then try to delete the purchase | Delete refused (goods sold) | message |
| Pay a supplier in cash beyond the drawer | Refused | message |

### 14.3 Returns

| Step | Expected outcome | Evidence |
|---|---|---|
| Partial return of a partly paid sale, kept as credit | Stock back in; customer due lowered by the return value; VAT and loyalty reversed proportionally; credit note PDF | return no., due before / after, VAT report |
| Full return of a paid sale, cash refund from a chosen account | Refund = grand total exactly; cash ledger outflow; due unchanged | figures |
| Return more than sold | Refused | message |
| Purchase return | Stock out; **supplier due goes down**; cannot return goods already sold | supplier ledger before / after |

### 14.4 Quotation → order → delivery

| Step | Expected outcome | Evidence |
|---|---|---|
| Quotation with VAT → Mark Sent → convert to a sale order | Order total = quotation total; quotation "Converted", a second conversion refused | QT and SO numbers |
| Take an advance on the order | Advance on the customer ledger; **no** full-value charge | customer ledger |
| Partial delivery | Delivery invoice created (in the sales list); due = delivered − advance; stock out FEFO | invoice no., due |
| Final delivery | Order "Delivered"; invoices sum exactly to the order total | sum |
| Over-deliver | Refused | message |
| Delete a delivery invoice | Order reopens | status |

### 14.5 Branch manager: multi-branch views (user permitted in 2+ branches)

| Step | Expected outcome | Evidence |
|---|---|---|
| Log in to branch A | Lists and reports show A and B rows together, each tagged with its branch | screenshot of the sales list |
| Edit / delete a branch B record | Action disabled with "Sign in to B to change it"; a direct request gets 403 | screenshot / message |
| View or print a branch B invoice | Allowed | PDF |
| New sale | Saved in A, stock from A only | invoice branch |
| Payment account picker | Shared + branch A ledgers only | screenshot |
| Single-branch user of B | Sees B only; A record by id gives 404 / 403 | message |
| Head-office screens (TB, settings, users) from a non-main branch | Refused | message |

### 14.6 Accountant: statements & year-end (main branch)

| Step | Expected outcome | Evidence |
|---|---|---|
| Trial Balance as at today | Debits = credits | totals |
| Balance Sheet | Assets = liabilities + equity; Inventory = Stock Valuation; AR = customer dues; AP = supplier dues; Cash = Cash & Bank Book closing | each pair of figures |
| P&L for the year | Net = current-year profit on the BS; revenue = net sales without VAT | figures |
| Journal: unbalanced entry | Refused | message |
| Close a finished fiscal year | Net moved to retained earnings; income / expense zeroed | closing journal |
| Back-dated sale / expense / delete in the closed year | Refused | messages |
| Reopen the year | Block lifted; balances restored | figures |
| Excel and PDF of TB / BS | Same figures as on screen | files |

### 14.7 HR: month-end payroll

| Step | Expected outcome | Evidence |
|---|---|---|
| Import attendance (file, or a real ZKTeco device **(not tested in QA)**) | Punches land on the right days; a night shift is one day; holidays / off-days are not Absent | monthly attendance sheet |
| Approve leave incl. unpaid | Leave ledger balance correct; unpaid days counted | leave ledger |
| Add bonus / addition / deduction; issue a loan | Visible on the salary sheet | salary sheet |
| Employee who joined mid-month | Gross and PF prorated | salary sheet line |
| Process the month (working branch) | Run frozen; journal Dr salary expense / Cr salary payable, PF payable, employee loans; only this branch's employees | run no., journal |
| Pay salary partly from cash, rest from bank | Salary payable reduced; payslips show paid amounts | payment nos., payslip PDF |
| Cancel a payment / reverse the run (no payments) | Salary due again / loans restored | figures |
| Try to pay from another branch's cash ledger | Refused | message |

### 14.8 Owner: theme, PDF, settings, users, backups

| Step | Expected outcome | Evidence |
|---|---|---|
| General Settings → Theme Colors: change accent / button | Admin and login pages follow the new colours, light and dark | screenshots |
| PDF template switch | Invoice, purchase, return, quotation, payslip and report PDFs use the new template and colours | PDFs |
| PDF of a huge report (> 2,000 rows) | Friendly refusal suggesting Excel or a narrower range | message |
| Create a user with the Cashier preset; a non-superadmin tries to grant Superadmin | Preset works; grant refused | messages |
| Deactivate a user who is logged in | Signed out on the next request | observation |
| Forgot password for unknown and known e-mails | Same message; link arrives only for the real active user (**real SMTP to verify**) | e-mail |
| SMS due reminder (**real BulkSMSBD to verify**) | Sent and logged | SMS log |
| Run a backup, then restore it on another machine | Restore succeeds; TB matches | file name, TB totals |

---

## 15. Production deployment recommendations

**1. Environment (`.env`):**

- Set `APP_ENV=production`, `APP_DEBUG=false`, `IGNITION_ENABLE_RUNNABLE_SOLUTIONS=false`.
- Set `APP_URL` to the real URL; reset links use it.
- Generate a new `APP_KEY` on the production server.
- Use dedicated DB credentials (not root), with least privilege plus the TRIGGER privilege.
- Set real SMTP / SMS keys.
- Then run `php artisan config:cache` and `php artisan route:cache`. Run `config:cache` again after **every** `.env` edit (DEF-29).

**2. Code:**

- Commit the working tree in reviewed chunks and tag a release.
- Deploy with `composer install --no-dev --optimize-autoloader`, after first running `composer update` within 10.x on dev and passing the suite (DEF-17).

**3. Database:**

- Fresh install: put `C:\xampp\mysql\bin` on PATH, then `php artisan migrate --force`. This loads `database/schema/mysql-schema.sql`, then the newer migrations.
- Existing data: back up first, then `php artisan migrate --force`. The data migrations `2026_11_06_300000`, `2026_11_09_100000` and `2026_11_11_100000_unify_table_collation` change data or schema, so rehearse them on a copy and compare TB / dues before and after.
- Check that triggers exist: one per transactional table, following the create_branches migration.
- Set `my.ini`: `innodb_buffer_pool_size=256M`, `skip-name-resolve`, `default-time-zone='+06:00'`.

**4. Data hygiene:**

- Production should start from a clean database (masters only, real opening balances), or the owner must resolve DEF-50 to DEF-55.
- Remove all test users (section 13 #14).

**5. Web server:**

- Make the Apache vhost DocumentRoot `C:\xampp\htdocs\FPOS\public` with `AllowOverride All`.
- Keep the root `.htaccess` as a second line of defence.
- Turn off `Options Indexes` globally.
- Do not expose port 80 / 3306 beyond the shop network. Bind MariaDB to 127.0.0.1.

**6. HTTPS and cookies:**

- Use a certificate (internal CA or Let's Encrypt if public).
- Set `SESSION_SECURE_COOKIE=true` and a FPOS-specific session cookie name; consider `SESSION_ENCRYPT=true`.
- Enable `TrustHosts`.

**7. Storage permissions:**

- The Apache user needs write access to `storage/` (logs, `app/backups`, `app/attendance-imports`), `bootstrap/cache/` and `public/uploads/`. Nothing else should be writable.
- `public/uploads/.htaccess` must stay in place.

**8. Scheduler and backups:**

- Create a Windows Task Scheduler task:
  - runs every minute;
  - action `C:\xampp\php\php.exe artisan schedule:run`;
  - "Start in" = `C:\xampp\htdocs\FPOS`;
  - "Run whether user is logged on or not".
- This runs the daily backup, activity-log pruning, and ZKTeco sync every 30 minutes.
- Copy backups off the machine daily (external disk or cloud).
- **Test a restore** monthly on another PC.

**9. Logging:**

- Use `LOG_CHANNEL=daily` with a retention period (e.g. 14 days) and `LOG_LEVEL=warning`.
- Watch `storage/logs` for the generic-error entries logged by `SafeJsonErrors`.
- The activity log is pruned by the scheduler.

**10. Rollback:**

- Before each deploy, take a DB backup (`php artisan backup:run`) and a copy of the code folder or git tag.
- To roll back, restore the code tag and the SQL dump together. Do not rely on `migrate:rollback` for data migrations.
- Then run `config:cache`.

**11. After deploy:**

- Run the smoke checks:
  - login per role;
  - one sale and one return;
  - cash closing;
  - TB balanced;
  - `curl https://<host>/.env` → 403 / 404;
  - security headers present.
- Run the UAT in section 14 on the pilot branch.

---

## Addendum — features delivered at the end of the engagement (2026-11-04)

These were built after the audit sections above were drafted; they are covered by the final test run: `php vendor/bin/phpunit --colors=never` → **OK (233 tests, 1984 assertions)**, 0 failures/errors/skips, on a fresh `fpos_testing` built from the schema dump.

**Investor party type + Suspense module (VERIFIED).** Owners are a party type `Investor` (CRM › Investors: NID, share %, opening capital, capital invested/withdrawn, outstanding suspense). Accounts › Suspense records advances to employees and investors and owners' capital, all branch-tagged (trigger) and permission-controlled (`investors.*`, `suspense.*`).

| Type | Who | Entry | Effect |
|---|---|---|---|
| Paid / Withdraw | Employee / Investor | Dr Suspense Account, Cr cash/bank | outstanding ↑ (cash drawer guarded) |
| Return | both | Dr cash/bank, Cr Suspense | outstanding ↓ (not beyond outstanding) |
| Adjustment | both | Dr chosen Expense (or Drawings/Equity for investors), Cr Suspense | outstanding ↓, no cash |
| Deposit | Employee | Dr cash/bank, Cr Suspense | office owes the employee |
| Investment | Investor | Dr cash/bank, Cr Owner's Capital | capital ↑ |
| Capital Withdrawal | Investor | Dr Owner's Capital, Cr cash | capital ↓ (cash drawer guarded) |

Owner's examples verified end to end (`scratchpad/suspense_test.php`, all PASS; `tests/Feature/SuspenseTest.php`, 6 tests): employee Paid 5,000 → Adjustment 3,000 → Return 2,000 = outstanding 0, expense 3,000; investor Investment 500,000 → Withdraw 300,000 → Adjustment 250,000 → Return 50,000 = outstanding 0, capital 500,000. Cash & Bank Book, Daily Cash Closing, dashboard and TB/BS/P&L (via `gl()`) include them; TB balanced; refusals (over-return/over-adjust, cash overdraw, other-branch edit/delete 403, deleting an investor with entries) all verified. Note: the migration granted `suspense.*` to every role that could already post payments/expenses/transfers — **including Cashier**; restrict it in Roles if cashiers should not record owners' capital.

**Other late items (VERIFIED):** Theme Settings (43 tokens × light/dark, presets Midnight (default)/White/Green/Yellow/Blue/Indigo, button radius 5/10/15 px) and five PDF templates (Classic default, Modern, Minimal, Corporate, Elegant) used by every PDF (42 PDF routes × 5 templates = 200 OK); dashboard redesign (equal-height grid, data tables, Sales/Money/Stock/People widgets, 70→40 queries); money safety (cash drawers cannot be overdrawn, deadlock retry, READ COMMITTED); Chart of Accounts shows statement balances; all 245 products have real photos (27 exact + 54 similar in the second pass; provenance CSVs in the scratchpad).

**Housekeeping for the owner:** leftover parallel test databases from the test-writing run can be dropped (the agent was not allowed to delete databases): `fpos_acct_testing`, `fpos_branch_testing`, `fpos_mm_testing`, `fpos_payroll_testing`, `fpos_sales_testing`, `fpos_unit_testing` (keep `fpos_testing`, the suite uses it).

## 16. Final release recommendation

**Verdict: not ready for production yet. Suitable for a controlled single-branch pilot once the blocking items below are done.**

The core money paths now behave correctly and are covered by automated tests and tie-out scripts:

- sales, returns, purchases, orders and deliveries;
- stock, the cash drawer and cash closing;
- the accounting statements;
- payroll.

Branch isolation passes 2,450 checks, and the P0 exposure is closed. PHPUnit (final): OK, 233 tests, 1,984 assertions, 0 failures. Browser: 88 pages × 2 sizes, 0 JS errors / 0 failed requests (only intended 403s).

These results come from a dev machine with seeded data and simulated concurrency. They are not proof of correctness under real shop load, real devices or real external services.

**Blocking before production:**

1. Production `.env`: debug off, production env, `config:cache`.
2. Secrets rotated if exposure was possible.
3. docroot = `public/`.
4. Dependency update + green suite.
5. Scheduler running and one successful restore test.
6. Clean production database without test users; legacy-data decision (DEF-50 to DEF-55).
7. HTTPS + secure cookies on any networked install.
8. UAT sections 14.1–14.4 and 14.7 signed off.

**Pilot conditions:**

- one branch for 2–4 weeks;
- the owner reviews the daily cash closing and the Trial Balance each day;
- backups are verified daily;
- any mismatch is logged with the document number, and the pilot pauses for P0/P1 issues.

**Widen to all branches after:**

- a clean pilot;
- the multi-branch UAT (14.5);
- one full month-end payroll (14.7) and one accountant period check (14.6) completed without discrepancies.
