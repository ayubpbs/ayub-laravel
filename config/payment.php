<?php

return [
    'bkash' => [
        // Shown to the customer in the bKash window at checkout (Send Money number)
        'number' => env('BKASH_NUMBER', '01711-302759'),

        // QR code image, relative to the /public folder. Put your file here: public/assets/img/bkash-qr.png
        // (if the file is missing, a "QR" placeholder box is shown instead)
        'qr'     => 'assets/img/bkash-qr.png',
    ],
];
