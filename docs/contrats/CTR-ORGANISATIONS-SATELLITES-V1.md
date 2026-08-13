# CTR-ORGANISATIONS-SATELLITES-V1

**Version :** 1.0.0  
**Producteur :** Gamad Core  
**Premier consommateur :** GamaDrive (`PRD-GAMAD-002`)  
**Statut :** contrat d'intégration v1

## Finalité

Permettre à un satellite de reconnaître, sans accès direct aux magasins du
Core, les organisations auxquelles une identité fédérée est activement
affiliée. Le Core reste la source canonique des identités, organisations et
affiliations. Le satellite reste propriétaire de ses rôles, permissions et
données métier.

## Route

`GET /api/v1/produits/{produit}/identites/{reference}/organisations`

L'appelant ouvre une session Core avec l'identifiant de raccordement du
satellite. La référence portée par la session doit être identique à
`{produit}`. L'identité demandée doit déjà posséder un lien actif avec ce
produit, créé par le parcours fédéré.

## Projection

La réponse expose :

- les références canoniques de l'identité, du produit, de l'organisation et de
  l'affiliation ;
- le nom d'affichage et l'état courant de l'organisation ;
- le type, l'état et le niveau d'assurance de l'affiliation ;
- l'indication qu'une identité est propriétaire désignée ;
- une version déterministe du contexte pour détecter un changement.

Elle n'expose jamais :

- la classification interne ou des données RH ;
- les membres des autres organisations ;
- les secrets et jetons ;
- les rôles ou permissions propres au satellite.

`DIRIGEANT`, `REPRESENTANT` et `proprietaire_designe=true` restent des faits
descriptifs. Ils ne doivent pas être convertis automatiquement en rôle
administrateur ou propriétaire du satellite. En v1, `mandat_opposable` reste
explicitement à `false` jusqu'à l'introduction d'une attestation de mandat
dédiée.

## Synchronisation attendue

Le consommateur lit le contexte après avoir vérifié le jeton fédéré, puis à
intervalle raisonnable. `version_contexte` permet d'éviter les écritures
locales inutiles. Une réponse `403 ACCES_PRODUIT_INACTIF` impose de fermer la
session locale ou d'exiger une nouvelle ouverture fédérée.

Les événements de changement et la réconciliation périodique constituent une
évolution compatible ultérieure ; ils ne changent pas la forme de cette v1.
