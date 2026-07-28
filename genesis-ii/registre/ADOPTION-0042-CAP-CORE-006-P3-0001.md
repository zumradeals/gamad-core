# REGISTRE D'ADOPTION — ADOPTION-0042
## Module propre du registre des sources (`CTR-09`) et garde de comportement : `CAP-CORE-006` atteint `P3`

> **PROJET D'ACTE — préparé sur la branche `agent/genesis-ii-cap-core-006-p3`.** Il n'entre en vigueur qu'à la fusion `--no-ff` dans `main`, laquelle **est** l'acte d'adoption et appartient exclusivement à l'autorité (`ADOPTION-0024`, Art. 3).

## Nature

Le présent acte adopte un incrément de code. Il ne conçoit rien de neuf : il livre la garde que la conception de `CAP-CORE-006`, adoptée par `ADOPTION-0032`, prescrivait à son Article 19 et que `ADOPTION-0034` a délibérément différée.

**Il n'abroge aucun article et ne modifie le corps d'aucun texte adopté.**

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté les dispositions ci-après.

---

## Article 1 — Ce que cet acte corrige

`CAP-CORE-006` était, avant le présent acte, la **seule capacité codée du Core dépourvue de garde de comportement propre**, et la seule à demeurer au niveau de preuve `P1 — DOCUMENTÉ` alors que son code était livré depuis `ADOPTION-0034`.

Deux causes, l'une doctrinale et l'autre matérielle.

## Article 2 — La cause doctrinale a cessé de valoir

`ADOPTION-0034`, Art. 5 justifiait l'absence d'essai en ces termes : l'écrire « créerait une **troisième garde**, là où la discipline du dépôt en pose deux ». Le motif était explicitement institutionnel, non technique.

`ADOPTION-0035`, Art. 2.2 a depuis arrêté la doctrine inverse — **une garde documentaire unique et une garde de comportement par capacité codée** — et cinq gardes de comportement ont été livrées à ce titre.

La retenue de `ADOPTION-0034` protégeait donc une règle qui n'existe plus. L'autorité constate que la raison du retard s'est éteinte et lève la retenue.

## Article 3 — La cause matérielle : un contrat logé chez autrui

`CTR-09` était servi par la classe `Ctr04`, à l'intérieur du module de `CAP-CORE-007`. Un contrat porté par le service d'une autre capacité ne peut être éprouvé séparément — c'était l'obstacle réel.

`CTR-09` est désormais servi par `core/registre-sources/`, module propre, dans son propre espace de noms. `CTR-04` lui **délègue** la résolution des sources : le comportement restitué est inchangé, les appelants existants ne changent pas, et le sens de la dépendance devient conforme à l'Article 42 du registre des capacités — le registre des normes dépend des sources, jamais l'inverse.

## Article 4 — Ce que la garde éprouve

La garde `core/registre-sources/tests/sources_p3.php` éprouve le contrat `CTR-09` sur des faits vrais du corpus, non sur des constantes du code :

| Invariant | Ce qui est éprouvé |
|---|---|
| `INV-7` — identité canonique | `SOURCES-0001` se résout par sa référence ; une référence inconnue rend `null`, sans source approchante |
| `INV-8` — rang fondé | le rang rendu est un rang que le corpus établit, ou `INDETERMINE` ; jamais une valeur inventée |
| `INV-9` — authenticité distincte de l'adoption | `SRC-0007`, inscrite par un acte **en vigueur**, demeure `AUTH-1` ; sa réserve est restituée ; une source non portée en fichier est déclarée **invérifiable**, et non concordante |
| `INV-1` — empreinte exacte | l'empreinte est **recalculée** depuis le fichier et comparée à la déclaration, jamais recopiée depuis l'index |
| `INV-11` — provenance | une source connue sans lignée rend deux listes vides ; une source inconnue rend `null` |

Le cas `INV-9` mérite d'être relevé : il éprouve la capacité par le fait même que `ADOPTION-0041` vient d'inscrire. Qu'un acte adopté inscrive une source ne l'authentifie pas — le service le démontre plutôt que de l'affirmer.

## Article 5 — Portée exacte de la preuve, et ce qu'elle ne couvre pas

Le corpus ne déclare à ce jour **aucune supersession** : quatre-vingt-six normes, toutes `EN VIGUEUR`. `resoudre_lignee` est donc exposée et éprouvée **dans sa manière de déclarer l'absence**, mais la supersession elle-même demeure non exercée, faute de matière.

`INV-11` est matérialisé ; il n'est pas encore mis à l'épreuve par un cas réel. Il le sera au premier acte qui remplacera ou abrogera un texte. Cette limite est inscrite pour qu'elle ne se découvre pas plus tard comme un silence.

## Article 6 — Écart d'intégration continue constaté et corrigé

L'intégration continue n'exécutait que **deux des sept gardes** du dépôt : le contrôle documentaire et celle de `CTR-04`. Les gardes de `CAP-CORE-001`, `CAP-CORE-003`, `CAP-CORE-004` et `CAP-CORE-005`, livrées depuis `ADOPTION-0035`, n'étaient éprouvées qu'à la main.

Une garde qui ne s'exécute pas ne garde rien. Les sept gardes sont désormais portées en intégration continue par `.github/workflows/gardes-comportement.yml`.

**Constat soumis à l'autorité :** `.github/workflows/registre-normes.yml` devient redondant, la garde de `CTR-04` étant reprise par le nouveau workflow. Sa suppression n'est pas opérée par le présent acte — retirer une infrastructure adoptée est une décision de l'autorité, non un choix d'implémentation.

## Article 7 — Effets

- `CAP-CORE-006` passe au niveau de preuve **`P3 — TESTÉ`**.
- `CTR-09` est servi par un module propre ; `CTR-04` lui délègue.
- Les sept gardes du dépôt sont portées en intégration continue.
- L'implémentation de `CAP-CORE-006` demeure **`PARTIELLEMENT MATÉRIALISÉE`** : l'outil de réindexation prescrit par l'Article 16 de `ADOPTION-0032` n'est pas livré.

Cet acte ne rend `CAP-CORE-006` ni admise ni active, n'admet aucun produit, n'accepte aucun risque nouveau, ne franchit pas la frontière des accès réservés et ne constate pas `G0`.

## Réserve d'audit maintenue

L'acte est rédigé par l'agent, sous une fonction AUDIT non indépendante. Le concepteur ne s'audite pas : la garde livrée ici est écrite par celui-là même qui a écrit le service qu'elle éprouve. C'est précisément pourquoi la contre-épreuve de l'Article 9 est exigée — elle est le seul élément que l'auteur ne peut pas se concéder à lui-même.

## Constat d'exécution

| Chemin | Mise à jour | Empreinte Git après mise à jour |
|---|---|---|
| `genesis-ii/registres/capacites/REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001.md` | Titre XXV (Articles 146-152) — `CAP-CORE-006` atteint `P3` | `b2e8c0c12edad898a4f74149ba14c03164f763be` |
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Article 4 — ligne `ADOPTION-0042` | `1bc407157a3d500945dce07915de3003f314dffa` |
| Incrément de code — `CTR-09`, garde `P3`, intégration continue | commit | `5bb4410a418c110503674c14b721c81194700cf5` |

Ces empreintes remplacent, pour ces fichiers et pour eux seuls, celles déclarées par les actes antérieurs, qui demeurent exactes à leur date. **Aucune ligne ni article préexistant n'a été réécrit ou supprimé.**

## Article 8 — Vérification des gardes

| Garde | Capacité | Sortie |
|---|---|---|
| `outils/verifier-integrite.py` — documentaire | — | `0` |
| `core/registre-identites/tests/identite_p3.php` | `CAP-CORE-001` | `0` |
| `core/registre-autorites/tests/mandat_p3.php` | `CAP-CORE-003` | `0` |
| `core/registre-autorisation/tests/autorisation_p3.php` | `CAP-CORE-004` | `0` |
| `core/registre-acces/tests/authentification_p3.php` | `CAP-CORE-005` | `0` |
| **`core/registre-sources/tests/sources_p3.php`** | **`CAP-CORE-006`** | **`0` — nouvelle** |
| `core/registre-normes/tests/temporel_p3.php` | `CAP-CORE-007` | `0` |

## Article 9 — Contre-épreuve de falsification (`ADOPTION-0032`, Art. 3)

Le corpus a été copié **hors dépôt** et le niveau d'authenticité déclaré de `SOURCES-0001` y a été délibérément abaissé, de `AUTH-3` à `AUTH-1`, au registre des sources. Le code exécuté est identique ; seul le corpus change, par la variable `CORPUS_PATH`.

| Corpus | Résultat de la garde | Sortie |
|---|---|---|
| Corpus du dépôt, intact | Preuve `P3` **ÉTABLIE** | `0` |
| Copie hors dépôt, `SOURCES-0001` abaissée à `AUTH-1` | Preuve `P3` **NON ÉTABLIE** — 1 écart | `1` |

L'écart relevé est exactement celui attendu : *« SOURCES-0001 est déclarée AUTH-3 par le corpus — authenticité rendue : 'AUTH-1' »*. Le dépôt est demeuré intact pendant l'épreuve.

Un test qui ne peut pas échouer ne prouve rien. Celui-ci peut échouer, et l'on a constaté qu'il échoue.

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire
- **Date :** 28 juillet 2026 · **Mention :** LU ET ADOPTÉ
