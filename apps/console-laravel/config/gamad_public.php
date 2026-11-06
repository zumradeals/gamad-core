<?php

return [
    'statistics' => [
        ['label' => 'Membres', 'value' => null, 'visible' => false],
        ['label' => 'Entreprises', 'value' => null, 'visible' => false],
        ['label' => 'Opportunités', 'value' => null, 'visible' => false],
        ['label' => 'Projets et initiatives', 'value' => null, 'visible' => false],
    ],
    'footer' => [
        'description' => 'Le réseau d’action des personnes, des entreprises et des initiatives qui créent de la valeur en Afrique.',
        'columns' => [
            ['title' => 'Explorer', 'links' => [
                ['label' => 'Accueil', 'route' => 'reseau.accueil'],
                ['label' => 'Personnes', 'route' => 'personnes.index'],
            ]],
            ['title' => 'Ressources', 'links' => [
                ['label' => 'Comment ça marche ?', 'url' => '/#comment'],
                ['label' => 'Aide', 'route' => 'reseau.aide'],
                ['label' => 'Contact', 'route' => 'reseau.contact'],
            ]],
            ['title' => 'Légal', 'links' => []],
        ],
        'social_links' => [],
        'bottom_text' => 'Tous droits réservés.',
    ],
];
