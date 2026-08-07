<?php

return [
    /*
     * Livraison souveraine des codes de preuve de possession.
     *
     * Le code de verification ne doit jamais etre renvoye au satellite.
     * EMAIL s'appuie sur le mailer Laravel existant. TELEPHONE reste ferme
     * tant qu'un transport SMS explicite n'est pas configure.
     */
    'email' => [
        'enabled' => env('GAMAD_VERIFICATION_EMAIL_ENABLED', false),
        'mailer' => env('GAMAD_VERIFICATION_EMAIL_MAILER', env('MAIL_MAILER', 'log')),
        'subject' => env('GAMAD_VERIFICATION_EMAIL_SUBJECT', 'Votre code de verification GAMAD'),
    ],

    'sms' => [
        'enabled' => env('GAMAD_VERIFICATION_SMS_ENABLED', false),
        'driver' => env('GAMAD_VERIFICATION_SMS_DRIVER'),
    ],
];
