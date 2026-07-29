# REGISTRE D'ADOPTION — ADOPTION-0052
## L'acte de lot : adopter plusieurs incréments par un seul acte, sans amoindrir aucune garantie

> **PROJET D'ACTE — préparé sur la branche `agent/genesis-ii-adoption-par-lot`.** Il n'entre en vigueur qu'à la fusion `--no-ff` dans `main`, laquelle **est** l'acte d'adoption et appartient exclusivement à l'autorité (`ADOPTION-0024`, Art. 3).

## Nature

Le présent acte arrête une **doctrine de forme** : celle d'un acte adoptant plusieurs incréments à la fois. Il adopte le Titre XIV du Registre initial des décisions (Articles 159 à 166, ajout seul), introduit `INV-51`, étend la garde de `CAP-CORE-008` et met à jour le document d'accueil des agents.

Il n'adopte aucun incrément de code de capacité, ne modifie aucun état et ne réécrit le corps d'aucun texte adopté.

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté les dispositions ci-après.

---

# TITRE I — LE CONSTAT

## Article 1 — La redite, mesurée

Sur les six actes qui ont précédé le présent : **neuf cent quatre-vingt-dix-sept lignes de corpus pour mille sept cent quatre-vingt-dix-sept lignes de code.** Le corpus a coûté plus de la moitié du code.

Pour le seul `CAP-CORE-008` : huit cent soixante-trois lignes de code, et quatre cent quatre-vingt-dix lignes de prose normative — conception, acte, Titres de registre — dont une large part répétait d'un texte à l'autre le même tableau de gardes, les mêmes empreintes, les mêmes clauses de non-effet.

## Article 2 — Cette redite n'ajoutait aucune garantie

Ce que le corpus vaut, il le tient de cinq choses : les deux gardes, la contre-épreuve de falsification, la règle d'ajout seul, l'empreinte exacte et la fusion réservée à l'autorité. **Aucune de ces cinq n'est produite par la répétition d'un tableau d'un acte à l'autre.**

La redite retranchait même une garantie. C'est dans sa part mécanique — un Titre d'état à ajouter, toujours le même geste — que `ADOPTION-0048` a fauté, et il a fallu deux actes et un mécanisme pour que l'omission soit vue (`ADOPTION-0050`).

## Article 3 — Le lot n'est pas une nouveauté

`ADOPTION-0020` a adopté cinquante-sept documents en un acte. La pratique existe depuis la fondation ; ce qui manquait était son encadrement. Le présent acte ne l'ouvre pas : il l'arrête.

---

# TITRE II — CE QUI EST ADOPTÉ

## Article 4 — `INV-51` — Un acte de lot énumère chacun de ses incréments

**Ce qu'un acte de lot n'énumère pas, il ne l'adopte pas.** Un incrément présent au commit mais absent de l'énumération demeure non adopté, quand bien même la fusion l'aurait porté dans `main`.

Le motif est le risque propre du lot, et il n'est pas théorique : qu'un incrément passe sans être vu parce qu'il voyageait avec d'autres. L'énumération est ce qui distingue un lot **examiné** d'un bloc **avalé**.

## Article 5 — La forme

> `- **Incrément :** <objet>. **Commit :** ` `` `<empreinte>` `` `. **Capacité :** ` `` `CAP-CORE-0NN` `` `. **Garde :** ` `` `<chemin>` `` `.`

La capacité nommée doit exister au Registre initial des capacités. La garde nommée doit exister sur le disque **et être exécutée par l'intégration continue** — une garde que rien n'exécute n'éprouve rien.

Un acte n'énumérant aucun incrément n'est pas un acte de lot. Les actes ordinaires demeurent inchangés, et un lot d'un seul incrément demeure régulier.

## Article 6 — Les cinq garanties que le lot ne peut pas amoindrir

Elles demeurent entières et **par incrément** :

| Garantie | Source |
|---|---|
| Une garde de comportement propre à chaque capacité | `ADOPTION-0035`, Art. 2.2 |
| Une contre-épreuve de falsification avec témoin | `ADOPTION-0032`, Art. 3 |
| Un Titre de constat d'état au Registre des capacités | `ADOPTION-0050`, Art. 5 |
| L'ajout seul : aucun texte adopté n'est réécrit | `ADOPTION-0024` |
| L'empreinte exacte du contenu adopté | `INV-1` |

Le lot change le nombre de textes à écrire. **Il ne change ni le nombre d'autorités qui décident, ni le nombre de preuves à produire.** Un lot qui amoindrirait l'une de ces cinq garanties ne serait pas un lot : ce serait une adoption en bloc, que le corpus n'admet pas.

## Article 7 — Ce qu'un lot ne mêle jamais

Un lot ne contient pas la **rectification d'un défaut** qu'un de ses propres incréments aurait introduit. La rectification appartient à un acte propre, qui la nomme et l'expose.

Le motif procède de `ADOPTION-0050` : un défaut de l'agent trouvé par un mécanisme mérite d'être lu pour lui-même, et non classé au milieu de travaux qui réussissent. Le corpus vaut par sa vérité sur lui-même, y compris sur ses fautes.

## Article 8 — La cadence appartient à l'autorité

Par séance, par jour, par thème ou par capacité. Aucun nombre minimal ni maximal n'est fixé.

---

# TITRE III — PREUVE

## Article 9 — La forme est gardée, non seulement écrite

Une doctrine de forme qu'aucune garde n'éprouve est un vœu. La garde de `CAP-CORE-008` est **étendue** : elle dérive les actes de lot et vérifie, pour chaque incrément énuméré, que la capacité nommée existe, que la garde nommée existe sur le disque et qu'elle est exécutée en intégration continue.

Aucune garde n'est ajoutée (`ADOPTION-0035`, Art. 2.2).

## Article 10 — Un contrôle qui n'a rien à voir aujourd'hui

Le dépôt ne porte aucun acte de lot : le contrôle s'exerce sur un ensemble vide, et il passe trivialement. Ce fait est déclaré plutôt que dissimulé derrière une sortie à `0`.

**C'est la contre-épreuve qui l'éprouve**, en fabriquant hors dépôt le lot défaillant que le dépôt ne porte pas. Un contrôle qui n'a rien à voir aujourd'hui doit voir demain, et c'est aujourd'hui qu'on le prouve.

## Article 11 — Vérification des gardes

| Garde | Capacité | Sortie |
|---|---|---|
| `outils/verifier-integrite.py` — documentaire | — | `0` |
| `core/registre-identites/tests/identite_p3.php` | `CAP-CORE-001` | `0` |
| `core/registre-autorites/tests/mandat_p3.php` | `CAP-CORE-003` | `0` |
| `core/registre-autorisation/tests/autorisation_p3.php` | `CAP-CORE-004` | `0` |
| `core/registre-acces/tests/authentification_p3.php` | `CAP-CORE-005` | `0` |
| `core/registre-sources/tests/sources_p3.php` | `CAP-CORE-006` | `0` |
| `core/registre-normes/tests/temporel_p3.php` | `CAP-CORE-007` | `0` |
| `core/registre-decisions/tests/decisions_p3.php` | `CAP-CORE-008` | `0` — étendue |
| `core/registre-contrats/tests/contrats_p3.php` | `CAP-CORE-009` | `0` |
| `core/registre-preuves/tests/preuves_p3.php` | `CAP-CORE-015` | `0` |
| `core/registre-annuaire/tests/annuaire_p3.php` | `CAP-CORE-020` | `0` |

## Article 12 — Contre-épreuve de falsification (`ADOPTION-0032`, Art. 3)

Deux lots défaillants ont été fabriqués sur des **copies hors dépôt**, avec témoin non altéré.

| Corpus | Altération | Résultat | Sortie |
|---|---|---|---|
| Corpus du dépôt, intact | aucune | `P3` **ÉTABLIE** | `0` |
| Copie hors dépôt — témoin | aucune | `P3` **ÉTABLIE** | `0` |
| Copie hors dépôt — falsification 1 | incrément énumérant une garde qui n'existe pas | `P3` **NON ÉTABLIE** | `1` |
| Copie hors dépôt — falsification 2 | incrément dont la garde existe mais n'est pas exécutée en intégration continue | `P3` **NON ÉTABLIE** | `1` |

La seconde importe autant que la première : c'est la faute la plus vraisemblable — livrer une garde et oublier de l'inscrire au flux d'intégration continue. Le contrôle la voit.

Le témoin établit que l'échec procède de l'altération et non de la copie. Le dépôt est demeuré intact pendant les deux épreuves.

---

# TITRE IV — EFFETS ET LIMITES

## Article 13 — Effets

`INV-51` est introduit. Aucune menace nouvelle n'est retenue : le risque du lot est traité par l'invariant et par la garde qui l'éprouve.

Aucun état de capacité n'est modifié. `CAP-CORE-008` demeure conception `CONÇUE`, implémentation `PARTIELLEMENT MATÉRIALISÉE`, exploitation `INACTIVE`, preuve `P3 — TESTÉ` : une extension de garde n'est pas un changement d'état.

`CLAUDE.md`, document d'accueil non normatif et sans valeur d'autorité, est mis à jour pour que tout agent connaisse la voie du lot. Il ne modifie aucun texte adopté et n'en interprète aucun.

## Article 14 — Ce que cet acte ne fait pas

Il n'adopte **aucun incrément de code de capacité** : il n'énumère aucun incrément et n'est donc pas lui-même un acte de lot. Il ne dispense d'aucune garde, d'aucune contre-épreuve, d'aucun Titre d'état et d'aucune empreinte. Il ne réduit en rien le rôle de l'autorité : la fusion `--no-ff` demeure l'acte d'adoption, unique et réservé.

Il ne rend aucune capacité admise ni active, n'opère aucun déploiement, n'accepte aucun risque nouveau, ne nomme aucun responsable, ne franchit pas la frontière des accès réservés (`ADOPTION-0025`, Art. 3) et **ne constate pas `G0`**.

## Réserve d'audit maintenue

L'acte est rédigé par l'agent, sous une fonction AUDIT non indépendante. Le concepteur ne s'audite pas.

Une réserve propre au présent acte mérite d'être dite : **c'est l'agent qui propose d'alléger les textes que l'agent doit écrire.** L'intérêt n'est pas neutre. C'est pourquoi l'allègement porte exclusivement sur la redite et le mécanique, pourquoi les cinq garanties sont énumérées une à une à l'Article 6, et pourquoi la doctrine nouvelle arrive avec sa propre garde et sa propre falsification plutôt qu'avec une promesse.

## Constat d'exécution

| Chemin | Mise à jour | Empreinte Git après mise à jour |
|---|---|---|
| `genesis-ii/registres/decisions/REGISTRE-INITIAL-DECISIONS-0001.md` | Titre XIV — Articles 159 à 166 (ajout seul) | `578336f565339544df9d52a3e1c07684b8113184` |
| Incrément de code — dérivation des lots, extension de la garde de `CAP-CORE-008`, mise à jour de `CLAUDE.md` | commit | `8515cd36e9520dabab3b9422265368ca7393134d` |
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Article 4 — ligne `ADOPTION-0052` | `668c67a535998b14874112d9061053a25a41f59e` |

Ces empreintes remplacent, pour ces fichiers et pour eux seuls, celles déclarées par les actes antérieurs, qui demeurent exactes à leur date. **Aucune ligne ni article préexistant n'a été réécrit ou supprimé.**

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire
- **Date :** 29 juillet 2026 · **Mention :** LU ET ADOPTÉ
