# CAP-CORE-016 — Modèle, fournisseurs et cycle

## 1. Principes du modèle

Le magasin de `CAP-CORE-016` contient uniquement de la gouvernance.

Il ne contient jamais le matériel secret.

Les tables doivent permettre de distinguer clairement :

- la ressource logique stable ;
- les versions successives ;
- le fournisseur qui conserve chaque version ;
- les usages autorisés ;
- le cycle de vie ;
- les rotations ;
- les dépendances historiques ;
- les informations publiques autorisées.

Toutes les tables d’historique sont en ajout seul.

---

## 2. Table `secret_ressource`

Champs minimaux :

- `reference` ;
- `nom` ;
- `type_secret` ;
- `finalite_reference` ;
- `proprietaire_reference` ;
- `source_reference` ;
- `realm_reference` nullable ;
- `environnement_reference` ;
- `classification_reference` ;
- `description` nullable ;
- `rotation_requise` ;
- `duree_rotation_jours` nullable ;
- `cree_le` ;
- `modifie_le`.

Contraintes :

- référence unique et immuable ;
- référence jamais réattribuée ;
- propriétaire connu ;
- source active ;
- realm actif lorsqu’il est renseigné ;
- environnement canonique ;
- finalité explicite ;
- aucune colonne de valeur secrète ;
- aucun champ générique permettant de contourner cette interdiction.

Types initiaux proposés :

```text
CLE_CHIFFREMENT_SYMETRIQUE
PAIRE_CLES_SIGNATURE
PAIRE_CLES_CHIFFREMENT
CLE_HMAC
SECRET_API
IDENTIFIANT_CONNEXION
MOT_DE_PASSE_SERVICE
CLE_SSH
CLE_GPG
PHRASE_SECRETE
CLE_APPLICATION
CERTIFICAT_TLS
```

Les termes exacts doivent provenir de `CAP-CORE-010`.

Un certificat public peut être référencé ici lorsqu’il appartient au même cycle qu’une clé privée externe.

---

## 3. Table `secret_fournisseur`

Champs minimaux :

- `reference` ;
- `nom` ;
- `type_fournisseur` ;
- `realm_reference` nullable ;
- `environnement_reference` ;
- `proprietaire_reference` ;
- `etat` ;
- `capacites_json` ;
- `configuration_reference` nullable ;
- `cree_le` ;
- `modifie_le`.

Types initiaux :

```text
FICHIER_0600
CREDENTIAL_SYSTEMD
VARIABLE_ENVIRONNEMENT_TRANSITION
TROUSSEAU_GPG
AGENT_SSH
FOURNISSEUR_EXTERNE
```

Règles :

- `VARIABLE_ENVIRONNEMENT_TRANSITION` est toléré pour migration, pas comme cible finale générale ;
- `FOURNISSEUR_EXTERNE` exige un adaptateur explicite ;
- aucune URL contenant des identifiants en clair ;
- aucune configuration secrète dans `configuration_reference` ;
- le fournisseur peut déclarer des capacités comme `LIRE`, `GENERER`, `ROTATION`, `DETRUIRE`, `SIGNER_SANS_EXPORT`, `DECHIFFRER_SANS_EXPORT` ;
- une capacité déclarée ne vaut pas autorisation d’usage.

États :

```text
PREPARATION
ACTIF
DEGRADE
SUSPENDU
RETIRE
```

---

## 4. Table `secret_version`

Champs minimaux :

- `id` ;
- `secret_reference` ;
- `version` ;
- `fournisseur_reference` ;
- `handle_fournisseur` ;
- `algorithme_reference` nullable ;
- `taille_bits` nullable ;
- `empreinte_publique` nullable ;
- `identifiant_public` nullable ;
- `cle_publique` nullable ;
- `date_debut_prevue` nullable ;
- `date_fin_prevue` nullable ;
- `cree_par_reference` ;
- `preuve_reference` ;
- `cree_le`.

Contraintes :

- couple `(secret_reference, version)` unique ;
- version jamais réutilisable ;
- `handle_fournisseur` opaque ;
- handle ne contenant pas le secret ;
- clé publique autorisée seulement pour une ressource asymétrique ;
- empreinte publique obligatoire lorsque le fournisseur peut la fournir ;
- aucune clé privée ;
- aucun secret symétrique ;
- aucun contenu de fichier ;
- aucune variable d’environnement résolue.

Format de version recommandé :

```text
1
2
3
```

ou :

```text
2026-08-rotation-01
```

Choisir un format stable et déterministe.

La version technique du secret ne doit pas être confondue avec une version de contrat ou de politique.

---

## 5. Table `secret_version_cycle`

Champs minimaux :

- `id` ;
- `secret_version_id` ;
- `etat` ;
- `date_effet` ;
- `motif` ;
- `acteur_reference` ;
- `politique_reference` ;
- `preuve_reference` ;
- `correlation_id` ;
- `cree_le`.

États initiaux :

```text
PREPARATION
ACTIVE_ECRITURE
ACTIVE_LECTURE
DEPRECIEE
SUSPENDUE
REVOQUEE
COMPROMISE
DETRUITE
```

Sémantique :

- `ACTIVE_ECRITURE` : utilisée pour produire de nouveaux artefacts ou connexions ;
- `ACTIVE_LECTURE` : utilisable uniquement pour relire, déchiffrer ou vérifier l’historique ;
- `DEPRECIEE` : plus utilisée pour de nouveaux usages, mais encore retenue pour migration ;
- `SUSPENDUE` : usage bloqué temporairement ;
- `REVOQUEE` : usage normal interdit ;
- `COMPROMISE` : traitement d’urgence obligatoire ;
- `DETRUITE` : fournisseur confirme l’indisponibilité définitive du matériel.

Règles :

- ajout seul ;
- une seule version `ACTIVE_ECRITURE` par secret, realm et environnement ;
- plusieurs versions `ACTIVE_LECTURE` possibles pendant une migration ;
- une version `COMPROMISE` ne redevient jamais active ;
- une version `DETRUITE` ne redevient jamais disponible ;
- aucun état passé réécrit ;
- activation atomique avec dépréciation de l’ancienne version d’écriture.

---

## 6. Table `secret_usage`

Champs minimaux :

- `id` ;
- `secret_version_id` nullable ;
- `secret_reference` ;
- `capacite_reference` nullable ;
- `produit_reference` nullable ;
- `organisation_reference` nullable ;
- `realm_reference` nullable ;
- `environnement_reference` ;
- `operation_reference` ;
- `finalite_reference` ;
- `mode_usage` ;
- `date_debut` ;
- `date_fin` nullable ;
- `acteur_reference` ;
- `politique_reference` ;
- `preuve_reference` ;
- `cree_le`.

Modes initiaux :

```text
LIRE_SECRET
CONNECTER
CHIFFRER
DECHIFFRER
SIGNER
VERIFIER
CALCULER_HMAC
AUTHENTIFIER
TRANSPORTER
```

Règles :

- au moins un consommateur : capacité ou produit ;
- consommateur connu ;
- finalité explicite ;
- opération canonique ;
- realm et environnement bornés ;
- aucun joker universel ;
- aucune expression exécutable ;
- aucune requête libre ;
- usage expirant lorsqu’il est temporaire ;
- usage d’une version précise lorsqu’une compatibilité historique l’exige ;
- usage du secret logique lorsque le résolveur doit prendre la version active.

Un usage ne donne jamais à un utilisateur humain la valeur du secret.

Il donne à un composant technique autorisé la capacité de résoudre ou d’exécuter l’opération nécessaire.

---

## 7. Table `secret_dependance`

Champs minimaux :

- `id` ;
- `secret_reference` ;
- `secret_version_id` nullable ;
- `type_dependance` ;
- `ressource_reference` ;
- `date_debut` ;
- `date_fin` nullable ;
- `obligation_conservation` ;
- `motif` ;
- `cree_le`.

Types :

```text
DONNEE_CHIFFREE
SAUVEGARDE
SESSION
SIGNATURE
JETON
CONNEXION
CERTIFICAT
ARTEFACT_EXTERNE
```

But : empêcher la destruction prématurée d’une ancienne version.

Exemple :

```text
La clé GPG version 2 doit rester disponible jusqu’à expiration du dernier lot de sauvegarde chiffré avec elle.
```

Règles :

- aucune destruction si une dépendance non expirée exige la version ;
- dépendance liée à une référence, jamais au contenu de l’artefact ;
- fermeture explicite lorsque l’artefact a expiré, été migré ou détruit.

---

## 8. Table `secret_rotation_plan`

Champs minimaux :

- `reference` ;
- `secret_reference` ;
- `ancienne_version_id` nullable ;
- `nouvelle_version_id` nullable ;
- `strategie` ;
- `date_prevue` ;
- `fenetre_fin` nullable ;
- `retour_arriere_autorise` ;
- `etapes_json` ;
- `impact_json` ;
- `etat` ;
- `cree_par_reference` ;
- `preuve_reference` ;
- `cree_le`.

Stratégies initiales :

```text
DOUBLE_LECTURE_ECRITURE_NOUVELLE
DOUBLE_IDENTIFIANT
BASCULE_ATOMIQUE
ROTATION_FOURNISSEUR
RECHIFFREMENT_PROGRESSIF
RENOUVELLEMENT_CERTIFICAT
```

États :

```text
BROUILLON
EN_VALIDATION
VALIDE
EN_COURS
REUSSI
ECHEC
ANNULE
```

Règles :

- plan obligatoire pour toute rotation de production ;
- consommateurs impactés inventoriés ;
- stratégie de retour arrière explicite ;
- durée de coexistence bornée ;
- aucune étape contenant un secret ;
- validation avant exécution ;
- version exacte liée à la preuve.

---

## 9. Table `secret_rotation_execution`

Champs minimaux :

- `reference` ;
- `plan_reference` ;
- `etape_reference` ;
- `etat` ;
- `commence_le` ;
- `termine_le` nullable ;
- `resultat_code` nullable ;
- `resume_json` ;
- `acteur_reference` ;
- `preuve_reference` ;
- `correlation_id` ;
- `cree_le`.

Règles :

- aucun secret dans le résumé ;
- aucune sortie brute de commande ;
- aucun stack trace contenant une configuration ;
- résultat borné ;
- reprise idempotente ;
- étapes déjà réussies non rejouées sans justification ;
- échec ne détruisant pas automatiquement l’ancienne version.

---

## 10. Table `secret_compromission`

Champs minimaux :

- `reference` ;
- `secret_version_id` ;
- `detectee_le` ;
- `declaree_par_reference` ;
- `source_reference` ;
- `niveau` ;
- `portee_presumee` ;
- `motif` ;
- `etat` ;
- `preuve_reference` ;
- `correlation_id` ;
- `cree_le`.

Niveaux :

```text
SUSPECTEE
PROBABLE
CONFIRMEE
```

États :

```text
OUVERTE
CONTENUE
ROTATION_EN_COURS
CLOTUREE
```

Règles :

- déclaration ne contenant pas le secret ;
- version immédiatement bloquée pour nouveaux usages lorsque le niveau l’exige ;
- événement commun minimal publié par `CAP-CORE-014` ;
- audit complet par `CAP-CORE-013` ;
- future liaison avec `CAP-CORE-018` sans attendre cette capacité pour bloquer l’usage.

---

## 11. Table `secret_materiel_public`

Cette table est facultative mais recommandée pour les clés asymétriques.

Champs :

- `id` ;
- `secret_version_id` ;
- `type_materiel` ;
- `format` ;
- `contenu_public` ;
- `empreinte` ;
- `date_debut` ;
- `date_fin` nullable ;
- `cree_le`.

Types :

```text
CLE_PUBLIQUE
CERTIFICAT
CHAINE_CERTIFICATS
EMPREINTE
IDENTIFIANT_CLE
```

Règles :

- contenu strictement public ;
- validation de format ;
- empreinte obligatoire ;
- aucune clé privée ;
- aucune phrase secrète ;
- export autorisé uniquement selon contrat.

---

## 12. Fournisseurs initiaux

### 12.1 `FournisseurFichier0600`

But : lire un secret depuis un fichier dédié.

Contraintes :

- chemin absolu ;
- fichier régulier ;
- pas de lien symbolique, sauf politique explicite et sûre ;
- propriétaire et groupe attendus ;
- mode maximal `0600` ou plus restrictif ;
- répertoire parent non accessible au monde ;
- taille maximale ;
- aucune sortie dans les logs ;
- lecture à la demande ;
- pas de mise en cache durable ;
- handle stocké sous forme de référence opaque ou chemin restreint.

### 12.2 `FournisseurCredentialSystemd`

But : consommer un credential injecté par systemd.

Contraintes :

- lecture depuis le répertoire de credentials fourni au processus ;
- nom canonique ;
- aucune valeur dans l’unité ou le dépôt ;
- indisponibilité fermée ;
- vérification de taille ;
- aucune copie durable.

### 12.3 `FournisseurEnvironnementTransition`

But : migrer progressivement les usages actuels.

Contraintes :

- nom de variable enregistré, jamais sa valeur ;
- réservé aux références explicitement déclarées ;
- alerte de dette de migration ;
- interdit comme fallback silencieux ;
- interdit pour une nouvelle clé privée de signature ;
- date de retrait prévue obligatoire en production.

### 12.4 `FournisseurTrousseauGpg`

But : demander à GPG de chiffrer ou déchiffrer sans exporter la clé privée.

Contraintes :

- handle = empreinte ou identifiant public ;
- clé privée jamais lue par PHP ;
- commande bornée ;
- homedir dédié ;
- permissions contrôlées ;
- aucun secret dans les arguments ;
- résultat contrôlé ;
- clé publique exportable selon contrat.

### 12.5 `FournisseurAgentSsh`

But : utiliser une clé SSH dédiée sans exposer sa valeur à l’application.

Contraintes :

- usage réservé aux opérations techniques déclarées ;
- hôte et algorithme bornés ;
- `StrictHostKeyChecking=yes` ;
- aucun shell arbitraire ;
- aucun forwarding non nécessaire ;
- rotation du handle explicite.

---

## 13. Interface fournisseur

Créer une interface ne permettant pas un export général.

Forme indicative :

```php
interface FournisseurSecret
{
    public function verifierDisponibilite(DescripteurVersion $version): DiagnosticFournisseur;

    public function avecSecret(
        DescripteurVersion $version,
        UsageSecret $usage,
        callable $operation,
    ): mixed;

    public function empreintePublique(DescripteurVersion $version): ?string;

    public function detruire(DescripteurVersion $version): ResultatDestruction;
}
```

Règles :

- pas de méthode `exporterTousLesSecrets()` ;
- pas de méthode HTTP retournant le matériel ;
- callback borné et interne ;
- paramètre secret marqué `SensitiveParameter` lorsque possible ;
- nettoyage best effort de la mémoire ;
- aucune promesse mensongère d’effacement garanti des chaînes PHP ;
- durée de vie minimale dans le processus ;
- aucune sérialisation ;
- aucune exception contenant la valeur.

Pour les opérations cryptographiques non exportables, préférer :

```text
signer(message)
dechiffrer(artefact)
```

à :

```text
lireClePrivee()
```

---

## 14. Résolveur interne

`ResolveurSecret` doit :

1. recevoir une référence de secret ;
2. recevoir un contexte d’usage ;
3. vérifier l’autorisation `CAP-CORE-004` ;
4. vérifier l’usage enregistré ;
5. vérifier le realm ;
6. vérifier l’environnement ;
7. choisir la version active correcte ;
8. vérifier l’état et les dates ;
9. vérifier le fournisseur ;
10. exécuter l’opération bornée ;
11. auditer uniquement les métadonnées ;
12. ne jamais retourner la valeur à une couche HTTP.

Contexte minimal :

```text
acteur technique
capacité ou produit consommateur
realm
environnement
finalité
opération
correlation_id
```

---

## 15. Séparation lecture / écriture

Une rotation doit séparer :

- la version servant aux nouveaux artefacts ;
- les versions servant à relire l’historique.

Exemple :

```text
version 3 = ACTIVE_ECRITURE + ACTIVE_LECTURE
version 2 = ACTIVE_LECTURE
version 1 = DEPRECIEE puis DETRUITE après expiration des dépendances
```

Le résolveur doit exiger le mode approprié.

Une opération `CHIFFRER` ne peut pas utiliser une version seulement `ACTIVE_LECTURE`.

Une opération `DECHIFFRER` peut utiliser une ancienne version liée explicitement à l’artefact.

---

## 16. Isolation

Interdictions :

- une clé de développement en production ;
- une clé de production en CI ;
- une clé d’un realm dans un autre realm sans contrat explicite ;
- une même clé privée pour plusieurs finalités incompatibles ;
- une clé de sauvegarde réutilisée comme clé d’application ;
- un secret d’API partagé entre plusieurs produits sans justification ;
- un joker `*` dans les usages ;
- un fallback automatique vers la première version disponible.

L’isolation doit être vérifiée par contraintes et tests.

---

## 17. Invariants centraux

Le diagnostic doit prouver :

- aucune colonne interdite ;
- une seule version active en écriture ;
- aucune version compromise active ;
- aucune version détruite utilisée ;
- aucun usage orphelin ;
- aucun fournisseur retiré utilisé ;
- aucune version expirée utilisée en écriture ;
- aucune destruction bloquée par une dépendance ;
- aucune référence dupliquée ;
- aucune clé privée dans le magasin ;
- aucune valeur ressemblant à un secret dans les métadonnées.
