# DutyCalc – handover

As of 2026-11-19, end of backend phase B8. Read with the [README](../README.md) and the [plan](IMPLEMENTATION_PLAN.md).

## Handover checklist

| Item | Status |
| --- | --- |
| Migrations and seeders with source and effective date on every tariff row | Done for SAMPLE data. Verified rows still to be sourced (`database/data/verified/` is empty) |
| README: setup, required `.env` keys (no values), endpoint list with request/response examples | Done ([README](../README.md)) |
| Supported products and routes list, known issues, engine limitations | Done (this file) |
| Golden test cases with who checked them and against which source | Arithmetic checked; method sign-off by a clearing agent or ZRA still open ([golden-cases.md](golden-cases.md)) |
| Demo steps and demo data | Steps below. Demo data = the SAMPLE seed. Needs the frontend for a live demo |
| CI on every push (PHP 8.2, MariaDB 10.4 and MySQL 8) | Workflow added ([tests.yml](../.github/workflows/tests.yml)); first run happens on the next push |

## Supported products and routes

**Everything below is SAMPLE data** (source "SAMPLE – not verified, for testing only", page 1 as a placeholder).
The HS codes look real, but the rates are invented. The agreed list of 20–30 goods replaces it once verified.

| ID | Product | HS code | Unit | Duty (sample) | Other charges (sample) |
| --- | --- | --- | --- | --- | --- |
| CAT-0001 | Cotton T-shirt | 61091000 | u | 25% | VAT 16% |
| CAT-0002 | Cotton vest or singlet | 61091000 | u | 25% | VAT 16% |
| CAT-0003 | Rice (milled) | 10063000 | kg | K1.50 per kg | VAT 16% |
| CAT-0004 | Sugar (white) | 17019900 | kg | 40% or K5.00 per kg, whichever is higher | VAT 16% |
| CAT-0005 | Soft drink (fizzy and sweetened) | 22021000 | l | 25% | Excise 3% on CIF + duty; VAT 16% on CIF + duty + excise |
| CAT-0006 | Phone charger | 85044000 | u | 15% | VAT 16% |
| CAT-0007 | Colour television | 85287200 | u | 25% | VAT 16% |
| CAT-0008 | Cooking oil (soya) | 15079000 | kg | 5% plus K0.50 per kg | VAT 16% |

| Route | Border post | Status |
| --- | --- | --- |
| Tanzania → Zambia | Nakonde | Supported (to be confirmed) |
| Malawi → Zambia | Mwami | Not supported (backup corridor; used to demo a refusal) |

Currencies: ZMW (no conversion), plus USD and TZS with SAMPLE rates from 2026-11-16.

## Known issues

Most urgent first.

1. **The sample VAT rows end on 2026-12-31.** From 1 January 2027 every sample item returns `unsupported` until a
   new VAT row is added. That is deliberate: rates are never invented. The 2027 Budget proposes 16.5% from
   1 Jan 2027, but it is not law yet. Once it is enacted, add it with "replace this rate" or a CSV import. Do this
   before any demo after 31 December.
2. **No verified tariff data yet.** Every number the app produces today comes from SAMPLE rows. The verified rows
   (copied from the ZRA tariff book with page numbers and checked by a second person) are still open question 2 in
   the plan.
3. **The tax order and bases are not confirmed by ZRA.** The sample uses duty on CIF, then excise, then VAT on
   CIF + duty + excise. These are data, so a correction is a data change.
4. **Rounding (2 dp, half up, each charge once) is not confirmed by ZRA.** Every estimate lists it as an assumption.
5. **Blank freight or insurance counts as ZMW 0.00.** The team still has to agree this from ZRA guidance. Setting
   `DUTYCALC_MISSING_FREIGHT_INSURANCE=incomplete` asks the trader instead.
6. **The AI service has not been connected.** `HttpIdentificationClient` follows the agreed contract but has only run
   against simulated responses. Until the service exists, the fake client keeps Laravel's candidate order. A photo
   "identifies" the first catalogue item, so the photo demo step needs the real service.
7. **Which exchange rate customs uses is not known yet** (open question 4). Admins enter rates by hand; every result
   shows the rate and its date.
8. **The Bemba and Swahili aliases are placeholders**, the Bemba ones especially. The language specialist's list
   ([TRANSLATION_REQUEST.md](TRANSLATION_REQUEST.md)) replaces them.
9. **Messages are saved in English.** The `missing` and `assumptions` texts in a saved estimate are English sentences.
   Showing them in Bemba or Swahili needs message keys with parameters, a small engine change to make with the
   frontend (the English texts are already listed in the translation request).
10. **No frontend yet.** The trader screens and admin pages are not built. Admin functions are JSON endpoints only.
11. **Only the admin login is rate-limited.** The public identify endpoints call the AI service, so add a limit there
    once the AI usage limits are known (out of scope for now).
12. **XAMPP's MariaDB 10.4 is end-of-life.** It works for development, but hosting should use MySQL 8 or a supported
    MariaDB. CI tests both.

## Engine limitations

These are by design for the MVP. Each one fails safely rather than guessing.

- **One destination:** Zambia. Tariff lines and rules have no destination column.
- **Standard rates only.** STR and trade preferences (COMESA, SADC) are out of scope. Origin-specific rules are
  ignored, and every estimate states "no trade preference is claimed".
- **No unit conversion.** The trader enters quantity in the tariff line's unit (kg, litres or pieces).
- **Per-unit amounts must be in ZMW.** A rule with a specific amount in another currency returns `unsupported`.
- **One currency per estimate.** Value, freight and insurance are entered in the same currency.
- **Customs value is CIF.** Other valuation methods and adjustments (commissions, royalties, used goods) are not modelled.
- **Customs duty and VAT must be in force** for every line (`required_tax_types`). A charge that does not apply must
  be entered as a 0% rule, never left out.
- **A charge that ended without a new rate stops the estimate** (`unsupported`), even if it is not required.
- **No excise goods, permits or prohibitions.** Alcohol, tobacco, fuel and goods needing an import permit are left out
  of the catalogue on purpose. The engine does not check permits or quotas.
- **Estimates are kept for 90 days**, then deleted with their AI analysis records. No personal data is stored:
  traders have no accounts, and photos are deleted straight after analysis.
- **Confirmation is per browser session.** An estimate must come from the same session that confirmed the product.

## Demo script

Uses the SAMPLE seed (`php artisan migrate:fresh --seed`). Steps 1, 3 and 4 work with the fake AI client. Step 2
needs the real identification service (see known issue 6). Until the frontend exists, the same journey runs as the
feature tests `TextJourneyTest` and `PhotoJourneyTest`.

1. **Text, in Swahili.** Search "sukari" → choose Sugar (white) → route Tanzania → Zambia via Nakonde, value
   ZMW 10,000, freight 0, insurance 0, quantity 500 kg.
   Result: duty ZMW 4,000.00 (40% beats K5/kg), VAT ZMW 2,240.00, **total ZMW 6,240.00**, each with its source and date.
2. **Photo with a clarification question.** Photograph a T-shirt → answer the question (for example "Does it have
   sleeves?") → confirm → the same trade-details form and the same engine.
3. **Change the quantity or value.** Same sugar, value ZMW 2,000, quantity 500 kg: now the K5/kg side wins.
   Duty ZMW 2,500.00, VAT ZMW 720.00, **total ZMW 3,220.00**.
4. **A refusal instead of a guess.** Choose the Malawi → Zambia via Mwami route: "The route Malawi to Zambia via
   Mwami is not supported yet." Or choose rice without a quantity: "Enter the quantity in kilograms (kg)…".

Before the demo: check the date against known issue 1, and run `php artisan test`.
