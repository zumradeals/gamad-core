<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Vocabulaire\AccesVocabulaire;
use Gamad\RegistreVocabulaire\PolitiqueVocabulaire;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Registre du vocabulaire canonique sous session (CAP-CORE-010).
 *
 * Le sujet vient exclusivement de la session ; les champs de gouvernance
 * (politique, producteur, source, preuve) ne sont jamais acceptés depuis la
 * requête — ils viennent de la décision CAP-CORE-004 et de la preuve
 * CAP-CORE-013 établies par `AccesVocabulaire`.
 */
final class VocabulaireController
{
    public function index(Request $request, AccesVocabulaire $acces): JsonResponse
    {
        $execution = $acces->lister((string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function show(Request $request, AccesVocabulaire $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudre($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function versions(Request $request, AccesVocabulaire $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudre($reference, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return response()->json($execution['corps'], $execution['statut']);
        }

        return response()->json(['versions' => $execution['corps']['versions']], 200);
    }

    public function version(Request $request, AccesVocabulaire $acces, string $reference, string $version): JsonResponse
    {
        $execution = $acces->resoudreVersion($reference, $version, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function versionActive(Request $request, AccesVocabulaire $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudreVersionActive($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function termes(Request $request, AccesVocabulaire $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudreVersionActive($reference, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return response()->json($execution['corps'], $execution['statut']);
        }

        return response()->json(['termes' => $execution['corps']['version']['termes']], 200);
    }

    public function compatibilite(Request $request, AccesVocabulaire $acces, string $reference): JsonResponse
    {
        $version = (string) $request->query('version', '');
        if ($version === '') {
            return response()->json(['erreur' => 'VERSION_REQUISE', 'message' => 'le paramètre `version` est obligatoire'], 422);
        }
        $execution = $acces->resoudreCompatibilite($reference, $version, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function conformite(Request $request, AccesVocabulaire $acces, string $reference): JsonResponse
    {
        $version = (string) $request->query('version', '');
        if ($version === '') {
            return response()->json(['erreur' => 'VERSION_REQUISE', 'message' => 'le paramètre `version` est obligatoire'], 422);
        }
        $execution = $acces->resoudreConformite($reference, $version, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function termeShow(Request $request, AccesVocabulaire $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudreTerme($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function termeUsages(Request $request, AccesVocabulaire $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudreTerme($reference, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return response()->json($execution['corps'], $execution['statut']);
        }

        return response()->json(['usages' => $execution['corps']['terme']['usages']], 200);
    }

    public function termeMappings(Request $request, AccesVocabulaire $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudreTerme($reference, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return response()->json($execution['corps'], $execution['statut']);
        }

        return response()->json(['mappings' => $execution['corps']['terme']['mappings']], 200);
    }

    public function store(Request $request, AccesVocabulaire $acces): JsonResponse
    {
        $donnees = $request->validate([
            'reference' => ['required', 'string', 'max:64'],
            'namespace' => ['required', 'string', 'max:128'],
            'nom' => ['required', 'string', 'max:255'],
            'domaine' => ['required', 'string', 'max:128'],
            'portee' => ['required', 'string', 'in:' . implode(',', PolitiqueVocabulaire::PORTEES)],
            'proprietaire_reference' => ['required', 'string', 'max:64'],
            'source_reference' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $execution = $acces->inscrire($donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function creerVersion(Request $request, AccesVocabulaire $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'version' => ['required', 'string', 'max:32'],
            'date_effet_prevue' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $execution = $acces->creerVersion($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function ajouterTerme(Request $request, AccesVocabulaire $acces, string $reference, string $version): JsonResponse
    {
        $donnees = $request->validate([
            'reference' => ['required', 'string', 'max:128'],
            'code' => ['required', 'string', 'max:64'],
            'definition' => ['required', 'string', 'max:2000'],
            'type_semantique' => ['required', 'string', 'in:' . implode(',', PolitiqueVocabulaire::TYPES_SEMANTIQUES)],
            'ordre_affichage' => ['nullable', 'integer'],
            'remplace_par_reference' => ['nullable', 'string', 'max:128'],
        ]);
        $execution = $acces->ajouterTerme($reference, $version, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function evoluerTerme(Request $request, AccesVocabulaire $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'reference' => ['required', 'string', 'max:128'],
            'nouvelle_version' => ['required', 'string', 'regex:/^\d+\.\d+\.\d+$/'],
            'code' => ['nullable', 'string', 'max:64'],
            'definition' => ['nullable', 'string', 'max:2000'],
            'type_semantique' => ['nullable', 'string', 'in:' . implode(',', PolitiqueVocabulaire::TYPES_SEMANTIQUES)],
            'ordre_affichage' => ['nullable', 'integer'],
        ]);
        $nouvelleVersion = (string) $donnees['nouvelle_version'];
        unset($donnees['nouvelle_version']);
        $execution = $acces->evoluerTerme($reference, $nouvelleVersion, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function ajouterLibelle(Request $request, AccesVocabulaire $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'locale' => ['required', 'string', 'in:' . implode(',', PolitiqueVocabulaire::LOCALES)],
            'libelle' => ['required', 'string', 'max:255'],
            'description_courte' => ['nullable', 'string', 'max:500'],
            'principal' => ['nullable', 'boolean'],
        ]);
        $execution = $acces->ajouterLibelle($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function ajouterAlias(Request $request, AccesVocabulaire $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'alias' => ['required', 'string', 'max:128'],
            'locale' => ['nullable', 'string', 'max:8'],
            'type_alias' => ['required', 'string', 'in:' . implode(',', PolitiqueVocabulaire::TYPES_ALIAS)],
            'source_reference' => ['required', 'string', 'max:256'],
        ]);
        $execution = $acces->ajouterAlias($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function declarerRelation(Request $request, AccesVocabulaire $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'terme_cible_reference' => ['required', 'string', 'max:128'],
            'type_relation' => ['required', 'string', 'in:' . implode(',', PolitiqueVocabulaire::TYPES_RELATION)],
            'preuve' => ['required', 'string', 'max:256'],
        ]);
        $cible = (string) $donnees['terme_cible_reference'];
        unset($donnees['terme_cible_reference']);
        $execution = $acces->declarerRelation($reference, $cible, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function declarerMappingExterne(Request $request, AccesVocabulaire $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'systeme_reference' => ['required', 'string', 'max:128'],
            'vocabulaire_externe' => ['required', 'string', 'max:128'],
            'code_externe' => ['required', 'string', 'max:128'],
            'sens' => ['required', 'string', 'in:' . implode(',', PolitiqueVocabulaire::SENS_MAPPING)],
            'statut_mapping' => ['required', 'string', 'in:' . implode(',', PolitiqueVocabulaire::STATUTS_MAPPING)],
            'preuve' => ['required', 'string', 'max:256'],
        ]);
        $execution = $acces->declarerMappingExterne($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function declarerUsage(Request $request, AccesVocabulaire $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'capacite_reference' => ['nullable', 'string', 'max:64'],
            'contrat_reference' => ['nullable', 'string', 'max:64'],
            'contrat_version' => ['nullable', 'string', 'max:32'],
            'politique_reference' => ['nullable', 'string', 'max:64'],
            'produit_reference' => ['nullable', 'string', 'max:64'],
            'usage_type' => ['required', 'string', 'in:' . implode(',', PolitiqueVocabulaire::TYPES_USAGE)],
            'obligatoire' => ['nullable', 'boolean'],
        ]);
        $execution = $acces->declarerUsage($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function soumettre(Request $request, AccesVocabulaire $acces, string $reference, string $version): JsonResponse
    {
        $execution = $acces->soumettreVersion($reference, $version, [], (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function analyser(Request $request, AccesVocabulaire $acces, string $reference, string $version): JsonResponse
    {
        $execution = $acces->analyserCompatibilite($reference, $version, [], (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function activer(Request $request, AccesVocabulaire $acces, string $reference, string $version): JsonResponse
    {
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->activerVersion($reference, $version, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function projections(Request $request, AccesVocabulaire $acces, string $reference, string $version): JsonResponse
    {
        $donnees = $request->validate(['type_projection' => ['required', 'string', 'in:' . implode(',', PolitiqueVocabulaire::TYPES_PROJECTION)]]);
        $execution = $acces->genererProjection($reference, $version, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function enregistrerConformite(Request $request, AccesVocabulaire $acces, string $reference, string $version): JsonResponse
    {
        $donnees = $request->validate([
            'resultat' => ['required', 'string', 'in:' . implode(',', PolitiqueVocabulaire::RESULTATS_CONFORMITE)],
            'consommateur_reference' => ['required', 'string', 'max:64'],
            'type_consommateur' => ['required', 'string', 'in:' . implode(',', PolitiqueVocabulaire::TYPES_CONSOMMATEUR)],
            'commit_reference' => ['nullable', 'string', 'max:128'],
            'rapport_resume_json' => ['nullable', 'string'],
        ]);
        $execution = $acces->enregistrerConformite($reference, $version, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function deprecierVersion(Request $request, AccesVocabulaire $acces, string $reference, string $version): JsonResponse
    {
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->deprecierVersion($reference, $version, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function retirerVersion(Request $request, AccesVocabulaire $acces, string $reference, string $version): JsonResponse
    {
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->retirerVersion($reference, $version, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function deprecierTerme(Request $request, AccesVocabulaire $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'date_fin' => ['nullable', 'date_format:Y-m-d'],
            'remplace_par_reference' => ['nullable', 'string', 'max:128'],
        ]);
        $execution = $acces->deprecierTerme($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function retirerTerme(Request $request, AccesVocabulaire $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate(['date_fin' => ['nullable', 'date_format:Y-m-d']]);
        $execution = $acces->retirerTerme($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }
}
