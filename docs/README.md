# Documentation active de GAMAD Core

Cette documentation décrit directement le produit, ses frontières et les capacités à construire. Elle remplace progressivement la lecture des anciens textes Genesis II pour les nouveaux chantiers.

Elle ne prouve pas qu’une fonction est implémentée. L’état réel doit toujours être vérifié dans le code, les migrations, les tests et l’exploitation.

## Ordre de lecture

1. [`00-vision-ecosysteme-gamad.md`](00-vision-ecosysteme-gamad.md)
2. [`01-architecture-core-portail-satellites.md`](01-architecture-core-portail-satellites.md)
3. [`02-compte-gamad-et-federation.md`](02-compte-gamad-et-federation.md)
4. [`03-matching-transversal.md`](03-matching-transversal.md)
5. [`04-donnees-securite-et-finalites.md`](04-donnees-securite-et-finalites.md)
6. [`05-exploitation-continuite-et-preuves.md`](05-exploitation-continuite-et-preuves.md)
7. [`06-transition-hors-genesis-ii.md`](06-transition-hors-genesis-ii.md)
8. [`07-consignes-operationnelles-agents.md`](07-consignes-operationnelles-agents.md)
9. [`capacites/README.md`](capacites/README.md)
10. [`capacites/CATALOGUE.md`](capacites/CATALOGUE.md)

## Situation temporaire de `CLAUDE.md`

Le fichier racine `CLAUDE.md` reste inchangé pendant cette première étape, car l’ancien contrôle d’intégrité Genesis II vérifie encore son empreinte Git.

Les consignes cibles sont déjà écrites dans `07-consignes-operationnelles-agents.md`. Leur déplacement définitif vers `CLAUDE.md` sera réalisé dans la première PR technique, en même temps que le découplage du contrôle historique.

## Règle de mise à jour

Un document doit répondre à une question opérationnelle claire :

- ce que le Core doit faire ;
- ce que le Core ne doit pas faire ;
- les données qu’il possède ;
- les contrats qu’il expose ;
- les produits qui le consomment ;
- l’état réel du code ;
- les manques et le prochain chantier.

Ne pas ajouter de loi, d’acte d’adoption, de décision normative ou de statut déclaratif pour remplacer une implémentation manquante.