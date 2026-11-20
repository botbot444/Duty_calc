# DutyCalc – handover

As of 2026-11-20: backend phases B0–B8, then the export pivot (phases X1–X5 of [EXPORT_PLAN.md](EXPORT_PLAN.md)).
Read with the [README](../README.md), the [plan](IMPLEMENTATION_PLAN.md) and the export plan.

**What changed on 2026-11-20:** DutyCalc now estimates the **Zambian export duty** for traders taking goods from
Zambia into DR Congo through Kasumbalesa. DR Congo charges are not included. Results are shown in USD first with ZMW
beside them. The import engine features (customs duty, excise, VAT, CIF) are still in the code and covered by tests,
but switched off in `config/dutycalc.php`.

## Handover checklist

| Item | Status |
| --- | --- |
| Migrations and seeders with source and effective date on every tariff row | Done for SAMPLE data. Verified export duty rows still to be sourced (`database/data/verified/` is empty) |
| README: setup, required `.env` keys (no values), endpoint list with request/response examples | Done ([README](../README.md)), updated for exports |
| Supported products and routes list, known issues, engine limitations | Done (this file) |
| Golden test cases with who checked them and against which source | Export cases E1–E7 checked by hand; method sign-off by a clearing agent or ZRA still open ([golden-cases.md](golden-cases.md)) |
| Demo steps and demo data | Steps below. Demo data = the SAMPLE seed. Runs live at `/` (`php artisan serve`) |
| CI on every push (PHP 8.2, MariaDB 10.4 and MySQL 8) | Workflow in [tests.yml](../.github/workflows/tests.yml) |
| Design for the trader screens | Direction A chosen, with error and empty states ([mockup canvas](https://claude.ai/artifact/Dqv9vQxGvkhzfuHQcP5HPv)) |

## Supported products and routes

**Everything below is SAMPLE data** (source "SAMPLE – not verified, for testing only", page 1 as a placeholder).
The HS codes look real, but the rates are invented. The 8 products are kept for testing; the real export product
list (EXPORT_PLAN Q2) replaces them once agreed and verified.

| ID | Product | HS code | Unit | Export duty (sample) | Charged on |
| --- | --- | --- | --- | --- | --- |
| CAT-0001 | Cotton T-shirt | 61091000 | u | 0% | value of the goods (FOB) |
| CAT-0002 | Cotton vest or singlet | 61091000 | u | 0% | value of the goods (FOB) |
| CAT-0003 | Rice (milled) | 10063000 | kg | K0.20 per kg | quantity |
| CAT-0004 | Sugar (white) | 17019900 | kg | 5% or K0.10 per kg, whichever is higher | value of the goods (FOB) |
| CAT-0005 | Soft drink (fizzy and sweetened) | 22021000 | l | 10% | value of the goods + freight |
| CAT-0006 | Phone charger | 85044000 | u | 0% | value of the goods (FOB) |
| CAT-0007 | Colour television | 85287200 | u | 0% | value of the goods (FOB) |
| CAT-0008 | Cooking oil (soya) | 15079000 | kg | 2% plus K0.05 per kg | value of the goods (FOB) |

| Route | Border post | Status |
| --- | --- | --- |
| Zambia → DR Congo | Kasumbalesa | Supported (to be confirmed) |
| Zambia → DR Congo | Mokambo | Not supported (used to demo a refusal) |
| Zambia → DR Congo | Kipushi | Not supported |

Currencies: values can be entered in ZMW (no conversion) or USD (SAMPLE rate from 2026-11-16). Results are shown
in USD at the same rate, with ZMW beside them.

## Known issues

Most urgent first.

1. **No verified export duty data yet.** Every number comes from SAMPLE rows. The legal source of Zambian export
   duties (tariff book column, Act schedule or ZRA notice) is still to be found (EXPORT_PLAN Q3). Each product needs a
   row with a source and page, including a **0% row** where there is no duty: a missing row is `unsupported`, never zero.
2. **What export duty is charged on is not confirmed** (EXPORT_PLAN Q1). The sample uses the value of the goods (FOB).
   This is data on each rule (`base_components`), so a correction is a data change, and the form asks for freight or
   insurance only if a rule needs them.
3. **Many products may carry 0% export duty**, so many estimates may show K0.00 (EXPORT_PLAN Q5).
4. **Other Zambian charges at exit** (levies, permit fees) are not modelled until their legal source is known (Q4).
5. **Rounding (2 dp, half up, each charge once) is not confirmed by ZRA.** Every estimate lists it as an assumption.
   USD amounts are each converted and rounded once, so with several charges the USD lines can differ from the USD
   total by a cent.
6. **The USD rate is entered by hand** (admin). Which rate ZRA uses is not known yet. With no USD rate in force the
   result is shown in ZMW only.
7. **The AI service has not been connected.** `HttpIdentificationClient` follows the agreed contract but has only run
   against simulated responses. The photo demo step needs the real service.
8. **The Bemba and Swahili aliases and screen texts are placeholders.** Send the changed lines in EXPORT_PLAN section 6
   to the language specialist. French is not planned.
9. **Messages are saved in English.** Showing them in Bemba or Swahili needs message keys with parameters, a small
   engine change to make with the frontend.
10. **Admin pages are not built.** The trader screens are done; admin functions are JSON endpoints only. The trader
    screens were checked in a desktop browser at phone size, not yet on a real Android or iPhone on a slow connection.
11. **Only the admin login is rate-limited.** Add a limit to the public identify endpoints once AI usage limits are known.
12. **XAMPP's MariaDB 10.4 is end-of-life.** Hosting should use MySQL 8 or a supported MariaDB. CI tests both.

## Engine limitations

These are by design for the MVP. Each one fails safely rather than guessing.

- **Zambian charges only.** `charged_tax_types` is `export_duty`; DR Congo charges are out of scope.
- **Standard rates only.** STR and trade preferences (COMESA, SADC) are out of scope; every estimate says so.
- **No unit conversion.** The trader enters quantity in the tariff line's unit (kg, litres or pieces).
- **Per-unit amounts must be in ZMW.** A rule with a specific amount in another currency returns `unsupported`.
- **One currency per estimate.** Value, freight and insurance are entered in the same currency.
- **Money is worked out in ZMW.** USD is a display conversion of the rounded ZMW figures.
- **Export duty must be in force** for every line (`required_tax_types`). A charge that ended without a new rate stops
  the estimate (`unsupported`).
- **No permits, bans or quotas** (for example on maize exports). The engine does not check them.
- **Estimates are kept for 90 days**, then deleted with their AI analysis records. No personal data is stored.
- **Confirmation is per browser session.** An estimate must come from the same session that confirmed the product.

## Upgrading an existing database

The export pivot adds columns with a new migration only (nothing is dropped). On a development database:
`php artisan migrate`, then load the new sample rows. The importer only adds rows, so a database that already has
the old import sample needs a fresh start (`php artisan migrate:fresh --seed`, which **deletes all data**).

## Demo script

Uses the SAMPLE seed, in a browser at `/`. Steps 1, 3 and 4 work with the fake AI client; step 2 needs the real
identification service. The same journey also runs as the feature tests `TextJourneyTest` and `PhotoJourneyTest`.

1. **Text, in Swahili.** Search "sukari" → choose Sugar (white) → Zambia → DR Congo via Kasumbalesa, value
   ZMW 10,000, quantity 500 kg. No freight or insurance is asked for.
   Result: export duty ZMW 500.00 (5% beats K0.10/kg), **total USD 18.87 (ZMW 500.00)**, with its source and date.
2. **Photo with a clarification question.** Photograph a product → answer the question → confirm → the same form and engine.
3. **Change the value.** Same sugar, value ZMW 500, quantity 1,000 kg: now the K0.10/kg side wins. **ZMW 100.00, USD 3.77.**
4. **A refusal instead of a guess.** Choose Mokambo: "The route Zambia to DR Congo via Mokambo is not supported yet."
   Or choose rice without a quantity: "Enter the quantity in kilograms (kg)…".

Before the demo: run `php artisan test`.
