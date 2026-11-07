# Applications GAMAD — audit, contrat et premier lot

## Vision reçue
Une plateforme unifiée, applications intégrées dans le même dépôt, comptes et
identités communs gouvernés par le Core. Chaque application peut disposer de son
domaine public (accueil à `/`), de fonctions et d'une console distinctes.
Les satellites fédérés restent un autre modèle. Aucune infrastructure de production
n'est modifiée par ce chantier.

## Sauvegardes
- Mon Espace : PR brouillon #125, `feature/mon-espace-gestion-integree`, commit
  `4e202e4aa8417c37437bd487b775a8eda505816b`. Arbre vérifié identique au local.
- Accueil public : PR brouillon #123, non modifiée, suspendue selon le mandat.
- Fondation : poursuite de #124, sans remplacer ses travaux ; ses deux fichiers
  initiaux sont conservés et complétés. Aucun travail sur main.

## Audit du code réel
| Fondation | Réutilisation / écart constaté |
|---|---|
| CAP-CORE-011 `RegistreProduits`, `SchemaProduits` | Type APPLICATION_INTERNE déjà présent. Identité, nom, propriétaire et cycle sont déjà canoniques. Les environnements imposent une audience fédérée : ils ne conviennent pas aux domaines publics internes. |
| CAP-CORE-001 `Ctr01` | Inscription d'une identité de type produit préalable à la fiche produit ; aucune nouvelle identité utilisateur nécessaire. |
| CAP-CORE-004 `Ctr03` | Règles actives, refus prioritaire, refus par défaut. La ressource est retournée mais ne filtre pas les règles. Normalisation de l'action limitée à 64 caractères. |
| `AccesProduits` / CAP-CORE-013 | Décision, preuve préalable, mutation et journal. Réutilisé pour la déclaration comme modification de métadonnées. |
| CAP-CORE-009 `RegistreContrats` | Registre gouverné/versionné existant. Aucun deuxième registre de contrats, aucune adoption de contrat ou règle inventée par migration. |
| CAP-CORE-022 | Environnements, audience et jetons fédérés ; conservés pour les produits externes. Une application interne n'utilise pas ces jetons pour simuler un second compte. |
| `ExigerSession`, `ExigerAccesConsole` | Session Core ; console centrale exige mandat et politique. Les routes Réseau actuelles partagent encore ce filtre global. |
| `AdministrationReseauGamadController` | Trois compteurs réels (signalements, entreprises en attente, contacts), paramètres de référentiels. Pas encore un dashboard complet ni une délégation applicative. |
| PR #124 initiale | Validation en mémoire seulement ; collisions non persistantes, nom dupliqué, pas de session, de décision ou de migration. |

## Conception du premier lot
La déclaration est une extension **1:1 du produit CAP-CORE-011**, pas un produit
concurrent : `produit_application.produit_reference` est sa clé primaire et une
référence au produit. Aucun nom, identité, propriétaire ou état n'est recopié.
Une révision append-only conserve la déclaration et sa provenance de gouvernance.
Une modification exige la révision actuelle (0 au premier appel).

`identifiant` est unique et immuable après déclaration. `domaine_public` est
nullable (application non encore exposée), normalisé en minuscules, sans port,
chemin, joker ou adresse IP. Les noms réservés et IDN sont refusés dans ce lot.
Un nom n'est pas une preuve de propriété DNS. Aucun DNS, certificat, proxy ou
connexion sortante n'est créé. L'unicité en base protège aussi deux écritures
concurrentes. Suspension conserve la réservation ; un changement explicite de
métadonnées peut retirer le domaine, en conservant l'historique.

`ApplicationsInstallees` décrit uniquement les adaptateurs et routes réellement
livrés. Ce catalogue de code n'inscrit pas de produit et ne donne aucun droit.
Seul Réseau y figure actuellement ; Travel et Couture ne sont pas inventés comme
applications opérationnelles. Les routes et capacités envoyées par le client
doivent correspondre au catalogue, jamais à une route administrative arbitraire.

Une capacité déclarée ne donne aucun droit. Les actions CAP-CORE-004 prennent la
forme `application gamad.reseau moderation`, etc. Elles sont courtes et distinctes
après normalisation. Aucune permission de console centrale n'est implicitement
convertie en permission applicative. Aucune nouvelle règle n'est adoptée par le code.

La déclaration d'une application interne ne peut autoriser sa fédération. Une fiche
avec extension applicative ne peut pas être retypée en satellite. Le comportement
des satellites existants reste couvert par les tests de fédération.

## Contrat HTTP livré
Sous la session API Core existante :
- `PUT /api/v1/produits/{reference}/application` : `identifiant`, `domaine_public`
  (null accepté), `route_accueil`, `route_console`, `capacites` (liste), `revision`.
  Décision via l'action de modification CAP-CORE-011 et preuve serveur ; acteur,
  politique et preuve du client sont ignorés.
- `GET /api/v1/produits/{reference}` : retourne également `application` ou null.
- `GET /api/v1/produits/{reference}/application/navigation` : routes nommées dont
  les capacités ont une permission explicite pour le sujet de la session ; 404 si
  application absente/non active ; liste vide sans habilitation.
- 401 sans session, 403 décision refusée, 409 collision/révision périmée,
  422 déclaration incompatible, 503 socle indisponible.

Le résolveur de domaine de CAP-CORE-011 ne retourne que la déclaration du domaine
exact d'un produit actif à la date courante, sans repli. Il n'est **pas encore un
middleware HTTP de routage**. La navigation calculée n'est **pas encore le filtre
d'accès des anciennes routes de console**. Aucun SSO interdomaines n'est livré.

## Compatibilité et migration
Migration 2 additive, tables `produit_application` et
`produit_application_revision`. Aucun DELETE de données existantes, ni changement
d'identité, ni réécriture du type/cycle des produits déjà présents. Le retour à
l'ancien code laisse ces tables intactes ; ne pas supprimer les déclarations.
Unicité, rollback et idempotence éprouvés sur SQLite et PostgreSQL 17 local.

Attention avant le raccordement réel de Réseau : la décision existante
`DEC-2026-09-08-GAMAD-PRODUIT-005` protège la référence de GAMAD (ancien portail),
son identité et ses délégations ; ses tests couvrent aussi une fédérabilité
historique. Ce lot ne convertit pas automatiquement PRD-GAMAD-005 et ne réutilise
pas sa référence pour un autre objet. La cible du raccordement doit être décidée
explicitement avant migration du produit réel vers APPLICATION_INTERNE.

## Qualification
- Contrat pur et domaines stricts : passe.
- Stockage SQLite et PostgreSQL 17 : passe (unicité domaine/identifiant, rollback,
  plusieurs domaines null, migration répétée).
- Garde CAP-CORE-011 : passe.
- Produits HTTP et scénarios applicatifs : passent localement ; résultat CI à vérifier sur le commit publié.
- Gardes et HTTP fédération : passent.
- Compte GAMAD HTTP : passe.
- Renommage PRD-GAMAD-005 : passe.
- Tests initiaux applicatifs corrigés : imports PHP du scénario inclus, champs de
  fixture politique, remise à zéro des quotas entre scénarios de test isolés.

## Séquence restante (ne pas confondre avec une livraison achevée)
1. Valider le rattachement canonique de Réseau (PRD-GAMAD-005 ou autre produit
   applicatif distinct) sans casser les délégations et consommateurs existants.
2. Raccorder le résolveur au routage HTTP : host autorisé exact, proxy de confiance,
   séparation hôte console/public, refus hôte inconnu, caches de routes et liens
   générés ; tests de Host et X-Forwarded-Host. Pas de redirection ouverte.
3. Raccorder les actions contextuelles aux routes métier (lecture ET écriture),
   puis adopter via contrats existants les règles nécessaires ; retrait immédiat
   des droits, mandat administratif préservé. La navigation seule ne protège rien.
4. Qualifier comptes/sessions host-only. Un compte commun n'implique pas un cookie
   partagé entre domaines : définir séparément tout futur parcours interdomaines.
5. Organiser la console centrale (produits, domaines, cycle, contrats et permissions)
   et le dashboard Réseau (modération, entreprises, contacts, référentiels livrés).
   Ne pas afficher de statistiques ou de fonctions de configuration inexistantes.
6. Développer ensuite les adaptateurs Travel/Couture et leurs propres tests ; pas
   de copie d'identités ou d'authentification.
7. Reprendre les interfaces suspendues après qualification de ces frontières.
