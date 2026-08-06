<?php

declare(strict_types=1);

namespace Gamad\MoteurMatching;

/**
 * Erreur interne du moteur de Matching (CAP-CORE-021).
 *
 * Réservée aux impossibilités de programmation (état hors liste close,
 * invariant de schéma violé). Un refus métier gouverné n'est jamais une
 * exception : il retourne `['refus' => ..., 'detail' => ...]`, comme dans
 * les autres registres persistants du Core.
 */
final class ExceptionMatching extends \RuntimeException
{
}
