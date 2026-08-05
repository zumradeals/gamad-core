<?php

declare(strict_types=1);

namespace Gamad\RegistrePreuves;

/**
 * Erreur interne du registre des preuves (CAP-CORE-015). Réservée aux
 * impossibilités de programmation ; un refus métier gouverné retourne
 * `['refus' => ..., 'detail' => ...]`, comme les autres registres du Core.
 */
final class ExceptionPreuve extends \RuntimeException
{
}
