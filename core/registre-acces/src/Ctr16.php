<?php

declare(strict_types=1);

namespace Gamad\RegistreAcces;

/**
 * Contrat CTR-16 — Authentification et assurance communes (CAP-CORE-005),
 * conception adoptée par ADOPTION-0039.
 *
 * `etablirSession` est la PREMIÈRE ÉCRITURE APPLICATIVE du Core. Elle n'écrit
 * que dans le magasin d'exploitation — jamais dans le corpus, jamais dans
 * l'index dérivé (INV-22). INV-4 demeure entier : aucun fait institutionnel
 * n'est produit ici.
 *
 * Un compte n'est pas une identité (INV-23) : révoquer tous les
 * authentificateurs d'une entité ne supprime pas l'entité et n'altère aucun
 * fait du corpus.
 *
 * Aucun secret n'est conservé, seulement une empreinte non réversible (INV-24).
 */
final class Ctr16
{
    /**
     * La capacité souveraine que ce module sert (INV-41).
     *
     * Une famille de contrat peut servir deux capacités — `CTR-10` sert
     * l'audit et l'intégrité. Le numéro de famille ne suffit donc pas à
     * rattacher un module ; le module le déclare lui-même.
     */
    public const CAPACITE = 'CAP-CORE-005';

    /** Durée d'une session, en secondes. Aucune session n'est permanente (INV-25). */
    private const DUREE_SESSION = 3600 * 8;

    public function __construct(
        private \PDO $magasin,
    ) {
    }

    /**
     * Inscrit un authentificateur pour une entité.
     *
     * Le secret n'est jamais conservé : seule son empreinte l'est. La méthode
     * ne journalise rien et ne retourne rien qui en dérive.
     */
    public function inscrireAuthentificateur(
        string $entite,
        #[\SensitiveParameter] string $secret,
        string $type = 'mot_de_passe',
        string $assurance = 'AS1 — FACTEUR UNIQUE',
    ): string {
        if (strlen($secret) < 12) {
            throw new \InvalidArgumentException('Secret trop court : douze caractères au moins.');
        }

        $reference = 'AUTHN-' . strtoupper(bin2hex(random_bytes(6)));
        $this->magasin->prepare(
            'INSERT INTO authentificateur
             (reference,entite_reference,type,empreinte,niveau_assurance,etat,cree_le)
             VALUES(?,?,?,?,?,?,?)'
        )->execute([
            $reference,
            $entite,
            $type,
            password_hash($secret, PASSWORD_DEFAULT),
            $assurance,
            'ACTIF',
            $this->maintenant(),
        ]);

        return $reference;
    }

    /**
     * Établit une session si le secret présenté correspond à un authentificateur
     * actif de l'entité. Retourne null en cas d'échec, sans distinguer l'entité
     * inconnue du secret erroné.
     *
     * @return array<string,mixed>|null
     */
    public function etablirSession(string $entite, #[\SensitiveParameter] string $secret): ?array
    {
        $st = $this->magasin->prepare(
            "SELECT reference, empreinte, niveau_assurance FROM authentificateur
             WHERE entite_reference = ? AND etat = 'ACTIF'"
        );
        $st->execute([$entite]);

        foreach ($st->fetchAll() as $a) {
            if (!password_verify($secret, (string) $a['empreinte'])) {
                continue;
            }

            $jeton = 'SESS-' . strtoupper(bin2hex(random_bytes(24)));
            $reference = 'SINT-' . strtoupper(bin2hex(random_bytes(12)));
            $expire = date('c', time() + self::DUREE_SESSION);

            $this->magasin->prepare(
                'INSERT INTO session_ouverte
                 (reference,jeton_empreinte,authentificateur_ref,entite_reference,
                  niveau_assurance,ouverte_le,expire_le)
                 VALUES(?,?,?,?,?,?,?)'
            )->execute([
                $reference,
                $this->empreinteSession($jeton),
                $a['reference'],
                $entite,
                $a['niveau_assurance'],
                $this->maintenant(),
                $expire,
            ]);

            return [
                'session'   => $jeton,
                'entite'    => $entite,
                'assurance' => $a['niveau_assurance'],
                'expire_le' => $expire,
            ];
        }

        return null;
    }

    /**
     * Une session est valide si elle existe, n'est ni expirée ni révoquée, ET
     * si l'authentificateur qui l'a ouverte est toujours actif.
     *
     * Ce dernier contrôle couvre `M-21` : une session ne survit pas à la
     * révocation de son authentificateur.
     *
     * @return array<string,mixed>
     */
    public function verifierSession(string $session, ?string $aLaDate = null): array
    {
        $instant = $aLaDate ?? $this->maintenant();

        $st = $this->magasin->prepare(
            'SELECT s.entite_reference, s.niveau_assurance, s.expire_le, s.revoquee_le, a.etat
             FROM session_ouverte s
             JOIN authentificateur a ON a.reference = s.authentificateur_ref
             WHERE s.jeton_empreinte = ?'
        );
        $st->execute([$this->empreinteSession($session)]);
        $s = $st->fetch();

        if ($s === false) {
            return ['valide' => false, 'entite' => null, 'assurance' => null, 'motif' => 'session inconnue'];
        }
        if ($s['revoquee_le'] !== null) {
            return ['valide' => false, 'entite' => null, 'assurance' => null, 'motif' => 'session révoquée'];
        }
        if ($s['etat'] !== 'ACTIF') {
            return ['valide' => false, 'entite' => null, 'assurance' => null,
                'motif' => 'authentificateur révoqué ou suspendu'];
        }
        if ($instant >= $s['expire_le']) {
            return ['valide' => false, 'entite' => null, 'assurance' => null, 'motif' => 'session expirée'];
        }

        return [
            'valide'    => true,
            'entite'    => $s['entite_reference'],
            'assurance' => $s['niveau_assurance'],
            'motif'     => null,
        ];
    }

    public function revoquerSession(string $session): bool
    {
        $st = $this->magasin->prepare(
            'UPDATE session_ouverte SET revoquee_le = ?
             WHERE jeton_empreinte = ? AND revoquee_le IS NULL'
        );
        $st->execute([$this->maintenant(), $this->empreinteSession($session)]);

        return $st->rowCount() > 0;
    }

    public function revoquerAuthentificateur(string $reference): bool
    {
        $st = $this->magasin->prepare(
            "UPDATE authentificateur SET etat = 'RÉVOQUÉ', revoque_le = ? WHERE reference = ? AND etat = 'ACTIF'"
        );
        $st->execute([$this->maintenant(), $reference]);

        return $st->rowCount() > 0;
    }

    /**
     * Atteste des moyens d'accès d'une entité, sans jamais rien restituer du
     * secret ni de son empreinte.
     *
     * @return array<string,mixed>
     */
    public function attester(string $entite): array
    {
        $a = $this->magasin->prepare(
            'SELECT reference, type, niveau_assurance, etat, cree_le FROM authentificateur
             WHERE entite_reference = ? ORDER BY cree_le'
        );
        $a->execute([$entite]);

        $s = $this->magasin->prepare(
            'SELECT count(*) FROM session_ouverte
             WHERE entite_reference = ? AND revoquee_le IS NULL AND expire_le > ?'
        );
        $s->execute([$entite, $this->maintenant()]);

        return [
            'entite'            => $entite,
            'authentificateurs' => $a->fetchAll(),
            'sessions_actives'  => (int) $s->fetchColumn(),
        ];
    }

    private function maintenant(): string
    {
        return date('c');
    }

    private function empreinteSession(#[\SensitiveParameter] string $session): string
    {
        return hash('sha256', $session);
    }
}
