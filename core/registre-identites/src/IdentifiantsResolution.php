<?php

declare(strict_types=1);

namespace Gamad\RegistreIdentites;

/**
 * Identifiants humains permettant de retrouver une identité canonique.
 *
 * Un identifiant de résolution n'est ni l'identité, ni un authentificateur.
 * Le registre conserve uniquement une empreinte déterministe de la valeur
 * normalisée : le courriel ou le numéro brut n'est pas nécessaire pour
 * retrouver IDN-... lors d'une connexion.
 *
 * EMAIL et TELEPHONE doivent être VERIFIE avant de pouvoir servir à une
 * authentification normale. USERNAME peut être déclaré vérifié au moment de sa
 * création puisqu'il ne prétend pas à la possession d'un canal externe.
 */
final class IdentifiantsResolution
{
    public const TYPES = ['EMAIL', 'TELEPHONE', 'USERNAME', 'EXTERNE'];
    public const ETATS = ['NON_VERIFIE', 'VERIFIE', 'RETIRE'];
    public const DELAI_RENVOI_SECONDES = 60;
    public const MAX_VERIFICATIONS_PAR_HEURE = 5;

    public function __construct(private \PDO $registre)
    {
        SchemaInscription::migrer($registre);
        SchemaVerificationIdentifiants::migrer($registre);
    }

    /** @return array<string,mixed> */
    public function attacher(
        string $identite,
        string $type,
        string $valeur,
        array $dossier = [],
    ): array {
        $type = strtoupper(trim($type));
        if (!in_array($type, self::TYPES, true)) {
            return ['refus' => 'TYPE_IDENTIFIANT_INCONNU'];
        }

        $normalisee = self::normaliser($type, $valeur);
        if ($normalisee === null) {
            return ['refus' => 'IDENTIFIANT_INVALIDE'];
        }

        $existe = $this->registre->prepare(
            "SELECT identite_reference FROM identifiant_resolution
             WHERE type = ? AND empreinte = ? AND etat <> 'RETIRE' LIMIT 1"
        );
        $empreinte = self::empreinte($type, $normalisee);
        $existe->execute([$type, $empreinte]);
        $porteur = $existe->fetchColumn();
        if (is_string($porteur) && $porteur !== '') {
            return $porteur === $identite
                ? ['refus' => 'IDENTIFIANT_DEJA_ATTACHE', 'identite' => $porteur]
                : ['refus' => 'IDENTIFIANT_DEJA_UTILISE'];
        }

        $reference = 'RID-' . strtoupper(bin2hex(random_bytes(8)));
        $etat = (($dossier['verifie'] ?? false) === true) ? 'VERIFIE' : 'NON_VERIFIE';
        $this->registre->prepare(
            'INSERT INTO identifiant_resolution
             (reference,identite_reference,type,empreinte,etat,source,preuve_reference,producteur,date_debut,classification)
             VALUES(?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $reference,
            $identite,
            $type,
            $empreinte,
            $etat,
            (string) ($dossier['source'] ?? 'SOURCE-NON-PRECISEE'),
            (string) ($dossier['preuve'] ?? 'PREUVE-NON-PRECISEE'),
            (string) ($dossier['producteur'] ?? 'PRODUCTEUR-NON-PRECISE'),
            (string) ($dossier['date'] ?? gmdate('Y-m-d')),
            (string) ($dossier['classification'] ?? 'CONFIDENTIEL'),
        ]);

        return [
            'reference' => $reference,
            'identite' => $identite,
            'type' => $type,
            'etat' => $etat,
        ];
    }

    /** @return array{identite:string,type:string,etat:string,reference:string}|null */
    public function resoudre(string $valeur, ?string $type = null): ?array
    {
        $types = $type !== null ? [strtoupper(trim($type))] : ['EMAIL', 'TELEPHONE', 'USERNAME', 'EXTERNE'];
        foreach ($types as $candidat) {
            if (!in_array($candidat, self::TYPES, true)) {
                continue;
            }
            $normalisee = self::normaliser($candidat, $valeur);
            if ($normalisee === null) {
                continue;
            }
            $st = $this->registre->prepare(
                "SELECT reference,identite_reference,type,etat FROM identifiant_resolution
                 WHERE type = ? AND empreinte = ? AND etat <> 'RETIRE'
                 ORDER BY CASE etat WHEN 'VERIFIE' THEN 0 ELSE 1 END, date_debut DESC LIMIT 1"
            );
            $st->execute([$candidat, self::empreinte($candidat, $normalisee)]);
            $r = $st->fetch();
            if (is_array($r)) {
                return [
                    'reference' => (string) $r['reference'],
                    'identite' => (string) $r['identite_reference'],
                    'type' => (string) $r['type'],
                    'etat' => (string) $r['etat'],
                ];
            }
        }

        return null;
    }

    /** @return array{identite:string,type:string,etat:string,reference:string}|null */
    public function resoudrePourAuthentification(string $valeur, ?string $type = null): ?array
    {
        $resolution = $this->resoudre($valeur, $type);
        if ($resolution === null || $resolution['etat'] !== 'VERIFIE') {
            return null;
        }

        return $resolution;
    }

    /**
     * Vérifie qu'une destination fournie à nouveau correspond bien au RID sans
     * nécessiter de conserver sa valeur brute dans le registre.
     */
    public function destinationCorrespond(string $identifiantReference, string $valeur): bool
    {
        $st = $this->registre->prepare(
            "SELECT type,empreinte FROM identifiant_resolution
             WHERE reference = ? AND etat <> 'RETIRE' LIMIT 1"
        );
        $st->execute([$identifiantReference]);
        $identifiant = $st->fetch();
        if (!is_array($identifiant)) {
            return false;
        }

        $type = (string) $identifiant['type'];
        $normalisee = self::normaliser($type, $valeur);
        if ($normalisee === null) {
            return false;
        }

        return hash_equals((string) $identifiant['empreinte'], self::empreinte($type, $normalisee));
    }

    /** @return array<string,mixed> */
    public function demarrerVerification(string $identifiantReference, array $dossier = []): array
    {
        $st = $this->registre->prepare(
            "SELECT reference,identite_reference,type,etat FROM identifiant_resolution
             WHERE reference = ? AND etat <> 'RETIRE' LIMIT 1"
        );
        $st->execute([$identifiantReference]);
        $identifiant = $st->fetch();
        if (!is_array($identifiant)) {
            return ['refus' => 'IDENTIFIANT_INCONNU'];
        }
        if ($identifiant['etat'] === 'VERIFIE') {
            return ['refus' => 'IDENTIFIANT_DEJA_VERIFIE'];
        }
        if (!in_array((string) $identifiant['type'], ['EMAIL', 'TELEPHONE'], true)) {
            return ['refus' => 'VERIFICATION_EXTERNE_NON_REQUISE'];
        }

        $this->registre->prepare(
            "UPDATE verification_identifiant SET etat = 'EXPIREE'
             WHERE identifiant_reference = ? AND etat = 'EN_ATTENTE'"
        )->execute([$identifiantReference]);

        return $this->creerVerification($identifiant, $identifiantReference, $dossier);
    }

    /**
     * Renvoi gouverné : même destination, même producteur, délai minimal et
     * volume horaire borné. Un nouveau défi invalide automatiquement l'ancien.
     *
     * @return array<string,mixed>
     */
    public function renvoyerVerification(
        string $identifiantReference,
        string $destination,
        string $producteur,
        array $dossier = [],
    ): array {
        $st = $this->registre->prepare(
            "SELECT reference,identite_reference,type,etat FROM identifiant_resolution
             WHERE reference = ? AND etat <> 'RETIRE' LIMIT 1"
        );
        $st->execute([$identifiantReference]);
        $identifiant = $st->fetch();
        if (!is_array($identifiant)) {
            return ['refus' => 'IDENTIFIANT_INCONNU'];
        }
        if ($identifiant['etat'] === 'VERIFIE') {
            return ['refus' => 'IDENTIFIANT_DEJA_VERIFIE'];
        }
        if (!in_array((string) $identifiant['type'], ['EMAIL', 'TELEPHONE'], true)) {
            return ['refus' => 'VERIFICATION_EXTERNE_NON_REQUISE'];
        }
        if (!$this->destinationCorrespond($identifiantReference, $destination)) {
            return ['refus' => 'DESTINATION_INCORRECTE'];
        }

        $dernier = $this->registre->prepare(
            'SELECT producteur,cree_le FROM verification_identifiant
             WHERE identifiant_reference = ? ORDER BY cree_le DESC, id DESC LIMIT 1'
        );
        $dernier->execute([$identifiantReference]);
        $precedent = $dernier->fetch();
        if (!is_array($precedent) || !hash_equals((string) $precedent['producteur'], $producteur)) {
            return ['refus' => 'RENVOI_NON_AUTORISE'];
        }

        $maintenant = time();
        $cree = strtotime((string) $precedent['cree_le']);
        if ($cree !== false && ($maintenant - $cree) < self::DELAI_RENVOI_SECONDES) {
            return [
                'refus' => 'RENVOI_TROP_RAPIDE',
                'reessayer_dans' => self::DELAI_RENVOI_SECONDES - ($maintenant - $cree),
            ];
        }

        $depuis = gmdate('c', $maintenant - 3600);
        $compte = $this->registre->prepare(
            'SELECT count(*) FROM verification_identifiant
             WHERE identifiant_reference = ? AND cree_le >= ?'
        );
        $compte->execute([$identifiantReference, $depuis]);
        if ((int) $compte->fetchColumn() >= self::MAX_VERIFICATIONS_PAR_HEURE) {
            return ['refus' => 'LIMITE_RENVOI_ATTEINTE'];
        }

        $this->registre->prepare(
            "UPDATE verification_identifiant SET etat = 'EXPIREE'
             WHERE identifiant_reference = ? AND etat = 'EN_ATTENTE'"
        )->execute([$identifiantReference]);

        return $this->creerVerification($identifiant, $identifiantReference, [
            ...$dossier,
            'producteur' => $producteur,
        ]);
    }

    public function annulerVerification(string $verificationReference): void
    {
        $this->expirerVerification($verificationReference);
    }

    /** @return array<string,mixed> */
    public function verifierPossession(
        string $identite,
        string $identifiantReference,
        string $verificationReference,
        string $code,
    ): array {
        $st = $this->registre->prepare(
            'SELECT v.*, i.identite_reference, i.etat AS identifiant_etat
             FROM verification_identifiant v
             JOIN identifiant_resolution i ON i.reference = v.identifiant_reference
             WHERE v.reference = ? AND v.identifiant_reference = ? LIMIT 1'
        );
        $st->execute([$verificationReference, $identifiantReference]);
        $verification = $st->fetch();
        if (!is_array($verification)
            || !hash_equals((string) $verification['identite_reference'], $identite)) {
            return ['refus' => 'VERIFICATION_INCONNUE'];
        }
        if ($verification['identifiant_etat'] === 'VERIFIE') {
            return ['identifiant_reference' => $identifiantReference, 'etat' => 'VERIFIE', 'idempotent' => true];
        }
        if ($verification['etat'] !== 'EN_ATTENTE') {
            return ['refus' => 'VERIFICATION_INACTIVE'];
        }
        if ((string) $verification['expire_le'] < gmdate('c')) {
            $this->expirerVerification($verificationReference);
            return ['refus' => 'VERIFICATION_EXPIREE'];
        }
        if ((int) $verification['tentatives'] >= 5) {
            $this->expirerVerification($verificationReference);
            return ['refus' => 'TROP_DE_TENTATIVES'];
        }

        if (!password_verify($code, (string) $verification['secret_empreinte'])) {
            $tentatives = ((int) $verification['tentatives']) + 1;
            $etat = $tentatives >= 5 ? 'EXPIREE' : 'EN_ATTENTE';
            $this->registre->prepare(
                'UPDATE verification_identifiant SET tentatives = ?, etat = ? WHERE reference = ?'
            )->execute([$tentatives, $etat, $verificationReference]);

            return ['refus' => $etat === 'EXPIREE' ? 'TROP_DE_TENTATIVES' : 'CODE_INVALIDE'];
        }

        $this->registre->beginTransaction();
        try {
            $this->registre->prepare(
                "UPDATE verification_identifiant SET etat = 'CONSOMMEE' WHERE reference = ?"
            )->execute([$verificationReference]);
            $this->registre->prepare(
                "UPDATE identifiant_resolution SET etat = 'VERIFIE' WHERE reference = ? AND etat = 'NON_VERIFIE'"
            )->execute([$identifiantReference]);
            $this->registre->commit();
        } catch (\Throwable $e) {
            if ($this->registre->inTransaction()) {
                $this->registre->rollBack();
            }
            throw $e;
        }

        return [
            'identifiant_reference' => $identifiantReference,
            'verification_reference' => $verificationReference,
            'etat' => 'VERIFIE',
            'idempotent' => false,
        ];
    }

    public static function normaliser(string $type, string $valeur): ?string
    {
        $valeur = trim($valeur);
        if ($valeur === '') {
            return null;
        }

        return match (strtoupper($type)) {
            'EMAIL' => filter_var(mb_strtolower($valeur), FILTER_VALIDATE_EMAIL) !== false
                ? mb_strtolower($valeur)
                : null,
            'TELEPHONE' => self::normaliserTelephone($valeur),
            'USERNAME' => preg_match('/^[a-z0-9][a-z0-9._-]{2,63}$/', mb_strtolower($valeur)) === 1
                ? mb_strtolower($valeur)
                : null,
            'EXTERNE' => mb_strlen($valeur) <= 256 ? $valeur : null,
            default => null,
        };
    }

    private static function normaliserTelephone(string $valeur): ?string
    {
        $compact = preg_replace('/[\s().-]+/', '', $valeur);
        if (!is_string($compact)) {
            return null;
        }
        if (str_starts_with($compact, '00')) {
            $compact = '+' . substr($compact, 2);
        }
        if (preg_match('/^\+[1-9][0-9]{7,14}$/', $compact) !== 1) {
            return null;
        }

        return $compact;
    }

    private static function empreinte(string $type, string $normalisee): string
    {
        return hash('sha256', strtoupper($type) . "\0" . $normalisee);
    }

    /** @param array<string,mixed> $identifiant @return array<string,mixed> */
    private function creerVerification(array $identifiant, string $identifiantReference, array $dossier): array
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $empreinte = password_hash($code, PASSWORD_ARGON2ID);
        if (!is_string($empreinte) || $empreinte === '') {
            throw new \RuntimeException('empreinte de vérification indisponible');
        }

        $reference = 'VRF-' . strtoupper(bin2hex(random_bytes(8)));
        $expireLe = gmdate('c', time() + (int) ($dossier['duree_secondes'] ?? 600));
        $this->registre->prepare(
            'INSERT INTO verification_identifiant
             (reference,identifiant_reference,secret_empreinte,etat,tentatives,expire_le,
              source,preuve_reference,producteur,cree_le)
             VALUES(?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $reference,
            $identifiantReference,
            $empreinte,
            'EN_ATTENTE',
            0,
            $expireLe,
            (string) ($dossier['source'] ?? 'SOURCE-NON-PRECISEE'),
            (string) ($dossier['preuve'] ?? 'PREUVE-NON-PRECISEE'),
            (string) ($dossier['producteur'] ?? 'PRODUCTEUR-NON-PRECISE'),
            gmdate('c'),
        ]);

        return [
            'reference' => $reference,
            'identifiant_reference' => $identifiantReference,
            'identite' => (string) $identifiant['identite_reference'],
            'type' => (string) $identifiant['type'],
            'code' => $code,
            'expire_le' => $expireLe,
        ];
    }

    private function expirerVerification(string $reference): void
    {
        $this->registre->prepare(
            "UPDATE verification_identifiant SET etat = 'EXPIREE' WHERE reference = ? AND etat = 'EN_ATTENTE'"
        )->execute([$reference]);
    }
}
