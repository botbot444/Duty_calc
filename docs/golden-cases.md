# Golden test cases – calculation engine

Worked by hand on 2026-11-19 against the **test fixture rates** in
`tests/Support/TariffFixtures.php`. The fixture rates are SAMPLE values (not from the ZRA tariff book).
These cases check the engine's arithmetic and tax order. They do not check ZRA's real rates.

Each case is a test in `tests/Feature/Estimate/EstimateServiceTest.php` (`goldenCases` data provider, plus the two
Lusaka date tests). The suite fails if the engine's figures move.

## Sign-off

| What is checked | Checked by | Against | Date | Status |
| --- | --- | --- | --- | --- |
| Arithmetic of G1–G9, R1, L1, L2 (each line worked by hand, then matched by the tests) | Claude (AI assistant), for the team | Test fixture rates in `tests/Support/TariffFixtures.php` | 2026-11-19 | Done |
| Method: CIF basis, tax order (duty → excise → VAT), VAT base, rounding 2 dp half up | Clearing agent at Nakonde, or ZRA | ZRA guidance and the Customs Tariff Book | – | **Open** |
| The same cases recomputed on verified rates | Team member + second checker | ZRA Customs Tariff Book (`docs/Customs-Tariff-Book.pdf`), page per row | – | **Open**: needs verified rows (plan, open question 2) |

When verified rows arrive, add a second table here with the real HS codes, rates and page numbers. Have the
clearing agent sign off at least one case per method (ad valorem, specific, compound max, compound sum, excise)
before the demo.

Conventions used by every case:

- Customs value = CIF = declared value + freight + insurance, each converted to ZMW and rounded to 2 dp.
- Each charge is rounded once, to 2 dp, half up. A later charge's base uses the rounded earlier charges.
- Total = sum of the rounded charges.
- VAT base = customs value + customs duty (+ excise where the line has it).
- Exchange rates: USD 1 = ZMW 26.50; TZS 1 = ZMW 0.0105.
- Test date: 2026-11-20, Africa/Lusaka.

| #  | Item (HS)            | Inputs                                                   | Working                                                                                      | Total (ZMW) |
| -- | -------------------- | -------------------------------------------------------- | -------------------------------------------------------------------------------------------- | ----------- |
| G1 | T-shirt (61091000)   | ZMW 10,000.00, freight 500.00, insurance 100.00          | CIF 10,600.00; duty 25% = 2,650.00; VAT 16% x 13,250.00 = 2,120.00                           | 4,770.00    |
| G2 | T-shirt (61091000)   | USD 400, freight USD 50, insurance USD 10                | 10,600.00 + 1,325.00 + 265.00 = CIF 12,190.00; duty 3,047.50; VAT 16% x 15,237.50 = 2,438.00 | 5,485.50    |
| G3 | Rice (10063000)      | ZMW 5,000.00, freight 300.00, insurance 0, 1,000 kg      | CIF 5,300.00; duty K1.50 x 1,000 = 1,500.00; VAT 16% x 6,800.00 = 1,088.00                   | 2,588.00    |
| G4 | Sugar (17019900)     | ZMW 10,000.00, freight 0, insurance 0, 500 kg            | 40% = 4,000.00 vs K5 x 500 = 2,500.00, higher = 4,000.00; VAT 16% x 14,000.00 = 2,240.00     | 6,240.00    |
| G5 | Sugar (17019900)     | ZMW 2,000.00, freight 0, insurance 0, 500 kg             | 40% = 800.00 vs K5 x 500 = 2,500.00, higher = 2,500.00; VAT 16% x 4,500.00 = 720.00          | 3,220.00    |
| G6 | Soft drink (22021000)| ZMW 8,000.00, freight 400.00, insurance 100.00           | CIF 8,500.00; duty 25% = 2,125.00; excise 3% x 10,625.00 = 318.75; VAT 16% x 10,943.75 = 1,751.00 | 4,194.75 |
| G7 | Cooking oil (15079000)| ZMW 6,000.00, freight 200.00, insurance 50.00, 400 kg   | CIF 6,250.00; duty 5% = 312.50 plus K0.50 x 400 = 200.00, sum = 512.50; VAT 16% x 6,762.50 = 1,082.00 | 1,594.50 |
| G8 | Phone charger (85044000)| TZS 1,000,000, freight TZS 50,000, insurance 0        | 10,500.00 + 525.00 + 0.00 = CIF 11,025.00; duty 15% = 1,653.75; VAT 16% x 12,678.75 = 2,028.60 | 3,682.35 |
| G9 | T-shirt (61091000)   | USD 10.01, freight 0, insurance 0                        | 10.01 x 26.50 = 265.265, rounded 265.27; duty 25% = 66.3175, rounded 66.32; VAT 16% x 331.59 = 53.0544, rounded 53.05 | 119.37 |
| R1 | T-shirt (61091000)   | ZMW 100.02, freight 0, insurance 0                       | duty 25% = 25.005, half up 25.01 (half even would give 25.00); VAT 16% x 125.03 = 20.0048, rounded 20.00 | 45.01 |
| L1 | T-shirt, 31 Dec 23:59 Lusaka | as G1                                            | VAT 16% (rule ends 2026-12-31) = 2,120.00                                                   | 4,770.00    |
| L2 | T-shirt, 1 Jan 00:01 Lusaka  | as G1                                            | VAT 16.5% (rule from 2027-01-01) x 13,250.00 = 2,186.25                                     | 4,836.25    |

Since the export pivot (`docs/EXPORT_PLAN.md`), G1–L2 run with the import charges switched on in config
(`TariffFixtures::chargeImportTaxes()`). They stay as tests of the rule mechanics (ad valorem, specific, compound,
charges in a later base, rounding, dates), which export duty uses too.

## Export cases (Zambia → DR Congo via Kasumbalesa)

Worked by hand on 2026-11-20 against the **export test fixture rates** in `tests/Support/TariffFixtures.php`
(SAMPLE values, not ZRA rates). Each case is a test in `tests/Feature/Estimate/ExportEstimateTest.php`.

| What is checked | Checked by | Against | Date | Status |
| --- | --- | --- | --- | --- |
| Arithmetic of E1–E7 | Claude (AI assistant), for the team | Export fixture rates | 2026-11-20 | Done |
| Method: valuation basis (FOB?), what export duty is charged on, rounding | Clearing agent at Kasumbalesa, or ZRA | ZRA export guidance | – | **Open** (plan Q1) |
| The same cases on verified export duty rows | Team member + second checker | ZRA export duty source, page per row | – | **Open** (plan Q3) |

Conventions:

- Only `export_duty` is charged. Each rule's base is data: `declared_value` (FOB) unless the case says otherwise.
- Values entered in USD are converted to ZMW first (USD 1 = ZMW 26.50), rounded to 2 dp.
- Export duty is worked out and rounded in ZMW (2 dp, half up).
- USD display: each ZMW amount ÷ 26.50, rounded once to 2 dp, half up. The USD total is the ZMW total ÷ 26.50,
  rounded once (so with several charges the USD lines may differ from the USD total by a cent).

| #  | Item (HS)                | Inputs                                         | Working (ZMW)                                                       | Duty (ZMW) | USD display |
| -- | ------------------------ | ---------------------------------------------- | ------------------------------------------------------------------- | ---------- | ----------- |
| E1 | T-shirt (61091000)       | ZMW 10,000.00                                  | FOB 10,000.00; export duty 0% = 0.00                                | 0.00       | 0.00        |
| E2 | Sugar (17019900)         | ZMW 10,000.00, 500 kg                          | 5% = 500.00 vs K0.10 x 500 = 50.00, higher = 500.00                 | 500.00     | 500.00 ÷ 26.50 = 18.8679 → 18.87 |
| E3 | Sugar (17019900)         | ZMW 500.00, 1,000 kg                           | 5% = 25.00 vs K0.10 x 1,000 = 100.00, higher = 100.00               | 100.00     | 3.7736 → 3.77 |
| E4 | Rice (10063000)          | USD 400, 2,000 kg                              | FOB 400 x 26.50 = 10,600.00; K0.20 x 2,000 = 400.00                 | 400.00     | 15.0943 → 15.09 |
| E5 | Cooking oil (15079000)   | ZMW 6,000.00, 400 kg                           | 2% = 120.00 plus K0.05 x 400 = 20.00, sum = 140.00                  | 140.00     | 5.2830 → 5.28 |
| E6 | Soft drink (22021000)    | ZMW 8,000.00, freight 400.00, insurance 100.00 | base = declared value + freight = 8,400.00 (insurance not in base); 10% = 840.00 | 840.00 | 31.6981 → 31.70 |
| E7 | Sugar (17019900)         | USD 10.01, 1 kg                                | 10.01 x 26.50 = 265.265 → 265.27; 5% = 13.2635 → 13.26 vs K0.10, higher = 13.26 | 13.26 | 0.5004 → 0.50 |
