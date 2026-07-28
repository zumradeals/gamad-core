# REGISTRE D'ADOPTION — ADOPTION-0031
## Rectification de la portée de la preuve `P3` de `CAP-CORE-007` et dérivation des statuts depuis le corpus

> **PROJET D'ACTE — la forme de la rectification a été arrêtée par l'autorité (portée restreinte, sans rétrogradation en `P2`). Non exécuté, non signé tant que la fusion `--no-ff` dans `main` — laquelle **est** l'acte d'adoption — n'a pas été effectuée par l'autorité elle-même (`ADOPTION-0024`, Art. 3).**

## Nature

Le présent acte est un **acte de rectification**. Il ne réécrit aucun texte adopté : il ajoute un Titre au registre des capacités et corrige le code pour le mettre en conformité avec une conception déjà adoptée. Il est le premier acte du corpus dont l'objet est de corriger le corpus lui-même.

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté la rectification décrite ci-après, ainsi que l'incrément de code qui la porte.

## Version adoptée

| Objet | Branche de préparation | Commit adopté |
|---|---|---|
| Rectification du cœur `core/registre-normes/src/Ingestion.php` | `agent/genesis-ii-rectification-p3-ctr-04` | `1f0ee4f5c37a462539b264e6d613828ee23288b0` |

- **Version :** `0.2`
- **Date d'adoption :** 28 juillet 2026
- **Entrée en vigueur :** immédiate, à la publication sur `main`

---

## Article 1 — Le fait rectifié

La méthode `Ingestion::amorcerFaitsP3()`, adoptée par `ADOPTION-0029`, inscrivait les deux états datés de `CAP-CORE-007` sous forme de **constantes du code source** :

```php
$this->insererStatut($versionId, 'EN CONCEPTION', '2026-07-26', 'ADOPTION-0015');
$this->insererStatut($versionId, 'CONÇUE',        '2026-07-27', 'ADOPTION-0026');
```

C'était le **seul** endroit du programme où un statut était jamais écrit. Le test `P3` relisait ensuite exactement ces deux valeurs. Il en découlait deux conséquences, l'une sur la preuve, l'autre sur le service.

**Sur la preuve.** Le test ne pouvait détecter aucune altération du corpus : les valeurs qu'il vérifiait ne venaient pas du corpus. La preuve se prouvait elle-même.

**Sur le service.** La table `statut` comptait deux lignes, portant sur une seule des soixante-sept normes indexées. Les soixante-six autres étaient restituées sans statut, donc `en_vigueur: false`. Le service exposé publiquement déclarait ainsi que l'`ACTE-DE-CONSTAT-G0-0001` — l'acte qui a ouvert la Porte — n'était pas en vigueur.

## Article 2 — Conformité à une conception déjà adoptée

La correction n'introduit aucune conception nouvelle. `ADOPTION-0028`, Titre III, Article 8, prescrivait déjà que l'ingestion lise « les actes d'adoption du répertoire `genesis-ii/registre/`, **les fichiers de statut**, l'index […] et remplisse l'index ». Le code ne l'avait jamais fait. Le présent acte met le code en conformité avec la conception adoptée ; il ne la modifie pas.

## Article 3 — La correction apportée

| Origine du statut | Avant | Après |
|---|---|---|
| Statut d'une norme | aucun | acte le plus récent liant le fichier ; libellé et date lus à l'index |
| États de `CAP-CORE-007` | deux constantes du code | tableau de l'Article 31, puis chaque Titre de mise à jour et son acte source |
| Libellé non reconnu | — | `INDETERMINE` (le service déclare son ignorance, il ne présume pas) |
| Lignes de la table `statut` | 2, sur 1 norme | 68, sur la totalité des versions |

## Article 4 — Preuve de la rectification, par falsification

L'affirmation « la preuve mord désormais » ne se vérifie pas en constatant que le test passe — c'était précisément le défaut. Elle se vérifie en montrant que le test **peut échouer**.

Un corpus de test a été copié hors du dépôt, puis délibérément falsifié : la date d'`ADOPTION-0026` y a été déplacée du 27 au 30 juillet 2026. Les deux versions du code ont été exécutées contre ce même corpus falsifié.

| Code exécuté | Résultat sur corpus falsifié | Code de sortie |
|---|---|---|
| Avant rectification (état de `main`, `ADOPTION-0030`) | `Preuve P3 : ÉTABLIE` | `0` — **aveugle** |
| Après rectification (commit adopté) | `Preuve P3 : NON ÉTABLIE (1 écart)` | `1` — **détecte** |

Le dépôt n'a pas été modifié par cette expérience.

## Article 5 — Portée de la preuve `P3`, telle que rectifiée

Conformément à la décision de l'autorité, le niveau `P3 — TESTÉ` est **maintenu** et sa portée est **précisée**, sans rétrogradation en `P2`. Le comportement éprouvé satisfaisait bien la définition de l'Article 17 du registre des capacités ; c'est l'énoncé de portée qui excédait ce qui était prouvé.

La preuve `P3` porte désormais sur la reconstruction temporelle **dérivée du corpus**, avec capacité d'échec démontrée. Sa portée demeure **bornée à l'état de conception de `CAP-CORE-007`**, seule capacité disposant à ce jour d'une conception adoptée. Le présent acte ne prétend pas au-delà, et il importe qu'il ne le fasse pas : la faute rectifiée ici est née d'un énoncé plus large que sa preuve.

## Article 6 — Écart signalé, non corrigé

La table `statut` accueille deux vocabulaires — statut de norme et état de conception de capacité — dans une même colonne. C'est un défaut de modèle. Il est signalé ici et **délibérément non corrigé** : sa correction appartient à la conception de `CAP-CORE-006` et `CAP-CORE-020`, où le rang et l'identité des normes seront eux-mêmes fondés. Sont également reportés à ces travaux : le rang de norme inscrit en dur (`'texte canonique'`) et l'identité d'une norme dérivée de son nom de fichier.

## Article 7 — Ce que cet acte enseigne sur la réserve d'audit

`ADOPTION-0030`, adopté la veille, reconduisait la preuve `P3` par la mention « inchangée ». L'agent avait vérifié que le test sortait `0` ; il n'avait pas vérifié ce que le test prouvait. Un contrôle qui constate qu'une garde est verte sans examiner ce qu'elle garde n'est pas un contrôle.

Ce fait n'est pas consigné par contrition mais parce qu'il documente la réserve d'`ADOPTION-0025`, Art. 3.b : la fonction AUDIT, exercée par l'agent qui conçoit et qui code, a laissé passer un défaut de raisonnement pendant deux actes consécutifs. Il a été relevé lors d'une relecture d'architecture, non par une garde. **Aucune garde automatique n'aurait pu le relever** : le défaut portait sur le sens de la preuve, non sur son exécution. La réserve d'audit non indépendant demeure entière et ce constat en mesure le coût réel.

## Article 8 — Effets

- `CAP-CORE-007` demeure : conception `CONÇUE`, implémentation `PARTIELLEMENT MATÉRIALISÉE`, exploitation `INACTIVE`, preuve `P3 — TESTÉ` de portée précisée. Aucun état n'est modifié par le présent acte ; seule la portée d'un énoncé est rectifiée.
- Ce fait est constaté au Titre XVII de `REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001`, sans réécriture d'aucun article antérieur.

Cette adoption n'admet aucun produit, n'accepte aucun risque nouveau, ne franchit pas la frontière des accès réservés (`ADOPTION-0025`, Art. 3.a) et ne constate pas `G0`.

## Constat d'exécution

| Chemin | Mise à jour | Empreinte Git après mise à jour |
|---|---|---|
| `genesis-ii/registres/capacites/REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001.md` | Titre XVII (Articles 103-108) — rectification de la portée de `P3` | `1ddbc6bc8c11c57e575bcb667bfb3a59a11d8b39` |
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Article 4 — ajout de la ligne `ADOPTION-0031` | `c24739b2b275113ebe7d30d764b8c77530b67fef` |

Ces empreintes remplacent, pour ces deux fichiers et pour eux seuls, celles déclarées par `ADOPTION-0030`, qui demeurent exactes à leur date. Aucune ligne ou article préexistant n'a été réécrit.

## Vérification des deux gardes

- **Garde 1** (`outils/verifier-integrite.py`) : `VÉRIFIÉE`, code de sortie `0`.
- **Garde 2** (`core/registre-normes/tests/temporel_p3.php`) : `ÉTABLIE`, code de sortie `0`, et **`NON ÉTABLIE`, code `1`, contre un corpus falsifié** (Article 4) — c'est cette seconde exécution qui donne à la première sa valeur.

## Publication

La fusion `--no-ff` dans `main` **est** l'acte d'adoption ; elle appartient exclusivement à l'autorité et n'est pas exécutée par l'agent.

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire
- **Date :** 28 juillet 2026
- **Entrée en vigueur :** immédiate, à la publication sur `main`
- **Mention :** LU ET ADOPTÉ
