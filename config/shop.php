<?php

return [

    /*
    | W6: how long stock is held while a customer pays online.
    */
    'hold_minutes' => 15,

    /*
    | W2: below this online balance the shop shows "Only N left".
    */
    'low_stock_threshold' => 100,

    /*
    | Open question Q4: which payment methods are offered at checkout.
    */
    'payment_methods' => array_filter(explode(',', env('SHOP_PAYMENT_METHODS', 'mobile_money,card,pay_on_delivery'))),

    /*
    | Payment providers need an email; customers only give a phone number.
    */
    'customer_email_domain' => env('SHOP_CUSTOMER_EMAIL_DOMAIN', 'customers.bluefoods.com.gh'),

    /*
    | W1/Q5: editable on the Website screen; these are the starting values.
    */
    'content_defaults' => [
        'home_title' => 'Fresh fish and seafood from Blue Foods',
        'home_intro' => 'Smoked, frozen and fresh fish, packed with care and delivered to your door.',
        'home_image' => null,
        'about_title' => 'About Blue Foods',
        'about_body' => "Blue Foods produces quality fish and seafood products in Ghana.\n\nEvery pack is handled from our warehouse to your table.",
        'about_image' => null,
        'contact_phone' => '+233 20 000 0000',
        'contact_email' => 'hello@bluefoods.com.gh',
        'contact_address' => 'Accra, Ghana',
        'contact_hours' => 'Monday to Saturday, 8:00 to 18:00',
        'delivery_fee_pesewas' => '2000',
        'delivery_areas' => 'We deliver within Accra and Tema.',
        'collection_point' => 'Blue Foods warehouse, Accra',
        'seo_description' => 'Buy smoked, frozen and fresh fish online from Blue Foods, Ghana.',
    ],

];
