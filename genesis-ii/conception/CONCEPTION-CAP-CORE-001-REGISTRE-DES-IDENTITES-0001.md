# CONCEPTION-CAP-CORE-001-REGISTRE-DES-IDENTITES-0001
## Projet de conception de la capacité souveraine `CAP-CORE-001` — Identity Registry

> **PROJET D'ACTE — soumis à l'autorité. Non exécuté, non adopté, non signé tant qu'un registre d'adoption distinct (pressenti `ADOPTION-0038`) n'a pas été signé.**

## Nature et rattachement

Étape de conception de la séquence de l'Article 63. Rédigé sous `MISSION-AI-GENESIS-II-CODE-0001` (`ADOPTION-0037`).

`CAP-CORE-001` se distingue des deux capacités racines précédentes sur un point décisif : **aucun registre initial des identités n'existe**. Là où `CAP-CORE-003` disposait de cent soixante-six articles à traduire, la présente conception ne dispose que de la fiche de l'Article 36 du registre des capacités et des textes de gouvernance. Elle établit donc davantage qu'elle ne traduit, et le signale.

## Article 1 — `CAP-CORE-001` est le successeur institutionnel de `GAMAD ID`

`ADOPTION-0023` a constaté que le produit `PRD-GAMAD-001` — GAMAD ID — passe à l'état **`DISSOUS — IDENTITÉ RENDUE AU CORE`** (Article 154 du Registre des produits).

La fonction d'identité n'est donc pas une ambition nouvelle : elle a été **rendue au Core** par la dissolution d'un produit qui la portait. `CAP-CORE-001` recueille cette fonction. Cette filiation n'avait été inscrite nulle part ; la présente conception la constate.

## Article 2 — Périmètre

Le service livrera, en lecture et attestation seulement :

1. l'index dérivé des entités que le corpus reconnaît déjà ;
2. `resoudre_identite` — l'entité, son type, son état à une date, sa source ;
3. `resoudre_inventaire` — les entités connues, par type ;
4. `resoudre_denominations` — **les dénominations divergentes** portées par une même référence.

Aucune écriture. Créer une identité demeure un acte signé.

---

# TITRE I — INVARIANTS

## Article 3 — `INV-17` — Identifiant canonique stable et jamais réattribué

Une entité est désignée par une référence canonique stable, indépendante de son nom, de son état et de son support. Une référence libérée par une dissolution ou une clôture **n'est jamais réattribuée** : `PRD-GAMAD-001` désignera toujours GAMAD ID, fût-il dissous.

Réattribuer une référence rendrait fausses toutes les citations antérieures.

## Article 4 — `INV-18` — Type d'entité déclaré

Toute entité porte un type déclaré, pris dans une liste close reprise de la fiche : `personne`, `organisation`, `produit`, `realm`, `agent`, `service`. Un type non déterminable donne `INDETERMINE` ; il n'est jamais présumé.

## Article 5 — `INV-19` — Minimalité, et données exclues

Le registre conserve l'identifiant, le type, l'état, la source, les dates de validité et les traces de cycle de vie. Il **ne conserve pas**, conformément à la fiche de l'Article 36 :

> profil universel, dossiers métier détaillés, réputation globale, **jugement moral ou spirituel**, agrégation transversale implicite.

Cette exclusion n'est pas une préférence technique. Un registre d'identités souverain qui accumulerait des jugements sur les personnes deviendrait un instrument de pouvoir sur elles. Le schéma ne prévoit aucune colonne pour de telles données, et aucune opération ne les restitue : l'interdit est tenu par la structure, non par la discipline.

## Article 6 — `INV-20` — L'assurance est distincte de l'existence

Le niveau d'assurance d'une identité — la force des preuves qui l'établissent — est indépendant de son existence au registre. Une entité peut être reconnue avec une assurance faible ; elle n'en est pas moins inscrite. Confondre les deux conduirait soit à taire les entités mal établies, soit à leur prêter une solidité qu'elles n'ont pas.

## Article 7 — `INV-21` — Cycle de vie en ajout seul

Correction, fusion, scission, clôture et dissolution sont **inscrites, jamais substituées**. L'état antérieur demeure lisible, avec sa date et son acte. Une identité dissoute reste consultable — c'est précisément le cas de `PRD-GAMAD-001`.

---

# TITRE II — DONNÉES

## Article 8 — Tables

```sql
-- Une entité reconnue. Aucune colonne de profil, de dossier ou de
-- jugement : l'exclusion d'INV-19 est tenue par la structure.
CREATE TABLE entite (
    reference text PRIMARY KEY,      -- INV-17
    type      text NOT NULL,         -- INV-18, liste close
    libelle   text NOT NULL,         -- dénomination canonique retenue
    source    text NOT NULL          -- texte du corpus qui la déclare
);

-- État, en ajout seul (INV-21), fondé sur un acte (INV-4).
CREATE TABLE etat_entite (
    id                 …,
    entite_reference   text NOT NULL REFERENCES entite(reference),
    valeur             text NOT NULL,
    date_effet         date NOT NULL,
    adoption_reference text NOT NULL REFERENCES adoption(reference)
);

-- Dénominations relevées dans le corpus pour une même référence.
-- Plusieurs lignes pour une référence = divergence à signaler, non à trancher.
CREATE TABLE denomination (
    id               …,
    entite_reference text NOT NULL REFERENCES entite(reference),
    libelle          text NOT NULL,
    source           text NOT NULL
);
```

## Article 9 — Ce que le corpus permet de dériver aujourd'hui

Sept entités, et sept seulement :

| Type | Références | Source |
|---|---|---|
| `personne` | `AUT-GAMAD-001` | Registre des autorités, Art. 46 |
| `agent` | `AGENT-IA-001`, `AGENT-IA-002` | Registre des usages IA, Art. 7 |
| `produit` | `PRD-GAMAD-001` à `004` | Registre des produits, Art. 43 et 154 |

**Le registre des identités de GAMAD contiendra sept entrées.** Ce chiffre est le fait le plus important de la présente conception : il mesure exactement la distance entre l'état du Core et une exploitation réelle.

---

# TITRE III — CONTRAT `CTR-01`

## Article 10 — Opérations

```
resoudre_identite(reference, date?)
  → { reference, type, libelle, etat, date_effet, adoption_reference, source }

resoudre_inventaire(type?)
  → [ { reference, type, libelle, etat } ]

resoudre_denominations(reference?)
  → [ { reference, libelles[], divergente } ]
    • 'divergente' est vrai lorsqu'une même référence porte plusieurs
      dénominations dans le corpus. Le service SIGNALE ; il ne tranche pas,
      la dénomination canonique étant une qualification (ADOPTION-0037, Art. 3).
```

## Article 11 — Lecture seule

Aucune écriture (`INV-4`). Créer, corriger, fusionner ou clore une identité demeure un acte signé.

---

# TITRE IV — MENACES

## Article 12 — Menaces retenues

| Réf. | Menace | Couverture |
|---|---|---|
| `M-14` | Référence réattribuée après dissolution | `INV-17` |
| `M-15` | Dérive de dénomination — une entité connue sous plusieurs noms | `resoudre_denominations`, qui la rend visible |
| `M-16` | Accumulation de données sur les personnes | `INV-19`, tenu par l'absence de colonnes |
| `M-17` | Identité présumée d'un usage de fait | `INV-18` et `INV-20` — aucun type ni assurance présumés |
| `M-18` | Effacement d'une identité close | `INV-21` — ajout seul |

## Article 13 — La menace que le service ne couvre pas

`M-19` — **l'identité qui n'existe pas encore.** Le registre ne connaîtra que sept entités parce que le corpus n'en déclare que sept. Aucun dispositif technique ne peuple un registre d'identités : seuls des actes le font. Le service dira combien il en connaît, et ce nombre sera petit.

---

# TITRE V — PREUVE

## Article 14 — Cas `P3` et contre-épreuve

Garde propre à la capacité, conformément à la doctrine de `ADOPTION-0035`, Art. 2.2.

**Cas d'essai.** `resoudre_identite('PRD-GAMAD-001')` restitue l'état `DISSOUS — IDENTITÉ RENDUE AU CORE` au 27 juillet 2026 et `HISTORIQUE À QUALIFIER` la veille — la reconstruction temporelle d'une identité dissoute.

**Contre-épreuve.** Sur copie hors dépôt, déplacer la date de `ADOPTION-0023` ; il est alors vérifié que le test échoue.

---

# TITRE VI — CE QUE CETTE CONCEPTION NE FAIT PAS

## Article 15 — Frontières

- Aucune authentification, aucune session, aucun mot de passe : `CAP-CORE-005`.
- Aucun droit, aucune permission : `CAP-CORE-004`.
- Aucune création d'identité : acte signé.
- Aucune qualification de dénomination canonique lorsque le corpus diverge.

## Article 16 — Ce que cette capacité ne donnera pas

**`CAP-CORE-001` ne permettra à personne de se connecter.** Elle établit *qui existe* aux yeux du Core. Se connecter exige de prouver qu'on est cette entité — c'est `CAP-CORE-005` — puis de savoir ce qu'on peut faire — c'est `CAP-CORE-004`. Toutes deux demeurent `À ÉTABLIR`.

---

# TITRE VII — RÉSERVE D'AUDIT ET DÉCISIONS RÉSERVÉES

## Article 17 — Réserve

Conçue, codée et vérifiée par le même agent, sous une fonction AUDIT non indépendante (`ADOPTION-0025`, Art. 3.b). L'agent est en outre lui-même l'une des sept entités du registre (`AGENT-IA-002`) : il écrit le registre qui l'inscrit.

## Article 18 — Décisions réservées à l'autorité

1. L'adoption ou la correction de la présente conception.
2. **La dénomination canonique de `PRD-GAMAD-002`**, que le Registre des produits nomme « GAMAD Drive » à son Article 43 et « GamaDrive » à son Article 154. Une qualification, hors de portée de l'agent.
3. L'inscription d'entités que le corpus ne déclare pas encore — notamment GAMAD elle-même comme organisation, absente du registre à ce jour.

## Article 19 — Non-effet

Le présent acte ne code rien par lui-même, ne crée aucune identité, ne rend `CAP-CORE-001` ni implémentée ni active, n'accepte aucun risque nouveau, ne modifie le corps d'aucun texte adopté et ne constate pas `G0`.

---

## Autorité d'adoption

- **Nom :** _[réservé à l'autorité]_
- **Date :** _[à compléter à l'adoption]_
- **Registre d'adoption pressenti :** `ADOPTION-0038`

> **PROJET NORMATIF — EN COURS DE DÉLIBÉRATION**
