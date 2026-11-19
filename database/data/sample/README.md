# Sample tariff data – NOT VERIFIED

Everything in this folder is made up so the engine and search can be built and tested
before verified rows arrive from the ZRA tariff book. **Do not use these numbers for a real estimate.**

- Source on every row: "SAMPLE – not verified, for testing only". Page number `1` is a placeholder.
- HS codes are real-looking 8-digit codes, but descriptions, rates and specific amounts are invented.
- VAT is seeded at 16% ending 2026-12-31. No 2027 row is included, so from 1 Jan 2027 these lines
  return `unsupported` until a new VAT row is added (see the plan, section 13).
- Bemba and Swahili aliases are placeholders; the language specialist must confirm them.
- Exchange rates (USD, TZS) are invented. ZMW needs no row.

What the lines cover (plan B2):

| HS code  | Item(s)              | Duty                                 | Other            |
| -------- | -------------------- | ------------------------------------ | ---------------- |
| 61091000 | CAT-0001, CAT-0002   | 25% ad valorem                       | VAT              |
| 10063000 | CAT-0003             | K1.50 per kg (specific)              | VAT              |
| 17019900 | CAT-0004             | 40% or K5.00 per kg, higher (compound max) | VAT        |
| 22021000 | CAT-0005             | 25% ad valorem                       | Excise 3%, VAT on CIF + duty + excise |
| 85044000 | CAT-0006             | 15% ad valorem                       | VAT              |
| 85287200 | CAT-0007             | 25% ad valorem (alias "tv" tests short words) | VAT     |
| 15079000 | CAT-0008             | 5% plus K0.50 per kg (compound sum)  | VAT              |

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
   base_components (pipe-separated, e.g. `customs_value|customs_duty`), origin_country, effective_from, effective_to,
   source_name, source_page
8. `exchange_rates.csv` – currency, rate_to_zmw, effective_from, effective_to, source

Rows refer to sources by exact `name` and to tariff lines by `hs_code` (the line valid on the rule's start date).
Rates are fractions: `0.16` means 16%. End dates are inclusive.
