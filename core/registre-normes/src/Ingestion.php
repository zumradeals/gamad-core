<?php

declare(strict_types=1);

namespace Gamad\RegistreNormes;

/**
 * Ingestion dérivée (conception d'implémentation, Titre III).
 *
 * Sens unique : des fichiers canoniques versionnés vers l'index. L'ingestion
 * n'écrit dans aucun fichier `.md` (INV-5). Les empreintes sont RECALCULÉES
 * (GitBlob) puis conservées telles que déclarées par l'acte le plus récent qui
 * lie chaque fichier — la vérification de concordance appartient à Ctr04.
 * Idempotente : rejouée, elle reconstruit le même index.
 */
final class Ingestion
{
    public function __construct(
        private \PDO $pdo,
        private string $corpus,
    ) {
    }

    /** @return array{adoptions:int,normes:int,versions:int} */
    public function executer(): array
    {
        Schema::create($this->pdo);

        $adoptions = $this->ingererAdoptions();
        $versions  = $this->ingererNormesDeclarees();
        $this->amorcerFaitsP3();

        return [
            'adoptions' => $adoptions,
            'normes'    => (int) $this->pdo->query('SELECT count(*) FROM norme')->fetchColumn(),
            'versions'  => $versions,
        ];
    }

    /** Lit le tableau de l'Article 4 de l'index et remplit la table `adoption`. */
    private function ingererAdoptions(): int
    {
        $index = $this->corpus . '/genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md';
        $texte = (string) @file_get_contents($index);
        $n = 0;

        foreach (explode("\n", $texte) as $ligne) {
            if (!preg_match('/^\|\s*`ADOPTION-(\d{4})`\s*\|(.*)$/u', trim($ligne), $m)) {
                continue;
            }
            $cellules = array_map('trim', explode('|', $m[2]));
            // cellules : [0]=texte, [1]=autorité, [2]=date, [3]=statut, ...
            $autorite = $this->nettoyer($cellules[1] ?? '');
            $date     = $this->nettoyer($cellules[2] ?? '');
            $statut   = $this->nettoyer($cellules[3] ?? '');
            $signature = str_contains($statut, 'SIGN') ? 1 : 0;

            $this->pdo->prepare(
                'INSERT INTO adoption(reference,autorite,date_adoption,signature_presente) VALUES(?,?,?,?)'
            )->execute(["ADOPTION-{$m[1]}", $autorite, $date, $signature]);
            $n++;
        }

        return $n;
    }

    /**
     * Parcourt les actes d'adoption, relève chaque couple (chemin, empreinte
     * déclarée), retient la déclaration de rang le plus élevé par chemin, puis
     * crée une norme et une version pour chaque fichier réellement présent.
     */
    private function ingererNormesDeclarees(): int
    {
        $repertoire = $this->corpus . '/genesis-ii/registre';
        $declarations = []; // chemin => [rang, empreinte]

        foreach (glob($repertoire . '/ADOPTION-*.md') ?: [] as $fichier) {
            if (!preg_match('/ADOPTION-(\d{4})/', basename($fichier), $mm)) {
                continue;
            }
            $rang = (int) $mm[1];
            foreach (explode("\n", (string) file_get_contents($fichier)) as $ligne) {
                if (preg_match('/^\|\s*`([^`]+?\.(?:md|py|yml))`[^|]*\|(?:[^|]*\|)*?\s*`([0-9a-f]{40})`\s*\|\s*$/u', trim($ligne), $m)) {
                    $chemin = $m[1];
                    if (!isset($declarations[$chemin]) || $rang >= $declarations[$chemin][0]) {
                        $declarations[$chemin] = [$rang, $m[2]];
                    }
                }
            }
        }

        $versions = 0;
        foreach ($declarations as $chemin => [$rang, $empreinte]) {
            if (!is_file($this->corpus . '/' . $chemin)) {
                continue; // fichier hors dépôt (exemption) — signalé ailleurs
            }
            $reference = $this->referenceDepuisChemin($chemin);
            $this->ensureNorme($reference, basename($chemin), 'texte canonique', $this->domaineDepuisChemin($chemin));
            $this->insererVersion($reference, '0.1', $empreinte, $chemin);
            $versions++;
        }

        return $versions;
    }

    /**
     * Amorce l'index avec les deux faits datés de l'état de conception de
     * CAP-CORE-007 (conception d'implémentation, Titre V, Article 15). Faits
     * réels et adoptés : EN CONCEPTION jusqu'à ADOPTION-0026, CONÇUE ensuite.
     * C'est la matière du test P3 de reconstruction temporelle.
     */
    private function amorcerFaitsP3(): void
    {
        $chemin = 'genesis-ii/conception/CONCEPTION-CAP-CORE-007-REGISTRE-DES-NORMES-0001.md';
        $empreinte = is_file($this->corpus . '/' . $chemin)
            ? GitBlob::hashFile($this->corpus . '/' . $chemin)
            : str_repeat('0', 40);

        $this->ensureNorme('CAP-CORE-007', 'Registre des normes (capacité souveraine)', 'capacité souveraine', 'DOM-01');
        $versionId = $this->insererVersion('CAP-CORE-007', '0.1', $empreinte, $chemin);

        // Statut en ajout seul (INV-3), chaque valeur fondée sur un acte (INV-4).
        $this->insererStatut($versionId, 'EN CONCEPTION', '2026-07-26', 'ADOPTION-0015');
        $this->insererStatut($versionId, 'CONÇUE', '2026-07-27', 'ADOPTION-0026');
    }

    // ---- utilitaires ------------------------------------------------------

    private function ensureNorme(string $ref, string $titre, string $rang, string $domaine): void
    {
        $st = $this->pdo->prepare('SELECT 1 FROM norme WHERE reference = ?');
        $st->execute([$ref]);
        if (!$st->fetchColumn()) {
            $this->pdo->prepare('INSERT INTO norme(reference,titre,rang,domaine) VALUES(?,?,?,?)')
                ->execute([$ref, $titre, $rang, $domaine]);
        }
    }

    private function insererVersion(string $ref, string $version, string $empreinte, string $chemin): int
    {
        if (Db::driver($this->pdo) === 'pgsql') {
            $st = $this->pdo->prepare(
                'INSERT INTO version_norme(norme_reference,version,empreinte_git,chemin) VALUES(?,?,?,?) RETURNING id'
            );
            $st->execute([$ref, $version, $empreinte, $chemin]);
            return (int) $st->fetchColumn();
        }
        $this->pdo->prepare(
            'INSERT INTO version_norme(norme_reference,version,empreinte_git,chemin) VALUES(?,?,?,?)'
        )->execute([$ref, $version, $empreinte, $chemin]);
        return (int) $this->pdo->lastInsertId();
    }

    private function insererStatut(int $versionId, string $valeur, string $dateEffet, string $adoption): void
    {
        $this->pdo->prepare(
            'INSERT INTO statut(version_norme_id,valeur,date_effet,adoption_reference) VALUES(?,?,?,?)'
        )->execute([$versionId, $valeur, $dateEffet, $adoption]);
    }

    private function referenceDepuisChemin(string $chemin): string
    {
        return preg_replace('/\.md$/', '', basename($chemin)) ?? basename($chemin);
    }

    private function domaineDepuisChemin(string $chemin): string
    {
        $parts = explode('/', $chemin);
        return count($parts) > 2 ? implode('/', array_slice($parts, 1, -1)) : 'genesis-ii';
    }

    private function nettoyer(string $cellule): string
    {
        return trim(str_replace(['`', '**'], '', $cellule));
    }
}
