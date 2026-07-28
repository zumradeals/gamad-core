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

    /** @var array<string,string>|null chemin => référence canonique (INV-7) */
    private ?array $canoniques = null;

    public function __construct(
        private \PDO $pdo,
        private string $corpus,
    ) {
    }

    /**
     * @return array{adoptions:int,normes:int,versions:int,statuts:int,etats:int,
     *               rangs:int,sources:int,fonctions:int,mandats:int,indetermines:int}
     */
    public function executer(): array
    {
        Schema::create($this->pdo);
        $this->actes = [];
        $this->canoniques = null;

        $rangs     = $this->ingererRangsNormatifs();
        $sources   = $this->ingererSources();
        $adoptions = $this->ingererAdoptions();
        $versions  = $this->ingererNormesDeclarees();
        $versions += $this->ingererNormesDeFeuillesStatut();
        $this->ingererEtatsCapacites();
        $fonctions = $this->ingererAutoritesEtMandats();

        return [
            'adoptions' => $adoptions,
            'normes'    => (int) $this->pdo->query('SELECT count(*) FROM norme')->fetchColumn(),
            'versions'  => $versions,
            'statuts'   => (int) $this->pdo->query('SELECT count(*) FROM statut')->fetchColumn(),
            'etats'     => (int) $this->pdo->query('SELECT count(*) FROM etat_capacite')->fetchColumn(),
            'rangs'     => $rangs,
            'sources'   => $sources,
            'fonctions' => $fonctions,
            'mandats'   => (int) $this->pdo->query('SELECT count(*) FROM mandat')->fetchColumn(),
            'indetermines' => (int) $this->pdo
                ->query("SELECT count(*) FROM norme WHERE rang_code = 'INDETERMINE'")->fetchColumn(),
        ];
    }

    /**
     * Dérive la hiérarchie normative des en-têtes d'articles de SOURCES-0001
     * (Articles 25 à 33), du rang supérieur au rang inférieur (`INV-8`).
     *
     * Le rang d'une norme donnée n'est PAS dérivé : SOURCES-0001 énumère en
     * prose les catégories de textes relevant de chaque rang, sans jamais
     * assigner un rang à une norme nommée. Établir cette correspondance est
     * une qualification, qui appartient à l'autorité (`SOURCES-0001`, Art. 6).
     * Toute norme reçoit donc `INDETERMINE`, et leur décompte est exposé.
     */
    private function ingererRangsNormatifs(): int
    {
        $fichier = $this->corpus . '/genesis-ii/sources/SOURCES-0001-hierarchie-authenticite-autorite-sources-gamad.md';
        $inserer = $this->pdo->prepare(
            'INSERT INTO rang_normatif(code,libelle,ordre,article) VALUES(?,?,?,?)'
        );

        $inserer->execute(['INDETERMINE', 'Rang non établi par le corpus', 99, '—']);
        $n = 0;

        foreach (explode("\n", (string) @file_get_contents($fichier)) as $ligne) {
            if (preg_match('/^##\s*Article\s+(2[5-9]|3[0-3])\s*[—-]\s*(.+?)\s*$/u', $ligne, $m)) {
                $article = (int) $m[1];
                $inserer->execute([
                    'R' . ($article - 24),      // Article 25 => R1, … Article 33 => R9
                    trim($m[2]),
                    $article - 24,
                    "SOURCES-0001, Art. {$article}",
                ]);
                $n++;
            }
        }

        return $n;
    }

    /**
     * Dérive les sources reconnues du registre initial des sources : le tableau
     * des sources fondatrices (Article 5) et celui des sources institutionnelles
     * adoptées (Article 8). L'authenticité est reprise telle que déclarée, jamais
     * calculée ni déduite du statut d'adoption (`INV-9`).
     */
    private function ingererSources(): int
    {
        $fichier = $this->corpus . '/genesis-ii/registres/sources/REGISTRE-INITIAL-SOURCES-0001.md';
        $texte = (string) @file_get_contents($fichier);
        $inserer = $this->pdo->prepare(
            'INSERT INTO source(reference,titre,categorie,authenticite,reserve) VALUES(?,?,?,?,?)'
        );

        $vues = [];
        $article = 0;

        foreach (explode("\n", $texte) as $ligne) {
            if (preg_match('/^##\s*Article\s+(\d+)/u', $ligne, $m)) {
                $article = (int) $m[1];
                continue;
            }
            $ligne = trim($ligne);
            if (!str_starts_with($ligne, '|') || !preg_match('/^\|\s*`([A-Z][A-Z0-9-]+)`\s*\|/u', $ligne, $m)) {
                continue;
            }
            $reference = $m[1];
            if (isset($vues[$reference])) {
                continue; // une source inscrite deux fois n'est comptée qu'une
            }
            $c = array_map(fn ($x) => $this->nettoyer(trim($x)), explode('|', trim($ligne, '|')));

            // Article 5 : référence | titre | catégorie | authenticité | statut | réserve
            // Article 8 : référence | titre | date | empreinte | authenticité
            if ($article === 5 && count($c) >= 6) {
                $inserer->execute([$reference, $c[1], $c[2], $c[3], $c[5] !== 'Aucune' ? $c[5] : null]);
            } elseif ($article === 8 && count($c) >= 5) {
                $inserer->execute([$reference, $c[1], 'Source institutionnelle adoptée (SOURCES-0001, Art. 9)', $c[4], null]);
            } else {
                continue;
            }
            $vues[$reference] = true;
        }

        return count($vues);
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
            // Rang INDETERMINE : le corpus n'assigne de rang à aucune norme
            // nommée (INV-8). L'ancienne valeur littérale « texte canonique »
            // n'était pas un rang mais un remplissage.
            $this->ensureNorme($reference, basename($chemin), 'INDETERMINE', $this->domaineDepuisChemin($chemin));
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
     * Dérive fonctions, titulaires et mandats DU REGISTRE ADOPTÉ des autorités
     * (`ADOPTION-0017`, complété par `ADOPTION-0022` et `ADOPTION-0035`).
     *
     * Rien n'est inscrit en dur : le catalogue vient des tableaux des Articles
     * 33 à 36, ses évolutions des Titres de mise à jour post-adoption, et le
     * mandat fondateur des Articles 46 et 47. Les dates d'effet viennent des
     * actes, par l'index des adoptions (`INV-12` : aucune autorité implicite).
     *
     * @return int nombre de fonctions inscrites au catalogue
     */
    private function ingererAutoritesEtMandats(): int
    {
        $chemin = 'genesis-ii/registres/autorites/REGISTRE-INITIAL-AUTORITES-MANDATS-0001.md';
        $absolu = $this->corpus . '/' . $chemin;
        if (!is_file($absolu)) {
            return 0;
        }
        $texte = (string) file_get_contents($absolu);

        // L'acte qui adopte le registre fonde les états initiaux du catalogue.
        $acteInitial = null;
        foreach ($this->actes as $ref => $a) {
            if (str_contains(mb_strtoupper($a['texte'], 'UTF-8'), 'AUTORITES-MANDATS')) {
                $acteInitial = $ref;
                break;
            }
        }
        $dateInitiale = $this->actes[$acteInitial]['date'] ?? null;

        $insFonction = $this->pdo->prepare('INSERT INTO fonction(reference,libelle,source) VALUES(?,?,?)');
        $insEtatF = $this->pdo->prepare(
            'INSERT INTO etat_fonction(fonction_reference,valeur,date_effet,adoption_reference) VALUES(?,?,?,?)'
        );

        // --- Catalogue : Articles 33 à 36, quatre colonnes, état en dernière.
        $article = 0;
        $vues = [];
        foreach (explode("\n", $texte) as $ligne) {
            if (preg_match('/^##\s*Article\s+(\d+)/u', $ligne, $m)) {
                $article = (int) $m[1];
                continue;
            }
            if ($article < 33 || $article > 36) {
                continue;
            }
            $ligne = trim($ligne);
            if (!preg_match('/^\|\s*`(FCT-[A-Z0-9-]+)`\s*\|/u', $ligne, $m)) {
                continue;
            }
            $c = array_map(fn ($x) => $this->nettoyer(trim($x)), explode('|', trim($ligne, '|')));
            if (count($c) < 4 || isset($vues[$m[1]])) {
                continue;
            }
            $insFonction->execute([$m[1], $c[1], $c[2]]);
            if ($dateInitiale !== null && $c[3] !== '') {
                $insEtatF->execute([$m[1], $c[3], $dateInitiale, $acteInitial]);
            }
            $vues[$m[1]] = true;
        }

        // --- Évolutions : Titres de mise à jour post-adoption, trois colonnes
        //     après la référence, la valeur d'arrivée en dernière.
        foreach (preg_split('/^# TITRE /m', $texte) ?: [] as $bloc) {
            if (!str_contains($bloc, 'MISE À JOUR POST-ADOPTION')
                || !preg_match('/\*\*Source :\*\*\s*`(ADOPTION-\d{4})[^`]*`/u', $bloc, $src)) {
                continue;
            }
            $date = $this->actes[$src[1]]['date'] ?? null;
            if ($date === null) {
                continue;
            }
            foreach (explode("\n", $bloc) as $ligne) {
                $ligne = trim($ligne);
                if (!preg_match('/^\|\s*`(FCT-[A-Z0-9-]+)`\s*\|/u', $ligne, $m) || !isset($vues[$m[1]])) {
                    continue;
                }
                $c = array_map(fn ($x) => $this->nettoyer(trim($x)), explode('|', trim($ligne, '|')));
                if (count($c) === 4 && $c[3] !== '') {
                    $insEtatF->execute([$m[1], $c[3], $date, $src[1]]);
                }
            }
        }

        $this->ingererMandatFondateur($texte, $vues);

        return count($vues);
    }

    /**
     * Dérive le titulaire et le mandat fondateurs des Articles 46 et 47, puis
     * leurs états successifs des Titres de mise à jour.
     *
     * @param array<string,bool> $fonctions fonctions connues du catalogue
     */
    private function ingererMandatFondateur(string $texte, array $fonctions): void
    {
        $champ = function (string $bloc, string $etiquette): ?string {
            $motif = '/^-\s*\*\*' . preg_quote($etiquette, '/') . '[^:]*:\*\*\s*(.+?)\s*[;.]?\s*$/mu';

            return preg_match($motif, $bloc, $m) ? $this->nettoyer($m[1]) : null;
        };

        $blocs = [];
        foreach (preg_split('/^## Article (\d+)/m', $texte, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $i => $part) {
            if ($i > 0 && ctype_digit(trim($part))) {
                $blocs[(int) trim($part)] = null;
                $courant = (int) trim($part);
            } elseif (isset($courant)) {
                $blocs[$courant] = $part;
                unset($courant);
            }
        }

        $a46 = $blocs[46] ?? '';
        $a47 = $blocs[47] ?? '';
        if ($a46 === '' || $a47 === '') {
            return;
        }

        $titulaire = $champ($a46, "Référence d'autorité") ?? $champ($a46, 'Référence d’autorité');
        $nom = $champ($a46, 'Titulaire');
        $mandat = $champ($a47, 'Référence');
        $fonction = $champ($a47, 'Fonction');
        $etat = $champ($a47, 'État proposé');
        $preuve = $champ($a46, 'Niveau de preuve') ?? 'P1';
        $debutBrut = $champ($a47, 'Début proposé') ?? '';
        $debut = $this->dateIso($debutBrut);

        if (!$titulaire || !$nom || !$mandat || !$fonction || !$debut || !isset($fonctions[$fonction])) {
            return; // rien n'est inventé : à défaut de source lisible, aucun mandat
        }

        // L'acte qui fonde le mandat est celui qui adopte le registre.
        $acte = null;
        foreach ($this->actes as $ref => $a) {
            if (str_contains(mb_strtoupper($a['texte'], 'UTF-8'), 'AUTORITES-MANDATS')) {
                $acte = $ref;
                break;
            }
        }
        if ($acte === null) {
            return;
        }

        $this->pdo->prepare('INSERT INTO titulaire(reference,libelle,nature) VALUES(?,?,?)')
            ->execute([$titulaire, $nom, 'personne']);
        $this->pdo->prepare(
            'INSERT INTO mandat(reference,fonction_reference,titulaire_reference,debut,fin,niveau_preuve,adoption_reference)
             VALUES(?,?,?,?,?,?,?)'
        )->execute([$mandat, $fonction, $titulaire, $debut, null, $preuve, $acte]);

        $insEtatM = $this->pdo->prepare(
            'INSERT INTO etat_mandat(mandat_reference,valeur,date_effet,adoption_reference) VALUES(?,?,?,?)'
        );
        if ($etat !== null) {
            $insEtatM->execute([$mandat, $etat, $debut, $acte]);
        }

        // Confirmations et changements d'état ultérieurs, portés par les Titres
        // de mise à jour qui citent le mandat et leur acte source.
        foreach (preg_split('/^# TITRE /m', $texte) ?: [] as $bloc) {
            // Le Titre peut désigner son acte par « **Source :** » ou par la
            // formule « conséquence d'exécution de `ADOPTION-NNNN-…` ». On
            // retient le premier acte cité, quelle que soit la forme.
            if (!str_contains($bloc, $mandat)
                || !preg_match('/`(ADOPTION-\d{4})[^`]*`/u', $bloc, $src)) {
                continue;
            }
            $date = $this->actes[$src[1]]['date'] ?? null;
            if ($date !== null && preg_match('/^\|\s*État\s*\|\s*`([^`]+)`/mu', $bloc, $m)) {
                $insEtatM->execute([$mandat, $this->nettoyer($m[1]), $date, $src[1]]);
            }
        }
    }

    /**
     * Indexe les textes fondateurs, dont l'empreinte est déclarée par une
     * feuille de statut `X-STATUT.md` et non par un constat d'exécution en
     * tableau (ère `ADOPTION-0001` à `0019`).
     *
     * Sans cette lecture, les Lois, les gouvernances, le lexique et les
     * sources — c'est-à-dire le socle du corpus — n'entraient tout simplement
     * pas dans l'index. C'est le même angle mort que celui rectifié dans le
     * contrôle documentaire par `ADOPTION-0033` ; il est ici refermé du côté
     * du service.
     *
     * Les fichiers déjà indexés par un constat d'exécution ne sont pas repris :
     * la déclaration d'un acte prime celle d'une feuille (mécanisme de rang).
     */
    private function ingererNormesDeFeuillesStatut(): int
    {
        $deja = [];
        foreach ($this->pdo->query('SELECT chemin FROM version_norme')->fetchAll() as $l) {
            $deja[$l['chemin']] = true;
        }

        $n = 0;
        foreach ($this->correspondancesCanoniques() as $chemin => $reference) {
            if (isset($deja[$chemin])) {
                continue;
            }
            $feuille = $this->corpus . '/' . dirname($chemin) . '/' . $reference . '-STATUT.md';
            if (!is_file($feuille)) {
                continue;
            }
            if (!preg_match(
                '/^-\s*\*\*Empreinte Git du contenu adopté\s*:\*\*\s*`([0-9a-f]{40})`/mu',
                (string) file_get_contents($feuille),
                $m,
            )) {
                continue; // aucune empreinte déclarée : rien n'est inventé
            }

            $this->ensureNorme($reference, basename($chemin), 'INDETERMINE', $this->domaineDepuisChemin($chemin));
            $versionId = $this->insererVersion($reference, '0.1', $m[1], $chemin);
            $n++;

            // Statut fondé sur l'acte qui adopte ce texte, identifié par la
            // référence citée en colonne « texte » de l'index des adoptions.
            foreach ($this->actes as $acte => $a) {
                if ($a['date'] !== null && str_contains($a['texte'], $reference)) {
                    $this->insererStatut($versionId, $this->statutDepuisLabel($a['label']), $a['date'], $acte);
                    break;
                }
            }
        }

        return $n;
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
                || !preg_match('/\*\*Source :\*\*\s*`(ADOPTION-\d{4})[^`]*`/u', $bloc, $src)) {
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
            $this->pdo->prepare('INSERT INTO norme(reference,titre,rang_code,domaine) VALUES(?,?,?,?)')
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

    /**
     * Référence canonique d'un texte (`INV-7`).
     *
     * L'identité d'une norme n'est pas son nom de fichier. Lorsqu'une feuille
     * de statut `X-STATUT.md` accompagne le texte, `X` est sa référence
     * canonique — c'est ainsi que le corpus désigne `CORE-LAWS-0001` ou
     * `GOVERNANCE-0001`, dont les fichiers portent un nom descriptif long.
     * À défaut de feuille, le radical du fichier fait référence.
     *
     * La correspondance est DÉRIVÉE de la présence des feuilles, jamais
     * inscrite en dur : déplacer ou renommer un fichier ne change pas
     * l'identité du texte qu'il porte.
     */
    private function referenceDepuisChemin(string $chemin): string
    {
        $this->canoniques ??= $this->correspondancesCanoniques();

        return $this->canoniques[$chemin]
            ?? (preg_replace('/\.md$/', '', basename($chemin)) ?: basename($chemin));
    }

    /**
     * Construit la table chemin => référence canonique à partir des feuilles de
     * statut. Une feuille dont le texte compagnon n'est pas univoque est
     * ignorée : mieux vaut une référence par défaut qu'une identité fausse.
     *
     * @return array<string,string>
     */
    private function correspondancesCanoniques(): array
    {
        $table = [];
        foreach (glob($this->corpus . '/genesis-ii/*/*-STATUT.md') ?: [] as $feuille) {
            $base = preg_replace('/-STATUT\.md$/', '', basename($feuille)) ?: '';
            if ($base === '') {
                continue;
            }
            $candidats = array_values(array_filter(
                glob(dirname($feuille) . '/' . $base . '*.md') ?: [],
                fn ($p) => !str_ends_with($p, '-STATUT.md'),
            ));
            if (count($candidats) === 1) {
                $relatif = ltrim(str_replace($this->corpus, '', $candidats[0]), '/');
                $table[$relatif] = $base;
            }
        }

        return $table;
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
