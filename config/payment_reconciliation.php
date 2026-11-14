<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Fixed reconcile attempt ages (minutes since order creation)
    |--------------------------------------------------------------------------
    |
    | Each pending order is checked at most once per listed age window
    | (Getfy IMPA: from 5 minutes up to 24 hours, for installs without gateway webhooks). After the last attempt the order is
    | marked exhausted and never polled again by payments:reconcile-pending.
    | Webhooks and checkout status poll remain the primary confirmation paths.
    |
    */
    'attempt_ages_minutes' => [5, 10, 15, 30, 60, 120, 240, 480, 720, 1440],

    /*
    | Auto-cancel unpaid PIX after this many minutes (reconcile_pix_expired).
    | 0 = never cancel by age; keep status pending for dashboard/conversion.
    | Gateway webhooks that report cancelled still cancel the order.
    */
    'pix_max_age_minutes' => 0,
];
