# DutyCalc – Implementation Plan (backend first)

As of 2026-11-19. Carried over from the team's Claude Docs plan
(https://claude.ai/artifact/WRhatHmENUfJbFjThpCz33) and reviewed for a backend-first build.
This file is the working copy that lives with the code. Plan only: no code has been written yet.

Where the project brief and the developer brief (.docx) disagree, this plan follows the developer brief.

---

## 1. Summary and scope

Build the deterministic core first: a versioned tariff database and a tested calculation engine.
Then connect both input routes (text and photo) to that one engine. **Current focus: backend only.**
The frontend (Blade screens) is built on top once the backend endpoints and tests are done.

| Topic | Decision (from the developer brief) |
| --- | --- |
| Stack | Laravel 12 + Blade + JavaScript + MySQL |
| STR eligibility | Out of scope for the MVP |
| Scope | Only the agreed, verified products and route; unsupported cases fail safely |
| Languages | English, Bemba and Swahili; final scope confirmed with the team lead and language specialist |
| Delivery | Responsive web app for mobile and desktop browsers |

**In the core:** catalogue search, product confirmation, trade-details input, itemised estimate with sources and dates, safe failure for anything unsupported.

**Built on the core:** photo identification through the AI engineer's service, language toggle, saved estimates, admin pages.

**Not in this MVP:** STR and trade-preference eligibility, vehicle imports, USSD, Zamtel Money or other payments, consignment tracking, ASYCUDA links or automatic declarations, full tariff coverage, AI usage limits (not yet communicated), hosting (set up when needed).

---

## 2. Environment (as set up on 2026-11-19)

| Item | Value |
| --- | --- |
| Project folder | `C:\xampp\htdocs\duty_calc` |
| Laravel | 12.x (`laravel/framework ^12.0`) |
| PHP | 8.2 (XAMPP's PHP 8.2.12), so teammates on XAMPP can run it |
| Database | XAMPP's MariaDB 10.4.32 (MySQL-compatible), port 3306. The separate MySQL service `MySQL2610` is not used |
| Database names | App database as named in `.env`; test database as named in `phpunit.xml` (currently `dutycalc_test`). Keep the two names consistent |
| Serving | `php artisan serve` (http://127.0.0.1:8000). Never through http://localhost/duty_calc, which would expose `.env` |
| Tariff book | `docs/Customs-Tariff-Book.pdf` (keep out of Git) |

**PHP 8.2 rule:** do not use PHP 8.3-only features (typed class constants, `json_validate()`, `#[\Override]`, dynamic class constant fetch).

---

## 3. Review findings carried into this plan

The plan was reviewed against the developer brief and the current project files. Changes from the Docs version:

1. **`Route` model name clashes with Laravel's `Route` facade.** The table becomes `trade_routes` and the model `TradeRoute`; foreign keys become `trade_route_id`.
2. **FULLTEXT search does not see uncommitted rows in InnoDB.** Tests that use `RefreshDatabase` (which wraps each test in a transaction) will find nothing through `MATCH ... AGAINST`. Search tests use `DatabaseTruncation` (or `DatabaseMigrations`) instead.
3. **InnoDB ignores FULLTEXT words shorter than 3 characters** (`innodb_ft_min_token_size`). The `LIKE` fallback covers short aliases such as "tv".
4. **Session-based confirmation is tested in PHPUnit feature tests**, not with Postman, because there is no frontend yet. Feature tests carry the session between requests.
5. **The clock must be controllable in tests.** The engine reads "today" through Laravel's `now()` so tests can fix the date with `Carbon::setTestNow()`.
6. **Duplicate or overlapping rules must be impossible.** A unique index on (tariff_line_id, tax_type, origin_country, effective_from), plus an overlap check in the import validator.
7. **ZMW needs no exchange-rate row.** A ZMW input uses a rate of 1 and records "no conversion".
8. **Setup items still open** (see Phase B0): timezone still `UTC` in `config/app.php`; no Composer `platform` setting; `.env.example` still on SQLite; leftover `database/database.sqlite`; tariff PDF not in `.gitignore`; Git not initialised.

---

## 4. Architecture

One Laravel app with three services. AI sits at the edge and only ranks; all money logic is deterministic PHP behind `EstimateService`.

```
Trader's browser (Blade + JS, later)
        |
Laravel routes (routes/web.php) + thin controllers + Form Requests
        |
 -----------------------------------------------------------
 |                         |                               |
ProductIdentification    CatalogueSearchService         EstimateService (core)
Service                  (aliases + FULLTEXT,           (decimal-safe,
(uses an                  works with no AI)              rules as data,
IdentificationClient)                                    one path for text and photo)
 |                         |                               |
AI service (external,     MySQL: catalogue, aliases, tariff lines and rules,
AI engineer)              trade routes, exchange rates, sources, estimates
```

If the AI service fails, search still works and the engine is untouched.

**Proposed folders**

| Folder | Contents |
| --- | --- |
| `app/Services/Estimate/` | `EstimateService`, `EstimateRequest`, `EstimateResult`, `LineItem`, `RuleEvaluator` |
| `app/Services/Catalogue/` | `CatalogueSearchService` |
| `app/Services/Identification/` | `IdentificationClient` (interface), `HttpIdentificationClient`, `FakeIdentificationClient`, `ProductIdentificationService`, `AiResponseValidator` |
| `app/Services/TariffData/` | `TariffCsvImporter`, `TariffDataValidator` (shared by seeders and the admin import) |
| `app/Http/Controllers/` | Thin controllers only |
| `app/Http/Requests/` | One Form Request per POST |
| `database/data/sample/` | Clearly labelled sample CSVs |
| `database/data/verified/` | Verified rows from the tariff book, with page numbers |

---

## 5. Data model (MySQL)

Tariff rates hang off 8-digit tariff lines, not off products, and every rate row carries a date range and a source.
Each estimate copies the values it used, so a later change never rewrites it.
This schema is for Day 1 review with the AI engineer and team lead; migrations start once they sign off.

### Reference data (seeded from CSV, never edited in place once used)

| Table | Key columns | Purpose |
| --- | --- | --- |
| `tariff_sources` | id, name, document_url, edition, published_on, retrieved_on, verified_by | Where every rate came from; shown on results as "as of" |
| `tariff_lines` | id, hs_code CHAR(8), official_description, stat_unit (kg / u / l), valid_from, valid_to, tariff_source_id, source_page | One row per national tariff code |
| `catalogue_items` | id (stable string, e.g. `CAT-0012`), tariff_line_id, display_name, active | What traders pick; several items can share one tariff line. The only ID the UI and AI pass around |
| `product_aliases` | catalogue_item_id, alias, language (en / bem / sw); FULLTEXT(alias) | Common names, misspellings and local terms for search |
| `tariff_rules` | id, tariff_line_id, tax_type (customs_duty / excise / vat), sequence, method (ad_valorem / specific / compound), rate DECIMAL(9,6), specific_amount DECIMAL(15,4), specific_currency CHAR(3), combine (sum / max), base_components JSON, origin_country (null = standard rate for all origins), effective_from, effective_to, tariff_source_id, source_page | One row per charge per tariff line. `combine = max` handles duties such as "40% or K5 per kg" |
| `countries` | iso2 (PK), name, is_origin, is_destination | |
| `trade_routes` | id, origin_iso2, destination_iso2, border_post, supported, notes | First row: TZ to ZM via Nakonde (if confirmed) |
| `exchange_rates` | id, currency CHAR(3), rate_to_zmw DECIMAL(18,8), effective_from, effective_to, source, entered_by (user id) | Entered weekly by an admin |
| `users` | Laravel default | Admins only; traders have no accounts |

### Records (written by the app)

| Table | Key columns | Purpose |
| --- | --- | --- |
| `ai_product_analyses` | id, input_type (text / image), language, normalised_description, attributes JSON, candidates_sent JSON, ranked JSON, clarifications JSON, status, manual_review, confirmed_catalogue_item_id, model_name, created_at | What the AI suggested and what the trader chose; no image binaries |
| `calculations` | id UUID, catalogue_item_id, tariff_line_id, hs_code (copied), trade_route_id, ai_product_analysis_id (nullable), inputs JSON, input_currency, exchange_rate_id, exchange_rate_value (copied), declared_value_zmw, freight_zmw, insurance_zmw, customs_value_zmw, quantity, quantity_unit, total_zmw, status (estimated / unsupported / incomplete), missing JSON, assumptions JSON, engine_version, created_at, expires_at | One row per estimate request, including the ones that could not be estimated |
| `calculation_breakdowns` | id, calculation_id, sequence, tax_type, taxable_base_zmw, method, rate, specific_amount, combine, amount_zmw, tariff_rule_id, rule_effective_from, tariff_source_id | One row per charge, with the rule values copied in |
| `admin_audit_logs` | id, user_id, action (create / replace / end / import / toggle), table_name, record_id, before JSON, after JSON, created_at | One row per admin save |

### Rules the schema relies on

- Money is `DECIMAL(15,2)` in ZMW; tax rates `DECIMAL(9,6)`; specific amounts `DECIMAL(15,4)`; exchange rates `DECIMAL(18,8)`. Never `float`.
- A rate that changes gets a new row: set `effective_to` on the old one. This is how the proposed VAT change to 16.5% on 1 Jan 2027 is added, if enacted.
- "In force" means in force today in Africa/Lusaka time: the app timezone and every date comparison use it.
- Zambia is the only destination in the MVP, so tariff lines and rules carry no destination column. Add one only if a second destination is ever supported.
- `base_components` may list only `customs_value` and tax types with a lower `sequence`. The import validator rejects the row otherwise.
- Unique (tariff_line_id, tax_type, origin_country, effective_from); date ranges for the same (line, tax_type, origin) may not overlap.
- The trader gives quantity in the tariff line's `stat_unit`, so the engine never converts units.
- `origin_country` null means the standard rate. Trade preferences are out of scope, so each estimate records the assumption "standard rate, no preference claimed".
- In `calculations`, `catalogue_item_id`, `tariff_line_id` and `trade_route_id` are nullable, so requests that fail as `unsupported` (unknown item or route) are still saved.
- Every tariff line and rule must point to an existing `tariff_sources` row; seeders and the CSV import reject rows that do not.
- Seeders read CSV files kept in the repo; every row carries its source and page number.
- A daily scheduled command deletes `calculations` past `expires_at` (90 days).

---

## 6. Core 1 – Calculation engine

The engine is a plain PHP service with no HTTP, AI or UI code in it, so it can be tested in isolation.
It takes a confirmed catalogue item, a trade route and trade inputs, and returns line items or a reason it cannot estimate.
Text and photo routes both call this same service.

**Steps inside `EstimateService::estimate()`**

1. Load the catalogue item and its tariff line; check the item is active and the line is valid today. Unknown or expired: `unsupported`.
2. Load the trade route. Not in `trade_routes` or `supported = false`: `unsupported`, naming the route.
3. Load every `tariff_rule` for the item's tariff line (+ origin where set) that is in force today (Lusaka time), ordered by `sequence`. None: `unsupported`, naming the item.
4. Check required inputs from the rules (quantity in the line's `stat_unit` for specific or compound rules). Missing: `incomplete`, listing the fields.
5. Convert declared value, freight and insurance to ZMW with the `exchange_rates` row in force (ZMW: rate 1); copy the rate value into the estimate.
6. Compute the customs value: CIF = value + freight + insurance. If freight or insurance is blank, apply the rule the team agrees from ZRA guidance and record it in `assumptions`.
7. For each rule in sequence: base = sum of the components it lists; amount = rate x base (ad valorem), specific amount x quantity (specific), or both added together or the higher of the two, as the rule's `combine` column says (compound).
8. Round each line once, to 2 dp, half up (to confirm with ZRA). Total = sum of rounded lines.
9. Save `calculations` + `calculation_breakdowns` with the rule values copied in, and the engine version.

**Rules for the code**

- Use `brick/math` `BigDecimal` for every amount. No `float` anywhere in the money path.
- The tax order and bases come from `base_components` in the data, not from `if` statements, so "VAT on CIF + duty + excise" (or whatever ZRA confirms) is a data change, not a code change.
- Never fall back to a default rate. A gap in the data is always `unsupported` or `incomplete` with the missing items named.
- Output follows the developer brief's response shape: `status`, `estimate_id`, `currency`, `line_items[]`, `total_estimated_charges`, `tariff_effective_date`, `disclaimer`, plus `assumptions[]` and `missing[]`. Amounts are strings with 2 decimals.

**Tests written before the endpoints**

- Golden cases: 10–15 worked examples checked by hand (or by a clearing agent) against the same source and date. One per method and per product group.
- Edge cases: zero freight, specific-duty item with no quantity, expired rule, rule starting tomorrow, unsupported route, unknown item, foreign-currency input, ZMW input, rounding on .005, compound `max` where each side wins once.
- Same input on the same data always gives the same output.
- Lusaka date boundary: a rule ending 2026-12-31 is used at 23:59 Lusaka time and not at 00:01 on 1 Jan.

---

## 7. Core 2 – Catalogue search and product confirmation

The text route works without AI first: a MySQL search over aliases returns catalogue items, and the trader picks one.
AI ranking is added on top later and can be switched off if the service fails. The demo never depends on the AI being up.

**Endpoints (all in `routes/web.php`, JSON responses)**

| Method | Path | Does |
| --- | --- | --- |
| GET | `/api/catalogue/search?q=&lang=` | FULLTEXT + LIKE over `product_aliases` and descriptions; returns the top 10 (the screen will show the best 5) |
| POST | `/api/identify/text` | Same candidates, re-ranked by the AI service; falls back to plain search on error |
| POST | `/api/identify/image` | See Core 3 |
| POST | `/api/identify/clarify` | Sends the trader's answers back for re-ranking (max 2 rounds) |
| POST | `/api/selection` | Stores the confirmed `catalogue_item_id` in the session; returns the item and which inputs its rules need |
| GET | `/api/routes` | Supported origins, destination and border posts |
| GET | `/api/currencies` | Currencies with a rate in force, plus ZMW |
| POST | `/api/estimates` | Validates, calls `EstimateService`, returns the result |
| GET | `/estimates/{uuid}` | Result for a saved estimate (JSON now; Blade page later) |

All of these routes go in `routes/web.php`, even the ones that return JSON. Laravel's `api` routes have no session and no CSRF check by default, and the confirmation step depends on both. The `/api/` prefix is only part of the path.

**Confirmation rules**

- `/api/estimates` rejects any request whose item was not confirmed in this session or does not exist in `catalogue_items`.
- Nothing is auto-selected, even a "High" match.
- `/api/selection` returns which inputs the rules need, so the form can show only those fields.

Controllers stay thin. Each POST uses a Form Request for server-side validation (positive decimals, supported currency, ISO country codes, quantity range).

---

## 8. Core 3 – AI service contract and photo route

Agree the JSON contract with the AI engineer on Day 1 and build against a fake client until their service is ready.
The AI only ranks catalogue IDs that Laravel sent it; Laravel checks every ID it gets back and drops any it does not recognise.

**Agree on Day 1 (these ripple through the app if changed later)**

1. The AI returns catalogue item IDs, not HS codes.
2. Status values: `ok`, `no_match`, `needs_clarification`, `error`.
3. Laravel supplies the candidate list (the AI does not search the catalogue itself).

**Laravel to AI (text or image)**

```json
{
  "input_type": "image",
  "description": "optional user text",
  "language": "en",
  "image_ref": "signed temporary URL or base64",
  "candidates": [
    {"id": "CAT-0012", "hs_code": "62034200", "description": "..."}
  ],
  "clarifications": [{"question": "...", "answer": "..."}]
}
```

**AI to Laravel**

```json
{
  "status": "ok | no_match | needs_clarification | error",
  "normalised_description": "...",
  "attributes": {"material": "cotton"},
  "ranked": [{"id": "CAT-0012", "label": "High", "reason": "..."}],
  "clarification_questions": ["Is it knitted or woven?"],
  "manual_review": false
}
```

The HS code above is illustrative only. The AI writes `normalised_description` and `clarification_questions` in the language it was sent (en, bem or sw).

**Photo route steps**

1. Validate the upload on the server: JPEG, PNG or WebP only, checked by file content; 5 MB limit; random file name; private `storage/app` disk. (The browser will shrink photos to JPEG before upload once the frontend exists.)
2. Pick candidates for the AI. With 20–30 goods, send the whole catalogue; with more, pre-filter by any typed words.
3. Call the identification client with a 15 s timeout and one retry. Bad JSON or a timeout: "We couldn't identify this photo. Try typing the product name."
4. Remove unknown IDs, then return the ranked list in the same shape as text search.
5. Delete the image once analysis ends; keep only the row in `ai_product_analyses`.

**Clarification loop (text and photo):** when the AI returns `needs_clarification`, the response carries its questions. The trader's answers go back to the AI with the same candidates and the earlier description, and it re-ranks. At most 2 rounds; after that, return the current ranking. Questions and answers are saved in the `clarifications` column, and the trader still confirms the item.

**Fake first:** `FakeIdentificationClient` returns fixed responses for each status, selected in tests and in local development by config. The API key lives in `.env` only and is read through `config/services.php`.

---

## 9. Backend build phases

Each phase ends with a gate. Do not start the next phase until the gate passes.

### B0 – Setup hygiene (about 1 hour)

- [x] Set `'timezone' => env('APP_TIMEZONE', 'Africa/Lusaka')` in `config/app.php`; add `APP_TIMEZONE=Africa/Lusaka` to `.env` and `.env.example`
- [x] Add `"platform": {"php": "8.2.12"}` under `config` in `composer.json`, then `composer update`
- [x] Update `.env.example` to MySQL (`DB_CONNECTION=mysql`, host, port, database name; no password)
- [x] Delete `database/database.sqlite`
- [x] Add `/docs/*.pdf` to `.gitignore`
- [x] Create the test database and make sure `phpunit.xml` names it
- [x] `composer require brick/math`
- [x] `git init`, first commit, push to GitHub

**Gate:** `php artisan migrate` and `php artisan test` both run clean on MySQL.

### B1 – Reference schema (half a day)

- [x] Migrations in foreign-key order: `tariff_sources`, `tariff_lines`, `catalogue_items`, `product_aliases`, `tariff_rules`, `countries`, `trade_routes`, `exchange_rates`
- [x] Models with relationships, casts (`decimal:6`, `array` for JSON, `date`), `$fillable` lists; `CatalogueItem` with string key (`$incrementing = false`, `$keyType = 'string'`)
- [x] Indexes: FULLTEXT on `product_aliases.alias`; unique rule index from section 3; index on `tariff_rules (tariff_line_id, effective_from)`. NULLs are distinct in a unique index, so the rule index uses a stored generated column `origin_key = COALESCE(origin_country, '')` in place of `origin_country`

**Gate:** `php artisan migrate:fresh` runs clean; schema signed off by the team lead and AI engineer.

### B2 – Sample data and import validation (half a day)

- [x] `database/data/sample/` CSVs: sources, tariff lines, catalogue items, aliases, rules, countries, trade routes, exchange rates. The single source is named "SAMPLE – not verified, for testing only"
- [x] Sample lines cover: ad valorem duty + VAT; specific duty per kg + VAT; compound "X% or K Y per kg" (max); a line with excise so VAT includes it
- [x] `TariffDataValidator`: existing source, page number, 8-digit HS code, allowed enum values, `base_components` only earlier sequences, no overlapping dates
- [x] `TariffCsvImporter` used by `SampleTariffSeeder` (and later by the admin import and `VerifiedTariffSeeder`)

**Gate:** `php artisan db:seed --class=SampleTariffSeeder` loads; validator tests reject each kind of bad row.

### B3 – Calculation engine (Days 2–4)

- [x] Value objects: `EstimateRequest`, `EstimateResult`, `LineItem`
- [x] `RuleEvaluator` for ad valorem, specific and compound (sum / max)
- [x] `EstimateService` following the 9 steps in section 6
- [x] Records migrations: `calculations`, `calculation_breakdowns`
- [x] Unit and feature tests from section 6, expected amounts as strings, worked out by hand first (`docs/golden-cases.md`)

**Gate:** all golden and edge-case tests pass.

Engine decisions taken in B3 (settings in `config/dutycalc.php`; none of them is a rate):

- **Blank freight or insurance:** counted as ZMW 0.00 and recorded in `assumptions` (`missing_freight_insurance = zero`). Set it to `incomplete` to ask the trader instead. Still to be agreed from ZRA guidance.
- **Required charges:** every line must have customs duty and VAT in force (`required_tax_types`), or the result is `unsupported`. A charge that does not apply is entered as a 0% rule.
- **Ended rates:** if any charge's rule has ended with no new rule in force, the result is `unsupported`, never an estimate without that charge.
- **Specific amounts** must be in ZMW; any other currency is `unsupported`.
- **Rounding:** converted values, then each charge, are rounded once to 2 dp half up; later bases use the rounded earlier charges.
- **Origin-specific rules** (preferences) are ignored; every estimate records "standard rate, no trade preference claimed".

### B4 – Catalogue search (half a day)

- [x] `CatalogueSearchService`: FULLTEXT `MATCH ... AGAINST` in natural language mode, `LIKE` fallback for short or no-hit queries, top 10 with HS code and official description
- [x] `GET /api/catalogue/search` with a thin controller
- [x] Tests (with `DatabaseTruncation`): English, Bemba and Swahili aliases; a misspelling; a 2-letter alias; no results. The test also truncates in `tearDown`, or its committed rows break the `RefreshDatabase` tests that run after it

**Gate:** search tests pass.

### B5 – Selection, estimates and saved results (1 day)

- [x] `POST /api/selection`, `GET /api/routes`, `GET /api/currencies`, `POST /api/estimates`, `GET /estimates/{uuid}`
- [x] Form Requests for selection and estimates
- [x] `expires_at` set to 90 days; `estimates:prune` command scheduled daily in `routes/console.php`
- [x] Feature tests: full text journey (search, select, estimate, fetch); refuses an unconfirmed item; `unsupported` and `incomplete` are saved and returned; CSRF enforced on POSTs; unknown UUID gives 404

Notes from B5:

- The confirmed item lives in the session (`ProductSelection`), shared by the text route now and the photo route in B6. `POST /api/estimates` must name that same item.
- An unknown or unsupported trade route is not a 422: the engine answers `unsupported` and the request is saved, so the trader sees why.
- A currency with no rate in force is a 422 (`GET /api/currencies` lists the valid ones).
- Numbers must be sent as strings ("10000.50"); JSON numbers, thousands separators and exponents are refused.
- The frontend sends the CSRF token from Laravel's `XSRF-TOKEN` cookie (or a `<meta name="csrf-token">` tag) as the `X-XSRF-TOKEN` / `X-CSRF-TOKEN` header.

**Gate:** text journey works end to end through HTTP in tests.

### B6 – AI integration layer (Days 6–8)

- [x] `IdentificationClient` interface; `FakeIdentificationClient`; `HttpIdentificationClient` (built once the AI engineer's endpoint exists); binding chosen by config
- [x] `AiResponseValidator`: schema check, unknown IDs dropped, status values enforced
- [x] `ProductIdentificationService`: text and image flows, clarification loop (max 2 rounds), fallback to plain search on error
- [x] Records migration: `ai_product_analyses`; link `calculations.ai_product_analysis_id`
- [x] Image upload handling: validation by content, private disk, delete after analysis
- [x] `POST /api/identify/text`, `/api/identify/image`, `/api/identify/clarify`
- [x] Tests with the fake client for every status; bad JSON; timeout; unknown IDs; renamed `.exe`; oversize file; image deleted after analysis

**Gate:** both routes reach the same selection step and the same engine in tests.

Notes from B6 (share the first four with the AI engineer):

- `HttpIdentificationClient` is written against the section 8 contract, but has not run against the real service yet. To switch, set `IDENTIFICATION_DRIVER=http`, `IDENTIFICATION_URL` and `IDENTIFICATION_API_KEY` in `.env`. The key is sent as `Authorization: Bearer <key>`.
- The photo goes in `image_ref` as a base64 data URI (`data:image/png;base64,...`), not a URL: the photo is on a private disk and is deleted straight after the call. Clarification rounds send `image_ref: null`, plus the original description and every question and answer so far.
- Laravel keeps only ranked IDs it sent as candidates that are active catalogue items. Anything else, including HS codes, is dropped and logged in `dropped_ids`. A label other than High/Medium/Low becomes Low.
- At most 3 clarification questions are used per round, and a third round is never sent. If the AI asks again in round 2, the current ranking is shown instead.
- Text identification sends the search results as candidates. If search finds nothing and the catalogue has at most 30 usable items, it sends the whole catalogue. Photos always send the whole catalogue while it is that small.
- The analysis column for the AI's `attributes` is `product_attributes`, because `attributes` clashes with Eloquent.
- Old analyses are pruned with estimates (90 days) unless a saved estimate still uses them.

### B7 – Admin backend (Days 9–10)

- [x] Admin login with Laravel's authentication; `php artisan make:admin` console command; no public sign-up
- [x] Controllers and Form Requests for: tariff sources; exchange rates (saving ends the previous rate); tariff CSV import (preview, then confirm, using `TariffDataValidator`); tariff lines and rules list with "replace this rate"; catalogue items and aliases; trade routes on/off; change log
- [x] `admin_audit_logs` migration and a single audit writer used by every admin save
- [x] Tests: guests refused; replace ends the old rule and creates a new one; used rates never edited in place; import rejects bad rows; a source in use cannot be deleted

If Days 9–10 overrun, the change log and the route toggle slip to Day 11; exchange rates and CSV import stay on time.

**Gate:** admin tests pass.

Notes from B7:

- Admin endpoints are JSON under `/api/admin/*` (session login, CSRF, 401 for guests). The Blade admin pages in the frontend phase will sit at `/admin` and call the same services.
- Every user is an admin. Traders have no accounts. Create an admin with `php artisan make:admin you@example.com`; it asks for the password (at least 12 characters), so it never lands in shell history. Login is limited to 5 tries a minute.
- No route edits a rate. "Replace" ends the old rule the day before the new one starts. "End" can only move an end date earlier. Both run the new row through `TariffDataValidator`, in one transaction with the audit row.
- A replacement keeps the old rule's method, base and amounts unless new ones are given, but it must cite its own source and page. A changed method does not inherit the old method's fields.
- Catalogue items are switched off, never deleted, because saved estimates point to them. A source still in use returns 409 on delete.
- `AuditLog::change()` is the only audit writer: the save and its log row commit together or not at all.

### B8 – Hardening and handover (Days 11–12, shared with frontend)

- [x] GitHub Actions: PHP 8.2 + MySQL service, `composer install`, `php artisan test` on every push (`.github/workflows/tests.yml`: MariaDB 10.4 and MySQL 8, plus Pint and a fresh migrate and seed; first run on the next push)
- [x] README: setup on XAMPP, required `.env` keys (no values), endpoint list with request/response examples, deployment notes (HTTPS, MySQL, cron for the scheduler)
- [x] Supported products and routes list, known issues, engine limitations (`docs/HANDOVER.md`)
- [x] Golden test cases with who checked them and against which source (`docs/golden-cases.md`; the clearing-agent or ZRA sign-off is still open)

---

## 10. Frontend (after the backend; summary carried over)

Four Blade screens plus a result page, mobile-first, plain JavaScript (or Alpine.js), no SPA build.
Every string goes through `__('key')`; files `lang/en.json`, `lang/bem.json`, `lang/sw.json`, plus `lang/bem/validation.php` and `lang/sw/validation.php`.

| Screen | Contents | States to handle |
| --- | --- | --- |
| Home / search | Language toggle, search box with 300 ms debounce, camera/upload button | Empty query, network error |
| Suggestions | Up to 5 matches: name, HS code, official description, High/Medium/Low label | No results, no reliable match, AI down (plain search shown), clarification questions |
| Confirm | Chosen product, HS code, and any clarification answers given | Back to suggestions without losing the query |
| Trade details | Route (origin, border post), value + currency, freight, insurance; quantity only when the rules need it | Server field errors inline; inputs kept on failure; submit disabled while sending |
| Result | Total in ZMW first, then each charge as an amount, the inputs used, valuation basis, tariff source and "as of" date, assumptions, disclaimer | `unsupported` and `incomplete` explain what is missing and offer the next step |

Photo input: `<input type="file" accept="image/*" capture="environment">`; the browser redraws the photo as a JPEG of at most 1600 px on the long side before upload (turns iPhone HEIC into JPEG and keeps uploads small).
Admin pages are plain Blade (Filament only if the team lead agrees; it adds Livewire).

---

## 11. Schedule (12-day window)

| Days | Phase | Gate |
| --- | --- | --- |
| 1 | Agree and source: corridor and 20–30 goods; schema and AI contract; start sourcing tariff rows; B0 | Schema and AI contract signed off |
| 2–4 | B1–B3: schema, sample data, engine and tests (core) | All golden test cases pass |
| 4–6 | B4–B5: search, selection, estimates; verified tariff rows swapped in (core) | Text journey works end to end in tests |
| 6–8 | B6: AI integration and photo route | Both routes reach the same selection step |
| 9–10 | B7 admin backend; frontend screens; language files | Admin tests pass |
| 11–12 | B8 hardening, README, demo script and rehearsal | Handover checklist done |

If tariff data is late, Days 2–4 still proceed on labelled sample rows. The Day 6 gate is the last point to swap in verified rows before the demo.

---

## 12. Testing, demo and handover

- **Unit (PHPUnit):** engine golden and edge cases; rule evaluator; import validator; AI response validation; clarification loop stops after 2 rounds; rules picked by Lusaka date.
- **Feature (HTTP):** full text journey; full photo journey with the fake AI client; unconfirmed items refused; upload refuses a renamed `.exe`, large files and wrong types; CSRF enforced; admin pages refuse guests.
- **Automatic:** GitHub Actions on every push, against MySQL.
- **Manual (once the frontend exists):** Chrome and Firefox on a desktop, plus one Android and one iPhone browser on a throttled 3G profile; language switch on every screen.

**Demo script (repeatable, seeded data)**

1. Type a common product in Bemba or Swahili, pick the match, enter details: itemised result with source and date.
2. Photograph a second product, answer a clarification question: same form, same engine.
3. Change quantity to show the cost moving.
4. Try an unsupported route or product: a clear "cannot estimate reliably" message instead of a guess.

**Handover checklist**

- [x] Migrations and seeders with source and effective date on every tariff row (SAMPLE data; verified rows still to come)
- [x] README: setup, required `.env` keys (no values), endpoint list with request/response examples
- [x] Supported products and routes list, known issues, engine limitations
- [ ] Golden test cases with who checked them and against which source (arithmetic done; clearing-agent or ZRA sign-off open)
- [x] Demo steps and demo data (`docs/HANDOVER.md`; the live demo needs the frontend, and the photo step needs the real AI service)

---

## 13. Open questions (with suggested answers)

The engine can be built now, but it cannot produce a real number until the first three are answered.

- [ ] **Product and route scope.** Suggested: Nakonde (Tanzania to Zambia), a major crossing prioritised for a one-stop border post upgrade (https://trademarkafrica.com/zambia/), with a Cross-Border Traders Association desk to interview. Goods from clothing, groceries, phone accessories and household items; leave out excise goods (alcohol, tobacco, fuel) and anything needing an import permit. Backup: Mwami (Malawi to Zambia).
- [ ] **Verified tariff data.** Suggested: copy the 20–30 rows by hand from ZRA's National Tariff Book (`docs/Customs-Tariff-Book.pdf`, https://www.zra.org.zm/), recording the page number per row; a second person checks every row; spot-check against the Zambia Trade Information Portal.
- [ ] **Tax sequence and bases.** Suggested: customs duty on CIF, then excise where applicable, then VAT on CIF + duty + excise (https://www.trade.gov/country-commercial-guides/Zambia-import-tariffs). VAT is 16% today; the 2027 Budget (6 Nov 2026) proposes 16.5% from 1 Jan 2027, not yet law (https://lookuptax.com/tax-changes/zambia/2027-budget-vat-rate-increase). Seed 16% ending 2026-12-31 and add a 16.5% row once enacted; check the goods against the 2027 customs changes. Rounding: 2 dp half up, shown as an assumption until ZRA confirms.
- [ ] **Exchange rates.** Suggested: ask a clearing agent at Nakonde which rate customs uses. Until then accept ZMW, USD and TZS; an admin enters a Bank of Zambia rate weekly; every result prints "rate as of [date]".
- [ ] **Data retention.** Suggested: no accounts, so no personal data; estimates deleted after 90 days; photos deleted as soon as analysis finishes; only the analysis record kept.
- [ ] **Hosting.** Not planned yet; set up when needed. A phone demo needs an HTTPS address, a MySQL database and a cron entry for the scheduler.

## 14. Risks

| Risk | Effect on the build | Mitigation |
| --- | --- | --- |
| Tariff data arrives late | No real numbers to demo | Build and test with clearly labelled sample data; swap in verified rows by Day 6 |
| AI service late or unreliable | Photo route breaks | Fake client from Day 1; text search never depends on AI |
| Wrong tax sequence | Every estimate is wrong | Sequence held as data; golden cases checked by an agent or ZRA |
| Teammate on a newer PHP uses 8.3 features | Breaks XAMPP users | Composer platform pinned to 8.2.12; CI runs on PHP 8.2 |
| Scope creep (STR, USSD, payments) | Core not finished | Out of scope per the developer brief; list as future work |

Estimates are not ZRA's official decisions. Verify all regulatory facts with ZRA before launch.
