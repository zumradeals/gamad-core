# CONCEPTION-DOM-04-ORGANISATIONS-PRODUITS-REALMS-0001
## Projet de conception du domaine `DOM-04` — `CAP-CORE-002`, `CAP-CORE-011` et `CAP-CORE-012`

> **PROJET NORMATIF — NON SIGNÉ.** Ce texte n'a aucune valeur d'autorité tant qu'un acte d'adoption ne l'a pas adopté et qu'une fusion `--no-ff` dans `main` ne l'a pas mis en vigueur. Il est rédigé par l'agent sous instruction (`ADOPTION-0024`, Art. 3).

## Nature et rattachement

Conception conjointe des trois capacités du domaine `DOM-04` — Organisations, produits et realms (Article 35 de `CORE-ATLAS-0001`) : `CAP-CORE-002`, `CAP-CORE-011` et `CAP-CORE-012`, décrites aux Articles 37, 46 et 47 du Registre initial des capacités souveraines. Criticité proposée `CRITIQUE` pour les trois.

Elle est présentée conformément à l'Article 63 : invariants, données, contrats, menaces, contrôles et preuves **avant** tout choix technologique.

**Un seul document pour trois capacités**, parce qu'elles partagent un domaine, une source d'écart commune et, pour deux d'entre elles, une famille de contrat. Les trois demeurent **trois capacités distinctes**, avec trois modules, trois gardes et trois constats d'état : la conception est conjointe, la preuve ne l'est pas (`ADOPTION-0035`, Art. 2.2).

---

# TITRE I — LE CONSTAT

## Article 1 — Deux des trois sources canoniques de `DOM-04` étaient absentes

L'Article 35 de l'Atlas nomme trois sources canoniques pour ce domaine : le **Registre des organisations**, le **Registre des produits**, le **Registre des realms**.

Seul le second existait, adopté par `ADOPTION-0016`. Les deux autres n'ont jamais été constitués, et les fiches des capacités le constataient elles-mêmes — Article 37 : « registre initial et sources de vérité organisationnelles non constitués » ; Article 47 : « aucun inventaire initial ni contrat de fédération établi ».

## Article 2 — `CAP-CORE-002` était la seule capacité sans famille de contrat

L'Article 37 énonce des contrats attendus — « résolution d'organisation, statut, représentation et événements de cycle de vie » — **en prose et sans référence**. Aucune des seize familles de l'Article 69 de l'Atlas ne les portait : `CTR-08` garde bien `DOM-04`, mais son intitulé — « Statut produit ou realm » — la restreint aux produits et aux realms.

C'est la troisième omission de l'Article 69, après les deux que le Titre XIV de l'Atlas a relevées. Elle est d'une autre espèce : non pas une famille empruntée hors de son domaine, mais une capacité **sans aucune famille**.

## Article 3 — Ce que le portefeuille des produits dit de lui-même

Les quatre produits historiques portent tous une admission `DOSSIER À CONSTITUER` et une conformité `NON ÉVALUÉ`, sans propriétaire institutionnel désigné. Leurs quatre états courants, constatés par `ADOPTION-0023`, sont **absents des onze états du vocabulaire de l'Article 22** — `DISSOUS — IDENTITÉ RENDUE AU CORE`, `PRODUIT OFFICIEL RECONNU`, `PARTENAIRE EXTERNE`.

Ces états procèdent d'un Titre adopté : ils sont réguliers **et** hors vocabulaire à la fois.

---

# TITRE II — INVARIANTS

## Article 4 — Numérotation

Les invariants forment une suite unique à l'échelle du Core (`ADOPTION-0032`, Art. 2.2). Le dernier attribué est `INV-51`. La présente conception introduit `INV-52` à `INV-56`.

## Article 5 — `INV-52` — Admission et conformité ne se présument jamais

Un produit dont l'admission n'est pas acquise n'est jamais restitué comme conforme. La reconnaissance d'un produit et son admission sont **deux actes distincts** : GamaDrive est reconnu produit officiel depuis `ADOPTION-0023` et son dossier d'admission demeure à constituer.

Les confondre certifierait un produit que nul n'a évalué, et ferait mentir la réserve d'`ADOPTION-0025`, Art. 3.c — aucun produit certifié. Cette réserve doit être **dérivée du corpus**, non recopiée : recopiée, elle cesse d'être vraie le jour où le corpus change sans que personne s'en aperçoive.

## Article 6 — `INV-53` — L'état courant procède du dernier Titre, et n'est jamais traduit

L'état d'un produit procède du dernier Titre qui l'a constaté, **l'état initial demeurant lisible à côté**. Un registre qui perdrait l'état antérieur perdrait la trace de la décision qui l'a changé.

Un état absent du vocabulaire adopté est restitué **mot pour mot** et relevé comme hors vocabulaire. Il n'est jamais rapproché du terme voisin — `PRODUIT OFFICIEL RECONNU` n'est pas `ADMIS`, `DISSOUS` n'est pas `RETIRÉ`.

C'est `INV-49` appliqué aux produits ; le fait qu'un même travers apparaisse dans deux registres indépendants indique qu'il est systémique.

## Article 7 — `INV-54` — Un realm non inscrit n'est pas reconnu

Aucune confiance n'est implicite. Une entité que le corpus nomme, fût-elle un partenaire déclaré disposé à être branché au Core, **n'est pas un realm fédéré** tant qu'aucune inscription ne la reconnaît.

C'est le risque que l'Article 47 range en tête : « confiance implicite ». Wasplex et IKOMA sont inscrits comme familles de produits partenaires ; les tenir pour des realms serait exactement cette faute.

## Article 8 — `INV-55` — L'absence d'une source canonique est constatée, jamais suppléée

Lorsqu'une source canonique nommée par l'Atlas est absente, le service **le constate** et ne tire ses données d'aucune source voisine.

Le Registre des produits existe ; il serait aisé d'en tirer des realms, puisqu'il nomme des partenaires. Ce serait suppléer une source canonique par une autre, et faire dire au corpus ce qu'il n'a pas écrit.

## Article 9 — `INV-56` — Être nommée par un texte ne vaut pas reconnaissance

Seule l'inscription reconnaît une organisation. GAMAD, Wasplex et IKOMA sont nommés en toutes lettres par des textes adoptés ; une seule de ces trois entités est une organisation inscrite.

Les organisations propriétaires des familles de produits partenaires ne sont nommées par aucun texte adopté : les inscrire exigerait de leur donner un nom que le corpus n'a pas écrit.

---

# TITRE III — DONNÉES ET CONTRATS

## Article 10 — La famille `CTR-17` — Référence d'organisation

Créée par le Titre XV de `CORE-ATLAS-0001`, gardée par `DOM-04`, attribuée à `CAP-CORE-002` par déclaration de rattachement. `INV-40` est satisfait : la capacité garde le domaine gardien de la famille.

Objet minimal : résoudre une organisation, son type, son statut et le texte qui la fonde.

## Article 11 — Le partage de `CTR-08` est régulier

`CTR-08` sert `CAP-CORE-011` et `CAP-CORE-012`, et l'Atlas l'énonce dans son intitulé même : le partage est **régulier** (Article 125 de l'Atlas), il n'est pas une collision.

Chaque capacité a **son module propre**, et chaque module déclare la capacité qu'il sert (`INV-41`). Le numéro de famille ne les distingue pas ; leur déclaration si. C'est précisément le cas que `INV-41` a été écrit pour traiter.

## Article 12 — Les opérations

| Capacité | Contrat | Opérations |
|---|---|---|
| `CAP-CORE-002` | `CTR-17` | `organisations()`, `resoudreOrganisation()`, `reconnues()`, `registreConstitue()`, `horsVocabulaire()`, `champs()`, `ecarts()` |
| `CAP-CORE-011` | `CTR-08` | `portefeuille()`, `resoudreProduit()`, `nonAdmis()`, `pretentionsSansDossier()`, `etatsChanges()`, `etatsHorsVocabulaire()`, `sansProprietaire()`, `ecarts()` |
| `CAP-CORE-012` | `CTR-08` | `realms()`, `inventaireConstitue()`, `sourcesCanoniques()`, `definitions()`, `externesNonRealms()`, `ecarts()` |

Lecture et attestation seulement. Aucune écriture applicative du corpus (`INV-4`) ; les fichiers Git demeurent la source de vérité (`INV-5`).

## Article 13 — Le Registre initial des organisations

La présente conception s'accompagne d'un texte nouveau : `REGISTRE-INITIAL-ORGANISATIONS-0001`, première des deux sources canoniques manquantes. Il arrête une forme d'inscription dérivable, trois types, quatre statuts, et **inscrit une seule organisation** — GAMAD, souveraine, fondée par `ACTE-0001`.

Ce nombre est un constat, non une lacune dissimulée : aucun autre texte adopté ne reconnaît d'organisation.

---

# TITRE IV — MENACES

## Article 14 — Menaces retenues

Le dernier numéro attribué est `M-57`. La présente conception retient `M-58` à `M-63`.

| Menace | Énoncé | Traitement |
|---|---|---|
| `M-58` | Un produit non admis est présenté comme conforme au Core | `INV-52` |
| `M-59` | Reconnaissance et admission d'un produit sont confondues | `INV-52` |
| `M-60` | Un état hors vocabulaire est rapproché du terme voisin, et l'écart disparaît | `INV-53` |
| `M-61` | Un partenaire externe est traité en realm fédéré | `INV-54` |
| `M-62` | Une source canonique absente est suppléée par une source voisine | `INV-55` |
| `M-63` | Une entité nommée par un texte est tenue pour reconnue | `INV-56` |

---

# TITRE V — CONTRÔLES ET PREUVES

## Article 15 — Trois preuves `P3`, jamais une seule

Les trois capacités visent `P3 — TESTÉ` par **trois gardes distinctes**. `CAP-CORE-011` et `CAP-CORE-012` partagent une famille de contrat ; elles ne partagent pas leur preuve.

Ce point n'est pas formel. Les produits sont inventoriés, les realms ne le sont pas : une garde commune aurait établi la première moitié de ce fait et masqué la seconde.

## Article 16 — Falsification

Conformément à `ADOPTION-0032`, Art. 3, chaque garde est accompagnée d'une **contre-épreuve de falsification avec témoin**, sur copie du corpus hors dépôt.

## Article 17 — Les gardes demeurent séparées

La garde documentaire Python demeure unique et indépendante de l'application (`ADOPTION-0027`, Art. 4). Le présent ensemble ajoute trois gardes de comportement, portant leur nombre à treize.

---

# TITRE VI — CE QUE CETTE CONCEPTION NE FAIT PAS

## Article 18 — Frontières

- Elle **n'admet, ne qualifie et ne certifie aucun produit** : `ADOPTION-0025`, Art. 3.c demeure entière.
- Elle **ne reconnaît aucun realm**, n'établit aucune fédération et n'accorde aucune confiance.
- Elle **ne constitue pas le Registre des realms** : cette absence est constatée, non comblée.
- Elle ne nomme aucun propriétaire institutionnel, aucun représentant, aucune autorité d'admission.
- Elle n'arbitre pas l'articulation avec les réalités juridiques ou institutionnelles externes, que l'Article 37 réserve à l'autorité.
- Elle ne rend les capacités ni admises, ni actives, et **ne constate pas `G0`**.

---

# TITRE VII — RÉSERVE D'AUDIT

## Article 19 — Rappel

Le présent texte est rédigé par l'agent, sous une fonction AUDIT non indépendante. Le concepteur ne s'audite pas.

Une précaution particulière s'imposait ici : deux des trois capacités portent principalement sur des **absences**, et une absence est ce qu'il est le plus tentant de combler. Les trois services ne lisent donc aucune prose. Ils lisent des tableaux, des formes déclaratives et des chemins de fichiers. Ce qui n'est pas écrit sous une forme dérivable est déclaré absent, jamais deviné.

---

# TITRE VIII — DÉCISIONS RÉSERVÉES À L'AUTORITÉ

## Article 20 — Points soumis

1. **Le Registre des realms** : constitué, ou faisant l'objet de la « décision motivée d'absence » que l'Article 47 admet comme alternative ?
2. **Les propriétaires institutionnels** des quatre produits, non désignés depuis `ADOPTION-0016`.
3. **Les états de produits hors vocabulaire** : le vocabulaire de l'Article 22 est-il étendu, ou les états sont-ils rapprochés ?
4. **La typologie des organisations**, l'autorité d'admission et l'articulation avec les réalités juridiques externes (Article 37).
5. **Les dossiers d'admission** des quatre produits, à constituer depuis `ADOPTION-0016`.

## Article 21 — Non-effet

L'adoption éventuelle de la présente conception ne rend les trois capacités ni admises, ni actives, n'arbitre aucune des décisions de l'Article 20, n'admet aucun produit, ne reconnaît aucun realm, n'accepte aucun risque nouveau et ne constate pas `G0`.
