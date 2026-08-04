<?php

declare(strict_types=1);

namespace Gamad\RegistreSecretsCles;

/**
 * Enveloppe minimale autour d'une valeur secrète en mémoire PHP.
 *
 * PHP ne garantit pas l'effacement immédiat de toutes les copies d'une
 * chaîne — cette classe ne le prétend pas non plus (fiche partie 5 §16).
 * Elle existe uniquement pour éviter qu'un `var_dump`, une sérialisation ou
 * un message d'exception n'exposent la valeur par accident : `__toString()`
 * et `__debugInfo()` sont volontairement absents, et `valeur()` est la seule
 * façon d'accéder au contenu, à l'intérieur d'un callback borné et interne.
 */
final class SensitiveValue
{
    public function __construct(
        #[\SensitiveParameter]
        private readonly string $valeur,
    ) {
    }

    public function valeur(): string
    {
        return $this->valeur;
    }

    public function longueur(): int
    {
        return strlen($this->valeur);
    }
}
