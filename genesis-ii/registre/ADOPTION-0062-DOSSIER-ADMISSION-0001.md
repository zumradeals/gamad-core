# REGISTRE D'ADOPTION — ADOPTION-0062
## Le dossier d'admission — `CTR-14` assemble, et ne conclut pas

> **PROJET D'ACTE — préparé sur la branche `agent/genesis-ii-dossier-admission`.** Il n'entre en vigueur qu'à la fusion `--no-ff` dans `main`, laquelle **est** l'acte d'adoption et appartient exclusivement à l'autorité (`ADOPTION-0024`, Art. 3).

## Nature

Acte **d'adoption d'un incrément de code**, à un seul incrément — forme régulière (`ADOPTION-0052`).

Il tire la conséquence de code de l'arbitrage rendu par `ADOPTION-0060` : l'admission d'une implémentation souveraine relevant de `CTR-14`, c'est le service de `CAP-CORE-020` qui assemble le dossier que `CONCEPTION-CONTROLE-ADMISSION-0001` décrit — et lui seul.

Aucune famille de contrat n'est créée. `CORE-ATLAS-0001` n'est pas modifié. **Aucune admission n'est inscrite.**

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté les dispositions ci-après.

---

# TITRE I — L'INCRÉMENT

## Article 1 — Objet

Est adopté l'incrément suivant :

- **Incrément :** le dossier d'admission — `Ctr14::admissions()` et `Ctr14::dossierAdmission()`, leur garde et leur restitution au tableau de bord. **Commit :** `5217becb5e54dc0604d0d90dbc06a669aefad538`. **Capacité :** `CAP-CORE-020`. **Garde :** `core/registre-annuaire/tests/annuaire_p3.php`.

L'incrément est porté par **deux commits** : `c354bc7b676af6e149cf8b5cdcef2933b9ffd6bd` livre le service et sa garde ; `5217becb5e54dc0604d0d90dbc06a669aefad538` rectifie l'environnement d'intégration continue, sur un défaut que l'intégration continue elle-même a trouvé avant toute adoption (Article 11). Le commit déclaré est le **second**, qui porte l'incrément entier. Les deux sont nommés plutôt qu'un seul, afin que la rectification demeure lisible et ne se confonde pas avec la livraison.

Est adopté le **Titre XXXVII** — Articles 222 à 227 — ajouté au Registre initial des capacités souveraines, qui constate l'état après cet incrément.

## Article 2 — Les neuf pièces, et les quatre questions

Le service dérive les **neuf pièces** que l'Article 13 de la conception déclare dérivables : identité (capacité, module, famille servie), commit présenté, acte adoptant, garde et son exécution en intégration continue, contre-épreuve de falsification et son témoin, concordance de l'état déclaré et de l'état observé, écarts ouverts, exclusions de mission déclarées, qualité de l'audit.

Il déclare `NON DÉRIVABLE — appréciation humaine` les **quatre questions** que l'Article 14 place hors de portée de tout service : la complétude au regard de l'objet d'une famille, la proportionnalité des contrôles, l'identité du responsable, l'opportunité.

Ces quatre questions sont **déclarées telles et non comblées**. Un service qui les remplirait d'une valeur plausible produirait un dossier apparemment complet et faux — le défaut qu'`INV-39` interdit déjà pour les champs du Registre.

## Article 3 — `INV-72` — le service ne conclut pas

Le dossier ne porte ni avis, ni suffisance, ni proposition d'admission. La garde le vérifie par l'absence : aucun champ nommé `avis`, `admis`, `suffisant`, `recommandation`, `proposition` ou `verdict_admission` n'existe dans ce que le service rend.

**Un dossier complet ne vaut pas admission ; il la rend examinable.** La garde le démontre plutôt que de l'énoncer : dix-neuf dossiers sont complets et aucun n'est recevable, l'implémentation des vingt capacités demeurant `PARTIELLEMENT MATÉRIALISÉE` (`INV-69`).

La retenue n'est pas une modestie de rédaction. C'est la précaution qu'`ADOPTION-0057` avait déjà portée au service d'audit, qui ne prononce aucune levée, et qu'`ADOPTION-0058` avait annoncée à son Article 220 : **le concepteur ne s'audite pas**, et un contrôle d'admission écrit par l'agent ne saurait conclure sur l'ouvrage de l'agent.

## Article 4 — `INV-70` — la qualité de l'audit est consommée, jamais recalculée

La qualité de l'audit est prise de `CAP-CORE-013`, seule capacité dont c'est la mission. `CTR-14` ne la réanalyse pas : deux analyseurs du même fait finiraient par diverger, et le corpus porterait alors deux vérités sur l'indépendance de son propre audit.

`ADOPTION-0061` ayant déclaré l'autorité de décision unique et transitoire, et cette autorité étant le titulaire de `FCT-CORE-021`, **les vingt dossiers portent la mention d'audit non indépendant**. La mention est portée à chaque dossier, non à un préambule : une admission prononcée un jour ne pourra pas être relue comme ordinaire.

## Article 5 — `INV-68` — une admission nomme un commit et ne lui survit pas

Le service ne relève comme admission que ce qui porte la forme de l'Article 174 du Registre initial des décisions. Une admission rédigée en prose n'est pas une admission : c'est la leçon d'`ADOPTION-0059`, où vingt-quatre décisions avaient attendu quatre actes faute de forme.

Dès qu'une admission est inscrite, le service compare le commit admis au commit courant du module et déclare l'admission **CADUQUE** si le module a changé. Aucune admission n'étant inscrite à ce jour, l'état rendu est `AUCUNE ADMISSION INSCRITE` — nommé, et non présumé favorable.

## Article 6 — Le dossier relève les faits sans les arrondir

Un dossier sur vingt est **incomplet** : celui de `CAP-CORE-007`. Son acte adoptant — `ADOPTION-0029` — est antérieur à `ADOPTION-0032`, Art. 3, qui a institué l'exigence de contre-épreuve ; il n'en déclare donc aucune.

La contre-épreuve existe : `ADOPTION-0031` l'a produite. Elle ne se trouve pas là où l'Article 13 la fait chercher. Le service ne va pas la prendre ailleurs, et la garde exige qu'il ne le fasse pas : **aller chercher la pièce dans un autre acte ferait disparaître l'anomalie au lieu de la montrer**, et rendrait le contrôle incapable de voir la même lacune sur un incrément futur.

Cette incomplétude n'est **pas un grief** contre `CAP-CORE-007`, ni un motif de refus d'admission. Elle est un fait daté, restitué comme tel.

---

# TITRE II — PREUVE

## Article 7 — État dérivé des vingt dossiers

Décomptes dérivés par `Ctr14::admissions()` et `Ctr14::dossierAdmission()`, relus du corpus selon la règle d'`ADOPTION-0054` :

| | Constat |
|---|---|
| Admissions inscrites à la forme de l'Article 174 | **0** |
| Dossiers assemblés | **20** |
| Dossiers complets | **19** |
| Dossiers incomplets | **1** — `CAP-CORE-007`, pièce manquante : `contre_epreuve` |
| Capacités recevables à l'admission (`INV-69`) | **0** |
| Dossiers portant la mention d'audit non indépendant (`INV-70`) | **20** |
| Écarts ouverts relevés au dossier, hors ceux de l'annuaire | **0** |

La dernière ligne est vérifiée par la garde et non déclarée : les écarts du dossier sont pris de `Ctr14::ecarts()`, source que l'Article 13 nomme. Un dossier qui tiendrait son propre compte d'écarts présenterait à l'admission un état plus clément que celui que l'annuaire publie.

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
| `core/registre-decisions/tests/decisions_p3.php` | `CAP-CORE-008` | `0` |
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
| `core/registre-annuaire/tests/annuaire_p3.php` | `CAP-CORE-020` | `0` — 43 vérifications, dont 16 ajoutées au titre du dossier |

Ces vingt et une sorties ont été relevées **une par une**, selon la règle portée par `ADOPTION-0054`, Art. 7.

## Article 9 — Contre-épreuve de falsification (`ADOPTION-0032`, Art. 3)

Cinq falsifications, sur **copies hors dépôt**, avec témoin non altéré. Chacune vise un invariant propre du dossier.

| Corpus | Altération | Sortie |
|---|---|---|
| Corpus du dépôt, intact | aucune | `0` |
| Copie hors dépôt — témoin | aucune | `0` |
| Copie hors dépôt | admission fabriquée à la forme de l'Article 174 | `1` |
| Copie hors dépôt | état d'implémentation de `CAP-CORE-016` porté à `IMPLÉMENTÉE NON ADMISE` | `1` |
| Copie hors dépôt | contre-épreuve effacée de l'acte adoptant `ADOPTION-0057` | `1` |
| Copie hors dépôt | exclusion de mission effacée de la conception de `CAP-CORE-016` | `1` |
| Copie hors dépôt | audit déclaré indépendant par le service consommé `CAP-CORE-013` | `1` |

Chaque falsification a fait échouer la vérification qu'elle visait, et non une autre :

| Altération | Vérification tombée |
|---|---|
| admission fabriquée | « aucune admission n'est inscrite, et le service n'en invente aucune » |
| état porté à `IMPLÉMENTÉE NON ADMISE` | « `INV-69` — aucune capacité n'est recevable à l'admission depuis un état partiel » |
| contre-épreuve effacée | « la contre-épreuve et son témoin sont relevés de l'acte adoptant » |
| exclusion de mission effacée | « une exclusion de mission déclarée appartient au périmètre, non aux manques » |
| audit déclaré indépendant | « `INV-70` — la mention d'audit non indépendant est requise » |

La cinquième altère le **service consommé** et non le corpus : c'est la seule manière d'éprouver qu'`INV-70` consomme la qualité de l'audit de `CAP-CORE-013` au lieu de la recalculer. Un service qui la recalculerait aurait passé cette épreuve — et c'est précisément ce qu'il ne doit pas faire.

Le témoin établit que les échecs procèdent des altérations et non des copies. Le dépôt est demeuré intact pendant les épreuves.

## Article 10 — Ce que la contre-épreuve a trouvé

La falsification de l'état d'implémentation **n'a pas échoué du premier coup**. Sa première rédaction visait la dernière mention de `CAP-CORE-020` dans le Registre, laquelle n'est pas une ligne d'état : aucune altération n'était écrite, et la garde passait — non parce qu'elle était aveugle, mais parce que rien n'avait été falsifié.

Le fait est consigné parce qu'il touche la **valeur d'une contre-épreuve** : une falsification qui manque sa cible se lit exactement comme une garde qui ne voit rien. La distinction ne se fait qu'en vérifiant que la vérification tombée est bien celle que l'altération visait — d'où la seconde table de l'Article 9, qui nomme la vérification tombée pour chacune des cinq.

## Article 11 — Ce que l'intégration continue a trouvé, et que la contre-épreuve n'avait pas vu

La garde de `CAP-CORE-020` **a échoué en intégration continue alors qu'elle passait localement, et sur les six copies de la contre-épreuve**.

La cause n'était pas le service. Deux des neuf pièces se dérivent du **dépôt** et non du corpus — le commit qui a introduit le module, et l'acte qui a déclaré cette empreinte. `actions/checkout` clone par défaut à `--depth 1` : sans histoire, ces deux pièces ne sont pas dérivables, le service les a déclarées telles, et les vingt dossiers sont devenus incomplets. **La garde a refusé d'établir la preuve, et elle a eu raison** — un dossier sans commit ne satisfait pas `INV-68`, et le dire vaut mieux que l'inventer (`INV-39`).

La contre-épreuve ne pouvait pas trouver ce défaut : ses copies portaient l'histoire du dépôt, puisqu'elles en étaient des copies intégrales. **Falsifier un corpus n'éprouve pas l'environnement qui l'exécute.**

Deux rectifications, portées par le commit `5217becb5e54dc0604d0d90dbc06a669aefad538` :

- `fetch-depth: 0` au checkout des vingt gardes de comportement ;
- une vérification qui **nomme la cause** — sans elle, vingt dossiers paraissaient incomplets sans raison lisible, et la lecture naturelle eût été d'accuser le code.

Vérifié dans les deux sens sur clone local : `--depth 1` → sortie `1`, cause nommée ; après `git fetch --unshallow` → sortie `0`.

**Ce que cet épisode établit :** `ADOPTION-0035`, Art. 2.2 exige qu'une garde soit exécutée en intégration continue pour valoir preuve `P3`. Le motif s'en vérifie ici — une garde qui n'aurait été lancée que sur le poste du concepteur aurait été déclarée verte et n'aurait rien prouvé de ce que produit une machine qui ne connaît pas le dépôt.

---

# TITRE III — LIMITES

## Article 12 — Ce que cet acte ne tranche pas

Trois des quatre décisions nées de l'Article 11 d'`ADOPTION-0058` demeurent **ouvertes** : `DECISION-0046` — la proportionnalité exigée d'une capacité `RACINE` ; `DECISION-0047` — le sens de la complétude au regard de l'objet d'une famille ; `DECISION-0048` — la condition de réexamen sous audit non indépendant. La quatrième, `DECISION-0045`, a été close par `ADOPTION-0061`.

Décomptes dérivés par `Ctr05` : **49** décisions inscrites, **44** ouvertes, **5** closes, **0** clôture désignant un acte absent.

Deux de ces trois décisions sont, mot pour mot, des questions que l'Article 14 déclare non dérivables. **Le code les rend visibles ; il ne les résout pas**, et l'adoption du présent acte ne vaut arbitrage d'aucune.

## Article 13 — Non-effet

Le présent acte **n'admet aucune implémentation** et n'en présente aucune à l'admission. Il n'inscrit aucune admission, n'en retire aucune, ne rend aucune capacité admise ni active, ne nomme aucun responsable, ne fixe aucune condition de réexamen.

Il ne change l'état d'aucune des quatre dimensions, pour aucune des vingt capacités — `CAP-CORE-020` était `P3 — TESTÉ` et le demeure. Il ne modifie l'Atlas ni aucune de ses tables, ne crée ni ne supprime aucune famille de contrat, n'arbitre aucune divergence, n'accepte aucun risque, ne lève et ne requalifie aucune réserve de `G0`, ne rend l'audit ni indépendant ni suffisant, ne franchit pas la frontière des accès réservés (`ADOPTION-0025`, Art. 3) et **ne constate pas `G0`**.

Il n'ouvre aucun déploiement : le tableau de bord étendu par cet incrément demeure exécutable localement, sans base et sans secret.

## Réserve d'audit maintenue

Le présent acte adopte un **contrôle d'admission écrit par l'agent, qui est l'auteur des vingt services que ce contrôle aura à examiner**. C'est le conflit que `ADOPTION-0058` avait nommé à son Article 220, et que le code ne supprime pas.

La précaution retenue est vérifiable et non déclarative : le service **ne conclut pas** (`INV-72`, éprouvé par l'absence de tout champ de conclusion), la qualité de l'audit lui est **imposée du dehors** (`INV-70`, consommée de `CAP-CORE-013` et éprouvée par falsification de ce service), et le seul dossier incomplet du corpus **demeure incomplet** alors que la pièce manquante existe ailleurs. Un contrôle complaisant aurait comblé ce dernier point sans que personne ne s'en aperçoive.

Ces précautions bornent le service ; elles ne rendent pas l'audit indépendant. `RISK-SEC-0001` demeure entier et sans terme fixe.

## Constat d'exécution

| Chemin | Mise à jour | Empreinte Git après mise à jour |
|---|---|---|
| `genesis-ii/registres/capacites/REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001.md` | Titre XXXVII — Articles 222 à 227 (ajout seul) | `5e17df88d84ede8b307002d043b91b370ce5ff12` |
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Article 4 — ligne `ADOPTION-0062` | `0eea7a1c36bd95f8e5a98ff32c40cea3c5eee180` |

Le code adopté est identifié par son **commit** : `5217becb5e54dc0604d0d90dbc06a669aefad538`, précédé de `c354bc7b676af6e149cf8b5cdcef2933b9ffd6bd` (Article 1).

Ces empreintes remplacent, pour ces fichiers et pour eux seuls, celles déclarées par les actes antérieurs — `ADOPTION-0061` compris —, lesquelles demeurent exactes à leur date. **Aucune ligne ni article préexistant n'a été réécrit ou supprimé.**

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire
- **Date :** 29 juillet 2026 · **Mention :** LU ET ADOPTÉ
