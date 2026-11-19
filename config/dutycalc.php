<?php

/*
 * DutyCalc engine settings. None of these is a tax rate: rates only ever come from
 * tariff_rules. These settings decide how the engine behaves when inputs are missing.
 */
return [

    // Bump when the calculation logic changes; every saved estimate records it.
    'engine_version' => '1.0.0',

    // Saved estimates are deleted after this many days (estimates:prune, phase B5).
    'estimate_retention_days' => 90,

    /*
     * What to do when the trader leaves freight or insurance blank. Still to be agreed
     * from ZRA guidance (plan, section 6 step 6).
     *   'zero'       – count it as K0.00 and record that in the estimate's assumptions
     *   'incomplete' – refuse to estimate and ask for the value
     */
    'missing_freight_insurance' => env('DUTYCALC_MISSING_FREIGHT_INSURANCE', 'zero'),

    /*
     * Charges every supported tariff line must have in force. If one is missing the
     * result is `unsupported`, so a gap in the data never shows as a lower estimate.
     * A charge that does not apply should be entered as a 0% rule, not left out.
     */
    'required_tax_types' => ['customs_duty', 'vat'],

    'disclaimer' => 'This is an estimate only, not an official ZRA assessment. '
        .'The amount charged at the border may differ. Check with ZRA or a clearing agent before you travel.',
];
