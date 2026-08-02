# CAP-CORE-022 — Satellite Federation

Fiche établie par inspection du code, des gardes et de la CI. Elle décrit ce
qui existe, pas ce qui est souhaité.

**Référence :** `CAP-CORE-022`

**Nom :** Fédération des satellites

**Objectif :** relier le Compte GAMAD authentifié aux comptes produit locaux,
et remettre au satellite une preuve d’accès bornée.

**Problème résolu :** le Core savait authentifier une personne mais ne savait
pas l’ouvrir sur un produit. Chaque satellite aurait donc dû réinventer son
propre lien d’identité, sans borne commune ni révocation commune.

**Produits consommateurs :** GamaDrive (`PRD-GAMAD-002`) comme pilote. Wasplex
(`PRD-GAMAD-003`) et IKOMA (`PRD-GAMAD-004`) restent des partenaires externes
non entérinés : leur état dérivé ne porte pas la reconnaissance, et le Core
refuse de les fédérer tant qu’il en est ainsi.

**Responsabilité du Core :** décider l’ouverture (CAP-CORE-004), provisionner un
lien produit minimal de façon idempotente (CAP-CORE-001), émettre un jeton borné
à un satellite, le vérifier une fois, le révoquer, et prouver le parcours
(CAP-CORE-013).

**Ce qui reste dans les satellites :** le compte produit local, le rôle métier,
le plan, l’abonnement, les quotas, les préférences, les contenus, les
transactions et l’historique. Le Core n’en connaît rien et n’a aucun endroit où
les mettre.

**Données possédées :**

- `jeton_federe` dans le magasin d’accès — audience, identité, lien produit,
  type de relation, portées, niveau d’assurance, empreinte de la session Core
  émettrice, référence de preuve, émission, expiration, consommation,
  révocation ;
- le lien produit lui-même reste la propriété de CAP-CORE-001
  (`relation_produit`), y compris le sujet local opaque du satellite.

**Données exclues :** aucune valeur de jeton n’est conservée — seule son
empreinte SHA-256 l’est. Aucune donnée économique, aucun profil, aucun jugement.

**Commandes :**

- `Federation::ouvrir()` — provisionnement idempotent puis émission d’un jeton ;
- `Federation::verifierJeton()` — vérification et consommation à usage unique ;
- `Federation::revoquerAcces()` — clôture du lien et fermeture des jetons ;
- `Federation::revoquerJetonsDeSession()` / `fermerJetonsDeSession()` —
  déconnexion globale.

**Requêtes :**

- `Federation::catalogueProduits()` — satellites connus et fédérables ;
- `Federation::resoudreAcces()` — vue Portail d’une identité : produit, état
  d’activation, niveau d’accès, dernière ouverture ;
- `Federation::resoudrePorteurs()` — vue transversale d’un satellite, que
  CAP-CORE-001 refuse délibérément de servir à un produit. Elle est donc bornée
  par le code au satellite concerné et à l’autorité d’inscription : un satellite
  qui ouvrirait une session Core ne lit jamais les porteurs d’un autre.

**Événements :** `FEDERATION` dans le journal opérationnel —
`DECISION_OUVERTURE_PRODUIT`, `OUVERTURE_PRODUIT`,
`OUVERTURE_PRODUIT_REFUSEE`, `VERIFICATION_JETON_FEDERE`,
`DECISION_REVOCATION_ACCES`, `REVOCATION_ACCES_PRODUIT`. Aucun jeton n’y figure,
seulement sa référence.

**Dépendances :** CAP-CORE-001 (identités et liens produits), CAP-CORE-004
(décision), CAP-CORE-005 (session Core et magasin d’accès), CAP-CORE-013
(preuve), CAP-CORE-007 (registre persistant et gouverné des politiques
techniques).

**Autorisations :** `POL-FEDERATION-SATELLITES-V1`, reprise fidèlement
(chantier CAP-CORE-007) dans le registre persistant et gouverné
`core/registre-politiques`, activée, et évaluée par CAP-CORE-004 depuis ce
même magasin. Le module n’écrit aucune règle. Au-delà de la décision, le
code oppose ses propres bornes : on ouvre pour soi-même ou par l’autorité
d’inscription ; on ne vérifie un jeton que si l’on est le satellite
destinataire ; on ne révoque qu’en tant que porteur, satellite concerné ou
autorité.

**Bornes du jeton :** audience unique, durée de 30 à 300 secondes (120 par
défaut), portée unique `ouverture_session_locale`, usage unique, rattachement à
la session Core et au niveau d’assurance qui l’ont produit.

**Comportement en panne :** une décision ou une preuve impossible ferme
l’ouverture (`503`). Un jeton dont la session Core est fermée, dont le lien
produit est clos ou dont l’audience diffère est refusé, sans être consommé au
préjudice de son destinataire légitime.

**Sauvegarde et restauration :** les jetons vivent dans le magasin d’accès,
couvert par `ops/core-foundation`. `php artisan core:fondation:migrer` applique
la migration ; la readiness la vérifie.

**Code actuel :** `core/registre-federation/`,
`apps/console-laravel/app/Application/Federation/AccesSatellites.php`,
`apps/console-laravel/app/Http/Controllers/Api/V1/FederationController.php`,
`apps/console-laravel/app/Http/Controllers/SatelliteConsoleController.php`,
`apps/console-laravel/resources/views/satellites/`, routes `/api/v1/produits*`
et `/satellites*`, contrat `apps/console-laravel/openapi/core-v1.yaml`.

**Écran d’administration :** `Satellites` dans la console — liste des produits
avec leur état d’ouverture, fiche de raccordement des quatre informations à
remettre à l’équipe du satellite, identifiants de raccordement, porteurs d’un
accès actif, ouverture et révocation avec confirmation. L’écran n’ouvre aucun
chemin parallèle : il appelle le même cas d’usage gouverné que l’API, et
n’écrit jamais en direct dans le registre des identités ni dans le magasin
d’accès. Le jeton n’y est montré qu’une fois.

**Identifiants de raccordement :** le secret avec lequel un satellite
s’authentifie auprès du Core se délivre depuis la console. Il est **engendré
par le Core** (24 octets d’entropie) et non saisi : un secret de service tapé
par une personne est court et réutilisé. Six bornes le tiennent :

1. l’action est réservée à `AUT-GAMAD-001` par la politique **et** par le code ;
2. un produit non entériné n’en reçoit aucun ;
3. deux identifiants actifs au plus, pour qu’un secret oublié ne survive pas ;
4. il n’est montré qu’une fois, en flash, sur une page `no-store` ;
5. il n’entre jamais au journal — la preuve ne porte que la référence de
   l’authentificateur ;
6. le retrait est immédiat et ferme les sessions Core ouvertes avec lui, donc
   les jetons fédérés qui en dépendaient.

Un identifiant créé en ligne de commande reste visible et retirable depuis
l’écran ; son origine est distinguée.

**Tests actuels :**

- `core/registre-federation/tests/federation_p3.php` — 17 épreuves et
  contre-épreuves, raccordée à la CI ;
- `apps/console-laravel/tests/Integration/federation_v1_p1.php` — parcours HTTP
  complet sur le pilote, raccordé à la CI ;
- `apps/console-laravel/tests/Integration/federation_console_p1.php` — 17
  épreuves de l’écran d’administration, dont la délivrance et le retrait des
  identifiants, raccordée à la CI.

**État réel :** `IMPLÉMENTÉ` — le parcours d’ouverture, de vérification, de
révocation et de déconnexion globale est éprouvé de bout en bout sur GamaDrive.
Aucun satellite réel ne le consomme encore : l’exploitation reste à établir.

**Manques :**

- aucune intégration réelle côté GamaDrive V2 ; le satellite est joué par sa
  session Core dans les épreuves ;
- la délivrance d’un identifiant n’exige pas une session à facteur fort ;
  l’écran affiche le niveau d’assurance de la session en cours mais ne le
  contrôle pas. Exiger `A2` fermerait l’action à une autorité connectée par
  mot de passe : c’est une décision d’exploitation, pas une évidence technique ;
- pas de suspension temporaire distincte de la révocation ;
- pas de publication d’événement vers les satellites (CAP-CORE-014 reste
  `PARTIEL`) : un satellite n’est pas notifié d’une révocation, il la constate
  au refus du jeton suivant.

**Prochain chantier :** intégration réelle de GamaDrive V2, puis publication
d’événements de révocation vers les satellites.
