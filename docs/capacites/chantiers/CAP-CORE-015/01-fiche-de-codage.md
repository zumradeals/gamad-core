# GAMAD CORE — FICHE DE CODAGE CAP-CORE-015
# INTEGRITY PROOFS — PARTIE 1/5

**Référence :** `CAP-CORE-015`  
**Nom :** Integrity Proofs / Preuves d’intégrité  
**Statut initial :** `NO GO`  
**Statut cible :** `GO`  
**Dépôt :** `zumradeals/gamad-core`  
**Branche cible de codage :** `main`  
**Branche de chantier recommandée :** `claude/cap-core-015-integrity-proofs-go`

---

## 1. Mission

Construire la capacité commune de création, conservation, vérification et révocation des preuves techniques de GAMAD Core.

À la fin du chantier, le Core doit pouvoir répondre de manière persistante et vérifiable à ces questions :

```text
Quel artefact a été protégé ?
Quelle représentation exacte a été empreintée ?
Quel algorithme a été utilisé ?
Quelle empreinte a été obtenue ?
La preuve est-elle seulement une empreinte ou porte-t-elle une signature ?
Quelle identité, capacité, organisation ou produit a demandé la preuve ?
Quelle clé ou version de clé a signé ?
La clé était-elle active et autorisée à cet instant ?
La preuve est-elle encore valide, expirée, révoquée ou compromise ?
Le contenu présenté aujourd’hui correspond-il au contenu protégé ?
La signature est-elle cryptographiquement valide ?
Le manifeste complet correspond-il à tous ses artefacts ?
Quelle politique, quel contrat, quelle source et quel realm bornent la preuve ?
Quelle trace d’audit démontre son émission et sa vérification ?
```

La capacité doit fournir un socle commun pour :

- les fichiers techniques ;
- les payloads JSON canoniques ;
- les versions de registres ;
- les manifestes de sauvegarde ;
- les rapports de restauration ;
- les projections de contrats ;
- les checkpoints des journaux ;
- les attestations produites par le Core ;
- les futurs événements signés ;
- les paquets d’export ou d’échange entre produits ;
- les preuves de conformité technique ;
- les résultats de vérification historisés.

Le résultat attendu n’est pas un simple utilitaire `hash()`.

Le résultat attendu est une capacité réellement codée et raccordée à :

- PostgreSQL ;
- Laravel ;
- `CAP-CORE-004` pour les autorisations ;
- `CAP-CORE-006` pour les sources ;
- `CAP-CORE-007` pour les politiques ;
- `CAP-CORE-009` pour les contrats ;
- `CAP-CORE-010` pour les codes canoniques ;
- `CAP-CORE-012` pour les realms ;
- `CAP-CORE-013` pour l’audit ;
- `CAP-CORE-014` pour les événements minimaux ;
- `CAP-CORE-016` pour les références et opérations de clés ;
- `CAP-CORE-019` pour les sauvegardes et restaurations ;
- la console ;
- l’API de vérification ;
- la readiness ;
- la sauvegarde ;
- la restauration ;
- la CI.

`CAP-CORE-015` doit devenir `GO` avant :

```text
CAP-CORE-008 — Decisions Registry
```

afin que les décisions puissent référencer des preuves techniques stables lorsqu’elles en ont besoin.

---

## 2. Prérequis obligatoires

Le codage ne doit commencer qu’après que les capacités suivantes soient `GO` et fusionnées dans `main` :

```text
CAP-CORE-010 — Canonical Vocabulary
CAP-CORE-002 — Organizations Registry
CAP-CORE-012 — Realms Registry
CAP-CORE-014 — Event Journal
CAP-CORE-016 — Secrets & Keys
```

Les capacités suivantes doivent déjà rester vertes :

```text
CAP-CORE-004 — Authorization
CAP-CORE-006 — Sources Registry
CAP-CORE-007 — Rules / Policies Registry
CAP-CORE-009 — Contracts Registry
CAP-CORE-013 — Common Audit
CAP-CORE-019 — Backup & Restore
CAP-CORE-022 — Satellite Federation
```

Raisons :

- `CAP-CORE-010` fournit les codes de types de preuves, d’algorithmes et d’états ;
- `CAP-CORE-012` borne les preuves par realm ;
- `CAP-CORE-014` transporte les événements minimaux d’émission, de révocation et d’échec ;
- `CAP-CORE-016` possède les références de clés et exécute les signatures sans exposer les clés privées ;
- `CAP-CORE-009` décrit les contrats des attestations, manifestes et vérifications ;
- `CAP-CORE-013` conserve les traces d’exploitation ;
- `CAP-CORE-019` fournit le premier cas d’usage critique de manifestes vérifiables.

Avant de coder :

1. récupérer le dernier `origin/main` ;
2. vérifier les statuts `GO` dans le catalogue ;
3. inspecter le code réellement livré de `CAP-CORE-016` ;
4. identifier l’interface exacte permettant de demander une signature sans obtenir la clé privée ;
5. inventorier tous les calculs d’empreinte existants ;
6. inventorier tous les manifestes et sommes de contrôle existants ;
7. inventorier les champs `preuve_reference` déjà utilisés ;
8. inventorier les contrats de type `ATTESTATION` et `EVENEMENT` ;
9. ne rien migrer avant d’avoir distingué empreinte, signature et preuve métier ;
10. arrêter après l’audit si `CAP-CORE-016` n’est pas fusionnée.

---

## 3. Règle de statut

Le dépôt utilise uniquement :

- `GO` ;
- `NO GO`.

`CAP-CORE-015` reste `NO GO` pendant tout le chantier.

Elle ne passe à `GO` qu’après :

- création du registre persistant ;
- émission d’empreintes canoniques ;
- émission de signatures via `CAP-CORE-016` ;
- vérification historique liée à une version de clé exacte ;
- gestion des manifestes ;
- gestion de la révocation et de la compromission ;
- raccordement d’au moins deux cas réels ;
- exercice PostgreSQL ;
- sauvegarde et restauration ;
- CI complète verte.

Une preuve individuelle possède ses propres états. Ceux-ci ne changent jamais le statut de la capacité.

---

## 4. Définitions humaines

### 4.1 Empreinte

Une empreinte est le résultat d’un algorithme de hachage appliqué à une représentation exacte.

Elle permet de répondre :

```text
Le contenu présenté est-il identique au contenu initialement empreinté ?
```

Elle ne permet pas, à elle seule, de répondre :

```text
Qui a créé ce contenu ?
Qui a calculé cette empreinte ?
Le contenu est-il vrai ?
```

### 4.2 Signature

Une signature cryptographique lie une empreinte et un contexte à une version de clé.

Elle permet de vérifier que l’opération de signature a été exécutée avec la clé privée correspondant à une clé publique donnée.

Elle ne prouve pas automatiquement :

- que le signataire humain a personnellement lu le contenu ;
- que le contenu est exact ;
- que l’acte est juridiquement valable ;
- que la clé n’était pas compromise avant que la compromission soit connue.

### 4.3 Attestation

Une attestation est une déclaration technique structurée, signée ou non, portant sur un fait limité.

Exemple :

```json
{
  "type": "SAUVEGARDE_VERIFIEE",
  "lot_reference": "BKP-20260802T010000Z",
  "resultat": "CONFORME",
  "verifie_le": "2026-08-02T01:30:00Z"
}
```

L’attestation doit toujours préciser son producteur, sa finalité et sa portée.

### 4.4 Manifeste

Un manifeste décrit un ensemble déterminé d’artefacts et leurs empreintes.

Exemple :

```text
lot de sauvegarde
→ index.dump
→ identites.dump
→ acces.dump
→ produits.dump
→ journal.dump
```

Le manifeste permet de vérifier l’ensemble sans confondre les fichiers ni leur ordre.

### 4.5 Checkpoint

Un checkpoint est une preuve portant sur l’état d’une structure à un instant donné :

- tête d’une chaîne d’audit ;
- dernière séquence d’événement ;
- version active d’un registre ;
- ensemble de manifestes.

Le checkpoint ne remplace pas les données sous-jacentes.

### 4.6 Résultat de vérification

Un résultat de vérification est un constat historisé :

```text
VALIDE
INVALIDE
INDETERMINE
EXPIRÉE
RÉVOQUÉE
CLÉ_COMPROMISE
ARTEFACT_ABSENT
ALGORITHME_NON_SUPPORTÉ
```

Il doit être lié à :

- la preuve vérifiée ;
- l’artefact réellement présenté ;
- l’instant de vérification ;
- le logiciel ou moteur de vérification ;
- les divergences observées.

---

## 5. Ce que CAP-CORE-015 possède

`CAP-CORE-015` possède :

- les références de preuves ;
- les types de preuves ;
- les sujets protégés ;
- les représentations canoniques déclarées ;
- les algorithmes d’empreinte ;
- les empreintes ;
- les contextes de signature ;
- les signatures et certificats publics lorsqu’ils sont nécessaires à la vérification ;
- les références exactes de versions de clés ;
- les manifestes ;
- les membres des manifestes ;
- les checkpoints ;
- les attestations structurées ;
- les cycles d’état ;
- les expirations ;
- les révocations de preuves ;
- les résultats de vérification ;
- les divergences ;
- les liens entre preuve source et preuve dérivée ;
- les paquets de preuve exportables ne contenant aucun secret ;
- l’historique technique.

---

## 6. Ce que CAP-CORE-015 ne possède pas

`CAP-CORE-015` ne possède pas :

- les clés privées ;
- les mots de passe ;
- les phrases secrètes ;
- les jetons actifs ;
- les secrets d’API ;
- les credentials PostgreSQL ;
- les décisions d’autorisation ;
- les politiques ;
- les contrats ;
- les sources ;
- les dossiers métier ;
- les fichiers originaux de taille arbitraire ;
- les dumps de bases ;
- les sauvegardes ;
- les événements complets ;
- les décisions opérationnelles de `CAP-CORE-008` ;
- la qualification juridique d’une preuve ;
- la vérité du contenu signé ;
- l’identité humaine derrière une clé sans résolution par les capacités compétentes.

Répartition :

```text
CAP-CORE-013
= trace qu’une opération a eu lieu

CAP-CORE-014
= transport d’un événement contractuel

CAP-CORE-015
= preuve cryptographique et vérification d’un artefact ou constat

CAP-CORE-016
= référence, cycle et usage des clés ; exécution cryptographique sans exposition

CAP-CORE-019
= production et restauration des sauvegardes
```

---

## 7. État actuel à confirmer

L’audit initial doit confirmer les constats suivants.

### 7.1 Baseline opérationnelle

La baseline du registre des normes :

- porte une empreinte SHA-256 codée ;
- vérifie le fichier avant reconstruction ;
- compare l’empreinte attendue et l’empreinte réelle ;
- ne produit pas une preuve commune enregistrée dans un registre transversal ;
- ne signe pas son empreinte.

### 7.2 Journal opérationnel

Le journal de `CAP-CORE-013` :

- est en ajout seul ;
- chaîne les événements avec SHA-256 ;
- vérifie la cohérence de la chaîne ;
- retourne explicitement `signee: false` ;
- prouve la cohérence interne et l’ordre observé ;
- ne prouve pas l’origine par signature.

### 7.3 Sauvegardes

`CAP-CORE-019` :

- calcule un fichier `SHA256SUMS` ;
- vérifie les sommes avant copie hors machine ;
- chiffre les archives ;
- ne possède pas encore un manifeste de preuve transversal et signé ;
- ne lie pas systématiquement chaque exercice de restauration à une preuve commune.

### 7.4 Contrats et registres

Plusieurs capacités utilisent déjà :

- des empreintes de bootstrap ;
- des empreintes de contenu ;
- des références de preuve ;
- des rapports de conformité ;
- des projections avec empreinte.

Ces mécanismes doivent rester fonctionnels.

La migration doit éviter deux erreurs :

```text
remplacer brutalement tous les hash existants
ou
laisser CAP-CORE-015 inutilisée à côté des mécanismes existants
```

### 7.5 Fédération et accès

Les jetons fédérés et secrets temporaires :

- ne sont pas des preuves exportables ;
- restent dans leurs capacités ;
- peuvent utiliser des empreintes pour la comparaison ;
- ne doivent pas être importés comme preuves dans `CAP-CORE-015`.

### 7.6 Absences actuelles

Aucun service commun ne semble encore posséder :

- une référence universelle de preuve ;
- une canonicalisation déclarée ;
- une signature liée à une version de clé ;
- une vérification historique ;
- un manifeste signé ;
- une révocation de preuve ;
- un paquet de preuve exportable ;
- une API publique de vérification limitée ;
- un catalogue des algorithmes réellement acceptés.

---

## 8. Audit initial obligatoire

Avant tout code, produire un inventaire des mécanismes existants.

Rechercher au minimum :

```bash
rg -n --hidden \
  --glob '!.git/**' \
  --glob '!vendor/**' \
  --glob '!node_modules/**' \
  "hash\(|hash_hmac\(|password_hash\(|password_verify\(|sha256sum|SHA256SUMS|empreinte|signature|signee|preuve_reference|manifest"
```

Classer chaque résultat :

```text
empreinte de contenu
empreinte de secret non réversible
signature
somme de sauvegarde
chaîne d’intégrité
preuve métier
référence d’audit
identifiant opaque
```

Pour chaque mécanisme, relever :

- fichier ;
- capacité propriétaire ;
- algorithme ;
- représentation ;
- format de sortie ;
- données protégées ;
- durée ;
- clé éventuelle ;
- consommateur ;
- risque de migration ;
- décision : conserver, raccorder, remplacer progressivement ou exclure.

Ne jamais enregistrer dans le rapport :

- une valeur de secret ;
- un token ;
- une clé privée ;
- un mot de passe ;
- un contenu confidentiel complet.

---

## 9. Architecture cible

Créer :

```text
core/registre-preuves/
├── README.md
├── resources/
│   └── bootstrap-preuves-v1.json
├── src/
│   ├── Magasin.php
│   ├── SchemaPreuves.php
│   ├── RegistrePreuves.php
│   ├── Canonicaliseur.php
│   ├── CalculateurEmpreinte.php
│   ├── ServiceSignature.php
│   ├── VerificateurPreuve.php
│   ├── ConstructeurManifeste.php
│   ├── ExportateurPaquetPreuve.php
│   ├── PolitiquePreuves.php
│   └── ExceptionPreuve.php
└── tests/
    └── preuves_p3.php
```

Variables proposées :

```text
PROOF_REGISTRY_URL
PROOF_REGISTRY_PATH
GAMAD_PROOFS_DRIVER
GAMAD_PROOF_MAX_INLINE_BYTES
GAMAD_PROOF_ALLOWED_HASH_ALGORITHMS
GAMAD_PROOF_ALLOWED_SIGNATURE_ALGORITHMS
```

Règles :

- PostgreSQL obligatoire en production ;
- SQLite autorisé uniquement en local et CI ;
- aucune valeur secrète dans le magasin ;
- aucun fallback silencieux en production ;
- configuration Laravel mise en cache supportée ;
- magasin distinct de l’index et des autres capacités ;
- aucune migration pendant une requête HTTP ;
- aucun calcul cryptographique non borné dans une requête publique ;
- aucune lecture arbitraire d’un chemin fourni par le client.

---

## 10. Borne de sécurité

Les opérations cryptographiques doivent toujours être contextualisées.

La donnée signée ne doit pas être seulement :

```text
empreinte
```

Elle doit intégrer un contexte canonique comme :

```text
GAMAD-PROOF-V1
preuve_reference
preuve_type
realm_reference
finalite_reference
sujet_type
sujet_reference
algorithme_empreinte
empreinte
cree_le
expire_le
```

Cette borne empêche de réutiliser une signature valide dans un autre contexte.

Aucune signature ne doit être créée à partir d’une concaténation ambiguë.

---

## 11. Livrable de cette partie

À la fin de l’audit préparatoire, Claude doit pouvoir expliquer :

1. quelles preuves existent déjà ;
2. lesquelles sont seulement des empreintes ;
3. lesquelles sont liées à des secrets et doivent rester exclues ;
4. quelles capacités seront raccordées pendant ce chantier ;
5. quelle interface réelle de `CAP-CORE-016` sera utilisée ;
6. quels algorithmes sont réellement disponibles sur le serveur ;
7. quels formats seront conservés pour compatibilité ;
8. comment la migration évitera toute rupture de production.

Ne commencer le schéma qu’après cette réponse.
