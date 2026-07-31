# Compte GAMAD et fédération des satellites

## 1. Compte GAMAD

Le Compte GAMAD est la porte d’accès commune à l’écosystème.

Il porte notamment :

- la référence canonique ;
- les moyens d’authentification ;
- les sessions et appareils ;
- les facteurs forts ;
- les consentements communs ;
- les produits activés ;
- les liens minimaux vers les comptes produit.

Le Compte GAMAD n’est pas le compte métier de Wasplex, GamaDrive ou IKOMA.

## 2. Compte produit local

Chaque satellite conserve un compte local lié à l’identité GAMAD.

Ce compte peut porter :

- le rôle dans le produit ;
- le plan ou l’abonnement ;
- les préférences locales ;
- les quotas ;
- l’état du compte ;
- l’historique et les données métier.

Fermer un compte satellite ne supprime pas l’identité GAMAD. Supprimer une session GAMAD ne supprime pas les données métier détenues légalement par le satellite.

## 3. Provisionnement

À la première ouverture d’un satellite :

```text
Compte GAMAD authentifié
→ vérification de l’accès au satellite
→ création d’un lien produit
→ création du compte produit local
→ attribution des valeurs initiales autorisées
→ ouverture de la session locale
```

Le provisionnement doit être idempotent : répéter la demande ne doit pas créer plusieurs comptes locaux pour la même relation.

## 4. Jetons destinés à un satellite

Un jeton fédéré doit être limité :

- à un satellite précis ;
- à une durée courte ;
- à des droits explicites ;
- à un contexte ou une audience définie ;
- à une référence de session ;
- à un niveau d’assurance connu.

Un jeton destiné à Wasplex ne doit pas être utilisable par GamaDrive.

## 5. Déconnexion et révocation

La fédération doit permettre :

- la déconnexion locale d’un satellite ;
- la déconnexion globale ;
- la révocation d’un appareil ;
- la révocation d’une session ;
- la désactivation d’un lien produit ;
- la suspension temporaire ;
- la reprise contrôlée après vérification.

## 6. Données visibles par le Portail

Le Portail peut afficher des informations minimales :

- nom du produit ;
- état d’activation ;
- dernière ouverture ;
- niveau d’accès ;
- action d’ouverture ou de révocation.

Il ne doit pas afficher automatiquement les campagnes Wasplex, les fichiers GamaDrive ou les ventes IKOMA.

## 7. Premier parcours pilote

La fédération doit être validée sur un produit pilote avec un parcours réel de bout en bout :

1. création ou résolution du Compte GAMAD ;
2. authentification ;
3. ouverture depuis le Portail ;
4. provisionnement du compte local ;
5. accès au satellite ;
6. déconnexion locale et globale ;
7. révocation ;
8. audit du parcours.

GamaDrive V2 peut servir de premier pilote, puis Wasplex de deuxième intégration. Ce choix doit être confirmé par le chantier concerné et par l’état réel des produits.