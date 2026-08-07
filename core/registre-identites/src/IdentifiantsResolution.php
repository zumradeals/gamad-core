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
 */
final class IdentifiantsResolution
{
    public const TYPES = ['EMAIL', 'TELEPHONE', 'USERNAME', 'EXTERNE'];
    public const ETATS = ['NON_VERIFIE', 'VERIFIE', 'RETIRE'];

    public function __construct(private \PDO $registre)
    {
        SchemaInscription::migrer($registre);
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

    /** @return array{identite:string,type:string,etat:string}|null */
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
                "SELECT identite_reference,type,etat FROM identifiant_resolution
                 WHERE type = ? AND empreinte = ? AND etat <> 'RETIRE'
                 ORDER BY CASE etat WHEN 'VERIFIE' THEN 0 ELSE 1 END, date_debut DESC LIMIT 1"
            );
            $st->execute([$candidat, self::empreinte($candidat, $normalisee)]);
            $r = $st->fetch();
            if (is_array($r)) {
                return [
                    'identite' => (string) $r['identite_reference'],
                    'type' => (string) $r['type'],
                    'etat' => (string) $r['etat'],
                ];
            }
        }

        return null;
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
}
