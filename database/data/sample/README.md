# Sample tariff data – NOT VERIFIED

Everything in this folder is made up so the engine and search can be built and tested
before verified rows arrive from the ZRA tariff book. **Do not use these numbers for a real estimate.**

- Source on every row: "SAMPLE – not verified, for testing only". Page number `1` is a placeholder.
- HS codes are real-looking 8-digit codes, but descriptions, rates and specific amounts are invented.
- Since the export pivot (`docs/EXPORT_PLAN.md`) the route is Zambia → DR Congo via Kasumbalesa, and the only
  charge is **export duty** (`export_duty`). Mokambo and Kipushi are listed as not supported, so the
  `unsupported` route message can be tried.
- Most lines carry a sourced **0% export duty** row: "no duty" must be a row with a source, never a gap.
- The 8 products are kept for testing only. The real export product list is still to be agreed (plan, Q2).
- Bemba and Swahili aliases are placeholders; the language specialist must confirm them.
- The USD exchange rate is invented. ZMW needs no row.

What the lines cover:

| HS code  | Item(s)              | Export duty (SAMPLE)                          | Base                     |
| -------- | -------------------- | --------------------------------------------- | ------------------------ |
| 61091000 | CAT-0001, CAT-0002   | 0% ad valorem                                 | declared value (FOB)     |
| 10063000 | CAT-0003             | K0.20 per kg (specific)                       | –                        |
| 17019900 | CAT-0004             | 5% or K0.10 per kg, higher (compound max)     | declared value (FOB)     |
| 22021000 | CAT-0005             | 10% ad valorem                                | declared value + freight |
| 85044000 | CAT-0006             | 0% ad valorem                                 | declared value (FOB)     |
| 85287200 | CAT-0007             | 0% (alias "tv" tests short words)             | declared value (FOB)     |
| 15079000 | CAT-0008             | 2% plus K0.05 per kg (compound sum)           | declared value (FOB)     |

## File format

Loaded in this order by `App\Services\TariffData\TariffCsvImporter`; each file is optional.
Blank cells mean null. Booleans: `1/0`, `true/false` or `yes/no`.

1. `tariff_sources.csv` – name, document_url, edition, published_on, retrieved_on, verified_by
2. `countries.csv` – iso2, name, is_origin, is_destination
3. `trade_routes.csv` – origin_iso2, destination_iso2, border_post, supported, notes
4. `tariff_lines.csv` – hs_code, official_description, stat_unit (kg/u/l), valid_from, valid_to, source_name, source_page
5. `catalogue_items.csv` – id (CAT-0000), hs_code, display_name, active
6. `product_aliases.csv` – catalogue_item_id, alias, language (en/bem/sw)
7. `tariff_rules.csv` – hs_code, tax_type, sequence, method, rate, specific_amount, specific_currency, combine,
   base_components (pipe-separated: value components `declared_value`, `freight`, `insurance` or `customs_value`
   (= all three), plus tax types charged earlier, e.g. `declared_value|freight`), origin_country, effective_from, effective_to,
   source_name, source_page
8. `exchange_rates.csv` – currency, rate_to_zmw, effective_from, effective_to, source

Rows refer to sources by exact `name` and to tariff lines by `hs_code` (the line valid on the rule's start date).
Rates are fractions: `0.16` means 16%. End dates are inclusive.
