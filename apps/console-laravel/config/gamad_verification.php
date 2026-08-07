<?php

return [
    /*
     * Livraison souveraine des codes de preuve de possession.
     *
     * Le code de verification ne doit jamais etre renvoye au satellite.
     * EMAIL s'appuie sur le mailer Laravel existant.
     * TELEPHONE passe par un relais HTTP interne fournisseur-agnostique : le
     * Core ne connait donc ni Orange, ni MTN, ni Twilio, ni un autre opérateur.
     */
    'email' => [
        'enabled' => env('GAMAD_VERIFICATION_EMAIL_ENABLED', false),
        'mailer' => env('GAMAD_VERIFICATION_EMAIL_MAILER', env('MAIL_MAILER', 'log')),
        'subject' => env('GAMAD_VERIFICATION_EMAIL_SUBJECT', 'Votre code de verification GAMAD'),
    ],

    'sms' => [
        'enabled' => env('GAMAD_VERIFICATION_SMS_ENABLED', false),
        'driver' => env('GAMAD_VERIFICATION_SMS_DRIVER'),
        'relay_url' => env('GAMAD_VERIFICATION_SMS_RELAY_URL'),
        'relay_token' => env('GAMAD_VERIFICATION_SMS_RELAY_TOKEN'),
        'timeout_seconds' => (int) env('GAMAD_VERIFICATION_SMS_TIMEOUT', 5),
        'sender' => env('GAMAD_VERIFICATION_SMS_SENDER', 'GAMAD'),
    ],
];
