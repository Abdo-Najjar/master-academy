<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Campaign send throttle
    |--------------------------------------------------------------------------
    |
    | Minimum/maximum seconds to sleep between two campaign messages so the
    | linked WhatsApp number isn't flagged for bulk sending. Overridden to a
    | near-zero range in the testing environment so the suite stays fast.
    |
    */

    'campaign_throttle_min_seconds' => (int) env('WHATSAPP_CAMPAIGN_THROTTLE_MIN', 40),

    'campaign_throttle_max_seconds' => (int) env('WHATSAPP_CAMPAIGN_THROTTLE_MAX', 60),

];
