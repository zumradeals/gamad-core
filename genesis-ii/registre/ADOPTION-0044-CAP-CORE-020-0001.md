# REGISTRE D'ADOPTION — ADOPTION-0044
## Conception et premier code de `CAP-CORE-020` — annuaire des capacités, comparaison Atlas–Registre–réalité et relevé des divergences

> **PROJET D'ACTE — préparé sur la branche `agent/genesis-ii-cap-core-020`.** Il n'entre en vigueur qu'à la fusion `--no-ff` dans `main`, laquelle **est** l'acte d'adoption et appartient exclusivement à l'autorité (`ADOPTION-0024`, Art. 3).

## Nature

Le présent acte adopte la **conception** de `CAP-CORE-020` et son **premier code**, dans un même incrément.

Il n'abroge aucun article, ne modifie le corps d'aucun texte adopté et **n'arbitre aucune des divergences qu'il relève**.

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté les dispositions ci-après.

---

## Article 1 — L'ensemble racine de l'Article 61 est complet

L'Article 61 désigne six capacités de référence : sources, normes, autorité, identité, intégrité et cartographie. Avec `CAP-CORE-020`, **les six sont conçues, codées et prouvées `P3`**.

Ce constat porte sur la conception, l'implémentation partielle et la preuve. Il ne porte ni sur l'admission ni sur l'exploitation — `INACTIVE` pour toutes — et **ne constate pas `G0`**.

## Article 2 — Ce que cette capacité était censée faire, et que rien ne faisait

L'Article 55 exige, parmi ses contrôles requis, la « **comparaison Atlas–Registre–réalité** », et nomme cinq risques : carte divergente de la réalité, capacité fantôme, domaine sans responsable, source multiple non arbitrée, annuaire confondu avec l'implémentation.

Aucun mécanisme ne l'opérait. Ces cinq risques n'étaient détectés par rien.

## Article 3 — Quatre numéros de contrat sont revendiqués deux fois

Le relevé produit par `CTR-14` établit ce qui suit :

| Contrat | Revendiqué par | Où |
|---|---|---|
| `CTR-05` | `CAP-CORE-008` — Registre des décisions | Article 43 |
| | `CAP-CORE-005` — Authentification | Titre XXIII, **et le code le sert** |
| `CTR-08` | `CAP-CORE-011` — Registre des produits | Article 46 |
| | `CAP-CORE-012` — Registre des realms | Article 47 |
| `CTR-10` | `CAP-CORE-013` — Audit commun | Article 49 |
| | `CAP-CORE-015` — Preuves d'intégrité | Article 50, **et le code le sert** |
| `CTR-11` | `CAP-CORE-017` — Registre des risques | Article 52 |
| | `CAP-CORE-018` — Registre des incidents | Article 53 |

`CTR-08` et `CTR-11` figurent au Registre initial adopté par `ADOPTION-0015` : ces collisions sont **antérieures à toute règle d'attribution**. `CTR-05` et `CTR-10` touchent en revanche du code en service, exposé par des modules livrés et prouvés.

`ADOPTION-0032`, Art. 2.1 avait arrêté la règle destinée à empêcher précisément cela : les numéros sont attribués dans l'ordre chronologique d'adoption, **jamais par correspondance avec le numéro de la capacité servie**, et ne sont jamais réemployés. `CTR-05` a été donné à `CAP-CORE-005` par correspondance de numéro, alors qu'il était déjà pris.

## Article 4 — Aucune de ces collisions n'est tranchée par le présent acte

Le service les **nomme** et s'arrête là (`INV-38`). Départager deux textes adoptés est un acte de l'autorité, non l'opération d'un outil.

Conséquence inscrite : les huit capacités concernées reçoivent le verdict `INDETERMINE` pour la comparaison au réel, qui est **suspendue** plutôt que devinée. Attribuer un module à l'une des deux revendications serait pire que le silence : la réponse aurait l'apparence d'un constat.

Leur résolution appelle un acte distinct. Sa forme est délicate et l'autorité devra la choisir : réattribuer un numéro déjà servi par du code adopté suppose de décider si l'on renomme le contrat, ou la revendication tenue pour fautive.

## Article 5 — Une dimension d'état sur quatre était dérivée

Le Registre déclare quatre dimensions par capacité — conception, implémentation, exploitation, preuve — et chaque Titre de mise à jour les constate toutes. Le service n'en dérivait qu'**une** : la conception.

Le Core déclarait donc des états d'implémentation et de preuve que rien ne restituait ni ne vérifiait. `CTR-14` dérive les quatre et les tient distinctes (`INV-37`).

## Article 6 — Ce qui concorde

Sur les vingt fiches, `CORE-ATLAS-0001` et le Registre coïncident **sans exception** — libellé et domaine. Le risque de « source multiple non arbitrée » ne se réalise pas sur ce terrain ; il se réalise sur les numéros de contrat, à l'Article 3.

Ce fait est rassurant, et aucun contrôle ne l'avait jamais établi.

## Article 7 — Trois champs ne sont établis pour aucune capacité

**Responsable, opérateur et sortie** sont restitués comme non établis, jamais comblés par une valeur plausible (`INV-39`). C'est la conséquence directe de l'Article 69, qui constate depuis l'origine qu'aucune autorité permanente n'est inscrite et qualifie cet écart de bloquant pour toute prétention opérationnelle.

Le présent acte ne le comble pas ; il le rend mesurable.

## Article 8 — Effets

- `CAP-CORE-020` : conception **`CONÇUE`**, implémentation **`PARTIELLEMENT MATÉRIALISÉE`**, preuve **`P3 — TESTÉ`**.
- `INV-36` à `INV-39` sont introduits ; `M-38` à `M-43` sont retenues.
- L'ensemble racine de l'Article 61 est complet quant à la conception, au code partiel et à la preuve.
- Quatre collisions de numéro de contrat sont inscrites, non résolues.
- Les neuf gardes du dépôt sont portées en intégration continue.

Cet acte ne rend `CAP-CORE-020` ni admise ni active, n'arbitre aucune divergence, n'admet aucun produit, n'accepte aucun risque nouveau, ne franchit pas la frontière des accès réservés et **ne constate pas `G0`**.

## Réserve d'audit maintenue — et un aveu

L'acte est rédigé par l'agent, sous une fonction AUDIT non indépendante.

Une précision s'impose. Parmi les quatre collisions relevées, **`CTR-10` a été aggravée par l'agent lui-même** : `ADOPTION-0043`, qu'il a rédigée, affirme « `CTR-10`, déjà nommé par l'Article 50 » sans relever que l'Article 49 le nommait aussi. La vérification a manqué.

L'outil conçu ici a détecté une faute de son propre auteur, commise à l'acte précédent. C'est à la fois le meilleur argument pour l'outil et le meilleur argument pour un AUDIT indépendant. `ADOPTION-0043` n'est pas réécrite — un texte adopté ne se réécrit pas ; l'erreur est constatée ici et demeure consultable là-bas.

## Constat d'exécution

| Chemin | Mise à jour | Empreinte Git après mise à jour |
|---|---|---|
| `genesis-ii/conception/CONCEPTION-CAP-CORE-020-ANNUAIRE-CAPACITES-0001.md` | Création — conception adoptée | `5981ee12e8e906e4de4133452786394fca5e1fab` |
| `genesis-ii/registres/capacites/REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001.md` | Titre XXVII (Articles 161-169) — état de `CAP-CORE-020` et relevé des divergences | `63490bd7bb3045b5237e5ac7eecf955b06930247` |
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Article 4 — ligne `ADOPTION-0044` | `4e4a7a77c44005d535730f318ca96c65144c60eb` |
| Incrément de code — `CTR-14`, garde `P3`, intégration continue | commit | `76c476f5bbabae554822fa603877949475520b9a` |

Ces empreintes remplacent, pour ces fichiers et pour eux seuls, celles déclarées par les actes antérieurs, qui demeurent exactes à leur date. **Aucune ligne ni article préexistant n'a été réécrit ou supprimé.**

## Article 9 — Vérification des gardes

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
| **`core/registre-annuaire/tests/annuaire_p3.php`** | **`CAP-CORE-020`** | **`0` — nouvelle** |

## Article 10 — Contre-épreuve de falsification (`ADOPTION-0032`, Art. 3)

Le corpus a été copié **hors dépôt** et le domaine de `CAP-CORE-015` y a été modifié **dans l'Atlas seul** — `DOM-09` remplacé par `DOM-01` —, le Registre demeurant inchangé. Le code exécuté est identique ; seul le corpus change, par la variable `CORPUS_PATH`.

| Corpus | Résultat de la garde | Sortie |
|---|---|---|
| Corpus du dépôt, intact | Preuve `P3` **ÉTABLIE** — 0 divergence Atlas/Registre | `0` |
| Copie hors dépôt, Atlas altéré | Preuve `P3` **NON ÉTABLIE** — 1 divergence | `1` |

L'écart relevé est exactement celui attendu : la concordance Atlas–Registre cesse d'être vraie, et le service la rapporte. Le dépôt est demeuré intact pendant l'épreuve.

Un test qui ne peut pas échouer ne prouve rien. Celui-ci peut échouer, et l'on a constaté qu'il échoue.

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire
- **Date :** 28 juillet 2026 · **Mention :** LU ET ADOPTÉ
