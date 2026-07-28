# CONCEPTION-CAP-CORE-004-AUTORISATION-0001
## Projet de conception de la capacité souveraine `CAP-CORE-004` — Moteur d'autorisation commun

> **PROJET D'ACTE — soumis à l'autorité. Non exécuté, non adopté, non signé tant qu'un registre d'adoption distinct (pressenti `ADOPTION-0040`) n'a pas été signé.**

## Nature

Étape de conception de la séquence de l'Article 63, sous `MISSION-AI-GENESIS-II-CODE-0001`.

`CAP-CORE-004` est la capacité qui **détermine ce que chacun peut faire**. Dans un Core où quinze fonctions sont vacantes et où un seul titulaire les détient toutes, elle appelle une question préalable que la présente conception traite avant toute technique.

---

# TITRE I — LA QUESTION PRÉALABLE

## Article 1 — Qu'autorise-t-on lorsqu'il n'y a qu'un titulaire ?

Un moteur d'autorisation sert ordinairement à distinguer les pouvoirs de plusieurs acteurs. Le Registre des identités compte sept entités, dont **une seule personne** ; le Registre des autorités constate quinze fonctions vacantes (Article 170).

Répondre « le titulaire peut tout » serait une tautologie : elle n'ajouterait rien à ce que la situation dit déjà, et donnerait au Core l'apparence d'un contrôle sans le contrôle.

## Article 2 — La réponse est déjà écrite au corpus

L'Article 49 du Registre des autorités énumère ce que le mandat transitoire **ne permet pas** :

> falsifier une source ou l'histoire ; attribuer au Fondateur une parole non établie ; transformer une préférence technique en norme supérieure ; effacer injustement une preuve ; convertir le Core en propriété personnelle ; confondre adoption, publication, déploiement et conformité ; prononcer `G0` sans l'acte distinct et les contrôles requis.

Ces sept limites sont **adoptées depuis le 26 juillet 2026** et n'ont jamais eu d'effet opératoire : elles sont écrites, non opposables.

**La valeur de `CAP-CORE-004` aujourd'hui n'est pas de restreindre autrui. Elle est de rendre les limites de l'autorité opposables à l'autorité elle-même.**

L'Article 48 énumère symétriquement six compétences. Ensemble, ils forment un catalogue de politiques que le corpus porte déjà — la conception ne l'invente pas, elle le rend exécutable.

---

# TITRE II — INVARIANTS

## Article 3 — `INV-27` — Refus par défaut

Toute demande dont aucune politique ne permet l'action est **refusée**. L'absence de règle n'est jamais une permission. Reprise directe du contrôle « refus par défaut » exigé par la fiche de l'Article 39.

## Article 4 — `INV-28` — Toute décision est explicable

Une décision restitue toujours son **motif**, la **politique** appliquée et sa **version**. Une décision sans motif est un défaut, non une commodité — la fiche l'exige, et l'Article 19 du Registre des autorités le pose déjà pour toute action critique.

Un moteur qui refuserait sans dire pourquoi serait indiscernable d'une panne.

## Article 5 — `INV-29` — Séparation de la politique et de l'exécution

Les politiques sont **dérivées du corpus**, jamais écrites dans le code du moteur. Le moteur évalue ; il ne décide pas des règles. Changer une règle exige un acte, non un correctif.

## Article 6 — `INV-30` — Les limites du mandat sont opposables à son titulaire

Une limite inscrite à l'Article 49 s'oppose au titulaire du mandat **comme à quiconque**. Aucune qualité, aucune urgence, aucune instruction ne la lève.

C'est l'invariant central de cette capacité. Un moteur d'autorisation qui exempterait l'autorité de ses propres bornes ne serait pas un moteur d'autorisation : ce serait une décoration.

---

# TITRE III — DONNÉES

## Article 7 — Tables

```sql
-- Politique dérivée d'un texte adopté. Jamais écrite dans le code (INV-29).
CREATE TABLE politique (
    reference          text PRIMARY KEY,   -- 'POL-MANDAT-COMPETENCES', 'POL-MANDAT-LIMITES'
    version            text NOT NULL,
    libelle            text NOT NULL,
    source             text NOT NULL,      -- article du corpus dont elle dérive
    adoption_reference text NOT NULL REFERENCES adoption(reference)
);

-- Règle élémentaire. `effet` vaut PERMET ou REFUSE ; REFUSE prime toujours.
CREATE TABLE regle (
    id                  …,
    politique_reference text NOT NULL REFERENCES politique(reference),
    effet               text NOT NULL CHECK (effet IN ('PERMET','REFUSE')),
    action              text NOT NULL,     -- énoncé de l'action, tel que le corpus le formule
    sujet_type          text,              -- NULL = applicable à tout sujet, titulaire compris
    motif               text NOT NULL      -- l'énoncé du corpus, cité (INV-28)
);
```

Aucune table de rôle, aucune hiérarchie de privilèges : le corpus n'en déclare pas, et la conception n'en invente pas.

---

# TITRE IV — CONTRAT `CTR-03`

## Article 8 — Opérations

```
autoriser(sujet, action, ressource?, contexte?)
  → { decision, motif, politique, version }
    • decision ∈ { PERMIS, REFUSÉ }
    • REFUSÉ par défaut si aucune règle ne permet (INV-27)
    • un REFUSE l'emporte toujours sur un PERMET (INV-30)

simuler(sujet, action, ressource?)
  → identique, marquée simulation, sans effet ni trace

resoudre_interdits(sujet?)
  → [ { action, motif, politique, source } ]
    • ce qui est interdit, à qui, et en vertu de quel texte
```

## Article 9 — Ce que le moteur n'est pas

Il n'exécute rien. Il ne bloque aucune opération technique du dépôt : Git, la fusion et le déploiement demeurent hors de sa portée. **Il dit ce qui est permis ; il n'empêche pas physiquement.**

Prétendre le contraire serait la menace `M-26` : croire qu'une politique déclarée est une contrainte appliquée.

---

# TITRE V — MENACES

## Article 10 — Menaces retenues

| Réf. | Menace (fiche, Art. 39) | Couverture |
|---|---|---|
| `M-26` | Autorisation par défaut | `INV-27` — refus par défaut |
| `M-27` | Politique ambiguë | `REFUSE` prime toujours sur `PERMET` |
| `M-28` | Décision non explicable | `INV-28` — motif, politique et version restitués |
| `M-29` | Contournement produit | **non couverte** — le moteur n'empêche rien physiquement (Article 9) |
| `M-30` | Politique écrite dans le code | `INV-29` — dérivation depuis le corpus |
| `M-31` | Autorité exemptée de ses propres limites | `INV-30` |

## Article 11 — La menace que cette capacité ne peut pas couvrir

`M-29` — **le contournement.** Le titulaire qui détient les accès techniques peut agir sans consulter le moteur. Aucune politique déclarée n'arrête une main qui dispose du dépôt.

Le moteur rend donc les limites **visibles et opposables**, non **infranchissables**. Il transforme un franchissement silencieux en franchissement constatable. C'est moins qu'un verrou, et c'est tout ce qu'un Core peut offrir tant que la séparation des fonctions n'est pas réelle.

---

# TITRE VI — PREUVE

## Article 12 — Cas `P3` et contre-épreuve

**Cas d'essai.** Une action inconnue est refusée (`INV-27`) ; une compétence de l'Article 48 est permise ; une limite de l'Article 49 est **refusée au titulaire du mandat lui-même** (`INV-30`) ; toute décision porte un motif non vide (`INV-28`).

**Contre-épreuve.** Le refus par défaut est neutralisé sur copie ; il est constaté que le test échoue.

---

# TITRE VII — CE QUE CETTE CONCEPTION NE FAIT PAS

## Article 13 — Frontières

- Aucun rôle, aucune hiérarchie de privilèges : le corpus n'en déclare pas.
- Aucune contrainte physique (Article 9, `M-29`).
- Aucune politique inventée : seules celles que les Articles 48 et 49 portent déjà.
- Aucune évaluation de finalité, de contexte de risque ni d'environnement, que la fiche prévoit : la conception les réserve, faute de texte adopté qui les définisse.

---

# TITRE VIII — DÉCISIONS RÉSERVÉES

## Article 14 — Points à trancher

1. L'adoption ou la correction de la présente conception.
2. **La formulation des actions.** Les Articles 48 et 49 sont rédigés en prose ; les traduire en actions nommées est une opération d'interprétation. L'agent a repris les énoncés **mot pour mot** comme motifs, sans les reformuler, afin que la traduction demeure vérifiable.
3. L'extension éventuelle du catalogue à d'autres textes adoptés.

## Article 15 — Non-effet

Le présent acte ne code rien par lui-même, ne confère ni ne retire aucun pouvoir, n'empêche aucune opération, ne rend `CAP-CORE-004` ni implémentée ni active et ne constate pas `G0`.

---

> **PROJET NORMATIF — EN COURS DE DÉLIBÉRATION** · Registre pressenti : `ADOPTION-0040`
