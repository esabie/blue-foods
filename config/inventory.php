<?php

return [

    /*
    | Open question Q1: may warehouse staff assign stock to team members,
    | or only management? Defaults to management only until answered.
    */
    'warehouse_can_assign' => (bool) env('WAREHOUSE_CAN_ASSIGN', false),

    'display_timezone' => 'Africa/Accra',

    /*
    | Team members should hand in the money for a sale within this many days;
    | older unpaid sales are flagged on the management overview.
    */
    'cash_due_days' => (int) env('CASH_DUE_DAYS', 2),

];
