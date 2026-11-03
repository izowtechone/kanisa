# KKKT CMS implementation progress

## Current status

The existing Laravel 13 application has a working members module, member-number sequencing, CSV/XLSX member imports, authentication, Spatie permissions, and a basic system-settings page. Member records now support authorized soft deletion, bulk actions, restore, and a lifecycle audit trail. Authenticated pages share a responsive Premium Dark layout with permission-aware navigation. The dashboard displays permission-protected member, community, and Sunday Offering aggregates, recent registrations, and only links to implemented, authorized pages. Community management and member Jumuiya assignment are implemented; Sunday Offerings use the existing approval and financial ledger workflow.

The requested CMS is not yet complete. The financial transaction, campaign, receipt, envelope import, SMS, and financial reporting features are implemented. The 11 original Finance migrations, one account-management migration, the community/member relationship migrations, and the Sunday Offering transaction-source migration are applied to the configured local database. Three SMS campaign/log migrations remain pending. User administration is implemented. Standalone cash/bank account management, event and attendance operations, visitors, and worker management remain unimplemented.

## Audit findings

- Runtime: Laravel 13.39.0, PHP 8.3.36, MySQL 8.4.3.
- Database: `church_management`; an InnoDB database dump was saved outside the project tree before further schema work.
- Existing application tables include users, settings, members, Spatie permissions/roles, sessions, cache, queues, member imports/sequences, departments, groups, and group assignments.
- Original financial migrations define categories, transactions, audits, campaigns, imports, number sequences, settings, and SMS logs/attempts. The Sunday Offering source migration is applied; the three newer bulk-campaign/SMS-log migrations remain pending. No standalone account, event, attendance, visitor, or church-worker tables exist yet.
- Existing members have unique membership and envelope numbers, but the schema does not yet include all profile and church-history fields requested in the master prompt.
- Latest full-suite verification: 127 tests passed and the same 3 known baseline failures remain (registration screen returns 404, registration does not authenticate, and `/` returns 302 instead of 200).
- Local PHP and MySQL are available. Node is installed at `C:\laragon\bin\nodejs\node-v22` and must be added to `PATH` when running npm commands from a new PowerShell process.
- Composer audit found no security vulnerability advisories in the installed PHP packages.

## Phases

| Phase | Scope | Status |
|---|---|---|
| 1. Audit and foundation | Inspect the existing project, preserve data, record baseline behavior, and create a database backup | Complete |
| 2. Core system | Dashboard, members, departments/groups, visitors, user management, and settings | In progress: dashboard, members/settings, departments/groups, communities, and user administration are implemented; visitors remain |
| 3. Financial system | Offerings, tithes, contributions, campaigns, expenses, transaction integrity, receipts, imports, SMS, and financial reports | Implemented; community/member relationship and Sunday Offering source schemas are applied; standalone accounts are not implemented |
| 4. Church operations | Events, attendance, and follow-up | Pending |
| 5. Communications | Notifications and documented SMS-provider integration | SwalaSMS integration (migrated from Beem) with signed delivery webhooks, balance check and consent-based batched campaigns implemented |
| 6. Reporting | Permission-aware reports and exports | Pending |
| 7. Quality assurance | Full regression, security, performance, and responsive checks | Pending |

## Work completed in this phase

- Replaced dashboard placeholder totals and fake financial figures with real member statistics, shown only to users with `view members`.
- Added recent member registrations and permission-gated member/settings shortcuts.
- Removed dashboard navigation items that pointed to `#` or routed to the wrong page.
- Added English and Kiswahili dashboard translations and the active locale in the document language.
- Updated the shared Breeze navigation to use the namespaced dashboard translation, avoiding a translation-key collision on Windows.
- Added feature tests for dashboard statistics, locale rendering, and member-data visibility.
- Replaced the standalone dashboard document with the shared authenticated application layout.
- Added responsive desktop/mobile navigation with accessible controls, active route styling, and permission-gated links to implemented pages only.
- Confirmed that dashboard, member, import, settings, and profile pages use the shared shell; unimplemented modules remain out of active navigation.
- Added bilingual navigation/profile labels and made the layout consistently honor the explicit dark theme.
- Updated member form and import-preview page titles to appear in the shared header, and removed nested full-screen wrappers from shared-layout pages.
- Added feature tests for shared navigation, active states, permission visibility, and the absence of dead/planned module links.
- Added Laravel soft deletes for members and a permission-protected deleted-members page with individual and bulk restore.
- Added transactional single/bulk delete and restore operations, server-side ID validation, per-operation confirmations, and a limit of 500 records per bulk request.
- Added a member lifecycle audit log recording the member and actor snapshots, action, actor reference, and timestamps; no audit system previously existed.
- Added the separate `restore members` permission while preserving existing role permissions; the Super Admin receives the new permission through the existing seeder.
- Kept deleted membership and envelope numbers reserved in the sequence generator and spreadsheet import validation to honor the existing unique database constraints.
- Added bilingual deletion/restoration UI and feature coverage for authorization, empty/invalid/duplicate selections, audit data, soft-delete visibility, restoration, and existing sequence/import behavior.
- Implemented department and group create, list, detail, edit, status, search, filters, and pagination pages using the shared responsive Premium Dark layout.
- Added department/group schemas with globally unique codes, unique department names, per-department group names, member leaders, foreign keys, and an indexed many-to-many group-member pivot that prevents duplicate assignments.
- Added safe deletion rules: departments with groups and groups with any member assignments cannot be deleted; deleting a group assignment never deletes the member.
- Added member-backed department/group leader selection, including server-side rejection of soft-deleted members and support for clearing an existing leader.
- Added transactional, idempotent multi-member assignment; group membership lists show membership number, full name, phone, and active/inactive status.
- Added granular department/group permissions and `assign group members`, preserved existing role grants, and gave the Secretary role view/create/edit/assignment access without delete permissions. Super Admin receives all permissions through the existing seeder behavior.
- Added bilingual English/Kiswahili interface and validation strings, permission-gated sidebar links, real named routes, SVG navigation icons, confirmation prompts, and accessible form labels.
- Added model factories and 21 feature tests covering CRUD, permissions, leaders, soft-deleted members, duplicates, assignments, safe deletion, search/filter/pagination, translation labels, scoped member removal, and HTML escaping.
- Applied the three additive department/group migrations and reran `RoleSeeder`. A read-only count showed 2 existing member records; these migrations and seeding did not create or alter member records.
- Implemented the financial ledger for offerings, tithes, contributions, other income, and expenses with category management, transaction numbering, idempotency, approval separation, audit records, void reasons, and signed reversal entries.
- Added contribution campaigns with inclusive start/end dates enforced both during request validation and again under a database transaction before posting.
- Added envelope-based CSV/XLSX import preview, row validation, rejection reports, duplicate checks, transactional confirmation, and generated transaction numbers.
- Added approved-only receipt access and printable receipt details, configurable receipt prefix, and optional church logo/contact details.
- Integrated Beem configuration, bilingual SMS templates, test controls, and provider connection checks into the shared System Settings page. API credentials are encrypted, never rendered, and excluded from validation old input.
- Added permission-protected test SMS, delivery history filters, masked phone numbers, encrypted message content, manual retry, delivery report checks, low-credit threshold display, and bilingual feedback. Provider acceptance is distinct from confirmed delivery.
- Added automatic configurable SMS notifications after approved member-linked offerings/tithes, contributions, and other income; messages use the shared template builder and are queued through the database queue. Automatic POST retries are disabled because Beem does not document send idempotency.
- Resolved MySQL 8.4 index-name length failures in the pending audit/SMS migrations using explicit short names and safe handling of the partial tables created by failed DDL attempts. The migration batch was resumed without dropping tables or member records.
- Added financial ledger and campaign reporting, CSV export sanitization, date/category/type/payment-method/campaign filtering, and separately authorized report export.
- Added finance permissions and role grants without removing existing grants; added bilingual finance labels and user-facing validation messages.
- Added Finance feature coverage for settings authorization/secrecy, template validation, test-mode and queued test SMS, automatic notification toggles, Beem connection/balance checks, send responses, delivery reports, history filters, masked phones, and the existing Finance behaviors.
- Added SMS consent controls to member registration and editing; existing members remain opted out by default.
- Added permission-protected SMS campaign creation, recipient filters and selection, scheduling, message previews, segment estimates, encrypted message logs, cancellation, and failed-recipient-only retries.
- Added SMS campaign preparation and delivery jobs with recipient deduplication, phone validation/normalization, personalization, queue tracking, and consent/phone eligibility re-checks immediately before delivery.
- Added user account management with search, status/role filters, member linking, role assignment limits, direct-permission escalation protection, activation/suspension checks, last-login tracking, password setup/reset links, and last-active-Super-Admin protection.
- Added permission-aware sidebar links and bilingual English/Kiswahili user and campaign interfaces.
- Removed the undocumented Beem balance and delivery-report requests; the UI now explains that Beem acceptance does not confirm delivery and that current balance/delivery checks are unavailable.
- Added additive migrations for campaigns, member SMS consent and account fields, SMS log campaign relationships, and nullable financial transaction references for test/campaign logs. The new migrations have not been applied to the configured database.
- Added feature tests for campaign authorization, consent, invalid phones, personalization, post-queue opt-out, failed-only retries, account invitation and role assignment, permission escalation, empty direct permissions, login status, last-admin protection, and permission-aware navigation.
- Added community management with searchable, paginated listing, create/edit/detail, status changes, member-backed leader selection, sequential configurable codes, and deletion safeguards that include soft-deleted member assignments.
- Added Sunday Offering recording, search/filter/list/detail views, active-member lookup, ledger source tagging, and separation-of-duties approval through the existing financial transaction workflow.
- Added opt-in and preferred-language-aware Sunday Offering SMS. Messages are only queued after approval, with member consent and current phone eligibility checked before sending.
- Connected member registration and editing to active communities and exposed community details in member and offering search results without replacing member records.
- Added permission-aware community and Sunday Offering navigation, role grants, English/Kiswahili interface strings, and dashboard aggregates. The dashboard does not expose individual community or offering records to aggregate-only users.
- Added feature tests for community permissions, sequential codes, leader validation, safe deletion, HTML escaping, member assignment/clearing, Sunday Offering authorization and validation, searchable pending transactions, approval separation, consent-based SMS, and member lookup.
- Fixed the member edit form so an explicitly cleared community selection stays cleared after a validation failure, added a non-null dashboard currency fallback, and made transaction source/type validation report the relevant field instead of an amount error.
- Added the required Sunday Offering SMS templates to existing Finance test payloads; template validation remains enforced.
- No Composer or npm dependencies were added.

## Verification

- Member-module feature tests: 19 passed (136 assertions).
- Full suite: 47 passed; the same 3 unrelated baseline failures remain (registration screen returns 404, registration does not authenticate, and `/` returns 302 rather than 200).
- Laravel Pint: passed.
- Vite production build: passed.
- Browser smoke check: shared navigation and Members/Deleted Members pages rendered in Kiswahili; select-all and selection count updated correctly.
- Applied the additive soft-delete and audit-log migrations and reran `RoleSeeder`; the existing member record remained visible and the deleted-members page loaded successfully.
- Departments/groups feature tests: 21 passed (115 assertions).
- Current full suite: 68 passed; the same 3 baseline failures remain (registration screen returns 404, registration does not authenticate, and `/` returns 302).
- Laravel Pint: passed on all PHP files changed for this module. The `--dirty` option was unavailable because this workspace is not a Git repository.
- Vite production build: passed.
- Applied migrations: 3 department/group tables and relationships; route verification listed 7 department routes and 10 group routes.
- Reran `RoleSeeder` successfully. The current local database read-only count returned 2 member records; no member records were inserted or changed by this module.
- Manual responsive browser validation of the new pages remains to be performed in the Laragon browser session.
- Finance feature tests: 26 passed (169 assertions).
- Latest full suite: 94 passed; the same 3 previously documented baseline failures remain (registration screen returns 404, registration does not authenticate, and `GET /` returns 302 rather than the ExampleTest's expected 200). No new Finance failures.
- Laravel Pint: passed. Vite production build: passed.
- Route verification: 11 SMS and 9 user-management routes are registered; the unverified delivery-report endpoint is not exposed.
- All 11 Finance migrations are applied (`migrate:status` reports Ran). MySQL schema inspection confirmed `financial_transactions`, `financial_sms_logs`, and `financial_sms_attempts`; the database queue driver is `database` and its `jobs` table exists.
- `RoleSeeder` completed successfully with the new `send test SMS` grant. No Composer or npm dependencies were added.
- Read-only checks confirmed 2 existing members remain; new Finance ledger, categories, and SMS tables contain zero records.
- A fresh pre-Finance backup is preserved outside the project at `C:\Users\John\.copilot\session-state\6c9e7553-2726-4773-b14d-edbe70289fe7\files\church_management_before_finance_20261101_1807.sql`.
- No live Beem credentials were available; automated tests use HTTP fakes, so a real provider send and live handset delivery remain unverified. Run `php artisan queue:work` in Laragon to process database-queued SMS.
- No Composer or npm dependencies were added.

## Latest SMS and user-management verification

- Bulk SMS campaign tests: 8 passed (57 assertions).
- User-management tests: 7 passed (32 assertions).
- Finance regression tests: 26 passed (160 assertions).
- Full Pest suite: 111 passed, 3 known baseline failures (the two Breeze registration tests and the root redirect expectation); no new failures.
- Laravel Pint: passed on changed PHP files.
- Vite production build: passed with Node.js 22.
- `git diff --check`: passed.
- Route checks: 11 SMS routes and 9 user routes listed successfully.
- Migration status was inspected read-only: the 11 original Finance migrations remain applied and the four new campaign/account migrations remain pending. No migration or seeder was run, no existing records were changed, and no live SMS was sent.
- Automated Beem tests use fakes. A live SMS, Beem delivery report, and current balance are not verified; run a queue worker for queued delivery after deployment and provider configuration.
- Visual browser checks of the new campaign and user pages remain outstanding.
- Campaign visibility is owner-scoped for users with `send bulk SMS`; the campaign detail page only links to campaign history for users with `view SMS campaigns`. Permission-specific navigation and ownership are covered by feature tests.
- Latest focused verification: all 8 campaign tests passed (57 assertions), Laravel Pint passed, and all 6 campaign routes were listed. The full suite remains at 111 passing tests with only the same 3 baseline failures.
- Latest community/Sunday Offering verification: full Pest suite has 124 passing tests and the same 3 baseline failures (two Breeze registration tests and the root redirect expectation); the new feature tests and Finance regression tests pass.
- Laravel Pint passed on the PHP files touched by the community and Sunday Offering implementation. The workspace is not a Git repository, so Pint was run against the explicit changed-file list rather than using `--dirty`.
- `npm run build` passed with Node.js 22. Route listing confirmed 10 community routes and 5 Sunday Offering routes.
- Responsive browser validation of the community and Sunday Offering pages remains outstanding.

## Members page missing-communities repair

- Cause: the configured MySQL `church_management` database did not have the `communities` or `community_sequences` table, and `members.community_id`/`preferred_language` were absent. The matching migrations existed but were pending; this was not a model/table naming mismatch.
- Inspected both existing community migration definitions before execution. Applied only `2026_11_02_090932_create_communities_and_community_sequences_tables` and `2026_11_02_090933_add_community_and_preferred_language_to_members_table`, in dependency order. Other pending migrations were left untouched during that repair; the Sunday Offering source migration was applied later as documented below.
- Verified the `communities` table columns, unique code index, status/name index, timestamps, soft deletes and leader foreign key; verified nullable `members.community_id` references `communities.id` with `ON DELETE SET NULL`.
- The existing member count was 2 before and after the migration; no member records were inserted, changed, or deleted. The communities table is present and currently contains zero rows.
- The database also lacked the community permissions used by the existing UI gate. Ran the existing idempotent `RoleSeeder`; Super Admin now has `assign members to communities`, allowing the existing member forms to render the authorized Jumuiya search field.
- Added a Members feature test proving the list, registration, and edit pages render when no communities exist and existing members remain unassigned. Focused member/community tests: 26 passed (179 assertions).
- Live browser verification: `/members`, `/members/create`, and `/members/1/edit` all loaded. Registration and edit pages each rendered the community search input and hidden `community_id` field for the authorized Super Admin.
- Full suite: 127 passed, 3 known baseline failures (Breeze registration screen 404, registration not authenticating, and `/` returning 302 instead of the example test's expected 200).
- Laravel Pint passed for the updated member feature test. `npm run build` passed. No push or deployment was performed.

## Sunday Offering missing-source-column repair

- Cause: the MySQL `financial_transactions` table had no `source` column, but the Sunday Offering listing and dashboard correctly filter by the source discriminator already used by `FinancialTransactionService` and `FinancialTransaction`.
- The existing `2026_11_02_090935_add_source_to_financial_transactions_table` migration was present but pending. Inspected it and the live table before execution. Applied only this migration; it adds a non-null `VARCHAR(30)` column defaulting to `finance` and a composite `source/type/status/transaction_date` index. No financial row was deleted or overwritten, and the one pre-existing transaction now reads as `finance`.
- `FinancialTransaction::SOURCE_SUNDAY_OFFERING` remains the discriminator for Sunday offerings; normal finance transactions use `SOURCE_FINANCE`. The service persists it at creation, preserves it on reversals, and both dashboard/list queries filter by it. Updated the Sunday Offering detail guard to use the model constant consistently.
- Added tests confirming the Sunday Offering index loads with zero results, creates/list-counts Sunday Offering transactions, and excludes ordinary offerings. Dashboard aggregate tests also pass with the source schema present.
- Focused Sunday Offering and dashboard tests: 15 passed (116 assertions). Latest full-suite result is recorded below after final verification.
- Live schema verification: `source` exists, the expected composite index covers all four filter columns, and `migrate:status` records the migration as run. The ledger row count remained 1; its source is `finance`.
- Live browser check returned HTTP 200 for `/finance/sunday-offerings` and displayed its empty-state message.
- Final full suite: 127 passed, 3 previously known baseline failures. Pint passed on the changed controller and feature test; `npm run build` passed.

## Safety notes

- Keep backups and uploaded/private data outside the public web root and out of version control.
- Use additive migrations for new modules. Do not delete or rewrite existing member or financial data.
- Back up the configured database before schema changes that may affect existing records.
- Financial posting, reversals, approval rules, and SMS-provider behavior require auditable implementation and tests; do not use placeholder buttons or fabricated figures.
- Finance reports currently use calendar-based periods; no configurable fiscal-year setting was found. Bank/cash account management is outside the implemented finance scope.


## SwalaSMS provider migration (replaces Beem)

- Added `SwalaSmsService` (Laravel HTTP client, `https://api.swalasms.com`, Bearer API key): single send `POST /v1/sms/messages`, bulk `POST /v2/sms/send-bulk` (max 1000), status `GET /v1/sms/messages/{uid}`, balance `GET /v1/balance`. Errors map to `SwalaSmsException` (acceptance state, retryability, sanitized message). The old `BeemSmsGateway` was removed; legacy `beem_*` settings rows and Beem-era logs/attempts/provider IDs are preserved (shared SMS settings fall back to `beem_*` values).
- Settings (System Settings > SMS): encrypted API key and webhook signing secret (masked, replace/clear), sender ID, test mode, safe connection test via `/v1/balance`, webhook URL display, last check, last successful connection and balance. Additive migration adds provider metadata columns to `financial_sms_logs` (idempotency key, batch key, campaign UID, provider/delivery status, error code, acceptance state, units, failure reason, delivered/event times).
- Single sends are queued with bounded backoff and one persisted `Idempotency-Key` per message; unknown-outcome (503) retries reuse the key. Campaigns are queued, split into batches of at most `SWALASMS_BULK_BATCH_SIZE` (<= 1000) with one stable key per batch, and bulk response `messages[]`/`failures[]` are mapped to per-recipient logs. Retry-failed re-queues only failed recipients; non-consenting members are never selected.
- `POST /webhooks/swalasms` verifies `X-SwalaSMS-Signature` (HMAC-SHA256 of the raw body, `hash_equals`), then records `sms.delivered`/`sms.failed` idempotently using the payload `timestamp`; repeats and older events are ignored. Configure the endpoint and copy its signing secret manually in the SwalaSMS dashboard (no registration API exists).
- SMS history shows provider and connection status, configured sender ID, real total/pending/delivered/failed counts, delivery status filter/column, manual status refresh, retry of delivery-failed messages and the failed queue job count. Translations added in `lang/en|sw/finance.php`.
- Tests: `tests/Feature/SwalaSmsTest.php` plus migrated `FinanceModuleTest`, `BulkSmsCampaignTest`, `SundayOfferingsTest` (all `Http::fake`; no provider contact).
- Validation: SwalaSMS feature tests, finance/Sunday Offering tests, Pint and `npm run build` pass. The full suite reports 150 tests, 145 passed and 4 failures unrelated to SMS: two registration tests, the root example response, and user-management navigation. No real SMS was sent and the migration was not run against the application database.
- Caveats: not verified against the live provider; a queue worker (`php artisan queue:work`) is required; the bulk `failures[]` entry shape (`to` + `reason`) is handled defensively; run the additive migration with `php artisan migrate` on deployment.

## Fix: missing `bulk_sms_campaigns` table (GET /finance/sms/campaigns)

- Cause: the live `church_management` database had four pending migrations. The campaign table migration had never been run, so the controller's query failed with SQLSTATE 42S02.
- Ran a normal `php artisan migrate` (additive only, previewed with `--pretend` first): `2026_11_02_060735_create_bulk_sms_campaigns_table`, `2026_11_02_060746_add_campaign_references_to_financial_sms_logs_table`, `2026_11_02_064559_make_financial_sms_transaction_id_nullable`, `2026_11_02_190329_add_swalasms_provider_fields_to_financial_sms_logs_table`. No existing data was changed (3 existing SMS logs preserved).
- No code change was needed in the controller or model; their column names match the real schema.
- Tests added to `tests/Feature/BulkSmsCampaignTest.php`: empty list, history permission, real per-campaign statistics, invalid status filter, missing creation data. Fixed a stale prepare-campaign test (dispatcher argument, `SendBulkSmsBatch` assertion).
- Deployments must run `php artisan migrate` after pulling these migrations.

## UI/Settings/SMS gateway improvement programme

### Phase 1 - Audit (complete)
- No gateway abstraction existed; SwalaSmsService was called directly from SendFinancialSms, SendBulkSmsBatch, PrepareBulkSmsCampaign and FinancialSettingsController.
- Settings were uncached (a query per key); system settings UI exposes only short name, community prefix, language and logo.
- No generic live search; all list pages use GET-form server search. Events, Attendance, Visitors, Pastors and Announcements modules do not exist and get no menu items.
- NextSMS docs (https://nextsms.co.tz/api-docs) were read. The Authorization header value is masked in the published page, so NextSMS authentication is pending confirmation from the dashboard docs. No NextSMS code has been written.

### Phase 2 - Performance and shared layout (in progress)
- Setting values are cached as one map (Setting::allCached) and flushed on every save/delete; AppSettings reads from it (removes the per-request Schema::hasTable check and per-key queries).
- Added migration 2026_11_02_213542_add_listing_indexes_to_members_users_and_sms_logs (members status/name, users name, SMS logs created_at). Existing indexes already covered most filters; '%term%' LIKE searches cannot use a b-tree index.
- Added tests/Feature/SettingsCacheTest.php.
- Measured in-process against the real DB (1 member, 1 transaction, 4 SMS logs, so not representative of scale): dashboard ~75 ms, members ~70 ms, finance ~66 ms, SMS campaigns ~54 ms, settings page 33 queries. Assets: app.css 76.5 KB, app.js 54.5 KB (before gzip).
- Known test note: MembersModuleTest "rejects invalid gender and future dates" fails only when the suite runs shortly after local midnight (UTC now()+1 day is still today in Africa/Dar_es_Salaam).

### Phase 3 - Settings categories, church information, logo and favicon (complete)
- Inspected `settings` migration and live schema: the existing table has a unique `key`, nullable `longText value`, `group` and timestamps. Existing keyed settings and uploaded assets are preserved; no migration was needed or added.
- Reorganized System Settings into General, Church Information, Logo and Favicon sections with separate authorized forms and centralized Kiswahili/English labels. The existing SMS settings partial and route were left unchanged.
- General preferences now save system name, default language, timezone, currency, date format, time format and community-code prefix. Locale/timezone middleware also publishes current date/time/currency preferences to app config.
- Church name, short name, address, phone and email save to the existing keys consumed by member numbering, SMS templates and receipts. Receipt rendering now reads those values through `AppSettings`.
- Logo uploads accept JPEG/PNG/WebP up to 2 MB; favicon uploads accept ICO/PNG up to 512 KB. Both use the public Laravel Storage disk, show the current asset, support replacement/removal, and delete only files from their managed directories. Existing `public/favicon.ico` was empty, so added a small local `public/favicon.svg` fallback; uploaded SVGs are not accepted.
- Added `tests/Feature/SystemSettingsTest.php` for section rendering, authorization, general/church settings, logo upload/replacement/removal, favicon PNG/ICO/default behavior, bilingual labels, file type and size validation, conflicting actions, and safe file deletion. Finance receipt coverage now asserts the configured church name, address, phone and email on the rendered receipt.
- Verification: focused settings/member/finance tests pass (57/57); Pint passes; `npm run build` succeeds. Full suite: 169 tests, 165 passed, 4 pre-existing failures (two registration cases, ExampleTest redirect expectation, and UserManagementTest navigation assertion); no skipped tests. No new migration, SMS gateway integration, real SMS or deployment.

## Offering Categories Management (2026-11-03)

- Reused the existing `finance_categories` table and `FinancialTransaction.category_id` relationship; no duplicate category table was added. The existing migration defines unique category codes/names per transaction type and a restrictive category foreign key, so existing transaction history remains attached and category deletion is not exposed.
- Added an additive migration for nullable Kiswahili and English offering-category names and per-type unique indexes. Existing category names are retained as the fallback display value; no existing categories or transactions are rewritten. The migration was not applied to the configured database, as requested. Its rollback intentionally refuses to discard administrator-entered translations.
- Added a bilingual offering-category administration page with search (debounced in the UI), status filtering, pagination, descriptions, transaction counts, create/edit, and activate/deactivate controls. Existing offering-category URLs now redirect to the bilingual manager.
- Added granular view/create/edit/toggle permissions to `RoleSeeder` and retained compatibility with the existing `manage finance categories` permission. Existing records stay safe because referenced categories can be deactivated but not deleted.
- Updated regular offerings, Sunday Offerings, envelope entry/import, receipts, transaction views, and financial reports to display the category name for the current system/session language. New transaction forms continue to select active database categories only; validation rejects inactive categories.
- Added `tests/Feature/OfferingCategoriesTest.php` for create/edit, duplicate names/codes, search, permissions, legacy routes, bilingual UI, active-category selection/rejection, legacy-name fallback, escaped content, and transaction-history preservation.
- Verification: focused offering/finance tests pass (43/43); full suite has 179 tests, 175 passed and the same 4 unrelated baseline failures (two registration tests, ExampleTest, and UserManagementTest navigation). Pint, `npm.cmd run build`, and `git diff --check` pass. Tests use the configured in-memory SQLite database; no migration was run against the application database.
- Manual deployment step: apply the new additive migration and rerun `RoleSeeder` to persist the granular permission records. Legacy managers remain authorized through the existing permission until then.

## Finance Categories Schema Mismatch Fix (2026-11-03)

- Root cause: /finance queried finance_categories.name_en/name_sw, but migration 2026_11_03_051526_add_bilingual_names_to_finance_categories_table was still pending on church_management (it was intentionally not applied during the Offering Categories task).
- Live table before fix had 9 columns (id, type, code, name, description, is_active, created_by, created_at, updated_at).
- The additive migration was previewed with --pretend and applied (name_en, name_sw plus unique (type,name_en)/(type,name_sw)). It now backfills name_en from name for existing rows; name_sw stays empty and falls back to name. Existing category AHADI and the 2 transactions were preserved.
- Added tests/Feature/FinanceCategorySchemaTest.php (schema, dashboard, per-category/type aggregation, EN/SW names, untranslated fallback, historical links).
- Full suite: 185 tests, 181 passed, 4 known unrelated failures (RegistrationTest x2, ExampleTest, UserManagementTest navigation). Pint, npm run build and git diff --check pass; no pending migrations.
- Granular offering-category permissions are not yet seeded in the live DB; controllers fall back to 'manage finance categories' until RoleSeeder is run.
