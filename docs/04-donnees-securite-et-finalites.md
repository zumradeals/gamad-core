# Données, sécurité et finalités

## Principe

Une donnée disponible n’est pas automatiquement utilisable.

Toute utilisation commune doit préciser :

- la source gardienne ;
- la finalité ;
- le consommateur ;
- les attributs nécessaires ;
- la durée ;
- le niveau de preuve ;
- les restrictions ;
- les droits de correction, retrait et contestation.

## Minimisation

Les jetons et contrats ne transportent pas le dossier métier complet par défaut.

Préférer :

- une référence canonique plutôt qu’un profil ;
- une attestation plutôt qu’un document ;
- un signal limité plutôt qu’un historique ;
- une référence de segment plutôt qu’une liste d’identités ;
- une réponse oui/non/indéterminé lorsque le détail n’est pas nécessaire.

## Authentification et sessions

- les secrets d’authentification restent dans le Core ;
- les satellites reçoivent des jetons destinés uniquement à eux ;
- les jetons sont courts, validés et révocables ;
- les actions sensibles peuvent demander une élévation d’assurance ;
- la déconnexion globale et la propagation des suspensions doivent être testées.

## Données interdites au profil commun

Le Core ne doit pas construire implicitement :

- une réputation universelle ;
- un jugement moral, spirituel ou humain ;
- un dossier intime sans finalité légitime ;
- un historique transversal illimité ;
- un profil commercial universel fusionnant tous les satellites.

## Audit

Les opérations critiques produisent une trace attribuable et corrélable, sans enregistrer les secrets ni exposer inutilement les données personnelles.

Une preuve technique doit pouvoir être vérifiée et, pour un test, falsifiée de manière contrôlée afin de démontrer que le contrôle peut réellement échouer.
