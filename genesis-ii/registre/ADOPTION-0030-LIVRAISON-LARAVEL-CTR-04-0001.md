# REGISTRE D'ADOPTION — ADOPTION-0030
## Second incrément de code du service `CTR-04` (`CAP-CORE-007`) — couche de livraison Laravel

> **PROJET D'ACTE — lu et validé par l'autorité en échange direct.** Le présent registre est préparé sur la branche `agent/genesis-ii-livraison-laravel-ctr-04` ; il n'entre formellement en vigueur qu'à la fusion `--no-ff` dans `main`, laquelle **est** l'acte d'adoption et appartient exclusivement à l'autorité (Koné Djakaridja, dit Zakaria le Soufi). Les deux gardes sont vertes sur cette branche (constat ci-dessous). Conformément à `ADOPTION-0024`, Art. 3, l'agent ne fusionne jamais lui-même dans `main` : la commande de fusion est fournie à l'autorité pour exécution de sa propre main.

## Nature

Le présent acte adopte le **second incrément de code canonique du Core**, écrit après le constat de `G0` (`ADOPTION-0025`) et le premier incrément de code (`ADOPTION-0029`). Il traite l'écart de cadre expressément constaté à l'Article 1 de `ADOPTION-0029` : `ADOPTION-0027` avait retenu **Laravel** comme cadre applicatif ; le premier incrément avait livré le cœur en PHP portable, la couche Laravel restant à poser. Le présent acte pose cette couche, **sans réécrire** le cœur ni le test `P3` adoptés par `ADOPTION-0029`.

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté le second incrément de code du service `CTR-04`, situé sous `apps/console-laravel/`, ainsi que le projet de conception qui l'a précédé (`CONCEPTION-LIVRAISON-LARAVEL-CTR-04-0001`).

## Version adoptée

| Objet | Branche de préparation | Commit adopté |
|---|---|---|
| Conception de livraison (`CONCEPTION-LIVRAISON-LARAVEL-CTR-04-0001`) | `agent/genesis-ii-livraison-laravel-ctr-04` | `559d1a0bb395322b27286ad94324f7977393b0fe` |
| Incrément de code `apps/console-laravel/` (couche Laravel de `CTR-04`) | `agent/genesis-ii-livraison-laravel-ctr-04` | `25b9ff2bd8f18b8a290eabf9a75a7e1412977b09` |

- **Version :** `0.1`
- **Date d'adoption :** 28 juillet 2026
- **Entrée en vigueur :** immédiate, à la publication sur `main`

Le commit désigné identifie l'état exact du code adopté. Toute évolution ultérieure fera l'objet d'un nouvel incrément et d'un nouvel acte.

## Contenu de l'incrément

- `apps/console-laravel/` — squelette Laravel 13 (PHP 8.3) ;
- `app/Http/Controllers/Ctr04Controller.php` — délègue strictement aux méthodes déjà adoptées de `Gamad\RegistreNormes\Ctr04` (`ADOPTION-0028`, `ADOPTION-0029`), aucune logique de résolution propre ;
- `routes/web.php` — quatre routes, toutes `GET` : `/`, `/normes/{reference}`, `/integrite/{reference?}`, `/index` ; aucune route d'écriture (`INV-4`) ;
- `resources/views/tableau-de-bord.blade.php` — portage à l'identique du tableau de bord adopté (`core/registre-normes/public/index.php`, conservé sans changement) ;
- `config/database.php` — connexion `pgsql` lisant `DATABASE_URL` (repli `DB_URL`), même variable que `Gamad\RegistreNormes\Db::connect()` ; aucun second mécanisme de connexion ;
- `composer.json` — autoload PSR-4 `Gamad\RegistreNormes\\` vers `../../core/registre-normes/src/`, chemin relatif, aucune copie de fichier ;
- `.gitignore` racine du dépôt — ajouté (absent jusqu'ici), protège `.env`, `vendor/`, `node_modules/` contre tout commit accidentel de secret.

## Non-réécriture du cœur, vérifiée

`core/registre-normes/src/` et `core/registre-normes/tests/` sont **inchangés** par le présent incrément : `git diff main -- core/registre-normes/src core/registre-normes/tests` sur le commit adopté est vide. Le fichier `core/registre-normes/public/index.php` (front-controller PHP portable, référence Railway « premier regard ») est également conservé sans modification.

## Vérification des deux gardes

- **Garde 1** (`outils/verifier-integrite.py`) : `VÉRIFIÉE`, code de sortie `0`, sur le commit adopté.
- **Garde 2** (`core/registre-normes/tests/temporel_p3.php`) : `ÉTABLIE`, code de sortie `0`, sur le commit adopté. Le test s'exécute, par construction du cœur adopté (`ADOPTION-0028`, Titre V, Article 24 du fichier), sur une base SQLite éphémère isolée — il force `DATABASE_URL` vide pour garantir sa reproductibilité sans secret, y compris en intégration continue. **Le présent acte ne modifie pas ce comportement.**
- **Vérification complémentaire sur PostgreSQL :** en l'absence d'un mode PostgreSQL dans le test `P3` lui-même (choix du cœur adopté, non remis en cause), le comportement de résolution temporelle a été vérifié manuellement, hors du test automatisé, en interrogeant les routes de la couche Laravel (`GET /normes/CAP-CORE-007?date=2026-07-26` et suivantes) contre une base PostgreSQL locale (`registre_normes`, provisionnée par l'autorité) : les trois statuts attendus par la preuve `P3` (`EN CONCEPTION` puis `CONÇUE`) ont été restitués correctement sur les deux moteurs. Ce constat opérationnel s'ajoute à la preuve `P3` sans s'y substituer ; il ne modifie pas le niveau de preuve `P3 — TESTÉ` déjà établi par `ADOPTION-0029`.

## Effets

- `CAP-CORE-007` reste en implémentation `PARTIELLEMENT MATÉRIALISÉE` et en preuve `P3 — TESTÉ` ; l'exploitation demeure `INACTIVE`. L'écart de cadre signalé par `ADOPTION-0029`, Article 1, est soldé : Laravel enveloppe désormais le cœur adopté. Ce fait est constaté au Titre XVI de `REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001`, sans réécriture d'aucun article antérieur.
- Aucune écriture applicative n'est introduite : `INV-4` demeure tenu, à la fois par le cœur (inchangé) et par l'absence structurelle de route d'écriture dans la couche de livraison.

Cette adoption :

- ne rend `CAP-CORE-007` ni admise, ni active au sens de l'exploitation ; elle n'autorise aucun déploiement par elle-même ;
- ne franchit pas la frontière des accès réservés : tout hébergement, base gérée ou secret demeure du ressort exclusif de l'autorité (`ADOPTION-0025`, Art. 3.a). L'installation système (PHP-FPM, PostgreSQL, nginx, certbot), la création du rôle et de la base `registre_normes`, ainsi que la configuration du domaine `console.dgafrique.com`, ont été réalisées séparément, hors du dépôt, sous instruction expresse et rendues à l'autorité par un compte-rendu distinct (secrets non consignés dans le présent acte) ;
- n'admet aucun produit, n'accepte aucun risque nouveau et ne modifie le corps d'aucun texte adopté.

## Réserve d'audit maintenue

L'incrément est conçu et vérifié par le même agent, sous une fonction AUDIT non indépendante (`ADOPTION-0025`, Art. 3.b). Les deux gardes reproductibles, inchangées par le présent incrément, demeurent le premier contre-pouvoir ne dépendant pas de l'agent ; la lecture critique de l'autorité demeure le filet ultime.

## Constat d'exécution

| Chemin | Mise à jour | Empreinte Git après mise à jour |
|---|---|---|
| `genesis-ii/registres/capacites/REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001.md` | Titre XVI (Articles 99-102) — écart de cadre soldé, implémentation `PARTIELLEMENT MATÉRIALISÉE` maintenue | `f3325cbfc1b38f8074de89d048887c52a96ecdff` |
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Article 4 — ajout de la ligne `ADOPTION-0030` | `6c17e6b6008c9c518c68eb52cae9c5d79079e72a` |

Ces empreintes remplacent, pour ces deux fichiers et pour eux seuls, celles déclarées par les actes antérieurs (`ADOPTION-0029` pour les deux), qui demeurent exactes à leur date et sont dépassées par le présent acte dans la seule mesure des ajouts décrits. Aucune ligne ou article préexistant n'a été réécrit.

## Publication

L'incrément de code, le présent registre et la mise à jour du registre des capacités sont destinés à être publiés ensemble sur `main`, conformément à l'Article 66 de `GOVERNANCE-0001`. La publication — la fusion `--no-ff` dans `main` — est l'acte d'adoption lui-même et appartient exclusivement à l'autorité ; elle n'est pas exécutée par l'agent sans autorisation expresse préalable.

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire
- **Date :** 28 juillet 2026
- **Entrée en vigueur :** immédiate, à la publication sur `main`
- **Mention :** LU ET ADOPTÉ
