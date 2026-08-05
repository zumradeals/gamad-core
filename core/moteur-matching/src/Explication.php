<?php

declare(strict_types=1);

namespace Gamad\MoteurMatching;

/**
 * Projection d'explication destinée au consommateur (doc de chantier 03
 * §11, doc 04 §18). Fonction pure de filtrage : ne lit rien, ne décide
 * d'aucune autorisation — seule `CAP-CORE-004` autorise l'accès au résultat
 * lui-même, en amont de cet appel.
 *
 * Ne révèle jamais : la référence interne d'un critère non public, une
 * donnée personnelle profonde, une source sensible, une règle de détection,
 * l'identité d'un membre de segment, ou un facteur marqué
 * `facteur_public_autorise=false` par le profil compilé.
 */
final class Explication
{
    /**
     * @param array{classe:string,pertinence:?float,confiance:?float,non_decisionnel:bool,expire_le:string,politique_reference:string,politique_version:string,preuve_reference:?string,obligations:list<string>} $resultat
     * @param list<array{critere_reference:string,nature:string,code_explication:?string,public_autorise:bool}> $facteurs
     * @return array{
     *     contexte:mixed, classe:string, pertinence:?float, confiance:?float,
     *     facteurs_favorables:list<string>, facteurs_defavorables:list<string>, facteurs_non_etablis:list<string>,
     *     restrictions:list<string>, obligations:list<string>, expire_le:string,
     *     non_decisionnel:true, politique:array{reference:string,version:string}, preuve:?string,
     * }
     */
    public static function projeter(array $resultat, array $facteurs, ?string $contexteReference = null): array
    {
        if ($resultat['non_decisionnel'] !== true) {
            throw new ExceptionMatching('Un résultat sans non_decisionnel=true ne peut jamais être projeté — invariant violé en amont.');
        }

        $favorables = [];
        $defavorables = [];
        $nonEtablis = [];
        $restrictions = [];

        foreach ($facteurs as $facteur) {
            if (!$facteur['public_autorise']) {
                continue;
            }
            $libelle = $facteur['code_explication'] ?? $facteur['critere_reference'];
            match ($facteur['nature']) {
                'FAVORABLE' => $favorables[] = $libelle,
                'DEFAVORABLE' => $defavorables[] = $libelle,
                'NON_ETABLI' => $nonEtablis[] = $libelle,
                'RESTRICTION' => $restrictions[] = $libelle,
                'CONTRADICTOIRE' => $nonEtablis[] = $libelle,
                default => null,
            };
        }

        return [
            'contexte' => $contexteReference,
            'classe' => $resultat['classe'],
            'pertinence' => $resultat['pertinence'],
            'confiance' => $resultat['confiance'],
            'facteurs_favorables' => $favorables,
            'facteurs_defavorables' => $defavorables,
            'facteurs_non_etablis' => $nonEtablis,
            'restrictions' => $restrictions,
            'obligations' => $resultat['obligations'],
            'expire_le' => $resultat['expire_le'],
            'non_decisionnel' => true,
            'politique' => ['reference' => $resultat['politique_reference'], 'version' => $resultat['politique_version']],
            'preuve' => $resultat['preuve_reference'] ?? null,
        ];
    }

    /**
     * Un résultat expiré ne doit jamais être présenté comme actuel (doc 04
     * §20 item 82) : l'appelant doit vérifier ce drapeau avant tout affichage.
     */
    public static function estExpire(string $expireLe, string $instantReference): bool
    {
        $expire = strtotime($expireLe);
        $instant = strtotime($instantReference);

        return $expire !== false && $instant !== false && $instant > $expire;
    }
}
