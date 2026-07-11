# ADR-0006 — The Core governs relationships before data

**Statut :** Accepté  
**Date :** 2026-07-11  
**Décideurs :** Orchestrateur de GAMAD et Architecte de GAMAD  
**Référence :** GENESIS-007A — Concepts et relations du GAMAD Core

---

## Contexte

Les modules GAMAD possèdent des données métier hétérogènes : documents, messages, sauvegardes, contenus communautaires, connaissances, ressources synchronisées ou futures données sectorielles.

Si le Core cherchait à centraliser ces contenus, il deviendrait rapidement un monolithe métier, difficile à faire évoluer et impossible à remplacer sans perturber tout l’écosystème.

À l’inverse, une simple juxtaposition d’applications sans autorité commune produirait des identités concurrentes, des droits incohérents, des relations opaques et une traçabilité fragmentée.

Le Core doit donc gouverner ce qui permet aux systèmes de coopérer durablement sans absorber leurs données métier.

---

## Décision

1. GAMAD Core gouverne prioritairement les relations transversales nécessaires à la cohérence de l’écosystème.
2. Ces relations comprennent notamment :
   - identité et continuité ;
   - appartenance ;
   - responsabilité ;
   - autorité d’action ;
   - accès aux ressources ;
   - attribution de capacités ;
   - exposition et consommation de contrats ;
   - cycles de vie ;
   - audit transversal.
3. Chaque module reste System of Record pour ses propres données métier.
4. Le Core conserve les références, états et métadonnées strictement nécessaires à la gouvernance commune.
5. Une donnée métier ne peut être intégrée au Core uniquement parce qu’elle est utile à plusieurs modules ; sa responsabilité doit d’abord être analysée.
6. Une relation transversale ne doit pas être dupliquée librement dans chaque module lorsqu’elle relève de l’autorité du Core.
7. Le Core n’est pas une base de données universelle ni un bus de stockage général.

---

## Conséquences positives

- Frontières de responsabilité plus stables.
- Réduction du couplage entre modules.
- Possibilité de remplacer une implémentation métier sans changer l’identité des ressources.
- Autorisations et audit cohérents à travers l’écosystème.
- Core plus petit, plus durable et plus compréhensible.
- Intégration progressive de futurs métiers sans absorber leurs modèles internes.

---

## Contraintes

- Les modules doivent exposer des contrats explicites.
- Certaines vues nécessiteront une composition de données entre Core et systèmes métier.
- Les équipes doivent résister à la tentation de copier les données métier dans le Core pour simplifier une interface.
- Les mécanismes de cohérence éventuelle devront être documentés lorsque plusieurs systèmes participent à une opération.

---

## Options rejetées

### Base centrale universelle

Rejetée, car elle transforme le Core en monolithe métier et rend les modules dépendants d’un schéma global.

### Identités et permissions propres à chaque module

Rejetées, car elles créent des vérités concurrentes et empêchent une gouvernance transversale fiable.

### Synchronisation libre des données entre bases

Rejetée comme principe général, car elle crée des copies sans responsabilité claire et une dette de réconciliation.

---

## Test de conformité

Toute donnée candidate au Core doit répondre aux questions suivantes :

1. Est-elle nécessaire à une responsabilité transversale du Core ?
2. Le Core est-il réellement son System of Record ?
3. Peut-elle rester dans un module et être référencée par contrat ?
4. Son intégration protège-t-elle un invariant ou simplifie-t-elle seulement une interface ?
5. Quel serait l’impact si le module métier était remplacé ?

Si le Core n’est pas l’autorité légitime, la donnée doit rester dans le domaine métier responsable.

---

## Formule canonique

> Les modules possèdent les données métier. Le Core gouverne les relations qui leur permettent de coopérer durablement.
