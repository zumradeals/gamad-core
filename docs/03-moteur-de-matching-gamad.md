# Moteur de Matching GAMAD

## Objectif

Le Moteur de Matching transforme la connaissance autorisée de l’écosystème en correspondances utiles entre personnes, organisations, offres, besoins et institutions.

Il appartient au Core et peut être consommé par Wasplex, G-Business, un portail de services, un satellite Emploi, des institutions ou d’autres produits autorisés.

## Ce qu’il produit

- qualification ;
- appariement ;
- recommandation ;
- classement contextualisé ;
- segment temporaire ;
- estimation agrégée ;
- explication des facteurs favorables, défavorables et non établis.

Un résultat est une recommandation contextualisée, jamais une vérité générale sur une personne.

## Architecture fédérée

Le Matching n’aspire pas toutes les bases des satellites.

Il consomme par contrat :

- les références canoniques du Core ;
- les signaux communs autorisés ;
- les offres et besoins transmis par le consommateur ;
- des réponses ciblées fournies par les satellites gardiens ;
- les restrictions de sécurité et d’autorisation.

Le modèle recommandé est hybride :

- signaux communs, peu sensibles et fréquemment utilisés : projection temporaire dans le Core ;
- données détaillées, rares ou sensibles : interrogation à la demande du satellite gardien.

## Signal normalisé

Un signal doit au minimum nommer :

- le sujet canonique ;
- le type de signal ;
- la valeur ou l’état établi ;
- la source ;
- la finalité ;
- la date d’observation ;
- l’expiration ;
- le niveau de confiance ou de preuve ;
- la référence de consentement ou d’autorisation lorsque nécessaire.

## Segments protégés

Un consommateur reçoit par défaut :

- une référence de segment ;
- sa taille estimée ;
- sa politique ;
- son expiration ;
- ses obligations d’utilisation.

Il ne reçoit pas la liste nominative complète ni les attributs profonds.

## Cas Wasplex

Pour une campagne BMW visant Abobo, les critères peuvent comprendre zone, âge, intérêt automobile et possession d’un véhicule.

Le Core construit ou vérifie la correspondance. Wasplex conserve les campagnes, le budget, la diffusion, la fréquence, les quotas, la preuve de visionnage, le débit, la rémunération et le reporting.

```text
Éligibilité finale Wasplex
= correspondance Matching
+ consentement actif
+ campagne approuvée et financée
+ quota et fréquence
+ budget et dates valides
```

## Limites

Le Matching :

- ne crée pas ou ne fusionne pas les identités ;
- ne donne pas de mandat ;
- ne vend, ne diffuse et ne facture pas ;
- ne produit pas de réputation universelle ;
- ne réutilise pas un segment pour une autre finalité ;
- ne transforme pas une donnée inconnue en réponse défavorable ;
- ne prend pas seul une décision sensible.

## État actuel

La conception fonctionnelle existe, mais le moteur transversal complet n’est pas encore implémenté. Le premier incrément doit être déterministe, explicable et capable de refuser une demande sans finalité, consommateur, politique ou critère autorisé.
