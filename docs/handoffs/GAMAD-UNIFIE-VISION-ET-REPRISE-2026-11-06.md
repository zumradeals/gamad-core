# GAMAD — Contrat de vision unifiée et passation à Claude / Work
Date : 2026-11-06
Statut : **décision explicite du propriétaire — référence pour reprise, non preuve de conformité du code**
Périmètre actuel : **GAMAD Core et fonctionnalités GAMAD Réseau uniquement**.

## 1. Décision souveraine du propriétaire (non négociable)

GAMAD est **une seule application unifiée**. Le **Core** est l'autorité unique pour **TOUS** les aspects des identités et des comptes, **y compris les données métier**. GAMAD Réseau est une partie fonctionnelle du même système, **pas une application autonome**, ni un domaine propriétaire d'un second compte.

Le Core gère intégralement :
- identité canonique, cycle de vie, création, modification, suspension, révocation et suppression selon les contrats ;
- compte utilisateur unique et toutes ses données, **y compris les données métier utilisées par Réseau** ;
- authentification, secrets, sessions, habilitations, autorisations et contrôle des accès ;
- intégrité, persistance et règles de gestion des comptes.

Réseau consomme les capacités et contrats du Core. Réseau ne crée, ne possède, ne synchronise, ne rapproche et ne relie **aucun compte parallèle** à une identité Core. Il ne possède **aucune autorité indépendante** sur les comptes ou les données métier des comptes.

**Interdits de conception :** « compte Réseau » distinct du compte Core ; association compte Réseau ↔ identité Core ; table de correspondance de comptes ; double authentification indépendante ; référentiel concurrent ; projection locale présentée comme source de vérité ; architecture à deux applications ; réinterprétation de cette décision sans accord explicite.

Une séparation physique de magasins techniques ne signifie **jamais** séparation d'autorité ou existence de comptes indépendants. Toute persistance métier des comptes doit rester gouvernée par les contrats et l'autorité du Core.

## 2. Erreur reconnue et correction exigée

Des travaux ont été réalisés sur une hypothèse **non autorisée** : un utilisateur local Laravel/Réseau associé à une identité canonique du Core. Cette hypothèse a entraîné des classes et une migration de liaison. La CI verte de ces travaux ne prouve pas leur conformité au produit souhaité.

Les éléments suivants doivent être **audités, puis retirés ou refondus sans conserver la séparation fautive** :
- `CompteReseauUnifie` ;
- `LierCompteIdentite` ;
- `ExecuterLiaisonReseau` ;
- `VerifierContexteLiaison` et `ResoudreCompteReseauCore` dans leur logique de liaison locale ;
- table/migration `reseau_comptes_identites` ;
- contrôleur et route `/reseau/mon-compte` dans leur forme actuelle ;
- usages de `users`, `Auth` Laravel et champs `password` comme possible autorité parallèle ;
- tests qui entérinent une identité Core liée à un compte Réseau distinct.

**Ne pas supprimer aveuglément** des éléments encore nécessaires à d'autres fonctions : établir d'abord la cartographie réelle, la migration de données et les contrats de remplacement. Aucun effacement de données en production.

## 3. Historique consolidé des travaux

Dépôt : `zumradeals/gamad-core`.

### Lots précédents (PR #89 à #101, signalés comme fusionnés dans la passation précédente)
- #89 diagnostic des stockages ;
- #90 collision de routes ;
- #91 normalisation ;
- #92 types d'identifiants UUID/BIGINT ;
- #93 rapprochement d'identités ;
- #94 vérification du registre Core ;
- #95 modèle `CompteReseauUnifie` ;
- #96 migration `reseau_comptes_identites` ;
- #97 `LierCompteIdentite` ;
- #98 qualification de liaison ;
- #99 vérification des contextes session/transaction ;
- #100 politique Core et possession du compte ;
- #101 exécution de la liaison.

**Important :** ce qui est déjà fusionné n'est pas automatiquement validé architecturalement. Auditer les effets et dépendances de #89–#101 sur la branche principale avant de retirer le mécanisme de liaison.

### PR #102 (ouverte, branche `feature/r02-2b-14-core-account-boundary`)
Travaux ajoutés :
- résolution du compte depuis la session Core CTR-16 ;
- route HTTP `/reseau/mon-compte` et contrôleur ;
- tests d'accès sans session Core et de non-équivalence d'une session Laravel ;
- prise en charge des dates civiles du journal CTR-01 ;
- lecture du registre souverain Core via un magasin distinct ;
- test d'intégration de session CTR-16, magasin d'identités distinct, révocation ;
- modification de la liaison pour consulter le registre Core sans copie locale ;
- qualification de transaction locale séparée du magasin Core ;
- tests de liaison sur registre Core distinct.

Commits repères :
- `53cf9da8ea45fd77e7527f306c98e495a77f0d48` : route et tests initiaux ;
- `e891f5fc15777c30bd71b0464329305c917d991c` : dates CTR-01 ;
- `aaef31f21eb21869eab3df12400787cccd488b2d` : test positif avec magasins distincts ;
- `43a548f4957d27988ed3366eb89dc5f71c93737f` : liaison contre registre Core ;
- `40d936fcf3e56893ebbc4faa306b03dfa34a358a` : contexte de stockage local ;
- `5f3fed3ea9ea8907b5b51d874ebf3eb4c4ca64f6` : tests de liaison ;
- `61a51ab0c01002dc5b91624b8d8dda52b942b5a2` : clarification des transactions.

Les deux workflows étaient verts au commit `aaef31f...`. **La qualification du dernier état de PR #102 et la conformité à la vision unifiée ne sont PAS établies.** Le propriétaire a explicitement rejeté le modèle de comptes liés : **PR #102 non fusionnable en l'état**.

## 4. Mission de reprise : résultat attendu, pas multiplication de micro-lots

1. **État des lieux réel** : comparer `main` et PR #102 ; inventorier les sources de vérité des comptes, identités, sessions, autorisations, données métier et routes ; identifier les dépendances historiques. Ne pas déduire la conception du nom des classes.
2. **Contrat d'architecture minimal et exécutable** : un seul compte canonique Core ; interfaces fonctionnelles Core pour toutes les opérations de compte, y compris les données métier Réseau ; aucun compte Réseau local autonome. Séparer vision, conception et implémentation.
3. **Correction du code** : remplacer les mécanismes de liaison par l'usage direct du compte Core. Retirer la table de correspondance et les chemins d'authentification concurrents **après** analyse de compatibilité et plan de migration réversible ; préserver les données existantes.
4. **Tests de preuve** : création/lecture/mise à jour du même compte par Core et Réseau, données métier gérées par Core, une seule autorité de session, révocation immédiate, absence de compte parallèle, refus d'accès non autorisé, migrations et non-régression. Les tests doivent porter sur le vrai parcours applicatif, pas uniquement sur des helpers.
5. **CI et livraison** : tests verts sur le head final, audit des migrations, diff cohérent, compte rendu des risques résiduels. Ne pas confondre CI verte et conformité architecturale.
6. **PR** : ne pas fusionner ou déployer sans autorisation explicite du propriétaire. Si #102 est impropre, proposer son abandon et un remplacement propre ; ne pas empiler de correctifs sur une prémisse rejetée.

## 5. Conditions d'acceptation

- Il n'existe **qu'un seul compte GAMAD Core** par utilisateur selon les règles du Core.
- Aucune fonctionnalité Réseau ne crée ni n'administre un compte indépendant.
- Les données métier du compte utilisées par Réseau sont **gérées par le Core**, sans propriété métier concurrente.
- Authentification, session et autorisation sont exclusivement Core.
- Aucune table d'association de « compte Réseau » à « identité Core » n'est requise.
- Les parcours fonctionnels et tests de non-régression le démontrent.
- Aucun changement de technologie/stack ou élargissement du périmètre sans autorisation.
- Livrable reconstructible depuis Git, avec contrats explicites et CI qualifiée.

## 6. Consignes de conduite pour Claude / Work

Agir comme architecte cognitif et gardien de cohérence, puis exécuter le code. Ne pas demander « continuer ? » après chaque micro-commit : traiter le chantier en lots cohérents jusqu'à une PR prête à être qualifiée. Éviter les rapports documentaires à la place du code. Signaler immédiatement toute contradiction réelle entre les contrats existants et la vision du propriétaire ; ne jamais inventer une autonomie Réseau. Ne pas mentionner ni inclure d'autres modules hors périmètre.

**Priorité absolue : corriger le modèle, pas faire passer les tests d'un modèle erroné.**
