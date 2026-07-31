# Matching transversal GAMAD

## 1. Objectif

Le Matching transforme des informations autorisées de l’écosystème en correspondances utiles entre :

- personnes ;
- organisations ;
- besoins ;
- offres ;
- produits ;
- institutions ;
- territoires ;
- signaux contextuels.

Il appartient au Core parce qu’il doit pouvoir servir plusieurs satellites.

## 2. Ce que le Matching ne fait pas

Le Matching ne doit pas :

- créer les comptes GAMAD ;
- authentifier les utilisateurs ;
- facturer une campagne ;
- créditer un Wallet ;
- décider de l’embauche ;
- décider d’une aide sociale ;
- produire une réputation humaine universelle ;
- exposer aux consommateurs les dossiers complets des sources.

Il produit une recommandation ou une éligibilité contextualisée. Le satellite consommateur applique ensuite ses règles métier.

## 3. Propriété des données

Le Core n’est pas propriétaire des dossiers métier complets.

Il peut consommer :

- des signaux normalisés publiés par les satellites ;
- des réponses à des requêtes autorisées ;
- des attestations ;
- des offres et besoins déclarés pour être rapprochés ;
- des données communes déjà détenues par le Core.

Chaque information doit conserver :

- sa source ;
- sa finalité ;
- sa date ;
- sa durée de validité ;
- son niveau de confiance ou de preuve ;
- sa référence de consentement ou d’autorisation lorsqu’elle est nécessaire.

## 4. Modèle hybride

### Signaux matérialisés

Les signaux fréquents, peu sensibles et nécessaires à la performance peuvent être copiés sous forme normalisée et temporaire dans le Core.

Exemples :

```text
geo.country = CI
geo.city = Abidjan
interest.automotive = true
offer.category = pieces_detachees
```

### Interrogation à la demande

Les informations détaillées, sensibles ou rares restent chez la source. Le Core demande seulement une réponse minimale.

Exemple :

```text
Question : cette personne dispose-t-elle d’un permis vérifié pour cette finalité ?
Réponse : oui, niveau vérifié, valable jusqu’à telle date.
```

Le document original n’est pas transféré au Matching.

## 5. Opérations principales

Le moteur doit pouvoir, selon les autorisations :

- qualifier un besoin ou une offre ;
- estimer une audience ;
- calculer une correspondance ;
- classer des résultats ;
- construire un segment temporaire ;
- vérifier l’appartenance à un segment ;
- expliquer les principaux critères d’un résultat ;
- signaler les critères inconnus ou non disponibles ;
- expirer et supprimer les résultats temporaires.

## 6. Exemple Wasplex

Une campagne automobile demande :

- Côte d’Ivoire ;
- Abidjan ;
- Abobo ;
- âge de 25 à 55 ans ;
- possession d’un véhicule ;
- intérêt pour l’automobile.

Wasplex transmet la finalité et les critères. Le Matching vérifie les sources autorisées et retourne une référence de segment et une estimation agrégée.

```text
Wasplex
→ demande de Matching
→ segment temporaire
→ vérification d’éligibilité dans le Feed
→ règles de diffusion Wasplex
→ preuve de vue et économie gérées par Wasplex
```

L’éligibilité finale dépend à la fois de la correspondance et des règles locales : campagne financée, dates, quota, fréquence, consentement et budget.

## 7. Exemple IKOMA

IKOMA peut publier des offres ou signaux de disponibilité sans transmettre toute sa comptabilité.

Le moteur peut rapprocher un besoin de pièces automobiles et un vendeur disponible dans la bonne zone. La vente, le prix et le stock final restent gérés par IKOMA.

## 8. Résultat explicable

Un résultat doit pouvoir indiquer :

- les critères satisfaits ;
- les critères défavorables ;
- les critères inconnus ;
- les sources utilisées ;
- la version de politique ;
- la date d’expiration.

Le score éventuel est propre au contexte et à la politique de Matching. Il ne représente jamais une valeur générale de la personne ou de l’organisation.

## 9. État actuel

La présence d’une conception ou d’une référence `CAP-CORE-021` ne prouve pas que le moteur complet est opérationnel. Avant tout chantier, vérifier le code, les contrats, les données d’essai, les tests et les intégrations satellites réellement disponibles.