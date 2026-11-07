# GAMAD Réseau — Page d'entrée, connexion et inscription
Statut : **maquette visuelle validée**, image à ajouter dans assets ; décision fonctionnelle à préciser sur les accès anonymes.

## Décision
**Pas de page d'accueil publique de présentation pour GAMAD Réseau.** L'entrée normale est une page de connexion/inscription qui expose immédiatement la vision du Réseau. Elle ne doit pas être confondue avec la page institutionnelle de la console Core.

## Composition de référence
- Deux zones sur ordinateur : panneau de mission et de bénéfices GAMAD à gauche ; formulaire d'accès à droite.
- Message central : réseau des personnes, compétences et actions ; valorisation du savoir-faire même sans diplôme, projets individuels ou en Zumra, opportunités, développement des activités.
- Onglets Se connecter / Créer un compte ; identifiant, mot de passe, récupération, conservation de session dans les limites des protections du Core.
- Version mobile simplifiée : formulaire directement visible, mission accessible sans surcharge.
- Identité graphique GAMAD Réseau : bleu nuit, blanc, accents jaune/orange.

## Frontières
- Authentification, inscription, comptes, identités et sessions exclusivement sous le GAMAD Core ; aucun système parallèle.
- Google, Apple et Facebook figurant sur la maquette sont **illustratifs, non approuvés comme intégrations**.
- Pas de SSO improvisé ; respecter HTTPS, CSRF, cookies et domaines.
- Décision antérieure : consultation de contenus **explicitement publics** sans connexion. La suppression de la page d'accueil marketing ne révoque pas automatiquement cette visibilité ; l'accès direct aux profils/contenus publics reste à préciser dans les parcours.
