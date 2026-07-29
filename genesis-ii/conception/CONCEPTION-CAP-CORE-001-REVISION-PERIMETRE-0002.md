# CONCEPTION-CAP-CORE-001-REVISION-PERIMETRE-0002
## Projet de loi révisée de la capacité souveraine `CAP-CORE-001` — Identity Registry

> **PROJET NORMATIF — NON SIGNÉ.** Ce texte n'a aucune valeur d'autorité tant qu'un acte d'adoption ne l'a pas adopté et qu'une fusion `--no-ff` dans `main` ne l'a pas mis en vigueur. Il est rédigé par l'agent sous instruction (`ADOPTION-0024`, Art. 3).

## Nature et rattachement

Révision doctrinale, normative et technique de `CAP-CORE-001`, demandée par l'autorité le 29 juillet 2026.

Elle procède **par ajout seul**. Elle ne réécrit ni l'Article 36 du Registre initial des capacités souveraines, ni le corps de `CONCEPTION-CAP-CORE-001-REGISTRE-DES-IDENTITES-0001.md`, adopté par `ADOPTION-0038`. Les articles de la conception initiale demeurent lisibles, avec la date et l'acte qui les ont établis. Le présent texte **les dépasse en nommant exactement ce qu'il dépasse**, article par article (Titre I, Article 3).

Elle est présentée conformément à l'Article 63 : invariants, données, contrats, menaces, contrôles et preuves **avant** tout choix technologique.

---

# TITRE I — LE CONSTAT

## Article 1 — Ce que la conception initiale a dit, et ce qu'elle n'a pas dit

L'Article 9 de la conception initiale porte : « **Le registre des identités de GAMAD contiendra sept entrées.** Ce chiffre est le fait le plus important de la présente conception : il mesure exactement la distance entre l'état du Core et une exploitation réelle. »

Cette phrase était un **constat sur le corpus**, non une doctrine de périmètre. Elle mesurait une distance ; elle ne prescrivait pas de la conserver. Il faut le dire clairement, parce que la lire comme une règle serait une faute d'interprétation que le présent texte a pour objet de prévenir.

De même, l'Article 5 (`INV-19`) exclut « profil universel, dossiers métier détaillés, réputation globale, jugement moral ou spirituel, agrégation transversale implicite ». Cette exclusion porte sur la **nature des données conservées**. Elle ne porte pas sur la **nature des entités reconnues**, et n'a jamais interdit de reconnaître une personne qui utilise un produit.

## Article 2 — Ce qui, en revanche, restreint réellement le périmètre

Deux dispositions de la conception initiale, et deux seulement, ferment le registre aux utilisateurs des produits :

1. **Article 11 — Lecture seule.** « Aucune écriture (`INV-4`). Créer, corriger, fusionner ou clore une identité demeure un acte signé. » Un acte signé par identité rend l'inscription à l'échelle matériellement impossible.
2. **Article 13 — `M-19`.** « Aucun dispositif technique ne peuple un registre d'identités : seuls des actes le font. » La proposition est vraie du corpus documentaire ; elle est fausse d'un registre d'identités souverain, où l'acte doit fonder la **politique d'inscription**, non chaque inscription.

Ce sont ces deux dispositions que la présente révision dépasse. Toutes les autres sont **maintenues sans réserve**.

## Article 3 — Tableau de ce qui est dépassé, et de ce qui demeure

| Disposition de `CONCEPTION-CAP-CORE-001-…-0001` | Sort |
|---|---|
| Article 1 — filiation de `PRD-GAMAD-001`, identité rendue au Core | **Maintenu** |
| Article 3 — `INV-17`, identifiant stable jamais réattribué | **Maintenu**, et étendu à toute référence inscrite |
| Article 4 — `INV-18`, type déclaré, jamais présumé | **Maintenu**, liste close étendue par l'Article 12 ci-dessous |
| Article 5 — `INV-19`, minimalité et données exclues | **Maintenu sans réserve** — c'est le cœur de la capacité |
| Article 6 — `INV-20`, assurance distincte de l'existence | **Maintenu**, et rendu opérant par l'Article 16 |
| Article 7 — `INV-21`, cycle de vie en ajout seul | **Maintenu**, et étendu aux relations |
| Article 9 — sept entités dérivables du corpus | **Maintenu comme constat daté** ; cesse d'être une mesure du périmètre |
| Article 11 — lecture seule, un acte signé par identité | **Dépassé** par les Articles 8 et 17 ci-dessous |
| Article 13 — `M-19`, seuls des actes peuplent le registre | **Dépassé** par l'Article 8 ci-dessous |
| Articles 15 et 16 — frontières avec `CAP-CORE-004` et `CAP-CORE-005` | **Maintenus et renforcés** |
| Article 17 — réserve d'audit non indépendante | **Maintenu** — inchangé |

## Article 4 — Le fait que la révision corrige

Un utilisateur de Wasplex, de GamaDrive, de G-Search, de G-Business, de G-Mail, de G-Docs ou de Zumra n'a, en l'état, aucune existence pour le Core. Chaque produit doit donc créer sa propre identité souveraine locale.

Les conséquences sont les dix menaces du Titre VI. La plus grave n'est pas la duplication : c'est la **capture**. Un produit qui détient seul l'identité de ses utilisateurs détient une fondation souveraine du Core, et sa sortie de l'écosystème emporterait ces identités. L'Article 21 de l'Atlas — non-capture — est directement en cause.

---

# TITRE II — DOCTRINE RÉVISÉE

## Article 5 — Principe fondamental

> **Toute personne, organisation, agent, produit, service ou realm reconnu comme acteur durable de l'écosystème GAMAD peut recevoir une référence canonique stable de l'Identity Registry.**

## Article 6 — Formule de partage

> **Le produit connaît l'usage. L'organisation connaît sa structure. Le Core connaît l'identité.**

Les produits possèdent leurs données métier. Le Core possède la référence canonique et la continuité de l'identité. Aucune des deux propositions ne cède devant l'autre.

## Article 7 — L'utilisateur n'est pas une catégorie d'identité

`utilisateur` désigne une **relation d'usage** entre une entité et un produit. Ce n'est ni un type d'entité, ni une identité concurrente. Une personne est une personne ; qu'elle utilise trois produits lui donne trois relations, non trois identités.

Cette distinction n'est pas terminologique. Elle est ce qui empêche qu'un produit, en fermant un compte, ferme une identité.

## Article 8 — L'inscription est gouvernée par une politique, non par un acte individuel

> **Toute création d'identité dérive d'une autorité d'inscription et d'un événement de preuve vérifiable. Elle n'exige pas un acte normatif individuel.**

L'acte signé demeure requis — mais il porte sur la **politique d'inscription** : quels canaux inscrivent, pour quels types, sous quel niveau d'assurance initial, avec quelles preuves exigées, sous quel responsable. Une fois la politique adoptée, l'inscription qui s'y conforme est régulière sans nouvel acte.

C'est le déplacement décisif de la présente révision, et il dépasse l'Article 11 et le `M-19` de la conception initiale.

## Article 9 — La minimalité n'est pas assouplie

L'ouverture du périmètre aux personnes et aux organisations **n'ouvre rien d'autre**. `INV-19` demeure entier : ni profil universel, ni dossier métier, ni réputation, ni jugement moral ou spirituel, ni agrégation transversale implicite.

Reconnaître plus d'entités et conserver moins de données sur chacune n'est pas une contradiction : c'est la définition même d'un registre d'identités souverain, par opposition à un profil.

---

# TITRE III — INVARIANTS

## Article 10 — Numérotation

Les invariants forment une suite unique à l'échelle du Core (`ADOPTION-0032`, Art. 2.2). Le dernier attribué est `INV-72`. La présente révision introduit `INV-73` à `INV-80`.

## Article 11 — `INV-73` — Deux régimes de vérité, jamais mêlés

C'est l'invariant le plus important de la présente révision, et le plus facile à enfreindre sans s'en apercevoir.

`INV-5` établit que la base est un **index dérivé, jamais autoritatif** : les fichiers Git sont la source de vérité. Cela reste exact pour les entités que le corpus déclare — `AUT-GAMAD-001`, `AGENT-IA-001`, `AGENT-IA-002`, `PRD-GAMAD-001` à `004`. Leur inscription est reconstructible depuis le corpus, et doit le demeurer.

Cela ne peut pas être exact pour une personne inscrite par un produit : **aucun fichier du corpus ne la déclare, et aucun ne la déclarera**. Pour cette classe d'entités, le registre est la source de vérité.

L'invariant est donc :

> **Toute entité porte un régime de vérité déclaré — `DÉRIVÉ_DU_CORPUS` ou `INSCRIT_AU_REGISTRE` — et les deux régimes ne se mêlent jamais.**

Conséquences tenues par la structure, non par la discipline :

- une entité `DÉRIVÉ_DU_CORPUS` est reconstruite à chaque réindexation ; toute divergence entre la base et le corpus est un défaut de la base, et le corpus l'emporte ;
- une entité `INSCRIT_AU_REGISTRE` n'est **jamais** produite par la réindexation, et une réindexation qui l'effacerait détruirait la source de vérité ;
- aucune opération ne convertit un régime en l'autre. Une entité inscrite ne devient pas dérivée parce qu'un texte finit par la nommer : le texte crée alors une entité distincte, que l'Article 20 permet de rapprocher.

`INV-5` n'est pas abrogé. Il est **borné à son domaine de vérité**, et l'était implicitement depuis l'origine ; la présente révision rend cette borne explicite parce qu'elle cesse d'être théorique.

## Article 12 — `INV-74` — Universalité de la référence, et type déclaré

Toute entité durable de l'écosystème peut recevoir une référence canonique du Core. Elle porte un type déclaré, pris dans une liste close étendue à : `personne`, `organisation`, `produit`, `realm`, `agent`, `service`. Un type non déterminable donne `INDETERMINE` ; il n'est jamais présumé.

La liste n'est pas allongée. `INV-18` la fixait déjà, et elle suffisait : un utilisateur est une `personne`, une entreprise annonceuse est une `organisation`. Ce n'est pas la liste des types qui fermait le registre, c'est le mode d'inscription.

## Article 13 — `INV-75` — Un produit ne crée pas d'identité souveraine

Un produit détient un **compte local** : identifiant local, préférences, abonnement, paramètres, historique métier. Il ne détient pas d'identité souveraine concurrente à celle du Core.

Un compte local est rattaché à une référence canonique par une relation gouvernée. Le produit conserve, de son côté, une correspondance `sujet_local_opaque → reference_canonique`, et rien d'autre du Core.

## Article 14 — `INV-76` — Le compte est distinct de l'identité

La suppression, la suspension ou la clôture d'un compte de produit ne supprime, ne suspend et ne clôt **jamais** l'identité canonique. Elle clôt la relation, qui demeure lisible avec sa date de fin.

La dissolution d'un produit entier ne fait pas exception. `PRD-GAMAD-001` en est la démonstration déjà acquise : le produit est dissous, la référence subsiste.

## Article 15 — `INV-77` — Toute relation est explicite, datée et classée

Une relation entre une identité et un produit, ou entre une identité et une organisation, porte : un type, un état, une source, une date de début, une date de fin éventuelle, un niveau d'assurance et une classification.

Aucune relation n'est présumée. Une personne liée à une organisation n'en est pas le représentant : la représentation relève de `CAP-CORE-003`, et le registre ne la déduit d'aucun lien.

Une relation expirée n'est jamais restituée comme active. Le cycle de vie des relations est en ajout seul, comme celui des identités (`INV-21`).

## Article 16 — `INV-78` — L'assurance ne se déduit d'aucun usage

Le niveau d'assurance d'une identité ne se déduit ni du nombre de produits utilisés, ni de l'ancienneté, ni du volume d'activité, ni de la popularité. Il procède exclusivement d'un événement de preuve produit par `CAP-CORE-005`.

C'est `INV-20` rendu opérant. Tant que l'existence seule était en jeu, la distinction était théorique. Dès lors qu'un produit peut inscrire, la tentation de tenir l'usage pour une preuve devient réelle, et l'invariant doit l'interdire par la structure : le champ `niveau_assurance` n'est écrit par aucun chemin autre qu'un événement d'assurance.

## Article 17 — `INV-79` — Écriture gouvernée, jamais libre

Les commandes d'écriture introduites par la présente révision ne constituent en aucun cas un CRUD. Toute commande :

1. identifie l'autorité d'inscription et vérifie sa compétence pour le type demandé ;
2. nomme la politique d'inscription qui l'autorise ;
3. produit un événement de cycle de vie horodaté, en ajout seul ;
4. journalise sa source, son canal et son producteur ;
5. refuse tout type, toute relation et toute classification non autorisés par la politique ;
6. laisse une preuve vérifiable par `CAP-CORE-015`.

Une commande qui ne satisfait pas ces six conditions ne s'exécute pas. Le refus est la position par défaut (Article 19 de l'Atlas).

`INV-4` — adoption distincte de la publication — demeure entier : l'adoption d'une politique n'est pas son exécution, et l'exécution d'une politique n'adopte rien.

## Article 18 — `INV-80` — Aucune fusion sur une probabilité

Un doublon probable est **signalé**, jamais fusionné automatiquement. Le rapprochement de deux références est une opération gouvernée qui exige des preuves énumérées et une décision ; il produit un événement de fusion, et l'histoire des deux références demeure lisible.

Une identité provisoire ne reçoit jamais une assurance forte du seul fait qu'elle a été rapprochée.

Cet invariant traite `M-ID-D` et, en creux, la faute la plus lourde qu'un registre d'identités puisse commettre : confondre deux personnes.

---

# TITRE IV — DONNÉES

## Article 19 — Tables

Le modèle ci-dessous étend le modèle de l'Article 8 de la conception initiale. Les trois tables existantes — `entite`, `etat_entite`, `denomination` — sont **conservées telles quelles** ; `entite` reçoit les colonnes que l'Article 20 énumère.

```sql
-- Colonnes ajoutées à la table `entite` de l'Article 8 initial.
-- Aucune colonne existante n'est modifiée ni supprimée.
ALTER TABLE entite ADD COLUMN regime_verite       text NOT NULL; -- INV-73
ALTER TABLE entite ADD COLUMN niveau_assurance    text NOT NULL; -- INV-78
ALTER TABLE entite ADD COLUMN politique_inscription text NULL;   -- INV-79, NULL si dérivé
ALTER TABLE entite ADD COLUMN classification      text NOT NULL; -- Titre VIII

-- Relation d'usage entre une identité et un produit. En ajout seul (INV-77).
CREATE TABLE relation_produit (
    id                 …,
    entite_reference   text NOT NULL REFERENCES entite(reference),
    produit_reference  text NOT NULL REFERENCES entite(reference),
    relation_type      text NOT NULL,   -- liste close, Article 21
    etat               text NOT NULL,
    sujet_local_opaque text NULL,       -- opaque au Core, jamais interprété
    niveau_assurance   text NOT NULL,   -- INV-78 : jamais déduit de la relation
    source             text NOT NULL,
    date_debut         date NOT NULL,
    date_fin           date NULL,
    classification     text NOT NULL
);

-- Relation entre une identité et une organisation. En ajout seul (INV-77).
CREATE TABLE relation_organisation (
    id                     …,
    entite_reference       text NOT NULL REFERENCES entite(reference),
    organisation_reference text NOT NULL REFERENCES entite(reference),
    relation_type          text NOT NULL,  -- liste close, Article 21
    etat                   text NOT NULL,
    mandat_reference       text NULL,      -- CAP-CORE-003 ; jamais déduit
    source                 text NOT NULL,
    date_debut             date NOT NULL,
    date_fin               date NULL,
    classification         text NOT NULL
);

-- Cycle de vie, en ajout seul (INV-21, INV-79). Aucun UPDATE, aucun DELETE.
CREATE TABLE evenement_cycle (
    id                    …,
    entite_reference      text NOT NULL REFERENCES entite(reference),
    evenement_type        text NOT NULL,  -- liste close, Article 22
    etat_avant            text NULL,
    etat_apres            text NOT NULL,
    source                text NOT NULL,
    politique_inscription text NULL,
    acteur_reference      text NOT NULL,  -- qui a inscrit, jamais anonyme
    date_effet            date NOT NULL
);

-- Rapprochement proposé entre deux références. Jamais exécuté d'office (INV-80).
CREATE TABLE rapprochement (
    id            …,
    reference_a   text NOT NULL REFERENCES entite(reference),
    reference_b   text NOT NULL REFERENCES entite(reference),
    preuves       text NOT NULL,   -- énumérées, jamais un score seul
    etat          text NOT NULL,   -- PROPOSE | VALIDE | REJETE
    decideur      text NULL,       -- NULL tant que l'état est PROPOSE
    date_effet    date NOT NULL
);
```

Aucune colonne de profil, de contenu métier, de réputation ou de jugement n'est prévue. L'exclusion d'`INV-19` demeure tenue par la structure.

## Article 20 — Ce que la table `entite` ne reçoit pas

Elle ne reçoit ni adresse, ni date de naissance, ni coordonnées, ni préférences, ni photographie, ni score, ni catégorie commerciale. Ces données appartiennent aux produits.

L'Identity Registry sait qu'une entité existe, sous quelle référence, de quel type, dans quel état, avec quel niveau d'assurance, par quelle politique, avec quels liens minimaux. Il ne sait rien d'autre, et l'absence de colonnes est la seule garantie qui tienne.

## Article 21 — Listes closes des types de relation

**Relation à un produit :** `UTILISATEUR`, `CLIENT`, `ANNONCEUR`, `ADMINISTRATEUR`, `OPERATEUR`, `RESPONSABLE_PRODUIT`, `PROPRIETAIRE_INSTITUTIONNEL`, `PARTENAIRE`.

**Relation à une organisation :** `MEMBRE`, `EMPLOYE`, `DIRIGEANT`, `REPRESENTANT`, `BENEFICIAIRE`, `CLIENT`, `FOURNISSEUR`, `PARTENAIRE`, `CONTACT_AUTORISE`.

`REPRESENTANT` et `DIRIGEANT` sont inscriptibles mais **jamais opposables** sans mandat vérifié par `CAP-CORE-003`. Le registre restitue la relation et l'absence de mandat séparément ; il ne présente pas la première comme valant la seconde.

## Article 22 — Liste close des événements de cycle de vie

`CREATION`, `VERIFICATION`, `SUSPENSION`, `REACTIVATION`, `FUSION`, `SCISSION`, `CLOTURE`, `DISSOLUTION`, `CORRECTION`, `CONVERSION_PROVISOIRE`, `RATTACHEMENT_PRODUIT`, `RETRAIT_PRODUIT`, `RATTACHEMENT_ORGANISATION`, `RETRAIT_ORGANISATION`.

## Article 23 — Identités provisoires et pseudonymes

Une **identité provisoire** est une identité de plein droit, portant une référence stable et un niveau d'assurance faible. Elle n'est pas un brouillon : elle est une identité dont la preuve est faible, ce qui est un fait, non un défaut.

Une **identité pseudonyme** déclare son contexte, son producteur, sa durée, son niveau d'assurance et les règles de levée ou de non-levée. Une identité pseudonyme dont les règles de levée ne sont pas déclarées n'est pas inscriptible.

Ni l'une ni l'autre n'autorise une action sensible du seul fait de son existence : c'est l'affaire de `resoudreEtatUtilisable` (Article 25).

---

# TITRE V — CONTRAT `CTR-01`, DEUXIÈME VERSION

## Article 24 — Les trois opérations existantes sont conservées

`resoudreIdentite(reference, date?)`, `resoudreInventaire(type?)` et `resoudreDenominations(reference?)` sont conservées **sans changement de signature ni de sémantique**. Un consommateur existant n'a rien à modifier.

L'Article 73 de l'Atlas exige que toute évolution de contrat documente compatibilité, coexistence, migration, délai de dépréciation, consommateurs affectés et condition de retrait. La présente version étant **strictement additive**, aucun consommateur n'est affecté et aucune dépréciation n'est prononcée.

## Article 25 — Opérations de lecture ajoutées

```
resoudreLiensProduits(reference, produit?, relationType?)
  → [ { produit_reference, relation_type, etat, assurance, date_debut, date_fin } ]

resoudreLiensOrganisations(reference, organisation?, relationType?)
  → [ { organisation_reference, relation_type, etat, mandat_reference, date_debut, date_fin } ]
    • `mandat_reference` vaut null lorsqu'aucun mandat n'est vérifié.
      Le service ne présente alors jamais la relation comme une représentation.

resoudreIdentiteDepuisSujetProduit(produit, sujetLocalOpaque)
  → { reference, etat, assurance } | null
    • Réservée au produit concerné ou à une autorité habilitée (Article 30).

resoudreAssurance(reference, contexte?)
  → { niveau, source, date_effet, evenements[] }

resoudreEtatUtilisable(reference, finalite)
  → { utilisable: bool, motif, exigences_non_satisfaites[] }
    • Répond à « cette identité peut-elle servir à CETTE fin ? »,
      jamais à « cette identité est-elle de bonne qualité ? ».

resoudreRapprochementsProposes(reference?)
  → [ { reference_a, reference_b, preuves[], etat } ]
    • SIGNALE les doublons probables. Ne fusionne rien (INV-80).

resoudreRegimeVerite(reference)
  → { regime, reconstructible: bool, source }   -- INV-73
```

## Article 26 — Commandes d'écriture ajoutées

```
inscrireIdentite(dossier)
  → { reference, etat, assurance } | REFUS { motif }

rattacherProduit(reference, produit, relationType, preuve)
  → { relation } | REFUS { motif }

rattacherOrganisation(reference, organisation, relationType, preuve)
  → { relation } | REFUS { motif }

cloreRelation(relation, dateFin, motif)
  → { relation }        -- ne touche jamais l'identité (INV-76)

proposerRapprochement(referenceA, referenceB, preuves[])
  → { rapprochement, etat: 'PROPOSE' }   -- jamais 'VALIDE' (INV-80)

inscrireEvenementAssurance(reference, niveau, preuve)
  → { assurance }       -- seul chemin d'écriture de l'assurance (INV-78)
```

Chaque commande est soumise aux six conditions d'`INV-79`. Aucune ne modifie une ligne existante : toutes ajoutent.

## Article 27 — Ce que le contrat n'offre pas, et n'offrira pas

Aucune opération ne supprime une identité. Aucune ne réattribue une référence. Aucune ne fusionne sans décision. Aucune ne restitue une donnée de profil. Aucune ne calcule une pertinence, une affinité ou un score — c'est l'objet de `CAP-CORE-021`, et la frontière est posée au Titre IX.

## Article 28 — Mécanisme de versionnement

La famille `CTR-01` — Référence d'identité, gardée par `DOM-02` — est **conservée**. `INV-40` demeure satisfait : `CAP-CORE-001` garde `DOM-02`.

Le mécanisme exact de coexistence des versions d'un contrat relève de `CAP-CORE-009` — Registre des contrats. La présente conception **ne l'arrête pas** et le soumet à l'autorité (Article 41, point 4).

---

# TITRE VI — MENACES

## Article 29 — Menaces retenues

Le dernier numéro attribué est `M-75`. La présente révision retient `M-76` à `M-85`.

| Menace | Énoncé | Traitement |
|---|---|---|
| `M-76` | Chaque produit détient ses propres identités, sans référence commune | `INV-74`, `INV-75` |
| `M-77` | Un produit devient propriétaire de fait des identités et empêche leur portabilité | `INV-75`, `INV-76` |
| `M-78` | Une même personne porte plusieurs références jamais rapprochées | `resoudreRapprochementsProposes` |
| `M-79` | Deux personnes distinctes sont fusionnées sur un signal insuffisant | `INV-80` |
| `M-80` | Le Core absorbe les données métier détaillées des satellites | `INV-19`, absence de colonnes |
| `M-81` | Une appartenance ou une représentation est déduite d'un simple lien | `INV-77`, `CAP-CORE-003` |
| `M-82` | Une entité est tenue pour fortement vérifiée parce qu'elle utilise plusieurs produits | `INV-78` |
| `M-83` | La suppression d'un compte ou d'un produit rend une identité inaccessible | `INV-76` |
| `M-84` | Une réindexation efface des identités inscrites, faute de source dans le corpus | `INV-73` |
| `M-85` | Un satellite lit les relations d'une identité dans un autre produit | Article 30 |

`M-84` mérite d'être signalée à part : elle n'est pas une menace externe. C'est la faute que **le code existant commettrait aujourd'hui**, puisqu'il reconstruit intégralement son index depuis le corpus. Ouvrir l'écriture sans poser `INV-73` d'abord détruirait les identités inscrites à la première réindexation.

---

# TITRE VII — CONTRÔLES ET PREUVE

## Article 30 — Classification et exposition minimale

Toute ligne des tables porte une classification : `PUBLIC_ECOSYSTEME`, `INTERNE`, `CONFIDENTIEL`, `RESTREINT`, `SECRET_CORE`.

Un produit consommateur reçoit **le résultat nécessaire, et lui seul** :

```json
{ "reference": "…", "etat": "ACTIVE", "assurance": "A2" }
```

Il ne reçoit ni les organisations liées, ni les autres produits utilisés, ni les risques, ni les incidents, ni les restrictions, ni les identifiants locaux d'autres produits, ni les dossiers de rapprochement. `resoudreLiensProduits` filtre sur le produit appelant, sauf autorité habilitée établie par `CAP-CORE-004`.

## Article 31 — Garde `P3` et cas d'essai

Garde propre à la capacité (`ADOPTION-0035`, Art. 2.2), portée par `core/registre-identites/tests/identite_p3.php`, étendue et non remplacée. Le cas d'essai initial — `PRD-GAMAD-001` restituant `DISSOUS — IDENTITÉ RENDUE AU CORE` au 27 juillet 2026 et `HISTORIQUE À QUALIFIER` la veille — est **conservé**.

Douze cas s'y ajoutent :

1. une personne inscrite par un produit reçoit une référence canonique ;
2. la même personne rejoignant un second produit conserve la même référence ;
3. la clôture d'un compte de produit laisse l'identité `ACTIVE` (`INV-76`) ;
4. une organisation porte une référence canonique et un dossier distinct en `CAP-CORE-002` ;
5. une relation `REPRESENTANT` sans mandat vérifié n'est pas restituée comme représentation opposable ;
6. une identité provisoire ne reçoit pas une assurance forte, quel que soit son usage (`INV-78`) ;
7. un doublon probable est restitué à l'état `PROPOSE`, jamais `VALIDE` (`INV-80`) ;
8. un produit n'obtient pas les relations d'une identité dans un autre produit ;
9. une relation expirée n'est pas restituée comme active ;
10. aucune opération ne restitue une donnée de profil, quelle que soit la requête ;
11. **une réindexation complète du corpus ne détruit aucune identité `INSCRIT_AU_REGISTRE`** (`INV-73`) ;
12. une commande d'écriture sans politique d'inscription est refusée (`INV-79`).

## Article 32 — Contre-épreuve de falsification

Conformément à `ADOPTION-0032`, Art. 3, sur copie du corpus hors dépôt, avec témoin. Quatre falsifications ciblées, dont il est vérifié qu'elles **font échouer la garde** :

- déplacer la date de `ADOPTION-0023` — la contre-épreuve initiale, conservée ;
- écrire un niveau d'assurance sans événement d'assurance correspondant ;
- porter un rapprochement à l'état `VALIDE` sans décideur ;
- antidater la fin d'une relation pour la faire paraître active.

Un test qui ne peut pas échouer ne prouve rien.

---

# TITRE VIII — FLUX D'INSCRIPTION

## Article 33 — Inscription d'un utilisateur de produit

```
Personne → Produit reconnu
         → CAP-CORE-005 établit le contrôle et le niveau d'assurance initial
         → le produit soumet un dossier conforme à une politique adoptée
         → CAP-CORE-001 cherche un rapprochement possible
         → inscription, rattachement, ou rapprochement PROPOSÉ
         → référence canonique restituée au produit
```

Le produit conserve ensuite `sujet_local_opaque → reference_canonique`, et rien d'autre du Core.

## Article 34 — Inscription d'une organisation

```
Organisation → CAP-CORE-002 reçoit le dossier organisationnel
             → CAP-CORE-001 attribue ou retrouve la référence canonique
             → CAP-CORE-003 vérifie représentants et mandats
             → les produits consomment la référence commune
```

L'identité canonique est portée par `CAP-CORE-001` ; le dossier organisationnel détaillé par `CAP-CORE-002` ; les mandats par `CAP-CORE-003`. Aucune de ces trois capacités n'exécute la fonction d'une autre.

## Article 35 — Migration

Ordre pilote proposé : Wasplex, puis GamaDrive, puis portail GAMAD, puis les autres satellites. Pendant la migration, les identités locales demeurent lisibles, les opérations nouvelles utilisent la référence canonique, les doublons sont signalés, les fusions demeurent gouvernées et le retour arrière demeure possible.

Le Core n'exige à aucun moment l'export des données métier locales.

**Cet ordre est une proposition.** Le décret de travail du 29 juillet 2026 réserve GamaDrive et écarte l'ouverture de ce chantier sans instruction expresse ; le présent article ne l'ouvre pas.

---

# TITRE IX — FRONTIÈRES

## Article 36 — Les frontières initiales sont maintenues et renforcées

- Aucune authentification, aucune session, aucune preuve de contrôle : `CAP-CORE-005`.
- Aucun droit, aucune permission, aucune évaluation d'autorisation : `CAP-CORE-004`.
- Aucun mandat, aucune représentation opposable : `CAP-CORE-003`.
- Aucun dossier organisationnel : `CAP-CORE-002`.
- Aucun profil, aucun contenu, aucune donnée métier : le produit compétent.

`CAP-CORE-001` ne permet toujours à personne de se connecter. Elle établit **qui existe**.

## Article 37 — Frontière avec `CAP-CORE-021` — le Matching

| `CAP-CORE-001` fournit | `CAP-CORE-021` fournit |
|---|---|
| qui existe, sous quelle référence | qui correspond à quoi |
| dans quel état | dans quel contexte et selon quelle politique |
| avec quel niveau d'assurance | avec quelle pertinence et quelle confiance |
| avec quels liens minimaux autorisés | avec quelles restrictions et quelle durée |

Le Matching **ne modifie jamais** une identité canonique et ne fusionne jamais deux références. L'Identity Registry **n'évalue jamais** une compatibilité métier et ne produit aucun score.

`INV-81`, posé par la conception de `CAP-CORE-021`, ferme cette frontière du côté du Matching. Le présent article la ferme du côté de l'identité.

## Article 38 — Ce que la présente révision ne fait pas

Elle n'inscrit aucune identité. Elle n'adopte aucune politique d'inscription. Elle ne code rien par elle-même. Elle ne rend `CAP-CORE-001` ni admise ni active dans sa version révisée. Elle n'ouvre le chantier d'aucun satellite. Elle ne franchit pas la frontière des accès réservés (`ADOPTION-0025`, Art. 3). Elle ne constate pas `G0` et ne lève aucune de ses réserves.

---

# TITRE X — EFFET SUR L'ADMISSION EN COURS

## Article 39 — L'admission actuelle deviendra caduque, et c'est régulier

`ADOPTION-0063` a admis `CAP-CORE-001` en nommant le commit `65093691e0efbb35cf8ff92aee9c59dcfb3b7704`. `INV-68` veut qu'une admission nomme un commit et ne lui survive pas.

La révision du module rendra donc cette admission **caduque**. L'Article 231 du Registre initial des capacités souveraines l'énonce : la caducité est le mécanisme qui fonctionne, non une faute ; elle est un constat porté au tableau de bord et n'interrompt ni le travail ni l'intégration continue.

Réinscrire relève de l'autorité, au moment qu'elle choisit. Le présent texte **ne prononce, ne propose et ne qualifie aucune admission** (`INV-72`).

## Article 40 — Ce que la révision n'efface pas

`ADOPTION-0038` n'est ni réécrite, ni retirée, ni corrigée. La conception initiale demeure le texte qui a établi `INV-17` à `INV-21` et les sept entités du 29 juillet 2026. Prétendre que sa première version couvrait déjà les utilisateurs serait une falsification de l'histoire, et le Titre I ci-dessus dit précisément le contraire.

---

# TITRE XI — RÉSERVE D'AUDIT

## Article 41 — Rappel, et précaution propre à ce texte

Rédigé par l'agent, sous une fonction `AUDIT` non indépendante (`ADOPTION-0025`, Art. 3.b). Le concepteur ne s'audite pas.

Une précaution particulière s'imposait : la présente révision **élargit** un périmètre, et un élargissement est ce qu'il est le plus tentant de justifier après coup. Le Titre I nomme donc, article par article et sous forme de tableau, ce qui est dépassé et ce qui demeure. Ce qui n'y figure pas n'est pas dépassé.

L'agent demeure lui-même l'une des entités du registre (`AGENT-IA-002`) : il écrit le registre qui l'inscrit, et la présente révision lui ouvrirait l'écriture. C'est la raison pour laquelle `INV-79` exige une autorité d'inscription distincte du producteur et une politique adoptée : **l'agent ne peut être l'autorité d'inscription d'aucune politique.**

---

# TITRE XII — DÉCISIONS RÉSERVÉES À L'AUTORITÉ

## Article 42 — Points soumis

Le dernier numéro de décision attribué est `DECISION-0049`. Les points ci-dessous seraient inscrits `DECISION-0050` à `DECISION-0054` si l'autorité adopte la présente révision.

1. **`DECISION-0050` — la politique d'inscription initiale** : quels canaux inscrivent, pour quels types, sous quel niveau d'assurance initial, avec quelles preuves exigées et sous quel responsable. Sans elle, l'Article 8 demeure lettre morte, `INV-79` refusant toute écriture sans politique.
2. **`DECISION-0051` — les niveaux d'assurance** : leur échelle, leurs preuves admises et le niveau exigé par finalité. La conception initiale les nommait sans les arrêter (Article 36 du Registre, « décisions ouvertes »).
3. **`DECISION-0052` — l'autorité d'inscription** : qui inscrit, qui rapproche, qui décide d'une fusion. `ADOPTION-0061` a établi l'autorité de décision unique et transitoire ; une autorité d'inscription opérationnelle est une autre question.
4. **`DECISION-0053` — le mécanisme de version du contrat `CTR-01`** : `CTR-01 v2`, ou tout autre mécanisme que `CAP-CORE-009` arrête. Le présent texte ne le tranche pas.
5. **`DECISION-0054` — l'ordre pilote de migration** et l'ouverture, ou non, du premier chantier satellite, que le décret de travail réserve.

Demeurent par ailleurs ouvertes et non tranchées par le présent texte : la dénomination canonique de `PRD-GAMAD-002` (Article 18, point 2 de la conception initiale) et les décisions `DECISION-0046` à `DECISION-0049`.

## Article 43 — Non-effet

L'adoption éventuelle de la présente révision ne rend `CAP-CORE-001` ni admise ni active dans sa version révisée, n'inscrit aucune identité, n'adopte aucune politique d'inscription, ne nomme aucune autorité d'inscription, ne tranche aucune des décisions de l'Article 42, n'accepte aucun risque nouveau, ne modifie le corps d'aucun texte adopté, ne crée aucune famille de contrat et ne constate pas `G0`.
