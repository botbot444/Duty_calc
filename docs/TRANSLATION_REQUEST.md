# DutyCalc – translation request (Bemba and Swahili)

**For:** the language specialist
**Languages:** Bemba (`bem`) and Swahili (`sw`). English (`en`) is the source.
**Date:** 2026-11-20

DutyCalc helps small cross-border traders work out the customs duty, excise and VAT they will pay when they bring goods into Zambia
(first route: Tanzania to Zambia through Nakonde). Traders search for a product in their own words, confirm it, and get an estimate.

We need two kinds of help:

1. **Product names** – the words traders actually say for each product, so search finds them (Part A).
2. **App text** – a short glossary (Part B) and the messages the app shows on results (Part C).

The screens (buttons, headings and form labels) are not built yet. They will come as a separate, shorter list later.

---

## How to send it back

Fill in the tables in this file, or send the same columns as a spreadsheet saved as **CSV, UTF-8**. Either is fine.

- Keep anything that starts with a colon exactly as it is: `:name`, `:hs`, `:date`, `:tax`, `:unit`, `:currency`, `:rate`, `:source`,
  `:origin`, `:destination`, `:border`, `:id`, `:needed`, `:unit_label`. The app replaces these with real values.
  You may move them within the sentence.
- Keep codes as they are: `ZMW`, `USD`, `TZS`, `HS`, `CIF`, `ZRA`, `COMESA`, `SADC`, and numbers such as `0.00`.
- Use the **Notes** column for anything uncertain, or if a sentence needs a different word order.
- If a word has no good translation, write the term people actually use (even if it is English) and say so in Notes.

---

## Part A – product names for search (most important)

For each product, give **every word or phrase traders actually use**, in Bemba and in Swahili. More is better:

- everyday names, slang and brand-like names people use for the product (for example "Colgate" for toothpaste)
- common misspellings and short forms (for example "tshirt", "t-shrit", "tv")
- singular and plural if both are common
- English or mixed words that traders use in Bemba or Swahili conversation

One name per row. Lower case is fine. Keep each name under 150 characters.

**Format** (this is the import file the app reads, `product_aliases.csv`):

```csv
catalogue_item_id,alias,language
CAT-0001,fulana,sw
CAT-0001,ishati,bem
```

`language` must be `bem`, `sw` or `en`. You can add English names too if you know ones we have missed.

**The final product list (20–30 goods) is still being agreed.** Please start with these eight. We will send the remaining products
in the same format as soon as the list is fixed.

| ID       | Product (English)                | What it is                                           | Names we have so far (please check) |
| -------- | -------------------------------- | ---------------------------------------------------- | ----------------------------------- |
| CAT-0001 | Cotton T-shirt                   | Knitted cotton T-shirt                               | en: t-shirt, tshirt · sw: fulana · bem: ishati |
| CAT-0002 | Cotton vest or singlet           | Sleeveless cotton undershirt                         | en: vest, singlet, undershirt       |
| CAT-0003 | Rice (milled)                    | White or polished rice, not in the husk              | sw: mchele · bem: umupunga          |
| CAT-0004 | Sugar (white)                    | Ordinary cane or beet sugar                          | sw: sukari · bem: shuga             |
| CAT-0005 | Soft drink (fizzy and sweetened) | Fizzy sweet drinks (soda, cola)                      | en: soda, soft drink · sw: soda     |
| CAT-0006 | Phone charger                    | Mains charger for a mobile phone                     | en: charger · sw: chaja             |
| CAT-0007 | Colour television                | TV set                                               | en: tv, television · sw: televisheni, runinga |
| CAT-0008 | Cooking oil (soya)               | Soya cooking oil                                     | sw: mafuta ya kupikia · bem: amafuta ya kuipikila |

The Bemba and Swahili names above are our own placeholders and have not been checked. Please correct any that are wrong.

---

## Part B – glossary (please do this before Part C)

These terms appear again and again. Fixing one translation for each keeps the whole app consistent.

| English term     | Meaning in the app                                                          | Bemba | Swahili | Notes |
| ---------------- | --------------------------------------------------------------------------- | ----- | ------- | ----- |
| estimate         | Our guess of what will be charged; not the official amount                  |       |         |       |
| customs duty     | Tax on goods coming into the country                                        |       |         |       |
| excise duty      | Extra tax on some goods (for example soft drinks)                           |       |         |       |
| VAT              | Value added tax, charged at the border on imports                           |       |         |       |
| charges          | All of the taxes above together                                             |       |         |       |
| customs value    | The value tax is worked out on: price of goods + transport + insurance      |       |         |       |
| declared value   | What the trader paid for the goods                                          |       |         |       |
| freight          | Cost of transporting the goods to the border                                |       |         |       |
| insurance        | Cost of insuring the goods on the way                                       |       |         |       |
| quantity         | How much (in kilograms, litres or pieces)                                   |       |         |       |
| exchange rate    | How many Kwacha one dollar or shilling is worth                             |       |         |       |
| border post      | Border crossing point (for example Nakonde)                                 |       |         |       |
| trade route      | Country the goods come from, plus the border post                           |       |         |       |
| rate             | Percentage or amount charged (for example 25%, or K5 per kg)                |       |         |       |
| standard rate    | The normal rate, with no special trade agreement                            |       |         |       |
| trade preference | A lower rate under a trade agreement (COMESA, SADC)                         |       |         |       |
| product          | The goods being imported                                                     |       |         |       |
| total            | Sum of all charges                                                           |       |         |       |
| not supported    | The app cannot give an estimate for this yet                                 |       |         |       |

---

## Part C – messages shown on results

### C1. Labels

| Key                 | English                    | Bemba | Swahili | Notes |
| ------------------- | -------------------------- | ----- | ------- | ----- |
| tax.customs_duty    | Customs duty               |       |         |       |
| tax.excise          | Excise duty                |       |         |       |
| tax.vat             | VAT                        |       |         |       |
| unit.kg             | kilograms                  |       |         |       |
| unit.u              | units (pieces)             |       |         |       |
| unit.l              | litres                     |       |         |       |
| status.estimated    | Estimate ready             |       |         |       |
| status.unsupported  | We cannot estimate this    |       |         |       |
| status.incomplete   | We need more information   |       |         |       |
| match.high          | Good match                 |       |         | Shown next to a search suggestion |
| match.medium        | Possible match             |       |         |       |
| match.low           | Weak match                 |       |         |       |

### C2. Disclaimer (shown on every result)

| English | Bemba | Swahili | Notes |
| ------- | ----- | ------- | ----- |
| This is an estimate only, not an official ZRA assessment. The amount charged at the border may differ. Check with ZRA or a clearing agent before you travel. | | | |

### C3. When something is missing (trader must add information)

| #  | English | Bemba | Swahili | Notes |
| -- | ------- | ----- | ------- | ----- |
| M1 | Enter the quantity in :unit_label (:unit): the :tax is charged per :unit. | | | e.g. "Enter the quantity in kilograms (kg): the Customs duty is charged per kg." |
| M2 | The quantity in :unit_label (:unit) must be more than 0. | | | |
| M3 | Enter the freight cost (enter 0 if there is none). | | | |
| M4 | Enter the insurance cost (enter 0 if there is none). | | | |

### C4. When the app cannot estimate

| #  | English | Bemba | Swahili | Notes |
| -- | ------- | ----- | ------- | ----- |
| U1 | We do not have the product :id in our catalogue. | | | |
| U2 | :name (:id) is no longer supported. | | | |
| U3 | The tariff line for :name (HS :hs) is not valid on :date. | | | |
| U4 | The selected trade route is not known. | | | |
| U5 | The route :origin to :destination via :border is not supported yet. | | | e.g. "Malawi to Zambia via Mwami" |
| U6 | No tax rates are in force for :name (HS :hs) on :date. | | | |
| U7 | The :tax rate ended on :date and no new rate is in force for HS :hs. | | | |
| U8 | No :tax rule in force for HS :hs on :date. | | | |
| U9 | No exchange rate for :currency is in force on :date. Enter the values in ZMW instead. | | | |
| U10 | The :tax rate for HS :hs is set in :currency; only ZMW amounts are supported. | | | Rare; data problem |
| U11 | The :tax base needs :needed, but no :needed rate is charged before it for HS :hs. | | | Rare; data problem |
| U12 | More than one :tax rate is in force for HS :hs; the tariff data needs fixing. | | | Rare; data problem |

### C5. Assumptions (listed under each estimate)

| #  | English | Bemba | Swahili | Notes |
| -- | ------- | ----- | ------- | ----- |
| A1 | Customs value is CIF: declared value + freight + insurance, converted to ZMW. | | | Keep "CIF" |
| A2 | Charged at the standard rate: no trade preference (for example COMESA or SADC) is claimed. | | | |
| A3 | Each charge is rounded once, to 2 decimals, half up (to be confirmed with ZRA). | | | |
| A4 | Freight not given: counted as ZMW 0.00. | | | |
| A5 | Insurance not given: counted as ZMW 0.00. | | | |
| A6 | Values entered in ZMW: no conversion. | | | |
| A7 | Converted at :currency 1 = ZMW :rate (:source, from :date). | | | e.g. "Converted at USD 1 = ZMW 26.50 (Bank of Zambia, from 2026-11-16)" |

---

## Questions for the language specialist

1. Which Swahili should we use: Tanzanian, or the Swahili traders at Nakonde actually speak (if it differs)?
2. Bemba is spoken widely in Northern Province. Do traders at Nakonde also use another local language we should support?
3. Should messages address the trader formally or informally?
