# REGISTRE D'ADOPTION — ADOPTION-0040
## Conception et premier code de `CAP-CORE-004` — moteur d'autorisation, opposabilité des limites du mandat

> **PROJET D'ACTE — préparé sur la branche `agent/genesis-ii-cap-core-004`.** Il n'entre en vigueur qu'à la fusion `--no-ff` dans `main`, laquelle **est** l'acte d'adoption et appartient exclusivement à l'autorité (`ADOPTION-0024`, Art. 3).

## Nature

Cet acte adopte la capacité qui détermine ce que chacun peut faire. Il achève la chaîne d'accès du Core : `CAP-CORE-001` établit qui existe, `CAP-CORE-003` qui a mandat, `CAP-CORE-005` comment on le prouve, `CAP-CORE-004` ce qui est permis.

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté la conception `CONCEPTION-CAP-CORE-004-AUTORISATION-0001` et le premier incrément de code de `CAP-CORE-004`.

## Version adoptée

| Objet | Empreinte / commit |
|---|---|
| `genesis-ii/conception/CONCEPTION-CAP-CORE-004-AUTORISATION-0001.md` | `1cd4138cd58e91133c6fbeef27d23564e26f6577` |
| Incrément de code (`core/registre-autorisation/`, dérivation, routes) | `b81e322801013f5483b50ec0619f0394f6e80890` |

- **Date d'adoption :** 28 juillet 2026 · **Entrée en vigueur :** à la publication sur `main`

---

## Article 1 — La question préalable, et sa réponse

Le Registre des identités compte **une seule personne** ; le Registre des autorités constate **quinze fonctions vacantes**. Répondre « le titulaire peut tout » aurait été une tautologie : elle n'aurait rien ajouté et aurait donné au Core l'apparence d'un contrôle sans le contrôle.

La réponse utile était déjà écrite. L'**Article 49** du Registre des autorités énumère depuis le 26 juillet 2026 ce que le mandat **ne permet pas** :

> falsifier une source ou l'histoire ; attribuer au Fondateur une parole non établie ; transformer une préférence technique en norme supérieure ; effacer injustement une preuve ; convertir le Core en propriété personnelle ; confondre adoption, publication, déploiement et conformité ; prononcer `G0` sans l'acte distinct et les contrôles requis.

Ces sept limites étaient **écrites, non opposables**.

**La valeur de `CAP-CORE-004` aujourd'hui n'est pas de restreindre autrui : elle est de rendre les limites de l'autorité opposables à l'autorité elle-même.**

## Article 2 — Quatre invariants proposés

`INV-27` refus par défaut — l'absence de règle n'est jamais une permission. `INV-28` toute décision restitue son motif, sa politique et sa version. `INV-29` les politiques sont dérivées du corpus, jamais écrites dans le code du moteur. `INV-30` une limite du mandat s'oppose à son titulaire comme à quiconque.

`INV-30` est l'invariant central. Un moteur d'autorisation qui exempterait l'autorité de ses propres bornes ne serait pas un moteur d'autorisation : ce serait une décoration.

## Article 3 — Treize règles, aucune inventée

Six règles `PERMET` dérivées de l'Article 48, sept règles `REFUSE` dérivées de l'Article 49. Les énoncés du corpus sont repris **mot pour mot** comme motifs, sans reformulation, afin que la traduction demeure vérifiable par simple lecture.

Aucun rôle, aucune hiérarchie de privilèges : le corpus n'en déclare pas, et l'agent n'en a pas inventé.

## Article 4 — Ce que le moteur n'empêche pas

Le moteur **n'empêche rien physiquement**. Le titulaire qui détient les accès techniques peut agir sans jamais le consulter ; aucune politique déclarée n'arrête une main qui dispose du dépôt.

Il transforme un franchissement silencieux en **franchissement constatable**. C'est moins qu'un verrou, et c'est tout ce qu'un Core peut offrir tant que la séparation des fonctions n'est pas réelle.

Prétendre le contraire serait la menace `M-29`, que le présent acte déclare non couverte.

## Article 5 — Contre-épreuve de falsification

Sur copie hors dépôt, le refus par défaut et la primauté du `REFUSE` ont été neutralisés.

| Exécution | Résultat | Code |
|---|---|---|
| Code sain | `Preuve P3 : ÉTABLIE` | `0` |
| Contrôles neutralisés | `Preuve P3 : NON ÉTABLIE (5 écarts)` | `1` |

Les écarts portent exactement sur les points neutralisés — et **quatre d'entre eux sont les limites de l'Article 49 qui cessent d'être opposables au titulaire**. C'est la démonstration que l'opposabilité tient au code et non à une déclaration d'intention.

## Article 6 — Ce que cet incrément ne livre pas

- Aucune contrainte physique (Article 4, `M-29`).
- Aucun rôle ni hiérarchie de privilèges.
- Aucune évaluation de finalité, de contexte de risque ni d'environnement, que la fiche prévoit : réservées, faute de texte adopté qui les définisse.
- Aucune trace de décision conservée : la fiche l'exige, l'incrément ne la livre pas.
- **Le rapprochement entre une demande et une règle demeure lexical.** Le moteur ne comprend pas le sens des énoncés ; il les rapproche par leur forme. Une action formulée autrement que le corpus ne la formule sera refusée par défaut — ce qui est le comportement sûr, non le comportement complet.

## Article 7 — Effets

`CAP-CORE-004` passe en conception `CONÇUE`, implémentation `PARTIELLEMENT MATÉRIALISÉE`, preuve `P3 — TESTÉ` ; exploitation `INACTIVE`. Constaté au Titre XXIV de `REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001`.

Cette adoption ne confère ni ne retire aucun pouvoir, n'empêche aucune opération, n'admet aucun produit, n'accepte aucun risque nouveau et ne constate pas `G0`.

## Réserve d'audit maintenue

Conçu, codé et vérifié par le même agent, sous une fonction AUDIT non indépendante. L'agent a écrit le moteur qui déclare les limites de l'autorité dont il exécute les instructions. Il a inscrit `INV-30` — l'opposabilité de ces limites au titulaire — sans que l'autorité le lui demande. Le fait est consigné ; il ne constitue pas une garantie.

## Constat d'exécution

| Chemin | Mise à jour | Empreinte Git après mise à jour |
|---|---|---|
| `genesis-ii/conception/CONCEPTION-CAP-CORE-004-AUTORISATION-0001.md` | Texte adopté (création) | `1cd4138cd58e91133c6fbeef27d23564e26f6577` |
| `genesis-ii/registres/capacites/REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001.md` | Titre XXIV (Articles 141-145) | `d46e25e37f9065de803577c2b2d9d3e2892266f9` |
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Article 4 — ligne `ADOPTION-0040` | `b100f87a019864f0caa797a415aba7d54c01a38e` |

Ces empreintes remplacent, pour ces fichiers et pour eux seuls, celles déclarées par les actes antérieurs, qui demeurent exactes à leur date. Aucune ligne ou article préexistant n'a été réécrit.

## Vérification des gardes

Garde documentaire : `0`. Gardes de comportement `registre-normes`, `registre-autorites`, `registre-identites`, `registre-acces`, `registre-autorisation` : `0` chacune. Contre-épreuve : `1` (Article 5).

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire
- **Date :** 28 juillet 2026 · **Mention :** LU ET ADOPTÉ
