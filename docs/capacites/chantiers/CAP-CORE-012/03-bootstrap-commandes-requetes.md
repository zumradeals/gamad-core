# CAP-CORE-012 — BOOTSTRAP, COMMANDES ET REQUÊTES

Cette partie complète les fichiers `01-fiche-de-codage.md` et `02-modele-de-donnees.md`.

---

## 26. Audit initial obligatoire

Avant toute écriture de code, produire un inventaire réel :

- identités de type `realm` présentes dans l’index ou le registre persistant ;
- références `IDN-RLM-*` déjà attribuées ;
- organisations qui semblent porter une responsabilité territoriale ou institutionnelle ;
- produits dont le nom ou la configuration évoque un pays, une région ou un environnement ;
- contrats ayant déjà une portée territoriale ou institutionnelle implicite ;
- politiques mentionnant un produit, une organisation ou un pays ;
- champs libres comme `realm`, `tenant`, `country`, `territory`, `zone`, `market`, `jurisdiction` ;
- tests qui supposent une portée globale ;
- routes et contrôleurs susceptibles d’accepter un futur `realm_reference`.

Commandes indicatives :

```bash
rg -n --hidden \
  --glob '!.git/**' \
  --glob '!vendor/**' \
  --glob '!node_modules/**' \
  "realm|tenant|territoire|pays|country|region|zone|market|juridiction|scope"
```

L’audit doit distinguer :

- une véritable donnée de realm ;
- un simple libellé ;
- un environnement de produit ;
- une organisation ;
- une portée d’autorisation ;
- une donnée métier de satellite.

Ne pas migrer automatiquement une chaîne contenant un nom de pays.

---

## 27. Bootstrap des realms existants

Créer une commande explicite :

```text
php artisan core:realms:bootstrap
```

Créer, lorsque nécessaire :

```text
core/registre-realms/resources/bootstrap-realms-v1.json
```

Le bootstrap doit :

- inventorier les identités de type `realm` existantes ;
- conserver leurs références d’identité ;
- créer une référence de registre stable selon la convention de `CAP-CORE-010` ;
- conserver le libellé historique comme nom d’affichage ;
- conserver la source historique lorsqu’elle est résoluble ;
- ne créer aucun rattachement d’organisation ou de produit par déduction de nom ;
- ne créer aucune hiérarchie par ressemblance lexicale ;
- ne créer aucun code pays sans preuve explicite ;
- fonctionner avec zéro realm historique ;
- être idempotent ;
- vérifier une empreinte de la ressource de bootstrap ;
- écrire dans une transaction ;
- produire un rapport d’import.

État initial recommandé pour un realm historique :

- `PREPARATION` lorsque son usage opérationnel n’est pas prouvé ;
- `ACTIF` uniquement lorsqu’un usage réel, une source et une preuve existent déjà.

Aucune activation ne doit être inventée pour préserver une simple lecture historique.

---

## 28. Politique d’administration

Créer dans `CAP-CORE-007` :

```text
POL-REALMS-V1
```

Actions minimales :

- `realm.lire` ;
- `realm.inscrire` ;
- `realm.modifier` ;
- `realm.activer` ;
- `realm.suspendre` ;
- `realm.fermer` ;
- `realm.retirer` ;
- `realm.relation.declarer` ;
- `realm.relation.fermer` ;
- `realm.perimetre.declarer` ;
- `realm.perimetre.fermer` ;
- `realm.organisation.rattacher` ;
- `realm.organisation.detacher` ;
- `realm.produit.rattacher` ;
- `realm.produit.detacher` ;
- `realm.contrat.rattacher` ;
- `realm.contrat.detacher` ;
- `realm.franchissement.declarer` ;
- `realm.franchissement.fermer` ;
- `realm.verification.enregistrer` ;
- `realm.portee.verifier`.

Bornes minimales :

- inscription réservée à l’autorité ou à une organisation explicitement mandatée ;
- activation réservée à l’autorité ;
- suspension, fermeture et retrait réservés à l’autorité ;
- une organisation ne peut pas s’auto-déclarer responsable sans mandat ;
- un produit ne peut pas s’auto-rattacher ;
- un realm ne peut pas s’auto-vérifier ;
- aucune décision absente ne vaut permission ;
- refus par défaut ;
- code conserve ses bornes critiques même si une politique est mal écrite.

---

## 29. Commande `inscrireRealm`

Entrées minimales :

- référence de registre ;
- identité de realm ;
- code canonique ;
- type ;
- nom d’affichage ;
- description éventuelle ;
- organisation responsable éventuelle ;
- classification ;
- source ;
- politique ;
- producteur ;
- preuve ;
- acteur ;
- `correlation_id`.

Règles :

- identité existante de type `realm` ;
- identité non déjà utilisée ;
- code unique ;
- type canonique actif ;
- source active ;
- organisation responsable active lorsqu’elle est fournie ;
- création en `PREPARATION` ;
- première révision créée dans la même transaction ;
- aucun rattachement automatique ;
- aucune activation automatique ;
- audit obligatoire.

---

## 30. Commande `creerRealmAvecIdentite`

Cette commande applicative est facultative mais recommandée pour la console.

Elle orchestre :

1. inscription de l’identité de type `realm` dans `CAP-CORE-001` ;
2. inscription du realm dans `CAP-CORE-012` ;
3. preuve et corrélation communes ;
4. rollback compensatoire ou transaction coordonnée selon l’architecture réelle.

Règles :

- aucune identité orpheline si l’inscription du realm échoue ;
- aucune fiche realm sans identité ;
- utiliser le canal autorisé par `CAP-CORE-001` ;
- ne pas contourner la politique d’identité ;
- ne pas ouvrir de transaction distribuée fragile sans stratégie documentée.

Lorsque les magasins sont distincts, préférer une saga locale explicite :

- identité inscrite ;
- realm inscrit ;
- en cas d’échec, identité marquée ou clôturée selon une commande gouvernée ;
- événement d’échec conservé.

Ne jamais supprimer directement l’identité.

---

## 31. Commande `modifierRealm`

Règles :

- référence et identité immuables ;
- code canonique immuable sauf procédure de remplacement explicite ;
- nouvelle révision ;
- dates cohérentes ;
- organisation responsable vérifiée ;
- aucune réécriture de la révision précédente ;
- audit obligatoire.

---

## 32. Commandes de cycle

Implémenter :

- `activerRealm()` ;
- `suspendreRealm()` ;
- `fermerRealm()` ;
- `retirerRealm()`.

### Activation

Exige :

- realm en `PREPARATION` ou `SUSPENDU` selon transition autorisée ;
- révision valide ;
- source active ;
- organisation responsable active lorsqu’elle est requise ;
- au moins un périmètre explicite pour les types qui l’exigent ;
- vérification non expirée lorsque la politique l’exige ;
- autorisation ;
- preuve ;
- audit.

### Suspension

Effets :

- nouveaux rattachements refusés ;
- nouveaux franchissements refusés ;
- contrôles de portée retournent un refus explicite ;
- historique toujours lisible ;
- aucun effacement.

### Fermeture

Effets :

- aucune nouvelle opération ;
- rattachements existants conservés pour l’histoire ;
- possibilité de définir un successeur ;
- aucune réactivation sans commande explicite autorisée si la politique le permet.

### Retrait

Effets :

- irréversible ;
- référence non réutilisable ;
- aucune nouvelle utilisation ;
- historique conservé ;
- identité canonique non supprimée ;
- audit obligatoire.

---

## 33. Commandes de relation entre realms

Implémenter :

- `declarerRelationRealm()` ;
- `fermerRelationRealm()`.

Règles :

- realms connus ;
- type canonique ;
- pas d’auto-relation ;
- pas de cycle hiérarchique ;
- dates cohérentes ;
- preuve obligatoire ;
- fermeture datée ;
- aucune suppression ;
- une relation `SUCCEDE_A` ne modifie pas automatiquement les cycles ;
- une relation `CHEVAUCHE` n’accorde aucun accès croisé.

---

## 34. Commandes de périmètre

Implémenter :

- `declarerPerimetre()` ;
- `fermerPerimetre()` ;
- `declarerIdentifiantExterne()` ;
- `fermerIdentifiantExterne()`.

Règles :

- dimension canonique ;
- valeur canonique ou système externe explicite ;
- aucun texte libre utilisé pour une décision ;
- pas de contradiction active non expliquée ;
- preuve obligatoire ;
- dates cohérentes ;
- audit obligatoire.

---

## 35. Commandes de rattachement d’organisation

Implémenter :

- `rattacherOrganisation()` ;
- `detacherOrganisation()`.

Règles :

- realm connu ;
- organisation active ;
- rôle canonique ;
- mandat vérifié pour l’acteur qui engage l’organisation ;
- aucun rôle implicite ;
- aucune représentation individuelle déduite ;
- période cohérente ;
- idempotence ;
- preuve ;
- audit.

Un rattachement `RESPONSABLE` signifie que l’organisation porte une responsabilité de realm.

Il ne signifie pas que toute personne liée à cette organisation peut agir sur le realm.

---

## 36. Commandes de rattachement de produit

Implémenter :

- `rattacherProduit()` ;
- `detacherProduit()`.

Règles :

- produit actif ;
- environnement existant lorsqu’il est fourni ;
- rôle canonique ;
- aucun auto-rattachement ;
- aucune copie des secrets ou URLs ;
- période cohérente ;
- preuve ;
- audit.

Le rattachement d’un produit à un realm ne rend pas automatiquement la fédération disponible.

`CAP-CORE-022`, `CAP-CORE-011` et `CAP-CORE-004` conservent leurs propres contrôles.

---

## 37. Commandes de contrat et franchissement

Implémenter :

- `rattacherContrat()` ;
- `detacherContrat()` ;
- `declarerFranchissement()` ;
- `fermerFranchissement()`.

Règles :

- contrat et version connus ;
- finalité canonique ;
- source et cible connues ;
- objet ou type d’objet explicite ;
- effet fermé `PERMET` ou `REFUSE` ;
- refus prioritaire ;
- période cohérente ;
- preuve ;
- audit.

Une règle de franchissement ne doit jamais contenir :

- expression PHP ;
- SQL ;
- regex libre exécutée ;
- script ;
- wildcard universel ;
- secret.

---

## 38. Commande `enregistrerVerification`

Règles :

- realm connu ;
- vérificateur connu ;
- type et résultat canoniques ;
- preuve obligatoire ;
- expiration cohérente ;
- pas d’auto-attestation forte ;
- ajout seul ;
- audit obligatoire.

---

## 39. Requêtes minimales

Implémenter au minimum :

- `resoudreRealm(reference, date?)` ;
- `resoudreRealmParIdentite(identite, date?)` ;
- `resoudreRealmParCode(code, date?)` ;
- `listerRealms(filtres)` ;
- `resoudreEtat(reference, date?)` ;
- `resoudreRevision(reference, date?)` ;
- `resoudreRelations(reference, date?)` ;
- `resoudreParents(reference, date?)` ;
- `resoudreEnfants(reference, date?)` ;
- `resoudreAscendance(reference, date?)` ;
- `resoudreDescendance(reference, date?)` ;
- `resoudrePerimetres(reference, date?)` ;
- `resoudreIdentifiantsExternes(reference, date?)` ;
- `resoudreOrganisations(reference, date?)` ;
- `resoudreProduits(reference, date?)` ;
- `resoudreContrats(reference, date?)` ;
- `resoudreFranchissements(reference, date?)` ;
- `resoudreVerificationCourante(reference, date?)` ;
- `verifierPortee(dossier)` ;
- `diagnostiquerRegistre()`.

Toutes les requêtes datées doivent reconstruire l’état applicable à la date demandée.

---

## 40. Évaluateur de portée

Créer un service déterministe :

```text
EvaluateurPortee
```

Entrée conceptuelle :

```json
{
  "realm": "RLM-CI-0001",
  "acteur": "IDN-PER-0001",
  "organisation": "ORG-GAMAD-0001",
  "produit": "PRD-GAMAD-0002",
  "contrat": "CTR-GAMAD-EXEMPLE",
  "operation": "operation.reference",
  "finalite": "FINALITE-EXEMPLE",
  "realm_source": "RLM-CI-0001",
  "realm_cible": "RLM-ML-0001",
  "date": "2026-08-02"
}
```

Sortie conceptuelle :

```json
{
  "dans_portee": false,
  "realm": "RLM-CI-0001",
  "motifs": [
    "PRODUIT_NON_RATTACHE",
    "FRANCHISSEMENT_NON_DECLARE"
  ],
  "faits": {
    "realm_actif": true,
    "organisation_rattachee": true,
    "produit_rattache": false,
    "contrat_rattache": false
  }
}
```

Règles d’évaluation :

1. realm connu ;
2. realm actif à la date ;
3. rattachements actifs ;
4. produit actif ;
5. organisation active ;
6. contrat actif ;
7. finalité connue ;
8. frontière directe ou franchissement explicite ;
9. refus applicable prioritaire ;
10. absence de fait nécessaire = refus explicable.

Cette réponse ne constitue pas une autorisation finale.

La couche applicative doit ensuite demander une décision à `CAP-CORE-004`.

---

## 41. Motifs canoniques de refus

Créer dans `CAP-CORE-010` des motifs stables, au minimum :

- `REALM_INCONNU` ;
- `REALM_EN_PREPARATION` ;
- `REALM_SUSPENDU` ;
- `REALM_FERME` ;
- `REALM_RETIRE` ;
- `ORGANISATION_NON_RATTACHEE` ;
- `ORGANISATION_INACTIVE` ;
- `MANDAT_INSUFFISANT` ;
- `PRODUIT_NON_RATTACHE` ;
- `PRODUIT_INACTIF` ;
- `CONTRAT_NON_RATTACHE` ;
- `CONTRAT_INACTIF` ;
- `FINALITE_INCONNUE` ;
- `PERIMETRE_NON_SATISFAIT` ;
- `FRANCHISSEMENT_NON_DECLARE` ;
- `FRANCHISSEMENT_REFUSE` ;
- `VERIFICATION_EXPIREE` ;
- `DEPENDANCE_INDISPONIBLE`.

Ne pas utiliser un texte libre comme seul motif machine.

---

## 42. Raccordement à CAP-CORE-001

`CAP-CORE-001` reste propriétaire de l’identité de type `realm`.

Ajouter un contrat ou service de lecture permettant à `CAP-CORE-012` de vérifier :

- existence ;
- type ;
- état ;
- assurance minimale lorsque nécessaire.

`CAP-CORE-012` ne doit pas écrire directement dans les tables d’identités.

La console peut offrir un cas d’usage orchestré, mais chaque capacité garde ses propres commandes.

---

## 43. Raccordement à CAP-CORE-002

`CAP-CORE-002` reste propriétaire des organisations.

`CAP-CORE-012` référence seulement :

- organisation ;
- rôle dans le realm ;
- période ;
- source ;
- preuve.

Ne pas recopier :

- structure ;
- unités ;
- membres ;
- fonctions ;
- identifiants organisationnels.

Prévoir une lecture inverse dans `CAP-CORE-002` ou une requête de `CAP-CORE-012` :

```text
listerRealmsOrganisation(organisation, date?)
```

---

## 44. Raccordement à CAP-CORE-003

Toute commande exercée au nom d’une organisation doit vérifier le mandat à la date de l’acte.

Ne jamais déduire un mandat à partir :

- d’un rôle `RESPONSABLE` ;
- d’une affiliation `DIRIGEANT` ;
- d’un titre affiché ;
- d’un nom approchant.

`CAP-CORE-003` reste la seule source des mandats opposables.

---

## 45. Raccordement à CAP-CORE-011 et CAP-CORE-022

`CAP-CORE-011` reste propriétaire des produits et environnements.

`CAP-CORE-012` répond uniquement :

```text
Ce produit est-il rattaché à ce realm, dans ce rôle et à cette date ?
```

Prévoir une intégration future avec `CAP-CORE-022` :

- la fédération peut demander un `realm_reference` ;
- l’ouverture doit vérifier le produit et le realm ;
- l’audience reste possédée par `CAP-CORE-011` ;
- le jeton peut porter une référence de realm bornée ;
- aucune donnée de realm complète ne doit être placée dans le jeton ;
- aucune modification de `CAP-CORE-022` sans test de non-régression GamaDrive.

Le chantier peut livrer cette intégration uniquement si un consommateur réel ou une exigence actuelle la justifie.

Sinon, enregistrer le contrat préparatoire sans modifier le parcours fédéré existant.
