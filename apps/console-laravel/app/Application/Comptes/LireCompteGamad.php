<?php

declare(strict_types=1);

namespace App\Application\Comptes;

use DateTimeImmutable;
use DateTimeZone;
use Gamad\RegistreIdentites\Magasin;
use PDO;

/** Lecture personnelle du compte dont le sujet est établi par le Core. */
final class LireCompteGamad
{
    /** @return array{identite_reference: string, nom: string}|null */
    public function executer(string $identite): ?array
    {
        $registre = Magasin::ouvrir();
        $requete = $registre->prepare(
            "SELECT libelle FROM identite_inscrite
             WHERE reference = ? AND type = 'personne'
             AND regime = 'INSCRIT_AU_REGISTRE' AND provisoire = 0
             AND EXISTS (
                 SELECT 1 FROM identifiant_resolution
                 WHERE identite_reference = identite_inscrite.reference AND etat = 'VERIFIE'
             )"
        );
        $requete->execute([$identite]);
        $inscription = $requete->fetch(PDO::FETCH_ASSOC);

        if ($inscription === false || ! $this->identiteActive($identite, $registre)) {
            return null;
        }

        return ['identite_reference' => $identite, 'nom' => $inscription['libelle']];
    }

    /**
     * Lecture du journal canonique, pas du fait d'inscription immuable.
     * Un événement futur ne peut pas activer ou révoquer prématurément.
     */
    private function identiteActive(string $reference, PDO $registre): bool
    {
        try {
            $requete = $registre->prepare(
                "SELECT id, date_effet, etat_apres FROM evenement_cycle_identite
                 WHERE identite_reference = ?
                 AND evenement_type NOT IN
                 ('RATTACHEMENT_PRODUIT','RETRAIT_PRODUIT',
                  'RATTACHEMENT_ORGANISATION','RETRAIT_ORGANISATION')"
            );
            $requete->execute([$reference]);
            $evenements = $requete->fetchAll(PDO::FETCH_OBJ);

            $maintenant = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $dernierInstant = null;
            $dernierId = -1;
            $dernierEtat = null;

            foreach ($evenements as $evenement) {
                // Une date ambiguë ou corrompue interdit toute résolution.
                $date = (string) $evenement->date_effet;
                if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/D', $date)) {
                    // Le registre CTR-01 enregistre canoniquement des dates civiles.
                    // Leur effet commence à 00:00 UTC le jour indiqué.
                    $instant = DateTimeImmutable::createFromFormat(
                        '!Y-m-d', $date, new DateTimeZone('UTC')
                    );
                    if ($instant === false || $instant->format('Y-m-d') !== $date) {
                        return false;
                    }
                } elseif (preg_match('/(?:Z|[+-][0-9]{2}:[0-9]{2})$/', $date)) {
                    $instant = new DateTimeImmutable($date);
                } else {
                    return false;
                }
                if ($instant > $maintenant) {
                    continue;
                }
                $id = (int) $evenement->id;
                if ($dernierInstant === null || $instant > $dernierInstant
                    || ($instant == $dernierInstant && $id > $dernierId)) {
                    $dernierInstant = $instant;
                    $dernierId = $id;
                    $dernierEtat = $evenement->etat_apres;
                }
            }

            return in_array($dernierEtat, ['ACTIVE', 'VERIFIEE'], true);
        } catch (\DateMalformedStringException) {
            return false;
        }
    }
}
