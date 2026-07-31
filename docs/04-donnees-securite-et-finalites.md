# Données, sécurité et finalités

## 1. Principe de minimisation

Le Core traite uniquement les données nécessaires à une responsabilité commune clairement définie.

Une information ne doit pas être centralisée seulement parce qu’elle pourrait être utile un jour. Son stockage ou son utilisation doit être lié à une finalité explicite, un responsable et une durée.

## 2. Catégories de données

### Données communes du Core

- références canoniques ;
- authentificateurs et sessions ;
- niveaux d’assurance ;
- relations minimales avec produits et organisations ;
- autorisations communes ;
- contrats, événements et preuves techniques ;
- catalogue des satellites ;
- consentements transversaux ;
- signaux de Matching autorisés et limités.

### Données détaillées des satellites

- profils publicitaires Wasplex ;
- campagnes, vues, récompenses et Wallet ;
- fichiers GamaDrive ;
- ventes et stocks IKOMA ;
- messages G-Mail ;
- dossiers détaillés propres aux futurs produits.

Ces données restent sous la responsabilité du satellite qui les collecte et les exploite.

## 3. Finalité obligatoire

Toute lecture ou transmission transversale doit préciser :

- la finalité ;
- le produit demandeur ;
- le sujet ou la population concernée ;
- les catégories de données ;
- la durée ;
- la base d’autorisation ;
- l’usage permis ;
- l’usage interdit.

Une donnée reçue pour le Matching publicitaire ne doit pas être réutilisée silencieusement pour l’emploi, le crédit ou une réputation générale.

## 4. Consentement et autorisation

Le consentement utilisateur n’est pas l’unique mécanisme possible, mais lorsqu’il est requis il doit être :

- compréhensible ;
- spécifique ;
- traçable ;
- révocable ;
- associé à une version ;
- vérifié au moment de l’usage.

Les autorisations institutionnelles ou contractuelles doivent être limitées de la même manière.

## 5. Durée et expiration

Les signaux, segments et résultats temporaires doivent avoir une date d’expiration.

À l’expiration :

- le signal n’est plus utilisable ;
- le segment n’est plus activable ;
- une nouvelle qualification est requise ;
- la suppression ou l’archivage suit la politique applicable.

## 6. Classification et secrets

Les secrets, clés privées, jetons bruts et mots de passe ne doivent jamais être inscrits dans Git, dans la documentation ou dans les journaux.

Les données sensibles doivent être protégées par :

- chiffrement adapté ;
- contrôle d’accès ;
- séparation des rôles ;
- journalisation ;
- rotation des secrets ;
- limitation des exports ;
- procédures de révocation.

## 7. Audit

Une opération sensible doit pouvoir indiquer :

- qui a demandé ;
- pour quel produit ;
- quelle action ;
- sur quelle ressource ;
- quelle décision a été prise ;
- quelle politique ou configuration a été appliquée ;
- quand l’action a eu lieu ;
- quelle preuve technique permet de la retrouver.

L’audit ne doit pas recopier inutilement les données sensibles de l’opération.

## 8. Refus par défaut

L’absence de règle ou d’autorisation explicite ne vaut pas permission.

Le système doit refuser proprement, produire un motif compréhensible et tracer le refus lorsqu’une opération sensible ne peut pas être établie.

## 9. Suppression et portabilité

Chaque capacité doit définir :

- ce qui peut être supprimé ;
- ce qui doit être conservé pour une obligation ou une preuve ;
- ce qui peut être exporté ;
- le format de restitution ;
- les effets sur les relations avec les satellites ;
- la différence entre fermeture d’un compte produit et clôture d’une identité GAMAD.