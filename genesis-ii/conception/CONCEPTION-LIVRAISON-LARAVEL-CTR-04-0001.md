# CONCEPTION-LIVRAISON-LARAVEL-CTR-04-0001
## Projet de conception de la couche de livraison Laravel du service `CTR-04` (`CAP-CORE-007`)

> **PROJET D'ACTE — soumis à l'autorité de proposition. Non exécuté, non adopté, non signé tant qu'un registre d'adoption distinct (pressenti `ADOPTION-0030`) n'a pas été signé.** Ce document conçoit la couche de livraison ; il ne réécrit ni le cœur ni le test `P3`.

## Nature et rattachement

Le présent acte est un **second incrément de code**, postérieur à `ADOPTION-0029` (premier incrément, cœur PHP indépendant du cadre). Il traite l'écart de cadre expressément constaté à l'Article 1 de `ADOPTION-0029` : `ADOPTION-0027` avait retenu **Laravel** comme cadre applicatif ; le premier incrément avait livré le cœur en PHP portable, la couche Laravel restant à poser. Le présent acte pose cette couche, **autour** du cœur adopté, sans le réécrire.

Rédigé par SIRR (Claude, `AGENT-IA-002`) sous instruction (`ADOPTION-0024`, Art. 3 : conçoit et vérifie, ne décide ni ne signe).

## Article 1 — Périmètre du second incrément

Le second incrément livre :

1. une application Laravel qui **enveloppe** `core/registre-normes/` sans en modifier un seul fichier ;
2. trois routes HTTP, **toutes en lecture seule**, exposant les trois opérations déjà adoptées de `Ctr04` : `resoudre_norme`, `verifier_integrite`, `resoudre_index` (`INV-4`) ;
3. le tableau de bord actuel (`core/registre-normes/public/index.php`) porté en vue Blade, à l'identique dans son contenu et ses invariants affichés ;
4. la connexion PostgreSQL par `DATABASE_URL`, en réutilisant `Gamad\RegistreNormes\Db` sans le dupliquer.

Il ne livre **aucune** route d'écriture, aucune authentification, aucune administration. Le cœur (`GitBlob`, `Schema`, `Ingestion`, `Ctr04`) et le test `tests/temporel_p3.php` restent, à l'identique, la source des invariants ; Laravel ne fait que les livrer.

---

# TITRE I — ARBORESCENCE : LARAVEL AUTOUR DU CŒUR, PAS AU-DESSUS

## Article 2 — Principe de non-réécriture

`core/registre-normes/` (Article 2 de `CONCEPTION-IMPLEMENTATION-CTR-04-0001`, adopté par `ADOPTION-0028`) demeure **intact** : mêmes fichiers, même `bootstrap.php`, même `composer.json` de module, même test `P3`. Aucun fichier sous `core/registre-normes/src/` ni `core/registre-normes/tests/` n'est modifié par le présent incrément. La preuve : `git diff` sur `core/registre-normes/src/` et `core/registre-normes/tests/` entre le commit adopté par `ADOPTION-0029` et le commit adopté par le présent acte doit être **vide**.

## Article 3 — Nouvelle racine de livraison

```
core/registre-normes/                  ← cœur, INCHANGÉ (ADOPTION-0028, ADOPTION-0029)
  src/ ...                             ← intact
  tests/temporel_p3.php                ← intact, continue de s'exécuter seul (garde 2)
  public/index.php                     ← conservé tel quel (référence PHP portable, Railway « premier regard »)

apps/console-laravel/                  ← NOUVEAU — couche de livraison, second incrément
  app/
    Http/Controllers/Ctr04Controller.php   ← appelle Gamad\RegistreNormes\Ctr04, aucune écriture
  bootstrap/app.php                     ← amorçage Laravel standard
  config/
    database.php                        ← connexions pgsql (DATABASE_URL) et sqlite (repli local)
  resources/views/
    tableau-de-bord.blade.php           ← portage du contenu de core/registre-normes/public/index.php
  routes/
    web.php                             ← GET uniquement (Article 6)
  public/
    index.php                           ← nouveau front-controller (celui de Laravel, pas celui du cœur)
  composer.json                         ← require laravel/framework ; autoload PSR-4 pointant vers
                                            ../../core/registre-normes/src (Gamad\RegistreNormes\\),
                                            sans copier ni dupliquer ces fichiers
  .env.example                          ← DATABASE_URL en exemple seulement, aucune valeur réelle
```

`apps/console-laravel/composer.json` déclare un autoload PSR-4 supplémentaire vers `core/registre-normes/src/` (chemin relatif, aucune copie de fichier) afin que les classes `Gamad\RegistreNormes\*` restent **uniques** dans le dépôt — une seule implémentation, deux points d'entrée (le front-controller nu du cœur, et désormais l'application Laravel).

## Article 4 — Le contrôle Python reste hors de portée

`outils/verifier-integrite.py` n'entre sous aucune des deux arborescences ci-dessus et n'est touché par aucune ligne du présent incrément (`ADOPTION-0027`, Art. 4 ; rappel de l'Article 3 de `CONCEPTION-IMPLEMENTATION-CTR-04-0001`).

---

# TITRE II — ROUTES DE LECTURE SEULE

## Article 5 — Les trois routes

```
GET /                              → tableau de bord (vue Blade, Titre III)
GET /normes/{reference}            → resoudre_norme(reference, version?, date?) en JSON
GET /integrite/{reference?}        → verifier_integrite(reference?) en JSON
GET /index                         → resoudre_index() en JSON
```

Chaque route délègue strictement à une méthode déjà adoptée de `Gamad\RegistreNormes\Ctr04` (Article 11 de `CONCEPTION-IMPLEMENTATION-CTR-04-0001`, `ADOPTION-0028`). Le contrôleur ne contient aucune logique de résolution propre : il traduit la requête HTTP en appel de méthode et la réponse de la méthode en JSON, rien de plus.

## Article 6 — Aucune route d'écriture, tenu par construction

`routes/web.php` ne déclare que des verbes `GET`. Aucun `POST`, `PUT`, `PATCH`, `DELETE` n'est enregistré vers `Ctr04Controller` ou tout autre contrôleur du module. `INV-4` (adoption distincte de la publication) est ainsi tenu à la fois par le cœur (Article 8 de la conception adoptée, `ADOPTION-0026`) et par l'absence structurelle de route d'écriture dans la couche de livraison.

## Article 7 — Explicabilité conservée

Les réponses JSON exposent les mêmes champs que documentés à l'Article 11 de `CONCEPTION-IMPLEMENTATION-CTR-04-0001` (`reference`, `version`, `empreinte_git`, `statut`, `adoption_reference`, `en_vigueur`, etc.) sans reformulation ni simplification qui masquerait la preuve rattachée (Article 13 de la même conception).

---

# TITRE III — TABLEAU DE BORD EN VUE BLADE

## Article 8 — Portage à l'identique

`resources/views/tableau-de-bord.blade.php` reproduit le contenu et les invariants affichés par `core/registre-normes/public/index.php` (adoption, intégrité, cohérence d'index, preuve `P3`) tel qu'adopté par `ADOPTION-0029`. C'est un **portage**, non une refonte : aucune information nouvelle n'est ajoutée, aucune n'est retirée. Le fichier `core/registre-normes/public/index.php` original est conservé sans changement, comme point d'entrée PHP portable indépendant (utile hors Laravel, ex. `nixpacks.toml`, Railway « premier regard »).

## Article 9 — Une seule source de données pour les deux vues

Les deux points d'entrée — `core/registre-normes/public/index.php` et `apps/console-laravel/resources/views/tableau-de-bord.blade.php` — lisent tous deux `Gamad\RegistreNormes\Ctr04` sur la même connexion `Db::connect()`. Aucune divergence de calcul n'est possible : il n'existe qu'une implémentation des trois opérations.

---

# TITRE IV — CONNEXION POSTGRESQL PAR `DATABASE_URL`

## Article 10 — Réutilisation, non duplication

La couche Laravel **n'introduit pas** de second mécanisme de connexion. `config/database.php` construit la connexion Laravel nommée `pgsql` à partir de la même variable d'environnement `DATABASE_URL` que celle déjà lue par `Gamad\RegistreNormes\Db::connect()` (Article du cœur, `ADOPTION-0028`, Titre II). Le contrôleur `Ctr04Controller` instancie `Ctr04` avec le PDO produit par `Db::connect()` directement — il n'emprunte pas le query builder Eloquent pour les trois opérations de lecture, afin de ne pas réécrire en Eloquent une logique déjà adoptée et testée en PDO nu.

## Article 11 — SQLite en local, PostgreSQL en déploiement

Sans `DATABASE_URL`, `Db::connect()` bascule sur SQLite (`SQLITE_PATH` ou défaut), exactement comme aujourd'hui. Le second incrément doit être **exécutable et testable sans aucun secret**, comme le premier (Article 17 de `CONCEPTION-IMPLEMENTATION-CTR-04-0001`).

## Article 12 — Le secret demeure hors du dépôt

`DATABASE_URL`, quand il pointe vers une base réelle, est un secret consigné exclusivement au registre d'accès de l'autorité (`ADOPTION-0025`, Art. 3.a). `apps/console-laravel/.env.example` ne contient aucune valeur réelle, à l'image de `core/registre-normes/.env.example` déjà adopté.

---

# TITRE V — LES DEUX GARDES, INCHANGÉES

## Article 13 — Non-réécriture des gardes

Le présent incrément ne modifie ni `outils/verifier-integrite.py` (garde 1), ni `core/registre-normes/tests/temporel_p3.php` (garde 2). Les deux continuent de s'exécuter indépendamment de la présence ou de l'absence de la couche Laravel (`ADOPTION-0027`, Art. 4).

## Article 14 — Preuve P3 sur les deux moteurs

L'acte d'adoption du présent incrément constate l'exécution de `tests/temporel_p3.php` **à la fois** contre SQLite (par défaut, sans secret) et contre une base PostgreSQL locale éphémère (avec `DATABASE_URL` positionné pour la seule durée du test, jamais consigné dans un fichier versionné). Les deux exécutions doivent sortir `0`.

---

# TITRE VI — CE QUE LE SECOND INCRÉMENT NE FAIT PAS

## Article 15 — Frontière opérationnelle

Le second incrément s'exécute localement et en intégration continue. Il ne comporte :

- aucune écriture applicative dans le corpus (`INV-4` maintenu) ;
- aucune authentification, aucune session, aucun compte utilisateur ;
- aucun déploiement effectif ni configuration de production au-delà de ce que l'autorité a déjà réalisé sous sa propre main (nginx, TLS, base et rôle PostgreSQL — hors du présent acte, hors du dépôt) ;
- aucune modification du cœur `core/registre-normes/src/` ni du test `P3`.

## Article 16 — Frontière des accès réservés

Comme pour le premier incrément (Article 18 de `CONCEPTION-IMPLEMENTATION-CTR-04-0001`), tout secret, tout hébergement réel demeure du ressort exclusif de l'autorité (`ADOPTION-0025`, Art. 3.a). Le présent acte ne franchit pas cette frontière.

---

# TITRE VII — RÉSERVE D'AUDIT

## Article 17 — Rappel

Le second incrément est conçu et vérifié par le même agent, sous une fonction AUDIT non indépendante (`ADOPTION-0025`, Art. 3.b). Les deux gardes reproductibles demeurent le premier contre-pouvoir ne dépendant pas de l'agent ; la lecture critique de l'autorité demeure le filet ultime.

---

# TITRE VIII — DÉCISIONS RÉSERVÉES À L'AUTORITÉ

## Article 18 — Points à trancher

1. l'adoption ou la correction de la présente conception (acte pressenti `ADOPTION-0030`) ;
2. la version majeure précise de Laravel retenue au premier build — `laravel/framework` `^13.0` (dernière version stable disponible sur Packagist au moment de la rédaction, compatible PHP 8.3) est proposée, sauf préférence contraire de l'autorité ;
3. le nom définitif de la racine de livraison (`apps/console-laravel/` est proposé, par cohérence avec le domaine `console.dgafrique.com`) ;
4. si le tableau de bord public de `console.dgafrique.com` doit basculer sur la vue Blade dès ce second incrément, ou continuer de servir `core/registre-normes/public/index.php` (choix sans conséquence sur les invariants, purement opérationnel — configuration nginx hors dépôt).

## Article 19 — Non-effet

Le présent acte ne code rien par lui-même, n'installe rien, ne rend `CAP-CORE-007` ni admise ni active, n'accepte aucun risque nouveau et ne modifie le corps d'aucun texte adopté.

---

## Autorité d'adoption

- **Nom :** _[réservé à l'autorité de proposition]_
- **Qualité :** _[à compléter]_
- **Date :** _[à compléter à l'adoption]_
- **Registre d'adoption pressenti :** `ADOPTION-0030`
- **Signature :** _[réservée à l'autorité]_

Jusqu'à adoption expresse et inscription au Registre des adoptions, le présent texte demeure :

> **PROJET NORMATIF — EN COURS DE DÉLIBÉRATION**
