<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Service Fee
    |--------------------------------------------------------------------------
    |
    | Percentage of the service price added at checkout as the platform fee.
    |
    */

    'service_fee_percent' => (float) env('BOOKING_SERVICE_FEE_PERCENT', 5),

];
