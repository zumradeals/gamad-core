<?php

declare(strict_types=1);

namespace Gamad\RegistreIdentites;

/**
 * Preuve de possession d'un identifiant humain (email / téléphone).
 *
 * Le code brut n'est jamais persisté. Il est retourné une seule fois au
 * produit reconnu afin qu'il puisse le remettre au canal de destination.
 */
final class VerificationIdentifiants
{
    public const DUREE_MINUTES = 10;
    public const MAX_TENTATIVES = 5;

    public function __construct(private \PDO $registre)
    {
        SchemaInscription::migrer($registre);
        SchemaVerificationIdentifiants::migrer($registre);
    }

    /** @return array<string,mixed> */
    public function demarrer(
        string $identifiantReference,
        string $source,
        string $preuve,
        string $producteur,
    ): array {
        $st = $this->registre->prepare(
            'SELECT identite_reference,type,etat FROM identifiant_resolution WHERE reference = ? LIMIT 1'
        );
        $st->execute([$identifiantReference]);
        $identifiant = $st->fetch();
        if (!is_array($identifiant)) {
            return ['refus' => 'IDENTIFIANT_INCONNU'];
        }
        if (!in_array((string) $identifiant['type'], ['EMAIL', 'TELEPHONE'], true)) {
            return ['refus' => 'VERIFICATION_NON_REQUISE'];
        }
        if (($identifiant['etat'] ?? null) === 'VERIFIE') {
            return ['refus' => 'IDENTIFIANT_DEJA_VERIFIE'];
        }

        $maintenant = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $expire = $maintenant->modify('+' . self::DUREE_MINUTES . ' minutes');
        $reference = 'VRF-' . strtoupper(bin2hex(random_bytes(8)));
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $empreinte = password_hash($code, PASSWORD_DEFAULT);
        if (!is_string($empreinte)) {
            throw new \RuntimeException('Impossible de protéger le code de vérification.');
        }

        $this->registre->beginTransaction();
        try {
            $this->registre->prepare(
                "UPDATE verification_identifiant
                 SET etat = 'EXPIREE'
                 WHERE identifiant_reference = ? AND etat = 'EN_ATTENTE'"
            )->execute([$identifiantReference]);
            $this->registre->prepare(
                'INSERT INTO verification_identifiant
                 (reference,identifiant_reference,secret_empreinte,etat,tentatives,expire_le,source,preuve_reference,producteur,cree_le)
                 VALUES(?,?,?,\'EN_ATTENTE\',0,?,?,?,?,?)'
            )->execute([
                $reference,
                $identifiantReference,
                $empreinte,
                $expire->format(DATE_ATOM),
                $source,
                $preuve,
                $producteur,
                $maintenant->format(DATE_ATOM),
            ]);
            $this->registre->commit();
        } catch (\Throwable $e) {
            if ($this->registre->inTransaction()) {
                $this->registre->rollBack();
            }
            throw $e;
        }

        return [
            'reference' => $reference,
            'identifiant_reference' => $identifiantReference,
            'type' => (string) $identifiant['type'],
            'code' => $code,
            'expire_le' => $expire->format(DATE_ATOM),
            'tentatives_max' => self::MAX_TENTATIVES,
        ];
    }

    /** @return array<string,mixed> */
    public function verifier(string $reference, string $code): array
    {
        $st = $this->registre->prepare(
            'SELECT * FROM verification_identifiant WHERE reference = ? LIMIT 1'
        );
        $st->execute([$reference]);
        $defi = $st->fetch();
        if (!is_array($defi)) {
            return ['refus' => 'VERIFICATION_INCONNUE'];
        }
        if (($defi['etat'] ?? null) !== 'EN_ATTENTE') {
            return ['refus' => 'VERIFICATION_NON_ACTIVE'];
        }

        $maintenant = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $expire = new \DateTimeImmutable((string) $defi['expire_le']);
        if ($maintenant > $expire) {
            $this->registre->prepare(
                "UPDATE verification_identifiant SET etat = 'EXPIREE' WHERE reference = ?"
            )->execute([$reference]);
            return ['refus' => 'VERIFICATION_EXPIREE'];
        }

        $tentatives = (int) $defi['tentatives'];
        if ($tentatives >= self::MAX_TENTATIVES) {
            $this->registre->prepare(
                "UPDATE verification_identifiant SET etat = 'EXPIREE' WHERE reference = ?"
            )->execute([$reference]);
            return ['refus' => 'TROP_DE_TENTATIVES'];
        }

        if (!password_verify($code, (string) $defi['secret_empreinte'])) {
            $tentatives++;
            $nouvelEtat = $tentatives >= self::MAX_TENTATIVES ? 'EXPIREE' : 'EN_ATTENTE';
            $this->registre->prepare(
                'UPDATE verification_identifiant SET tentatives = ?, etat = ? WHERE reference = ?'
            )->execute([$tentatives, $nouvelEtat, $reference]);
            return [
                'refus' => $nouvelEtat === 'EXPIREE' ? 'TROP_DE_TENTATIVES' : 'CODE_INVALIDE',
                'tentatives_restantes' => max(0, self::MAX_TENTATIVES - $tentatives),
            ];
        }

        $this->registre->beginTransaction();
        try {
            $this->registre->prepare(
                "UPDATE verification_identifiant SET etat = 'CONSOMMEE' WHERE reference = ? AND etat = 'EN_ATTENTE'"
            )->execute([$reference]);
            $this->registre->prepare(
                "UPDATE identifiant_resolution SET etat = 'VERIFIE'
                 WHERE reference = ? AND etat = 'NON_VERIFIE'"
            )->execute([(string) $defi['identifiant_reference']]);
            $this->registre->commit();
        } catch (\Throwable $e) {
            if ($this->registre->inTransaction()) {
                $this->registre->rollBack();
            }
            throw $e;
        }

        $identifiant = $this->registre->prepare(
            'SELECT identite_reference,type,etat FROM identifiant_resolution WHERE reference = ? LIMIT 1'
        );
        $identifiant->execute([(string) $defi['identifiant_reference']]);
        $r = $identifiant->fetch();

        return [
            'verification' => $reference,
            'identifiant_reference' => (string) $defi['identifiant_reference'],
            'identite' => is_array($r) ? (string) $r['identite_reference'] : null,
            'type' => is_array($r) ? (string) $r['type'] : null,
            'etat' => is_array($r) ? (string) $r['etat'] : 'VERIFIE',
        ];
    }
}
