# CONCEPTION-DOM-10-RISQUES-INCIDENTS-CONTINUITE-0001
## Projet de conception du domaine `DOM-10` — `CAP-CORE-017`, `CAP-CORE-018` et `CAP-CORE-019`

> **PROJET NORMATIF — NON SIGNÉ.** Ce texte n'a aucune valeur d'autorité tant qu'un acte d'adoption ne l'a pas adopté et qu'une fusion `--no-ff` dans `main` ne l'a pas mis en vigueur. Il est rédigé par l'agent sous instruction (`ADOPTION-0024`, Art. 3).

## Nature et rattachement

Conception conjointe des trois capacités du domaine `DOM-10` : `CAP-CORE-017` — Registre des risques et exceptions, `CAP-CORE-018` — Registre des incidents, `CAP-CORE-019` — Sauvegarde et restauration souveraines, décrites aux Articles 52, 53 et 54 du Registre initial des capacités souveraines. Criticité `CRITIQUE` pour les deux premières, **`RACINE`** pour la troisième.

Un seul document pour trois capacités qui partagent un domaine et, pour deux d'entre elles, une famille de contrat. Les trois demeurent distinctes : trois modules, trois gardes, trois constats d'état. **La conception est conjointe, la preuve ne l'est pas** (`ADOPTION-0035`, Art. 2.2).

---

# TITRE I — LE CONSTAT

## Article 1 — Ce que le corpus porte déjà, et qu'il n'avait jamais rassemblé

`DOM-10` est le domaine le mieux pourvu en sources adoptées de tous ceux abordés jusqu'ici : quatre registres existent — risques et contrôles, exceptions de sécurité, incidents de sécurité, sauvegardes et restaurations. Aucun service ne les lisait.

Trois faits en ressortent, dérivés et non estimés.

## Article 2 — Deux risques sur trois portent un niveau que nul n'a arbitré

Le tableau de l'Article 5 du Registre des risques inscrit `RISK-SEC-0001` (`S3`), `RISK-SEC-0002` (`S1`) et `RISK-SEC-0003` (`S2`). L'Article 6 précise que ces niveaux sont **proposés à titre provisoire par un agent artificiel**.

Un seul a été arbitré : `RISK-SEC-0001`, par l'Article 1 d'`ADOPTION-0022`. Les deux autres conservent leur niveau provisoire depuis le 26 juillet 2026.

La Loi 65 de `CORE-LAWS-0001` est explicite : l'acceptation du risque n'appartient pas à l'IA. Un niveau proposé par un agent n'est donc pas un niveau du corpus — et rien ne le disait à qui lit le tableau.

## Article 3 — La seule acceptation de risque du corpus n'a pas d'échéance

`RISK-SEC-0001` — l'absence de séparation entre l'audit et les fonctions qu'il devrait auditer — est accepté à titre transitoire. Sa **date de réexamen** est : « dès disponibilité d'une seconde personne de confiance ; **aucun terme fixe** ».

L'exception associée, `EXC-SEC-0001`, porte une durée « transitoire, **sans terme fixe** », un statut de sortie **ouvert**, et déclare qu'**aucun contrôle technique compensatoire distinct n'est constitué à ce jour**.

L'Article 52 range pourtant « échéance obligatoire » parmi les **contrôles requis** de `CAP-CORE-017`, et « exception permanente » ainsi qu'« acceptation tacite » parmi ses **risques**. Le corpus contient donc, sur son propre audit, l'exemple exact de ce que cette capacité a mission de prévenir.

## Article 4 — Deux absences qui ne se valent pas

Le Registre des incidents est **ouvert, vide, et motivé** : son Article 4 déclare qu'aucun incident n'a été déclaré, et son Article 5 écarte expressément, avec leur motif, les difficultés techniques rencontrées.

Le Registre des sauvegardes constate une **redondance de fait** — le dépôt existe sur `origin` et sur un clone local — et dit lui-même que ce n'est pas un plan de sauvegarde testé. Il **exclut expressément de sa mission** l'inventaire des sauvegardes techniques réelles, réservé à l'autorité.

Ces deux situations diffèrent de celle des realms, que `ADOPTION-0053` a laissée sans registre : ici, l'absence est **écrite**, et ce qui est écrit est vérifiable.

---

# TITRE II — INVARIANTS

## Article 5 — Numérotation

Le dernier invariant attribué est `INV-56`. La présente conception introduit `INV-57` à `INV-61`.

## Article 6 — `INV-57` — Une acceptation sans échéance ferme est nommée telle

Une acceptation de risque ou une exception dont le terme est suspendu à un événement incertain **n'a pas d'échéance : elle a une condition**. Le service nomme la différence.

Il ne fixe aucun terme, n'en propose aucun et ne réputé aucune acceptation expirée par écoulement du temps. Fixer un terme serait accepter le risque à la place de l'autorité, ce que la Loi 65 interdit.

## Article 7 — `INV-58` — Un niveau proposé n'est pas un niveau arbitré

Le niveau qu'un agent artificiel a proposé demeure **proposé** jusqu'à ce qu'un acte de l'autorité l'arbitre, et cet arbitrage est restitué avec l'acte qui le porte.

Le service ne promeut jamais un niveau proposé en niveau arrêté, fût-il ancien, vraisemblable ou jamais contesté. Le silence n'arbitre pas.

## Article 8 — `INV-59` — Une déclaration motivée d'absence n'est pas une absence d'inventaire

Un registre présent, ouvert, vide et **motivé** est distingué d'un registre inexistant.

Le motif n'est pas formel : un registre vide et muet laisse ignorer si nul fait n'est survenu ou si nul n'a regardé. L'Article 53 admettait expressément l'alternative — « registre initial des incidents connus **ou déclaration motivée d'absence** » — et le service vérifie laquelle est satisfaite au lieu de les confondre.

## Article 9 — `INV-60` — Une redondance de fait n'est pas une sauvegarde éprouvée

Deux copies d'un dépôt ne constituent pas une sauvegarde. La Loi 44 de `CORE-LAWS-0001` exige vérification d'intégrité et **tests périodiques de restauration** ; sans eux, il y a deux copies, et rien de plus.

Le service ne requalifie jamais une redondance en plan de sauvegarde, et ne tient jamais une preuve `G0` de restauration pour satisfaite par l'existence de copies.

## Article 10 — `INV-61` — Le service ne franchit pas une exclusion de mission

Ce que l'autorité s'est expressément réservé n'est pas inventorié, fût-ce partiellement, fût-ce « pour être utile ».

Le service de `CAP-CORE-019` pourrait énumérer des dépôts, des artefacts, des emplacements. L'Article 4 du Registre des sauvegardes le lui interdit, et `ADOPTION-0025`, Art. 3.a range ce domaine dans l'exclusivité de l'autorité. Le service restitue donc `NON INVENTORIÉ — réservé à l'autorité`, et le déclare avec sa source.

Un service qui franchirait cette frontière rendrait le corpus faux sur le point même où il se veut le plus strict.

---

# TITRE III — DONNÉES ET CONTRATS

## Article 11 — Le partage de `CTR-11` est régulier

`CTR-11` — Risque et incident sert `CAP-CORE-017` et `CAP-CORE-018`, et l'Atlas l'énonce dans son intitulé même : le partage est **régulier** (Article 125 de l'Atlas). Chaque capacité a son module, et chacun déclare la capacité qu'il sert (`INV-41`).

## Article 12 — La famille `CTR-18` — Preuve de sauvegarde et restauration

Créée par le Titre XVI de `CORE-ATLAS-0001`, gardée par `DOM-10`, attribuée à `CAP-CORE-019` par déclaration de rattachement.

`CAP-CORE-019` était, après `CAP-CORE-002`, la **seconde et dernière** capacité dépourvue de famille de contrat. Avec elle, les vingt capacités portent toutes au moins une famille.

## Article 13 — Les opérations

| Capacité | Contrat | Opérations |
|---|---|---|
| `CAP-CORE-017` | `CTR-11` | `risques()`, `resoudreRisque()`, `exceptions()`, `nonArbitres()`, `sansEcheanceFerme()`, `exceptionsOuvertes()`, `sansCompensationTechnique()`, `ecarts()` |
| `CAP-CORE-018` | `CTR-11` | `incidents()`, `declarationAbsence()`, `nonClassifications()`, `champs()`, `ecarts()` |
| `CAP-CORE-019` | `CTR-18` | `redondanceDeFait()`, `exclusionDeMission()`, `testsDeRestauration()`, `champs()`, `ecarts()` |

Lecture et attestation seulement. Aucune écriture applicative du corpus (`INV-4`) ; les fichiers Git demeurent la source de vérité (`INV-5`).

---

# TITRE IV — MENACES

## Article 14 — Menaces retenues

Le dernier numéro attribué est `M-63`. La présente conception retient `M-64` à `M-69`.

| Menace | Énoncé | Traitement |
|---|---|---|
| `M-64` | Une acceptation de risque devient permanente faute d'échéance | `INV-57` |
| `M-65` | Un niveau proposé par un outil est tenu pour arrêté | `INV-58` |
| `M-66` | Un registre vide est lu comme une absence de fait | `INV-59` |
| `M-67` | Une redondance est présentée comme une sauvegarde | `INV-60` |
| `M-68` | Un service inventorie ce que l'autorité s'est réservé | `INV-61` |
| `M-69` | Un fait est écarté de la qualification d'incident sans motif | opération `nonClassifications()` — l'exclusion est restituée avec sa raison |

---

# TITRE V — CONTRÔLES ET PREUVES

## Article 15 — Trois preuves `P3`, jamais une seule

`CAP-CORE-017` et `CAP-CORE-018` partagent `CTR-11` ; elles ne partagent pas leur preuve. Le motif est celui qui valait déjà pour `CTR-08` : **les risques sont inscrits, les incidents ne le sont pas.** Une garde commune aurait établi la première moitié de ce fait et masqué la seconde.

## Article 16 — Falsification

Conformément à `ADOPTION-0032`, Art. 3, chaque garde est accompagnée d'une **contre-épreuve de falsification avec témoin**, sur copie du corpus hors dépôt.

## Article 17 — Les gardes demeurent séparées

La garde documentaire Python demeure unique et indépendante de l'application (`ADOPTION-0027`, Art. 4). Le présent ensemble ajoute trois gardes de comportement, portant leur nombre à seize.

---

# TITRE VI — CE QUE CETTE CONCEPTION NE FAIT PAS

## Article 18 — Frontières

- Elle **n'évalue, n'accepte, ne clôt et ne réexamine aucun risque** : la Loi 65 réserve l'acceptation à l'autorité compétente.
- Elle **ne fixe aucune échéance** à `RISK-SEC-0001` ni à `EXC-SEC-0001` : elle constate qu'ils n'en ont pas.
- Elle **ne déclare, ne classe et ne clôt aucun incident** : l'Article 176 de `SECURITY-GOVERNANCE-0001` réserve la déclaration aux acteurs.
- Elle **n'atteste aucune sauvegarde** et n'inventorie rien de ce que l'Article 4 du Registre des sauvegardes réserve à l'autorité.
- Elle ne comble pas l'**écart global de continuité** de l'Article 74, ni l'**écart global de sécurité** de l'Article 72.
- Elle ne rend les capacités ni admises, ni actives, et **ne constate pas `G0`**.

---

# TITRE VII — RÉSERVE D'AUDIT

## Article 19 — Une réserve qui porte ici sur son propre objet

Le présent texte est rédigé par l'agent, sous une fonction AUDIT non indépendante. Le concepteur ne s'audite pas.

Cette réserve a, dans le présent ensemble, une portée singulière : **le risque `RISK-SEC-0001` que le service de `CAP-CORE-017` restitue est précisément celui de la non-indépendance de l'audit sous lequel ce service est écrit.** L'agent construit l'outil qui nomme le défaut dont il bénéficie.

Deux précautions en découlent, portées au code et non à l'intention. Le service **ne fixe aucun terme** : il constate l'absence d'échéance sans en proposer, ce qui lui interdit de paraître clore ce qui le concerne. Et il **ne promeut aucun niveau proposé** : deux des trois risques inscrits ont été proposés par un agent artificiel, et le service refuse de les tenir pour arbitrés.

---

# TITRE VIII — DÉCISIONS RÉSERVÉES À L'AUTORITÉ

## Article 20 — Points soumis

1. **L'arbitrage de `RISK-SEC-0002` et `RISK-SEC-0003`**, dont les niveaux demeurent proposés par un agent artificiel depuis le 26 juillet 2026.
2. **L'échéance de `RISK-SEC-0001` et `EXC-SEC-0001`** : une condition tient-elle lieu d'échéance, ou un terme est-il fixé ?
3. **La compensation technique de `EXC-SEC-0001`**, que le Registre déclare inexistante à ce jour.
4. **La méthode d'évaluation, les seuils et la fréquence de revue** des risques (Article 52).
5. **La classification des incidents, les délais, les autorités de crise et la politique de communication** (Article 53).
6. **Les objectifs de reprise, emplacements, rétention et responsabilités** de la continuité, et le premier **exercice de restauration** que l'Article 54 attend (Article 74).

## Article 21 — Non-effet

L'adoption éventuelle de la présente conception ne rend les trois capacités ni admises, ni actives, n'arbitre aucun risque, ne fixe aucune échéance, n'atteste aucune sauvegarde, n'accepte aucun risque nouveau et ne constate pas `G0`.
