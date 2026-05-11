<?php

// GATE LABEL
$gate_label = [

    'CUT_TO_SM_SUBCONT' => 'Out Cutting to SM Subcont',
    'SM_SUBCONT_FROM_CUT' => 'In SM Subcont From Cutting',

    'SM_SUBCONT_TO_WH_SUBCONT' => 'Out SM Subcont to WH Subcont',
    'WH_SUBCONT_FROM_SM_SUBCONT' => 'In WH Subcont From SM Subcont',

    'WH_SUBCONT_TO_VENDOR' => 'Out WH Subcont to Vendor',
    'VENDOR_FROM_WH_SUBCONT' => 'In Vendor From WH Subcont',

    'VENDOR_TO_WH_SUBCONT' => 'Out Vendor to WH Subcont',
    'WH_SUBCONT_FROM_VENDOR' => 'In WH Subcont From Vendor',

    'WH_SUBCONT_TO_SM_SUBCONT' => 'Out WH Subcont to SM Subcont',
    'SM_SUBCONT_FROM_WH_SUBCONT' => 'In SM Subcont From WH Subcont',
];


// MAPPING GATE
$next_gate_map = [

    'CUT_TO_SM_SUBCONT' => 'SM_SUBCONT_FROM_CUT',
    'SM_SUBCONT_FROM_CUT' => 'SM_SUBCONT_TO_WH_SUBCONT',

    'SM_SUBCONT_TO_WH_SUBCONT' => 'WH_SUBCONT_FROM_SM_SUBCONT',
    'WH_SUBCONT_FROM_SM_SUBCONT' => 'WH_SUBCONT_TO_VENDOR',

    'WH_SUBCONT_TO_VENDOR' => 'VENDOR_FROM_WH_SUBCONT',
    'VENDOR_FROM_WH_SUBCONT' => 'VENDOR_TO_WH_SUBCONT',

    'VENDOR_TO_WH_SUBCONT' => 'WH_SUBCONT_FROM_VENDOR',
    'WH_SUBCONT_FROM_VENDOR' => 'WH_SUBCONT_TO_SM_SUBCONT',

    'WH_SUBCONT_TO_SM_SUBCONT' => 'SM_SUBCONT_FROM_WH_SUBCONT',
];