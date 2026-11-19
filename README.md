# DutyCalc

DutyCalc estimates the customs duty, excise and VAT a small-scale cross-border trader will pay when bringing goods
into Zambia, before they reach the border. The first route is Tanzania to Zambia through Nakonde.

A trader finds a product by typing (in English, Bemba or Swahili) or by taking a photo, confirms it, enters the
value and quantity, and gets an itemised estimate with the source and date of every rate.

**Status:** hackathon MVP. The backend (phases B0–B8 of [the plan](docs/IMPLEMENTATION_PLAN.md)) is complete and
tested. The frontend (Blade screens) is not built yet. **All tariff data is SAMPLE data**; see
[known issues](docs/HANDOVER.md#known-issues).

> Estimates are not ZRA's official decisions. Verify all regulatory facts with ZRA before launch.

## Contents

- [How it works](#how-it-works)
- [Setup on XAMPP (Windows)](#setup-on-xampp-windows)
- [Environment keys](#environment-keys)
- [Running the tests](#running-the-tests)
- [Endpoints](#endpoints)
- [Admin endpoints](#admin-endpoints)
- [Deployment notes](#deployment-notes)
- [More documents](#more-documents)

## How it works

```
Trader's browser (Blade + JS, later)
        |
routes/web.php  ->  thin controllers + Form Requests
        |
 -----------------------------------------------------------------------
 |                              |                                      |
ProductIdentificationService   CatalogueSearchService                 EstimateService
(AI only re-ranks catalogue    (aliases + FULLTEXT + LIKE,            (BigDecimal maths, rules as data,
 IDs Laravel sends it)          works with no AI)                      one path for text and photo)
        \_____________________________|______________________________________/
                       ProductSelection (session: the item the trader confirmed)
```

Rules the code keeps to:

1. **AI suggests, the trader confirms, Laravel calculates.** No estimate is made for an item that was not confirmed in the session.
2. **Rates are never invented.** Missing data gives `unsupported` or `incomplete`, naming what is missing.
3. **No floats in the money path.** DECIMAL columns, `brick/math` BigDecimal, amounts as 2-decimal strings in JSON. Each charge is rounded once, 2 dp, half up.
4. **Tax order and bases are data** (`tariff_rules.sequence` and `base_components`), not code.
5. **Rates are never edited in place.** A change ends the old row and adds a new one. Every estimate copies the values it used.
6. **Every tariff line and rule names its source and page.**

| Folder | Contents |
| --- | --- |
| `app/Services/Estimate` | `EstimateService`, `RuleEvaluator`, request and result objects |
| `app/Services/Catalogue` | `CatalogueSearchService`, `ProductSelection` |
| `app/Services/Identification` | AI client interface, fake and HTTP clients, response validator, identification service |
| `app/Services/TariffData` | CSV reader, `TariffDataValidator`, `TariffCsvImporter` |
| `app/Services/Admin` | `RateChangeService`, `AuditLog` (the single audit writer) |
| `database/data/sample` | SAMPLE CSVs (not verified); format described in its [README](database/data/sample/README.md) |
| `database/data/verified` | Verified rows from the tariff book (empty until sourced) |

## Setup on XAMPP (Windows)

Requirements: XAMPP with **PHP 8.2** (8.2.12 is the reference version), its **MariaDB 10.4** (or MySQL 8), and
[Composer](https://getcomposer.org/). Do not use PHP 8.3-only features: Composer is pinned to PHP 8.2.12.

1. Get the code and install packages:

   ```bash
   git clone https://github.com/botbot444/Duty_calc.git duty_calc
   cd duty_calc
   composer install
   ```

2. Create the environment file and app key:

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. Start MySQL in the XAMPP control panel, then create the two databases (app and tests):

   ```bash
   C:/xampp/mysql/bin/mysql.exe -uroot -e "CREATE DATABASE duty_calc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE dutycalc_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   ```

4. Create the tables and load the SAMPLE data:

   ```bash
   php artisan migrate --seed
   ```

   To start again from empty: `php artisan migrate:fresh --seed`. The importer only ever adds rows, so it refuses
   to load the same data twice.

5. Create an admin (asks for a password of at least 12 characters):

   ```bash
   php artisan make:admin you@example.com --name="Your Name"
   ```

6. Run the app:

   ```bash
   php artisan serve
   ```

   Open http://127.0.0.1:8000. **Never serve the project through Apache** (http://localhost/duty_calc): that would
   expose `.env` and the source code.

## Environment keys

Values live only in `.env`, which is never committed. `.env.example` lists every key without secrets.

| Key | Purpose |
| --- | --- |
| `APP_KEY` | Set by `php artisan key:generate` |
| `APP_ENV`, `APP_DEBUG`, `APP_URL` | Standard Laravel; `APP_DEBUG=false` in production |
| `APP_TIMEZONE` | `Africa/Lusaka`. Every "in force today" check uses Lusaka time |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Database (MySQL driver for both MariaDB and MySQL) |
| `SESSION_DRIVER` | `database`. The confirmed product is kept in the session |
| `IDENTIFICATION_DRIVER` | `fake` (default, no network) or `http` (the AI engineer's service) |
| `IDENTIFICATION_URL` | The identification endpoint (needed for `http`) |
| `IDENTIFICATION_API_KEY` | Sent as `Authorization: Bearer …`; read only through `config/services.php` |
| `IDENTIFICATION_MODEL` | Optional; recorded on each analysis |
| `IDENTIFICATION_TIMEOUT` | Seconds, default 15 (one retry on a connection failure) |
| `DUTYCALC_MISSING_FREIGHT_INSURANCE` | `zero` (default: blank counts as 0 and is listed as an assumption) or `incomplete` |

Engine settings that are not secrets (engine version, 90-day retention, required charges, disclaimer) are in
[config/dutycalc.php](config/dutycalc.php).

## Running the tests

```bash
php artisan test
```

- Tests run against the database named in `phpunit.xml` (`dutycalc_test`), never the app database.
- They never call a real AI service: `phpunit.xml` forces `IDENTIFICATION_DRIVER=fake`.
- Search tests use `DatabaseTruncation` (through `tests/Support/FullTextDatabase.php`), because InnoDB FULLTEXT ignores uncommitted rows.
- Golden cases (hand-worked amounts) are in [docs/golden-cases.md](docs/golden-cases.md).

GitHub Actions ([.github/workflows/tests.yml](.github/workflows/tests.yml)) runs Pint, a fresh migrate and seed,
and the full suite on PHP 8.2 against both MariaDB 10.4 and MySQL 8 on every push.

## Endpoints

Every route is in `routes/web.php`, including the JSON ones under `/api/`, so they have the session and CSRF
protection. Errors on `/api/*` are always JSON (`422` validation, `404`, `419` CSRF, `401` guest).

**From the browser:** send the CSRF token on every POST, either as `X-XSRF-TOKEN` (from Laravel's `XSRF-TOKEN`
cookie, which axios does automatically) or as `X-CSRF-TOKEN` (from a `<meta name="csrf-token">` tag). Send numbers
as **strings** (`"10000.50"`); JSON numbers, thousands separators and exponents are refused.

### Trader journey

| Method | Path | Does |
| --- | --- | --- |
| GET | `/api/catalogue/search?q=&lang=` | Plain search (no AI): top 10 active items |
| POST | `/api/identify/text` | `{description, lang}`: search candidates re-ranked by the AI; plain search if the AI fails |
| POST | `/api/identify/image` | multipart `photo` (JPEG/PNG/WebP, max 5 MB), optional `description`, `lang` |
| POST | `/api/identify/clarify` | `{analysis_id, answers: [{question, answer}]}`: re-rank after a question (max 2 rounds) |
| POST | `/api/selection` | `{catalogue_item_id, ai_product_analysis_id?}`: the trader confirms an item |
| GET | `/api/routes` | Supported routes (origin, destination, border post) |
| GET | `/api/currencies` | ZMW plus currencies with an exchange rate in force today |
| POST | `/api/estimates` | Calculates, saves and returns the estimate (201, `Location` header) |
| GET | `/estimates/{uuid}` | A saved estimate exactly as calculated; 404 when unknown or older than 90 days |

`lang` is `en`, `bem` or `sw`.

### Example: search, select, estimate

Responses below are real output from the SAMPLE data (sugar: "40% or K5.00 per kg, whichever is higher", then VAT 16%).

`GET /api/catalogue/search?q=sukari&lang=sw`

```json
{
  "query": "sukari",
  "language": "sw",
  "count": 1,
  "results": [
    {
      "id": "CAT-0004",
      "name": "Sugar (white)",
      "hs_code": "17019900",
      "official_description": "Cane or beet sugar, other (SAMPLE)",
      "stat_unit": "kg",
      "matched_alias": "sukari",
      "matched_language": "sw",
      "match_type": "exact"
    }
  ]
}
```

`POST /api/selection` with `{"catalogue_item_id": "CAT-0004"}`

```json
{
  "selected": { "id": "CAT-0004", "name": "Sugar (white)", "hs_code": "17019900",
                "official_description": "Cane or beet sugar, other (SAMPLE)", "stat_unit": "kg" },
  "required_inputs": { "quantity": { "unit": "kg", "unit_label": "kilograms" } },
  "optional_inputs": ["freight", "insurance"]
}
```

`POST /api/estimates`

```json
{
  "catalogue_item_id": "CAT-0004",
  "trade_route_id": 1,
  "declared_value": "10000",
  "currency": "ZMW",
  "freight": "0",
  "insurance": "0",
  "quantity": "500"
}
```

Response (`201 Created`):

```json
{
  "status": "estimated",
  "estimate_id": "01a1f565-94cb-7d43-92b1-a7d49f0e92df",
  "currency": "ZMW",
  "product": { "id": "CAT-0004", "name": "Sugar (white)", "hs_code": "17019900",
               "official_description": "Cane or beet sugar, other (SAMPLE)", "stat_unit": "kg" },
  "route": { "id": 1, "origin": "TZ", "origin_name": "Tanzania", "destination": "ZM",
             "destination_name": "Zambia", "border_post": "Nakonde" },
  "valuation": {
    "basis": "CIF", "input_currency": "ZMW", "exchange_rate": "1.00000000",
    "exchange_rate_date": null, "exchange_rate_source": null,
    "declared_value_zmw": "10000.00", "freight_zmw": "0.00", "insurance_zmw": "0.00",
    "customs_value_zmw": "10000.00", "quantity": "500.0000", "quantity_unit": "kg"
  },
  "line_items": [
    {
      "tax_type": "customs_duty", "label": "Customs duty", "sequence": 1,
      "method": "compound", "rate": "0.400000", "specific_amount": "5.0000", "specific_currency": "ZMW",
      "combine": "max", "quantity": "500.0000", "base_components": ["customs_value"],
      "taxable_base": "10000.00", "amount": "4000.00", "rule_effective_from": "2026-01-01",
      "source": { "name": "SAMPLE – not verified, for testing only", "page": 1 }
    },
    {
      "tax_type": "vat", "label": "VAT", "sequence": 3,
      "method": "ad_valorem", "rate": "0.160000", "specific_amount": null, "specific_currency": null,
      "combine": null, "quantity": null, "base_components": ["customs_value", "customs_duty"],
      "taxable_base": "14000.00", "amount": "2240.00", "rule_effective_from": "2026-01-01",
      "source": { "name": "SAMPLE – not verified, for testing only", "page": 1 }
    }
  ],
  "total_estimated_charges": "6240.00",
  "tariff_effective_date": "2026-01-01",
  "calculated_on": "2026-11-19",
  "assumptions": [
    "Values entered in ZMW: no conversion.",
    "Customs value is CIF: declared value + freight + insurance, converted to ZMW.",
    "Charged at the standard rate: no trade preference (for example COMESA or SADC) is claimed.",
    "Each charge is rounded once, to 2 decimals, half up (to be confirmed with ZRA)."
  ],
  "missing": [],
  "disclaimer": "This is an estimate only, not an official ZRA assessment. The amount charged at the border may differ. Check with ZRA or a clearing agent before you travel.",
  "engine_version": "1.0.0"
}
```

When the estimate cannot be made, the same shape comes back with no line items and the reasons in `missing`:

```json
{
  "status": "incomplete",
  "line_items": [],
  "total_estimated_charges": null,
  "missing": [
    { "item": "quantity", "message": "Enter the quantity in kilograms (kg): the Customs duty is charged per kg." }
  ]
}
```

`unsupported` means our data cannot support the request: an unknown or inactive item, an unsupported route, no
exchange rate in force, or a missing or ended rate (`missing[].item` is `catalogue_item`, `tariff_line`,
`trade_route`, `tariff_rule` or `exchange_rate`). `incomplete` means the trader left out an input (`quantity`, or
`freight` / `insurance` when so configured).

### Example: identification

`POST /api/identify/text` with `{"description": "sukari", "lang": "sw"}`. The response is illustrative: the
label, reason and normalised description come from the AI service (the fake client keeps search order).

```json
{
  "analysis_id": 12,
  "input_type": "text",
  "status": "ok",
  "ai_used": true,
  "normalised_description": "white sugar",
  "clarification_questions": [],
  "round": 0,
  "max_rounds": 2,
  "manual_review": false,
  "message": null,
  "count": 1,
  "results": [
    { "id": "CAT-0004", "name": "Sugar (white)", "hs_code": "17019900",
      "official_description": "Cane or beet sugar, other (SAMPLE)", "stat_unit": "kg",
      "label": "High", "reason": "sukari is Swahili for sugar" }
  ]
}
```

`status` is `ok`, `no_match`, `needs_clarification` (with `clarification_questions`), `fallback` (the AI failed and
these are plain search results) or `error` (photo could not be identified; `message` asks the trader to type the
name). The trader then confirms with `POST /api/selection`, passing `ai_product_analysis_id`.

The contract with the AI service is in [section 8 of the plan](docs/IMPLEMENTATION_PLAN.md#8-core-3--ai-service-contract-and-photo-route).

## Admin endpoints

Session login for admins created with `php artisan make:admin`. Every route below needs a logged-in admin.
There is no route that edits a rate in place, and every save writes an `admin_audit_logs` row.

| Method | Path | Does |
| --- | --- | --- |
| POST | `/api/admin/login` | `{email, password}` (5 tries a minute) |
| POST | `/api/admin/logout` | |
| GET | `/api/admin/me` | The logged-in admin |
| GET / POST | `/api/admin/tariff-sources` | List / create a source |
| PATCH / DELETE | `/api/admin/tariff-sources/{id}` | Correct / delete (409 while used by lines or rules) |
| GET / POST | `/api/admin/exchange-rates` | List / add a rate. Adding ends the currency's open rate the day before |
| POST | `/api/admin/imports` | multipart `files[]` named like `tariff_rules.csv`: **preview** (validates, saves nothing, returns a token) |
| POST | `/api/admin/imports/{token}/confirm` | Import the previewed files (token works once) |
| GET | `/api/admin/tariff-lines?hs=` | Lines with their rules, dates and sources |
| GET | `/api/admin/tariff-lines/{id}` | One line |
| POST | `/api/admin/tariff-rules/{id}/replace` | **Replace this rate**: `{effective_from, rate?, specific_amount?, …, tariff_source_id, source_page}` |
| POST | `/api/admin/tariff-rules/{id}/end` | `{effective_to}`: end a rate (an end date can only move earlier) |
| GET / POST | `/api/admin/catalogue-items` | List / create an item |
| PATCH | `/api/admin/catalogue-items/{id}` | `{display_name?, active?}`: items are switched off, never deleted |
| POST / DELETE | `/api/admin/catalogue-items/{id}/aliases[/{aliasId}]` | Add / remove a search alias |
| GET | `/api/admin/trade-routes` | All routes |
| POST | `/api/admin/trade-routes/{id}/toggle` | `{supported: true|false}` |
| GET | `/api/admin/audit-logs?table=&action=&record_id=` | The change log, newest first |

## Deployment notes

Not hosted yet. A phone demo needs:

- **HTTPS** (camera upload and secure cookies). Set `APP_URL` to the HTTPS address, `APP_ENV=production`, `APP_DEBUG=false`, and `SESSION_SECURE_COOKIE=true`.
- **Web root = `public/`** only. Never expose the project root.
- **MySQL 8** (or a supported MariaDB). XAMPP's MariaDB 10.4 is end-of-life; CI tests both.
- **PHP 8.2** with `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, `curl`.
- **Cron** for the scheduler, which deletes estimates and AI analyses older than 90 days (`estimates:prune`, daily at midnight Lusaka time):

  ```bash
  * * * * * cd /path/to/duty_calc && php artisan schedule:run >> /dev/null 2>&1
  ```

- `php artisan migrate --force`, then `php artisan config:cache` and `php artisan route:cache`.
- `storage/` and `bootstrap/cache/` writable by the web server. Uploaded photos go to `storage/app/private` and are deleted straight after analysis.
- `IDENTIFICATION_DRIVER=http`, with the URL and key, once the AI service is ready.
- Load verified tariff rows (admin CSV import or a `VerifiedTariffSeeder`) instead of the sample data.

## More documents

- [docs/IMPLEMENTATION_PLAN.md](docs/IMPLEMENTATION_PLAN.md): the full plan, with decisions and notes from each phase
- [docs/HANDOVER.md](docs/HANDOVER.md): supported products and routes, known issues, engine limitations, demo script
- [docs/golden-cases.md](docs/golden-cases.md): hand-worked test cases and who checked them
- [docs/TRANSLATION_REQUEST.md](docs/TRANSLATION_REQUEST.md): words and messages for the Bemba and Swahili translations
- [database/data/sample/README.md](database/data/sample/README.md): the CSV import format
