- ajouter un nouveau type de produit dans le registre ne doit pas rendre `CAP-CORE-011` capable de le créer sans code et test ;
- ajouter une nouvelle action ne doit pas créer une permission ;
- ajouter une nouvelle finalité ne doit pas autoriser l’usage d’une source ;
- ajouter un nouvel état ne doit pas rendre les transitions métier possibles.

---

## 13. Projections locales et disponibilité

Les capacités ne doivent pas interroger le magasin du vocabulaire à chaque opération critique.

Architecture recommandée :

```text
registre canonique
→ version active immuable
→ projection vérifiée
→ consommateur déclaré compatible
→ usage local exact
```

Chaque consommateur peut utiliser :

- une projection JSON immuable ;
- des constantes PHP générées ;
- un enum OpenAPI généré ;
- une contrainte SQL vérifiée ;
- un cache local avec empreinte.

Règles :

- la projection porte la référence et la version du vocabulaire ;
- l’empreinte est vérifiée ;
- une projection ne remplace pas la conformité ;
- une projection périmée est détectée ;
- le dernier snapshot actif vérifié peut rester utilisable en lecture si le registre central est indisponible ;
- aucune nouvelle version ne peut être activée lorsque les projections obligatoires ne sont pas produites ;
- aucune nouvelle écriture utilisant un terme inconnu n’est acceptée.

Pour les décisions de sécurité :

- exactitude obligatoire ;
- pas de fuzzy matching ;
- pas d’alias humain ;
- pas de traduction ;
- pas de fallback vers `INDETERMINE` pour créer une permission.

---

## 14. Bootstrap initial

Créer :

```text
core/registre-vocabulaire/resources/bootstrap-vocabulaire-v1.json
```

Le bootstrap doit être généré après audit du dépôt fusionné.

Premiers ensembles candidats à confirmer :

### 14.1 Identités

- types : `personne`, `organisation`, `produit`, `realm`, `agent`, `service`, `INDETERMINE` ;
- assurance : `A0`, `A1`, `A2`, `A3` ;
- canaux d’inscription ;
- relations produit ;
- relations organisation ;
- classifications ;
- états d’identité ;
- finalités d’identité.

### 14.2 Produits

- types de produits ;
- états ;
- environnements ;
- relations fédérées réellement contractuelles.

### 14.3 Sources

- types de sources ;
- états ;
- niveaux de vérification ;
- résultats ;
- relations de lignée ;
- finalités actives.

### 14.4 Politiques

- effets ;
- états de version ;
- actions canoniques actives.

### 14.5 Contrats

- types de contrats ;
- états ;
- rôles ;
- opérations ;
- résultats de compatibilité ;
- erreurs canoniques ;
- types d’obligation.

### 14.6 Exploitation

- catégories et types d’événements réellement partagés ;
- résultats de readiness si exposés dans des contrats ;
- statuts de continuité réellement échangés.

Exigences :

- aucun terme inventé ;
- aucun terme purement documentaire sans consommateur ;
- références stables ;
- définitions explicites ;
- propriétaire et source ;
- usages réels ;
- empreinte globale ;
- bootstrap idempotent ;
- transaction unique ;
- rollback complet ;
- rapport des valeurs non migrées.

---

## 15. Politique d’administration

Créer :

```text
POL-VOCABULAIRE-V1
```

Actions minimales :

- `vocabulaire.lire` ;
- `vocabulaire.inscrire` ;
- `vocabulaire.version.creer` ;
- `vocabulaire.terme.ajouter` ;
- `vocabulaire.terme.modifier` ;
- `vocabulaire.alias.ajouter` ;
- `vocabulaire.mapping.ajouter` ;
- `vocabulaire.usage.declarer` ;
- `vocabulaire.version.soumettre` ;
- `vocabulaire.version.analyser` ;
- `vocabulaire.version.activer` ;
- `vocabulaire.version.deprecier` ;
- `vocabulaire.version.retirer` ;
- `vocabulaire.projection.generer` ;
- `vocabulaire.conformite.enregistrer`.

Bornes :

- lecture des versions actives : session autorisée ;
- brouillons : propriétaire et autorité ;
- activation : autorité ;
- retrait : autorité ;
- un produit ne modifie pas un vocabulaire qu’il ne possède pas ;
- un consommateur ne se déclare pas conforme sans preuve ;
- une version de rupture ne s’active pas sans plan ;
- décision absente : refus ;
- refus par défaut.

---

## 16. Commandes métier

### 16.1 `inscrireVocabulaire`

Entrées :

- référence ;
- namespace ;
- nom ;
- domaine ;
- portée ;
- propriétaire ;
- source ;
- description ;
- acteur ;
- politique ;
- preuve ;
- `correlation_id`.

Règles :

- référence unique ;
- namespace unique ;
- propriétaire connu ;
- source active ;
- aucune version active automatique ;
- audit obligatoire.

### 16.2 `creerVersion`

Règles :

- vocabulaire existant ;
- version unique ;
- état `BROUILLON` ;
- aucune activation automatique ;
- audit obligatoire.

### 16.3 `ajouterTerme`

Règles :

- version brouillon ;
- référence stable ;
- code unique ;
- définition obligatoire ;
- type sémantique valide ;
- aucune réutilisation d’un code retiré avec un autre sens ;
- audit obligatoire.

### 16.4 `ajouterLibelle`

Règles :

- locale valide ;
- libellé non vide ;
- un principal par locale ;
- aucun effet sur le code ;
- audit obligatoire.

### 16.5 `ajouterAlias`

Règles :

- alias explicite ;
- absence d’ambiguïté ;
- type d’alias ;
- période ;
- source ;
- aucun usage de sécurité automatique ;
- audit obligatoire.

### 16.6 `declarerRelation`

Règles :

- deux termes existants ;
- type connu ;
- absence de cycle hiérarchique ;
- équivalence explicite ;
- preuve obligatoire ;
- audit obligatoire.

### 16.7 `declarerMappingExterne`

Règles :

- système connu ;
- code externe explicite ;
- sens ;
- statut ;
- aucune équivalence inférée ;
- perte d’information visible ;
- audit obligatoire.

### 16.8 `declarerUsage`

Règles :

- terme existant ;
- consommateur connu ;
- contrat ou capacité connu ;
- type d’usage ;
- période ;
- aucune portée universelle implicite ;
- audit obligatoire.

### 16.9 `soumettreVersion`

Règles :

- au moins un terme ;
- définitions présentes ;
- codes uniques ;
- libellé principal minimal en français pour les interfaces actuelles ;
- usages déclarés pour les termes de sécurité ou contrats ;
- aucune ambiguïté d’alias ;
- aucune relation cyclique ;
- empreinte calculée ;
- passage à `EN_VALIDATION` ;
- version ensuite immuable.

### 16.10 `analyserCompatibilite`

Détecter :

- ajout de terme ;
- retrait de terme ;
- changement de code ;
- changement de définition ;
- changement de type sémantique ;
- changement de relation ;
- changement de mapping ;
- changement de portée ;
- changement de finalité ;
- consommateurs impactés ;
- contrats impactés ;
- politiques impactées ;
- contraintes SQL ou OpenAPI impactées.

### 16.11 `activerVersion`

Règles :

- version en validation ;
- analyse disponible ;
- projections obligatoires générées ;
- conformités obligatoires vertes ;
- plan de migration pour rupture ;
- source active ;
- activation atomique ;
- ancienne version remplacée ou dépréciée selon plan ;
- audit obligatoire.

### 16.12 `deprecierTerme`

Règles :

- date de fin ;
- remplaçant explicite lorsqu’il existe ;
- usages et consommateurs identifiés ;
- aucun nouvel usage ;
- historique conservé ;
- audit obligatoire.

### 16.13 `retirerTerme`

Règles :

- aucun contrat actif dépendant ;
- aucune politique active dépendante ;
- aucun consommateur obligatoire non migré ;
- référence non réutilisable ;
- aucune suppression physique ;
- audit obligatoire.

### 16.14 `enregistrerConformite`

Règles :

- version exacte ;
- consommateur exact ;
- commit ou artefact identifié ;
- résultat conservé ;
- expiration possible ;
- aucune auto-certification silencieuse.

---

## 17. Analyse de compatibilité

### 17.1 Généralement compatible

- ajout d’un libellé dans une nouvelle locale ;
- correction orthographique d’un libellé sans changer le code ni la définition ;
- ajout d’un terme non obligatoire sans consommateur existant ;
- ajout d’un alias non ambigu ;
- ajout d’une relation informative non utilisée en décision.

### 17.2 Adaptation requise

- ajout d’un terme dans un enum fermé consommé par du code ;
- ajout d’une finalité ;
- ajout d’une action ;
- ajout d’un type de produit ou de source ;
- modification d’une traduction utilisée dans une interface réglementée ;
- mapping externe approximatif ;
- dépréciation d’un terme encore utilisé.

### 17.3 Rupture

- changement de code canonique ;
- changement de sens d’un code existant ;
- suppression d’un terme actif ;
- réutilisation d’un code avec un nouveau sens ;
- changement d’un état vers une sémantique différente ;
- suppression d’une action de contrat ;
- fusion de deux termes non équivalents ;
- transformation d’un mapping approximatif en exact sans preuve ;
- retrait d’un terme utilisé par un contrat actif.

Une rupture exige :

- consommateurs impactés ;
- contrats impactés ;
- plan de migration ;
- période de coexistence ;
- alias ou mapping temporaire ;
- tests ;
- retour arrière ;
- date limite.

---
