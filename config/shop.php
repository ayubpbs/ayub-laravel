<?php

return [
    'name'    => 'Ayub Tech Shop',
    'tagline' => 'Electrical Parts',
    // Printed on invoices. Add SHOP_ADDRESS=... and SHOP_EMAIL=... to your .env file
    'address' => env('SHOP_ADDRESS', ''),
    'email'   => env('SHOP_EMAIL', ''),
    'invoice_note' => env('INVOICE_NOTE', 'Thank you for shopping with us!'),
    // Add SHOP_PHONE=01XXXXXXXXX to your .env file to show the phone number
    'phone'   => env('SHOP_PHONE', ''),

    // Floating contact buttons on the Home Page. Add these to your .env file:
    //   SHOP_WHATSAPP=8801XXXXXXXXX            (international format, digits only, no + or spaces)
    //   SHOP_MESSENGER_USERNAME=YOUR_PAGE_USERNAME
    // Leave a value empty to hide that button.
    'whatsapp_number'    => env('SHOP_WHATSAPP', '8801XXXXXXXXX'),
    'messenger_username' => env('SHOP_MESSENGER_USERNAME', 'YOUR_PAGE_USERNAME'),
    // Delivery charge (in Taka). Change it with SHOP_DELIVERY_CHARGE=100 in your .env file
    'delivery_charge' => (int) env('SHOP_DELIVERY_CHARGE', 60),

    // A product raises a "Low stock" notification when its stock is this number or fewer.
    // Change it with SHOP_LOW_STOCK=10 in your .env file
    'low_stock_threshold' => (int) env('SHOP_LOW_STOCK', 5),

    // Admin login. Add these two lines to your .env file to enable /admin:
    //   ADMIN_USER=your_username
    //   ADMIN_PASSWORD=a_strong_password
    // If no password is set, the admin panel stays disabled (for safety).
    'admin_user'     => env('ADMIN_USER', 'admin'),
    'admin_password' => env('ADMIN_PASSWORD', ''),

    // Steadfast Courier (Merchant Panel -> Settings -> API). Add these to your .env file:
    //   STEADFAST_API_KEY=...
    //   STEADFAST_SECRET_KEY=...
    'steadfast_key'      => env('STEADFAST_API_KEY', ''),
    'steadfast_secret'   => env('STEADFAST_SECRET_KEY', ''),
    'steadfast_base_url' => env('STEADFAST_BASE_URL', 'https://portal.packzy.com/api/v1'),

    // Shown in the website footer and on the policy pages (Ayub Tech Shop contact details).
    // A value in .env (SHOP_PHONE, SHOP_EMAIL, SHOP_ADDRESS) wins over the default written here.
    'contact' => [
        'phone'    => env('SHOP_PHONE') ?: '09638-604020',
        'email'    => env('SHOP_EMAIL') ?: 'ayub.pbs@gmail.com',
        'address'  => env('SHOP_ADDRESS') ?: 'মাওনা, গাজীপুর',
        'whatsapp' => '8801711302759',   // international format, digits only
        'social'   => [
            'facebook'  => 'https://www.facebook.com/ayubpbs/',
            'youtube'   => 'https://www.youtube.com/@ayubtechshop',
            'tiktok'    => 'https://www.tiktok.com/@ayub365.t',
            'instagram' => 'https://www.instagram.com/ayub.pbs/',
        ],
    ],
];
