# CAP-CORE-014 — Event Journal

**Nom :** Journal des événements
**État réel au commit de tête de ce chantier :** voir `docs/capacites/CATALOGUE.md` — ne jamais présumer `GO` à partir de ce seul document.

Cette fiche décrit le code réellement livré. Elle n'est ni une loi, ni un acte d'adoption, ni une preuve d'exécution en production.

## 1. Objectif

Publier et faire consommer, de façon gouvernée et vérifiable, les événements communs entre le Core et les satellites — sans que le Core devienne un bus universel ni une base de données métier partagée.

## 2. Différence avec l'audit (CAP-CORE-013)

`core/journal-operationnel` (`CAP-CORE-013`) est le journal d'audit **privé** de l'exploitation : traces d'authentification, de décision et d'effet, jamais consulté par un producteur ou un consommateur métier. `core/journal-evenements` (`CAP-CORE-014`, ce module) est le registre **commun** : des faits contractualisés, volontairement publiés pour circuler entre produits. Les deux magasins sont physiquement distincts, ne se fusionnent jamais, et chaque écriture gouvernée de CAP-CORE-014 laisse elle-même une trace dans CAP-CORE-013.

## 3. Modèle de données livré

`core/journal-evenements/src/SchemaEvenements.php` — treize tables persistantes dans un magasin `gamad_evenements` séparé (`EVENT_JOURNAL_URL`/`EVENT_JOURNAL_PATH`) :

- `evenement_commun` — enveloppe canonique en ajout seul, chaînée par `empreinte_evenement`/`empreinte_precedente`, référence unique, idempotence unique par producteur ;
- `evenement_charge` — charge séparée, expirante, purgeable indépendamment de l'enveloppe ;
- `recu_publication` — idempotence de publication par producteur ;
- `abonnement_evenement`, `abonnement_cycle`, `abonnement_type_evenement`, `abonnement_producteur`, `abonnement_realm` — gouvernance et filtres d'abonnement (ajout seul pour le cycle) ;
- `livraison_evenement`, `tentative_livraison` — livraison PULL par bail, tentatives, en ajout seul pour les tentatives ;
- `curseur_abonnement` — progression contiguë par abonnement ;
- `demande_rejeu` — rejeux bornés, curseur persistant, reprenables ;
- `lettre_morte_evenement` — visibles, en ajout seul ; une relance crée une nouvelle tentative, n'écrit jamais dans cette table.

`core/evenements-sortants/src/SchemaOutbox.php` ajoute une seule table, `evenement_sortant`, **dans chaque magasin producteur** — jamais dans le journal central : un rollback métier ne laisse jamais d'outbox, un commit en laisse toujours une.

## 4. Producteur pilote et relais

`core/evenements-sortants/src/OutboxProducteur.php` prépare l'outbox dans la même transaction que l'écriture métier. `RelaisOutbox` la publie vers le journal central de façon idempotente (rejouer un relais ne republie jamais une ligne déjà `PUBLIE`) et classe un contrat inconnu en `ECHEC_DEFINITIF`, jamais en retentative indéfinie. Le seul producteur réellement raccordé dans ce chantier est **CAP-CORE-011 (registre des produits)** — activation, suspension : `PublierEvenementsCommand` le documente explicitement comme le seul pilote livré. D'autres producteurs (organisations, contrats, realms, sources) restent à raccorder au fil de besoins réels, pas par anticipation.

## 5. Abonnements, livraison, reprise

`RegistreAbonnements` gouverne le cycle (`PREPARATION → ACTIF → SUSPENDU → RETIRE`) et les filtres (type, producteur, realm). `LivreurEvenements` distribue par bail opaque, refuse la redistribution d'une livraison sous bail actif, accepte un accusé idempotent, refuse un accusé hors bail, avance le curseur uniquement sur la suite contiguë accusée, retente sur refus temporaire jusqu'au plafond puis bascule en lettre morte. `RejoueurEvenements::executerRejeu()` avance par curseur persistant (`demande_rejeu.curseur_sequence`) : un crash à mi-parcours ne perd que le lot en cours, jamais la progression déjà actée ; un rejeu ne crée jamais un nouvel événement métier, il redistribue l'original.

## 6. Realms et confidentialité

Le realm est obligatoire à l'acceptation d'un événement (`realm_reference`, realm actif requis). Le filtrage d'abonnement par realm est exercé dans la garde du noyau avec deux realms isolés (`RLM-P3-EVT-A`/`RLM-P3-EVT-B`) : un abonnement filtré sur A ne reçoit jamais un événement publié sur B.

## 7. Rapprochement et intégrité

`RapprochementEvenements` (lecture seule, ne répare rien) détecte : livraison référençant un événement inexistant, lettre morte référençant une livraison inexistante, et les autres écarts décrits dans la fiche de chantier — exercé par `core:evenements:rapprocher` et par la garde du noyau (épreuves 56 à 58). `core:evenements:verifier` vérifie la chaîne d'empreintes en lecture seule.

## 8. Vocabulaire, politique et contrats

- **Vocabulaire (`CAP-CORE-010`)** — huit vocabulaires `VOC-GAMAD-EVENEMENT-*` (états d'outbox, d'abonnement, de livraison, modes de livraison, portée realm, etc.) ajoutés à `core/registre-vocabulaire/resources/bootstrap-vocabulaire-v1.json`, repris fidèlement des constantes réelles de `PolitiqueEvenements`.
- **Politique (`CAP-CORE-007`)** — `POL-EVENEMENTS-V1`, établie par `core:evenements:bootstrap`, réserve toutes les actions gouvernées à `AUT-GAMAD-001`. Ce n'est pas une lacune : le dirigeant a confirmé que l'autorité unique est le design voulu pour toutes les actions sensibles du Core, pas seulement pour cette capacité.
- **Contrats (`CAP-CORE-009`)** — huit contrats techniques `CTR-GAMAD-EVENEMENT-*` décrivant les opérations de CAP-CORE-014 elle-même, établis par le même bootstrap ; les contrats métier par famille (`EVT-GAMAD-PRODUIT-*`) sont établis séparément, déjà actifs pour le pilote CAP-CORE-011.

## 9. API et console

API v1 : 27 routes sous `/api/v1/evenements*`, `/api/v1/abonnements*`, `/api/v1/rejeux*` et `/api/v1/lettres-mortes*` (`EvenementController`, `AbonnementController`, `RejeuController`, `LettreMorteController`), gouvernées par `App\Application\Evenements\AccesEvenements`. Console : 19 routes (`EvenementConsoleController`), huit vues (`tableau-de-bord`, `evenement`, `abonnement`, `rejeu`, `rejeu-nouveau`, `rejeux-index`, `lettre-morte`, `lettres-mortes-index`).

## 10. Exploitation

Huit commandes artisan (`core:evenements:bootstrap`, `publier`, `liberer-baux`, `traiter-rejeux`, `purger-charges`, `verifier`, `diagnostiquer`, `rapprocher`) — voir `core/journal-evenements/README.md`. `purger-charges` reste en simulation par défaut ; `--force` est un choix d'opérateur explicite, jamais un défaut automatisé.

Cinq unités systemd sous `ops/core-foundation/systemd/` (`gamad-core-events-publish/lease/replay/verify/purge`), sur le même modèle `oneshot` + `OnFailure=gamad-core-operations-alert@…` que les unités existantes. Détail des fréquences dans `ops/core-foundation/README.md`.

## 11. Readiness, sauvegarde et restauration

`EtatFondation` inspecte désormais douze magasins : la cible `evenements` vérifie la connexion, les treize tables, la lisibilité de la tête de chaîne (`empreinte_evenement`), le compte de baux expirés non libérés et de lettres mortes. `ops/core-foundation/backup.sh` et `restore-drill.sh` incluent la cible `evenements` (dump, somme SHA-256, comptages post-restauration sur `evenement_commun`, `abonnement_evenement`, `livraison_evenement`, `lettre_morte_evenement`). `apps/console-laravel/tests/Integration/postgresql_p0.sh` provisionne `gamad_events`/`drill_events` et exécute le cycle complet aux côtés des onze autres magasins.

## 12. Code livré

```text
core/journal-evenements/
├── README.md
├── src/
│   ├── Magasin.php
│   ├── SchemaEvenements.php
│   ├── PolitiqueEvenements.php
│   ├── ValidateurEvenement.php
│   ├── EnveloppeEvenement.php
│   ├── RegistreEvenements.php
│   ├── RegistreAbonnements.php
│   ├── RouteurEvenements.php
│   ├── LivreurEvenements.php
│   ├── RejoueurEvenements.php
│   ├── RapprochementEvenements.php
│   └── ExceptionEvenement.php
└── tests/evenements_p3.php

core/evenements-sortants/
├── src/
│   ├── SchemaOutbox.php
│   ├── OutboxProducteur.php
│   └── RelaisOutbox.php
└── tests/outbox_p3.php

apps/console-laravel/
├── app/Application/Evenements/AccesEvenements.php
├── app/Http/Controllers/Api/V1/{Evenement,Abonnement,Rejeu,LettreMorte}Controller.php
├── app/Http/Controllers/EvenementConsoleController.php
├── app/Console/Commands/{Bootstrap,Publier,LibererBaux,TraiterRejeux,PurgerCharges,Verifier,Diagnostiquer,Rapprocher}EvenementsCommand.php
├── app/Support/EtatFondation.php (cible evenements)
├── resources/views/evenements/*.blade.php
└── tests/Integration/evenements_{v1,drift,commandes,console}_p1.php

ops/core-foundation/
├── backup.sh, restore-drill.sh, README.md (cible evenements)
└── systemd/gamad-core-events-{publish,lease,replay,verify,purge}.{service,timer}
```

## 13. Tests exécutés et résultats réels

Exécutés le 4 août 2026, sur ce commit :

- `core/journal-evenements/tests/evenements_p3.php` — **60/60** épreuves (SQLite) : enveloppe, chaîne d'empreintes, idempotence, realms, abonnements, baux, concurrence de lecture, accusés idempotents, curseur contigu, retentatives, lettres mortes, relance, rejeu borné multi-lots, rapprochement, purge de charge.
- `core/evenements-sortants/tests/outbox_p3.php` — **14/14** épreuves (SQLite) : outbox transactionnelle, idempotence de préparation, relais, pilote CAP-CORE-011, échec définitif sur contrat inconnu.
- `apps/console-laravel/tests/Integration/evenements_v1_p1.php` — **15/15** : parcours HTTP complet publication → abonnement → livraison PULL → accusé → lettre morte → rejeu. Ce parcours sert de **consommateur de conformité en dépôt** au sens de la fiche de chantier (§16) : aucun satellite déployé n'a été raccordé dans ce chantier, mais la déduplication, l'accusé, la reprise, le refus temporaire, la lettre morte et le rejeu borné y sont tous prouvés contre l'API réelle, pas seulement contre le registre.
- `apps/console-laravel/tests/Integration/evenements_drift_p1.php` — **5/5** : aucune dérive entre les routes déclarées et `openapi/core-v1.yaml`.
- `apps/console-laravel/tests/Integration/evenements_commandes_p1.php` — **6/6** : commandes d'exploitation, rapprochement sans suppression.
- `apps/console-laravel/tests/Integration/evenements_console_p1.php` — **15/15** : écran console gouverné.
- `apps/console-laravel/tests/Integration/migration_config_cache_p1.php` — **4/4**, y compris après correction : la contre-épreuve ne déclarait pas `EVENT_JOURNAL_URL` parmi les connexions attendues et échouait donc elle-même depuis que cette variable est devenue obligatoire en production.
- `apps/console-laravel/tests/Integration/api_v1_p1.php` — la readiness vérifie désormais les **douze** magasins (mise à jour depuis onze).
- **PostgreSQL réel** (`apps/console-laravel/tests/Integration/postgresql_p0.sh`, exécuté sous l'utilisateur `postgres`) — douze magasins réellement PostgreSQL, session par empreinte, journal d'audit chaîné et protégé par trigger, API de production refusant HTTP en clair, **readiness de production acceptant le socle complet**, sauvegarde et restauration sur douze cibles isolées avec comptages post-restauration cohérents (`evenements_communs`, `abonnements`, `livraisons`, `lettres_mortes`).
- `core/journal-operationnel/tests/fondation_operationnelle_p3.php` — établi, sans régression.
- Copie hors machine (`copie_hors_machine_p1.sh`, `copie_hors_machine_ftp_p1.sh`) et continuité pilotée (`continuite_p1.sh`) — établies, sans régression (fixtures synthétiques indépendantes du nombre réel de magasins).
- Le reste de la suite d'intégration existante (fédération, produits, organisations, realms, sources, politiques, contrats, vocabulaire, accès, passkey, import SQLite) — établi, sans régression observée.
- Syntaxe PHP de l'ensemble `core/` et `apps/` — aucune erreur.

Total des épreuves propres à CAP-CORE-014 : **115** (60 + 14 + 15 + 5 + 6 + 15), plus le cycle PostgreSQL réel.

**Non exécuté dans ce chantier :** la CI GitHub elle-même (les jobs sont vérifiés par lecture et par exécution locale équivalente des mêmes commandes, mais un run GitHub Actions réel sur la PR reste à confirmer une fois ouverte).

## 14. Limites non bloquantes et réserves

- **Aucun satellite réel n'est raccordé** en consommateur : la preuve de consommation (§16 de la fiche de chantier) repose sur le parcours HTTP `evenements_v1_p1.php`, explicitement autorisé par la fiche comme substitut à un satellite déployé (« consommateur de conformité dans le dépôt, clairement identifié comme pilote technique »). Ne jamais présenter ce parcours comme une intégration de production réelle.
- **Un seul producteur réel est raccordé** : CAP-CORE-011 (produits), activation et suspension. Les autres producteurs potentiels (organisations, contrats, realms, sources) ne sont pas raccordés, faute de besoin réel exprimé.
- **Aucune protection contre les abus** (fiche §11 : rate limiting par producteur/consommateur, quotas de taille/lot/rejeu, circuit breaker) n'est implémentée. Ce n'est pas une régression propre à CAP-CORE-014 : aucune capacité du dépôt n'implémente ce type de protection à ce jour. Un consommateur lent ou un producteur bruyant n'est aujourd'hui limité par aucun mécanisme dédié.
- **Aucune métrique Prometheus-style ni alerte routée vers un opérateur externe** (fiche §4-5) n'existe. Même réserve que ci-dessus : aucune capacité du dépôt n'a de tel exportateur ; les unités systemd écrivent uniquement dans le journal système local (`auth.alert`), comme pour les timers déjà en service.
- **La rétention contractuelle de la charge n'est jamais calculée automatiquement.** `RegistreEvenements::accepterEvenement()` n'écrit `charge_expire_le` (`evenement_commun`) ni `expire_le` (`evenement_charge`) sur aucune insertion réelle : ces deux colonnes restent `NULL` pour tout événement réellement accepté aujourd'hui. `listerChargesPurgeables()` et `purgerCharge()` fonctionnent et sont éprouvés (épreuves 59-60 de la garde du noyau), mais avec une date d'expiration posée manuellement par le test — en exploitation réelle, `core:evenements:purger-charges` ne trouvera donc aucun candidat tant qu'un calcul de rétention par contrat n'est pas ajouté à `accepterEvenement()`. Ce n'est pas une fuite de données : une charge sans expiration reste simplement accessible indéfiniment, ce qui est le comportement le plus restrictif, pas le plus permissif. C'est en revanche une promesse de rétention non tenue en pratique, à corriger avant de s'appuyer sur la purge en exploitation.
- **Le worker de publication n'a jamais tourné en continu en exploitation réelle** : les commandes et leurs unités systemd sont testées à l'exécution unique, jamais sous charge soutenue ni sous crash simulé d'un processus en cours de lot (la reprenabilité du rejeu, elle, est testée par simulation de curseur — épreuves 51-55).
- **La concurrence réelle multi-processus** n'est pas éprouvée par un test multi-processus : les épreuves d'idempotence (bail, accusé) prouvent l'absence de doublon au rejeu séquentiel, la protection sous charge concurrente réelle tient aux contraintes `UNIQUE` et aux transactions, non à un verrou applicatif testé sous concurrence véritable — même réserve que pour `CAP-CORE-012`.
- **Signature et preuve d'archive** (fiche §13, après `CAP-CORE-015`/`CAP-CORE-016`) : `signee: false` annoncée, champs facultatifs prévus mais non remplis — conformément à ce que la fiche autorise explicitement dans ce chantier.
- La readiness, la sauvegarde et la restauration du magasin `evenements` ont été écrites et exercées dans ce chantier (y compris en PostgreSQL réel) mais n'ont jamais tourné sur le serveur d'exploitation actuel.

## 15. Décision

```text
CAP-CORE-014 — GO
```

Sous réserve explicite des limites listées en §14, notamment l'absence de
satellite réel raccordé, l'absence de protection contre les abus et
l'absence de calcul automatique de rétention de charge — des points à
traiter au premier besoin réel, pas par anticipation dans ce
chantier.
