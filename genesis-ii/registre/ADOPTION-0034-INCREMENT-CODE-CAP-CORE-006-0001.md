# REGISTRE D'ADOPTION — ADOPTION-0034
## Premier incrément de code de `CAP-CORE-006` — identité canonique, rang fondé et séparation des vocabulaires

> **PROJET D'ACTE — préparé sur la branche `agent/genesis-ii-code-cap-core-006`.** Il n'entre en vigueur qu'à la fusion `--no-ff` dans `main`, laquelle **est** l'acte d'adoption et appartient exclusivement à l'autorité (`ADOPTION-0024`, Art. 3).

## Nature

Le présent acte adopte le **premier incrément de code de `CAP-CORE-006`**, écrit sur la conception adoptée par `ADOPTION-0032` et après la rectification d'intégrité `ADOPTION-0033`, qui avait suspendu ces travaux.

Il livre les trois objets dans l'ordre arrêté par l'autorité, lequel plaçait délibérément la substance de la capacité en dernier.

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté le premier incrément de code de `CAP-CORE-006`.

## Version adoptée

| Objet | Branche de préparation | Commit adopté |
|---|---|---|
| Incrément de code `CAP-CORE-006` (`core/registre-normes/`, `apps/console-laravel/`) | `agent/genesis-ii-code-cap-core-006` | `e6280c9245a182533d5813780f64be7bf46a1da7` |

- **Version :** `0.3`
- **Date d'adoption :** 28 juillet 2026
- **Entrée en vigueur :** immédiate, à la publication sur `main`

---

# TITRE I — LES TROIS OBJETS LIVRÉS

## Article 1 — Objet 1 : la commande de réindexation

`php artisan registre:reindexer` solde la dette contractée par `ADOPTION-0031`, où la réindexation de production avait dû passer par un script rédigé hors du dépôt.

Conformément à l'Article 16 de la conception, elle **refuse de s'exécuter** si les deux gardes ne sont pas vertes : un index reconstruit depuis un corpus incohérent propagerait l'incohérence. Elle invoque les deux gardes comme deux programmes distincts, sans les absorber ni les réécrire (`ADOPTION-0027`, Art. 4).

## Article 2 — Objet 2 : la séparation des vocabulaires (`INV-10`)

Une table `etat_capacite` distincte accueille les états des capacités souveraines, qui quittent la table `statut`. Une opération `resoudre_capacite` leur est propre.

`en_vigueur` n'y est **pas** calculé : la question n'a pas de sens pour une capacité. C'était l'anomalie visible en production, où `CAP-CORE-007`, au 26 juillet 2026, était restituée `EN CONCEPTION` et pourtant `en_vigueur: true`. Une règle propre aux normes s'appliquait à un état de capacité.

`resoudre_norme('CAP-CORE-007')` retourne désormais `null` : une capacité n'est pas une norme.

**Gain non prévu.** En supprimant la correspondance capacité/document inscrite en dur, l'ingestion s'est mise à dériver les vingt capacités et non plus la seule `CAP-CORE-007` — vingt-deux états au total. L'angle mort constaté à l'Article 4 de `ADOPTION-0032`, qui annonçait `CAP-CORE-006` adoptée `CONÇUE` mais non restituable par le service, **est comblé**, et temporellement exact : `EN CONCEPTION` avant son adoption, `CONÇUE` après.

## Article 3 — Objet 3 : identité canonique et rang fondé (`INV-7`, `INV-8`)

**`INV-7`.** Une norme n'est plus identifiée par son nom de fichier. La correspondance est **dérivée** des feuilles de statut : lorsque `X-STATUT.md` accompagne un texte, `X` est sa référence canonique. Rien n'est inscrit en dur ; renommer un fichier ne change pas l'identité du texte qu'il porte.

Cette correction a révélé que le service portait **le même angle mort que le contrôle documentaire avant `ADOPTION-0033`** : ne lisant pas les feuilles de statut, il n'indexait pas les textes fondateurs. Les Lois, les quatre gouvernances, le lexique, la charte, l'atlas et les sources n'entraient tout simplement pas dans l'index. Ils y entrent désormais, chacun avec l'empreinte déclarée par sa feuille et le statut fondé sur son acte. L'index passe de 69 à **82 normes**.

```
resoudre_norme('CORE-LAWS-0001')
  → EN VIGUEUR, ADOPTION-0013, empreinte 2cd8a110…
```

Avant le présent incrément, cette interrogation répondait « norme introuvable ».

**`INV-8`.** Une table `rang_normatif` est dérivée des en-têtes des Articles 25 à 33 de `SOURCES-0001` — neuf rangs, du bloc patrimonial fondateur aux implémentations. La chaîne littérale `'texte canonique'`, qui n'était pas un rang mais un remplissage, disparaît.

**Aucune norme ne reçoit de rang.** Le motif est à l'Article 116 du registre des capacités : `SOURCES-0001` énumère les rangs en prose sans désigner de texte nommé, et établir cette correspondance est une qualification réservée à l'autorité. Les quatre-vingt-deux normes portent `INDETERMINE`, et ce décompte est **exposé** sur les deux tableaux de bord. Une ignorance chiffrée et visible vaut mieux qu'un remplissage silencieux.

## Article 4 — Ajouts connexes

- Table `source`, dérivée des Articles 5 et 8 du registre initial des sources : vingt-cinq sources, fondatrices et institutionnelles.
- Opération `resoudre_source` et route `GET /sources/{reference}`.
- `INV-9` matérialisé : l'authenticité et le statut d'adoption sont rendus côte à côte sans jamais se confondre. `SRC-0001` — les Statuts du Mouvement — est `AUTH-1 — PROVENANCE DÉCLARÉE`, avec sa réserve sur les signatures, et n'est pas versionnée ; `CORE-LAWS-0001` est `AUTH-3` **et** `EN VIGUEUR`. Deux axes distincts.

Aucune route d'écriture n'est créée (`INV-4`). Les verbes `POST`, `PUT`, `PATCH` et `DELETE` retournent `405`.

---

# TITRE II — GARDES ET PREUVES

## Article 5 — La garde `P3` a été modifiée, et pourquoi l'autorité l'a autorisé

La séparation des vocabulaires (Article 2) a une conséquence que la conception n'avait pas anticipée : le test `P3`, qui résolvait `CAP-CORE-007` par `resoudre_norme`, ne pouvait plus la trouver.

L'agent a **arrêté ses travaux et soumis la question** avant d'écrire une ligne, trois options à l'appui. L'autorité a retenu la migration du test vers `resoudre_capacite`, seule option obtenant réellement la séparation adoptée — les deux autres déplaçaient le défaut ou le différaient.

Deux protections encadrent cette modification :

1. **Les cas d'essai, les dates et les valeurs attendues sont inchangés.** Seul l'appel diffère.
2. **La contre-épreuve a été refaite après migration**, pour établir que le test migré échoue toujours sur un corpus altéré. Un test qu'on ajusterait pour qu'il passe ne serait plus une garde.

## Article 6 — Contre-épreuve de falsification

Conformément à l'Article 3 de `ADOPTION-0032`, qui l'exige désormais pour toute preuve `P3` du Core :

| Exécution | Résultat | Code |
|---|---|---|
| Test migré, corpus sain | `Preuve P3 : ÉTABLIE` | `0` |
| Test migré, corpus falsifié — date d'`ADOPTION-0026` déplacée du 27 au 30 juillet, sur copie hors dépôt | `Preuve P3 : NON ÉTABLIE (1 écart)` | `1` |

La migration n'a pas affaibli la preuve. Le dépôt n'a pas été modifié par cette expérience.

## Article 7 — Les deux gardes

- **Garde 1** (`outils/verifier-integrite.py`) : `VÉRIFIÉE`, code `0`, 83 fichiers.
- **Garde 2** (`core/registre-normes/tests/temporel_p3.php`) : `ÉTABLIE`, code `0`.

---

# TITRE III — CE QUE CET INCRÉMENT NE LIVRE PAS

## Article 8 — Le niveau de preuve de `CAP-CORE-006` demeure `P1`

L'Article 19 de la conception adoptée prescrivait un essai `P3` propre à `CAP-CORE-006` — `resoudre_source('SOURCES-0001')` avec sa contre-épreuve. **Il n'est pas livré.**

Le motif n'est pas technique. L'écrire créerait une **troisième garde**, alors que la discipline du dépôt en pose deux, séparées à dessein. Le nombre et la répartition des gardes engagent la doctrine du Core et relèvent de l'autorité, non de l'agent. C'est une décision réservée (Article 11).

L'agent écarte expressément le raisonnement qui consisterait à invoquer les deux gardes existantes pour revendiquer un niveau supérieur : elles éprouvent le comportement de `CTR-04`, non celui de `CTR-09`. **Une capacité n'hérite pas de la preuve d'une autre.**

## Article 9 — Le contrat `CTR-09` est partiellement livré

`resoudre_source` est exposée. `verifier_authenticite` et `resoudre_lignee` ne le sont pas. La table `lignee_source` prévue à l'Article 9 de la conception n'est pas créée : `INV-11` — non-effacement de la provenance — demeure conçu et non matérialisé.

## Article 10 — Ce qui demeure ouvert

- Le rang de chaque norme, qui appelle un acte de qualification.
- La mise à jour du Registre des sources, dont l'Article 7 énonce encore « dix-neuf textes adoptés ».
- Les sources `GENESIS-003` et `GENESIS-005`, maintenues `NON ÉTABLI`.

---

# TITRE IV — DÉCISION RÉSERVÉE ET RÉSERVE D'AUDIT

## Article 11 — Décision réservée à l'autorité

**Le nombre de gardes du dépôt.** `CAP-CORE-006` ne peut atteindre `P3` sans un essai propre à son contrat, et cet essai serait une troisième garde. L'autorité peut : en autoriser une troisième ; décider que chaque capacité codée apporte la sienne, la doctrine passant de « deux gardes » à « une garde documentaire et une garde par capacité » ; ou maintenir deux gardes et laisser `CAP-CORE-006` en `P1`.

L'agent ne trancherait pas cette question sans instruction : elle fixe la forme que prendra la preuve dans le Core pour les dix-huit capacités à venir.

## Article 12 — Réserve d'audit maintenue

L'incrément est conçu, codé et vérifié par le même agent, sous une fonction AUDIT non indépendante (`ADOPTION-0025`, Art. 3.b). Deux constats de la même journée — `ADOPTION-0031` et `ADOPTION-0033` — ont établi que cette réserve a un coût mesurable : dans les deux cas, le défaut fut relevé par une relecture d'architecture et par aucune garde.

Le présent incrément illustre la même leçon en sens inverse : c'est en cherchant à appliquer `INV-7` que le service s'est révélé porteur du même angle mort que le contrôle documentaire — non parce qu'un test l'a signalé, mais parce qu'une correction en a croisé une autre.

## Article 13 — Effets

- `CAP-CORE-006` passe en implémentation `PARTIELLEMENT MATÉRIALISÉE` ; conception `CONÇUE`, exploitation `INACTIVE`, preuve `P1` inchangées. Constaté au Titre XIX de `REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001`.
- `CAP-CORE-007` demeure inchangée dans tous ses états ; son contrat `CTR-04` gagne l'identité canonique et le rang, sans que son niveau de preuve varie.

Cette adoption n'admet aucun produit, n'accepte aucun risque nouveau, ne franchit pas la frontière des accès réservés (`ADOPTION-0025`, Art. 3.a) et ne constate pas `G0`.

---

## Constat d'exécution

| Chemin | Mise à jour | Empreinte Git après mise à jour |
|---|---|---|
| `genesis-ii/registres/capacites/REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001.md` | Titre XIX (Articles 113-117) — `CAP-CORE-006` en implémentation `PARTIELLEMENT MATÉRIALISÉE` | `4fbd944a7db609cec5b88c399c28358c92c0fbc2` |
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Article 4 — ajout de la ligne `ADOPTION-0034` | `cdde8deccf9a5958fa8402be785dfe5caf3ee82f` |

Ces empreintes remplacent, pour ces deux fichiers et pour eux seuls, celles déclarées par `ADOPTION-0032` et `ADOPTION-0033`, qui demeurent exactes à leur date. Aucune ligne ou article préexistant n'a été réécrit.

## Publication

La fusion `--no-ff` dans `main` **est** l'acte d'adoption ; elle appartient exclusivement à l'autorité et n'est pas exécutée par l'agent.

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire
- **Date :** 28 juillet 2026
- **Entrée en vigueur :** immédiate, à la publication sur `main`
- **Mention :** LU ET ADOPTÉ
