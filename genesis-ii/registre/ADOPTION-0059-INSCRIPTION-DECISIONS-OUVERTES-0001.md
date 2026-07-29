# REGISTRE D'ADOPTION — ADOPTION-0059
## Seconde inscription de décisions ouvertes — vingt-quatre points portés de la prose à la forme

> **PROJET D'ACTE — préparé sur la branche `agent/genesis-ii-inscription-decisions`.** Il n'entre en vigueur qu'à la fusion `--no-ff` dans `main`, laquelle **est** l'acte d'adoption et appartient exclusivement à l'autorité (`ADOPTION-0024`, Art. 3).

## Nature

Acte **d'inscription**, et rien d'autre. Il porte à la forme dérivable de l'Article 153 des décisions que quatre actes adoptés énoncent déjà en prose.

**Il ne tranche aucune décision, n'en préjuge aucune et n'en clôt aucune.** Aucun code n'est livré, aucune garde n'est ajoutée ni modifiée.

Il n'est pas un acte de lot : il n'énumère aucun incrément.

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté les dispositions ci-après.

---

# TITRE I — LE CONSTAT

## Article 1 — Objet

Est adopté le **Titre XVI** — Articles 176 à 183 — ajouté au Registre initial des décisions, qui inscrit `DECISION-0026` à `DECISION-0049`.

## Article 2 — Une forme adoptée n'est pas une forme employée

`ADOPTION-0051` a arrêté au Titre XIII la forme d'inscription d'une décision ouverte, et son Article 152 a dit pourquoi : les décisions réservées à l'autorité vivaient « dans la prose, sous des intitulés qui varient », et aucun service ne pouvait les retrouver toutes.

Le défaut a reparu **dans les actes écrits après ce Titre** :

| Acte | Article | Points énoncés | Inscrits |
|---|---|---|---|
| `ADOPTION-0053` | 15 | 5 | 0 |
| `ADOPTION-0055` | 16 | 6 | 0 |
| `ADOPTION-0057` | 19 | 7 | 0 |
| `ADOPTION-0058` | 11 | 6 | 0 |
| **Total** | | **24** | **0** |

Treize de ces vingt-quatre points ont été écrits par l'agent, dans `ADOPTION-0057` et `ADOPTION-0058`, après que le Titre XIII eut nommé le défaut et donné la forme qui l'évitait. Le constat est porté ici sans atténuation.

## Article 3 — Ce que six points en prose valaient

Avant le présent acte, `Ctr05::inscrites()` dérivait **25 inscrites, 23 ouvertes, 2 closes**. Les six points de l'Article 11 d'`ADOPTION-0058` — dont le rattachement de l'admission, que l'acte recommandait de trancher en premier — n'existaient, pour tout service du Core, que comme du texte.

Une décision qu'aucun service ne peut nommer ne peut pas non plus être close : l'Article 154 exige une ligne de clôture désignant une référence, et il n'y avait pas de référence.

---

# TITRE II — CE QUI EST INSCRIT

## Article 4 — Inscrire suppose un jugement, et ce jugement est déclaré

Passer d'un point de prose à une ligne inscrite n'est pas mécanique : il faut décider ce qui compte pour une décision, où l'une finit, et si un point n'est pas déjà inscrit sous un autre libellé.

L'Article 152 l'avait dit — « chercher une décision ouverte dans la prose, c'est décider laquelle en est une ». Le présent acte ne s'en exempte pas. Ses regroupements sont des **appréciations soumises**, non des constats, et l'Article 178 les déclare comme telles.

## Article 5 — Un point n'est pas inscrit, parce qu'il l'est déjà

Le point 6 de l'Article 11 d'`ADOPTION-0058` — l'ordre entre définir l'admission, admettre effectivement et rendre l'audit indépendant — invoque expressément l'Article 83 du Registre initial des capacités souveraines, qui est la source de `DECISION-0019`, inscrite et demeurée ouverte.

Il n'en est qu'une espèce. **Il ne reçoit pas de référence propre** : deux références pour une décision valent moins qu'une, car clore l'une laisserait l'autre ouverte sans motif.

L'adjacence de `DECISION-0043` — ce qui suit `P3` — est signalée et traitée autrement : elle ne demande pas dans quel ordre exploiter, mais **si l'exploitation est entreprise**, et à quelle frontière.

## Article 6 — L'acte inscrit sa propre réserve plutôt que de l'écrire en prose

`DECISION-0049` soumet à l'autorité les regroupements de l'Article 180 et le rejet de doublon de l'Article 179.

Cette inscription n'est pas une précaution de style. Un acte qui répare la dispersion des décisions en prose et poserait ses propres réserves en prose recommencerait le défaut dans le texte même qui le corrige. C'est le seul point sur lequel le présent acte se distingue de ceux qu'il rattrape.

## Article 7 — État dérivé après inscription

Décomptes dérivés par `Ctr05`, selon la règle d'`ADOPTION-0054` :

| | Avant | Après |
|---|---|---|
| Inscrites | 25 | **49** |
| Ouvertes | 23 | **47** |
| Closes | 2 | **2** |
| Clôtures désignant un acte absent | 0 | **0** |

Les vingt-quatre nouvelles sont **toutes ouvertes**. Le nombre de décisions closes ne bouge pas d'une unité, et c'est exactement ce que le présent acte doit produire.

## Article 8 — Comment une décision se clôt désormais

L'inscription rend la clôture possible ; elle ne la donne pas. Une décision inscrite n'est close que par une ligne de la forme de l'Article 154 :

> `- **Décision close :** ` + `` `DECISION-XXXX` `` + ` — **Par :** <acte>.`

Ni le silence, ni l'ancienneté, ni l'exécution d'un acte voisin ne closent une décision. L'acte qui tranche et la ligne qui le constate vont ensemble : trancher sans inscrire la clôture laisse la décision ouverte au regard de tout service.

---

# TITRE III — PREUVE

## Article 9 — Vérification des gardes

| Garde | Capacité | Sortie |
|---|---|---|
| `outils/verifier-integrite.py` — documentaire | — | `0` |
| `core/registre-identites/tests/identite_p3.php` | `CAP-CORE-001` | `0` |
| `core/registre-organisations/tests/organisations_p3.php` | `CAP-CORE-002` | `0` |
| `core/registre-autorites/tests/mandat_p3.php` | `CAP-CORE-003` | `0` |
| `core/registre-autorisation/tests/autorisation_p3.php` | `CAP-CORE-004` | `0` |
| `core/registre-acces/tests/authentification_p3.php` | `CAP-CORE-005` | `0` |
| `core/registre-sources/tests/sources_p3.php` | `CAP-CORE-006` | `0` |
| `core/registre-normes/tests/temporel_p3.php` | `CAP-CORE-007` | `0` |
| `core/registre-decisions/tests/decisions_p3.php` | `CAP-CORE-008` | `0` — 49 inscrites |
| `core/registre-contrats/tests/contrats_p3.php` | `CAP-CORE-009` | `0` |
| `core/registre-lexique/tests/lexique_p3.php` | `CAP-CORE-010` | `0` |
| `core/registre-produits/tests/produits_p3.php` | `CAP-CORE-011` | `0` |
| `core/registre-realms/tests/realms_p3.php` | `CAP-CORE-012` | `0` |
| `core/registre-audit/tests/audit_p3.php` | `CAP-CORE-013` | `0` |
| `core/registre-evenements/tests/evenements_p3.php` | `CAP-CORE-014` | `0` |
| `core/registre-preuves/tests/preuves_p3.php` | `CAP-CORE-015` | `0` |
| `core/registre-secrets/tests/secrets_p3.php` | `CAP-CORE-016` | `0` |
| `core/registre-risques/tests/risques_p3.php` | `CAP-CORE-017` | `0` |
| `core/registre-incidents/tests/incidents_p3.php` | `CAP-CORE-018` | `0` |
| `core/registre-continuite/tests/continuite_p3.php` | `CAP-CORE-019` | `0` |
| `core/registre-annuaire/tests/annuaire_p3.php` | `CAP-CORE-020` | `0` |

Ces vingt et une sorties ont été relevées **une par une**, selon la règle portée par `ADOPTION-0054`, Art. 7.

## Article 10 — Aucune garde n'a été modifiée

La garde de `CAP-CORE-008` éprouve la **forme** d'une inscription, jamais leur nombre. Elle passe de vingt-cinq à quarante-neuf inscriptions sans qu'une ligne de son code change, et vérifie que la page rendue les porte toutes.

C'est la démonstration la plus directe que l'inscription a pris : vingt-quatre lignes écrites à la main sont lues, rattachées à leur source et rendues par un service qui ne savait rien d'elles.

## Article 11 — Contre-épreuve de falsification

Aucune contre-épreuve n'est déclarée. `ADOPTION-0032`, Art. 3 l'exige de toute garde livrée au titre d'une preuve `P3` ; le présent acte n'en livre ni n'en modifie aucune.

---

# TITRE IV — LIMITES

## Article 12 — Ce que cette inscription n'établit pas

Elle **n'est pas déclarée complète**, et `DECISION-0025` demeure ouverte. Le service ne découvre que ce qui porte la forme ; il ne peut pas trouver une décision ouverte que nul n'a inscrite.

Le fait même que vingt-quatre points aient attendu quatre actes est la mesure de cette limite, non sa réfutation. Rien dans le présent acte n'empêche le défaut de se reformer une troisième fois.

Elle n'attribue aucune classe, aucun niveau de risque, aucun délai, et ne confirme pas la série `DECISION`, qui demeure soumise à `DECISION-0001`.

## Article 13 — Non-effet

Le présent acte **ne tranche, ne préjuge et ne clôt aucune décision**. Il ne rend aucune capacité admise ni active, ne modifie aucun état, ne livre aucun code, n'ajoute ni ne modifie aucune garde, ne nomme aucun responsable, n'accepte aucun risque, ne lève et ne requalifie aucune réserve de `G0`, ne franchit pas la frontière des accès réservés (`ADOPTION-0025`, Art. 3) et **ne constate pas `G0`**.

## Réserve d'audit maintenue

L'acte est rédigé par l'agent, sous une fonction AUDIT non indépendante. Le concepteur ne s'audite pas.

La réserve porte ici sur un fait que l'acte constate contre lui-même : **treize des vingt-quatre points laissés en prose sont les siens**, écrits après que la forme qui les évitait eut été adoptée. Un agent qui relève un défaut qu'il a commis ne s'en absout pas ; il le rend seulement lisible.

L'appréciation par laquelle vingt-quatre points de prose deviennent vingt-quatre décisions distinctes est, elle aussi, celle de l'agent. `DECISION-0049` la soumet expressément plutôt que de la présenter comme un constat.

## Constat d'exécution

| Chemin | Mise à jour | Empreinte Git après mise à jour |
|---|---|---|
| `genesis-ii/registres/decisions/REGISTRE-INITIAL-DECISIONS-0001.md` | Titre XVI — Articles 176 à 183 (ajout seul) | `04027826721d710d1d7151eb1b6819369fc6f835` |
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Article 4 — ligne `ADOPTION-0059` | `7536a4620b131dc05d49d3b3fdc9c6a76195f5af` |

Ces empreintes remplacent, pour ces fichiers et pour eux seuls, celles déclarées par les actes antérieurs — `ADOPTION-0058` compris —, lesquelles demeurent exactes à leur date. **Aucune ligne ni article préexistant n'a été réécrit ou supprimé.**

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire
- **Date :** 29 juillet 2026 · **Mention :** LU ET ADOPTÉ
