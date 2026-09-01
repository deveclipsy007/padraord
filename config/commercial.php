<?php

return [
    // Activation requires an authorized rule sheet and a recorded decision, not a default rate.
    'rules_approved' => (bool) env('COMMERCIAL_RULES_APPROVED', false),
    'rules_evidence' => env('COMMERCIAL_RULES_EVIDENCE'),
    'contract_template_approved' => (bool) env('CONTRACT_TEMPLATE_APPROVED', false),
];
