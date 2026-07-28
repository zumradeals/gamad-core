# REGISTRE D'ADOPTION — ADOPTION-0036
## Premier incrément de code de `CAP-CORE-003` — contrat `CTR-02`, vérification des mandats à la date de l'acte

> **PROJET D'ACTE — préparé sur la branche `agent/genesis-ii-code-cap-core-003`.** Il n'entre en vigueur qu'à la fusion `--no-ff` dans `main`, laquelle **est** l'acte d'adoption et appartient exclusivement à l'autorité (`ADOPTION-0024`, Art. 3).

## Nature

Le présent acte adopte le **premier incrément de code de `CAP-CORE-003`**, écrit sur la conception adoptée par `ADOPTION-0035`. Il livre le contrat `CTR-02` en entier et constitue la **première capacité à atteindre `P3` sous la doctrine des gardes** arrêtée par ce même acte.

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté le premier incrément de code de `CAP-CORE-003`.

## Version adoptée

| Objet | Branche de préparation | Commit adopté |
|---|---|---|
| Incrément `core/registre-autorites/` et dérivation associée | `agent/genesis-ii-code-cap-core-003` | `2f321094f97cd3e96461b4d3199f796f70cbca25` |

- **Version :** `0.1`
- **Date d'adoption :** 28 juillet 2026
- **Entrée en vigueur :** immédiate, à la publication sur `main`

---

## Article 1 — Ce qui est dérivé, et de quoi

Aucune donnée n'est inscrite en dur. La leçon de `ADOPTION-0031` est appliquée sans exception :

| Objet | Source dans le corpus |
|---|---|
| 24 fonctions du catalogue | Tableaux des Articles 33 à 36 du registre adopté |
| Leurs états initiaux | Même tableaux ; date et fondement de `ADOPTION-0017` |
| Leurs transitions | Titres de mise à jour post-adoption et leur acte source |
| `MANDAT-GENESIS-II-0001` | Articles 46 et 47 |
| Ses états successifs | Article 47, puis Titre XVI (`ADOPTION-0035`) |

L'exactitude de cette dérivation est vérifiable sur un cas daté : `FCT-CORE-021` — l'autorité d'audit — est restituée `VACANTE` au 26 juillet 2026 sur le fondement de `ADOPTION-0017`, puis `ATTRIBUÉE À TITRE TRANSITOIRE — TITULAIRE UNIQUE` au 27 juillet sur le fondement de `ADOPTION-0022`.

## Article 2 — Le contrat `CTR-02`

Les trois opérations prévues à l'Article 10 de la conception sont livrées, en lecture seule (`INV-4`) :

- **`resoudre_mandat(fonction, titulaire, date)`** — aucune couverture rétroactive (`INV-13`).
- **`verifier_acte(adoption)`** — quatre verdicts, dont **un seul affirme une vérification** : `VÉRIFIÉ`, `CONSTITUTIF`, `NON COUVERT`, `INDETERMINE`.
- **`resoudre_vacance(date)`** — quinze fonctions vacantes restituées, exposées et non masquées.

Routes `GET` correspondantes. Aucun verbe d'écriture : nommer, suspendre ou révoquer demeure un acte signé, jamais une opération de service.

## Article 3 — `INV-15` matérialisé : la chaîne se termine, elle ne boucle pas

`ADOPTION-0001` et `ADOPTION-0004` reçoivent le verdict `CONSTITUTIF`, non `VÉRIFIÉ`, avec le motif suivant restitué par le service :

> « acte contemporain du début du mandat qu'il invoquerait ; la chaîne de mandats s'arrête ici (`INV-15`) »

La règle est **dérivée et non inscrite** : est `CONSTITUTIF` tout acte dont la date coïncide avec le début du mandat qui le couvrirait. Aucune référence d'acte n'est codée en dur, ce que la contre-épreuve démontre à l'Article 4.

## Article 4 — Contre-épreuve de falsification

Conformément à l'Article 3 de `ADOPTION-0032`. Sur copie du corpus hors dépôt, le début de `MANDAT-GENESIS-II-0001` (Article 47) a été déplacé du 24 au 28 juillet 2026.

| Exécution | Résultat | Code |
|---|---|---|
| Corpus sain | `Preuve P3 : ÉTABLIE` | `0` |
| Corpus falsifié | `Preuve P3 : NON ÉTABLIE (5 écarts)` | `1` |

Le détail de l'échec est probant : `ADOPTION-0001`, `0004` et `0026` deviennent `NON COUVERT`, et **`ADOPTION-0035` devient `CONSTITUTIF`** — parce qu'elle coïncide désormais avec le début falsifié. Le verdict suit le corpus et non une constante ; c'est ce déplacement qui l'établit.

Le dépôt n'a pas été modifié par cette expérience.

## Article 5 — Première preuve `P3` sous la doctrine des gardes

`core/registre-autorites/tests/mandat_p3.php` est la première garde de comportement livrée au titre d'une capacité autre que `CAP-CORE-007`, sous la règle de l'Article 2.2 de `ADOPTION-0035`. Elle éprouve `CTR-02` et n'hérite rien de la garde de `CTR-04`.

Le réindexeur **découvre** désormais les gardes (`core/*/tests/*_p3.php`) au lieu de les énumérer : ajouter une capacité et sa garde suffit à la placer sous contrôle. Cette découverte évite qu'une garde nouvelle soit oubliée d'une liste — défaut de périmètre dont `ADOPTION-0033` a montré le coût.

## Article 6 — Ce que le Core peut désormais établir

Pour la première fois, la question suivante reçoit une réponse fondée :

> *L'autorité signataire de cet acte détenait-elle un mandat valide le jour où elle l'a signé ?*

La chaîne `norme → acte d'adoption → autorité signataire → mandat valide à cette date` est close. Son quatrième maillon n'était jusqu'ici vérifié par personne.

## Article 7 — Ce que cet incrément ne livre pas

- La table `delegation` est créée mais **n'est alimentée par aucune dérivation** : `INV-11` et les délégations du Titre VII du registre adopté demeurent conçus et non matérialisés.
- Aucun rapprochement des accès techniques (Titre VIII du registre adopté) : frontière réservée, `ADOPTION-0025`, Art. 3.a.
- Le décompte des verdicts sur l'ensemble des trente-six actes, prévu à l'Article 16 de la conception, n'est pas exposé par une opération dédiée.
- **`CAP-CORE-003` ne permet à personne de se connecter.** Elle rend le pouvoir traçable, non exerçable.

## Article 8 — Constat de tension d'arborescence

L'infrastructure partagée — connexion, schéma, ingestion, empreintes — demeure sous `core/registre-normes/`, module nommé pour `CAP-CORE-007` alors qu'il porte désormais l'index dérivé de tout le Core, y compris les tables de `CAP-CORE-003` et `CAP-CORE-006`.

Le nom est devenu trop étroit pour son contenu. Ce constat est **signalé et non corrigé** : extraire cette infrastructure serait un incrément distinct, et renommer un module adopté exige un acte propre. Il est inscrit ici pour qu'il ne se découvre pas plus tard comme une surprise.

## Article 9 — Effets

- `CAP-CORE-003` passe en implémentation `PARTIELLEMENT MATÉRIALISÉE` et atteint `P3 — TESTÉ` ; conception `CONÇUE` et exploitation `INACTIVE` inchangées. Constaté au Titre XXI de `REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001`.
- `CAP-CORE-007` et `CAP-CORE-006` demeurent inchangées dans tous leurs états.

Cette adoption ne nomme personne, n'étend aucun pouvoir, n'admet aucun produit, n'accepte aucun risque nouveau, ne franchit pas la frontière des accès réservés et ne constate pas `G0`.

## Réserve d'audit maintenue

L'incrément est conçu, codé et vérifié par le même agent, sous une fonction AUDIT non indépendante (`ADOPTION-0025`, Art. 3.b ; Article 163 du registre des autorités).

Cette réserve pèse ici d'un poids particulier : le service livré a précisément pour objet de rendre visibles la concentration des pouvoirs et les vacances, et il restitue que quinze fonctions demeurent vacantes et que l'audit est détenu par le titulaire unique. L'agent a écrit l'instrument qui mesure cette situation, pour le compte de celui qu'elle concerne. Le fait est inscrit ; il n'est pas résolu.

## Constat d'exécution

| Chemin | Mise à jour | Empreinte Git après mise à jour |
|---|---|---|
| `genesis-ii/registres/capacites/REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001.md` | Titre XXI (Articles 123-128) — `CAP-CORE-003` `PARTIELLEMENT MATÉRIALISÉE`, preuve `P3` | `4551546373bb4381925fb798298caca8668314f3` |
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Article 4 — ajout de la ligne `ADOPTION-0036` | `0808ce5013a6182da9e2b96129d78647b56c2aee` |

Ces empreintes remplacent, pour ces deux fichiers et pour eux seuls, celles déclarées par `ADOPTION-0035`, qui demeurent exactes à leur date. Aucune ligne ou article préexistant n'a été réécrit.

## Vérification des gardes

- **Garde documentaire** (`outils/verifier-integrite.py`) : `VÉRIFIÉE`, code `0`.
- **Garde de comportement — `registre-normes`** (`CTR-04`) : `ÉTABLIE`, code `0`.
- **Garde de comportement — `registre-autorites`** (`CTR-02`) : `ÉTABLIE`, code `0`, et `NON ÉTABLIE`, code `1`, contre le corpus falsifié de l'Article 4.

## Publication

La fusion `--no-ff` dans `main` **est** l'acte d'adoption ; elle appartient exclusivement à l'autorité et n'est pas exécutée par l'agent.

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire
- **Date :** 28 juillet 2026
- **Entrée en vigueur :** immédiate, à la publication sur `main`
- **Mention :** LU ET ADOPTÉ
