# CONCEPTION-CAP-CORE-020-ANNUAIRE-CAPACITES-0001
## Projet de conception de la capacité souveraine `CAP-CORE-020` — Annuaire des capacités et Atlas

> **PROJET D'ACTE — soumis à l'autorité de proposition. Non exécuté, non adopté, non signé tant qu'un registre d'adoption distinct (pressenti `ADOPTION-0044`) n'a pas été signé.** Ce document conçoit ; il ne tranche aucune divergence et n'arbitre rien.

## Nature et rattachement

Étape de conception de la séquence de l'Article 63. Elle raffine la fiche de `CAP-CORE-020` (Article 55 du registre des capacités), qui lui attribue le contrat `CTR-14`.

Rédigé par SIRR (Claude, `AGENT-IA-002`) sous instruction (`ADOPTION-0024`, Art. 3 : conçoit et vérifie, ne décide ni ne signe).

## Article 1 — Pourquoi cette capacité arrive en dernier, et ce qu'elle trouve

`CAP-CORE-020` ferme l'ensemble racine de l'Article 61. Elle arrive en dernier parce qu'elle décrit les autres : un annuaire écrit avant ce qu'il décrit n'aurait décrit qu'un projet.

L'Article 55 énumère cinq risques — **carte divergente de la réalité, capacité fantôme, domaine sans responsable, source multiple non arbitrée, annuaire confondu avec l'implémentation** — et exige parmi ses contrôles la **« comparaison Atlas–Registre–réalité »**.

Aucun mécanisme ne l'opérait. Ces cinq risques n'étaient donc détectés par rien, et le Core ne pouvait pas savoir si sa carte correspondait à son territoire.

## Article 2 — Trois constats mesurés

Relevés au 28 juillet 2026, `main` à `08481c8`.

**2.1 — Une seule dimension d'état sur quatre était dérivée.** Le Registre déclare pour chaque capacité quatre états — conception, implémentation, exploitation, preuve — et chaque Titre de mise à jour les constate tous les quatre. Le service n'en dérivait qu'un : la conception. Le Core **déclarait** que `CAP-CORE-015` est `PARTIELLEMENT MATÉRIALISÉE` et `P3 — TESTÉ` sans qu'aucun mécanisme ne restitue ni ne vérifie ces deux états.

**2.2 — Quatre numéros de contrat sont revendiqués par deux capacités chacun.**

| Contrat | Revendiqué par | Où |
|---|---|---|
| `CTR-05` | `CAP-CORE-008` — Registre des décisions | Article 43, fiche adoptée |
| | `CAP-CORE-005` — Authentification | Titre XXIII (`ADOPTION-0039`), et le code le sert |
| `CTR-08` | `CAP-CORE-011` — Registre des produits | Article 46 |
| | `CAP-CORE-012` — Registre des realms | Article 47 |
| `CTR-10` | `CAP-CORE-013` — Audit commun | Article 49 |
| | `CAP-CORE-015` — Preuves d'intégrité | Article 50, et le code le sert |
| `CTR-11` | `CAP-CORE-017` — Registre des risques | Article 52 |
| | `CAP-CORE-018` — Registre des incidents | Article 53 |

Deux de ces collisions touchent du **code en service**. `CTR-05` et `CTR-10` sont exposés par des modules livrés et prouvés.

`ADOPTION-0032`, Art. 2.1 avait arrêté la règle destinée à empêcher exactement cela : « les numéros de contrat sont attribués dans l'ordre chronologique d'adoption de la conception qui les définit, **jamais par correspondance avec le numéro de la capacité servie**, et ne sont jamais réemployés ». `CTR-05` a précisément été donné à `CAP-CORE-005` par correspondance de numéro, alors qu'il était déjà pris.

Les collisions `CTR-08` et `CTR-11` sont antérieures à la règle : elles figurent dans le Registre initial adopté par `ADOPTION-0015`.

**2.3 — Atlas et Registre concordent.** Sur les vingt fiches, libellé et domaine coïncident sans exception. C'est un fait rassurant, et personne ne l'avait jamais établi.

## Article 3 — Périmètre

1. **La fiche complète** — identité, domaine, criticité, contrats, dépendances, et les quatre dimensions d'état.
2. **La triple comparaison** — Atlas contre Registre, Registre contre réalité du dépôt.
3. **Le registre des écarts** — divergences nommées, collisions relevées, champs non établis déclarés.

---

# TITRE I — INVARIANTS

## Article 4 — Numérotation

Séquence unique du Core (`CONCEPTION-CAP-CORE-006`, Art. 2). Dernier attribué : `INV-35` (`ADOPTION-0043`). La présente conception introduit `INV-36` à `INV-39`.

## Article 5 — `INV-36` — L'annuaire décrit, il ne fonde pas

**L'annuaire est dérivé. Il ne crée aucune capacité, n'en supprime aucune, n'établit aucun état.**

Sa source est le Registre adopté ; son rôle est de le restituer et de le confronter. Un annuaire qui fonderait deviendrait une seconde source, et l'Article 55 nomme précisément « source multiple non arbitrée » parmi les risques qu'il doit prévenir. L'annuaire ne peut pas être le risque qu'il surveille.

## Article 6 — `INV-37` — Quatre dimensions, jamais confondues

**Conception, implémentation, exploitation et preuve sont quatre états distincts d'une même capacité, et aucun ne s'infère d'un autre.**

Une capacité peut être conçue et non codée, codée et non prouvée, prouvée et non exploitée. Confondre deux de ces dimensions produirait exactement la « carte divergente de la réalité » que l'Article 55 redoute. `INV-10` séparait déjà le vocabulaire des normes de celui des capacités ; `INV-37` sépare les dimensions entre elles.

## Article 7 — `INV-38` — Divergence nommée, jamais arbitrée

**Le service nomme les divergences ; il n'en tranche aucune.**

Départager deux textes adoptés est un acte de l'autorité. Un service qui choisirait, entre `CAP-CORE-008` et `CAP-CORE-005`, lequel détient `CTR-05`, aurait modifié le corpus sans acte.

Conséquence pratique : une capacité dont le contrat est contesté reçoit le verdict `INDETERMINE`, et la comparaison au réel est **suspendue**. Deviner à qui appartient le code serait pire que de ne rien dire, car la réponse aurait l'apparence d'un constat.

## Article 8 — `INV-39` — Champ non établi déclaré tel

**Un champ que le corpus n'établit pas est rendu comme non établi, jamais comblé par une valeur plausible.**

Responsable, opérateur et sortie ne sont établis pour aucune des vingt capacités — conséquence directe de l'écart d'autorité de l'Article 69, qui constate qu'aucune autorité permanente n'est inscrite. L'annuaire qui inventerait un responsable créerait une responsabilité que personne n'a acceptée.

---

# TITRE II — CONTRAT

## Article 9 — Les opérations de `CTR-14`

```
resoudre_capacite(reference)
  → { reference, libelle, domaine, criticite, contrats[], dependances,
      etats: { conception, implementation, exploitation, preuve } }

attributions()  /  collisions()
  → contrat → capacités qui le revendiquent ; collisions = plus d'une.

comparer_atlas()
  → [ { capacite, atlas, registre, divergences[], verdict } ]

observer(reference)
  → { contrats, contrats_contestes, module, garde, garde_en_ci, observable }

comparer_reel(reference?)
  → [ { capacite, declare, observe, divergences[], verdict } ]
    verdict ∈ { CONCORDE, DIVERGENCE, INDETERMINE }

ecarts()
  → synthèse : capacités, codées, divergentes, indéterminées, collisions,
    champs non établis.
```

## Article 10 — Cinq divergences nommées

| Nom | Ce qu'elle constate | Risque de l'Article 55 |
|---|---|---|
| `CAPACITÉ FANTÔME` | implémentation déclarée, aucun module ne sert le contrat | capacité fantôme |
| `CODE NON DÉCLARÉ` | module présent, implémentation déclarée non commencée | carte divergente |
| `PREUVE NON FONDÉE` | preuve `P3` déclarée, aucune garde propre | carte divergente |
| `GARDE NON EXÉCUTÉE` | garde présente, absente de l'intégration continue | carte divergente |
| `CONTRAT CONTESTÉ` | numéro revendiqué par plusieurs capacités | source multiple non arbitrée |

## Article 11 — Lecture et attestation seulement

Aucune écriture applicative du corpus (`INV-4`). L'annuaire ne corrige aucune fiche, n'arbitre aucune collision, ne met à jour aucun état.

---

# TITRE III — MENACES

## Article 12 — Menaces retenues

| Réf. | Menace | Ce qui la contient |
|---|---|---|
| `M-38` | Capacité déclarée codée sans code, ou codée sans déclaration | `INV-37`, comparaison au réel |
| `M-39` | Preuve `P3` déclarée sans garde propre | comparaison au réel, divergence `PREUVE NON FONDÉE` |
| `M-40` | Numéro de contrat réemployé, deux capacités servies par un même nom | `INV-38`, relevé des collisions |
| `M-41` | Divergence tranchée par l'outil au lieu de l'autorité | `INV-38` — verdict `INDETERMINE` |
| `M-42` | Champ vide comblé par une valeur plausible | `INV-39` |
| `M-43` | Annuaire devenu seconde source de vérité | `INV-36` — dérivation stricte |

---

# TITRE IV — CONTRÔLES ET PREUVES

## Article 13 — Preuve `P3` visée, et sa falsification

`CAP-CORE-020` vise `P3 — TESTÉ` par une garde propre (`ADOPTION-0035`, Art. 2.2).

Cas d'essai : les vingt fiches sont dérivées ; les quatre dimensions sont restituées séparément ; les collisions sont relevées ; une capacité au contrat contesté reçoit `INDETERMINE` ; Atlas et Registre concordent ; la réalité est observée sur un module livré.

> **Contre-épreuve obligatoire** (`ADOPTION-0032`, Art. 3) : sur copie hors dépôt, le domaine d'une capacité est modifié dans l'Atlas seul. Il est constaté que la garde échoue, la concordance Atlas–Registre cessant d'être vraie.

## Article 14 — Les gardes demeurent séparées

Le contrôle Python n'est ni absorbé ni réécrit (`ADOPTION-0027`, Art. 4).

---

# TITRE V — CE QUE CETTE CONCEPTION NE FAIT PAS

## Article 15 — Frontières

**Elle n'arbitre aucune des quatre collisions.** Elle les nomme et s'arrête là. Leur résolution appelle un acte de l'autorité, et la forme en est délicate : réattribuer un numéro déjà servi par du code adopté suppose de décider si l'on renomme le contrat ou la capacité qui le revendique à tort.

**Elle n'établit ni responsable, ni opérateur, ni sortie.** Ces champs dépendent de l'écart d'autorité de l'Article 69, qui demeure bloquant et hors du périmètre de cette capacité.

**Elle ne mesure pas l'exploitation.** La dimension `exploitation` est restituée telle que déclarée — `INACTIVE` pour toutes —, sans vérification : le Core n'est déployé nulle part que le dépôt puisse observer.

---

# TITRE VI — RÉSERVE D'AUDIT

## Article 16 — Rappel

Conception rédigée par l'agent, sous une fonction AUDIT non indépendante. Une précision s'impose : parmi les quatre collisions relevées, **`CTR-10` a été aggravée par l'agent lui-même**. `ADOPTION-0043`, que l'agent a rédigée, affirme « `CTR-10`, déjà nommé par l'Article 50 » sans relever que l'Article 49 le nommait aussi.

L'outil conçu ici a détecté une faute de son propre auteur, commise l'acte précédent. C'est le meilleur argument pour l'outil, et le meilleur argument pour un AUDIT indépendant.

---

# TITRE VII — DÉCISIONS RÉSERVÉES À L'AUTORITÉ

## Article 17 — Points soumis

1. **Les quatre collisions de numéro de contrat** — à trancher par acte, capacité par capacité.
2. **La portée de la règle d'attribution de `ADOPTION-0032`** — s'applique-t-elle rétroactivement aux collisions antérieures (`CTR-08`, `CTR-11`) ?
3. **La fréquence de revue** que l'Article 55 laisse ouverte : la garde en intégration continue en tient lieu de fait, ce qui n'est pas une décision.

## Article 18 — Non-effet

La présente conception ne rend `CAP-CORE-020` ni admise, ni active, n'arbitre aucune divergence, ne modifie le corps d'aucun texte adopté et ne constate pas `G0`.
