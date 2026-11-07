# GAMAD Réseau — Mon Espace

**Statut : maquette visuelle validée par le commanditaire le 7 novembre 2026.**
**Nature : contrat de conception, non preuve de disponibilité du code.**

## Référence visuelle
Maquette générée et approuvée dans la conversation du 7 novembre 2026 : tableau de bord desktop « GAMAD Réseau — Mon Espace », fond blanc, bleu nuit et touches jaune/orange. **L'image binaire doit être ajoutée au dépôt par Work dans `assets/mon-espace-reference.png` avant toute implémentation de l'écran.** Ne pas substituer une autre image sans nouvelle validation.

## Composition de référence
- En-tête : marque GAMAD Réseau, Accueil, Découvrir, Agir, Zumra, Opportunités, Entreprises, notifications, messagerie et accès au compte.
- Colonne personnelle : photo/avatar, identité publique, localisation si consentie, présentation, disponibilité, accès « Voir mon profil public » ; navigation Mon espace, Mon profil, Mes activités, Mes projets, Mes opportunités, Mes communautés, Mes échanges, Mes favoris, Paramètres.
- Zone principale : titre Mon Espace ; indicateurs Actions en cours, Réalisations, Projets/Zumras, Opportunités suivies.
- Objectif du moment : objectif personnel, état/progression et accès à la modification. Progression déclarée et non automatiquement certifiée.
- Mes activités récentes : vues Tout, Publications, Actions, Projets, Opportunités ; chronologie et statuts.
- Colonne de droite : Mes prochaines actions (échéances, réponses attendues), Suggestions pour moi (personnes, communautés, opportunités).
- Zone « Agir maintenant » : J'ai besoin d'aide ; Je veux apprendre ; Je peux aider ; Je partage un savoir ; Je montre une réalisation.
- Sur mobile : préserver la hiérarchie, éviter de transposer trois colonnes ; navigation Accueil, Découvrir, Agir, Mon espace.

## Règles fonctionnelles
- Tableau de bord personnel, distinct du profil public et de la console administrative.
- Une action ou réalisation affichée doit provenir des données autorisées du Core ; ne pas inventer de scores, statuts, notifications ou suggestions.
- Les compteurs et suggestions de la maquette sont **illustratifs**, pas des données réelles ni des exigences quantitatives.
- « Agir » ouvre des formulaires contextualisés, selon les capacités et règles métier réellement autorisées.
- Les actualités et images peuvent être publiées sans bouton d'action contextuelle obligatoire ; commentaires et partage restent possibles selon visibilité.
- Aucune fonctionnalité ne doit être déclarée implémentée sur la seule base de cette maquette.
- Accessibilité, lisibilité, confidentialité et adaptation mobile à qualifier avant réalisation.

## Parcours
1. Se connecter via le Core → accéder à Mon Espace.
2. Voir ce qui est en cours et ce qui nécessite une action.
3. Consulter l'historique ou reprendre un engagement.
4. Ouvrir le profil public ou une activité.
5. Démarrer une nouvelle action via les cinq intentions.
6. Suivre la réalisation et, le cas échéant, sa valorisation.

## Hors périmètre
Aucune nouvelle autorité d'identité, aucun moteur de réputation inventé, aucun SSO, aucun déploiement.
