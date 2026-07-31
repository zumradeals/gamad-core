# Vision de l’écosystème GAMAD

## 1. Objectif

GAMAD forme un seul écosystème numérique composé d’un socle commun et de plusieurs produits spécialisés.

La personne ou l’organisation doit pouvoir utiliser plusieurs services GAMAD avec une identité commune, une authentification cohérente et des relations maîtrisées, sans que tous les produits fusionnent leurs bases et leurs métiers.

## 2. Les cinq niveaux

### GAMAD Core

Le Core fournit les capacités communes :

- identité canonique ;
- organisations et relations minimales ;
- authentification et sessions ;
- autorisations ;
- catalogue des produits ;
- contrats et événements transversaux ;
- audit, preuves techniques et continuité ;
- fédération des satellites ;
- Matching transversal.

### Portail GAMAD

Le Portail est la maison commune de l’écosystème :

- création et gestion du Compte GAMAD ;
- accès aux satellites ;
- gestion des appareils, sessions et facteurs d’authentification ;
- affichage des produits activés ;
- gestion des consentements communs ;
- continuité de navigation.

Le Portail ne doit pas absorber les interfaces métier des satellites.

### Fédération

La fédération permet au même Compte GAMAD d’ouvrir plusieurs produits avec des jetons et des droits limités à chaque produit.

Elle gère le provisionnement, l’activation, la révocation, la déconnexion et la continuité d’accès. Elle ne possède pas les données métier détaillées.

### Matching transversal

Le Matching rapproche des personnes, organisations, besoins, offres, institutions et signaux autorisés selon une finalité explicite.

Il peut alimenter Wasplex, un futur produit Emploi, IKOMA ou d’autres satellites. Il ne remplace pas leurs décisions métier.

### Satellites

Les satellites sont les organes spécialisés de l’écosystème. Chacun conserve :

- son compte produit local ;
- son stockage ;
- ses données détaillées ;
- ses abonnements et quotas ;
- ses transactions ;
- ses règles métier ;
- son interface ;
- son exploitation spécifique.

## 3. Exemples de satellites

- **Wasplex** : publicité, Feed, Wallet, Alertes, Fonds social et Cartes partenaires.
- **GamaDrive** : documents, dossiers, partage et conservation.
- **IKOMA** : ventes, produits, stocks et caisse.
- **G-Mail** : messagerie et boîtes mail.
- **G-Business** : activités économiques et relations d’affaires.
- **Futur produit Emploi** : offres, candidatures et parcours de recrutement.

## 4. Formule d’architecture

```text
Un écosystème GAMAD
        │
        ├── une identité commune
        ├── une authentification commune
        ├── un Portail commun
        ├── une fédération commune
        ├── un Matching transversal
        │
        └── plusieurs satellites autonomes dans leur métier
```

## 5. Ce que GAMAD ne doit pas devenir

GAMAD ne doit devenir ni :

- une collection d’applications isolées avec une identité différente partout ;
- une super-application unique absorbant toutes les interfaces et toutes les bases ;
- une base de surveillance universelle ;
- un système produisant une note générale sur la valeur d’une personne.

## 6. Résultat attendu

Une même personne peut utiliser plusieurs satellites avec le même Compte GAMAD, tandis que chaque satellite conserve son autonomie métier et n’accède qu’aux informations autorisées pour son fonctionnement.