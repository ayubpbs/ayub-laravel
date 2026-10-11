<?php

return [
    'name'    => 'Ayub Tech Shop',
    'tagline' => 'Electrical Parts',
    // Add SHOP_PHONE=01XXXXXXXXX to your .env file to show the phone number
    'phone'   => env('SHOP_PHONE', ''),
    // Delivery charge (in Taka). Change it with SHOP_DELIVERY_CHARGE=100 in your .env file
    'delivery_charge' => (int) env('SHOP_DELIVERY_CHARGE', 60),

    // Admin login. Add these two lines to your .env file to enable /admin:
    //   ADMIN_USER=your_username
    //   ADMIN_PASSWORD=a_strong_password
    // If no password is set, the admin panel stays disabled (for safety).
    'admin_user'     => env('ADMIN_USER', 'admin'),
    'admin_password' => env('ADMIN_PASSWORD', ''),
];
