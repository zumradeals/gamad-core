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
    /**
     * Actes relevés de l'index, par référence : date d'effet (ISO) et libellé
     * de statut. Sert à FONDER chaque statut sur un acte réel plutôt que sur
     * une constante du code (ADOPTION-0028, Titre III, Art. 8 ; rectification
     * ADOPTION-0031).
     *
     * @var array<string,array{date:?string,label:string}>
     */
    private array $actes = [];

    public function __construct(
        private \PDO $pdo,
        private string $corpus,
    ) {
    }

    /** @return array{adoptions:int,normes:int,versions:int,statuts:int,etats:int} */
    public function executer(): array
    {
        Schema::create($this->pdo);
        $this->actes = [];

        $adoptions = $this->ingererAdoptions();
        $versions  = $this->ingererNormesDeclarees();
        $this->ingererEtatsCapacites();

        return [
            'adoptions' => $adoptions,
            'normes'    => (int) $this->pdo->query('SELECT count(*) FROM norme')->fetchColumn(),
            'versions'  => $versions,
            'statuts'   => (int) $this->pdo->query('SELECT count(*) FROM statut')->fetchColumn(),
            'etats'     => (int) $this->pdo->query('SELECT count(*) FROM etat_capacite')->fetchColumn(),
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

            $reference = "ADOPTION-{$m[1]}";
            $this->pdo->prepare(
                'INSERT INTO adoption(reference,autorite,date_adoption,signature_presente) VALUES(?,?,?,?)'
            )->execute([$reference, $autorite, $date, $signature]);

            // Conservé pour fonder les statuts sur l'acte, non sur une constante.
            $this->actes[$reference] = [
                'date'  => $this->dateIso($date),
                'label' => $statut,
                'texte' => $this->nettoyer($cellules[0] ?? ''),
            ];
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
        $declarations = []; // chemin => [rang, empreinte, acte]

        foreach (glob($repertoire . '/ADOPTION-*.md') ?: [] as $fichier) {
            if (!preg_match('/ADOPTION-(\d{4})/', basename($fichier), $mm)) {
                continue;
            }
            $rang = (int) $mm[1];
            $acte = "ADOPTION-{$mm[1]}";
            foreach (explode("\n", (string) file_get_contents($fichier)) as $ligne) {
                if (preg_match('/^\|\s*`([^`]+?\.(?:md|py|yml))`[^|]*\|(?:[^|]*\|)*?\s*`([0-9a-f]{40})`\s*\|\s*$/u', trim($ligne), $m)) {
                    $chemin = $m[1];
                    if (!isset($declarations[$chemin]) || $rang >= $declarations[$chemin][0]) {
                        $declarations[$chemin] = [$rang, $m[2], $acte];
                    }
                }
            }
        }

        $versions = 0;
        foreach ($declarations as $chemin => [$rang, $empreinte, $acte]) {
            if (!is_file($this->corpus . '/' . $chemin)) {
                continue; // fichier hors dépôt (exemption) — signalé ailleurs
            }
            $reference = $this->referenceDepuisChemin($chemin);
            $this->ensureNorme($reference, basename($chemin), 'texte canonique', $this->domaineDepuisChemin($chemin));
            $versionId = $this->insererVersion($reference, '0.1', $empreinte, $chemin);

            // Statut DÉRIVÉ de l'acte le plus récent qui lie ce fichier : sa date
            // et son libellé viennent de l'index, non d'une constante du code.
            // Sans acte identifiable, aucun statut n'est inventé.
            $a = $this->actes[$acte] ?? null;
            if ($a !== null && $a['date'] !== null) {
                $this->insererStatut($versionId, $this->statutDepuisLabel($a['label']), $a['date'], $acte);
            }
            $versions++;
        }

        return $versions;
    }

    /**
     * Traduit le libellé de statut porté par l'index (Article 4) dans le
     * vocabulaire de statut de norme. Un libellé non reconnu donne
     * `INDETERMINE` : le service dit qu'il ne sait pas, il ne présume pas
     * qu'une norme est en vigueur.
     */
    private function statutDepuisLabel(string $label): string
    {
        $l = mb_strtoupper($label, 'UTF-8');

        return match (true) {
            str_contains($l, 'ABROG')      => 'ABROGE',
            str_contains($l, 'REMPLAC')    => 'REMPLACE',
            str_contains($l, 'AMEND')      => 'AMENDE',
            str_contains($l, 'EN VIGUEUR') => 'EN VIGUEUR',
            str_contains($l, 'CONSTAT')    => 'EN VIGUEUR',
            default                        => 'INDETERMINE',
        };
    }

    /** Convertit une date française (« 27 juillet 2026 ») en ISO comparable. */
    private function dateIso(string $fr): ?string
    {
        static $mois = [
            'janvier' => '01', 'février' => '02', 'mars' => '03', 'avril' => '04',
            'mai' => '05', 'juin' => '06', 'juillet' => '07', 'août' => '08',
            'septembre' => '09', 'octobre' => '10', 'novembre' => '11', 'décembre' => '12',
        ];

        if (!preg_match('/(\d{1,2})\s+(\p{L}+)\s+(\d{4})/u', $fr, $m)) {
            return null;
        }
        $mm = $mois[mb_strtolower($m[2], 'UTF-8')] ?? null;

        return $mm === null ? null : sprintf('%s-%s-%02d', $m[3], $mm, (int) $m[1]);
    }

    /**
     * Dérive les états de conception successifs des capacités souveraines DU
     * REGISTRE DES CAPACITÉS lui-même, et non de constantes du code.
     *
     * Rectification apportée par ADOPTION-0031 : jusque-là, les deux faits
     * datés de CAP-CORE-007 étaient écrits en dur ici, si bien que le test P3
     * relisait ce que cette méthode venait d'écrire — la preuve se prouvait
     * elle-même. Désormais :
     *
     *   · l'état initial vient du tableau de l'Article 31 du registre ;
     *   · chaque transition vient d'un Titre « MISE À JOUR POST-ADOPTION »,
     *     dont la valeur d'arrivée et l'acte source sont lus dans le texte ;
     *   · la date d'effet vient de l'acte source, via l'index des adoptions.
     *
     * Altérer une date ou un état dans le corpus déplace donc réellement le
     * résultat de la reconstruction temporelle (INV-3, INV-5, INV-6).
     */
    private function ingererEtatsCapacites(): void
    {
        $registre = 'genesis-ii/registres/capacites/REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001.md';
        $absolu = $this->corpus . '/' . $registre;
        if (!is_file($absolu)) {
            return; // rien à dériver : aucun état n'est inventé
        }
        $texte = (string) file_get_contents($absolu);

        // L'acte qui adopte le registre des capacités fonde l'état initial.
        $acteInitial = null;
        foreach ($this->actes as $ref => $a) {
            if (str_contains(mb_strtoupper($a['texte'], 'UTF-8'), 'CAPACITES-SOUVERAINES')) {
                $acteInitial = $ref;
                break;
            }
        }

        foreach ($this->capacitesDeclarees($texte) as $capacite => $etats) {
            $precedent = null;
            foreach ($etats as [$valeur, $acte]) {
                $acte ??= $acteInitial;
                $date = $this->actes[$acte]['date'] ?? null;
                if ($date === null || $valeur === $precedent) {
                    continue; // sans acte daté, aucun état ; un état réaffirmé n'est pas un changement
                }
                $this->insererEtatCapacite($capacite, 'conception', $valeur, $date, $acte);
                $precedent = $valeur;
            }
        }
    }

    /**
     * Relève, pour chaque capacité, la suite chronologique de ses états de
     * conception : l'état initial du tableau de l'Article 31, puis chaque
     * valeur d'arrivée déclarée par un Titre de mise à jour post-adoption.
     *
     * @return array<string,list<array{0:string,1:?string}>> capacité => [[état, acte|null], …]
     */
    private function capacitesDeclarees(string $texte): array
    {
        $etats = [];

        // État initial : tableau de l'Article 31 (neuf colonnes).
        foreach (explode("\n", $texte) as $ligne) {
            $ligne = trim($ligne);
            if (!str_starts_with($ligne, '|') || !preg_match('/`(CAP-CORE-\d{3})`/', $ligne, $m)) {
                continue;
            }
            $cellules = array_map('trim', explode('|', trim($ligne, '|')));
            if (count($cellules) >= 9 && preg_match('/`([^`]+)`/u', $cellules[5], $c)) {
                $etats[$m[1]][] = [$c[1], null]; // acte fondateur résolu par l'appelant
            }
        }

        // Transitions : Titres « MISE À JOUR POST-ADOPTION ».
        foreach (preg_split('/^# TITRE /m', $texte) ?: [] as $bloc) {
            if (!str_contains($bloc, 'MISE À JOUR POST-ADOPTION')
                || !preg_match('/\*\*Source :\*\*\s*`(ADOPTION-\d{4})`/u', $bloc, $src)) {
                continue;
            }
            foreach (explode("\n", $bloc) as $ligne) {
                $ligne = trim($ligne);
                if (!str_starts_with($ligne, '|') || !preg_match('/`(CAP-CORE-\d{3})`/', $ligne, $m)) {
                    continue;
                }
                $cellules = array_map('trim', explode('|', trim($ligne, '|')));
                if (count($cellules) !== 3) {
                    continue; // en-tête ou tableau d'une autre forme
                }
                if (preg_match('/Conception\s+\*{0,2}`([^`]+)`/u', $cellules[2], $c)) {
                    $etats[$m[1]][] = [$c[1], $src[1]];
                }
            }
        }

        return $etats;
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

    private function insererEtatCapacite(
        string $capacite,
        string $dimension,
        string $valeur,
        string $dateEffet,
        string $adoption,
    ): void {
        $this->pdo->prepare(
            'INSERT INTO etat_capacite(capacite_reference,dimension,valeur,date_effet,adoption_reference)
             VALUES(?,?,?,?,?)'
        )->execute([$capacite, $dimension, $valeur, $dateEffet, $adoption]);
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
