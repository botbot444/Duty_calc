# DutyCalc – translation request (Bemba and Swahili)

**For:** the language specialist
**Languages:** Bemba (`bem`) and Swahili (`sw`). English (`en`) is the source.
**Date:** 2026-11-20

DutyCalc helps small cross-border traders work out the customs duty, excise and VAT they will pay when they bring goods into Zambia
(first route: Tanzania to Zambia through Nakonde). Traders search for a product in their own words, confirm it, and get an estimate.

We need three kinds of help:

1. **Product names** – the words traders actually say for each product, so search finds them (Part A).
2. **Key terms** – a short glossary, so the same word is used everywhere (Part B). Please do this before Parts C and D.
3. **App text** – the messages shown on results (Part C) and the text on every screen: headings, buttons, form labels and errors (Part D).

**Suggested order:** Part A, then Part B, then Parts C and D.

The screens are not built yet, so a few Part D sentences may change slightly. Any changes will come back to you as a short list
of only the changed lines. The admin pages (used by our team, not by traders) stay in English.

---

## How to send it back

Fill in the tables in this file, or send the same columns as a spreadsheet saved as **CSV, UTF-8**. Either is fine.

- Keep anything that starts with a colon exactly as it is, for example `:name`, `:date`, `:amount`, `:unit`, `:attribute`.
  The app replaces these with real values (a product name, a date, an amount). You may move them within the sentence,
  but do not translate or remove them.
- Short texts in Part D are often **buttons**: keep them short (one to three words if possible).
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

## Part D – screen text (frontend)

The app has five screens. A trader **searches** for a product (by typing or taking a photo), **chooses** it from a list of
suggestions, **confirms** it, enters **trade details**, and sees the **result**.

### D1. Shown on every screen

| #   | English | Bemba | Swahili | Notes |
| --- | ------- | ----- | ------- | ----- |
| D1.1 | DutyCalc | | | App name. Keep as is unless a local name is better |
| D1.2 | Know your border charges before you travel | | | Tagline under the app name |
| D1.3 | Language | | | Label for the language switch |
| D1.4 | English | | | Name of the language, as a trader would recognise it |
| D1.5 | Bemba | | | e.g. "Ichibemba" |
| D1.6 | Swahili | | | e.g. "Kiswahili" |
| D1.7 | Back | | | Button |
| D1.8 | Continue | | | Button |
| D1.9 | Start again | | | Button |
| D1.10 | Close | | | Button |
| D1.11 | Please wait… | | | |
| D1.12 | Try again | | | Button |
| D1.13 | Something went wrong. Please try again. | | | |
| D1.14 | No internet connection. Check your connection and try again. | | | |
| D1.15 | This page has expired. Reload the page and try again. | | | After the page is left open too long |

### D2. Search screen (home)

| #   | English | Bemba | Swahili | Notes |
| --- | ------- | ----- | ------- | ----- |
| D2.1 | What are you bringing into Zambia? | | | Main heading |
| D2.2 | Type the name of the product | | | Label above the search box |
| D2.3 | For example: sugar, rice, phone charger | | | Grey hint inside the empty search box. Use local product names |
| D2.4 | Search | | | Button |
| D2.5 | or | | | Between "Search" and the photo buttons |
| D2.6 | Take a photo | | | Button: opens the camera |
| D2.7 | Choose a photo | | | Button: picks a photo already on the phone |
| D2.8 | Type a word to search. | | | Shown if the trader presses Search with an empty box |
| D2.9 | Searching… | | | |

### D3. Photo

| #   | English | Bemba | Swahili | Notes |
| --- | ------- | ----- | ------- | ----- |
| D3.1 | Take the photo in good light, with the product filling the picture. | | | Tip shown before taking a photo |
| D3.2 | Checking your photo… | | | |
| D3.3 | We couldn't identify this photo. Try typing the product name. | | | |
| D3.4 | This file is not a photo. Use a JPEG, PNG or WebP image. | | | Keep JPEG, PNG, WebP |
| D3.5 | This photo is too large. The limit is :size MB. | | | `:size` becomes 5 |
| D3.6 | Your photo is deleted as soon as it has been checked. | | | Privacy note |

### D4. Suggestions screen

| #   | English | Bemba | Swahili | Notes |
| --- | ------- | ----- | ------- | ----- |
| D4.1 | Is it one of these? | | | Heading |
| D4.2 | Choose the product that matches yours. | | | |
| D4.3 | HS code | | | Label. Keep "HS" |
| D4.4 | Official description | | | Label for the description from the tariff book |
| D4.5 | Choose | | | Button under each suggestion |
| D4.6 | None of these | | | Button |
| D4.7 | We couldn't find a match. Try another word or check the spelling. | | | |
| D4.8 | We couldn't find a reliable match. Try describing the product in a different way. | | | |
| D4.9 | Photo checking is not available right now, so these are search results. | | | |
| D4.10 | We need a little more detail | | | Heading when the app asks the trader a question |
| D4.11 | Answer the question below so we can find the right product. | | | |
| D4.12 | Your answer | | | Label above the answer box |
| D4.13 | Send answer | | | Button |

The questions themselves (for example "Is it knitted or woven?") are written by the AI service in the trader's language,
so they are not in this list.

### D5. Confirm screen

| #   | English | Bemba | Swahili | Notes |
| --- | ------- | ----- | ------- | ----- |
| D5.1 | Confirm your product | | | Heading |
| D5.2 | You chose: | | | Followed by the product name |
| D5.3 | Your answers | | | Heading above the trader's answers to D4.10 questions |
| D5.4 | Yes, this is my product | | | Button |
| D5.5 | No, show the list again | | | Button |
| D5.6 | The estimate depends on choosing the right product. If you are not sure, ask a clearing agent. | | | |

### D6. Trade details screen

| #   | English | Bemba | Swahili | Notes |
| --- | ------- | ----- | ------- | ----- |
| D6.1 | Trade details | | | Heading |
| D6.2 | Where are the goods coming from? | | | Section heading |
| D6.3 | Country the goods come from | | | Label |
| D6.4 | Border post | | | Label |
| D6.5 | Going to: Zambia | | | |
| D6.6 | Value of the goods | | | Label |
| D6.7 | The amount you paid for the goods | | | Hint under D6.6 |
| D6.8 | Currency | | | Label |
| D6.9 | Zambian kwacha | | | Currency name (ZMW) |
| D6.10 | US dollar | | | Currency name (USD) |
| D6.11 | Tanzanian shilling | | | Currency name (TZS) |
| D6.12 | Freight (transport cost) | | | Label |
| D6.13 | Insurance | | | Label |
| D6.14 | Enter 0 if there is none. | | | Hint under freight and insurance |
| D6.15 | Quantity | | | Label |
| D6.16 | Amount in :unit_label | | | Hint, e.g. "Amount in kilograms" |
| D6.17 | Use the same currency for the value, freight and insurance. | | | |
| D6.18 | Get my estimate | | | Main button |
| D6.19 | Working out your charges… | | | |
| D6.20 | Some details need fixing. Check the boxes marked in red. | | | |

### D7. Result screen

| #   | English | Bemba | Swahili | Notes |
| --- | ------- | ----- | ------- | ----- |
| D7.1 | Your estimate | | | Heading |
| D7.2 | Estimated total to pay at the border | | | Above the big total |
| D7.3 | Charges | | | Section heading |
| D7.4 | Charge | | | Table column |
| D7.5 | Rate | | | Table column |
| D7.6 | Charged on | | | Table column: the amount the rate is applied to |
| D7.7 | Amount | | | Table column |
| D7.8 | Total | | | |
| D7.9 | :rate% of :amount | | | e.g. "25% of K10,600.00" |
| D7.10 | :amount per :unit | | | e.g. "K1.50 per kg" |
| D7.11 | :first or :second, whichever is higher | | | e.g. "40% or K5.00 per kg, whichever is higher" |
| D7.12 | :first plus :second | | | e.g. "5% plus K0.50 per kg" |
| D7.13 | How we worked it out | | | Section heading |
| D7.14 | Customs value (CIF) | | | Keep "CIF" |
| D7.15 | Exchange rate used | | | |
| D7.16 | Rate as of :date | | | |
| D7.17 | Tariff source | | | Where the rates come from |
| D7.18 | page :page | | | e.g. "page 412" |
| D7.19 | Tariff as of :date | | | |
| D7.20 | What we assumed | | | Heading above the C5 assumptions |
| D7.21 | Estimate number: :id | | | |
| D7.22 | Keep this link to see this estimate again for :days days. | | | `:days` becomes 90 |
| D7.23 | Copy link | | | Button |
| D7.24 | Link copied | | | |
| D7.25 | Change quantity | | | Button |
| D7.26 | New estimate | | | Button |
| D7.27 | Print | | | Button |
| D7.28 | What you can do | | | Heading when the app cannot estimate (C4) or needs more information (C3) |
| D7.29 | Try another product | | | Button |
| D7.30 | Choose another route | | | Button |
| D7.31 | Add the missing details | | | Button |
| D7.32 | For this product, ask a clearing agent or ZRA. | | | |
| D7.33 | This estimate has expired or does not exist. | | | Estimates are kept for 90 days |

### D8. Form error messages

These appear next to a box the trader filled in wrongly. `:attribute` becomes the name of the box, from the second table.

| #   | English | Bemba | Swahili | Notes |
| --- | ------- | ----- | ------- | ----- |
| D8.1 | Enter the :attribute. | | | Box left empty |
| D8.2 | The :attribute must be a number. | | | |
| D8.3 | The :attribute must be more than :value. | | | e.g. "more than 0" |
| D8.4 | The :attribute must not be more than :max. | | | |
| D8.5 | The :attribute can have at most :count decimal places. | | | `:count` becomes 2 for money |
| D8.6 | Choose a :attribute from the list. | | | For currency, country, border post |
| D8.7 | The :attribute must be a photo (JPEG, PNG or WebP). | | | |
| D8.8 | The :attribute must not be larger than :size MB. | | | |
| D8.9 | Please choose and confirm a product first. | | | |

Box names used in place of `:attribute`. Write them so they fit into the sentences above, for example "Enter the value of the goods."

| #   | English | Bemba | Swahili | Notes |
| --- | ------- | ----- | ------- | ----- |
| D8.10 | product | | | |
| D8.11 | value of the goods | | | |
| D8.12 | currency | | | |
| D8.13 | freight | | | |
| D8.14 | insurance | | | |
| D8.15 | quantity | | | |
| D8.16 | country | | | |
| D8.17 | border post | | | |
| D8.18 | photo | | | |
| D8.19 | search words | | | |

If Bemba or Swahili grammar makes the `:attribute` pattern awkward, say so in Notes. A full sentence can then be written for each box instead.

---

## Questions for the language specialist

1. Which Swahili should we use: Tanzanian, or the Swahili traders at Nakonde actually speak (if it differs)?
2. Bemba is spoken widely in Northern Province. Do traders at Nakonde also use another local language we should support?
3. Should messages address the trader formally or informally?
4. How are amounts usually written: "K10,600.00", "ZMW 10,600.00" or another way? And dates: "20 Nov 2026" or "20/11/2026"?
