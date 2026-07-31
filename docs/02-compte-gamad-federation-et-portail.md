# Compte GAMAD, fédération des satellites et Portail

## Objectif

Une personne utilise un seul Compte GAMAD pour accéder à plusieurs satellites sans recréer une identité générale, un mot de passe ou une passkey dans chaque produit.

## Distinctions

### Identité canonique

Répond à : « Qui est cette personne ou cette entité dans GAMAD ? »

### Compte GAMAD

Expérience commune regroupant l’identité, l’authentification, les sessions, appareils, relations avec les produits, organisations, consentements et préférences communes.

### Compte produit

Présence opérationnelle locale dans Wasplex, GamaDrive, G-Mail, G-Business ou un autre satellite.

### Relation identité-produit

Lien daté et traçable entre l’identité et le compte produit local.

### Droit d’accès

Un Compte GAMAD ne donne pas automatiquement accès à tous les produits. Un produit peut être visible, éligible, activable, provisionné, actif, limité, suspendu ou fermé.

## Connexion fédérée

Architecture cible :

- OpenID Connect pour l’identité de session ;
- OAuth et Authorization Code avec PKCE pour les applications ;
- WebAuthn/passkeys pour l’authentification forte ;
- jetons courts et destinés à un satellite précis ;
- identités de service pour les communications machine-à-machine ;
- rotation des clés et validation stricte de l’émetteur, du destinataire et de l’expiration.

Un jeton GamaDrive doit être refusé par Wasplex.

## Provisionnement

Modèle recommandé : création juste à temps au premier accès.

```text
Compte GAMAD existant
→ ouverture d’un satellite
→ contrôle d’éligibilité
→ création idempotente du compte local
→ rattachement au sujet local opaque
→ accès
```

Politiques possibles : automatique, activation explicite, invitation, organisation requise, abonnement requis, approbation ou indisponibilité.

## Portail GAMAD

Le Portail est :

- la porte d’entrée publique ;
- le lanceur de satellites ;
- l’espace du Compte GAMAD ;
- le centre des sessions, appareils et passkeys ;
- le catalogue des produits ;
- le centre des consentements communs et alertes de sécurité.

Il n’est ni une base universelle, ni le Matching, ni une messagerie, ni un stockage documentaire, ni une console d’administration publique.

## Premier pilote

GamaDrive V2 est le premier satellite pilote recommandé. Wasplex est la deuxième intégration.

Le pilote doit démontrer l’identité unique, la connexion sans second mot de passe, le provisionnement idempotent, le jeton limité au produit, la déconnexion globale et la fermeture d’un compte produit sans suppression de l’identité.
