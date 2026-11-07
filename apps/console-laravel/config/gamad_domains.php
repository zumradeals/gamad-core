<?php

declare(strict_types=1);

// Le domaine institutionnel doit être défini par l'exploitant avant activation.
// Aucune liste de domaines applicatifs n'est dupliquée ici : CAP-CORE-011 décide.
return [
    'institutional_host' => strtolower(trim((string) env('GAMAD_INSTITUTIONAL_HOST', ''))),
];
