# CONCEPTION-CAP-CORE-006-REGISTRE-DES-SOURCES-0001
## Projet de conception de la capacité souveraine `CAP-CORE-006` — Registre des sources

> **PROJET D'ACTE — soumis à l'autorité de proposition. Non exécuté, non adopté, non signé tant qu'un registre d'adoption distinct (pressenti `ADOPTION-0032`) n'a pas été signé.** Ce document conçoit ; il ne code rien et n'installe rien.

## Nature et rattachement

Le présent acte est l'**étape de conception** de la séquence de l'Article 63 : invariants, données, contrats, menaces, contrôles, migrations et preuves **avant** tout choix technologique. Il raffine la fiche de `CAP-CORE-006` (Article 41 du registre des capacités) sur la doctrine adoptée `SOURCES-0001` et l'inventaire adopté `REGISTRE-INITIAL-SOURCES-0001`.

Il porte en outre trois corrections dues au titre d'actes antérieurs, énoncées à l'Article 6 de `ADOPTION-0031` et délibérément renvoyées à la présente capacité.

Rédigé par SIRR (Claude, `AGENT-IA-002`) sous instruction (`ADOPTION-0024`, Art. 3 : conçoit et vérifie, ne décide ni ne signe).

## Article 1 — Périmètre, en trois objets ordonnés

L'ordre ci-dessous a été arrêté par l'autorité. Il n'est pas l'ordre de facilité : il place la substance de la capacité **en dernier**, parce que la bâtir sur deux défauts connus reviendrait à les rendre structurels.

1. **La commande de réindexation.** Dette contractée par `ADOPTION-0031` : la reconstruction de l'index de production a dû être exécutée par un script écrit hors du dépôt. Une opération dont l'autorité dépend ne peut pas vivre hors du corpus.
2. **La séparation des vocabulaires de statut.** Défaut signalé à l'Article 107 du registre des capacités et **observable en production** : `CAP-CORE-007`, au 26 juillet 2026, est restituée avec le statut `EN CONCEPTION` et le drapeau `en_vigueur: true`. Une capacité en cours de conception est déclarée en vigueur, parce qu'une règle propre aux normes est appliquée à un état de capacité.
3. **Le rang et l'identité fondés.** Substance de `CAP-CORE-006` : le rang d'une norme, aujourd'hui la chaîne littérale `'texte canonique'`, et son identité, aujourd'hui son nom de fichier, doivent procéder des sources reconnues.

---

# TITRE I — INVARIANTS

## Article 2 — Numérotation des invariants

Les invariants du Core forment une **séquence unique**, `INV-1`, `INV-2`, … , à l'échelle du Core entier et non par capacité. Chaque invariant demeure attribué à la capacité qui l'a introduit et cette attribution est inscrite ; le numéro, lui, est unique et **n'est jamais réemployé**, fût-ce après l'abandon de l'invariant qu'il désignait.

Le motif est la citation. Une numérotation propre à chaque capacité obligerait toute citation à porter sa portée — « `INV-3` de `CAP-CORE-007` » — et l'usage abrège toujours. La citation abrégée deviendrait alors fausse. La séquence unique rend chaque citation exacte pour toujours, au prix d'une numérotation qui montera : inconvénient sans gravité, là où l'ambiguïté serait fatale. Le non-réemploi obéit au même motif : un numéro recyclé rendrait menteuses les citations anciennes.

Cette règle formalise l'usage déjà établi — `INV-1`, `INV-4` et `INV-5` sont cités sans mention de portée dans le code adopté (`ADOPTION-0029`, `ADOPTION-0030`) comme dans les documents d'accueil du dépôt. Elle est arbitrée par l'autorité au titre de l'acte qui adopte la présente conception.

## Article 3 — `INV-7` — Identité canonique

Une norme ou une source est identifiée par sa **référence canonique** telle qu'inscrite au registre des sources (`REGISTRE-INITIAL-SOURCES-0001`, Articles 5 et 8) — `SOURCES-0001`, `CORE-LAWS-0001`, `SRC-0003` — et **jamais** par son nom de fichier, son chemin ni son emplacement.

Un fichier peut être déplacé, renommé ou dupliqué sans que l'identité de la norme qu'il porte en soit affectée. Réciproquement, deux fichiers ne peuvent porter la même référence canonique.

*État actuel non conforme :* `Ingestion::referenceDepuisChemin()` dérive la référence du nom de fichier. C'est pourquoi `resoudre_norme('CORE-LAWS-0001')` répond aujourd'hui « norme introuvable » alors que le texte existe, est adopté et est en vigueur.

## Article 4 — `INV-8` — Rang fondé, jamais inventé

Le rang normatif d'une norme procède exclusivement de la hiérarchie de `SOURCES-0001` (Articles 25 à 33) :

| Ordre | Rang | Article fondateur |
|---|---|---|
| 1 | Bloc patrimonial fondateur | `SOURCES-0001`, Art. 25 |
| 2 | Bloc constitutionnel | Art. 26 |
| 3 | Normes organiques | Art. 27 |
| 4 | Lois, invariants et lexique canonique | Art. 28 |
| 5 | Actes, politiques, directives et interprétations | Art. 29 |
| 6 | Décisions d'architecture et contrats | Art. 30 |
| 7 | Spécifications, manuels et procédures | Art. 31 |
| 8 | Implémentations | Art. 32 |
| 9 | Sources de vision (pour les seules dispositions expressément adoptées) | Art. 33 |

Un texte dont le rang ne peut être établi reçoit la valeur `INDETERMINE`. **Le service déclare son ignorance ; il ne présume aucun rang.** Cette règle reprend celle déjà appliquée aux statuts par `ADOPTION-0031`.

## Article 5 — `INV-9` — Authenticité distincte de l'adoption

Le niveau d'authenticité (`AUTH-0` à `AUTH-4`, `SOURCES-0001`, Articles 18 à 22) répond à la question *« cette source est-elle bien ce qu'elle prétend être ? »*. Le statut d'adoption répond à *« cette source fait-elle règle aujourd'hui ? »*. Les deux sont **indépendants** :

- une source peut être `AUTH-3 — AUTHENTIFIÉE` et néanmoins `ABROGE` ;
- une source peut être `AUTH-1 — PROVENANCE DÉCLARÉE` et faire règle, si une autorité compétente l'a adoptée en connaissance de cette réserve ;
- `SRC-0001` (Statuts du Mouvement) est précisément dans ce cas, avec une réserve inscrite sur les signatures.

Confondre les deux est la menace `M-3` (Article 17). Aucune opération du contrat ne doit permettre de déduire l'un de l'autre.

## Article 6 — `INV-10` — Séparation des vocabulaires

Trois vocabulaires distincts coexistent dans le Core et **ne doivent jamais partager une même colonne** :

| Vocabulaire | Valeurs | Porte sur |
|---|---|---|
| Statut de norme | `EN VIGUEUR`, `AMENDE`, `REMPLACE`, `ABROGE`, `INDETERMINE` | un texte |
| État de capacité | `EN CONCEPTION`, `CONÇUE`, `NON COMMENCÉE`, `PARTIELLEMENT MATÉRIALISÉE`, `INACTIVE`, `P1`…`P4` | une capacité souveraine |
| Niveau d'authenticité | `AUTH-0` à `AUTH-4`, `NON ÉTABLI` | une source |

Toute règle dérivée — au premier chef le calcul de `en_vigueur` — s'applique à un seul vocabulaire et refuse de s'appliquer aux autres.

## Article 7 — `INV-11` — Non-effacement de la provenance

Conformément au principe de non-effacement de `SOURCES-0001` (Article 5), une source retirée, remplacée ou déclassée **demeure inscrite**, avec la mention de ce qui l'a remplacée et l'acte qui l'a décidé. Le registre ne perd jamais une provenance ; il en date les changements.

---

# TITRE II — DONNÉES

## Article 8 — Principe

Le modèle demeure un **index dérivé** des fichiers versionnés (`INV-5`) : reconstructible à volonté, jamais autoritatif. Les tables ci-dessous s'ajoutent à celles adoptées par `ADOPTION-0028` sans en modifier la définition, à la seule exception traitée à l'Article 11.

## Article 9 — Rang normatif et source

```sql
-- La hiérarchie de SOURCES-0001, Articles 25 à 33 (Article 4 ci-dessus).
CREATE TABLE rang_normatif (
    code     text PRIMARY KEY,          -- 'R1' … 'R9', 'INDETERMINE'
    libelle  text NOT NULL,
    ordre    integer NOT NULL UNIQUE,   -- 1 = rang supérieur
    article  text NOT NULL              -- article de SOURCES-0001 qui le fonde
);

-- Une source reconnue, au sens de SOURCES-0001.
CREATE TABLE source (
    reference     text PRIMARY KEY,     -- référence canonique (INV-7)
    titre         text NOT NULL,
    categorie     text NOT NULL,        -- SOURCES-0001, Articles 7 à 15
    authenticite  text NOT NULL,        -- 'AUTH-0'…'AUTH-4' | 'NON ÉTABLI'  (INV-9)
    rang_code     text REFERENCES rang_normatif(code),
    chemin        text,                 -- si la source est versionnée ; NULL sinon
    reserve       text                  -- réserve inscrite au registre, conservée telle quelle
);

-- Lignée et provenance, en ajout seul (INV-11).
CREATE TABLE lignee_source (
    id                 …,
    source_amont       text NOT NULL REFERENCES source(reference),
    source_aval        text NOT NULL REFERENCES source(reference),
    nature             text NOT NULL,   -- 'remplace' | 'derive_de' | 'archive'
    adoption_reference text NOT NULL REFERENCES adoption(reference)
);
```

Une source **non versionnée** (`SRC-0001`, statuts transmis sur copie) a `chemin` à `NULL` : elle est reconnue sans être vérifiable par empreinte. Le registre ne doit pas laisser croire qu'elle l'est.

## Article 10 — État de capacité, hors de la table `statut`

```sql
-- Corrige le défaut de l'Article 107 du registre des capacités (INV-10).
CREATE TABLE etat_capacite (
    id                 …,
    capacite_reference text NOT NULL,           -- 'CAP-CORE-007'
    dimension          text NOT NULL
        CHECK (dimension IN ('conception','implementation','exploitation','preuve')),
    valeur             text NOT NULL,
    date_effet         date NOT NULL,
    adoption_reference text NOT NULL REFERENCES adoption(reference)
);
```

Les états de capacité quittent la table `statut`, qui redevient homogène — un statut de norme, et rien d'autre. `en_vigueur` cesse d'être calculé pour les capacités : la question n'a pas de sens pour elles.

## Article 11 — Succession de schéma, et non réécriture

Deux colonnes définies par `ADOPTION-0028` (Titre II) évoluent :

| Table | Avant | Après | Motif |
|---|---|---|---|
| `norme.rang` | texte libre, en pratique `'texte canonique'` | `rang_code` référençant `rang_normatif` | `INV-8` |
| `norme.reference` | nom de fichier | référence canonique du registre des sources | `INV-7` |

Cette évolution emprunte le mécanisme que le corpus applique déjà à ses empreintes : **le Titre II de `ADOPTION-0028` n'est pas réécrit et demeure exact à sa date** ; l'acte qui adopte la présente conception déclare l'état de schéma qui lui succède, dans la seule mesure des deux colonnes décrites. Aucune autre définition n'est touchée.

L'index étant dérivé et reconstruit à chaque ingestion (`INV-5`), la succession ne migre ni ne perd aucune donnée. **Aucune résolution possible avant la succession ne devient impossible après** : la colonne `chemin` demeure inchangée et continue de porter le chemin du fichier, de sorte qu'un texte reste atteignable par son emplacement même si son identité canonique change (`INV-11`).

---

# TITRE III — CONTRATS

## Article 12 — Identification du contrat et opérations

Le contrat attendu de `CAP-CORE-006` est décrit à l'Article 41 du registre des capacités — « résolution de source, vérification d'authenticité, publication de statut et lignée » — sans numéro attribué à ce jour. `CTR-01` à `CTR-08` sont pris ; aucun ne porte les sources. L'autorité lui attribue le numéro **`CTR-09`**.

Elle arrête en outre la règle d'attribution, faute de quoi la vacance persistante des contrats de `CAP-CORE-002` et `CAP-CORE-005` produirait une collision au premier chantier parallèle :

> Les numéros de contrat sont attribués **dans l'ordre chronologique d'adoption de la conception qui les définit**, jamais par correspondance avec le numéro de la capacité qu'ils servent. Un numéro attribué n'est jamais réemployé.

Les trois opérations de `CTR-09` sont les suivantes.

```
resoudre_source(reference, date?)
  → { reference, titre, categorie, authenticite, rang, statut, adoption_reference, reserve }
    • sans 'date' : l'état courant ; avec une date : l'état à cette date.

verifier_authenticite(reference)
  → { authenticite, chemin, empreinte_declaree, empreinte_reelle, concorde, verifiable }
    • 'verifiable' est faux pour une source non versionnée : l'absence de
      vérification est déclarée, jamais silencieuse (INV-9).

resoudre_lignee(reference)
  → { amont[], aval[] }
    • provenance et supersession, en ajout seul (INV-11).
```

## Article 13 — Lecture et attestation seulement

Comme `CTR-04`, ce contrat n'expose **aucune** écriture applicative (`INV-4`). Les seules écritures du corpus passent par des actes d'adoption signés.

## Article 14 — Explicabilité

Toute réponse rattache son résultat à sa preuve : quelle source, quel article du registre, quel acte. Une réponse sans preuve rattachée est un défaut (reprise de l'Article 13 de `CONCEPTION-IMPLEMENTATION-CTR-04-0001`).

---

# TITRE IV — L'OUTIL DE RÉINDEXATION

## Article 15 — Nature et nécessité

La reconstruction de l'index dérivé est une **opération d'exploitation courante** : elle est requise après chaque adoption modifiant le corpus. Elle a été exécutée le 28 juillet 2026 au moyen d'un script rédigé hors du dépôt, ce qui est doublement fautif — l'autorité ne peut pas la relancer sans l'agent, et l'outil n'est couvert par aucune garde.

## Article 16 — Forme

Une commande du cadre adopté (`php artisan registre:reindexer`), livrée dans `apps/console-laravel/`, qui :

- reconstruit l'index par `Ingestion::executer()`, sans réécrire aucun fichier du corpus (`INV-5`) ;
- rend compte du décompte obtenu et des divergences éventuelles ;
- **refuse de s'exécuter** si les deux gardes ne sont pas vertes au préalable — un index reconstruit depuis un corpus incohérent propagerait l'incohérence ;
- sort `0` en cas de succès, non nul sinon, afin d'être utilisable en intégration continue.

Elle ne prend aucun secret en argument : la connexion procède de `DATABASE_URL`, comme le reste du service.

---

# TITRE V — MENACES

## Article 17 — Menaces retenues

Reprises de la fiche `CAP-CORE-006` (Article 41) et rattachées aux invariants qui les couvrent.

| Réf. | Menace | Invariant couvrant |
|---|---|---|
| `M-1` | Source fabriquée, introduite comme authentique | `INV-9` — authenticité déclarée, jamais déduite |
| `M-2` | Copie sans provenance, substituée à l'original | `INV-11` — lignée conservée en ajout seul |
| `M-3` | Confusion rang / statut / authenticité | `INV-9`, `INV-10` — vocabulaires séparés |
| `M-4` | Disparition de l'original non versionné | `chemin NULL` et `verifiable: false` — l'absence est déclarée |
| `M-5` | Attribution abusive au Fondateur | `AUTH-4` réservé, `SOURCES-0001`, Art. 22 — hors de portée du service |
| `M-6` | Identité glissante par renommage de fichier | `INV-7` — identité canonique indépendante du chemin |

`M-5` mérite une mention particulière : aucun mécanisme automatique ne doit pouvoir élever une source au rang `AUTH-4`. Cette classification relève d'une autorité compétente et d'elle seule.

---

# TITRE VI — CONTRÔLES ET PREUVES

## Article 18 — Contrôles

- **Empreintes** — recalculées, jamais recopiées (`INV-1`, déjà tenu par `GitBlob`).
- **Concordance registre / dépôt** — toute source déclarée versionnée dont le fichier est absent est signalée, non ignorée.
- **Complétude du rang** — décompte des normes de rang `INDETERMINE`, exposé et non masqué.
- **Séparation des vocabulaires** — aucune valeur d'un vocabulaire ne doit apparaître dans la colonne d'un autre.

## Article 19 — Preuve `P3` visée, et sa falsification

`CAP-CORE-006` vise `P3 — TESTÉ` (Article 17 du registre des capacités). Instruite par `ADOPTION-0031`, la présente conception pose une exigence que `CAP-CORE-007` n'avait pas :

> **Un test qui ne peut pas échouer ne prouve rien.** Tout test livré au titre de `P3` sera accompagné d'une **contre-épreuve de falsification** : une altération délibérée du corpus, sur copie hors dépôt, dont il sera constaté qu'elle fait échouer le test. L'acte d'adoption déclarera les deux exécutions — celle qui passe et celle qui échoue.

Cas d'essai proposé : `resoudre_source('SOURCES-0001')` restitue `AUTH-3` et le rang constitutionnel ; falsification par modification du niveau d'authenticité au registre, dont il est vérifié qu'elle fait échouer le test.

## Article 20 — Les deux gardes demeurent séparées

Le contrôle Python (`outils/verifier-integrite.py`) n'est ni absorbé, ni réécrit dans le cadre applicatif (`ADOPTION-0027`, Art. 4). La duplication entre `GitBlob` et le contrôle `C5` est **maintenue à dessein** : deux implémentations indépendantes qui concordent valent mieux qu'une seule mutualisée.

---

# TITRE VII — CE QUE CETTE CONCEPTION NE FAIT PAS

## Article 21 — Frontières

- Aucune écriture applicative du corpus (`INV-4`).
- Aucune classification d'authenticité prononcée par le service : il **restitue** ce que le registre déclare, il ne juge pas.
- Aucune réévaluation des sources laissées `NON ÉTABLI` (`GENESIS-003`, `GENESIS-005`, Article 6 du registre des sources) : leur qualification appartient à une autorité compétente (`SOURCES-0001`, Art. 21). Elles sont néanmoins **inscrites au registre et exposées comme `NON ÉTABLI`**, et non omises : une source non qualifiée qui disparaît de l'index peut reparaître plus tard avec un statut présumé, tandis qu'une source déclarée non qualifiée reste visible et interrogeable. L'ignorance déclarée est l'état sûr.
- Aucun franchissement de la frontière des accès réservés (`ADOPTION-0025`, Art. 3.a).

## Article 22 — Ce qui reste hors périmètre

La séparation des vocabulaires corrige le modèle ; elle ne prétend pas fixer le vocabulaire canonique définitif des états de capacité, qui demeure une décision ouverte (Article 80 du registre des capacités).

---

# TITRE VIII — RÉSERVE D'AUDIT

## Article 23 — Rappel, et ce que l'expérience récente y ajoute

La présente conception est rédigée par l'agent qui la vérifiera et la codera, sous une fonction AUDIT non indépendante (`ADOPTION-0025`, Art. 3.b). `ADOPTION-0031` a établi que cette réserve n'est pas théorique : un défaut de raisonnement — non d'exécution — a traversé deux actes sans être relevé par aucune garde, et n'a été levé que par une relecture d'architecture.

L'exigence de contre-épreuve posée à l'Article 19 est la réponse technique à ce constat. Elle ne remplace pas un auditeur indépendant, qui demeure dû.

---

# TITRE IX — DÉCISIONS RÉSERVÉES À L'AUTORITÉ

## Article 24 — Points soumis à l'autorité et arbitrés

Cinq points excédaient le pouvoir de l'agent. Ils ont été soumis à l'autorité, qui les a arbitrés ; l'acte qui adopte la présente conception (`ADOPTION-0032`) en porte le constat motivé. Ils sont ici rappelés dans leur état arrêté :

1. **Adoption de la présente conception** — accordée, après révision incorporant les arbitrages 2 à 5.
2. **Numéro de contrat de `CAP-CORE-006`** — `CTR-09`, assorti de la règle d'attribution chronologique de l'Article 12.
3. **Numérotation des invariants** — séquence unique à l'échelle du Core, sans réemploi (Article 2).
4. **Évolution des deux colonnes de `norme`** — accordée sous forme de **succession de schéma**, `ADOPTION-0028` demeurant exact à sa date (Article 11).
5. **Sources `NON ÉTABLI`** — maintenues en l'état, mais inscrites et exposées comme telles (Article 21).

## Article 25 — Décisions demeurant ouvertes

Ne sont tranchés ni par la présente conception ni par l'acte qui l'adopte : le vocabulaire canonique définitif des états de capacité (Article 80 du registre des capacités), les numéros de contrat de `CAP-CORE-002` et `CAP-CORE-005`, et la qualification d'authenticité de `GENESIS-003` et `GENESIS-005`.

## Article 26 — Non-effet

Le présent acte ne code rien, n'installe rien, ne rend `CAP-CORE-006` ni implémentée ni active, n'accepte aucun risque nouveau, ne modifie le corps d'aucun texte adopté et ne constate pas `G0`.

---

## Autorité d'adoption

- **Nom :** _[réservé à l'autorité de proposition]_
- **Qualité :** _[à compléter]_
- **Date :** _[à compléter à l'adoption]_
- **Registre d'adoption pressenti :** `ADOPTION-0032`
- **Signature :** _[réservée à l'autorité]_

Jusqu'à adoption expresse et inscription au Registre des adoptions, le présent texte demeure :

> **PROJET NORMATIF — EN COURS DE DÉLIBÉRATION**
