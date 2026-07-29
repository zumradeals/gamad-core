# REGISTRE D'ADOPTION — ADOPTION-0055
## Second acte de lot — le domaine `DOM-10` : risques, incidents et continuité

> **PROJET D'ACTE — préparé sur la branche `agent/genesis-ii-lot-dom-10`.** Il n'entre en vigueur qu'à la fusion `--no-ff` dans `main`, laquelle **est** l'acte d'adoption et appartient exclusivement à l'autorité (`ADOPTION-0024`, Art. 3).

## Nature

Second **acte de lot** au sens du Titre XIV du Registre initial des décisions. Il adopte trois incréments de code, énumérés à l'Article 5, et les textes qui les fondent.

Il porte les trois capacités du domaine `DOM-10` — `CAP-CORE-017`, `CAP-CORE-018` et `CAP-CORE-019` — de la conception à la preuve `P3`. Il ne réécrit le corps d'aucun texte adopté.

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté les dispositions ci-après.

---

# TITRE I — LE CONSTAT

## Article 1 — Quatre registres adoptés, aucun service pour les lire

`DOM-10` était le domaine le mieux pourvu en sources adoptées de tous ceux abordés jusqu'ici : risques et contrôles, exceptions de sécurité, incidents de sécurité, sauvegardes et restaurations. Aucun service ne les lisait, et rien ne rassemblait ce qu'ils portent.

## Article 2 — Deux risques sur trois portent un niveau que nul n'a arbitré

Le tableau de l'Article 5 du Registre des risques inscrit `RISK-SEC-0001` (`S3`), `RISK-SEC-0002` (`S1`) et `RISK-SEC-0003` (`S2`). Son Article 6 précise que ces niveaux sont **proposés à titre provisoire par un agent artificiel**.

Un seul a été arbitré : `RISK-SEC-0001`, par l'Article 1 d'`ADOPTION-0022`. Les deux autres conservent leur niveau provisoire depuis le 26 juillet 2026. La Loi 65 de `CORE-LAWS-0001` réserve l'acceptation du risque à l'autorité compétente ; un niveau proposé par un agent n'est donc pas un niveau du corpus, et rien ne le disait à qui lisait le tableau.

## Article 3 — La seule acceptation de risque du corpus n'a pas d'échéance

`RISK-SEC-0001` — l'absence de séparation entre l'audit et les fonctions qu'il devrait auditer — est accepté à titre transitoire. Sa date de réexamen est : « dès disponibilité d'une seconde personne de confiance ; **aucun terme fixe** ».

L'exception associée `EXC-SEC-0001` porte une durée « transitoire, **sans terme fixe** », un statut de sortie **ouvert**, et déclare qu'**aucun contrôle technique compensatoire distinct n'est constitué à ce jour**.

L'Article 52 range pourtant « échéance obligatoire » parmi les **contrôles requis** de `CAP-CORE-017`, et « exception permanente » ainsi qu'« acceptation tacite » parmi ses **risques**. **Le corpus contient, sur son propre audit, l'exemple exact de ce que cette capacité a mission de prévenir.**

## Article 4 — Deux absences qui ne se valent pas

Le Registre des incidents est **ouvert, vide et motivé** : son Article 4 déclare qu'aucun incident n'a été déclaré, son Article 5 écarte expressément, avec leur motif, les difficultés techniques rencontrées. L'Article 53 admettait « registre initial des incidents connus **ou déclaration motivée d'absence** » : la seconde branche est satisfaite.

Le Registre des sauvegardes constate une **redondance de fait** et dit lui-même que ce n'est pas un plan de sauvegarde testé. Il **exclut expressément de sa mission** l'inventaire des sauvegardes techniques réelles, réservé à l'autorité.

Ces deux situations diffèrent de celle des realms, laissée sans registre par `ADOPTION-0053` : ici l'absence est **écrite**, et ce qui est écrit est vérifiable.

---

# TITRE II — LES INCRÉMENTS DU LOT

## Article 5 — Énumération (`INV-51`, Article 163 du Registre des décisions)

Ce que le présent acte n'énumère pas, il ne l'adopte pas.

- **Incrément :** service des risques et exceptions, relevé des niveaux non arbitrés et des acceptations sans échéance ferme. **Commit :** `2c61d09175900e84fa547f09ac28c5d274d295ea`. **Capacité :** `CAP-CORE-017`. **Garde :** `core/registre-risques/tests/risques_p3.php`.
- **Incrément :** service des incidents, distinction de la déclaration motivée d'absence et relevé des non-classifications motivées. **Commit :** `f21b1ce22a94d616db1718b4cdb7b6d7660908ec`. **Capacité :** `CAP-CORE-018`. **Garde :** `core/registre-incidents/tests/incidents_p3.php`.
- **Incrément :** famille `CTR-18`, service de la continuité, refus de franchir l'exclusion de mission. **Commit :** `4f3eb229ab27f30629ee45b462241cc4e1a1dce2`. **Capacité :** `CAP-CORE-019`. **Garde :** `core/registre-continuite/tests/continuite_p3.php`.

Un quatrième commit — `5730b0a902b8f04794e20d5294b1c693007c62fa` — porte les textes communs au lot : la conception conjointe et le Titre XXXIV du Registre initial des capacités. Il n'est pas un incrément et n'est pas énuméré comme tel.

## Article 6 — Les cinq garanties sont entières, et par incrément

| Garantie | `CAP-CORE-017` | `CAP-CORE-018` | `CAP-CORE-019` |
|---|---|---|---|
| Garde de comportement propre | `risques_p3.php` | `incidents_p3.php` | `continuite_p3.php` |
| Contre-épreuve de falsification | Article 13 | Article 13 | Article 13 |
| Titre de constat d'état | Article 210 | Article 210 | Article 210 |
| Ajout seul | aucun texte réécrit | — | — |
| Empreinte exacte | Article 15 | Article 15 | Article 15 |

## Article 7 — Le lot ne contient aucune rectification

Conformément à l'Article 164 du Registre des décisions, le présent lot ne mêle à ses incréments aucune rectification d'un défaut que l'un d'eux aurait introduit.

Deux modifications touchent des gardes existantes : le décompte des familles de contrat passe de dix-sept à dix-huit, et les décomptes par criticité passent de huit à neuf `RACINE` codées et de cinq à sept `CRITIQUE`. Ce sont des **conséquences directes** des incréments du lot, non des rectifications.

---

# TITRE III — CE QUI EST ADOPTÉ

## Article 8 — La conception conjointe et les cinq invariants

`CONCEPTION-DOM-10-RISQUES-INCIDENTS-CONTINUITE-0001` est adoptée.

| Invariant | Énoncé |
|---|---|
| `INV-57` | Une acceptation sans échéance ferme est nommée telle |
| `INV-58` | Un niveau proposé n'est pas un niveau arbitré |
| `INV-59` | Une déclaration motivée d'absence n'est pas une absence d'inventaire |
| `INV-60` | Une redondance de fait n'est pas une sauvegarde éprouvée |
| `INV-61` | Le service ne franchit pas une exclusion de mission |

Menaces retenues : `M-64` à `M-69`.

## Article 9 — La famille `CTR-18`, et la dernière capacité sans contrat

Le Titre XVI de `CORE-ATLAS-0001` crée `CTR-18` — Preuve de sauvegarde et restauration, gardée par `DOM-10`, rattachée à `CAP-CORE-019`.

`CAP-CORE-019` était, après `CAP-CORE-002`, la **seconde et dernière** capacité dépourvue de famille de contrat. **Les vingt capacités portent désormais toutes au moins une famille**, et l'Article 69 de l'Atlas est complet de ses trois omissions.

## Article 10 — Ce que `INV-61` interdit au service, et pourquoi

Le service de `CAP-CORE-019` pourrait techniquement énumérer des dépôts, des artefacts, des emplacements de sauvegarde. **Il ne le fait pas.**

L'Article 4 du Registre des sauvegardes réserve cet inventaire à l'autorité de proposition ; `ADOPTION-0025`, Art. 3.a le range dans son domaine exclusif. Le service restitue `NON INVENTORIÉ — réservé à l'autorité`, avec sa source.

Un service qui franchirait cette frontière « pour être utile » rendrait le corpus faux sur le point même où il se veut le plus strict. C'est la première fois qu'une capacité est **définie par ce qu'elle refuse de voir**, et ce refus est éprouvé par sa garde.

## Article 11 — Ce que le service des risques nomme sans y toucher

Il restitue `RISK-SEC-0001` et `EXC-SEC-0001` comme **sans échéance ferme**, et `RISK-SEC-0002` et `RISK-SEC-0003` comme **non arbitrés**.

Il ne fixe aucun terme, n'en propose aucun, et ne répute aucune acceptation expirée par écoulement du temps. Fixer un terme serait accepter le risque à la place de l'autorité, ce que la Loi 65 interdit.

---

# TITRE IV — PREUVE

## Article 12 — Vérification des gardes

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
| `core/registre-decisions/tests/decisions_p3.php` | `CAP-CORE-008` | `0` |
| `core/registre-contrats/tests/contrats_p3.php` | `CAP-CORE-009` | `0` — dix-huit familles |
| `core/registre-produits/tests/produits_p3.php` | `CAP-CORE-011` | `0` |
| `core/registre-realms/tests/realms_p3.php` | `CAP-CORE-012` | `0` |
| `core/registre-preuves/tests/preuves_p3.php` | `CAP-CORE-015` | `0` |
| `core/registre-risques/tests/risques_p3.php` | `CAP-CORE-017` | `0` — **nouvelle** |
| `core/registre-incidents/tests/incidents_p3.php` | `CAP-CORE-018` | `0` — **nouvelle** |
| `core/registre-continuite/tests/continuite_p3.php` | `CAP-CORE-019` | `0` — **nouvelle** |
| `core/registre-annuaire/tests/annuaire_p3.php` | `CAP-CORE-020` | `0` — décomptes étendus |

Ces dix-sept sorties ont été relevées **une par une**, selon la règle portée par `ADOPTION-0054`, Art. 7.

## Article 13 — Contre-épreuve de falsification (`ADOPTION-0032`, Art. 3)

Une falsification par incrément, sur **copies hors dépôt**, avec témoin non altéré.

| Corpus | Altération | Garde | Sortie |
|---|---|---|---|
| Corpus du dépôt, intact | aucune | les trois | `0` |
| Copie hors dépôt — témoin | aucune | les trois | `0` · `0` · `0` |
| Copie hors dépôt | arbitrage fabriqué pour `RISK-SEC-0002`, avec terme fixe | `CAP-CORE-017` | `1` |
| Copie hors dépôt | déclaration motivée d'absence retirée du Registre des incidents | `CAP-CORE-018` | `1` |
| Copie hors dépôt | exclusion de mission effacée du Registre des sauvegardes | `CAP-CORE-019` | `1` |

Chaque falsification vise l'invariant propre de sa capacité : la promotion d'un niveau proposé pour `INV-58`, la perte du motif d'absence pour `INV-59`, l'effacement de la frontière pour `INV-61`.

Le témoin établit que les échecs procèdent des altérations et non des copies. Le dépôt est demeuré intact pendant les trois épreuves.

---

# TITRE V — EFFETS ET LIMITES

## Article 14 — Effets sur l'état des trois capacités

Les trois passent de conception `À ÉTABLIR` à **`CONÇUE`**, d'implémentation `NON COMMENCÉE` à **`PARTIELLEMENT MATÉRIALISÉE`**, et de preuve `P1` à **`P3 — TESTÉ`**. L'exploitation demeure **`INACTIVE`**.

Le Titre XXXIV du Registre initial des capacités (Articles 207 à 212, ajout seul) porte ce constat.

Décomptes **dérivés du corpus** par `Ctr14::parCriticite()`, selon la règle d'`ADOPTION-0054` :

| Criticité | Total | Codées et éprouvées | Restantes |
|---|---|---|---|
| `RACINE` | 10 | **9** | `CAP-CORE-016` |
| `CRITIQUE` | 10 | **7** | `CAP-CORE-010`, `013`, `014` |
| **Total** | **20** | **16** | **4** |

`CAP-CORE-019` étant `RACINE`, **neuf des dix capacités `RACINE` sont désormais codées et éprouvées** ; seule `CAP-CORE-016` — Gouvernance des secrets et clés ne l'est pas, et elle touche la frontière des accès réservés.

## Article 15 — Ce que cet acte ne fait pas

Il **n'évalue, n'accepte, ne clôt et ne réexamine aucun risque**, et **ne fixe aucune échéance** à `RISK-SEC-0001` ni à `EXC-SEC-0001` : il constate qu'ils n'en ont pas. Il **ne déclare, ne classe et ne clôt aucun incident**. Il **n'atteste aucune sauvegarde** et n'inventorie rien de ce que l'autorité s'est réservé.

Il ne comble ni l'**écart global de sécurité** de l'Article 72, ni l'**écart global de continuité** de l'Article 74.

Il ne rend aucune capacité admise ni active, n'opère aucun déploiement, n'accepte aucun risque nouveau, ne nomme aucun responsable, ne franchit pas la frontière des accès réservés (`ADOPTION-0025`, Art. 3) et **ne constate pas `G0`**.

## Article 16 — Points soumis à l'autorité

1. **L'arbitrage de `RISK-SEC-0002` et `RISK-SEC-0003`**, dont les niveaux sont proposés par un agent artificiel depuis le 26 juillet 2026.
2. **L'échéance de `RISK-SEC-0001` et `EXC-SEC-0001`** : une condition tient-elle lieu d'échéance, ou un terme est-il fixé ?
3. **La compensation technique de `EXC-SEC-0001`**, que le Registre déclare inexistante.
4. La méthode d'évaluation, les seuils et la fréquence de revue des risques (Article 52).
5. La classification des incidents, les délais, les autorités de crise et la politique de communication (Article 53).
6. Les objectifs de reprise, emplacements, rétention et responsabilités, et le premier **exercice de restauration** attendu par l'Article 54.

## Réserve d'audit maintenue

L'acte est rédigé par l'agent, sous une fonction AUDIT non indépendante. Le concepteur ne s'audite pas.

Cette réserve a ici une portée singulière : **le risque `RISK-SEC-0001` que le service de `CAP-CORE-017` restitue est celui de la non-indépendance de l'audit sous lequel ce service est écrit.** L'agent construit l'outil qui nomme le défaut dont il bénéficie.

Deux précautions en découlent, portées au code et non à l'intention. Le service **ne fixe aucun terme** — ce qui lui interdit de paraître clore ce qui le concerne — et **ne promeut aucun niveau proposé**. L'une et l'autre sont éprouvées par la garde et par sa falsification.

## Constat d'exécution

| Chemin | Mise à jour | Empreinte Git après mise à jour |
|---|---|---|
| `genesis-ii/atlas/CORE-ATLAS-0001-atlas-initial-gamad-core.md` | Titre XVI — Articles 133 à 135 (ajout seul) | `dafacb89cfa13d30c313f9d8c00a8416ee87e74d` |
| `genesis-ii/conception/CONCEPTION-DOM-10-RISQUES-INCIDENTS-CONTINUITE-0001.md` | création | `e5c89912e09610a965661a73c4b9b2abe2574d84` |
| `genesis-ii/registres/capacites/REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001.md` | Titre XXXIV — Articles 207 à 212 (ajout seul) | `af54670777baa5ed660b78e3f73edaf8fd528cb0` |
| Incrément 1 — `CAP-CORE-017` | commit | `2c61d09175900e84fa547f09ac28c5d274d295ea` |
| Incrément 2 — `CAP-CORE-018` | commit | `f21b1ce22a94d616db1718b4cdb7b6d7660908ec` |
| Incrément 3 — `CAP-CORE-019` | commit | `4f3eb229ab27f30629ee45b462241cc4e1a1dce2` |
| Textes communs du lot | commit | `5730b0a902b8f04794e20d5294b1c693007c62fa` |
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Article 4 — ligne `ADOPTION-0055` | `2364a7bcb19f30261592d259f559a2396ec1c2d3` |

Ces empreintes remplacent, pour ces fichiers et pour eux seuls, celles déclarées par les actes antérieurs, qui demeurent exactes à leur date. **Aucune ligne ni article préexistant n'a été réécrit ou supprimé.**

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire
- **Date :** 29 juillet 2026 · **Mention :** LU ET ADOPTÉ
