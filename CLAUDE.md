# DutyCalc – project context for Claude

DutyCalc estimates customs duty, excise and VAT for small-scale cross-border traders importing into Zambia,
before they reach the border. Hackathon MVP, 12-day build. **Current focus: backend only.**

The full plan is in `docs/IMPLEMENTATION_PLAN.md`. Read it before starting a task, and follow its build phases (B0–B8) and gates in order.
Team copy of the plan: https://claude.ai/artifact/WRhatHmENUfJbFjThpCz33

## Stack and environment

- Laravel 12, PHP 8.2 (must run on XAMPP's PHP 8.2.12). Do not use PHP 8.3-only features.
- Database: XAMPP's MariaDB 10.4 (MySQL-compatible, port 3306), not SQLite. Tests use the test database named in `phpunit.xml` (`dutycalc_test`). Avoid MySQL-8-only SQL (CTEs are fine; no `JSON_TABLE`, no functional indexes).
- App timezone: Africa/Lusaka. Every "in force today" check uses Lusaka time.
- Run with `php artisan serve`. Never serve the project root through Apache/htdocs.
- Money maths: `brick/math` `BigDecimal` only.

## Non-negotiable rules

1. **AI suggests, the trader confirms, Laravel calculates.** No estimate from an unconfirmed or unknown catalogue item.
2. **Never invent a rate.** Missing data returns `unsupported` or `incomplete` with the missing items named. No default percentages.
3. **No `float` in the money path.** DECIMAL columns, BigDecimal in PHP, amounts as 2-decimal strings in JSON. Round each line once, 2 dp, half up.
4. **Tax order and bases are data** (`tariff_rules.sequence` and `base_components`), not `if` statements.
5. **Rates are never edited in place once used.** Changes end the old row (`effective_to`) and add a new one. Estimates copy every value they used.
6. **Every tariff line and rule has a source and page number** (`tariff_sources`). Reject rows without them.
7. **Text and photo routes share one selection step and one `EstimateService`.** Never duplicate calculation logic.
8. **All routes go in `routes/web.php`** (session + CSRF), including JSON endpoints under `/api/...`.
9. **API keys only in `.env`**, read through `config/services.php`. Never commit `.env` or the tariff PDF.
10. **The AI returns catalogue item IDs only.** Laravel checks each one exists and drops unknown IDs.

## Naming

- Trade routes: table `trade_routes`, model `TradeRoute` (not `Route`, which clashes with the facade).
- Catalogue items use string IDs such as `CAT-0012`.
- Services: `app/Services/Estimate`, `app/Services/Catalogue`, `app/Services/Identification`, `app/Services/TariffData`.
- Sample CSVs in `database/data/sample/` (source named "SAMPLE – not verified"); verified rows in `database/data/verified/`.

## Testing

- Write the engine tests before the engine; expected amounts as strings worked out by hand.
- FULLTEXT search tests use `DatabaseTruncation`, not `RefreshDatabase` (InnoDB FULLTEXT ignores uncommitted rows).
- Fix the clock with `Carbon::setTestNow()` for date-dependent tests.
- Use `FakeIdentificationClient` for AI tests; never call a real AI service in tests.
- Run `php artisan test` before calling a task done.

## Out of scope

STR and trade preferences, vehicle imports, USSD, payments, ASYCUDA, consignment tracking, AI usage limits, hosting (for now).
