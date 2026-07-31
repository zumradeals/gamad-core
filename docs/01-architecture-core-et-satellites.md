# Architecture du Core et des satellites

## Vue générale

```text
                         PORTAIL GAMAD
                 entrée publique et authentifiée
                               |
                               v
                         GAMAD CORE
     identité · authentification · autorisation · contrats
      produits · événements · audit · Matching · fédération
         |                 |                  |
         v                 v                  v
     GamaDrive          Wasplex          autres satellites
```

## Une identité, plusieurs comptes produits

Une personne possède une seule référence canonique dans GAMAD Core.

Chaque satellite peut créer un compte produit local contenant uniquement ce dont son métier a besoin. Ce compte conserve une référence opaque vers l’identité canonique.

La fermeture du compte local n’efface pas l’identité GAMAD.

## Coopération

Les satellites coopèrent par :

- API explicites ;
- événements ;
- attestations ;
- références temporaires ;
- liens signés ;
- signaux de Matching ;
- contrats versionnés et permissions limitées.

Ils ne coopèrent pas par lecture directe des bases, partage de mots de passe, copie incontrôlée de tables ou jeton universel.

## Propriété des données

La source métier reste gardienne de son dossier détaillé.

Le Core peut traiter une projection minimale lorsqu’elle est :

- nécessaire à une finalité ;
- autorisée ;
- sourcée ;
- datée ;
- limitée à certains consommateurs ;
- temporaire ou révisable ;
- traçable.

## Modes dégradés

Chaque satellite doit définir ce qu’il fait lorsque le Core est temporairement indisponible :

- continuer une session locale récemment validée ;
- interdire une nouvelle connexion ;
- refuser les opérations sensibles ;
- mettre les événements en attente ;
- utiliser un cache signé et expirant ;
- réconcilier ensuite.

Un mode dégradé ne crée jamais une identité souveraine locale concurrente et ne prolonge pas indéfiniment une autorisation.
