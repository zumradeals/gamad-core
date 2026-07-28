# CONCEPTION-CAP-CORE-009-REGISTRE-DES-CONTRATS-0001
## Projet de conception de la capacité souveraine `CAP-CORE-009` — Registre des contrats

> **PROJET NORMATIF — NON SIGNÉ.** Ce texte n'a aucune valeur d'autorité tant qu'un acte d'adoption ne l'a pas adopté et qu'une fusion `--no-ff` dans `main` ne l'a pas mis en vigueur. Il est rédigé par l'agent sous instruction (`ADOPTION-0024`, Art. 3).

## Nature et rattachement

Conception de la capacité `CAP-CORE-009` — Registre des contrats, `DOM-06`, criticité proposée `CRITIQUE`, décrite à l'Article 44 du Registre initial des capacités souveraines.

Elle est présentée conformément à l'Article 63 : invariants, données, contrats, menaces, contrôles et preuves **avant** tout choix technologique.

## Article 1 — Pourquoi cette capacité, et pourquoi maintenant

L'ensemble racine de l'Article 61 est complet. `CAP-CORE-009` n'en fait pas partie, et l'autorité la retient néanmoins pour la suite immédiate. Le motif est établi par les faits, non par l'ordre du Registre.

Les deux usurpations de famille rectifiées par `ADOPTION-0045` ne procèdent pas d'une inattention isolée. Elles procèdent d'une absence : **la table des familles de contrat vit dans un article de l'Atlas que rien n'oblige à consulter.** `ADOPTION-0032` a attribué `CTR-09` en croyant la numérotation arrêtée à `CTR-08` ; `ADOPTION-0039` a pris `CTR-05` par correspondance de numéro. Deux actes, deux fautes, une seule cause.

`INV-40` empêche désormais la récidive silencieuse : une usurpation est nommée par la garde de `CAP-CORE-020`. Mais nommer une faute n'est pas tenir un catalogue. Le Core sait aujourd'hui **qu'une attribution est fautive** ; il ne sait toujours pas quels contrats existent, qui les produit, qui les consomme, dans quelle version, ni ce qu'il advient de leurs consommateurs quand ils changent.

## Article 2 — Trois constats mesurés

Les faits ci-après sont relevés sur `main`, à la date du présent projet. Ils ne sont pas des estimations.

**Constat 1 — seize familles définies, huit servies, aucune cataloguée.** L'Atlas définit seize familles de contrat. Huit modules servent huit familles. Aucun document ne dit lesquelles sont servies, lesquelles attendent, laquelle n'a aucun titulaire.

**Constat 2 — une famille sans capacité titulaire.** `CTR-09` — Données et droits, gardée par `DOM-07`, n'est revendiquée par aucune des vingt capacités depuis `ADOPTION-0045`. Ce fait est régulier et voulu, l'écart global de données de l'Article 70 le prévoyait. Il n'est inscrit nulle part qu'au détour d'un article.

**Constat 3 — une dépendance entre contrats existe, et aucun texte ne la déclare.** Le service `CTR-04` (`CAP-CORE-007`) délègue la résolution des sources au service `CTR-15` (`CAP-CORE-006`). Cette dépendance est réelle, elle est dans le code, et elle est invisible au corpus. C'est exactement le « contrat implicite » et la « dépendance cachée » que l'Article 44 nomme parmi les risques de cette capacité.

## Article 3 — Périmètre

`CAP-CORE-009` **catalogue** les contrats communs : elle les décrit, les relie à leurs producteurs et consommateurs, et expose ce que le corpus n'établit pas à leur sujet.

Elle ne crée aucun contrat, n'en approuve aucun, n'en déprécie aucun et n'en retire aucun. Ces actes appartiennent à l'autorité (Article 44, décisions ouvertes : formats, protocoles, règles de compatibilité et autorité d'approbation).

---

# TITRE I — INVARIANTS

## Article 4 — Numérotation

Les invariants forment une suite unique à l'échelle du Core (`ADOPTION-0032`, Art. 2.2). Le dernier attribué est `INV-41`. La présente conception introduit `INV-42` à `INV-45`.

## Article 5 — `INV-42` — Le catalogue dérive, il ne crée aucun contrat

Le registre des contrats est **dérivé** du corpus et du code. Il ne fonde aucun contrat, n'en approuve aucun et ne rend aucun contrat opposable par le seul fait de l'inscrire.

Un catalogue qui créerait ce qu'il liste deviendrait une autorité concurrente de l'Atlas, qui définit les familles, et du Registre des capacités, qui les attribue. Il en est le miroir, jamais la source.

## Article 6 — `INV-43` — Un contrat sans producteur est déclaré tel

Une famille définie par l'Atlas et servie par aucun module est restituée **sans producteur**, jamais rattachée par ressemblance au module qui s'en rapprocherait le plus.

Une famille sans titulaire — `CTR-09` aujourd'hui — est restituée **sans titulaire**, et ce fait est un constat, non un défaut.

C'est l'application à cette capacité de la doctrine que `INV-39` a posée pour les champs : l'ignorance déclarée est l'état sûr.

## Article 7 — `INV-44` — Une dépendance entre contrats est observée, jamais déduite

Le lien entre un contrat et ceux qu'il consomme est relevé **dans le code**, par l'usage effectif d'une classe de contrat par un autre module. Il n'est jamais déduit d'une déclaration du corpus.

Le motif est celui que l'Article 44 nomme : un « contrat implicite fondé sur le comportement accidentel d'une implémentation » n'est pas un contrat, mais il est une dépendance réelle. Le catalogue qui ne relèverait que les dépendances déclarées manquerait précisément celles qui font courir un risque.

Une dépendance observée dans le code et absente du corpus est **nommée**, non corrigée (`INV-38`).

## Article 8 — `INV-45` — Version et compatibilité ne sont pas inventées

Aucun texte adopté n'établit à ce jour la version d'un contrat commun, sa politique de compatibilité, sa stratégie d'erreur ni sa procédure de retrait. L'Article 71 du Registre initial des capacités le constate comme écart global.

Tant que l'autorité ne les a pas arrêtées, ces champs sont restitués **`NON ÉTABLI`**. Le service ne propose ni convention par défaut, ni numérotation implicite, ni règle de compatibilité déduite de l'usage.

Un catalogue qui inventerait une version créerait une promesse de compatibilité que personne n'a faite.

---

# TITRE II — CONTRAT

## Article 9 — Les opérations de `CTR-06`

La famille `CTR-06` — Catalogue de contrats, gardée par `DOM-06`, est attribuée à `CAP-CORE-009` par l'Article 44 du Registre initial. Elle satisfait `INV-40` : la capacité garde `DOM-06`.

Le service expose des opérations de **lecture et d'attestation seulement**.

| Opération | Objet |
|---|---|
| `catalogue()` | les familles définies par l'Atlas, avec libellé, domaine gardien et objet minimal |
| `resoudreContrat(famille)` | la fiche d'une famille : titulaires déclarés, producteur observé, consommateurs observés, champs non établis |
| `producteurs()` | pour chaque famille, le module qui la sert et la capacité qu'il déclare servir |
| `consommateurs()` | pour chaque famille, les contrats qui l'utilisent effectivement dans le code |
| `sansProducteur()` | familles définies qu'aucun module ne sert |
| `ecarts()` | synthèse : familles, servies, sans titulaire, dépendances non déclarées, champs non établis |

## Article 10 — Quatre écarts nommés

Chacun répond à un risque que l'Article 44 énumère.

| Écart | Risque de l'Article 44 |
|---|---|
| `CONTRAT SANS PRODUCTEUR` — famille définie, aucun module ne la sert | version non maîtrisée |
| `CONTRAT SANS TITULAIRE` — famille définie, aucune capacité ne la revendique | contrat implicite |
| `DÉPENDANCE NON DÉCLARÉE` — un contrat en consomme un autre sans qu'aucun texte ne le dise | dépendance cachée |
| `CHAMP NON ÉTABLI` — version, compatibilité, stratégie d'erreur ou procédure de sortie absentes du corpus | rupture non annoncée |

Aucun de ces écarts n'est corrigé par le service. Il les nomme (`INV-38`).

## Article 11 — Lecture et attestation seulement

Aucune écriture applicative du corpus (`INV-4`). Les fichiers Git demeurent la source de vérité (`INV-5`). Le catalogue est reconstruit à chaque interrogation, sans état conservé.

---

# TITRE III — MENACES

## Article 12 — Menaces retenues

Le dernier numéro attribué est `M-45`. La présente conception retient `M-46` à `M-51`.

| Menace | Énoncé | Traitement |
|---|---|---|
| `M-46` | Un module consomme un contrat sans qu'aucun texte ne l'indique | `INV-44` — dépendance observée dans le code, nommée |
| `M-47` | Un accès direct aux données contourne le contrat | hors périmètre du catalogue ; relevé comme non traité (Article 15) |
| `M-48` | Un contrat change et ses consommateurs ne l'apprennent pas | `INV-45` — l'absence de politique de compatibilité est déclarée, non comblée |
| `M-49` | Une famille est servie par un module qu'aucune capacité ne rattache | `INV-43` + `INV-41` — producteur observé par déclaration du module |
| `M-50` | Une version est présumée par un outil | `INV-45` — `NON ÉTABLI` plutôt qu'une valeur plausible |
| `M-51` | Le catalogue est confondu avec l'implémentation | `INV-42` — le catalogue dérive et ne fonde rien |

---

# TITRE IV — CONTRÔLES ET PREUVES

## Article 13 — Preuve `P3` visée, et sa falsification

La capacité vise `P3 — TESTÉ` par une garde de comportement qui lui est propre (`ADOPTION-0035`, Art. 2.2). Une capacité n'hérite pas de la preuve d'une autre.

Conformément à `ADOPTION-0032`, Art. 3, la garde sera accompagnée d'une **contre-épreuve de falsification** avec témoin : une copie du corpus hors dépôt, altérée de façon à faire apparaître une dépendance non déclarée ou un contrat sans producteur, doit faire échouer la garde ; une copie non altérée doit la faire passer. L'acte déclarera les trois exécutions.

## Article 14 — Les gardes demeurent séparées

La garde documentaire Python demeure unique et indépendante de l'application (`ADOPTION-0027`, Art. 4). La présente capacité ajoute une garde de comportement, la neuvième, et n'en modifie aucune autre.

---

# TITRE V — CE QUE CETTE CONCEPTION NE FAIT PAS

## Article 15 — Frontières

- Elle **n'arrête ni format, ni protocole, ni règle de compatibilité, ni autorité d'approbation** : l'Article 44 les réserve expressément à l'autorité, et l'agent ne les décide pas.
- Elle ne traite pas le **blocage des accès directs aux bases**, que l'Article 44 range parmi les contrôles requis. Ce contrôle suppose une exploitation, qui est `INACTIVE` pour toutes les capacités. Il est ici **nommé et non traité**, plutôt que prétendu satisfait.
- Elle ne produit pas le **registre initial des contrats** attendu parmi les preuves `G0` : elle produit le service qui le dérive. Le registre initial demeure un document que l'autorité adopte.
- Elle ne rend la capacité ni admise, ni active, et **ne constate pas `G0`**.
- Elle ne franchit pas la frontière des accès réservés (`ADOPTION-0025`, Art. 3).

---

# TITRE VI — RÉSERVE D'AUDIT

## Article 16 — Rappel, et une leçon récente

Le présent texte est rédigé par l'agent, sous une fonction AUDIT non indépendante. Le concepteur ne s'audite pas.

Cette réserve n'est pas formelle. `ADOPTION-0044` a inscrit comme faits quatre collisions dont trois n'existaient pas, et il a fallu une instruction de l'autorité — non un contrôle — pour que l'erreur soit vue. La présente conception est écrite après cet épisode et par le même agent.

Deux précautions en découlent, portées au texte plutôt qu'à la seule intention : les dépendances sont **observées dans le code** et non déduites d'une déclaration (`INV-44`), et tout champ que le corpus n'établit pas demeure **`NON ÉTABLI`** (`INV-45`). L'une et l'autre retirent à l'agent la possibilité de combler un vide par une valeur vraisemblable.

---

# TITRE VII — DÉCISIONS RÉSERVÉES À L'AUTORITÉ

## Article 17 — Points soumis

1. **L'opportunité même de cette capacité à ce stade.** Elle ne figure pas à l'ensemble racine de l'Article 61 ; l'Article 83 réserve l'ordre des priorités à l'autorité.
2. **Le sort de `CTR-09`**, famille définie sans capacité titulaire : demeure-t-elle en attente d'une capacité de gouvernance des données, ou l'autorité entend-elle la traiter autrement ?
3. **Les décisions ouvertes de l'Article 44** — formats, protocoles, règles de compatibilité, autorité d'approbation — que la présente conception laisse entières et déclare non établies.
4. **Le registre initial des contrats** comme document adopté, distinct du service qui le dérive.

## Article 18 — Non-effet

L'adoption éventuelle de la présente conception ne rend `CAP-CORE-009` ni implémentée, ni admise, ni active, n'arbitre aucune des décisions de l'Article 17, n'accepte aucun risque nouveau et ne constate pas `G0`. Elle porte la seule dimension de conception.
