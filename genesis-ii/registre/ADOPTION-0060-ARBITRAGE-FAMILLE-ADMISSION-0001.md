# REGISTRE D'ADOPTION — ADOPTION-0060
## Arbitrage de `DECISION-0044` — l'admission d'une implémentation souveraine relève de `CTR-14`

> **PROJET D'ACTE — préparé sur la branche `agent/genesis-ii-arbitrage-decision-0044`.** Il n'entre en vigueur qu'à la fusion `--no-ff` dans `main`, laquelle **est** l'acte d'adoption et appartient exclusivement à l'autorité (`ADOPTION-0024`, Art. 3).

## Nature

Acte **d'arbitrage**. Il tranche une décision inscrite et la clôt.

C'est le **premier arbitrage rendu dans la forme** que le Titre XIII a arrêtée : une décision inscrite à l'Article 153, tranchée par un acte, close par une ligne de l'Article 154 qui nomme cet acte. Les deux clôtures constatées jusqu'ici — `DECISION-0020` et `DECISION-0024` — avaient été rendues avant que la forme existe, puis rattrapées par elle.

Aucun code n'est livré, aucune garde n'est ajoutée ni modifiée. `CORE-ATLAS-0001` n'est pas modifié.

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté les dispositions ci-après.

---

# TITRE I — L'ARBITRAGE

## Article 1 — Objet

Est adopté le **Titre XVII** — Articles 184 à 190 — ajouté au Registre initial des décisions, qui tranche et clôt `DECISION-0044`.

## Article 2 — La décision

L'admission d'une implémentation d'une capacité souveraine — son inscription, sa mention d'audit et son retrait — relève de la famille **`CTR-14` — Capacité souveraine**, gardien `Transversal`.

## Article 3 — Le motif : l'objet plutôt que le mot

`ADOPTION-0058` avait soumis le point en signalant ce qui le rendait glissant : deux familles portent le mot « admission » pour deux objets, et c'est ce genre de voisinage qui a produit les deux usurpations rectifiées par `ADOPTION-0045`.

L'arbitrage suit l'**objet déclaré** de la famille, non son vocabulaire :

| Famille | Objet déclaré à l'Article 69 de `CORE-ATLAS-0001` |
|---|---|
| `CTR-14` | résoudre mission, **statut**, opérateur, dépendances et **sortie** |
| `CTR-08` | résoudre **admission**, conformité et cycle de vie |

L'admission d'une implémentation est le **statut** d'une capacité ; son retrait est sa **sortie**. `CTR-14` la porte par son objet, quand `CTR-08` ne la porterait que par un mot.

## Article 4 — Le corpus l'avait déjà lu ainsi

L'Article 130 de `CORE-ATLAS-0001` — écrit pour un tout autre motif, l'absence de famille pour les organisations — constate que `CTR-08` « garde `DOM-04`, mais **son intitulé même la restreint aux produits et aux realms** ».

Le présent arbitrage ne crée donc pas cette lecture : il la retient et l'étend au cas qui l'appelait. Une décision qui rejoint une lecture déjà écrite ailleurs dans le corpus vaut mieux qu'une décision qui l'ignore.

## Article 5 — Ce que la clôture couvre

`DECISION-0044` portait **deux membres** : le rattachement, et le sort du mot « admission » présent dans les deux objets. L'Article 154 ne connaît pas de clôture partielle : la clôture prononcée ici couvre les deux.

Le second est réglé par l'Article 186 du Titre XVII, **sans rien retrancher** : le mot demeure dans l'objet de `CTR-08`, borné par son intitulé.

| Objet admis | Famille compétente |
|---|---|
| Implémentation d'une capacité souveraine | `CTR-14` |
| Produit, realm | `CTR-08` |

Les dossiers d'admission des quatre produits, attendus depuis `ADOPTION-0016` et inscrits comme `DECISION-0030`, **demeurent sous `CTR-08`** et ne sont ni déplacés ni traités.

## Article 6 — Une interprétation, non une modification

La décision est une **interprétation** au sens de l'Article 52 du présent Registre : elle explique l'application d'un texte sans le modifier.

`CORE-ATLAS-0001` n'est ni corrigé ni prolongé, la table de son Article 69 demeure telle qu'adoptée, et **aucune empreinte de l'Atlas n'est mise à jour**. Deux familles porteront encore le mot « admission » pour deux objets ; ce qui change est que la distinction est désormais écrite au lieu d'être implicite, donc vérifiable et opposable.

---

# TITRE II — PREUVE

## Article 7 — État dérivé après clôture

Décomptes dérivés par `Ctr05`, selon la règle d'`ADOPTION-0054` :

| | Avant | Après |
|---|---|---|
| Inscrites | 49 | **49** |
| Ouvertes | 47 | **46** |
| Closes | 2 | **3** |
| Clôtures désignant un acte absent | 0 | **0** |

Le nombre d'inscrites ne bouge pas : une clôture s'ajoute à une inscription, elle ne la remplace pas. La ligne d'ouverture de `DECISION-0044` demeure lisible à l'Article 180 (`INV-47`).

## Article 8 — Vérification des gardes

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
| `core/registre-decisions/tests/decisions_p3.php` | `CAP-CORE-008` | `0` — 3 closes, aucune sans acte |
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

## Article 9 — La clôture est éprouvée, non déclarée

`Ctr05::cloturesSansActe()` vérifie qu'une clôture désigne un acte présent au dépôt : une clôture nommant un acte absent ne clôt rien.

Avant que le fichier du présent acte n'existe, ce contrôle relevait `DECISION-0044 → ADOPTION-0060` comme clôture sans acte. Il ne relève plus rien. **La garde de `CAP-CORE-008` a donc éprouvé, dans les deux sens, que cette clôture tient** — c'est la première fois qu'elle le fait sur une clôture prononcée après elle.

## Article 10 — Contre-épreuve de falsification

Aucune contre-épreuve n'est déclarée. `ADOPTION-0032`, Art. 3 l'exige de toute garde livrée au titre d'une preuve `P3` ; le présent acte n'en livre ni n'en modifie aucune.

---

# TITRE III — LIMITES

## Article 11 — Ce qui demeure ouvert

L'arbitrage ne tranche aucune des quatre autres décisions nées de l'Article 11 d'`ADOPTION-0058` :

- `DECISION-0045` — qui prononce l'admission, et qui en répond ;
- `DECISION-0046` — la proportionnalité exigée d'une capacité `RACINE` ;
- `DECISION-0047` — le sens de la complétude au regard de l'objet d'une famille ;
- `DECISION-0048` — la condition de réexamen sous audit non indépendant.

Demeurent également ouvertes `DECISION-0019` — l'ordre de conception et de mise en capacité, dont le point 6 de ce même Article 11 était une espèce —, `DECISION-0043` — ce qui suit `P3` — et `DECISION-0049` — les regroupements du Titre XVI.

**Désigner la famille compétente n'ordonne aucun incrément.** Aucun code n'est commandé et aucun ordre de travaux n'est fixé.

## Article 12 — Non-effet

Le présent acte **n'admet aucune implémentation** et n'en présente aucune à l'admission. Il ne change l'état d'aucune des quatre dimensions, pour aucune des vingt capacités.

Il ne livre aucun code, n'ajoute ni ne modifie aucune garde, ne modifie l'Atlas ni aucune de ses tables, ne crée ni ne supprime aucune famille de contrat, ne nomme aucun responsable, n'accepte aucun risque, ne lève et ne requalifie aucune réserve de `G0`, ne rend l'audit ni indépendant ni suffisant, ne franchit pas la frontière des accès réservés (`ADOPTION-0025`, Art. 3) et **ne constate pas `G0`**.

## Réserve d'audit maintenue

La décision de l'Article 2 est celle de l'autorité. La rédaction qui la porte est celle de l'agent, sous une fonction AUDIT non indépendante — et l'agent est l'auteur de la conception qui a posé la question comme du texte qui la referme.

Le concepteur ne s'audite pas. La précaution retenue ici est de nature vérifiable et non déclarative : le motif de l'arbitrage repose sur **deux textes adoptés antérieurement** — l'objet de `CTR-14` à l'Article 69 de l'Atlas, et la restriction de `CTR-08` que son Article 130 constatait déjà — plutôt que sur le raisonnement de l'agent. Un lecteur peut vérifier la décision sans faire crédit à qui l'a rédigée.

La réserve demeure entière pour le reste.

## Constat d'exécution

| Chemin | Mise à jour | Empreinte Git après mise à jour |
|---|---|---|
| `genesis-ii/registres/decisions/REGISTRE-INITIAL-DECISIONS-0001.md` | Titre XVII — Articles 184 à 190 (ajout seul) | `f89fa24d28b935c7c52d939cdf58eafd548c7a07` |
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Article 4 — ligne `ADOPTION-0060` | `be782a39e83f2e6ba20c1795ab811ed61165ab67` |

Ces empreintes remplacent, pour ces fichiers et pour eux seuls, celles déclarées par les actes antérieurs — `ADOPTION-0059` compris —, lesquelles demeurent exactes à leur date. **Aucune ligne ni article préexistant n'a été réécrit ou supprimé.**

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire
- **Date :** 29 juillet 2026 · **Mention :** LU ET ADOPTÉ
