<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Kitchen Costing Settings
    |--------------------------------------------------------------------------
    |
    | Default rates for calculating labour and electricity costs in recipes.
    | These can be overridden per-recipe if needed.
    |
    */

    // Labour cost per hour (€/hour)
    'labour_rate' => env('KITCHEN_LABOUR_RATE', 15.00),

    // Electricity cost per kWh (€/kWh)
    'electricity_rate' => env('KITCHEN_ELECTRICITY_RATE', 0.25),

    // Average cooking power consumption (kW)
    // Typical: Oven ~2.5kW, Hob ~1.5kW, Average ~2.0kW
    'avg_cooking_power' => env('KITCHEN_AVG_COOKING_POWER', 2.0),

    // Portion of cook time that counts as attended labour (0.10 = 10%)
    // Cooking is largely unattended - the oven runs without a chef standing
    // over it - so only a fraction of cook time is charged as direct labour.
    // The full cook time is still charged as electricity.
    'cook_supervision_factor' => env('KITCHEN_COOK_SUPERVISION_FACTOR', 0.10),
];
