# REGISTRE D'ADOPTION — ADOPTION-0050
## Constat de l'état de `CAP-CORE-009` omis par `ADOPTION-0048`, et refus par la garde d'une divergence d'état

> **PROJET D'ACTE — préparé sur la branche `agent/genesis-ii-etat-cap-core-009`.** Il n'entre en vigueur qu'à la fusion `--no-ff` dans `main`, laquelle **est** l'acte d'adoption et appartient exclusivement à l'autorité (`ADOPTION-0024`, Art. 3).

## Nature

Le présent acte répare une omission de `ADOPTION-0048` et tire de cette omission une conséquence sur les gardes.

Il adopte le **Titre XXXI** du Registre initial des capacités souveraines (ajout seul, Articles 191 à 194) et une **extension de la garde** de `CAP-CORE-020`. Il ne réécrit le corps d'aucun texte adopté.

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté les dispositions ci-après.

---

# TITRE I — L'OMISSION

## Article 1 — Ce que le corpus a porté de faux

`ADOPTION-0048` a adopté le premier code de `CAP-CORE-009` et déclaré, à son Article 10, le changement d'état qui en résulte : implémentation `PARTIELLEMENT MATÉRIALISÉE`, preuve `P3 — TESTÉ`.

**Il n'a pas ajouté au Registre initial des capacités le Titre qui porte ce constat.** Le Registre a donc continué de déclarer, pour `CAP-CORE-009`, une implémentation `NON COMMENCÉE` alors qu'un module servait la famille `CTR-06`, et une preuve `P1` alors qu'une garde propre était livrée, éprouvée par contre-épreuve et exécutée en intégration continue.

L'écart a duré deux actes. Il ne portait pas sur un fait obscur : il portait sur ce que l'acte lui-même avait adopté.

## Article 2 — Comment il a été vu

Le mécanisme a fonctionné. La comparaison Atlas–Registre–réalité de l'Article 55 a nommé les deux divergences dès la première interrogation qui a suivi :

> `CAP-CORE-009` : `CODE NON DÉCLARÉ` — module `registre-contrats` présent, implémentation déclarée `NON COMMENCÉE` ; `PREUVE SOUS-DÉCLARÉE` — garde `core/registre-contrats/tests/contrats_p3.php` présente, preuve déclarée `P1`.

L'agent n'a pas vu son omission ; le service qu'il avait écrit l'a vue.

## Article 3 — Pourquoi nommer ne suffisait pas

Aucune garde n'a échoué. La garde de `CAP-CORE-020` comptait les capacités codées et exigeait que ce nombre ne fût ni nul ni total ; elle n'exigeait pas qu'aucune divergence ne demeurât. **Elle savait dire ; elle ne savait pas refuser.**

Un relevé qu'aucune garde ne rend bloquant est une information, non un contrôle. Le corpus a ainsi porté deux actes durant un état que l'acte adopté contredisait, sans qu'aucune sortie ne passât de `0` à `1`.

---

# TITRE II — CE QUE L'ACTE ADOPTE

## Article 4 — Le Titre XXXI

L'état de `CAP-CORE-009` est constaté : conception `CONÇUE`, implémentation **`PARTIELLEMENT MATÉRIALISÉE`**, exploitation `INACTIVE`, preuve **`P3 — TESTÉ`**. Source : `ADOPTION-0048`.

Le Titre prend rang à la date du présent acte, quoiqu'il constate un fait antérieur. L'Article 10 de `ADOPTION-0048` demeure exact à sa date ; il n'est ni réécrit ni corrigé.

L'exploitation demeure `INACTIVE`. Un service qui s'exécute en local n'est pas un service exploité.

## Article 5 — La garde refuse désormais une divergence d'état

La garde de `CAP-CORE-020` est **étendue** : une divergence entre l'état déclaré par le Registre et la réalité observée sur le disque fait échouer la preuve `P3` de la capacité qui a mission de la voir.

Aucune garde n'est ajoutée (`ADOPTION-0035`, Art. 2.2).

Cette extension **n'arbitre aucun écart** et ne contrevient donc pas à `INV-38`. Le service ne corrige rien, ne déclare rien à la place de l'autorité et ne modifie aucun état : il **refuse d'attester** un corpus qui se contredit lui-même. La divergence demeure nommée, avec son motif et son détail ; ce qui change est qu'elle a désormais un coût.

La conséquence est voulue : tout incrément de code futur devra porter au Registre le Titre qui constate son état, faute de quoi la garde de l'annuaire échouera. L'omission que le présent acte répare ne pourra plus se répéter en silence.

---

# TITRE III — PREUVE

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
| `core/registre-contrats/tests/contrats_p3.php` | `CAP-CORE-009` | `0` |
| `core/registre-preuves/tests/preuves_p3.php` | `CAP-CORE-015` | `0` |
| `core/registre-annuaire/tests/annuaire_p3.php` | `CAP-CORE-020` | `0` — étendue |

## Article 7 — Contre-épreuve de falsification (`ADOPTION-0032`, Art. 3)

La falsification porte sur le constat adopté par le présent acte : sur une copie du corpus **hors dépôt**, la ligne du Titre XXXI qui constate l'état de `CAP-CORE-009` est effacée. Le corpus retrouve alors exactement l'état qu'il portait avant le présent acte.

| Corpus | Altération | Résultat | Sortie |
|---|---|---|---|
| Corpus du dépôt, intact | aucune | `P3` **ÉTABLIE** | `0` |
| Copie hors dépôt — témoin | aucune | `P3` **ÉTABLIE** | `0` |
| Copie hors dépôt | ligne de constat d'état effacée | `P3` **NON ÉTABLIE** — les deux divergences reparaissent nommées | `1` |

Cette épreuve établit exactement ce qu'il fallait : **l'état antérieur du corpus fait désormais échouer la garde.** Le témoin établit que l'échec procède de l'altération et non de la copie. Le dépôt est demeuré intact pendant l'épreuve.

---

# TITRE IV — RÉSERVE ET LIMITES

## Article 8 — Ce que cette omission enseigne

C'est la seconde fois en deux actes qu'un défaut du travail de l'agent est trouvé par un mécanisme du corpus et non par l'agent : `ADOPTION-0049` rapportait la première.

La différence entre les deux mérite d'être dite. Le rattachement de `CTR-07` était un défaut ancien du corpus, que l'agent a trouvé. Le présent défaut est de l'agent lui-même, dans l'acte qu'il venait d'écrire, et il portait sur ce que cet acte déclarait.

La réserve d'audit non indépendant de `ADOPTION-0025`, Art. 3, n'est pas une formule de style. Le concepteur ne s'audite pas : ce sont des mécanismes écrits d'avance, et l'AUDIT humain, qui le contrôlent.

## Article 9 — Effets et non-effets

Aucun invariant, aucune menace, aucun contrat nouveau. Aucun état n'est modifié pour aucune capacité autre que `CAP-CORE-009`, dont l'état est **constaté** et non changé — le changement procède de `ADOPTION-0048`.

`CAP-CORE-020` demeure conception `CONÇUE`, implémentation `PARTIELLEMENT MATÉRIALISÉE`, exploitation `INACTIVE`, preuve `P3 — TESTÉ` : une extension de garde n'est pas un changement d'état.

Le présent acte ne rend aucune capacité admise ni active, n'opère aucun déploiement, n'accepte aucun risque nouveau, ne nomme aucun responsable, ne franchit pas la frontière des accès réservés (`ADOPTION-0025`, Art. 3) et **ne constate pas `G0`**.

## Réserve d'audit maintenue

L'acte est rédigé par l'agent, sous une fonction AUDIT non indépendante.

## Constat d'exécution

| Chemin | Mise à jour | Empreinte Git après mise à jour |
|---|---|---|
| `genesis-ii/registres/capacites/REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001.md` | Titre XXXI — Articles 191 à 194 (ajout seul) | `b0df82ed053d2d6d19052485329d62e641e21b5f` |
| Incrément de code — extension de la garde de `CAP-CORE-020` | commit | `10d77de796d8f2e3e5be1bd622af1812933b731c` |
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Article 4 — ligne `ADOPTION-0050` | `a36b8540f91a8a5629afbf37aa7762885d9ba1d9` |

Ces empreintes remplacent, pour ces fichiers et pour eux seuls, celles déclarées par les actes antérieurs, qui demeurent exactes à leur date. **Aucune ligne ni article préexistant n'a été réécrit ou supprimé.**

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire
- **Date :** 29 juillet 2026 · **Mention :** LU ET ADOPTÉ
