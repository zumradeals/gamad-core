# REGISTRE D'ADOPTION — ADOPTION-0043
## Conception et premier code de `CAP-CORE-015` — preuves d'intégrité, politique des algorithmes et inventaire des preuves racines

> **PROJET D'ACTE — préparé sur la branche `agent/genesis-ii-cap-core-015`.** Il n'entre en vigueur qu'à la fusion `--no-ff` dans `main`, laquelle **est** l'acte d'adoption et appartient exclusivement à l'autorité (`ADOPTION-0024`, Art. 3).

## Nature

Le présent acte adopte la **conception** de `CAP-CORE-015` et son **premier code**, dans un même incrément, selon la forme retenue par `ADOPTION-0039` et `ADOPTION-0040`.

Il n'abroge aucun article et ne modifie le corps d'aucun texte adopté.

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté les dispositions ci-après.

---

## Article 1 — Ce que cette capacité fonde

Six capacités sont codées et prouvées. Toutes reposent, sans exception, sur une même affirmation : **l'empreinte déclarée dans un acte identifie exactement le texte adopté**. `INV-1` la porte depuis `ADOPTION-0029` et chaque garde du dépôt la présuppose.

Cette affirmation n'avait jamais été fondée. Elle était **pratiquée**. Le présent acte la fonde — et, ce faisant, en montre trois limites que le Core ignorait de lui-même.

## Article 2 — Ce que l'inventaire des preuves racines révèle

L'Article 50 du registre des capacités attend, parmi ses preuves `G0`, un « inventaire des preuves racines ». Il n'existait pas. `CTR-10` le produit. Trois faits en ressortent, mesurés et non estimés :

**2.1 — Un seul algorithme, et il est affaibli.** Les 88 objets porteurs d'une empreinte déclarée la portent tous en `git-sha1`, et en lui seul. La double conservation exigée par `INV-32` est satisfaite pour **zéro** objet.

La portée doit être dite sans dramatisation : ce qui est rompu dans SHA-1 est la résistance aux **collisions**, non à la **préimage**. Nul ne peut forger aujourd'hui un faux texte correspondant à une empreinte déjà inscrite dans un acte signé. En revanche, un texte préparé avec son jumeau malveillant, puis soumis à l'adoption, pourrait être substitué après coup sans que l'empreinte change.

**2.2 — Les actes eux-mêmes ne portent aucune preuve.** Quarante-trois actes d'adoption sont sur disque, accompagnés d'un constat d'exécution compagnon — quarante-quatre fichiers au total. **Aucun** ne fait l'objet d'une empreinte déclarée par un autre acte.

Le contrôle `C5` vérifie les empreintes que les actes **déclarent** — les objets vers lesquels ils pointent. Il ne vérifie jamais l'acte qui déclare. Le corpus sait donc prouver tout ce dont il parle, et rien de ce qui parle.

Ce que cela signifie exactement : les actes sont versionnés dans Git, `main` ne se réécrit pas, et l'historique publié est une protection réelle. Mais cette protection est **extérieure au système de preuve du corpus** — elle tient à une discipline d'exploitation et à l'hébergeur, non à une déclaration que le corpus pourrait vérifier lui-même. Un acte altéré passerait `C5` sans un mot.

**2.3 — La couverture est partielle.** Cent cinquante fichiers composent le corpus ; quatre-vingt-huit portent une empreinte déclarée.

Ces trois faits ne sont pas des défaillances de l'incrément : ils en sont le résultat. Ils étaient vrais avant lui, et invisibles.

## Article 3 — Politique des algorithmes

L'autorité arrête la politique inscrite au Titre XXVI, Article 155 du registre des capacités : `git-sha1` déclaré `AFFAIBLI` et faisant foi, `sha256` déclaré `ADMIS`. Trois statuts existent — `ADMIS`, `AFFAIBLI`, `RÉVOQUÉ` —, le dernier portant la **révocation** que l'Article 50 attend du contrat, sans clé ni signature : révoquer un algorithme est une décision inscrite, non une opération cryptographique.

Qu'un algorithme affaibli fasse foi est inconfortable et exact. Le révoquer invaliderait les quatre-vingt-huit déclarations en vigueur et le corpus entier ; le déclarer `ADMIS` serait faux. L'autorité retient le constat juste plutôt que la formulation confortable.

La politique est **dérivée du registre adopté** par le service, jamais codée en dur. Un service décidant de ses propres algorithmes déciderait à la place de l'autorité.

## Article 4 — Une seconde implémentation, délibérément indépendante

`CTR-10` **ne lit pas l'index dérivé**. Il relève les déclarations directement dans le corpus et recalcule les empreintes depuis les fichiers.

Il constitue ainsi une seconde implémentation, indépendante du contrôle Python `C5` comme de l'ingestion de `CTR-04`, **sur le même périmètre** — tout fichier du corpus peut déclarer, et les feuilles de statut déclarent l'empreinte d'origine de leur texte compagnon. Le périmètre est repris à dessein : deux implémentations ne se contrôlent l'une l'autre que si elles portent sur la même affirmation. Un périmètre plus étroit aurait produit deux chiffres incomparables, dont le désaccord n'aurait rien appris.

**Les deux implémentations concordent : 88 objets déclarés de part et d'autre.** C'est le premier contrôle croisé du Core, et il est vert.

## Article 5 — Ce que cet acte ne fait pas, et pourquoi

**Aucune signature.** Signer suppose une clé privée. Les clés relèvent exclusivement de l'autorité (`ADOPTION-0025`, Art. 3.a) et `CAP-CORE-016` n'est pas conçue. Le Core ne signera rien tant que la gouvernance des clés n'aura pas été adoptée. Un dépôt contenant une clé de signature ne prouverait plus rien : il offrirait le moyen de fabriquer la preuve.

**Aucun horodatage de confiance.** Le Core ne dispose d'aucune horloge de confiance. Il rend le moment de son propre calcul, en le déclarant tel — un fait local, non une attestation temporelle.

En conséquence, quatre des données minimales de l'Article 50 — signature, horodatage, chaîne de confiance, révocation de clé — demeurent **explicitement vides**. Les laisser vides et le dire vaut mieux que les remplir de valeurs sans autorité.

## Article 6 — La menace `M-32` n'est pas contenue

La collision SHA-1 permettrait de faire adopter un texte préparé avec un jumeau de même empreinte, puis de substituer l'un à l'autre. **Cette menace n'est pas contenue par le présent incrément.**

Le service la réduit d'un cran — il rend l'écart visible et chiffré là où il était invisible. Il ne l'annule pas. La contenir suppose que l'autorité déclare des empreintes `sha256` dans les actes à venir, décision qui lui appartient et que le présent acte ne préjuge pas.

## Article 7 — Effets

- `CAP-CORE-015` : conception **`CONÇUE`**, implémentation **`PARTIELLEMENT MATÉRIALISÉE`**, preuve **`P3 — TESTÉ`**.
- `INV-31` à `INV-35` sont introduits ; `M-32` à `M-37` sont retenues.
- La politique des algorithmes de preuve est établie, pour la première fois.
- L'inventaire des preuves racines existe et chiffre trois écarts.
- Les huit gardes du dépôt sont portées en intégration continue.

Cet acte ne rend `CAP-CORE-015` ni admise ni active, n'installe aucune clé, ne signe rien, n'admet aucun produit, ne franchit pas la frontière des accès réservés et ne constate pas `G0`.

## Réserve d'audit maintenue — et son poids particulier ici

L'acte est rédigé par l'agent, sous une fonction AUDIT non indépendante. La réserve pèse ici plus lourd qu'ailleurs, et il faut le dire.

La menace `M-32` suppose un agent capable de soumettre à l'adoption un texte préparé avec son jumeau. Dans l'état actuel du Core, cet agent, c'est **le rédacteur du présent acte**. Le document qui décrit la menace est écrit par celui qui serait en position de l'exécuter.

Cela ne rend pas la conception fausse. Cela rend l'AUDIT indépendant plus nécessaire ici que pour toute capacité précédente, et la double conservation moins théorique qu'elle n'en a l'air.

## Constat d'exécution

| Chemin | Mise à jour | Empreinte Git après mise à jour |
|---|---|---|
| `genesis-ii/conception/CONCEPTION-CAP-CORE-015-PREUVES-INTEGRITE-0001.md` | Création — conception adoptée | `85d0618fc2bbcfb91d788ca073eb47c713361bc6` |
| `genesis-ii/registres/capacites/REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001.md` | Titre XXVI (Articles 153-160) — état de `CAP-CORE-015` et politique des algorithmes | `e46e0773cc12d98468ede8728ae6ad85c89004c3` |
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Article 4 — ligne `ADOPTION-0043` | `d08abc13d7e4e3c7eeae5847c0e4d09bafcd06e7` |
| Incrément de code — `CTR-10`, garde `P3`, intégration continue | commit | `a4e999e48ba6cd0ae1793fbce330fad17808b977` |

Ces empreintes remplacent, pour ces fichiers et pour eux seuls, celles déclarées par les actes antérieurs, qui demeurent exactes à leur date. **Aucune ligne ni article préexistant n'a été réécrit ou supprimé.**

## Article 8 — Vérification des gardes

| Garde | Capacité | Sortie |
|---|---|---|
| `outils/verifier-integrite.py` — documentaire | — | `0` |
| `core/registre-identites/tests/identite_p3.php` | `CAP-CORE-001` | `0` |
| `core/registre-autorites/tests/mandat_p3.php` | `CAP-CORE-003` | `0` |
| `core/registre-autorisation/tests/autorisation_p3.php` | `CAP-CORE-004` | `0` |
| `core/registre-acces/tests/authentification_p3.php` | `CAP-CORE-005` | `0` |
| `core/registre-sources/tests/sources_p3.php` | `CAP-CORE-006` | `0` |
| `core/registre-normes/tests/temporel_p3.php` | `CAP-CORE-007` | `0` |
| **`core/registre-preuves/tests/preuves_p3.php`** | **`CAP-CORE-015`** | **`0` — nouvelle** |

## Article 9 — Contre-épreuve de falsification (`ADOPTION-0032`, Art. 3)

Le corpus a été copié **hors dépôt** et **un seul octet** a été ajouté à un objet déclaré — `SOURCES-0001-hierarchie-authenticite-autorite-sources-gamad.md`. Le code exécuté est identique ; seul le corpus change, par la variable `CORPUS_PATH`.

| Corpus | Résultat de la garde | Sortie |
|---|---|---|
| Corpus du dépôt, intact | Preuve `P3` **ÉTABLIE** | `0` |
| Copie hors dépôt, un octet ajouté | Preuve `P3` **NON ÉTABLIE** | `1` |

L'écart relevé est exactement celui attendu : `SOURCES-0001` cesse de concorder avec l'empreinte déclarée, et le service rend l'empreinte réelle du fichier altéré plutôt que celle qu'il aurait pu recopier de l'index. Le dépôt est demeuré intact pendant l'épreuve.

Un test qui ne peut pas échouer ne prouve rien. Celui-ci peut échouer, et l'on a constaté qu'il échoue.

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire
- **Date :** 28 juillet 2026 · **Mention :** LU ET ADOPTÉ
