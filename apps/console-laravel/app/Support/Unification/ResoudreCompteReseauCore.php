<?php

declare(strict_types=1);

namespace App\Support\Unification;

use DateTimeImmutable;
use DateTimeZone;
use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin;
use Gamad\RegistreIdentites\Magasin as IdentitesMagasin;
use PDO;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Résolution en lecture seule du compte Réseau depuis la session Core.
 * Ne confère aucun accès à la console institutionnelle.
 */
final class ResoudreCompteReseauCore
{
    public static function resoudre(Request $request): ?CompteReseauUnifie
    {
        try {
            $session = $request->session()->get('gamad_session');
            if (! is_string($session) || $session === '') {
                return null;
            }

            $verdict = (new Ctr16(Magasin::connecter()))->verifierSession($session);
            $identite = $verdict['entite'] ?? null;
            if (($verdict['valide'] ?? false) !== true
                || ! is_string($identite) || $identite === '') {
                return null;
            }

            return self::resoudreLiaisonActive($identite);
        } catch (Throwable) {
            return null;
        }
    }


    /**
     * Seconde moitié du parcours : référence déjà vérifiée par CTR-16.
     * Ne jamais appeler depuis une entrée HTTP sans validation Core.
     */
    private static function resoudreLiaisonActive(string $identite): ?CompteReseauUnifie
    {
        try {
            // Projection locale en lecture seule : aucun besoin de transaction
            // repartie avec le registre souverain du Core.
            $lien = DB::table('reseau_comptes_identites')
                ->join('users', 'users.id', '=', 'reseau_comptes_identites.user_id')
                ->where('reseau_comptes_identites.identite_reference', $identite)
                ->first(['reseau_comptes_identites.user_id', 'reseau_comptes_identites.identite_reference']);

            if ($lien === null) {
                return null;
            }

            // Le registre souverain est lu dans son stockage Core distinct.
            // Aucune copie locale d'identite_inscrite ne fait autorite.
            $registre = IdentitesMagasin::ouvrir();
            $requete = $registre->prepare(
                'SELECT type, regime, provisoire FROM identite_inscrite WHERE reference = ?'
            );
            $requete->execute([$identite]);
            $inscription = $requete->fetch(PDO::FETCH_ASSOC);
            if ($inscription === false
                || $inscription['type'] !== 'personne'
                || $inscription['regime'] !== 'INSCRIT_AU_REGISTRE'
                || (int) $inscription['provisoire'] !== 0
                || ! self::identiteActive($identite, $registre)) {
                return null;
            }

            return CompteReseauUnifie::creer((string) $lien->user_id, $lien->identite_reference);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Lecture du journal canonique, pas du fait d'inscription immuable.
     * Un événement futur ne peut pas activer ou révoquer prématurément.
     */
    public static function identiteActive(string $reference, ?PDO $registre = null): bool
    {
        try {
            $registre ??= IdentitesMagasin::ouvrir();
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
        } catch (Throwable) {
            return false;
        }
    }
}
