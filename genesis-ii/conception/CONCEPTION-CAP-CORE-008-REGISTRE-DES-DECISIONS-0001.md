# CONCEPTION-CAP-CORE-008-REGISTRE-DES-DECISIONS-0001
## Projet de conception de la capacité souveraine `CAP-CORE-008` — Registre des décisions

> **PROJET NORMATIF — NON SIGNÉ.** Ce texte n'a aucune valeur d'autorité tant qu'un acte d'adoption ne l'a pas adopté et qu'une fusion `--no-ff` dans `main` ne l'a pas mis en vigueur. Il est rédigé par l'agent sous instruction (`ADOPTION-0024`, Art. 3).

## Nature et rattachement

Conception de la capacité `CAP-CORE-008` — Registre des décisions, `DOM-05`, criticité proposée `RACINE`, décrite à l'Article 43 du Registre initial des capacités souveraines.

Elle est présentée conformément à l'Article 63 : invariants, données, contrats, menaces, contrôles et preuves **avant** tout choix technologique.

## Article 1 — Pourquoi cette capacité, et pourquoi maintenant

`CAP-CORE-008` n'appartient pas à l'ensemble racine de l'Article 61. Elle est néanmoins la **seule capacité de criticité `RACINE` non codée** que l'agent puisse construire sans franchir la frontière des accès réservés — `CAP-CORE-016` gouverne les secrets et les clés, `CAP-CORE-019` la sauvegarde et la restauration, l'une et l'autre sur le domaine que `ADOPTION-0025`, Art. 3.a réserve à l'autorité seule.

Le motif principal n'est pourtant pas celui-là. Il est que **ce dépôt produit des décisions et n'en tient pas registre.**

## Article 2 — Trois constats mesurés

Relevés sur `main` à la date du présent projet, par le service lui-même et non par estimation.

**Constat 1 — cinquante décisions formelles, dix-sept inventoriées.** Le dépôt porte cinquante actes d'adoption. L'index de l'Article 4 les porte tous. Le tableau consolidé de l'Article 92 du Registre initial des décisions — celui qui a pour objet de les inventorier — s'arrête à `ADOPTION-0017` et n'a jamais été prolongé. Trente-trois décisions formelles sont hors de la table qui prétend les recenser.

**Constat 2 — trois statuts employés, aucun du vocabulaire adopté.** L'Article 17 arrête quinze états possibles d'une décision. L'index en emploie trois — `LU ET ADOPTÉ — EN VIGUEUR` (quarante-six fois), `ADOPTÉ — EN VIGUEUR` (trois fois), `SIGNÉ — G0 CONSTATÉE` (une fois) — dont **aucun ne figure au vocabulaire de l'Article 17**. La ressemblance est grande ; elle n'est pas l'identité.

**Constat 3 — vingt-cinq décisions réservées à l'autorité, dispersées et non suivies.** Quatorze au Titre XI du Registre des décisions, cinq au Titre XII du Registre des capacités, six posées par les actes du cycle de codage. Elles vivent sous des intitulés qui varient — « Décisions ouvertes », « Points soumis à l'autorité », « Décisions humaines à valider », « Décision réservée ». Rien ne les rassemble, rien ne dit lesquelles ont été tranchées.

## Article 3 — Périmètre

`CAP-CORE-008` **tient le registre** des décisions : elle les inventorie, les confronte à leurs inscriptions, relève leurs statuts et suit celles qui demeurent ouvertes.

Elle ne décide rien, n'adopte rien, ne clôt rien et n'attribue ni classe ni niveau de risque. Ces actes appartiennent à l'autorité (Article 43, décisions ouvertes : classes de décision, quorums, autorités, délais, urgence et contestation).

---

# TITRE I — INVARIANTS

## Article 4 — Numérotation

Les invariants forment une suite unique à l'échelle du Core (`ADOPTION-0032`, Art. 2.2). Le dernier attribué est `INV-45`. La présente conception introduit `INV-46` à `INV-50`.

## Article 5 — `INV-46` — Le registre dérive des actes, il n'en fonde aucun

Le registre des décisions est le **miroir** des actes, jamais leur source. Décider est l'acte de l'autorité ; l'inscription au registre ne fonde, ne valide et ne rend opposable aucune décision.

Un registre qui fonderait ce qu'il inscrit deviendrait une autorité concurrente des actes eux-mêmes, que l'Article 4 du Registre des adoptions déclare prévalents en cas de divergence.

## Article 6 — `INV-47` — Une décision ouverte ne se clôt que par un acte qui la nomme

Ni le silence, ni l'ancienneté, ni l'exécution d'un acte voisin ne closent une décision réservée. Seule une déclaration de clôture, désignant la décision et l'acte qui la tranche, la clôt.

Le motif est à l'Article 7 du Registre initial des décisions, qui exclut l'adoption tacite, et à l'Article 43 du Registre des capacités, qui range le « silence interprété comme accord » parmi les risques propres de cette capacité. Une clôture qui invoquerait un acte absent du dépôt ne clôt rien non plus.

## Article 7 — `INV-48` — Les inventaires sont confrontés, jamais réconciliés

Trois sources décomptent les décisions formelles : les actes présents sur le disque, l'index de l'Article 4, le tableau consolidé de l'Article 92. Le service les **confronte** et restitue leurs écarts chiffrés et énumérés.

Il n'aligne aucune source sur une autre. Prolonger d'office le tableau de l'Article 92 ferait disparaître un écart que l'Article 133 pose expressément en question à l'autorité — et un registre dont les écarts s'effacent tout seuls ne prouve rien.

## Article 8 — `INV-49` — Un statut hors vocabulaire est nommé, jamais traduit

Un statut employé par le corpus qui ne figure pas au vocabulaire de l'Article 17 est restitué **tel quel** et relevé comme hors vocabulaire.

Il n'est jamais remplacé par le terme le plus proche. `LU ET ADOPTÉ — EN VIGUEUR` ressemble à `ADOPTÉE` suivi de `EN VIGUEUR`, et c'est cette ressemblance qui est le piège : traduire ferait dire au corpus ce qu'il n'a pas écrit, et l'écart cesserait d'être visible.

## Article 9 — `INV-50` — Classe et niveau de risque ne sont pas déduits de l'objet

La classe d'une décision est restituée **lorsqu'un texte adopté la porte** — dix-sept adoptions en portent une au tableau de l'Article 92 — et **`NON ÉTABLI`** sinon. Elle n'est jamais étendue à une autre décision par ressemblance d'objet, fût-elle évidente.

Le niveau de risque, le dossier de décision et la contestation demeurent `NON ÉTABLI` pour toutes : aucun texte ne les établit. L'Article 132 réserve la classification à l'autorité, l'Article 139 l'acceptation de risque.

C'est l'application à cette capacité de la doctrine posée par `INV-39` pour les capacités et `INV-45` pour les contrats.

---

# TITRE II — CONTRAT

## Article 10 — Les opérations de `CTR-05`

La famille `CTR-05` — Cycle de décision, gardée par `DOM-05`, est attribuée à `CAP-CORE-008` par l'Article 43. Elle satisfait `INV-40` et demeurait sans producteur depuis que `ADOPTION-0045` l'a rendue à sa capacité titulaire.

Le service expose des opérations de **lecture et d'attestation seulement**.

| Opération | Objet |
|---|---|
| `actes()` | les actes présents au dépôt, groupés par référence |
| `index()` | l'index consolidé de l'Article 4 |
| `consolide()` | le tableau consolidé de l'Article 92, relevé tel quel |
| `decisions()` | l'inventaire confronté sur ses trois termes |
| `resoudreDecision(référence)` | la fiche d'une décision : objet, autorité, date, statut, champs non établis |
| `inscrites()` · `ouvertes()` · `closes()` | les décisions réservées à l'autorité, et leur sort |
| `cloturesSansActe()` | les clôtures invoquant un acte que le dépôt ne porte pas |
| `statutsHorsVocabulaire()` | les statuts employés absents de l'Article 17 |
| `ecarts()` | la synthèse |

## Article 11 — La forme d'une décision ouverte

Une décision réservée n'est pas cherchée dans la prose. **Chercher une décision ouverte dans une phrase, ce serait décider laquelle en est une.**

Elle est inscrite sous une forme dérivable, et close sous une autre, arrêtées par le Titre XIII du Registre initial des décisions. C'est la doctrine que `ADOPTION-0049` a posée pour les attributions de contrat : ce que le service dérive est une forme, jamais une phrase.

## Article 12 — Quatre écarts nommés

| Écart | Risque de l'Article 43 |
|---|---|
| `DÉCISION HORS INVENTAIRE` — décision formelle absente d'une table qui prétend la recenser | état confondu |
| `DÉCISION OUVERTE NON SUIVIE` — réservée à l'autorité, sans acte qui la close | silence interprété comme accord ; clôture prématurée |
| `STATUT HORS VOCABULAIRE` — statut employé absent de l'Article 17 | état confondu |
| `CHAMP NON ÉTABLI` — classe, risque, dossier ou contestation absents du corpus | motifs absents ; décision sans autorité |

Aucun n'est corrigé par le service. Il les nomme.

## Article 13 — Lecture et attestation seulement

Aucune écriture applicative du corpus (`INV-4`). Les fichiers Git demeurent la source de vérité (`INV-5`). L'inventaire est reconstruit à chaque interrogation, sans état conservé.

---

# TITRE III — MENACES

## Article 14 — Menaces retenues

Le dernier numéro attribué est `M-51`. La présente conception retient `M-52` à `M-57`.

| Menace | Énoncé | Traitement |
|---|---|---|
| `M-52` | Une décision réservée est tenue pour acquise faute d'objection | `INV-47` — seule une clôture nommée clôt |
| `M-53` | Une décision ancienne est réputée close par prescription | `INV-47` — l'ancienneté ne clôt rien |
| `M-54` | Un inventaire est aligné d'office sur un autre, l'écart disparaissant | `INV-48` — confrontation sans réconciliation |
| `M-55` | Un statut est traduit vers le terme voisin du vocabulaire adopté | `INV-49` — nommé, jamais traduit |
| `M-56` | Une classe ou un niveau de risque est présumé de l'objet | `INV-50` — `NON ÉTABLI` plutôt qu'une valeur plausible |
| `M-57` | Le registre est confondu avec l'autorité qui décide | `INV-46` — le registre dérive et ne fonde rien |

---

# TITRE IV — CONTRÔLES ET PREUVES

## Article 15 — Preuve `P3` visée, et sa falsification

La capacité vise `P3 — TESTÉ` par une garde de comportement qui lui est propre (`ADOPTION-0035`, Art. 2.2) — la dixième. Une capacité n'hérite pas de la preuve d'une autre.

Conformément à `ADOPTION-0032`, Art. 3, la garde est accompagnée d'une **contre-épreuve de falsification avec témoin** : une copie du corpus hors dépôt, altérée de façon à clore une décision ouverte par un acte inexistant ou à faire disparaître un écart d'inventaire, doit faire échouer la garde ; une copie non altérée doit la faire passer.

## Article 16 — Les gardes demeurent séparées

La garde documentaire Python demeure unique et indépendante de l'application (`ADOPTION-0027`, Art. 4). La présente capacité ajoute une garde de comportement, la dixième, et n'en modifie aucune autre.

---

# TITRE V — CE QUE CETTE CONCEPTION NE FAIT PAS

## Article 17 — Frontières

- Elle **n'arrête ni classe de décision, ni quorum, ni autorité, ni délai, ni régime d'urgence ou de contestation** : l'Article 43 les réserve expressément à l'autorité.
- Elle ne prolonge pas le tableau de l'Article 92 et ne corrige aucun statut employé : elle les confronte.
- Elle ne prétend pas que l'inscription des décisions ouvertes soit **complète**. Aucun texte n'établit l'ensemble des décisions réservées du corpus, et un service qui ne lit qu'une forme ne peut pas découvrir ce qui n'y est pas écrit. Cette limite est elle-même soumise à l'autorité.
- Elle ne mandate aucun **registraire** : l'Article 143 réserve cette fonction à un acte distinct.
- Elle ne rend la capacité ni admise, ni active, et **ne constate pas `G0`**.
- Elle ne franchit pas la frontière des accès réservés (`ADOPTION-0025`, Art. 3).

---

# TITRE VI — RÉSERVE D'AUDIT

## Article 18 — Rappel, et deux précédents immédiats

Le présent texte est rédigé par l'agent, sous une fonction AUDIT non indépendante. Le concepteur ne s'audite pas.

Deux faits récents en donnent la mesure. `ADOPTION-0049` a rapporté un défaut du corpus trouvé par un mécanisme et non par l'agent. `ADOPTION-0050` a rapporté un défaut **de l'agent lui-même**, dans l'acte qu'il venait d'écrire, trouvé par le service qu'il avait écrit la veille.

La précaution portée ici en découle : le service ne lit **aucune prose**. Il lit des formes déclaratives, des tableaux et des noms de fichiers. Ce qui n'est pas écrit sous une forme dérivable est déclaré absent, jamais deviné.

---

# TITRE VII — DÉCISIONS RÉSERVÉES À L'AUTORITÉ

## Article 19 — Points soumis

1. **La complétude de l'inscription** des décisions ouvertes, et le moyen par lequel une décision réservée non inscrite serait découverte.
2. **Les décisions ouvertes de l'Article 43** — classes, quorums, autorités, délais, urgence, contestation — que la présente conception laisse entières.
3. **Le sort du tableau de l'Article 92** : prolongé, déclaré arrêté à sa date, ou remplacé par l'index de l'Article 4.
4. **Les statuts employés hors du vocabulaire de l'Article 17** : le vocabulaire est-il étendu, ou les statuts sont-ils rapprochés ?
5. **Le mandat de registraire**, que l'Article 143 réserve à un acte distinct.

## Article 20 — Non-effet

L'adoption éventuelle de la présente conception ne rend `CAP-CORE-008` ni admise, ni active, n'arbitre aucune des décisions de l'Article 19 ni aucune décision inscrite, n'accepte aucun risque nouveau et ne constate pas `G0`.
