# GAMAD CORE — FICHE DE CODAGE CAP-CORE-016
# SECRETS & KEYS — PASSAGE DE NO GO À GO PRODUCTION

**Référence :** `CAP-CORE-016`  
**Nom :** Secrets & Keys / Registre des secrets et clés  
**Statut initial :** `NO GO`  
**Statut cible :** `GO`  
**Dépôt :** `zumradeals/gamad-core`  
**Branche cible :** `main`  
**Nature :** chantier complet de code, migration, tests, exploitation et documentation

---

## 1. Mission

Construire la capacité opérationnelle qui gouverne les références, versions, usages, rotations, révocations et compromissions des secrets et clés utilisés par GAMAD Core.

À la fin du chantier, le Core doit pouvoir répondre de manière persistante et vérifiable à ces questions :

```text
Quel secret ou quelle clé est utilisé ?
À quelle finalité sert-il ?
Quel fournisseur conserve le matériel secret ?
Quelle version est actuellement active ?
Quelles capacités, produits, realms et environnements peuvent l’utiliser ?
Pour quelles opérations exactes ?
Quand doit-il être renouvelé ?
Quelle ancienne version doit rester disponible pour lire les données historiques ?
Cette clé a-t-elle été révoquée ou déclarée compromise ?
Quels services dépendent de cette version ?
La rotation a-t-elle réellement été exécutée et vérifiée ?
Le matériel est-il disponible sans être exposé ?
```

Le registre doit permettre de gouverner notamment :

- la clé d’application Laravel ;
- les clés de chiffrement de données ou d’archives ;
- les clés de signature futures ;
- les clés HMAC ;
- les identifiants et secrets de connexion à des services ;
- les mots de passe de bases de données ;
- les secrets FTP ou FTPS ;
- les clés SSH dédiées ;
- les destinataires et clés GPG utilisés pour les sauvegardes ;
- les secrets d’API de produits ou de fournisseurs externes ;
- les secrets nécessaires aux transports d’événements ;
- les clés qui seront consommées par `CAP-CORE-015 — Integrity Proofs`.

Le résultat attendu n’est pas un coffre-fort PostgreSQL contenant les secrets.

Le résultat attendu est :

- un registre de gouvernance persistant ;
- des adaptateurs vers des fournisseurs externes ;
- des commandes de cycle de vie ;
- des rotations sûres ;
- des gardes contre l’exposition ;
- une exploitation testée ;
- une API et une console limitées aux métadonnées ;
- une intégration avec les capacités déjà `GO`.

`CAP-CORE-016` doit devenir `GO` avant le chantier de :

```text
CAP-CORE-015 — Integrity Proofs
```

---

## 2. Prérequis obligatoires

Le codage ne doit commencer qu’après que les capacités suivantes soient `GO` et fusionnées dans `main` :

```text
CAP-CORE-001 — Identity Registry
CAP-CORE-004 — Authorization
CAP-CORE-005 — Authentication & Access
CAP-CORE-006 — Sources Registry
CAP-CORE-007 — Rules / Policies Registry
CAP-CORE-009 — Contracts Registry
CAP-CORE-010 — Canonical Vocabulary
CAP-CORE-011 — Products Registry
CAP-CORE-012 — Realms Registry
CAP-CORE-013 — Common Audit
CAP-CORE-014 — Event Journal
CAP-CORE-019 — Backup & Restore
```

Raisons principales :

- `CAP-CORE-001` fournit les propriétaires et acteurs canoniques ;
- `CAP-CORE-004` décide les opérations autorisées ;
- `CAP-CORE-005` fournit les sessions et niveaux d’assurance ;
- `CAP-CORE-006` fournit la provenance ;
- `CAP-CORE-007` fournit les politiques ;
- `CAP-CORE-009` fournit les contrats d’échange ;
- `CAP-CORE-010` fournit les types, usages, états et algorithmes canoniques ;
- `CAP-CORE-011` fournit les produits consommateurs ;
- `CAP-CORE-012` fournit les périmètres d’isolation ;
- `CAP-CORE-013` conserve la preuve des opérations ;
- `CAP-CORE-014` publie les événements de rotation et compromission sans secret ;
- `CAP-CORE-019` prouve la sauvegarde et la restauration des métadonnées.

Avant de coder :

1. récupérer le dernier `origin/main` ;
2. vérifier que tous les prérequis sont marqués `GO` dans le catalogue ;
3. inspecter les contrats, vocabulaires et politiques réellement livrés ;
4. inventorier toutes les variables, fichiers et fournisseurs actuels ;
5. ne lire ni afficher aucune valeur réelle pendant l’audit ;
6. ne pas déplacer un secret réel vers Git, une base de test ou un artefact de CI ;
7. produire uniquement des exemples factices.

Si `CAP-CORE-014` n’est pas fusionnée, arrêter après l’audit préparatoire.

---

## 3. Règle de statut

Le dépôt utilise uniquement :

- `GO` ;
- `NO GO`.

`CAP-CORE-016` reste `NO GO` pendant tout le chantier.

Elle ne passe à `GO` qu’après :

- inventaire des usages réels ;
- registre persistant fonctionnel ;
- fournisseurs testés ;
- migrations sans exposition ;
- rotations éprouvées ;
- révocation et compromission éprouvées ;
- récupération après panne éprouvée ;
- API et console limitées aux métadonnées ;
- sauvegarde et restauration des métadonnées ;
- CI complète verte.

Les états d’un secret ou d’une version ne sont pas des statuts de capacité.

---

## 4. Définition opérationnelle

### 4.1 Secret

Un secret est une valeur qui donne un pouvoir lorsqu’elle est connue.

Exemples :

- mot de passe de base de données ;
- secret d’API ;
- phrase secrète ;
- jeton permanent ;
- secret HMAC ;
- clé de chiffrement symétrique.

### 4.2 Clé cryptographique

Une clé cryptographique est un matériel utilisé pour :

- chiffrer ;
- déchiffrer ;
- signer ;
- vérifier ;
- calculer un MAC ;
- dériver une valeur.

Une paire asymétrique comprend :

- une clé privée, toujours secrète ;
- une clé publique, publiable selon sa finalité.

### 4.3 Référence de secret

Une référence de secret est un identifiant canonique qui désigne un secret sans révéler sa valeur.

Exemples :

```text
SEC-GAMAD-APP-KEY
SEC-GAMAD-DB-IDENTITES
SEC-GAMAD-OFFSITE-FTP
KEY-GAMAD-OFFSITE-GPG
KEY-GAMAD-EVENTS-SIGNING
```

La référence reste stable pendant les rotations.

Chaque version reçoit un identifiant distinct.

### 4.4 Fournisseur

Un fournisseur conserve ou rend accessible le matériel secret.

Le registre connaît :

- son type ;
- son identifiant ;
- son état ;
- ses capacités ;
- la référence opaque du matériel.

Le registre ne connaît pas nécessairement la valeur secrète.

### 4.5 Usage

Un usage est une autorisation structurelle et bornée d’utiliser une version pour une opération déterminée.

Exemples :

```text
chiffrer une sauvegarde de production
ouvrir une connexion PostgreSQL du registre des identités
signer un événement commun
vérifier une signature historique
chiffrer les sessions Laravel
```

Un usage enregistré ne remplace jamais une décision de `CAP-CORE-004`.

---

## 5. Ce que CAP-CORE-016 possède

`CAP-CORE-016` possède :

- la référence canonique d’un secret ou d’une clé ;
- son type ;
- sa finalité ;
- son propriétaire ;
- sa source ;
- son realm ;
- son environnement ;
- son fournisseur ;
- ses versions ;
- les références opaques de matériel ;
- les empreintes et informations publiques autorisées ;
- les algorithmes déclarés ;
- les dates de validité ;
- les usages ;
- les consommateurs ;
- les plans de rotation ;
- les exécutions de rotation ;
- les révocations ;
- les déclarations de compromission ;
- les dépendances historiques ;
- les preuves de destruction logique ou fournisseur ;
- l’historique de gouvernance.

---

## 6. Ce que CAP-CORE-016 ne possède jamais

`CAP-CORE-016` ne possède jamais dans son magasin :

- un secret en clair ;
- une clé privée ;
- un mot de passe ;
- une phrase secrète ;
- un code de secours ;
- un jeton de session ;
- un jeton fédéré ;
- un challenge WebAuthn ;
- une clé privée de passkey ;
- une valeur `APP_KEY` ;
- une valeur `DB_PASSWORD` ;
- une valeur `AWS_SECRET_ACCESS_KEY` ;
- le contenu d’un fichier `.env` ;
- le contenu d’un fichier de secret ;
- le contenu d’un trousseau GPG privé ;
- le contenu d’une clé SSH privée.

Ces valeurs restent dans leurs fournisseurs spécialisés.

Le registre peut conserver :

- une clé publique ;
- une empreinte publique ;
- un identifiant de clé ;
- un chemin ou handle opaque restreint ;
- une version ;
- une date ;
- un algorithme ;
- un état ;
- une finalité.

---

## 7. Éléments explicitement exclus du périmètre

### 7.1 Mots de passe utilisateurs

Les mots de passe et codes de secours appartiennent à `CAP-CORE-005`.

Leur empreinte non réversible n’est pas une clé gérée par `CAP-CORE-016`.

`CAP-CORE-016` peut référencer la politique de hachage utilisée, mais ne doit pas importer les empreintes.

### 7.2 Passkeys

La clé privée d’une passkey reste dans l’authentificateur de l’utilisateur.

Le Core conserve uniquement le credential public et le compteur de signature dans `CAP-CORE-005`.

`CAP-CORE-016` ne devient pas un registre de passkeys.

### 7.3 Jetons temporaires

Les jetons temporaires sont créés, bornés, hachés et expirés par leur capacité productrice.

Le registre peut gouverner une clé de signature ou de chiffrement utilisée pour ces jetons, mais pas les jetons eux-mêmes.

### 7.4 Données chiffrées

Le registre ne stocke pas les données chiffrées métier.

Il indique uniquement quelle version de clé est nécessaire pour les lire.

### 7.5 Preuves générales

`CAP-CORE-015` possédera les preuves d’intégrité et signatures.

`CAP-CORE-016` fournit les clés gouvernées nécessaires, sans absorber le service de preuves.

---

## 8. Répartition avec les autres capacités

- `CAP-CORE-001` possède les identités.
- `CAP-CORE-004` décide les accès.
- `CAP-CORE-005` possède les authentificateurs, passkeys et sessions.
- `CAP-CORE-006` possède les sources.
- `CAP-CORE-007` possède les politiques.
- `CAP-CORE-009` possède les contrats.
- `CAP-CORE-010` possède les codes canoniques.
- `CAP-CORE-011` possède les produits.
- `CAP-CORE-012` possède les realms.
- `CAP-CORE-013` possède l’audit.
- `CAP-CORE-014` publie les événements communs.
- `CAP-CORE-015` utilisera les clés pour les preuves.
- `CAP-CORE-016` possède les références, versions, usages et rotations.
- `CAP-CORE-019` sauvegarde les métadonnées et prouve la récupération.

---

## 9. État actuel à confirmer

Inspecter le dépôt avant toute modification.

État attendu :

1. `CAP-CORE-016` est `NO GO`.
2. Aucun module persistant `core/registre-secrets-cles/` n’existe.
3. Les secrets sont actuellement dispersés entre :
   - variables d’environnement ;
   - fichiers lisibles par un compte système ;
   - configuration PostgreSQL ;
   - trousseaux GPG ;
   - clés SSH ;
   - fournisseurs externes éventuels.
4. `.env.example` contient des noms de variables mais aucune gouvernance de version.
5. `APP_KEY` et `APP_PREVIOUS_KEYS` existent dans la configuration Laravel.
6. `APP_KEY` est utilisé au moins par le chiffrement Laravel et par la génération de descripteurs factices WebAuthn.
7. Les mots de passe et codes de secours sont hachés dans `CAP-CORE-005`.
8. Les passkeys conservent une clé publique, jamais la clé privée.
9. `backup.sh` évite de passer les mots de passe PostgreSQL en argument.
10. `offsite.sh` exige un chiffrement avant départ.
11. La copie hors machine peut utiliser :
    - un destinataire GPG ;
    - une phrase secrète lue depuis un fichier ;
    - une clé SSH dédiée ;
    - un secret FTP lu depuis un fichier.
12. Aucun registre ne sait aujourd’hui :
    - quelle version est active ;
    - quand elle doit tourner ;
    - quels consommateurs en dépendent ;
    - quelles anciennes versions doivent rester disponibles ;
    - si une rotation a été achevée ;
    - si une clé a été compromise.
13. Aucun contrôle central ne détecte une référence orpheline ou un secret expiré.
14. Les sauvegardes du registre ne sauvegardent pas automatiquement les fournisseurs externes.
15. Aucun secret réel ne doit être lu ou affiché pendant cet inventaire.

---

## 10. Objectif de sécurité

À la fin du chantier :

- aucune valeur secrète ne doit entrer dans PostgreSQL ;
- aucune valeur secrète ne doit entrer dans Git ;
- aucune valeur secrète ne doit entrer dans un log ;
- aucune valeur secrète ne doit entrer dans `CAP-CORE-013` ;
- aucune valeur secrète ne doit entrer dans `CAP-CORE-014` ;
- aucune valeur secrète ne doit sortir par API ou console ;
- une application ne reçoit que la version nécessaire à son usage ;
- le fournisseur refuse un consommateur non déclaré ;
- une version révoquée ne sert plus aux nouveaux usages ;
- une version historique peut rester disponible uniquement pour lire ou vérifier des artefacts antérieurs ;
- une compromission déclenche un traitement explicite ;
- une panne du registre ou du fournisseur ferme l’opération sensible ;
- aucun fallback silencieux ne réutilise un secret obsolète.

---

## 11. Architecture cible

Créer :

```text
core/registre-secrets-cles/
├── README.md
├── resources/
│   └── bootstrap-secrets-cles-v1.json
├── src/
│   ├── Magasin.php
│   ├── SchemaSecretsCles.php
│   ├── RegistreSecretsCles.php
│   ├── PolitiqueSecretsCles.php
│   ├── ValidateurSecret.php
│   ├── PlanificateurRotation.php
│   ├── ResolveurSecret.php
│   ├── FournisseurSecret.php
│   ├── FournisseurFichier0600.php
│   ├── FournisseurCredentialSystemd.php
│   ├── FournisseurEnvironnementTransition.php
│   └── ExceptionSecret.php
└── tests/
    └── secrets_cles_p3.php
```

Les noms précis peuvent être adaptés aux conventions réellement livrées par les capacités précédentes.

Le principe ne change pas :

```text
registre de gouvernance
→ fournisseur externe
→ matériel secret temporairement disponible au processus autorisé
```

et jamais :

```text
registre PostgreSQL
→ secret en clair
```

---

## 12. Variables du magasin de gouvernance

Ajouter :

```text
SECRET_REGISTRY_URL
SECRET_REGISTRY_PATH
GAMAD_SECRETS_DRIVER
```

Règles :

- PostgreSQL obligatoire en production ;
- SQLite autorisé en local et CI ;
- aucune valeur secrète dans ces variables au-delà des mécanismes normaux de connexion ;
- aucun fallback silencieux en production ;
- configuration Laravel mise en cache supportée ;
- magasin distinct des autres capacités ;
- aucun bootstrap ou migration pendant une requête métier.

---

## 13. Règle de conception fondamentale

Une capacité consommatrice ne demande jamais :

```text
Donne-moi tous les secrets du produit.
```

Elle demande :

```text
Résous SEC-GAMAD-OFFSITE-FTP,
version active,
pour l’usage OFFSITE_UPLOAD,
le produit et le realm attendus,
dans l’environnement production.
```

Le résolveur vérifie :

- la référence ;
- l’état ;
- la version ;
- la période ;
- l’usage ;
- le consommateur ;
- le realm ;
- l’environnement ;
- la politique ;
- le fournisseur.

Puis il transmet la valeur au composant autorisé sans la persister ni la journaliser.

---

## 14. Résultat attendu

La PR de codage doit livrer :

- le registre persistant ;
- les fournisseurs initiaux ;
- l’inventaire des références existantes ;
- le bootstrap sans valeurs ;
- les commandes de cycle ;
- les rotations pilotes ;
- la résolution interne bornée ;
- la politique d’administration ;
- les contrats ;
- l’API de métadonnées ;
- la console ;
- l’audit ;
- les événements ;
- la readiness ;
- la sauvegarde et restauration ;
- les gardes et intégrations ;
- la fiche finale fondée sur le code réel.

Aucune autre capacité ne doit être commencée dans cette session.
