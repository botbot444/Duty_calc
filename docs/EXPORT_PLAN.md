# DutyCalc – Export change plan (Zambia → DR Congo)

**Date:** 2026-11-20
**Status:** X1–X5 built on SAMPLE data (2026-11-20), using the suggested defaults for Q1–Q5. Q6 answered: no French.
Sourcing verified export duty rows (Q1–Q4) is still open.
**Replaces, for the MVP:** the import route in `docs/IMPLEMENTATION_PLAN.md` (Tanzania → Zambia via Nakonde)

## 1. What changed and why

The real users are Zambian small-scale traders **taking goods out of Zambia into DR Congo**, not traders importing into Zambia.
The team agreed (2026-11-20):

| Decision | Choice |
| --- | --- |
| Charges shown | **Zambian side only** – Zambian export duty and any other Zambian charge at exit. DR Congo charges are not included. |
| Direction | **Exports to Congo only** for the MVP. The Nakonde import route is dropped. |
| Border post | **Kasumbalesa only** for the first version. |
| Currency shown | **USD first, with the ZMW amount beside it.** |
| Design | Direction **A (Border Ledger)**, with the state screens on the design canvas. |

The non-negotiable rules in `CLAUDE.md` do not change: AI suggests, the trader confirms, Laravel calculates; never invent a rate;
no floats; tax order and bases are data; rates are never edited in place; every rule has a source and page.

## 2. How much of the backend survives

Most of it. The engine is already driven by data, so the direction of trade lives mainly in the rows, not the code.

| Area | Keep as is | Must change |
| --- | --- | --- |
| Catalogue search, aliases, selection, AI layer, photo route | Yes | Catalogue list (new products), aliases, AI prompt wording if it says "import" |
| `RuleEvaluator` (ad valorem, specific, compound) | Yes | – |
| `EstimateService` steps and statuses | Structure | Valuation basis, a new tax type, USD display, assumption texts |
| Rate-change rules, audit log, admin endpoints | Yes | Admin can enter `export_duty` rules and a USD rate |
| Schema | Most tables | `TaxType`, base components, USD display columns on `calculations` and `calculation_breakdowns` |
| Sample data | Format | Every row: countries, route, rules, exchange rates |
| Docs and translations | Format | `CLAUDE.md`, plan, README, handover, golden cases, translation request |

## 3. Changes, by area

### 3.1 Tax types

- Add `TaxType::ExportDuty = 'export_duty'` (label "Export duty").
- `config/dutycalc.php` → `required_tax_types` becomes `['export_duty']`.
  A product with no export duty must carry an explicit **0% export duty rule with its source and page**, so "no duty" is a sourced fact,
  never a gap. Without that row the result stays `unsupported`.
- Keep `customs_duty`, `excise` and `vat` in the enum (the code still reads old rows and saved estimates) but they are no longer required.
- Any other Zambian charge at exit (for example a levy or fee) is added as its own tax type **only once its legal source is known**.

### 3.2 Valuation basis (open question Q1)

Imports used CIF (declared value + freight + insurance). Exports are commonly valued FOB (the value of the goods at the point of exit),
but this must be confirmed with ZRA or a clearing agent.

To keep the basis in the data (rule 4), allow these base components alongside `customs_value`:

| Component | Meaning |
| --- | --- |
| `declared_value` | Value of the goods, converted to ZMW |
| `freight` | Freight to the Zambian border, converted to ZMW |
| `insurance` | Insurance to the Zambian border, converted to ZMW |
| `customs_value` | Kept: declared value + freight + insurance (CIF-style) |

An FOB rule then reads `["declared_value"]` (or `["declared_value","freight","insurance"]` if ZRA counts transport to the border).
`TariffDataValidator` must accept the new components. The form shows freight and insurance only when a rule in force uses them
(the same way `requiredInputs()` already decides on quantity).

The assumption text changes from "Customs value is CIF…" to one built from the rule's components, for example
"Export duty is charged on the value of the goods (FOB), converted to ZMW."

### 3.3 Currency: work in ZMW, show USD first

- ZRA assesses in kwacha, so the engine keeps calculating, rounding and storing every charge in **ZMW**, exactly as now.
- USD is a **display conversion** added after step 8:
  - Use the USD rate in force today (`exchange_rates`, currency `USD`), the same table and rules as input conversion.
  - `amount_usd = amount_zmw ÷ rate_to_zmw`, BigDecimal division, rounded once to 2 dp half up, per line.
  - `total_usd = total_zmw ÷ rate_to_zmw`, rounded once. Say in the assumptions that USD lines may not add up to the USD total by a cent.
  - **No USD rate in force:** still return the ZMW estimate, leave the USD fields null, and add the assumption
    "No USD rate is in force today: amounts are shown in ZMW only." Never a default rate.
- New nullable columns (additive migration, named for any display currency): `calculations.display_currency`,
  `display_rate_id`, `display_rate_value`, `display_rate_effective_from`, `display_rate_source`, `total_display`,
  `valuation_basis`, `value_for_duty_zmw`; `calculation_breakdowns.amount_display`.
- JSON keeps `total_estimated_charges` (ZMW) and adds a `display` block (`currency`, `exchange_rate`, `exchange_rate_date`,
  `exchange_rate_source`, `total`) and per-line `amount_display`, all as 2-dp strings. `display` is null when no rate is in force.
- The currency is set by `dutycalc.display_currency` (`DUTYCALC_DISPLAY_CURRENCY`, default `USD`).
- Input currencies: ZMW and USD. Drop TZS from the sample data.

### 3.4 Routes and countries

- `countries`: add `CD` (Democratic Republic of the Congo). Keep `TZ` only if old saved estimates need it.
- `trade_routes`: `ZM → CD, Kasumbalesa, supported = 1`. Optionally `ZM → CD, Mokambo` and `Kipushi` with `supported = 0`,
  so the `unsupported` route message can be shown and tested.
- Mark `TZ → ZM, Nakonde` and `MW → ZM, Mwami` as `supported = 0` (or remove them from the sample CSVs if nothing references them).
- Messages already use the route's names, so "The route Zambia to DR Congo via Mokambo is not supported yet." works unchanged.

### 3.5 Texts and assumptions

| Where | Old | New |
| --- | --- | --- |
| Assumption | "Customs value is CIF: …" | Built from the rule's base components (3.2) |
| Assumption | "Charged at the standard rate: no trade preference (COMESA or SADC) is claimed." | Keep. COMESA and SADC preferences, and the COMESA Simplified Trade Regime, stay out of scope. |
| Assumption | – | "Only Zambian charges are shown. Charges in DR Congo are not included." |
| Disclaimer | Same | Same (it already names ZRA) |
| Screen text | D2.1, D6.2–D6.5, D6.11, D7.2 | See section 6 |

### 3.6 Catalogue and data (open questions Q2–Q4)

- **The product list must be redone** for what traders actually take to Congo. The team supplies 20–30 goods; nothing is invented.
- For each product: the Zambian HS code, the export duty from the **ZRA source with page number**, or a sourced 0% row.
- Check whether `docs/Customs-Tariff-Book.pdf` contains export duties. If it does not, find the legal source
  (for example the Customs and Excise Act export duty schedule or a ZRA practice note) and register it in `tariff_sources`.
- Sample CSVs (`database/data/sample/`, source "SAMPLE – not verified") are rewritten for the export route so tests and the demo work
  before verified rows arrive.

## 4. Build phases and gates

Same discipline as B0–B8: tests first, expected amounts as strings worked out by hand, `php artisan test` green at every gate.

| Phase | Work | Gate |
| --- | --- | --- |
| **X0 – Agree** | Answer Q1–Q4 below. Freeze the export product list. Update `CLAUDE.md` and section 1 of the implementation plan. | Team sign-off on this file |
| **X1 – Schema** | `TaxType::ExportDuty`; new base components in the validator; additive migration for USD display columns; `CD` country. | `ReferenceSchemaTest`, validator tests pass |
| **X2 – Sample data** | Rewrite sample CSVs: Kasumbalesa route, SAMPLE export-duty rules (including at least one 0% row and one per-kg row), a USD rate. Remove TZS. | Importer and validator tests pass on the new files |
| **X3 – Engine** | Write new golden cases in `docs/golden-cases.md` first (ZMW input, USD input, 0% duty, per-kg duty, no USD rate, unsupported route). Then change `EstimateService`: valuation from base components, USD display step, new assumptions, `required_tax_types`. | `EstimateServiceTest` and `RuleEvaluatorTest` pass with the new cases |
| **X4 – Endpoints** | `/api/estimates` and saved results return `total_usd`, `display_currency`, `amount_usd`. Reference data lists the new route and currencies. `requiredInputs()` also reports freight/insurance when needed. | `EstimateEndpointsTest`, `TextJourneyTest`, `PhotoJourneyTest` pass |
| **X5 – Docs** | README examples, `HANDOVER.md`, translation request change list (section 6), engine version `2.0.0`. | Docs reviewed; CI green |

Rough size: X1–X2 half a day, X3 one day, X4 half a day, X5 half a day, plus however long sourcing the export duties takes.
**Sourcing is the critical path, not the code.**

**Built (2026-11-20):** all five phases, with these choices:

- `TaxType::ExportDuty`; new `App\Enums\ValueComponent` (`declared_value`, `freight`, `insurance`, `customs_value`).
- `config/dutycalc.php`: `charged_tax_types` and `required_tax_types` are `['export_duty']`; rules of other types on a
  line are ignored. `home_country` = `ZM` adds the assumption "Only charges in Zambia are shown. Charges in DR Congo
  are not included." on routes leaving Zambia.
- The import golden cases G1–L2 still run, with the import charges switched on by `TariffFixtures::chargeImportTaxes()`,
  as tests of the rule mechanics. Export cases E1–E7 are in `ExportEstimateTest`.
- `POST /api/selection` returns `optional_inputs` from the rules (empty for FOB).
- A line whose only charged rule has ended names that rate; one with nothing in force says "No tax rates are in force".
- Engine version `2.0.0`. Sample CSVs rewritten for Kasumbalesa.

## 5. Open questions

| # | Question | Who answers | Suggested default until answered |
| --- | --- | --- | --- |
| Q1 | What value is Zambian export duty charged on: FOB, and does it include transport to the border? | ZRA or a clearing agent | FOB = value of the goods only; freight and insurance not asked |
| Q2 | Which 20–30 products do traders take to Congo? | Team, from traders at Kasumbalesa | Keep the 8 sample products for tests only |
| Q3 | Where are Zambian export duties published (tariff book column, Act schedule, ZRA notice)? | Team / ZRA | Sample rows only, clearly marked |
| Q4 | Are there other Zambian charges at exit for small traders (levies, permits with fees)? | ZRA or a clearing agent | None shown; the assumption says only export duty is included |
| Q5 | Most small-trader goods may carry **0% export duty**. Is an estimate that often says "K0.00" still useful, or should DR Congo charges come back into scope after the MVP? | Team | Ship Zambian side first; revisit after demo feedback |
| Q6 | Should French be added for traders and agents on the Congo side? | Team | **Answered 2026-11-20: no French.** English, Bemba and Swahili stay |

## 6. Translation request: lines that change

Send these to the language specialist as a short change list (English source shown).

| # | Old | New |
| --- | --- | --- |
| D2.1 | What are you bringing into Zambia? | What are you taking out of Zambia? |
| D6.2 | Where are the goods coming from? | Where are the goods going? |
| D6.3 | Country the goods come from | Country the goods go to |
| D6.5 | Going to: Zambia | Going from: Zambia |
| D6.11 | Tanzanian shilling | (removed) |
| D7.2 | Estimated total to pay at the border | Estimated total to pay at the Zambian border |
| C1 | – | `tax.export_duty`: Export duty |
| C5 | A1 (CIF) | Replaced by the valuation text from 3.2 |
| C5 | – | Only Zambian charges are shown. Charges in DR Congo are not included. |
| C5 | – | No USD rate is in force today: amounts are shown in ZMW only. |
| Part A | Product names | Redo once the export product list (Q2) is fixed |

## 7. Out of scope (unchanged, plus)

STR and trade preferences, DR Congo charges, vehicle exports, USSD, payments, ASYCUDA, consignment tracking, AI usage limits, hosting.
