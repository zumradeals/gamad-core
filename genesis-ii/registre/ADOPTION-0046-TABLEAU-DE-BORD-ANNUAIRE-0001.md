# REGISTRE D'ADOPTION — ADOPTION-0046
## Point d'entrée de consultation de l'annuaire des capacités (`CAP-CORE-020`)

> **PROJET D'ACTE — préparé sur la branche `agent/genesis-ii-annuaire-tableau-de-bord`.** Il n'entre en vigueur qu'à la fusion `--no-ff` dans `main`, laquelle **est** l'acte d'adoption et appartient exclusivement à l'autorité (`ADOPTION-0024`, Art. 3).

## Nature

Le présent acte adopte un **incrément de code** de faible portée : le point d'entrée de consultation du service `CTR-14`, déjà adopté par `ADOPTION-0044` et rectifié par `ADOPTION-0045`.

Il n'introduit aucune capacité, aucun contrat, aucun invariant. Il ne modifie le corps d'aucun texte adopté.

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté les dispositions ci-après.

---

## Article 1 — Ce que le défaut coûtait

Le module `core/registre-annuaire/` ne comportait que `src/` et `tests/`. Consulter l'état des vingt capacités souveraines exigeait de lancer la garde `P3` de la capacité et d'en lire la sortie.

**Une capacité dont l'état n'est lisible qu'en exécutant sa propre preuve n'est pas consultable par l'autorité.** L'annuaire existait pour être interrogé ; il ne pouvait l'être que par son auteur.

Le module `core/registre-normes/` portait un tableau de bord depuis `ADOPTION-0029`. L'écart n'était pas doctrinal, seulement inachevé.

## Article 2 — Ce que la vue restitue

- Les vingt capacités et leurs **quatre dimensions d'état** tenues distinctes (`INV-37`), confrontées au module, à la garde et à son exécution en intégration continue.
- Les **seize familles de contrat**, leur domaine gardien, et les trois partages réguliers signalés comme tels (`INV-40`).
- Les **modules présents** et la capacité que chacun déclare servir (`INV-41`).
- Les **champs non établis**, restitués comme tels et jamais comblés (`INV-39`).
- La **concordance Atlas–Registre** et le relevé des divergences, nommées et non arbitrées (`INV-38`).

## Article 3 — Ce que la vue n'est pas

La vue est en **lecture seule** et **sans base de données** : elle relève les fichiers du dépôt à chaque affichage. Aucune écriture applicative, aucun index persistant, aucun état conservé entre deux consultations.

L'annuaire décrit ; il ne fonde rien (`INV-36`). Ce que la page montre n'a pas plus d'autorité que les textes dont elle procède, et son affichage ne constitue ni une publication, ni une admission, ni une mise en service.

## Article 4 — La garde est étendue au point d'entrée

Aucune garde n'est ajoutée : la garde de `CAP-CORE-020` est **étendue**, conformément à `ADOPTION-0035`, Art. 2.2 — une capacité, une garde.

La page est rendue **en mémoire**, sur le corpus de la garde, et trois faits sont éprouvés : elle se rend sans erreur, elle restitue les vingt capacités, elle ne laisse échapper aucun diagnostic PHP. Une page qui cesserait de se rendre ferait désormais échouer la preuve `P3` de la capacité.

## Article 5 — Effets

- `CAP-CORE-020` demeure conception `CONÇUE`, implémentation `PARTIELLEMENT MATÉRIALISÉE`, exploitation **`INACTIVE`**, preuve `P3 — TESTÉ`. **Aucune dimension n'est modifiée.**
- Aucun invariant, aucune menace, aucun contrat n'est introduit.
- Aucune ligne du corpus n'est réécrite ; le présent acte n'ajoute aucun Titre à aucun registre.

L'existence d'une vue web ne rend pas la capacité active : l'exploitation demeure `INACTIVE` tant qu'aucun acte de mise en service n'est adopté, et aucun déploiement n'est opéré. Confondre une page rendue en local avec une exploitation serait précisément le raccourci que l'Article 68 du Registre initial des capacités interdit.

## Réserve d'audit maintenue

L'acte est rédigé par l'agent, sous une fonction AUDIT non indépendante.

## Constat d'exécution

| Chemin | Mise à jour | Empreinte Git après mise à jour |
|---|---|---|
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Article 4 — ligne `ADOPTION-0046` | `dec5271afbc30664bf3c0a36b200c86d3da2892c` |
| Incrément de code — point d'entrée et extension de la garde | commit | `8513ebd45cac5384c6a8fb0d8cac58ca605ec49e` |

Ces empreintes remplacent, pour ces fichiers et pour eux seuls, celles déclarées par les actes antérieurs, qui demeurent exactes à leur date. **Aucune ligne ni article préexistant n'a été réécrit ou supprimé.**

## Article 6 — Vérification des gardes

| Garde | Capacité | Sortie |
|---|---|---|
| `outils/verifier-integrite.py` — documentaire | — | `0` |
| `core/registre-identites/tests/identite_p3.php` | `CAP-CORE-001` | `0` |
| `core/registre-autorites/tests/mandat_p3.php` | `CAP-CORE-003` | `0` |
| `core/registre-autorisation/tests/autorisation_p3.php` | `CAP-CORE-004` | `0` |
| `core/registre-acces/tests/authentification_p3.php` | `CAP-CORE-005` | `0` |
| `core/registre-sources/tests/sources_p3.php` | `CAP-CORE-006` | `0` |
| `core/registre-normes/tests/temporel_p3.php` | `CAP-CORE-007` | `0` |
| `core/registre-preuves/tests/preuves_p3.php` | `CAP-CORE-015` | `0` |
| `core/registre-annuaire/tests/annuaire_p3.php` | `CAP-CORE-020` | `0` — étendue |

## Article 7 — Contre-épreuve de falsification (`ADOPTION-0032`, Art. 3)

La contre-épreuve de `ADOPTION-0045` est reconduite sur la garde étendue : le corpus est copié **hors dépôt** et le domaine gardien de la famille `CTR-04` y est déplacé de `DOM-01` à `DOM-03`, dans l'Atlas seul.

| Corpus | Résultat de la garde | Sortie |
|---|---|---|
| Corpus du dépôt, intact | Preuve `P3` **ÉTABLIE** | `0` |
| Copie hors dépôt, non altérée — témoin | Preuve `P3` **ÉTABLIE** | `0` |
| Copie hors dépôt, domaine gardien déplacé | Preuve `P3` **NON ÉTABLIE** | `1` |

Le témoin établit que l'échec procède de l'altération et non de la copie. Le dépôt est demeuré intact pendant l'épreuve.

## Article 8 — Non-effet

Le présent acte ne rend aucune capacité admise ni active, n'opère aucun déploiement, n'admet aucun produit, n'accepte aucun risque nouveau, ne nomme aucun responsable, ne franchit pas la frontière des accès réservés (`ADOPTION-0025`, Art. 3) et **ne constate pas `G0`**.

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire
- **Date :** 28 juillet 2026 · **Mention :** LU ET ADOPTÉ
