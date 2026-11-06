<?php

declare(strict_types=1);

namespace App\Application\Reseau;

use Gamad\RegistreIdentites\Magasin;

/** Référentiels initiaux repris des seeders DomainSeeder et TerritorySeeder du Réseau (D-068, D-069). */
final class ReferentielBesoinsGamad
{
    public const DOMAINES = [
        'sante-et-action-sociale' => 'Santé et Action sociale',
        'religion' => 'Religion',
        'formation-et-education' => 'Formation et Éducation',
        'technologie-et-sciences-naturelles' => 'Technologie et Sciences naturelles',
        'economie-et-finances' => 'Économie et Finances',
        'construction-et-industries' => 'Construction et Industries',
        'promotion-immobiliere' => 'Promotion immobilière',
        'genie-civil-et-btp' => 'Génie civil et BTP',
        'travail-ressources-humaines-et-developpement' => 'Travail, Ressources humaines et Développement',
        'securite' => 'Sécurité',
        'sport-et-culture' => 'Sport et Culture',
        'communication-et-communaute' => 'Communication et Communauté',
        'transport-et-automobile' => 'Transport et Automobile',
        'justice-et-droits-de-lhomme' => 'Justice et Droits de l’homme',
        'affaires-commerciales' => 'Affaires commerciales',
        'agriculture-peche-et-elevage' => 'Agriculture, Pêche et Élevage',
        'artisanat' => 'Artisanat',
    ];

    public const TERRITOIRES = [
        'ci-abidjan' => 'Abidjan',
        'ci-yamoussoukro' => 'Yamoussoukro',
        'ci-abobo' => 'Abobo',
        'ci-adjame' => 'Adjamé',
        'ci-anyama' => 'Anyama',
        'ci-attecoube' => 'Attécoubé',
        'ci-bingerville' => 'Bingerville',
        'ci-cocody' => 'Cocody',
        'ci-koumassi' => 'Koumassi',
        'ci-marcory' => 'Marcory',
        'ci-plateau' => 'Plateau',
        'ci-port-bouet' => 'Port-Bouët',
        'ci-songon' => 'Songon',
        'ci-treichville' => 'Treichville',
        'ci-yopougon' => 'Yopougon',
        'ci-agboville' => 'Agboville',
        'ci-touba' => 'Touba',
        'ci-boundiali' => 'Boundiali',
        'ci-toumodi' => 'Toumodi',
        'ci-mankono' => 'Mankono',
        'ci-bouna' => 'Bouna',
        'ci-guiglo' => 'Guiglo',
        'ci-minignan' => 'Minignan',
        'ci-bouake' => 'Bouaké',
        'ci-sassandra' => 'Sassandra',
        'ci-gagnoa' => 'Gagnoa',
        'ci-bondoukou' => 'Bondoukou',
        'ci-dabou' => 'Dabou',
        'ci-duekoue' => 'Duékoué',
        'ci-katiola' => 'Katiola',
        'ci-daloa' => 'Daloa',
        'ci-daoukro' => 'Daoukro',
        'ci-abengourou' => 'Abengourou',
        'ci-odienne' => 'Odienné',
        'ci-divo' => 'Divo',
        'ci-bouafle' => 'Bouaflé',
        'ci-adzope' => 'Adzopé',
        'ci-bongouanou' => 'Bongouanou',
        'ci-dimbokro' => 'Dimbokro',
        'ci-soubre' => 'Soubré',
        'ci-korhogo' => 'Korhogo',
        'ci-san-pedro' => 'San-Pédro',
        'ci-aboisso' => 'Aboisso',
        'ci-ferkessedougou' => 'Ferkessédougou',
        'ci-man' => 'Man',
        'ci-seguela' => 'Séguéla',
    ];

    public static function libelles(string $liste): array
    {
        $libelles = self::initiaux($liste);
        $requete = Magasin::ouvrir()->prepare(
            'SELECT code, libelle FROM renommage_reference_reseau_gamad
             WHERE liste = ? AND id IN (
                 SELECT MAX(id) FROM renommage_reference_reseau_gamad WHERE liste = ? GROUP BY code
             )'
        );
        $requete->execute([$liste, $liste]);

        return array_replace($libelles, $requete->fetchAll(\PDO::FETCH_KEY_PAIR));
    }

    public static function renommer(string $liste, string $code, string $libelle, string $acteur): void
    {
        if (!isset(self::initiaux($liste)[$code])) {
            throw new \InvalidArgumentException('Référence inconnue.');
        }

        $requete = Magasin::ouvrir()->prepare(
            'INSERT INTO renommage_reference_reseau_gamad (liste, code, libelle, acteur_reference, cree_le)
             VALUES (?, ?, ?, ?, ?)'
        );
        $requete->execute([$liste, $code, $libelle, $acteur, gmdate('c')]);
    }

    private static function initiaux(string $liste): array
    {
        return match ($liste) {
            'domaines' => self::DOMAINES,
            'territoires' => self::TERRITOIRES,
            default => throw new \InvalidArgumentException('Liste inconnue.'),
        };
    }
}
