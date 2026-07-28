# REGISTRE D'ADOPTION — ADOPTION-0029
## Premier incrément de code du service `CTR-04` (`CAP-CORE-007`)

## Nature

Le présent acte adopte le **premier incrément de code canonique du Core**, écrit après le constat de `G0` (`ADOPTION-0025`) sur la conception adoptée (`ADOPTION-0026`), la pile (`ADOPTION-0027`) et la conception d'implémentation (`ADOPTION-0028`). C'est le premier acte qui adopte du code, non un texte : le code est identifié par son commit Git, empreinte de l'incrément entier.

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté le premier incrément de code du service `CTR-04`, situé sous `core/registre-normes/`.

## Version adoptée

| Objet | Branche de préparation | Commit adopté |
|---|---|---|
| Incrément de code `core/registre-normes/` (service `CTR-04`) | `agent/genesis-ii-code-ctr-04` | `2e9ded22a122801953245f7014e7424d67f810f4` |

- **Version :** `0.1`
- **Date d'adoption :** 27 juillet 2026
- **Entrée en vigueur :** immédiate, à la publication sur `main`

Le commit désigné identifie l'état exact du code adopté. Toute évolution ultérieure fera l'objet d'un nouvel incrément et d'un nouvel acte.

## Contenu de l'incrément

- `GitBlob` — empreinte `git hash-object` recalculée en PHP, validée identique octet pour octet (`INV-1`) ;
- `Schema` — les cinq tables PostgreSQL/SQLite ; invariants portés par les contraintes ; ajout seul tenu par le code (`INV-3`) ;
- `Ingestion` — dérivation à sens unique depuis les fichiers, empreintes recalculées et non recopiées, idempotente (`INV-5`) ;
- `Ctr04` — `resoudre_norme` (temporel), `verifier_integrite`, `resoudre_index` ; lecture et attestation seulement (`INV-4`) ;
- `tests/temporel_p3` — preuve `P3` de reconstruction temporelle ;
- `public/index.php` — tableau de bord en lecture seule ;
- `nixpacks.toml`, `README.md` — déploiement Railway (premier regard en SQLite, sans secret) ;
- `.github/workflows/registre-normes.yml` — preuve `P3` en intégration continue, garde distincte du contrôle documentaire Python (`ADOPTION-0027`, Art. 4).

## Effets

- `CAP-CORE-007` passe en implémentation `PARTIELLEMENT MATÉRIALISÉE` et atteint le niveau de preuve `P3 — TESTÉ` ; l'exploitation demeure `INACTIVE`. Ce fait est constaté au Titre XV de `REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001`, sans réécriture d'aucun article antérieur.
- La preuve `P3` que l'Article 73 du registre des capacités signalait manquante est produite.

Cette adoption :

- ne rend `CAP-CORE-007` ni admise, ni active au sens de l'exploitation ; elle n'autorise aucun déploiement par elle-même ;
- ne franchit pas la frontière des accès réservés : tout hébergement, base gérée ou secret demeure du ressort exclusif de l'autorité (`ADOPTION-0025`, Art. 3.a) ;
- n'admet aucun produit, n'accepte aucun risque nouveau et ne modifie le corps d'aucun texte adopté.

## Article 1 — Écart de cadre, expressément traité

`ADOPTION-0027` a retenu **Laravel** comme cadre applicatif. Le présent incrément livre le cœur porteur des invariants en **PHP indépendant du cadre**, afin qu'il soit testable sans réseau ni dépendance, la couche de livraison Laravel restant à poser au moment du déploiement.

Cet écart n'est pas dissimulé : il est constaté ici. Il est conforme au principe « invariants avant technologie » de l'Article 63 — le cœur testable d'abord, le cadre de livraison ensuite. L'autorité, par l'adoption du présent acte, **accepte cette approche** : Laravel demeure le cadre de livraison retenu (`ADOPTION-0027` inchangé), la couche Laravel étant ajoutée dans un incrément ultérieur sans remettre en cause le cœur adopté. Si l'autorité préférait imposer Laravel dès cet incrément, elle refuse le présent acte et le fait savoir.

## Réserve d'audit maintenue

L'incrément est conçu et vérifié par le même agent, sous une fonction AUDIT non indépendante (`ADOPTION-0025`, Art. 3.b). Le test `P3` reproductible, exécutable par quiconque en intégration continue, est le premier contre-pouvoir ne dépendant pas de l'agent ; la lecture critique de l'autorité demeure le premier filet.

## Constat d'exécution

| Chemin | Mise à jour | Empreinte Git après mise à jour |
|---|---|---|
| `genesis-ii/registres/capacites/REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001.md` | Titre XV (Articles 95-98) — implémentation `PARTIELLEMENT MATÉRIALISÉE`, preuve `P3` | `83b25ab0ac944bcac4c951decd03df86b223bc71` |
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Article 4 — ajout de la ligne `ADOPTION-0029` | `0d393f2ed7bdf79c7a5cf2671e8207b1e1ec1e28` |

Ces empreintes remplacent, pour ces deux fichiers et pour eux seuls, celles déclarées par les actes antérieurs (`ADOPTION-0026` pour le premier ; `ADOPTION-0028` pour le second), qui demeurent exactes à leur date et sont dépassées par le présent acte dans la seule mesure des ajouts décrits. Aucune ligne ou article préexistant n'a été réécrit.

## Publication

L'incrément de code, le présent registre et la mise à jour du registre des capacités sont destinés à être publiés ensemble sur `main`, conformément à l'Article 66 de `GOVERNANCE-0001`. La publication est exécutée par `AGENT-IA-002` sous instruction expresse de l'autorité ; cette exécution ne fait pas de `AGENT-IA-002` l'autorité d'adoption.

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire
- **Date :** 27 juillet 2026
- **Entrée en vigueur :** immédiate, à la publication sur `main`
- **Mention :** LU ET ADOPTÉ
