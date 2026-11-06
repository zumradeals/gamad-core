<?php

return [
    'defaults' => ['guard' => 'web'],
    // Laravel transporte la session. Les gardes gamad.session et gamad.api
    // établissent l'identité et les autorisations depuis les registres Core.
    'guards' => [
        'web' => ['driver' => 'gamad-transport'],
    ],
    'providers' => [],
    'passwords' => [],
];
