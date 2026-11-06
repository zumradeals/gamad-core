# Transfert complet de GAMAD Réseau vers GAMAD Core

## Vision approuvée

GAMAD est une application unique livrée depuis `zumradeals/gamad-core`, avec une seule base PostgreSQL. Le Core possède les comptes, les profils et leurs données métier. Il gouverne l’authentification, les sessions, les autorisations, la suspension et la révocation. Réseau utilise directement le compte canonique.

Le propriétaire a confirmé une installation neuve sans données historiques à reprendre. Le dépôt source `zumradeals/gamad` sert de référence pour les écrans, les comportements et les tests à transférer. La référence de départ est `83d1a1df1496f322a5e718996c9420120d021ec0`.

## Conception

- Un seul démarrage Laravel et un seul catalogue de routes. L’accueil public Réseau est à `/`, la console Core sous `/console`. Les noms et URI des parcours importés sont inventoriés afin de vérifier les collisions et les redirections.
- Les associations métier se rattachent directement au compte Core. Aucun fournisseur de comptes local, aucune session autonome, aucune table de correspondance.
- Un seul magasin PostgreSQL sur installation neuve. Les frontières entre registres et données métier demeurent explicites dans les contrats du Core.
- Reprendre les règles existantes de visibilité, d’autorisation et de modération avant d’exposer les parcours correspondants.

## Implémentation en une PR

1. Recenser et transférer les écrans publics, les vues, les composants, les styles, les ressources et les routes.
2. Intégrer les écrans et opérations authentifiés. Reprendre les profils, l’annuaire, le fil, les publications, la messagerie, les projets, les ZUMRA, les opportunités et les écrans administratifs, ainsi que les autres parcours relevés à l’inventaire.
3. Raccorder les inscriptions, sessions, écritures de compte et règles d’accès à l’autorité Core. Installer l’ensemble dans une base PostgreSQL vierge.
4. Vérifier le rendu et les actions dans le navigateur, l’accès public et privé, les refus attendus et la révocation. Comparer les écrans et parcours avec l’inventaire source et vérifier la CI sur le commit de livraison.

La PR peut être construite en plusieurs commits vérifiables. Le transfert est terminé lorsque tous les écrans et leurs opérations fonctionnent dans `gamad-core` avec le même compte Core et la même base. Le déploiement fait l’objet d’une décision séparée.

## Inscription publique

Le formulaire web utilise le produit GAMAD existant `PRD-GAMAD-005`. Sur installation neuve, ce produit doit être inscrit et activé dans le registre Core, la politique d’inscription doit être initialisée par `core:comptes:bootstrap` et le canal e-mail du Core doit être configuré. Le formulaire suit la règle historique de Réseau de huit caractères minimum. Le code et son renvoi passent par les mêmes opérations Core que l’API. Le défi en cours est lié à la session du navigateur qui a ouvert l’inscription.

## Premiers contenus Réseau

Le fil authentifié et les fiches de publication utilisent le compte Core à e-mail vérifié. Le transfert couvre les publications personnelles textuelles « Je partage », « Je partage un savoir » et « Ma réalisation », les audiences public et membres, et les commentaires simples. Les références d’auteur sont des références Core. Une révocation retire l’auteur et ses contenus des lectures Réseau.

Restent à intégrer dans ce domaine les photos de profil, les territoires, les domaines, les pièces jointes, les autres types de publication et leurs actions, l’édition et les réponses aux commentaires, ainsi que la modération.
