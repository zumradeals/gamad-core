<?php

declare(strict_types=1);

namespace Gamad\RegistreSecretsCles;

/**
 * Erreur interne du registre de gouvernance des secrets et clés (CAP-CORE-016).
 *
 * Réservée aux impossibilités de programmation (état hors liste close,
 * invariant de schéma violé). Un refus métier gouverné n'est jamais une
 * exception : il retourne `['refus' => ..., 'detail' => ...]`, comme dans les
 * autres registres persistants du Core. Cette exception ne doit jamais
 * transporter une valeur secrète — voir `ExceptionSecretSensible` pour la
 * garde qui l'assure côté résolveur.
 */
final class ExceptionSecret extends \RuntimeException
{
}
