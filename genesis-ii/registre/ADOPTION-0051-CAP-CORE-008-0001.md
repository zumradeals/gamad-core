# REGISTRE D'ADOPTION — ADOPTION-0051
## Conception et premier code de `CAP-CORE-008` — Registre des décisions ; forme dérivable d'une décision ouverte et première inscription

> **PROJET D'ACTE — préparé sur la branche `agent/genesis-ii-cap-core-008`.** Il n'entre en vigueur qu'à la fusion `--no-ff` dans `main`, laquelle **est** l'acte d'adoption et appartient exclusivement à l'autorité (`ADOPTION-0024`, Art. 3).

## Nature

Le présent acte adopte la **conception** de la capacité souveraine `CAP-CORE-008` — Registre des décisions et son **premier incrément de code**, servant le contrat `CTR-05` — Cycle de décision.

Il arrête en outre la **forme dérivable** par laquelle une décision réservée à l'autorité est inscrite et close, et il inscrit un premier ensemble de vingt-cinq décisions relevées de textes adoptés. Il ne réécrit le corps d'aucun texte adopté.

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté les dispositions ci-après.

---

# TITRE I — MOTIF ET PÉRIMÈTRE

## Article 1 — Pourquoi cette capacité

`CAP-CORE-008` est de criticité `RACINE` et n'appartient pas à l'ensemble racine de l'Article 61. L'Article 83 réserve l'ordre à l'autorité, qui la retient.

Deux motifs. Elle est la **seule capacité `RACINE` non codée** que l'agent puisse construire sans franchir la frontière des accès réservés — `CAP-CORE-016` gouverne les secrets et les clés, `CAP-CORE-019` la sauvegarde et la restauration, l'une et l'autre sur le domaine que `ADOPTION-0025`, Art. 3.a réserve à l'autorité seule. Et surtout : **ce dépôt produit des décisions et n'en tient pas registre.**

## Article 2 — Trois constats, relevés par le service et non estimés

**Constat 1 — cinquante décisions formelles, dix-sept inventoriées.** Le dépôt porte cinquante actes d'adoption, tous inscrits à l'index de l'Article 4. Le tableau consolidé de l'Article 92 du Registre initial des décisions — dont l'objet est de les inventorier — s'arrête à `ADOPTION-0017` et n'a jamais été prolongé. **Trente-trois décisions formelles sont hors de la table qui prétend les recenser.**

**Constat 2 — trois statuts employés, aucun du vocabulaire adopté.** L'Article 17 arrête quinze états possibles d'une décision. L'index en emploie trois — `LU ET ADOPTÉ — EN VIGUEUR` (quarante-six fois), `ADOPTÉ — EN VIGUEUR` (trois), `SIGNÉ — G0 CONSTATÉE` (une) — et **aucun ne figure au vocabulaire de l'Article 17**.

**Constat 3 — vingt-cinq décisions réservées, dispersées et non suivies.** Quatorze au Titre XI du Registre des décisions, cinq au Titre XII du Registre des capacités, six posées par les actes du cycle de codage, sous des intitulés qui varient. Rien ne les rassemblait ; rien ne disait lesquelles avaient été tranchées.

## Article 3 — Ce que la capacité fait, et ce qu'elle ne fait pas

Elle **tient le registre** : elle inventorie, confronte, relève les statuts et suit les décisions ouvertes.

Elle ne décide rien, n'adopte rien, ne clôt rien, n'attribue ni classe ni niveau de risque, et ne prolonge aucune table. Ces actes appartiennent à l'autorité.

---

# TITRE II — CE QUI EST ADOPTÉ

## Article 4 — La conception

`genesis-ii/conception/CONCEPTION-CAP-CORE-008-REGISTRE-DES-DECISIONS-0001.md` est adoptée. Elle introduit `INV-46` à `INV-50` et retient `M-52` à `M-57`.

| Invariant | Énoncé |
|---|---|
| `INV-46` | Le registre dérive des actes, il n'en fonde aucun |
| `INV-47` | Une décision ouverte ne se clôt que par un acte qui la nomme |
| `INV-48` | Les inventaires sont confrontés, jamais réconciliés |
| `INV-49` | Un statut hors vocabulaire est nommé, jamais traduit |
| `INV-50` | Classe et niveau de risque ne sont pas déduits de l'objet |

## Article 5 — La forme d'une décision ouverte, et de sa clôture

Le **Titre XIII** du Registre initial des décisions (Articles 151 à 158, ajout seul) arrête deux formes que le service dérive sans interprétation :

> `- **Décision ouverte :** ` `` `DECISION-XXXX` `` ` — <objet>. **Source :** <texte>, <article>.`
>
> `- **Décision close :** ` `` `DECISION-XXXX` `` ` — **Par :** <acte>.`

Le motif est celui que `ADOPTION-0049` a posé pour les attributions de contrat, et il vaut ici davantage encore : **chercher une décision ouverte dans une phrase, ce serait décider laquelle en est une.** Le service ne lit aucune prose.

La ligne d'ouverture n'est jamais effacée : la clôture s'y ajoute, et les deux faits demeurent lisibles.

## Article 6 — La première inscription

Vingt-cinq décisions sont inscrites, **relevées de textes adoptés** où elles figurent déjà comme réservées à l'autorité — quatorze du Titre XI du Registre des décisions, cinq du Titre XII du Registre des capacités, six des actes du cycle de codage. Leur inscription ne les crée pas et n'en tranche aucune.

Deux sont inscrites puis **closes**, l'ouverture demeurant lisible : `DECISION-0020` — l'opportunité de `CAP-CORE-009` — par `ADOPTION-0048` ; `DECISION-0024` — le rattachement de `CTR-07` — par `ADOPTION-0049`.

**Vingt-trois décisions demeurent ouvertes.** La plus ancienne date du 26 juillet 2026.

## Article 7 — Le code

`core/registre-decisions/` — service `CTR-05` en lecture et attestation seulement, sans écriture applicative du corpus (`INV-4`), sans base et sans état conservé ; point d'entrée de consultation en lecture seule ; garde de comportement propre, **la dixième**, inscrite à l'intégration continue.

Le service ne consomme aucun autre contrat. Il lit trois sources et les confronte : les actes présents sur le disque, l'index de l'Article 4, le tableau consolidé de l'Article 92.

## Article 8 — L'inscription n'est pas déclarée complète

Aucun texte n'établit l'ensemble des décisions réservées du corpus, et un service qui ne lit qu'une forme **ne peut pas découvrir ce qui n'y est pas écrit**.

Cette limite n'est pas passée sous silence : elle est inscrite comme `DECISION-0025`, et le service restitue l'exhaustivité de l'inscription comme `NON ÉTABLI`. Un registre qui se prétendrait complet sans pouvoir le prouver serait plus dangereux que l'absence de registre.

---

# TITRE III — PREUVE

## Article 9 — Vérification des gardes

| Garde | Capacité | Sortie |
|---|---|---|
| `outils/verifier-integrite.py` — documentaire | — | `0` |
| `core/registre-identites/tests/identite_p3.php` | `CAP-CORE-001` | `0` |
| `core/registre-autorites/tests/mandat_p3.php` | `CAP-CORE-003` | `0` |
| `core/registre-autorisation/tests/autorisation_p3.php` | `CAP-CORE-004` | `0` |
| `core/registre-acces/tests/authentification_p3.php` | `CAP-CORE-005` | `0` |
| `core/registre-sources/tests/sources_p3.php` | `CAP-CORE-006` | `0` |
| `core/registre-normes/tests/temporel_p3.php` | `CAP-CORE-007` | `0` |
| `core/registre-decisions/tests/decisions_p3.php` | `CAP-CORE-008` | `0` — **nouvelle** |
| `core/registre-contrats/tests/contrats_p3.php` | `CAP-CORE-009` | `0` |
| `core/registre-preuves/tests/preuves_p3.php` | `CAP-CORE-015` | `0` |
| `core/registre-annuaire/tests/annuaire_p3.php` | `CAP-CORE-020` | `0` |

La garde de `CAP-CORE-020`, étendue par `ADOPTION-0050`, exige qu'aucune capacité ne déclare un état que la réalité contredit. Le Titre XXXII du Registre des capacités, adopté par le présent acte, satisfait cette exigence pour `CAP-CORE-008` : le mécanisme a fonctionné dès le premier incrément qui le suivait.

## Article 10 — Contre-épreuve de falsification (`ADOPTION-0032`, Art. 3)

Deux falsifications distinctes sur des **copies hors dépôt**, avec témoin non altéré.

| Corpus | Altération | Résultat | Sortie |
|---|---|---|---|
| Corpus du dépôt, intact | aucune | `P3` **ÉTABLIE** | `0` |
| Copie hors dépôt — témoin | aucune | `P3` **ÉTABLIE** | `0` |
| Copie hors dépôt — falsification 1 | `DECISION-0009` close par `ADOPTION-0099`, acte inexistant | `P3` **NON ÉTABLIE** — 1 écart | `1` |
| Copie hors dépôt — falsification 2 | tableau de l'Article 92 prolongé d'office aux cinquante adoptions | `P3` **NON ÉTABLIE** — 2 écarts | `1` |

La première éprouve `INV-47` : une clôture qui invoque un acte que le dépôt ne porte pas ne clôt rien. La seconde éprouve `INV-48` et `INV-50` à la fois — la réconciliation d'office fait disparaître l'écart **et** attribue une classe à trente-trois décisions qu'aucun texte ne classe.

Le témoin établit que l'échec procède de l'altération et non de la copie. Le dépôt est demeuré intact pendant les deux épreuves.

---

# TITRE IV — EFFETS ET LIMITES

## Article 11 — Effets sur l'état de `CAP-CORE-008`

| Dimension | Avant | Après |
|---|---|---|
| Conception | `À ÉTABLIR` | **`CONÇUE`** |
| Implémentation | `NON COMMENCÉE` | **`PARTIELLEMENT MATÉRIALISÉE`** |
| Exploitation | `INACTIVE` | `INACTIVE` — inchangée |
| Preuve | `P1` | **`P3 — TESTÉ`** |

Le **Titre XXXII** du Registre initial des capacités (Articles 195 à 200, ajout seul) porte ce constat. La famille `CTR-05`, demeurée sans producteur depuis que `ADOPTION-0045` l'a rendue à sa capacité titulaire, en a désormais un.

L'exploitation demeure `INACTIVE` : un service qui s'exécute en local n'est pas un service exploité.

## Article 12 — Points soumis à l'autorité

1. **La complétude de l'inscription** des décisions ouvertes, et le moyen par lequel une décision réservée non inscrite serait découverte — inscrite comme `DECISION-0025`.
2. **Le sort du tableau de l'Article 92** : prolongé, déclaré arrêté à sa date, ou remplacé par l'index de l'Article 4.
3. **Les statuts employés hors du vocabulaire de l'Article 17** : le vocabulaire est-il étendu, ou les statuts sont-ils rapprochés ?
4. **Le mandat de registraire**, que l'Article 143 réserve à un acte distinct — inscrit comme `DECISION-0013`.
5. Les décisions ouvertes de l'Article 43 — classes, quorums, autorités, délais, urgence, contestation — demeurent entières.

## Article 13 — Ce que cet acte ne fait pas

Il ne tranche **aucune** des vingt-trois décisions demeurées ouvertes, n'en préjuge aucune et ne leur fixe aucun délai. Il ne prolonge pas le tableau de l'Article 92, ne corrige aucun statut employé, n'attribue aucune classe ni niveau de risque, et ne mandate aucun registraire.

Il ne rend aucune capacité admise ni active, n'opère aucun déploiement, n'admet aucun produit, n'accepte aucun risque nouveau, ne nomme aucun responsable, ne franchit pas la frontière des accès réservés (`ADOPTION-0025`, Art. 3) et **ne constate pas `G0`**.

## Réserve d'audit maintenue

L'acte est rédigé par l'agent, sous une fonction AUDIT non indépendante. Le concepteur ne s'audite pas.

La précaution qui en découle est portée au code et non à la seule intention : **le service ne lit aucune prose.** Il lit des formes déclaratives, des tableaux et des noms de fichiers. Ce qui n'est pas écrit sous une forme dérivable est déclaré absent, jamais deviné. `ADOPTION-0049` et `ADOPTION-0050` ont établi, coup sur coup, que la vigilance de l'agent ne suffit pas ; seuls des mécanismes écrits d'avance tiennent.

## Constat d'exécution

| Chemin | Mise à jour | Empreinte Git après mise à jour |
|---|---|---|
| `genesis-ii/conception/CONCEPTION-CAP-CORE-008-REGISTRE-DES-DECISIONS-0001.md` | création | `10fb943268ab918491ff7a9e799f0482a047d0af` |
| `genesis-ii/registres/decisions/REGISTRE-INITIAL-DECISIONS-0001.md` | Titre XIII — Articles 151 à 158 (ajout seul) | `720ffd373c2ed553dbac70696d3686b2e9475d96` |
| `genesis-ii/registres/capacites/REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001.md` | Titre XXXII — Articles 195 à 200 (ajout seul) | `a192d1002286fbd28e6138efcb5bb420c3c3db68` |
| Incrément de code — service `CTR-05`, point d'entrée, garde `P3` et inscription en intégration continue | commit | `de595e79332a56591ee6266cf62ae0924eee9533` |
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Article 4 — ligne `ADOPTION-0051` | `e717e7440f6491bdf00503c0330da13c91d6ce18` |

Ces empreintes remplacent, pour ces fichiers et pour eux seuls, celles déclarées par les actes antérieurs, qui demeurent exactes à leur date. **Aucune ligne ni article préexistant n'a été réécrit ou supprimé.**

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire
- **Date :** 29 juillet 2026 · **Mention :** LU ET ADOPTÉ
