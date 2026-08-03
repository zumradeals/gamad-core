# CAP-CORE-016 — Inventaire, migration, commandes et rotations

## 1. Audit initial obligatoire

Avant toute migration, produire un inventaire des **références et usages**, jamais des valeurs.

Pour chaque secret ou clé observé, relever uniquement :

- nom logique ;
- variable ou fichier de référence ;
- capacité ou produit consommateur ;
- finalité ;
- realm ;
- environnement ;
- fournisseur actuel ;
- possibilité de rotation ;
- dépendances historiques ;
- tests existants ;
- propriétaire opérationnel ;
- niveau d’urgence de migration.

Ne jamais afficher :

- valeur ;
- longueur exacte d’une valeur réelle lorsque cela facilite l’identification ;
- préfixe ;
- suffixe ;
- hash non prévu à cet effet ;
- contenu de fichier ;
- contenu de `.env` ;
- contenu de trousseau ;
- sortie brute d’un gestionnaire de secrets.

Le rapport doit utiliser des valeurs comme :

```text
présent
absent
illisible
non vérifié
référence inconnue
rotation non documentée
```

et jamais la valeur elle-même.

---

## 2. Inventaire minimal attendu

### 2.1 Laravel

Inventorier :

```text
APP_KEY
APP_PREVIOUS_KEYS
```

Vérifier leurs usages réels :

- chiffrement Laravel ;
- cookies ou sessions chiffrés ;
- données applicatives éventuellement chiffrées ;
- génération HMAC des descripteurs WebAuthn factices ;
- autres appels à `config('app.key')` ;
- autres appels à `Crypt`, `encrypt`, `decrypt`.

Ne pas supposer que la rotation est sans effet.

### 2.2 Bases PostgreSQL

Inventorier pour chaque magasin :

- service PostgreSQL ;
- utilisateur ;
- mécanisme de fourniture du mot de passe ;
- rotation possible ;
- consommateurs ;
- environnement ;
- privilèges.

Ne pas stocker les mots de passe dans le registre.

Préférer les références de service PostgreSQL et les fichiers d’identification protégés aux URI contenant un mot de passe.

### 2.3 Sauvegarde et copie hors machine

Inventorier :

```text
GAMAD_OFFSITE_RECIPIENT
GAMAD_OFFSITE_PASSPHRASE_FILE
GAMAD_OFFSITE_FTP_SECRET_FILE
GAMAD_OFFSITE_SSH_KEY
trousseau GPG dédié
clé ou certificat TLS épinglé
```

Identifier les lots historiques dépendant de chaque version.

Une clé de déchiffrement ne peut pas être détruite tant qu’un lot conservé dépend d’elle.

### 2.4 Services et fournisseurs externes

Inventorier les références présentes, notamment :

```text
REDIS_PASSWORD
MAIL_USERNAME
MAIL_PASSWORD
AWS_ACCESS_KEY_ID
AWS_SECRET_ACCESS_KEY
```

Ne créer aucune ressource active pour une variable vide ou un service non utilisé.

Le bootstrap doit distinguer :

- référence réellement utilisée ;
- référence prévue mais non configurée ;
- exemple documentaire seulement.

### 2.5 Fédération et événements

Inspecter :

- génération des jetons fédérés ;
- hachage et expiration ;
- éventuelle clé de signature ;
- authentification des consommateurs d’événements ;
- secrets de transport ;
- certificats utilisés.

Les jetons temporaires eux-mêmes restent hors `CAP-CORE-016`.

### 2.6 Passkeys et authentificateurs

Confirmer :

- clé privée WebAuthn hors serveur ;
- credential public dans `CAP-CORE-005` ;
- mots de passe hachés ;
- codes de secours hachés ;
- autorisations temporaires hachées.

Ne migrer aucune de ces valeurs vers `CAP-CORE-016`.

---

## 3. Recherche de code

Commandes indicatives :

```bash
rg -n --hidden \
  --glob '!.git/**' \
  --glob '!vendor/**' \
  --glob '!node_modules/**' \
  "APP_KEY|APP_PREVIOUS_KEYS|PASSWORD|SECRET|PRIVATE_KEY|PASSPHRASE|SSH_KEY|RECIPIENT|PGSERVICE|PGPASSWORD|AWS_SECRET|hash_hmac|openssl|sodium|gpg|ssh"
```

Inspecter aussi :

```bash
php artisan about
php artisan config:show app
php artisan config:show database
php artisan config:show session
```

Ne jamais exécuter une commande qui imprime une valeur réelle dans un journal de CI ou dans la PR.

Masquer ou éviter les sorties sensibles.

---

## 4. Bootstrap

Créer :

```text
core/registre-secrets-cles/resources/bootstrap-secrets-cles-v1.json
```

Le bootstrap contient seulement :

- références logiques ;
- types ;
- finalités ;
- propriétaires ;
- sources ;
- realms ;
- environnements ;
- fournisseurs ;
- handles factices ou noms de variables/fichiers de référence ;
- usages ;
- états de migration ;
- aucune valeur.

Exigences :

- inventaire fondé sur le code et la configuration réellement utilisés ;
- aucune ressource fictive active ;
- empreinte SHA-256 du fichier ;
- bootstrap idempotent ;
- transaction unique ;
- rapport des références non résolues ;
- aucune tentative de lire le secret pendant le bootstrap ;
- aucune activation automatique sans vérification du fournisseur.

Le bootstrap peut inscrire une ressource en `PREPARATION` lorsque son fournisseur n’est pas encore vérifié.

---

## 5. Politique d’administration

Créer :

```text
POL-SECRETS-CLES-V1
```

Actions minimales :

```text
secret.lire-metadonnees
secret.inscrire
secret.version.declarer
secret.version.verifier
secret.version.activer
secret.usage.declarer
secret.rotation.planifier
secret.rotation.valider
secret.rotation.executer
secret.version.suspendre
secret.version.revoquer
secret.version.compromettre
secret.version.detruire
secret.materiel-public.exporter
secret.diagnostic.lire
```

Bornes :

- lecture des métadonnées selon périmètre ;
- handles fournisseurs masqués pour les acteurs non autorisés ;
- aucune action permettant de lire une valeur ;
- activation réservée à l’autorité compétente ;
- compromission disponible en urgence mais auditée ;
- destruction exige confirmation renforcée ;
- séparation entre demande et validation de rotation lorsque les identités disponibles le permettent ;
- niveau d’assurance renforcé pour activation, compromission et destruction ;
- refus par défaut.

Ne pas créer une action `secret.exporter`.

---

## 6. Commandes métier

### 6.1 `inscrireSecret`

Entrées :

- référence ;
- nom ;
- type ;
- finalité ;
- propriétaire ;
- source ;
- realm ;
- environnement ;
- classification ;
- politique de rotation ;
- acteur ;
- preuve ;
- `correlation_id`.

Règles :

- référence unique ;
- aucune valeur ;
- propriétaire connu ;
- source active ;
- realm actif ;
- environnement canonique ;
- audit obligatoire.

### 6.2 `inscrireFournisseur`

Règles :

- type supporté ;
- environnement borné ;
- configuration non secrète ;
- diagnostic avant activation ;
- aucune activation si permissions de fichier faibles ;
- audit obligatoire.

### 6.3 `declarerVersion`

Entrées :

- secret ;
- version ;
- fournisseur ;
- handle ;
- algorithme ;
- empreinte publique ;
- matériel public éventuel ;
- dates ;
- acteur ;
- preuve.

Règles :

- aucune valeur ;
- version unique ;
- fournisseur connu ;
- handle non vide ;
- état initial `PREPARATION` ;
- aucune activation automatique.

### 6.4 `verifierVersion`

Règles :

- fournisseur disponible ;
- matériel présent ;
- permissions conformes ;
- algorithme conforme ;
- empreinte publique cohérente lorsqu’elle existe ;
- test borné non destructif ;
- résultat sans secret ;
- audit obligatoire.

La vérification ne doit pas imprimer le matériel.

### 6.5 `declarerUsage`

Règles :

- consommateur connu ;
- opération canonique ;
- finalité ;
- realm ;
- environnement ;
- période ;
- version ou secret logique ;
- aucune portée universelle ;
- audit obligatoire.

### 6.6 `activerVersion`

Règles :

- version vérifiée ;
- usages compatibles ;
- plan de rotation validé lorsqu’une version active existe ;
- activation atomique ;
- ancienne version basculée en lecture ou dépréciée selon stratégie ;
- cache invalidé après commit ;
- audit obligatoire ;
- événement minimal publié.

### 6.7 `suspendreVersion`

Règles :

- blocage immédiat des nouveaux usages ;
- aucune destruction ;
- usages historiques explicitement définis ;
- motif obligatoire ;
- audit et événement.

### 6.8 `revoquerVersion`

Règles :

- nouveaux usages interdits ;
- effets sur les dépendances évalués ;
- aucune réactivation normale ;
- audit et événement ;
- procédure d’urgence si la version était active.

### 6.9 `declarerCompromission`

Règles :

- référence de version obligatoire ;
- niveau ;
- source ;
- motif ;
- blocage selon politique ;
- ouverture d’un suivi interne ;
- publication minimale ;
- aucune donnée sur la valeur compromise ;
- audit immédiat.

### 6.10 `detruireVersion`

Règles :

- version non active ;
- aucune dépendance non expirée ;
- aucune obligation de conservation ;
- fournisseur capable de détruire ;
- confirmation renforcée ;
- preuve de résultat ;
- état `DETRUITE` seulement après confirmation ;
- aucune suppression de l’historique.

---

## 7. Requêtes

Implémenter au minimum :

```text
resoudreSecret(reference)
listerSecrets(filtres)
listerVersions(reference)
resoudreVersion(reference, version)
resoudreVersionActiveEcriture(reference, contexte)
resoudreVersionsLecture(reference, contexte)
listerUsages(reference)
listerDependances(reference, version?)
listerRotations(reference)
resoudreRotation(reference)
listerCompromissions(filtres)
diagnostiquerRegistre()
diagnostiquerFournisseurs()
```

Les réponses publiques ne retournent jamais le matériel.

Les handles fournisseurs sont masqués selon l’autorisation.

---

## 8. Résolution interne

Créer une API PHP interne, pas une route HTTP de lecture du secret.

Forme indicative :

```php
$resolveur->avecSecret(
    reference: 'SEC-GAMAD-OFFSITE-FTP',
    contexte: $contexte,
    operation: static function (SensitiveValue $secret): Resultat {
        // usage borné
    },
);
```

Règles :

- contexte obligatoire ;
- autorisation ;
- usage ;
- realm ;
- environnement ;
- finalité ;
- version ;
- fournisseur ;
- audit sans valeur ;
- aucune sérialisation ;
- aucune mise en cache générale ;
- aucune remontée vers contrôleur ou vue.

Pour une clé privée non exportable, l’adaptateur doit exécuter l’opération :

```text
signer
chiffrer
déchiffrer
```

sans transmettre la clé au code appelant.

---

## 9. Rotation de `APP_KEY`

La rotation doit tenir compte de :

- `APP_KEY` courant ;
- `APP_PREVIOUS_KEYS` ;
- sessions chiffrées ;
- cookies chiffrés ;
- données persistantes éventuellement chiffrées ;
- HMAC utilisé pour les descripteurs factices WebAuthn ;
- configuration Laravel mise en cache.

Plan minimal :

1. inventaire des données chiffrées ;
2. déclaration de la nouvelle version ;
3. vérification du fournisseur ;
4. ajout de l’ancienne clé dans `APP_PREVIOUS_KEYS` ;
5. activation de la nouvelle clé ;
6. reconstruction du cache de configuration ;
7. test de lecture d’artefacts anciens ;
8. test de création d’artefacts nouveaux ;
9. renouvellement ou invalidation contrôlée des sessions selon comportement réel ;
10. retrait de l’ancienne clé uniquement après fin des dépendances.

La modification des descripteurs WebAuthn factices après rotation est acceptable seulement si :

- aucune passkey réelle n’est affectée ;
- l’anti-énumération reste constante ;
- les tests le prouvent.

Ne jamais remplacer `APP_KEY` sans plan de retour arrière.

---

## 10. Rotation des mots de passe PostgreSQL

Stratégie recommandée selon possibilités du serveur :

1. créer ou préparer le nouvel identifiant ;
2. accorder uniquement les privilèges nécessaires ;
3. déclarer la nouvelle version ;
4. tester une connexion non destructive ;
5. basculer les consommateurs ;
6. vérifier readiness et opérations ;
7. révoquer l’ancien identifiant ;
8. conserver la preuve de rotation.

Ne jamais journaliser une URI de connexion contenant le mot de passe.

Ne jamais passer le mot de passe en argument de processus.

---

## 11. Rotation du chiffrement des sauvegardes

### 11.1 Destinataire GPG

Pour chaque nouvelle version :

- importer ou rendre disponible la nouvelle clé publique ;
- vérifier son empreinte ;
- chiffrer un lot de test ;
- déchiffrer dans une cible isolée avec la clé privée externe ;
- activer le nouveau destinataire pour les nouveaux lots ;
- garder les anciennes clés privées tant que des lots historiques existent ;
- rattacher chaque lot à la version utilisée.

### 11.2 Phrase secrète

La phrase secrète doit être lue depuis un fichier protégé ou un credential systemd.

La rotation exige :

- nouvelle version ;
- nouveau fichier ou credential ;
- test de chiffrement et déchiffrement ;
- bascule ;
- conservation de l’ancienne version jusqu’à expiration des lots ;
- destruction contrôlée.

Aucune phrase secrète dans :

- arguments ;
- logs ;
- journal ;
- événements ;
- base ;
- rapport de test.

---

## 12. Rotation SSH et FTP

### SSH

- clé dédiée ;
- nouvelle clé publique installée à distance ;
- test de connexion borné ;
- bascule du handle ;
- vérification du dépôt ;
- révocation de l’ancienne clé ;
- contrôle des hôtes connus.

### FTP ou FTPS

- nouveau secret déposé dans un fichier protégé ;
- test TLS selon politique ;
- vérification d’un dépôt factice ;
- bascule ;
- révocation de l’ancien secret ;
- aucune sortie du secret dans curl, shell ou logs.

---

## 13. Rotation d’une clé de signature

Préparer pour `CAP-CORE-015` :

1. générer ou déclarer la nouvelle paire dans le fournisseur ;
2. enregistrer la clé publique et son empreinte ;
3. activer la nouvelle version pour `SIGNER` ;
4. conserver les anciennes versions pour `VERIFIER` ;
5. publier la nouvelle clé publique selon contrat ;
6. rattacher chaque signature à une version ;
7. ne détruire une ancienne clé privée qu’après fin de toute obligation de resignature ou preuve ;
8. ne jamais empêcher la vérification historique par suppression prématurée du matériel public.

---

## 14. Compromission

Procédure minimale :

```text
déclarer
→ suspendre ou bloquer
→ identifier les usages
→ identifier les artefacts affectés
→ préparer une nouvelle version
→ tourner
→ révoquer
→ notifier les consommateurs autorisés
→ conserver les preuves
```

La compromission ne doit jamais être masquée par une simple rotation silencieuse.

Le registre doit distinguer :

- rotation planifiée ;
- rotation d’urgence ;
- révocation ;
- destruction ;
- compromission.

---

## 15. Événements CAP-CORE-014

Publier uniquement des métadonnées minimales :

```text
SECRET_REFERENCE_INSCRITE
VERSION_SECRET_DECLAREE
VERSION_SECRET_ACTIVEE
ROTATION_SECRET_PLANIFIEE
ROTATION_SECRET_REUSSIE
ROTATION_SECRET_ECHOUEE
VERSION_SECRET_SUSPENDUE
VERSION_SECRET_REVOQUEE
VERSION_SECRET_COMPROMISE
VERSION_SECRET_DETRUITE
FOURNISSEUR_SECRET_DEGRADE
```

Charge autorisée :

- référence ;
- version ;
- état ;
- realm ;
- environnement ;
- type ;
- date ;
- corrélation ;
- aucun handle sensible lorsque non nécessaire ;
- aucune valeur ;
- aucune clé privée ;
- aucun chemin confidentiel.

---

## 16. Idempotence et concurrence

Garanties :

- commandes rejouables ;
- verrou sur la ressource pendant activation ou rotation ;
- une seule version active en écriture ;
- aucun double plan actif incompatible ;
- étape de rotation idempotente ;
- activation après commit ;
- audit dans la transaction lorsque possible ;
- rollback si la gouvernance échoue ;
- aucune destruction automatique après échec ;
- version précédente conservée tant que la bascule n’est pas prouvée.

---

## 17. Migration progressive

Phases :

### Phase A — Inventaire

- références ;
- usages ;
- fournisseurs ;
- dépendances ;
- aucune modification.

### Phase B — Registre sans bascule

- bootstrap métadonnées ;
- fournisseurs en préparation ;
- diagnostics ;
- aucune résolution obligatoire.

### Phase C — Double contrôle

- application continue son mécanisme actuel ;
- registre vérifie cohérence et présence ;
- écarts visibles ;
- aucun fallback nouveau.

### Phase D — Résolution gouvernée

- consommateurs pilotes migrés ;
- usage et contexte obligatoires ;
- tests de rotation.

### Phase E — Retrait des chemins historiques

- suppression des lectures directes dispersées lorsque tous les consommateurs sont migrés ;
- retrait de `VARIABLE_ENVIRONNEMENT_TRANSITION` pour les références concernées ;
- aucune suppression globale de `.env` sans preuve.

Ne pas convertir tous les secrets en une seule PR non testable.

Le chantier reste une seule capacité, mais la migration doit être ordonnée et prouvée référence par référence.
