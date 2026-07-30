<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Security\PasskeyService;
use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class PasskeyController
{
    public function formulaireEnrolement(): View
    {
        return view('acces.enrolement-passkey');
    }

    public function optionsEnrolement(Request $request, PasskeyService $passkeys): JsonResponse
    {
        $donnees = $request->validate([
            'entite' => ['required', 'string', 'max:64'],
            'jeton' => ['required', 'string', 'max:128'],
        ]);

        try {
            $resultat = $passkeys->commencerEnrolement($donnees['entite'], $donnees['jeton']);
            $enrolements = $request->session()->get('gamad_passkey_enrollements', []);
            $enrolements[$resultat['ceremonie']] = [
                'entite' => $donnees['entite'],
                'autorisation' => $resultat['autorisation'],
            ];
            $request->session()->put('gamad_passkey_enrollements', $enrolements);

            return response()->json([
                'options' => $resultat['options'],
                'ceremonie' => $resultat['ceremonie'],
                'expire_le' => $resultat['expire_le'],
            ]);
        } catch (\Throwable) {
            return response()->json([
                'erreur' => 'ENROLEMENT_REFUSE',
                'message' => 'Autorisation d’enrôlement invalide, expirée ou déjà consommée.',
            ], 403);
        }
    }

    public function enroler(Request $request, PasskeyService $passkeys): JsonResponse
    {
        $donnees = $request->validate([
            'ceremonie' => ['required', 'string', 'max:64'],
            'libelle' => ['required', 'string', 'max:120'],
            'credential' => ['required', 'array'],
        ]);
        $enrolements = $request->session()->get('gamad_passkey_enrollements', []);
        $etat = $enrolements[$donnees['ceremonie']] ?? null;
        unset($enrolements[$donnees['ceremonie']]);
        $request->session()->put('gamad_passkey_enrollements', $enrolements);
        if (! is_array($etat)) {
            return response()->json([
                'erreur' => 'CEREMONIE_INVALIDE',
                'message' => 'Cérémonie absente ou déjà utilisée.',
            ], 409);
        }

        try {
            $reference = $passkeys->terminerEnrolement(
                (string) $etat['entite'],
                $donnees['ceremonie'],
                (string) $etat['autorisation'],
                $donnees['libelle'],
                $donnees['credential'],
            );
        } catch (\Throwable) {
            return response()->json([
                'erreur' => 'ENROLEMENT_REFUSE',
                'message' => 'La passkey n’a pas été enregistrée.',
            ], 422);
        }

        try {
            $this->journaliser(
                (string) $etat['entite'],
                'ENROLEMENT_PASSKEY',
                'ACCEPTE',
                null,
                $request,
                ['passkey' => $reference],
            );
        } catch (\Throwable) {
            try {
                (new Ctr16(Magasin::connecter()))->revoquerPasskey($reference);
            } catch (\Throwable) {
                // La readiness révélera l'indisponibilité du registre d'accès.
            }

            return response()->json([
                'erreur' => 'JOURNAL_INDISPONIBLE',
                'message' => 'La passkey a été révoquée faute de preuve opérationnelle.',
            ], 503);
        }

        return response()->json([
            'statut' => 'PASSKEY_ENROLEE',
            'reference' => $reference,
            'redirection' => route('acces.formulaire'),
        ], 201);
    }

    public function optionsAuthentification(Request $request, PasskeyService $passkeys): JsonResponse
    {
        $donnees = $request->validate([
            'entite' => ['required', 'string', 'max:64'],
        ]);

        try {
            $resultat = $passkeys->commencerAuthentification($donnees['entite']);
            $authentifications = $request->session()->get('gamad_passkey_authentifications', []);
            $authentifications[$resultat['ceremonie']] = $donnees['entite'];
            $request->session()->put('gamad_passkey_authentifications', $authentifications);

            return response()->json($resultat);
        } catch (\Throwable) {
            return response()->json([
                'erreur' => 'AUTHENTIFICATION_INDISPONIBLE',
                'message' => 'L’authentification forte est temporairement indisponible.',
            ], 503);
        }
    }

    public function authentifier(Request $request, PasskeyService $passkeys): JsonResponse
    {
        $donnees = $request->validate([
            'ceremonie' => ['required', 'string', 'max:64'],
            'credential' => ['required', 'array'],
        ]);
        $authentifications = $request->session()->get('gamad_passkey_authentifications', []);
        $entite = $authentifications[$donnees['ceremonie']] ?? null;
        unset($authentifications[$donnees['ceremonie']]);
        $request->session()->put('gamad_passkey_authentifications', $authentifications);
        if (! is_string($entite)) {
            return response()->json([
                'erreur' => 'CEREMONIE_INVALIDE',
                'message' => 'Cérémonie absente ou déjà utilisée.',
            ], 409);
        }

        try {
            $session = $passkeys->terminerAuthentification(
                $donnees['ceremonie'],
                $donnees['credential'],
            );
        } catch (\Throwable) {
            try {
                $this->journaliser(
                    null,
                    'AUTHENTIFICATION_PASSKEY',
                    'REFUSEE',
                    'assertion WebAuthn refusée',
                    $request,
                    ['entite_empreinte' => $this->empreinte($entite)],
                );
            } catch (\Throwable) {
                // L'accès reste fermé.
            }

            return response()->json([
                'erreur' => 'AUTHENTIFICATION_REFUSEE',
                'message' => 'Passkey refusée.',
            ], 401);
        }

        try {
            $this->journaliser(
                $session['entite'],
                'AUTHENTIFICATION_PASSKEY',
                'ACCEPTEE',
                null,
                $request,
                ['assurance' => $session['assurance'], 'passkey' => $session['passkey']],
            );
        } catch (\Throwable) {
            try {
                (new Ctr16(Magasin::connecter()))->revoquerSession((string) $session['session']);
            } catch (\Throwable) {
                // La readiness révélera l'indisponibilité du registre d'accès.
            }

            return response()->json([
                'erreur' => 'JOURNAL_INDISPONIBLE',
                'message' => 'Aucune session n’est conservée sans preuve opérationnelle.',
            ], 503);
        }

        $request->session()->regenerate();
        $request->session()->put('gamad_session', $session['session']);

        return response()->json([
            'statut' => 'AUTHENTIFIE',
            'assurance' => $session['assurance'],
            'redirection' => '/',
        ]);
    }

    /**
     * @param  array<string,mixed>  $donnees
     */
    private function journaliser(
        ?string $acteur,
        string $type,
        string $decision,
        ?string $motif,
        Request $request,
        array $donnees = [],
    ): void {
        (new Journal(JournalMagasin::connecter()))->enregistrer([
            'categorie' => 'SECURITE',
            'type' => $type,
            'acteur' => $acteur,
            'action' => $type === 'ENROLEMENT_PASSKEY'
                ? 'enrôler une passkey'
                : 'ouvrir une session forte',
            'decision' => $decision,
            'motif' => $motif,
            'correlation_id' => 'REQ-'.Str::upper((string) Str::uuid()),
            'donnees' => $donnees + [
                'adresse_ip_empreinte' => is_string($request->ip())
                    ? $this->empreinte((string) $request->ip())
                    : null,
            ],
        ]);
    }

    private function empreinte(string $valeur): string
    {
        return hash_hmac('sha256', Str::lower(trim($valeur)), (string) config('app.key'));
    }
}
