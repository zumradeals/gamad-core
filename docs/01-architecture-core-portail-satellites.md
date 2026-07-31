# Architecture Core, Portail et satellites

## 1. Principe de séparation

GAMAD Core porte les responsabilités transversales. Les satellites portent les responsabilités propres à leur métier.

Cette séparation évite deux erreurs :

- dupliquer l’identité, l’authentification et les droits dans chaque produit ;
- centraliser dans le Core toutes les données et toutes les règles de chaque produit.

## 2. Responsabilités du Core

Le Core peut posséder et exposer :

- l’identifiant canonique d’une personne, organisation, produit, agent ou service ;
- les relations minimales nécessaires à l’écosystème ;
- les authentificateurs, sessions et niveaux d’assurance ;
- les autorisations communes ;
- les produits reconnus et leurs points d’entrée ;
- les contrats d’échange ;
- les événements et preuves transversales ;
- les références de consentement et les finalités autorisées ;
- les signaux normalisés nécessaires au Matching lorsqu’ils sont autorisés ;
- les références temporaires de segments ou de résultats.

## 3. Responsabilités des satellites

Un satellite reste propriétaire de :

- son profil métier détaillé ;
- ses formulaires et pièces justificatives ;
- ses abonnements et tarifs ;
- ses quotas ;
- ses transactions ;
- ses contenus ;
- ses règles de validation ;
- ses décisions économiques ;
- ses interfaces et parcours utilisateurs.

Exemples :

- Wasplex conserve les profils publicitaires détaillés, campagnes, vues, récompenses et Wallet ;
- GamaDrive conserve les fichiers et autorisations de partage ;
- IKOMA conserve les ventes, stocks, articles et opérations de caisse.

## 4. Échanges par contrats

Aucun module ne doit lire directement la base privée d’un autre satellite.

Les échanges passent par des contrats explicites :

```text
commande
requête
événement
signal normalisé
attestation
référence temporaire
```

Chaque contrat doit préciser :

- le producteur ;
- le consommateur ;
- la finalité ;
- les données minimales ;
- le niveau d’autorisation ;
- la durée de validité ;
- les erreurs possibles ;
- la traçabilité attendue.

## 5. Identifiants et références

Le Core utilise une référence canonique stable. Chaque satellite peut conserver un identifiant local opaque.

```text
Identité GAMAD : IDN-PER-...
Wasplex : sujet local opaque
GamaDrive : sujet local opaque
IKOMA : sujet local opaque
```

Le lien entre la référence canonique et l’identifiant local est contrôlé. Un satellite ne reçoit pas automatiquement les relations d’un autre satellite.

## 6. Flux type d’ouverture d’un satellite

```text
Utilisateur authentifié dans GAMAD
→ choix du satellite dans le Portail
→ vérification des droits et de l’état du produit
→ émission d’un jeton destiné à ce satellite
→ création ou résolution du compte produit local
→ ouverture de la session locale
```

## 7. Flux type d’une demande transversale

```text
Satellite consommateur
→ demande avec finalité et critères
→ autorisation par le Core
→ lecture des signaux autorisés
→ calcul ou résolution
→ résultat minimal
→ action métier finale dans le satellite
```

## 8. Règle d’implémentation

Lorsqu’une fonction peut être réalisée soit dans le Core, soit dans un satellite, poser trois questions :

1. est-elle utile à plusieurs produits ?
2. dépend-elle d’une vérité commune à l’écosystème ?
3. peut-elle fonctionner sans absorber les règles détaillées d’un seul produit ?

Une réponse négative forte indique généralement que la fonction appartient au satellite.