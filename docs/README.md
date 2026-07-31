# Documentation active de GAMAD Core

Cette documentation décrit directement le fonctionnement attendu du Core.

## Documents transversaux

- `00-vision-gamad-core.md` — mission et limites du Core ;
- `01-architecture-core-et-satellites.md` — séparation Core, Portail et produits ;
- `02-compte-gamad-federation-et-portail.md` — identité unique, connexion commune et provisionnement ;
- `03-moteur-de-matching-gamad.md` — rapprochement des offres, besoins, personnes et organisations ;
- `04-donnees-securite-et-finalites.md` — signaux autorisés, minimisation et contrôles ;
- `05-exploitation-continuite-et-preuves.md` — production, sauvegarde, restauration et état réel ;
- `capacites/` — fiches des capacités du Core.

## Lecture d’une fiche de capacité

Chaque fiche indique :

- son objectif ;
- ce qu’elle possède ;
- ce qu’elle ne possède pas ;
- ses entrées et sorties ;
- ses dépendances ;
- son code actuel ;
- ses tests ;
- son état réel ;
- le prochain résultat à construire.

Les statuts utilisés sont :

- `OPÉRATIONNEL LIMITÉ` ;
- `IMPLÉMENTÉ PARTIEL` ;
- `HÉRITÉ À MIGRER` ;
- `À CONSTRUIRE` ;
- `PROJETÉ`.

Un statut peut évoluer uniquement lorsque le code et les preuves techniques évoluent.
