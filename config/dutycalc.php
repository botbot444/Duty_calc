<?php

/*
 * DutyCalc engine settings. None of these is a tax rate: rates only ever come from
 * tariff_rules. These settings decide which charges are estimated and how the engine
 * behaves when inputs are missing.
 */
return [

    // Bump when the calculation logic changes; every saved estimate records it.
    // 2.0.0: export pivot (valuation from base components, display currency).
    'engine_version' => '2.0.0',

    // Saved estimates are deleted after this many days (estimates:prune, phase B5).
    'estimate_retention_days' => 90,

    // The country whose charges DutyCalc estimates. A route leaving it is an export.
    'home_country' => 'ZM',

    /*
     * The charges this deployment estimates (docs/EXPORT_PLAN.md: Zambian side of exports
     * only). Rules of other tax types on a tariff line are ignored.
     */
    'charged_tax_types' => ['export_duty'],

    /*
     * Charges every supported tariff line must have in force. If one is missing the
     * result is `unsupported`, so a gap in the data never shows as a lower estimate.
     * A charge that does not apply must be entered as a 0% rule with its source, not left out.
     */
    'required_tax_types' => ['export_duty'],

    /*
     * Second currency the result is shown in, converted from the ZMW figures at the rate in
     * force today. Null shows ZMW only. With no rate in force the result is still given in ZMW.
     */
    'display_currency' => env('DUTYCALC_DISPLAY_CURRENCY', 'USD'),

    /*
     * What to do when a rule's base needs freight or insurance and the trader leaves it blank.
     * Still to be agreed from ZRA guidance (EXPORT_PLAN.md, Q1).
     *   'zero'       – count it as K0.00 and record that in the estimate's assumptions
     *   'incomplete' – refuse to estimate and ask for the value
     */
    'missing_freight_insurance' => env('DUTYCALC_MISSING_FREIGHT_INSURANCE', 'zero'),

    'disclaimer' => 'This is an estimate only, not an official ZRA assessment. '
        .'The amount charged at the border may differ. Check with ZRA or a clearing agent before you travel.',
];
