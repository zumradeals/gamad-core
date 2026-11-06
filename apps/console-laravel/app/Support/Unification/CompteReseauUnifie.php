<?php

declare(strict_types=1);

namespace App\Support\Unification;

use DomainException;

/**
 * Représentation métier d'un nouveau compte Réseau associé à une identité Core.
 * Le compte authentifiable et l'identité canonique restent deux concepts distincts.
 * Ce contrat ne délivre aucun droit d'accès à la console.
 */
final readonly class CompteReseauUnifie
{
    private function __construct(
        public string $userId,
        public string $identiteReference,
    ) {}

    public static function creer(string $userId, string $identiteReference): self
    {
        if (! preg_match('/^[0-9]+$/D', $userId) || $userId === '0'
            || ! preg_match('/^[1-9][0-9]*$/D', $userId)) {
            throw new DomainException('Identifiant de compte invalide');
        }

        if ($identiteReference === '' || trim($identiteReference) !== $identiteReference
            || strlen($identiteReference) > 255) {
            throw new DomainException('Référence canonique invalide');
        }

        return new self($userId, $identiteReference);
    }

    /**
     * Représentation minimale sans secret ni attribut de profil.
     * @return array{user_id: string, identite_reference: string}
     */
    public function references(): array
    {
        return ['user_id' => $this->userId, 'identite_reference' => $this->identiteReference];
    }
}
