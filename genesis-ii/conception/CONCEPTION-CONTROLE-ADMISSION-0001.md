# CONCEPTION-CONTROLE-ADMISSION-0001
## Projet de conception du contrôle d'admission d'une implémentation souveraine

> **PROJET NORMATIF — NON SIGNÉ.** Ce texte n'a aucune valeur d'autorité tant qu'un acte d'adoption ne l'a pas adopté et qu'une fusion `--no-ff` dans `main` ne l'a pas mis en vigueur. Il est rédigé par l'agent sous instruction (`ADOPTION-0024`, Art. 3).

## Nature et rattachement

Conception d'un **contrôle**, non d'une capacité. Aucun `CAP-CORE-0NN` n'en est l'objet ; les vingt le sont également.

Le contrôle conçu ici est celui que l'Article 27 du Registre initial des capacités souveraines exige de **chaque fiche de capacité** — « contrôle d'admission » — et que le corpus n'a jamais défini. La présente conception ne crée donc pas une exigence : elle honore une exigence adoptée le 26 juillet 2026, restée sans contenu depuis.

Elle ne livre aucun code. Elle décrit ce qu'un contrôle d'admission vérifie, ce qu'aucun contrôle ne peut vérifier, et qui prononce.

---

# TITRE I — LE CONSTAT

## Article 1 — L'exigence est écrite une fois, et définie nulle part

L'expression « contrôle d'admission » figure **une seule fois** dans l'ensemble du corpus : à l'Article 27 du Registre initial des capacités souveraines, en tête de la liste des contrôles que chaque fiche doit prévoir.

Les dix autres contrôles de cette même liste — séparation des fonctions, moindre privilège, revue périodique, tests de contrat, journalisation, vérification d'intégrité, sauvegarde, restauration, révocation, sortie — désignent chacun une opération dont le corpus dit ailleurs quelque chose. L'admission, non. Elle est requise et muette.

## Article 2 — Deux états d'implémentation existent, et nul ne les porte

L'Article 14 énumère sept états d'implémentation, dont `IMPLÉMENTÉE NON ADMISE` et `ADMISE`.

L'expression `IMPLÉMENTÉE NON ADMISE` n'apparaît **qu'à cette énumération**, et pas une fois ailleurs dans le corpus. Aucune capacité ne l'a jamais portée. `ADMISE` non plus.

Décompte dérivé de `Ctr14::comparerReel()`, selon la règle d'`ADOPTION-0054` :

| État déclaré | Capacités |
|---|---|
| Implémentation `PARTIELLEMENT MATÉRIALISÉE` | **20 sur 20** |
| Exploitation `INACTIVE` | **20 sur 20** |
| Concordance déclaré / observé | **`CONCORDE` pour 20 sur 20** |

Les deux barreaux hauts de l'échelle sont posés depuis l'origine. **Personne n'y est jamais monté, et aucun texte ne dit comment on y monte.**

## Article 3 — L'empêchement que l'Article 14 invoquait a cessé le 27 juillet 2026

L'Article 14 se clôt ainsi :

> « À la date du présent projet, aucune implémentation Genesis II ne peut être déclarée `ADMISE`, car `G0` n'est pas constatée. »

Cette phrase motive une impossibilité par **un fait daté, et ce fait a changé** : la Porte `G0` est constatée par `ADOPTION-0025`, signée le 27 juillet 2026.

L'obstacle est donc tombé, et la définition n'a jamais été écrite à sa place. Depuis deux jours, l'admission d'une implémentation souveraine est **possible et indéfinie** — l'état le plus dangereux qu'une notion d'autorité puisse prendre, car rien n'empêche plus de l'employer et rien ne dit ce qu'elle engagerait.

## Article 4 — Adopter n'est pas admettre, et le corpus le sait déjà à demi

`INV-4` distingue l'**adoption** de la **publication**. Le corpus n'a jamais eu à distinguer un troisième terme, parce qu'il n'a jamais admis.

Or l'acte qui adopte du code fixe **un texte et une empreinte** : il répond de ce que l'incrément *est*. L'admission répondrait d'autre chose — de ce sur quoi un tiers peut **s'appuyer**, et de qui en porte la charge. Ce sont deux questions distinctes, et vingt incréments adoptés n'ont répondu qu'à la première.

L'Article 61 du Registre initial des décisions tient déjà la moitié de la distinction : « la validation vérifie des exigences définies et **ne devient pas l'adoption** ». Le contrôle d'admission est une validation en ce sens exact.

## Article 5 — Ce que vingt preuves `P3` ne disent pas

L'Article 20 interdit la surévaluation : aucun commit ne vaut preuve complète d'une capacité « sans analyse de périmètre, provenance, conformité et continuité ».

Les vingt gardes prouvent chacune qu'un service honore **son propre contrat tel qu'il l'a écrit**. Aucune ne prouve que ce contrat couvre l'objet de la famille qu'il sert. C'est précisément l'écart que l'état `PARTIELLEMENT MATÉRIALISÉE` déclare — et il le déclare avec exactitude pour les vingt.

**Définir le contrôle d'admission ne rapproche aucune capacité de l'admission.** Il rend seulement mesurable la distance qui l'en sépare.

---

# TITRE II — INVARIANTS

## Article 6 — Numérotation

Les invariants du corpus sont numérotés jusqu'à `INV-66`. La présente conception introduit `INV-67` à `INV-72`.

## Article 7 — `INV-67` — L'admission est un troisième terme

L'adoption fixe un contenu. La publication le rend accessible. L'**admission** déclare qu'une implémentation peut être invoquée par un tiers, et sous quelle responsabilité.

Un acte qui adopte du code n'admet rien par lui-même. Confondre les deux ferait de chaque fusion une admission tacite, et de vingt incréments adoptés vingt implémentations admises que personne n'a jugées.

## Article 8 — `INV-68` — Une admission nomme un commit, et ne lui survit pas

L'admission porte sur une **version identifiée** — un commit — conformément à l'Article 54 du Registre initial des décisions, qui exige d'une décision de conformité qu'elle nomme objet, version, environnement et périmètre.

Le commit suivant qui touche le module admis **n'hérite pas de l'admission**. L'implémentation retombe à `IMPLÉMENTÉE NON ADMISE` jusqu'à une nouvelle admission expresse.

Cet invariant est vérifiable par un service : le commit admis est déclaré, l'état du module est observable, leur écart se dérive. C'est ce qui distingue une admission d'une réputation.

## Article 9 — `INV-69` — Nul ne se présente à l'admission depuis un état partiel

Le passage de `PARTIELLEMENT MATÉRIALISÉE` à `IMPLÉMENTÉE NON ADMISE` est un **constat de complétude**, jamais un constat de qualité. Il précède l'admission et ne la préjuge pas.

La complétude se mesure au regard de **l'objet de la famille de contrat servie**, tel que l'Atlas le définit — non au regard de ce que le service a choisi d'offrir.

Une **exclusion déclarée** fait partie du périmètre et ne compte pas comme manque : que `CTR-20` ne restitue jamais la valeur d'un secret est une borne de mission, non une lacune. Une opération que l'objet de la famille promet et que le service n'offre pas est un **manque**, et l'implémentation demeure partielle.

## Article 10 — `INV-70` — Une admission déclare la qualité de l'audit sous lequel elle est prononcée

L'Article 59 du Registre initial des décisions range la **revue d'audit** parmi les revues que la classe et le risque d'une décision peuvent exiger. `RISK-SEC-0001` constate que la fonction d'audit `FCT-CORE-021` est portée par le titulaire des autres fonctions : elle n'est pas indépendante, et son réexamen n'a **aucun terme fixe**.

Une admission peut néanmoins être prononcée. Le corpus a déjà tranché ce cas de figure pour `G0` : l'Article 15 de `DOSSIER-AUDIT-G0-0001` lève l'écart sur les accès « **par décision documentée, non par résolution technique complète** ». Décider, et écrire ce que la décision ne couvre pas.

Toute admission prononcée sans audit distinct de la production **le mentionne dans son inscription**. Cette mention n'est pas une formalité : elle est la seule chose qui empêche une admission de se lire, dix ans plus tard, comme une admission ordinaire.

Le présent invariant **n'ajoute aucune valeur** à l'énumération close de l'Article 14. L'état demeure `ADMISE` ; c'est l'inscription qui porte la mention.

## Article 11 — `INV-71` — L'admission n'active rien

`ADMISE` est un état d'implémentation. `ACTIVE` est un état d'exploitation, que l'Article 15 subordonne à autorisation, opérateur, contrats, contrôles, surveillance, sauvegarde, restauration et preuves proportionnés.

Une implémentation admise dont l'exploitation demeure `INACTIVE` est un état régulier, et c'est l'état que toute admission produirait aujourd'hui. Le niveau `P4 — OPÉRATIONNEL PROUVÉ` reste hors d'atteinte de l'admission : il exige une exploitation réelle, surveillée et révisée.

## Article 12 — `INV-72` — Le service assemble le dossier et ne conclut pas

Un service peut dériver tout le dossier d'admission décrit au Titre III. Il ne prononce aucune admission, n'en propose aucune et ne qualifie aucun dossier de suffisant.

Le motif est celui qu'`ADOPTION-0057` a porté au code pour le service d'audit, qui **ne prononce aucune levée** : un service écrit par le concepteur ne saurait conclure sur l'ouvrage du concepteur. Il rassemble ce qui permet à un humain de conclure, et s'arrête là.

---

# TITRE III — LE DOSSIER D'ADMISSION

## Article 13 — Ce qui se dérive

Neuf pièces se dérivent du corpus et du dépôt, sans jugement :

| Pièce | Source dérivable |
|---|---|
| Capacité, module, famille servie | `Ctr14::comparerReel()` |
| Commit présenté à l'admission | dépôt |
| Acte qui a adopté cet incrément | index des adoptions |
| Garde propre, et son exécution en intégration continue | `garde`, `garde_en_ci` |
| Contre-épreuve de falsification et son témoin | acte adoptant l'incrément |
| Concordance entre état déclaré et état observé | `verdict` |
| Écarts ouverts touchant la capacité | `Ctr14::ecarts()` |
| Exclusions de mission déclarées par le service | conception adoptée |
| Qualité de l'audit à la date du dossier | `RISK-SEC-0001` |

Un dossier auquel manque l'une de ces pièces est **incomplet**, et l'incomplétude se constate sans débat.

## Article 14 — Ce qui ne se dérive pas

Quatre questions demeurent hors de portée de tout service, et ce sont celles qui décident :

1. La complétude au sens d'`INV-69` — l'écart entre l'objet d'une famille et ce qu'un service en offre suppose de **lire** l'objet, non de le compter.
2. La proportionnalité des contrôles à la criticité (Article 18).
3. L'identité du **responsable** qui répondra de l'implémentation admise.
4. L'opportunité — admettre maintenant, ou attendre.

Un dossier complet ne vaut pas admission. Il rend l'admission **examinable**.

## Article 15 — Forme d'une inscription d'admission

> `- **Admission :** ` + `` `CAP-CORE-0NN` `` + `. **Commit admis :** ` + `` `<empreinte>` `` + `. **Famille :** ` + `` `CTR-NN` `` + `. **Responsable :** <nom>. **Audit :** <indépendant | non indépendant>. **Réexamen :** <condition ou terme>.`

Le retrait d'une admission s'inscrit de la même façon, avec son motif. Une admission qui cesserait sans inscription serait une admission qui ne se retire pas — donc une admission perpétuelle, que l'Article 27 interdit en exigeant révocation et sortie.

---

# TITRE IV — RATTACHEMENT ET SUITE

## Article 16 — La famille de contrat compétente est `CTR-14`

`CTR-14` — *Capacité souveraine*, gardien `Transversal` — a pour objet de « résoudre mission, **statut**, opérateur, dépendances et **sortie** ». L'admission d'une implémentation est le statut d'une capacité ; son retrait est sa sortie. Le rattachement suit l'objet déclaré.

`CTR-08` — *Statut produit ou realm*, gardien `DOM-04` — a pour objet de « résoudre **admission**, conformité et cycle de vie ». Le mot y figure, et la famille ne convient pas : elle admet des **produits et des realms**, non des implémentations de capacités souveraines.

Les deux familles portent le mot « admission » pour deux objets distincts. La conception le signale plutôt que de le taire : c'est le genre de voisinage qui a produit les deux usurpations rectifiées par `ADOPTION-0045`. Le rattachement retenu demeure soumis à l'autorité (Article 23, point 1).

## Article 17 — L'incrément de code qui suivrait

Un service dérivant le dossier de l'Article 13 relèverait de `CAP-CORE-020`, qui sert déjà `CTR-14` et porte déjà `comparerReel()` et `ecarts()`.

Il serait éprouvé par la garde existante de cette capacité, étendue — jamais par une garde nouvelle : `CAP-CORE-020` a sa garde propre, et une capacité n'hérite pas de la preuve d'une autre (`ADOPTION-0035`, Art. 2.2).

Cet incrément **n'est pas adopté par le présent projet** et n'est pas écrit.

## Article 18 — Ordre

La présente conception ne fixe aucun ordre entre l'admission, l'exploitation et l'indépendance de l'audit. L'Article 83 réserve cet ordre à l'autorité.

---

# TITRE V — MENACES

## Article 19 — Menaces retenues

| Menace | Traitement |
|---|---|
| **Admission tacite** — la fusion d'un incrément lue comme une admission | `INV-67` : l'admission est expresse ou n'est pas |
| **Admission qui dérive** — le module change, l'admission demeure | `INV-68` : l'admission nomme un commit et n'y survit pas |
| **Admission par complaisance** — un dossier assemblé et jugé par le même agent | `INV-72` : le service ne conclut pas |
| **Admission qui blanchit** — une décision prise sous audit non indépendant lue plus tard comme ordinaire | `INV-70` : la qualité de l'audit est inscrite |
| **Glissement vers l'exploitation** — `ADMISE` compris comme autorisation de mise en service | `INV-71`, et l'Article 4 qui le disait déjà |
| **Complétude proclamée** — un service déclaré complet parce qu'il passe sa propre garde | `INV-69` : la complétude se mesure à l'objet de la famille |

---

# TITRE VI — CE QUE CETTE CONCEPTION NE FAIT PAS

## Article 20 — Frontières

Elle **n'admet aucune implémentation** et ne présente aucune capacité à l'admission.

Elle **ne change l'état d'aucune des quatre dimensions**, pour aucune des vingt capacités. Les vingt demeurent conception `CONÇUE`, implémentation `PARTIELLEMENT MATÉRIALISÉE`, exploitation `INACTIVE`, preuve `P3 — TESTÉ`.

Elle **n'ajoute aucune valeur** aux énumérations des Articles 13 à 17, ne réécrit le corps d'aucun texte adopté et ne met à jour aucune empreinte déclarée.

Elle **ne nomme aucun responsable**, n'attribue aucune fonction, ne lève et ne requalifie aucune réserve de `G0`, ne rend l'audit ni indépendant ni suffisant, n'opère aucun déploiement, ne franchit pas la frontière des accès réservés (`ADOPTION-0025`, Art. 3) et **ne constate pas `G0`**.

---

# TITRE VII — RÉSERVE D'AUDIT

## Article 21 — L'agent conçoit le contrôle qui jugerait son propre ouvrage

Le projet est rédigé par l'agent sous une fonction AUDIT non indépendante. Le concepteur ne s'audite pas.

La réserve porte ici sur l'objet même du texte : **les vingt implémentations qu'un contrôle d'admission aurait à juger ont toutes été écrites par l'agent qui conçoit ce contrôle.** C'est la troisième fois qu'une conception a pour objet le défaut dont son auteur bénéficie — après `CAP-CORE-017` et `CAP-CORE-013`.

Deux précautions en découlent, portées à la doctrine et non à l'intention. `INV-72` **interdit au service de conclure** : il assemblera le dossier et refusera de le qualifier. `INV-70` **oblige toute admission à déclarer sous quel audit elle est prononcée**, ce qui rendrait visible, dans l'inscription même, que l'agent a conçu le contrôle qui l'admet.

Elles ne suppriment pas la réserve. Aucune conception de l'agent ne le peut.

---

# TITRE VIII — DÉCISIONS RÉSERVÉES À L'AUTORITÉ

## Article 22 — Points soumis

1. **Le rattachement à `CTR-14` plutôt qu'à `CTR-08`** (Article 16), et le sort du mot « admission » présent dans les deux objets.
2. **Qui prononce l'admission** — l'Article 22 du Registre des capacités distingue neuf qualités de gouvernance ; laquelle admet, et laquelle en répond ensuite ?
3. **La proportionnalité exigée** d'une capacité `RACINE` par rapport à une capacité `CRITIQUE` (Article 18).
4. **Le sens exact de la complétude** au regard de l'objet d'une famille (`INV-69`) : appréciation d'espèce, ou grille arrêtée une fois pour toutes ?
5. **La condition de réexamen** d'une admission prononcée sous audit non indépendant : condition, terme fixe, ou aucun.
6. **L'ordre** entre définir l'admission, admettre effectivement, et rendre l'audit indépendant (Article 18).

## Article 23 — Non-effet

L'adoption éventuelle de la présente conception ne rend aucune capacité admise ni active, ne modifie aucun état, ne livre aucun code, n'accepte aucun risque nouveau, ne nomme aucun responsable et ne constate pas `G0`.
