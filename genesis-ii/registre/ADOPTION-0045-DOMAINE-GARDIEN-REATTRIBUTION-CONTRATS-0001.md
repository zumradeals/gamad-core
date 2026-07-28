# REGISTRE D'ADOPTION — ADOPTION-0045
## Règle du domaine gardien, rectification de trois collisions inexistantes et réattribution des familles `CTR-09` et `CTR-05`

> **PROJET D'ACTE — préparé sur la branche `agent/genesis-ii-collisions-contrats`.** Il n'entre en vigueur qu'à la fusion `--no-ff` dans `main`, laquelle **est** l'acte d'adoption et appartient exclusivement à l'autorité (`ADOPTION-0024`, Art. 3).

## Nature

Le présent acte est un **acte de rectification**, troisième du genre après `ADOPTION-0021` et `ADOPTION-0033`. Il procède de la recherche demandée par l'autorité sur les quatre collisions de numéro de contrat inscrites par `ADOPTION-0044`.

Il ne réécrit le corps d'aucun texte adopté. `ADOPTION-0044` demeure au dépôt telle qu'adoptée ; ses constats erronés sont constatés ici et demeurent consultables là-bas.

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté les dispositions ci-après.

---

# TITRE I — CE QUE L'INSTRUCTION A ÉTABLI

## Article 1 — La question posée n'avait pas la réponse attendue

L'autorité a demandé la résolution des quatre collisions de numéro de contrat relevées par `ADOPTION-0044`, Art. 3 : `CTR-05`, `CTR-08`, `CTR-10` et `CTR-11`.

L'instruction a établi que **trois de ces quatre collisions n'existent pas**, et qu'une cinquième faute, réelle, n'avait été relevée par personne.

## Article 2 — L'Atlas ne numérote pas des contrats, il définit des familles

L'Article 69 de `CORE-ATLAS-0001`, adopté par `ADOPTION-0014` le 26 juillet 2026 et jamais amendé, ne donne pas un numéro de contrat par capacité. Il définit **quatorze familles de contrats communs**, chacune rattachée à un **domaine gardien**.

Trois de ces familles annoncent dans leur intitulé même qu'elles servent deux capacités :

| Famille | Intitulé à l'Article 69 | Domaine gardien | Capacités servies | Domaine des capacités |
|---|---|---|---|---|
| `CTR-08` | Statut produit **ou** realm | `DOM-04` | `CAP-CORE-011`, `CAP-CORE-012` | `DOM-04`, `DOM-04` |
| `CTR-10` | Audit **et** intégrité | `DOM-09` | `CAP-CORE-013`, `CAP-CORE-015` | `DOM-09`, `DOM-09` |
| `CTR-11` | Risque **et** incident | `DOM-10` | `CAP-CORE-017`, `CAP-CORE-018` | `DOM-10`, `DOM-10` |

Les six capacités gardent le domaine de la famille qu'elles portent. Le Registre initial des capacités est, sur ces trois familles, fidèle à l'Atlas depuis son adoption. **Il n'y a jamais eu de collision.**

## Article 3 — L'origine de l'erreur

Le service `CTR-14`, livré par `ADOPTION-0044`, comptait les revendications d'un même numéro et concluait à la faute au-delà de un. Il ne lisait pas la table de l'Article 69 et ignorait jusqu'à l'existence des familles.

Il rapportait une arithmétique là où un constat était attendu. L'acte qui l'a adopté a inscrit ce comptage comme un fait, suspendu la comparaison au réel de huit capacités, et accusé `ADOPTION-0043` d'une faute qui n'existe pas.

## Article 4 — Le coût mesurable de cette erreur

`CAP-CORE-015` — preuves d'intégrité, dont le module est livré, prouvé `P3` et vert en intégration continue, était rendue **invisible à l'annuaire** : son contrat étant tenu pour contesté, aucun module ne pouvait lui être rattaché.

L'annuaire déclarait **six capacités codées quand le dépôt en portait huit**. L'instrument chargé de mesurer l'écart entre la carte et la réalité produisait lui-même un écart de deux.

## Article 5 — Deux emprunts réels, dont un jamais détecté

Deux capacités souveraines sont dépourvues de famille à l'Article 69 : `CAP-CORE-006` — Registre des sources, et `CAP-CORE-005` — Authentification et assurance communes. Leurs fiches (Articles 41 et 40) décrivent leurs contrats sans leur attribuer de numéro. Chacune a emprunté la famille d'un autre domaine.

| Famille empruntée | Intitulé | Domaine gardien | Prise par | Domaine de la capacité | Acte |
|---|---|---|---|---|---|
| `CTR-09` | Données et droits | `DOM-07` | `CAP-CORE-006` | `DOM-01` | `ADOPTION-0032`, Art. 2.1 |
| `CTR-05` | Cycle de décision | `DOM-05` | `CAP-CORE-005` | `DOM-02` / `DOM-08` | `ADOPTION-0039` |

`ADOPTION-0044` avait vu le second emprunt. Elle n'a pas vu le premier, et **aucun mécanisme ne pouvait le voir** : `CTR-09` n'est revendiquée qu'une seule fois, et un contrôle qui ne cherche que les doublons ne voit pas une usurpation solitaire.

## Article 6 — L'acte qui a posé la règle est celui qui l'a enfreinte le premier

`ADOPTION-0032`, Art. 2.1 a arrêté la règle d'attribution — ordre chronologique d'adoption, jamais par correspondance avec le numéro de la capacité, jamais de réemploi — en constatant que « `CTR-01` à `CTR-08` sont pris ». La table de l'Article 69 allait déjà jusqu'à `CTR-14`.

`CTR-09` a donc été attribuée dans le même mouvement que la règle destinée à protéger les attributions, et contre elle.

---

# TITRE II — CE QUE L'AUTORITÉ ARRÊTE

## Article 7 — Deux familles sont ajoutées à l'Atlas

Le Titre XIV de `CORE-ATLAS-0001`, ajouté par le présent acte en fin de texte et sans réécrire aucun article, définit les deux familles qui manquaient :

| Référence | Famille de contrat | Domaine gardien | Objet minimal |
|---|---|---|---|
| `CTR-15` | Référence de source | `DOM-01` | Résoudre une source, son authenticité, son statut et sa qualification |
| `CTR-16` | Preuve de contrôle et assurance | `DOM-02` / `DOM-08` | Établir une session, son niveau d'assurance, son élévation et sa révocation |

Le nombre de familles est porté de quatorze à seize.

## Article 8 — Règle du domaine gardien (`INV-40`)

**Une capacité ne porte que les familles dont elle garde le domaine.** Le domaine gardien de la famille, tel que l'Article 69 de l'Atlas l'établit, doit figurer parmi les domaines de la capacité qui la revendique.

Un partage entre plusieurs capacités satisfaisant toutes cette condition est **régulier**. Une revendication qui ne la satisfait pas est une **usurpation**, fût-elle solitaire.

Cette règle complète celle de `ADOPTION-0032`, Art. 2.1 sur un point où elle était muette : elle disait comment attribuer, elle ne disait pas comment constater une attribution fautive. La règle nouvelle est mécanique, donc vérifiable par un programme, donc gardée en intégration continue.

## Article 9 — Un module déclare la capacité qu'il sert (`INV-41`)

Une famille pouvant servir deux capacités, le numéro de famille ne suffit plus à rattacher un module. Chaque classe de contrat porte désormais une constante `CAPACITE`, **lue sur le disque et non dans le corpus**.

Un module qui ne la déclare pas, ou qui déclare une capacité ne revendiquant pas sa famille, est signalé. Sans cette déclaration, rien ne dirait laquelle des deux capacités de `CTR-10` le module `registre-preuves` sert réellement.

Deux menaces sont retenues : **`M-44`** — une capacité porte une famille hors de son domaine et personne ne le voit ; **`M-45`** — un module est attribué à la mauvaise capacité par correspondance de numéro.

## Article 10 — Réattribution

| Capacité | Famille retirée | Famille attribuée |
|---|---|---|
| `CAP-CORE-006` — Registre des sources | `CTR-09` | **`CTR-15`** |
| `CAP-CORE-005` — Authentification et assurance | `CTR-05` | **`CTR-16`** |

L'ordre des deux numéros n'est pas un choix de l'autorité : la règle de `ADOPTION-0032`, Art. 2.1 attribue dans l'ordre chronologique d'adoption de la conception qui les définit. Celle de `CAP-CORE-006` a été adoptée par `ADOPTION-0032`, celle de `CAP-CORE-005` par `ADOPTION-0039`. **La règle désigne, l'autorité n'arbitre pas.**

`CTR-05` retourne à `CAP-CORE-008` — Registre des décisions, `DOM-05`, qui la revendique depuis l'Article 43. `CTR-09` retourne au domaine `DOM-07` et demeure **sans capacité titulaire** : aucune des vingt ne garde ce domaine, que la table des concepts de l'Atlas rattache aux « Registres de gouvernance des données ». Une famille sans titulaire n'est pas un défaut ; l'écart global de données de l'Article 70 la prévoyait.

## Article 11 — Une attribution se retire par déclaration, jamais par réécriture

Le Titre XXVIII du Registre initial des capacités inscrit les deux réattributions sous une forme que le service dérive sans interprétation : capacité, famille retirée, famille attribuée.

Les Articles 40, 41 et les Titres qui ont porté les attributions fautives **ne sont pas modifiés**. Ils demeurent exacts à leur date ; la déclaration la plus récente prévaut. C'est le mécanisme que le corpus applique déjà à ses empreintes.

## Article 12 — Les six suspensions sans fondement sont levées

L'Article 165 du Registre avait suspendu la comparaison au réel de huit capacités. Six de ces suspensions — `CAP-CORE-011`, `CAP-CORE-012`, `CAP-CORE-013`, `CAP-CORE-015`, `CAP-CORE-017`, `CAP-CORE-018` — étaient sans fondement et sont levées. Les deux autres — `CAP-CORE-005` et `CAP-CORE-008` — sont levées par la réattribution de l'Article 10.

**Aucune capacité ne demeure au verdict `INDETERMINE`.**

## Article 13 — Aucun état de capacité n'est modifié

Le présent acte ne change l'état d'aucune des quatre dimensions, pour aucune des vingt capacités. Le code servant `CAP-CORE-005` et `CAP-CORE-006` est renommé pour porter le numéro de famille exact ; son comportement est inchangé et ses gardes l'établissent. Un renommage n'est pas une régression d'état.

---

# TITRE III — RÉSERVE D'AUDIT

## Article 14 — L'agent a produit l'erreur qu'il corrige

`ADOPTION-0044` a été rédigée par l'agent, sous une fonction AUDIT non indépendante. Son Article 3 a qualifié de collisions trois partages réguliers, son Article 4 a suspendu huit comparaisons sur ce fondement, et sa réserve d'audit a imputé à `ADOPTION-0043` une faute inexistante.

La cause n'est pas une distraction : l'agent a écrit un service qui comparait le Registre à lui-même sans jamais consulter l'Atlas, alors que l'Article 55 exige une comparaison **Atlas**–Registre–réalité. Le terme manquant était nommé dans l'exigence.

Un jour a séparé l'inscription de l'erreur de sa découverte, et cette découverte est venue d'une instruction de l'autorité, non d'un contrôle. La leçon est celle que `ADOPTION-0032` tirait déjà : un outil qui ne peut pas contredire son auteur ne prouve rien. La règle de l'Article 8 est écrite pour être vérifiable par un programme précisément parce que le jugement de l'agent s'est montré insuffisant.

Cette réserve appelle un AUDIT indépendant. Elle ne le remplace pas.

---

## Constat d'exécution

| Chemin | Mise à jour | Empreinte Git après mise à jour |
|---|---|---|
| `genesis-ii/atlas/CORE-ATLAS-0001-atlas-initial-gamad-core.md` | Titre XIV (Articles 121-129) — familles `CTR-15` et `CTR-16`, règle du domaine gardien | `95a0da4bf8724502d076f9eb91e41ad123548272` |
| `genesis-ii/registres/capacites/REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001.md` | Titre XXVIII (Articles 170-177) — réattribution et rectification de l'Article 164 | `dd3993e2e1f9e9c762056693948bcba7c9980177` |
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Article 4 — ligne `ADOPTION-0045` | `fbbbd75084f22a1f15216250d997963649d95f00` |
| Incrément de code — `CTR-14` corrigé, renommage de `Ctr09` et `Ctr05`, constante `CAPACITE` | commit | `65093691e0efbb35cf8ff92aee9c59dcfb3b7704` |

Ces empreintes remplacent, pour ces fichiers et pour eux seuls, celles déclarées par les actes et feuilles de statut antérieurs, qui demeurent exactes à leur date. **Aucune ligne ni article préexistant n'a été réécrit ou supprimé.**

## Article 15 — Vérification des gardes

| Garde | Capacité | Sortie |
|---|---|---|
| `outils/verifier-integrite.py` — documentaire | — | `0` |
| `core/registre-identites/tests/identite_p3.php` | `CAP-CORE-001` | `0` |
| `core/registre-autorites/tests/mandat_p3.php` | `CAP-CORE-003` | `0` |
| `core/registre-autorisation/tests/autorisation_p3.php` | `CAP-CORE-004` | `0` |
| `core/registre-acces/tests/authentification_p3.php` | `CAP-CORE-005` | `0` |
| `core/registre-sources/tests/sources_p3.php` | `CAP-CORE-006` | `0` |
| `core/registre-normes/tests/temporel_p3.php` | `CAP-CORE-007` | `0` |
| `core/registre-preuves/tests/preuves_p3.php` | `CAP-CORE-015` | `0` |
| `core/registre-annuaire/tests/annuaire_p3.php` | `CAP-CORE-020` | `0` |

Aucune garde n'est ajoutée : la règle de l'Article 8 est éprouvée par la garde de la capacité qui la porte, `CAP-CORE-020`.

## Article 16 — Contre-épreuve de falsification (`ADOPTION-0032`, Art. 3)

Le corpus a été copié **hors dépôt** et le **domaine gardien** de la famille `CTR-04` — Référence normative y a été déplacé de `DOM-01` à `DOM-03`, dans l'Atlas seul. `CAP-CORE-007`, qui porte cette famille et garde `DOM-01`, cesse alors d'en garder le domaine. Le code exécuté est identique ; seul le corpus change, par la variable `CORPUS_PATH`.

| Corpus | Résultat de la garde | Sortie |
|---|---|---|
| Corpus du dépôt, intact | Preuve `P3` **ÉTABLIE** — 0 usurpation | `0` |
| Copie hors dépôt, non altérée — témoin | Preuve `P3` **ÉTABLIE** — 0 usurpation | `0` |
| Copie hors dépôt, domaine gardien déplacé | Preuve `P3` **NON ÉTABLIE** — 1 usurpation nommée | `1` |

Le témoin établit que l'échec procède de l'altération et non de la copie. L'écart relevé est exactement celui attendu : la règle du domaine gardien cesse d'être vraie, et le service la nomme sans la corriger. Le dépôt est demeuré intact pendant l'épreuve.

Un test qui ne peut pas échouer ne prouve rien. Celui-ci peut échouer, et l'on a constaté qu'il échoue.

## Article 17 — Non-effet

Le présent acte ne rend aucune capacité admise ni active, n'admet aucun produit, n'accepte aucun risque nouveau, ne nomme aucun responsable, ne comble pas l'écart de l'Article 69 du Registre initial des capacités, ne franchit pas la frontière des accès réservés (`ADOPTION-0025`, Art. 3) et **ne constate pas `G0`**.

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire
- **Date :** 28 juillet 2026 · **Mention :** LU ET ADOPTÉ
