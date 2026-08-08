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

    private const MAX_PASSKEYS_PAR_ENTITE = 5;

    /**
     * Code de secours : un moyen d'accès de repli, à usage unique.
     *
     * Il n'est pas un facteur fort et ne prétend pas l'être. Il existe pour une
     * raison précise : tant qu'une entité n'a qu'un seul moyen d'accès, le
     * perdre ferme le Core à tout le monde. Un code de secours est donc un
     * second chemin, tenu ailleurs que le premier.
     *
     * Il se comporte comme un authentificateur ordinaire à l'ouverture de
     * session — même empreinte non réversible, même vérification — mais il est
     * marqué CONSOMMÉ dans la foulée : un code lu une fois ne se rejoue pas.
     *
     * Consommé, et non révoqué : une session ne survit pas à la révocation de
     * son authentificateur (M-21). Révoquer le code fermerait donc la session
     * qu'il vient d'ouvrir, et le code n'aurait servi à rien.
     */
    public const TYPE_CODE_SECOURS = 'code_secours';

    private const MAX_CODES_SECOURS = 8;

    public function __construct(
        private \PDO $magasin,
    ) {
    }

    /**
     * Inscrit un authentificateur pour une entité.
     *
     * Le secret n'est jamais conservé : seule son empreinte l'est. La méthode
     * ne journalise rien et ne retourne rien qui en dérive.
     *
     * Le minimum reste 12 par défaut. Un appelant gouverné peut demander un
     * seuil inférieur, sans jamais descendre sous 6 caractères.
     */
    public function inscrireAuthentificateur(
        string $entite,
        #[\SensitiveParameter] string $secret,
        string $type = 'mot_de_passe',
        string $assurance = 'AS1 — FACTEUR UNIQUE',
        int $longueurMinimale = 12,
    ): string {
        if ($longueurMinimale < 6) {
            throw new \InvalidArgumentException('Longueur minimale de secret invalide.');
        }
        if (strlen($secret) < $longueurMinimale) {
            throw new \InvalidArgumentException("Secret trop court : {$longueurMinimale} caractères au moins.");
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
            "SELECT reference, empreinte, niveau_assurance, type FROM authentificateur
             WHERE entite_reference = ? AND etat = 'ACTIF' AND consomme_le IS NULL"
        );
        $st->execute([$entite]);

        foreach ($st->fetchAll() as $a) {
            if (!password_verify($secret, (string) $a['empreinte'])) {
                continue;
            }

            $session = $this->ouvrirSession(
                $entite,
                (string) $a['reference'],
                (string) $a['niveau_assurance'],
            );

            // Un code de secours ne sert qu'une fois. Il est marqué consommé,
            // non révoqué : révoquer fermerait la session qu'il vient d'ouvrir
            // (M-21), et le code n'aurait alors servi à rien.
            if ($a['type'] === self::TYPE_CODE_SECOURS) {
                $this->marquerConsomme((string) $a['reference']);
                $session['code_secours_consomme'] = true;
            }

            return $session;
        }

        return null;
    }

    /**
     * Engendre un jeu de codes de secours et retire le jeu précédent.
     *
     * Les codes ne sont retournés qu'ici : le magasin n'en conserve que
     * l'empreinte, comme pour tout secret.
     *
     * @return list<string>
     */
    public function engendrerCodesSecours(string $entite, int $nombre = self::MAX_CODES_SECOURS): array
    {
        if ($nombre < 1 || $nombre > self::MAX_CODES_SECOURS) {
            throw new \InvalidArgumentException(
                'Nombre de codes hors bornes (1 à '.self::MAX_CODES_SECOURS.').'
            );
        }

        $codes = [];
        $this->magasin->beginTransaction();
        try {
            // Les anciens codes sont dépensés, non révoqués : une session
            // ouverte avec l'un d'eux survit à l'engendrement du jeu suivant.
            $anciens = $this->magasin->prepare(
                'UPDATE authentificateur SET consomme_le = ?
                 WHERE entite_reference = ? AND type = ? AND consomme_le IS NULL'
            );
            $anciens->execute([$this->maintenant(), $entite, self::TYPE_CODE_SECOURS]);

            for ($i = 0; $i < $nombre; $i++) {
                $code = $this->engendrerCodeSecours();
                $this->inscrireAuthentificateur(
                    $entite,
                    $code,
                    self::TYPE_CODE_SECOURS,
                    'AS1 — FACTEUR UNIQUE',
                );
                $codes[] = $code;
            }
            $this->magasin->commit();
        } catch (\Throwable $e) {
            if ($this->magasin->inTransaction()) {
                $this->magasin->rollBack();
            }
            throw $e;
        }

        return $codes;
    }

    /**
     * Consomme un code de secours sans ouvrir de session.
     *
     * Sert à autoriser une opération sensible — attacher un premier facteur
     * fort — sans faire du seul mot de passe une autorisation suffisante.
     */
    public function consommerCodeSecours(string $entite, #[\SensitiveParameter] string $code): bool
    {
        $st = $this->magasin->prepare(
            "SELECT reference, empreinte FROM authentificateur
             WHERE entite_reference = ? AND type = ? AND etat = 'ACTIF'
               AND consomme_le IS NULL"
        );
        $st->execute([$entite, self::TYPE_CODE_SECOURS]);

        foreach ($st->fetchAll() as $a) {
            if (password_verify($code, (string) $a['empreinte'])) {
                return $this->marquerConsomme((string) $a['reference']);
            }
        }

        return false;
    }

    /** Nombre de codes de secours encore utilisables. */
    public function codesSecoursRestants(string $entite): int
    {
        $st = $this->magasin->prepare(
            "SELECT count(*) FROM authentificateur
             WHERE entite_reference = ? AND type = ? AND etat = 'ACTIF'
               AND consomme_le IS NULL"
        );
        $st->execute([$entite, self::TYPE_CODE_SECOURS]);

        return (int) $st->fetchColumn();
    }

    /**
     * Retire un moyen d'accès, sauf s'il est le dernier.
     *
     * C'est la garde qui empêche de se fermer soi-même la porte. Elle vaut pour
     * l'autorité comme pour quiconque : aucune qualité ne permet de retirer le
     * dernier moyen d'accès d'une entité.
     *
     * @return array<string,mixed>
     */
    public function revoquerMoyenAcces(string $entite, string $reference): array
    {
        $this->magasin->beginTransaction();
        try {
            $st = $this->magasin->prepare(
                "SELECT reference, type FROM authentificateur
                 WHERE reference = ? AND entite_reference = ? AND etat = 'ACTIF'
                   AND consomme_le IS NULL"
            );
            $st->execute([$reference, $entite]);
            if ($st->fetch() === false) {
                $this->magasin->rollBack();

                return ['refus' => 'MOYEN_INTROUVABLE',
                    'detail' => 'aucun moyen d’accès actif de cette entité ne porte cette référence'];
            }

            // Seuls les moyens DURABLES comptent. Un code de secours est à
            // usage unique : laisser quelqu'un retirer son mot de passe parce
            // qu'il lui reste six codes reviendrait à lui laisser six entrées
            // et aucun moyen permanent — un piège, pas une protection.
            $compter = $this->magasin->prepare(
                "SELECT count(*) FROM authentificateur
                 WHERE entite_reference = ? AND etat = 'ACTIF' AND consomme_le IS NULL
                   AND type <> ?"
            );
            $compter->execute([$entite, self::TYPE_CODE_SECOURS]);
            if ((int) $compter->fetchColumn() <= 1) {
                $this->magasin->rollBack();

                return ['refus' => 'DERNIER_MOYEN',
                    'detail' => 'retirer le dernier moyen d’accès fermerait le Core à son porteur'];
            }

            $this->magasin->prepare(
                "UPDATE passkey SET etat = 'RÉVOQUÉ', revoque_le = ?
                 WHERE authentificateur_ref = ? AND etat = 'ACTIF'"
            )->execute([$this->maintenant(), $reference]);
            $this->magasin->prepare(
                "UPDATE authentificateur SET etat = 'RÉVOQUÉ', revoque_le = ?
                 WHERE reference = ? AND etat = 'ACTIF'"
            )->execute([$this->maintenant(), $reference]);
            $this->magasin->commit();

            return ['reference' => $reference, 'etat' => 'RÉVOQUÉ'];
        } catch (\Throwable $e) {
            if ($this->magasin->inTransaction()) {
                $this->magasin->rollBack();
            }
            throw $e;
        }
    }

    private function marquerConsomme(string $reference): bool
    {
        $st = $this->magasin->prepare(
            'UPDATE authentificateur SET consomme_le = ?
             WHERE reference = ? AND consomme_le IS NULL'
        );
        $st->execute([$this->maintenant(), $reference]);

        return $st->rowCount() === 1;
    }

    private function engendrerCodeSecours(): string
    {
        $brut = strtoupper(bin2hex(random_bytes(8)));

        return implode('-', str_split($brut, 4));
    }

    /**
     * Prépare l'autorisation hors HTTP nécessaire à l'enrôlement d'une
     * passkey. Le jeton en clair n'est retourné qu'une fois.
     *
     * @return array{reference:string,jeton:string,entite:string,expire_le:string}
     */
    public function preparerEnrolementPasskey(string $entite, int $duree = 600): array
    {
        if ($duree < 60 || $duree > 1800) {
            throw new \InvalidArgumentException('Durée d’enrôlement hors limites (60 à 1800 secondes).');
        }

        $reference = 'PENR-' . strtoupper(bin2hex(random_bytes(12)));
        $jeton = 'PASSKEY-' . strtoupper(bin2hex(random_bytes(24)));
        $expire = date('c', time() + $duree);

        $this->magasin->prepare(
            'INSERT INTO autorisation_enrolement_passkey
             (reference,entite_reference,jeton_empreinte,expire_le,cree_le)
             VALUES(?,?,?,?,?)'
        )->execute([
            $reference,
            $entite,
            hash('sha256', $jeton),
            $expire,
            $this->maintenant(),
        ]);

        return [
            'reference' => $reference,
            'jeton' => $jeton,
            'entite' => $entite,
            'expire_le' => $expire,
        ];
    }

    /**
     * @return array{reference:string,entite:string,expire_le:string}|null
     */
    public function verifierAutorisationEnrolement(
        string $entite,
        #[\SensitiveParameter] string $jeton,
    ): ?array {
        $st = $this->magasin->prepare(
            'SELECT reference,entite_reference,expire_le,consommee_le
             FROM autorisation_enrolement_passkey
             WHERE jeton_empreinte = ?'
        );
        $st->execute([hash('sha256', $jeton)]);
        $autorisation = $st->fetch();

        if ($autorisation === false
            || !hash_equals((string) $autorisation['entite_reference'], $entite)
            || $autorisation['consommee_le'] !== null
            || $this->maintenant() >= $autorisation['expire_le']) {
            return null;
        }

        return [
            'reference' => (string) $autorisation['reference'],
            'entite' => (string) $autorisation['entite_reference'],
            'expire_le' => (string) $autorisation['expire_le'],
        ];
    }

    public function inscrirePasskey(
        string $entite,
        string $credentialId,
        string $userHandle,
        string $credentialRecord,
        string $libelle,
        string $autorisationEnrolement,
    ): string {
        $libelle = trim($libelle);
        if ($credentialId === '' || $userHandle === '' || $credentialRecord === '') {
            throw new \InvalidArgumentException('Credential WebAuthn incomplet.');
        }
        if ($libelle === '' || mb_strlen($libelle) > 120) {
            throw new \InvalidArgumentException('Libellé de passkey invalide.');
        }

        $authentificateur = 'AUTHN-' . strtoupper(bin2hex(random_bytes(6)));
        $reference = 'PASS-' . strtoupper(bin2hex(random_bytes(12)));
        $maintenant = $this->maintenant();

        $this->magasin->beginTransaction();
        try {
            $compter = $this->magasin->prepare(
                "SELECT count(*) FROM passkey
                 WHERE entite_reference = ? AND etat = 'ACTIF'",
            );
            $compter->execute([$entite]);
            if ((int) $compter->fetchColumn() >= self::MAX_PASSKEYS_PAR_ENTITE) {
                throw new \RuntimeException('Nombre maximal de passkeys actives atteint.');
            }

            $consommer = $this->magasin->prepare(
                'UPDATE autorisation_enrolement_passkey SET consommee_le = ?
                 WHERE reference = ? AND entite_reference = ?
                   AND consommee_le IS NULL AND expire_le > ?'
            );
            $consommer->execute([
                $maintenant,
                $autorisationEnrolement,
                $entite,
                $maintenant,
            ]);
            if ($consommer->rowCount() !== 1) {
                throw new \RuntimeException('Autorisation d’enrôlement absente, expirée ou déjà consommée.');
            }
            $this->magasin->prepare(
                'INSERT INTO authentificateur
                 (reference,entite_reference,type,empreinte,niveau_assurance,etat,cree_le)
                 VALUES(?,?,?,?,?,?,?)'
            )->execute([
                $authentificateur,
                $entite,
                'passkey_webauthn',
                hash('sha256', $credentialId),
                'A2 — FACTEUR FORT',
                'ACTIF',
                $maintenant,
            ]);
            $this->magasin->prepare(
                'INSERT INTO passkey
                 (reference,authentificateur_ref,entite_reference,credential_id,user_handle,
                  credential_record,libelle,etat,cree_le)
                 VALUES(?,?,?,?,?,?,?,?,?)'
            )->execute([
                $reference,
                $authentificateur,
                $entite,
                $credentialId,
                $userHandle,
                $credentialRecord,
                $libelle,
                'ACTIF',
                $maintenant,
            ]);
            $this->magasin->commit();
        } catch (\Throwable $e) {
            if ($this->magasin->inTransaction()) {
                $this->magasin->rollBack();
            }
            throw $e;
        }

        return $reference;
    }

    public function revoquerPasskey(string $reference): bool
    {
        $this->magasin->beginTransaction();
        try {
            $lire = $this->magasin->prepare(
                "SELECT authentificateur_ref FROM passkey
                 WHERE reference = ? AND etat = 'ACTIF'"
            );
            $lire->execute([$reference]);
            $authentificateur = $lire->fetchColumn();
            if (!is_string($authentificateur)) {
                $this->magasin->rollBack();

                return false;
            }

            $maintenant = $this->maintenant();
            $passkey = $this->magasin->prepare(
                "UPDATE passkey SET etat = 'RÉVOQUÉ', revoque_le = ?
                 WHERE reference = ? AND etat = 'ACTIF'"
            );
            $passkey->execute([$maintenant, $reference]);
            $authn = $this->magasin->prepare(
                "UPDATE authentificateur SET etat = 'RÉVOQUÉ', revoque_le = ?
                 WHERE reference = ? AND etat = 'ACTIF'"
            );
            $authn->execute([$maintenant, $authentificateur]);
            $this->magasin->commit();

            return $passkey->rowCount() === 1 && $authn->rowCount() === 1;
        } catch (\Throwable $e) {
            if ($this->magasin->inTransaction()) {
                $this->magasin->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function passkeysActives(string $entite): array
    {
        $st = $this->magasin->prepare(
            "SELECT reference,authentificateur_ref,entite_reference,credential_id,
                    user_handle,credential_record,libelle,cree_le,utilise_le
             FROM passkey
             WHERE entite_reference = ? AND etat = 'ACTIF'
             ORDER BY cree_le"
        );
        $st->execute([$entite]);

        return $st->fetchAll();
    }

    /**
     * @return array<string,mixed>|null
     */
    public function trouverPasskey(string $credentialId): ?array
    {
        $st = $this->magasin->prepare(
            "SELECT reference,authentificateur_ref,entite_reference,credential_id,
                    user_handle,credential_record,libelle,cree_le,utilise_le
             FROM passkey WHERE credential_id = ? AND etat = 'ACTIF'"
        );
        $st->execute([$credentialId]);
        $passkey = $st->fetch();

        return $passkey === false ? null : $passkey;
    }

    public function actualiserPasskey(string $reference, string $credentialRecord): bool
    {
        $st = $this->magasin->prepare(
            "UPDATE passkey SET credential_record = ?, utilise_le = ?
             WHERE reference = ? AND etat = 'ACTIF'"
        );
        $st->execute([$credentialRecord, $this->maintenant(), $reference]);

        return $st->rowCount() === 1;
    }

    /**
     * @return array{reference:string,expire_le:string}
     */
    public function enregistrerCeremoniePasskey(
        string $entite,
        string $usage,
        string $optionsJson,
        int $duree = 300,
    ): array {
        if (!in_array($usage, ['ENROLEMENT', 'AUTHENTIFICATION'], true)) {
            throw new \InvalidArgumentException('Usage de cérémonie WebAuthn invalide.');
        }
        if ($duree < 60 || $duree > 600) {
            throw new \InvalidArgumentException('Durée de cérémonie hors limites (60 à 600 secondes).');
        }

        $reference = 'PCER-' . strtoupper(bin2hex(random_bytes(12)));
        $expire = date('c', time() + $duree);
        $this->magasin->prepare(
            'INSERT INTO ceremonie_passkey
             (reference,entite_reference,usage,options_json,expire_le,cree_le)
             VALUES(?,?,?,?,?,?)'
        )->execute([
            $reference,
            $entite,
            $usage,
            $optionsJson,
            $expire,
            $this->maintenant(),
        ]);

        return ['reference' => $reference, 'expire_le' => $expire];
    }

    /**
     * Brûle le challenge avant validation : une réponse invalide ne rend pas
     * le challenge réutilisable.
     *
     * @return array<string,mixed>|null
     */
    public function consommerCeremoniePasskey(
        string $reference,
        string $usage,
        ?string $entite = null,
    ): ?array {
        $this->magasin->beginTransaction();
        try {
            $st = $this->magasin->prepare(
                'SELECT reference,entite_reference,usage,options_json,expire_le,consommee_le
                 FROM ceremonie_passkey WHERE reference = ?'
            );
            $st->execute([$reference]);
            $ceremonie = $st->fetch();
            $maintenant = $this->maintenant();
            if ($ceremonie === false
                || $ceremonie['usage'] !== $usage
                || ($entite !== null && $ceremonie['entite_reference'] !== $entite)
                || $ceremonie['consommee_le'] !== null
                || $maintenant >= $ceremonie['expire_le']) {
                $this->magasin->rollBack();

                return null;
            }

            $maj = $this->magasin->prepare(
                'UPDATE ceremonie_passkey SET consommee_le = ?
                 WHERE reference = ? AND consommee_le IS NULL'
            );
            $maj->execute([$maintenant, $reference]);
            if ($maj->rowCount() !== 1) {
                $this->magasin->rollBack();

                return null;
            }
            $this->magasin->commit();

            return $ceremonie;
        } catch (\Throwable $e) {
            if ($this->magasin->inTransaction()) {
                $this->magasin->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Ouvre une session A2 après vérification cryptographique de la passkey.
     *
     * @return array<string,mixed>|null
     */
    public function etablirSessionPasskey(string $referencePasskey): ?array
    {
        $st = $this->magasin->prepare(
            "SELECT p.entite_reference,p.authentificateur_ref,a.niveau_assurance
             FROM passkey p
             JOIN authentificateur a ON a.reference = p.authentificateur_ref
             WHERE p.reference = ? AND p.etat = 'ACTIF' AND a.etat = 'ACTIF'"
        );
        $st->execute([$referencePasskey]);
        $passkey = $st->fetch();
        if ($passkey === false) {
            return null;
        }

        return $this->ouvrirSession(
            (string) $passkey['entite_reference'],
            (string) $passkey['authentificateur_ref'],
            (string) $passkey['niveau_assurance'],
        );
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

    /**
     * @return array<string,mixed>
     */
    private function ouvrirSession(
        string $entite,
        string $authentificateur,
        string $assurance,
    ): array {
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
            $authentificateur,
            $entite,
            $assurance,
            $this->maintenant(),
            $expire,
        ]);

        return [
            'session' => $jeton,
            'entite' => $entite,
            'assurance' => $assurance,
            'expire_le' => $expire,
        ];
    }
}
