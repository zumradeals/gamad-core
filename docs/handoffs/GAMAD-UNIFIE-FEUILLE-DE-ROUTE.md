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
