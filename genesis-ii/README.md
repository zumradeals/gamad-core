# GAMAD Core — Genesis II

## Statut du chantier

Genesis II est la refondation générationnelle, constitutionnelle, documentaire et architecturale de GAMAD Core.

La branche canonique de suivi est :

```text
main
```

Les textes adoptés sont publiés directement dans cette branche après leur adoption.

## Textes adoptés

### 1. ACTE-0001

**Titre :** Acte de refondation générationnelle de GAMAD Core  
**Statut :** ADOPTÉ — EN VIGUEUR  
**Date d’adoption :** 24 juillet 2026  
**Chemin :** `genesis-ii/actes/ACTE-0001-refondation-generationnelle.md`  
**Registre :** `genesis-ii/registre/ADOPTION-0001-ACTE-0001.md`

### 2. SOURCES-0001

**Titre :** Hiérarchie, authenticité et autorité des sources GAMAD  
**Version adoptée :** 0.1  
**Statut :** ADOPTÉ — EN VIGUEUR  
**Date d’adoption :** 24 juillet 2026  
**Chemin :** `genesis-ii/sources/SOURCES-0001-hierarchie-authenticite-autorite-sources-gamad.md`  
**Statut canonique :** `genesis-ii/sources/SOURCES-0001-STATUT.md`  
**Registre :** `genesis-ii/registre/ADOPTION-0002-SOURCES-0001.md`

### 3. GOVERNANCE-0001

**Titre :** Constitution de gouvernance de GAMAD Core  
**Version adoptée :** 0.1  
**Statut :** ADOPTÉ — EN VIGUEUR  
**Date d’adoption :** 24 juillet 2026  
**Chemin :** `genesis-ii/gouvernance/GOVERNANCE-0001-constitution-gouvernance-gamad-core.md`  
**Statut canonique :** `genesis-ii/gouvernance/GOVERNANCE-0001-STATUT.md`  
**Registre :** `genesis-ii/registre/ADOPTION-0003-GOVERNANCE-0001.md`

### 4. GOVERNANCE-0002

**Titre :** Autorités, rôles, nominations et délégations  
**Version adoptée :** 0.1  
**Statut :** LU ET ADOPTÉ — EN VIGUEUR  
**Date d’adoption :** 24 juillet 2026  
**Chemin :** `genesis-ii/gouvernance/GOVERNANCE-0002-autorites-roles-nominations-delegations.md`  
**Statut canonique :** `genesis-ii/gouvernance/GOVERNANCE-0002-STATUT.md`  
**Registre :** `genesis-ii/registre/ADOPTION-0004-GOVERNANCE-0002.md`

### 5. GOVERNANCE-0003

**Titre :** Cycle des décisions, délibérations, validations et registres  
**Version adoptée :** 0.1  
**Statut :** LU ET ADOPTÉ — EN VIGUEUR  
**Date d’adoption :** 24 juillet 2026  
**Chemin :** `genesis-ii/gouvernance/GOVERNANCE-0003-cycle-decisions-deliberations-validations-registres.md`  
**Statut canonique :** `genesis-ii/gouvernance/GOVERNANCE-0003-STATUT.md`  
**Registre :** `genesis-ii/registre/ADOPTION-0005-GOVERNANCE-0003.md`

### 6. ENGINEERING-GOVERNANCE-0001

**Titre :** Gouvernance du dépôt, des versions et des mises en production  
**Version adoptée :** 0.1  
**Statut :** LU ET ADOPTÉ — EN VIGUEUR  
**Date d’adoption :** 24 juillet 2026  
**Chemin :** `genesis-ii/gouvernance/ENGINEERING-GOVERNANCE-0001-gouvernance-depot-versions-mises-production.md`  
**Statut canonique :** `genesis-ii/gouvernance/ENGINEERING-GOVERNANCE-0001-STATUT.md`  
**Registre :** `genesis-ii/registre/ADOPTION-0006-ENGINEERING-GOVERNANCE-0001.md`

### 7. SECURITY-GOVERNANCE-0001

**Titre :** Gouvernance des accès, secrets, incidents et de la continuité  
**Version adoptée :** 0.1  
**Statut :** LU ET ADOPTÉ — EN VIGUEUR  
**Date d’adoption :** 24 juillet 2026  
**Chemin :** `genesis-ii/gouvernance/SECURITY-GOVERNANCE-0001-gouvernance-acces-secrets-incidents-continuite.md`  
**Statut canonique :** `genesis-ii/gouvernance/SECURITY-GOVERNANCE-0001-STATUT.md`  
**Registre :** `genesis-ii/registre/ADOPTION-0007-SECURITY-GOVERNANCE-0001.md`

## Porte constitutionnelle G0

`GOVERNANCE-0001` institue la Porte constitutionnelle `G0`. Le codage canonique de GAMAD Core — Genesis II ne pourra commencer qu’après satisfaction et constat formel des conditions prévues par cette Constitution.

Conformément à `GOVERNANCE-0002`, un Registre initial des autorités et mandats devra être créé avant le passage de `G0`. L’adoption de `GOVERNANCE-0002` ne nomme automatiquement aucune personne aux fonctions permanentes du Core.

Conformément à `GOVERNANCE-0003`, un Registre initial des décisions et les premiers modèles canoniques de proposition, revue, délibération, validation, adoption, publication, acceptation de risque, contestation, urgence et clôture devront être créés avant le passage de `G0`.

Conformément à `ENGINEERING-GOVERNANCE-0001`, les registres initiaux des dépôts, contributions, versions, déploiements, migrations, dépendances et exceptions d’ingénierie, ainsi que les modèles de contribution, intégration, release, déploiement, rollback, migration, hotfix et revue post-incident, devront être créés avant le passage de `G0`.

Conformément à `SECURITY-GOVERNANCE-0001`, les registres initiaux des actifs critiques, risques et contrôles, accès privilégiés, secrets et clés, vulnérabilités, incidents, sauvegardes et restaurations, continuité, tiers critiques, exceptions de sécurité et agents sensibles, ainsi que les modèles d’accès, risque, cérémonie de clé, incident, restauration et continuité, devront être créés avant le passage de `G0`.

L’adoption de `SECURITY-GOVERNANCE-0001` établit la doctrine minimale de sécurité requise, sous réserve de ces réalisations et du constat formel de `G0`. Elle n’ouvre pas à elle seule le codage canonique de Genesis II.

## Archives

Genesis I est préservé par :

- la branche `archive/genesis-i-2026-07-24` ;
- le commit source `45144dfc12edf885a77e833fd1f6443b7116b967` ;
- le manifeste `archives/GAMAD-CORE-GENESIS-I-2026-07-24/README.md`.

## Règle de publication

1. Les projets peuvent être préparés sur une branche de chantier.
2. L’adoption est enregistrée par un acte ou un registre distinct.
3. Dès adoption, l’état adopté est publié ou avancé sur `main`.
4. Une modification substantielle ultérieure exige un amendement, une révision ou un texte de remplacement.
5. La branche `cursor` demeure une branche historique de Genesis I tant qu’elle n’est pas explicitement retirée ou archivée.

## Lecture recommandée

1. `ACTE-0001`
2. `SOURCES-0001`
3. `GOVERNANCE-0001`
4. `GOVERNANCE-0002`
5. `GOVERNANCE-0003`
6. `ENGINEERING-GOVERNANCE-0001`
7. `SECURITY-GOVERNANCE-0001`
8. les statuts canoniques et registres d’adoption correspondants
9. les futurs textes organiques et techniques dans l’ordre prévu par la Constitution de gouvernance
