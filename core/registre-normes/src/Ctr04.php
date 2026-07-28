<?php

declare(strict_types=1);

namespace Gamad\RegistreNormes;

/**
 * Les trois opérations de lecture du contrat CTR-04 (conception adoptée,
 * Titre III ; conception d'implémentation, Titre IV). Lecture et attestation
 * seulement : aucune écriture applicative du corpus (INV-4, Article 68 du
 * registre des capacités).
 */
final class Ctr04
{
    public function __construct(
        private \PDO $pdo,
        private string $corpus,
    ) {
    }

    /**
     * Résout une norme et son statut, éventuellement à une date passée.
     * Sans date : l'état courant. Avec une date : la version et le statut en
     * vigueur à cette date (INV-3, INV-6).
     *
     * @return array<string,mixed>|null
     */
    public function resoudreNorme(string $reference, ?string $version = null, ?string $date = null): ?array
    {
        $sql = 'SELECT id, norme_reference, version, empreinte_git, chemin FROM version_norme WHERE norme_reference = ?';
        $args = [$reference];
        if ($version !== null) {
            $sql .= ' AND version = ?';
            $args[] = $version;
        }
        $sql .= ' ORDER BY version DESC LIMIT 1';
        $st = $this->pdo->prepare($sql);
        $st->execute($args);
        $v = $st->fetch();
        if ($v === false) {
            return null;
        }

        if ($date !== null) {
            $sq = $this->pdo->prepare(
                'SELECT valeur, date_effet, adoption_reference FROM statut
                 WHERE version_norme_id = ? AND date_effet <= ?
                 ORDER BY date_effet DESC, id DESC LIMIT 1'
            );
            $sq->execute([$v['id'], $date]);
        } else {
            $sq = $this->pdo->prepare(
                'SELECT valeur, date_effet, adoption_reference FROM statut
                 WHERE version_norme_id = ?
                 ORDER BY date_effet DESC, id DESC LIMIT 1'
            );
            $sq->execute([$v['id']]);
        }
        $statut = $sq->fetch() ?: null;

        return [
            'reference'          => $v['norme_reference'],
            'version'            => $v['version'],
            'empreinte_git'      => $v['empreinte_git'],
            'chemin'             => $v['chemin'],
            'statut'             => $statut['valeur'] ?? null,
            'date_effet'         => $statut['date_effet'] ?? null,
            'adoption_reference' => $statut['adoption_reference'] ?? null,
            'en_vigueur'         => $statut !== null && !in_array($statut['valeur'], ['REMPLACE', 'ABROGE'], true),
        ];
    }

    /**
     * Recalcule l'empreinte réelle de chaque fichier référencé et la compare à
     * l'empreinte déclarée. L'empreinte réelle n'est jamais recopiée : elle est
     * recalculée (INV-1). Retourne une ligne par version.
     *
     * @return list<array<string,mixed>>
     */
    public function verifierIntegrite(?string $reference = null): array
    {
        $sql = 'SELECT norme_reference, version, empreinte_git, chemin FROM version_norme';
        $args = [];
        if ($reference !== null) {
            $sql .= ' WHERE norme_reference = ?';
            $args[] = $reference;
        }
        $sql .= ' ORDER BY chemin';
        $st = $this->pdo->prepare($sql);
        $st->execute($args);

        $lignes = [];
        foreach ($st->fetchAll() as $v) {
            $fichier = $this->corpus . '/' . $v['chemin'];
            $present = is_file($fichier);
            $reelle  = $present ? GitBlob::hashFile($fichier) : null;
            $lignes[] = [
                'reference'         => $v['norme_reference'],
                'chemin'            => $v['chemin'],
                'empreinte_declaree' => $v['empreinte_git'],
                'empreinte_reelle'  => $reelle,
                'fichier_present'   => $present,
                'concorde'          => $present && $reelle === $v['empreinte_git'],
            ];
        }

        return $lignes;
    }

    /**
     * Reconstruit l'ensemble des actes d'adoption à partir des fichiers
     * primaires (répertoire des actes) et le compare à l'index dérivé (INV-5).
     * Les constats d'exécution compagnons ne sont pas des actes distincts.
     *
     * @return array{actes_primaires:int,index:int,divergences:list<string>}
     */
    public function resoudreIndex(): array
    {
        $primaires = [];
        foreach (glob($this->corpus . '/genesis-ii/registre/ADOPTION-*.md') ?: [] as $f) {
            if (str_contains(basename($f), '-EXECUTION')) {
                continue;
            }
            if (preg_match('/(ADOPTION-\d{4})/', basename($f), $m)) {
                $primaires[$m[1]] = true;
            }
        }

        $index = [];
        foreach ($this->pdo->query('SELECT reference FROM adoption')->fetchAll() as $r) {
            $index[$r['reference']] = true;
        }

        $divergences = array_merge(
            array_map(fn ($r) => "acte présent, absent de l'index : {$r}", array_keys(array_diff_key($primaires, $index))),
            array_map(fn ($r) => "index cite un acte absent du dépôt : {$r}", array_keys(array_diff_key($index, $primaires))),
        );

        return [
            'actes_primaires' => count($primaires),
            'index'           => count($index),
            'divergences'     => array_values($divergences),
        ];
    }
}
