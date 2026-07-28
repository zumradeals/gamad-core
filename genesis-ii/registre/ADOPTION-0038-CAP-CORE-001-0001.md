# REGISTRE D'ADOPTION — ADOPTION-0038
## Conception et premier code de `CAP-CORE-001` — Registre des identités, contrat `CTR-01`

> **PROJET D'ACTE — préparé sur la branche `agent/genesis-ii-cap-core-001`.** Il n'entre en vigueur qu'à la fusion `--no-ff` dans `main`, laquelle **est** l'acte d'adoption et appartient exclusivement à l'autorité (`ADOPTION-0024`, Art. 3).

## Nature

Premier acte présenté sous `MISSION-AI-GENESIS-II-CODE-0001` (`ADOPTION-0037`). Conformément à cette mission, **conception et code sont adoptés en un seul acte** au lieu de deux ou trois, et l'agent n'a interrompu ses travaux à aucun moment.

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté la conception `CONCEPTION-CAP-CORE-001-REGISTRE-DES-IDENTITES-0001` et le premier incrément de code de `CAP-CORE-001`.

## Version adoptée

| Objet | Empreinte / commit |
|---|---|
| `genesis-ii/conception/CONCEPTION-CAP-CORE-001-REGISTRE-DES-IDENTITES-0001.md` | `c31816ceb713ae4ed37d9b8958aeb8f16f9323e8` |
| Incrément de code (`core/registre-identites/`, dérivation, routes) | `3581a6e9e07196817dc7d295dc93ed59d16e636f` |

- **Date d'adoption :** 28 juillet 2026 · **Entrée en vigueur :** à la publication sur `main`

---

## Article 1 — Questions portées à l'autorité sans interruption des travaux

Conformément à l'Article 175 du Registre des autorités, les points suivants sont **proposés** et non arbitrés par l'agent. Ils sont déclarés ici, en tête de l'acte.

**1.1 — Cinq invariants nouveaux.** `INV-17` identifiant canonique jamais réattribué ; `INV-18` type d'entité en liste close ; `INV-19` minimalité et données exclues ; `INV-20` assurance distincte de l'existence ; `INV-21` cycle de vie en ajout seul.

**1.2 — Une divergence du corpus, signalée et non tranchée.** Le Registre des produits nomme `PRD-GAMAD-002` « **GAMAD Drive** » à son Article 43 et « **GamaDrive** » à son Article 154. Une même référence, deux dénominations, dans un même texte adopté.

L'agent ne l'a pas corrigée : retenir une dénomination canonique est une **qualification**, hors de sa portée (`ADOPTION-0037`, Art. 3). Le service l'expose par `resoudre_denominations`, qui marque la référence `divergente`. La qualification demeure due.

**1.3 — Une absence.** GAMAD elle-même ne figure pas au registre des identités, aucun texte du corpus ne la déclarant comme entité. L'agent n'a pas suppléé : inscrire une organisation est une qualification.

## Article 2 — Filiation constatée : `CAP-CORE-001` succède à `GAMAD ID`

`ADOPTION-0023` a placé `PRD-GAMAD-001` — GAMAD ID — à l'état **`DISSOUS — IDENTITÉ RENDUE AU CORE`**.

La capacité d'identité n'est donc pas une ambition nouvelle : elle a été **rendue au Core** par la dissolution du produit qui la portait. `CAP-CORE-001` la recueille. Cette filiation n'était inscrite nulle part ; elle l'est désormais au Titre XXII, Article 131 du registre des capacités.

## Article 3 — L'interdit tenu par la structure

La fiche de l'Article 36 exclut du registre : *profil universel, dossiers métier détaillés, réputation globale, jugement moral ou spirituel, agrégation transversale implicite.*

`INV-19` traduit cet interdit non en règle de conduite mais en **absence de colonnes**. Le schéma ne prévoit aucun emplacement pour de telles données ; aucune opération ne peut en restituer. Un registre d'identités souverain qui accumulerait des jugements sur les personnes deviendrait un instrument de pouvoir sur elles — l'interdit est donc tenu par la structure, où il ne dépend d'aucune discipline future.

## Article 4 — Sept entités, et sept seulement

| Type | Références |
|---|---|
| `personne` | `AUT-GAMAD-001` |
| `agent` | `AGENT-IA-001`, `AGENT-IA-002` |
| `produit` | `PRD-GAMAD-001` à `PRD-GAMAD-004` |

Ce chiffre n'est pas une limite du code : c'est l'état réel du corpus. **Aucun dispositif technique ne peuple un registre d'identités ; seuls des actes le font.** Il mesure exactement la distance entre le Core et une exploitation réelle.

## Article 5 — Contre-épreuve de falsification

Conformément à l'Article 3 de `ADOPTION-0032`. Sur copie hors dépôt, la date de `ADOPTION-0023` a été déplacée du 27 au 30 juillet 2026.

| Exécution | Résultat | Code |
|---|---|---|
| Corpus sain | `Preuve P3 : ÉTABLIE` | `0` |
| Corpus falsifié | `Preuve P3 : NON ÉTABLIE (1 écart)` | `1` |

L'écart porte exactement sur le point attendu : au 27 juillet, `PRD-GAMAD-001` est restitué `HISTORIQUE À QUALIFIER` au lieu de `DISSOUS`. Le dépôt n'a pas été modifié par cette expérience.

## Article 6 — Quatrième garde, découverte et non déclarée

`core/registre-identites/tests/identite_p3.php` a été **découverte automatiquement** par la commande de réindexation, sans qu'aucune liste soit modifiée. Le mécanisme introduit par `ADOPTION-0036`, Article 5, produit ici son effet : une garde nouvelle ne peut plus être oubliée d'une énumération.

## Article 7 — Ce que cet incrément ne livre pas

- **Aucune connexion.** `CAP-CORE-001` établit *qui existe*. Prouver qu'on est cette entité relève de `CAP-CORE-005` ; savoir ce qu'on peut faire, de `CAP-CORE-004`. Toutes deux `À ÉTABLIR`.
- Aucun niveau d'assurance n'est dérivé : `INV-20` est conçu, non matérialisé, le corpus ne déclarant d'assurance pour aucune entité.
- Aucun lien gouverné entre entités, prévu par la fiche, n'est livré.
- Aucune trace de fusion ou de scission : le corpus n'en comporte aucune.

## Article 8 — Effets

`CAP-CORE-001` passe en conception `CONÇUE`, implémentation `PARTIELLEMENT MATÉRIALISÉE`, preuve `P3 — TESTÉ` ; exploitation `INACTIVE` inchangée. Constaté au Titre XXII de `REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001`.

Cette adoption ne crée aucune identité, ne nomme personne, n'admet aucun produit, n'accepte aucun risque nouveau, ne franchit pas la frontière des accès réservés et ne constate pas `G0`.

## Réserve d'audit maintenue

Conçu, codé et vérifié par le même agent, sous une fonction AUDIT non indépendante. L'agent est en outre **l'une des sept entités qu'il inscrit** (`AGENT-IA-002`) : il a écrit le registre qui le recense. Le fait est inscrit ; il n'est pas résolu.

## Constat d'exécution

| Chemin | Mise à jour | Empreinte Git après mise à jour |
|---|---|---|
| `genesis-ii/conception/CONCEPTION-CAP-CORE-001-REGISTRE-DES-IDENTITES-0001.md` | Texte adopté (création) | `c31816ceb713ae4ed37d9b8958aeb8f16f9323e8` |
| `genesis-ii/registres/capacites/REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001.md` | Titre XXII (Articles 129-134) | `c6495aec1218adbbc8ce4a83b0a1cfdcab820db4` |
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Article 4 — ligne `ADOPTION-0038` | `ec4b20194e15c33af3b30faba165cd42cd038cc6` |

Ces empreintes remplacent, pour ces fichiers et pour eux seuls, celles déclarées par les actes antérieurs, qui demeurent exactes à leur date. Aucune ligne ou article préexistant n'a été réécrit.

## Vérification des gardes

Garde documentaire : `0`. Gardes de comportement `registre-normes`, `registre-autorites`, `registre-identites` : `0`, `0`, `0`. Contre-épreuve : `1` sur corpus falsifié (Article 5).

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire
- **Date :** 28 juillet 2026 · **Mention :** LU ET ADOPTÉ
