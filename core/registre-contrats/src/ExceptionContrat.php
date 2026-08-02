<?php

declare(strict_types=1);

namespace Gamad\RegistreContrats;

/**
 * Violation d'un invariant du registre des contrats (état hors liste close,
 * transition de cycle impossible). Jamais utilisée pour un refus gouverné
 * ordinaire — ceux-ci restent des valeurs de retour `{refus, detail}`, comme
 * dans les autres registres persistants du Core.
 */
final class ExceptionContrat extends \RuntimeException
{
}
