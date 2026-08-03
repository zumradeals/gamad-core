# CAP-CORE-015 — INTEGRITY PROOFS
# PARTIE 2/5 — MODÈLE, PREUVES ET CRYPTOGRAPHIE

---

## 1. Principes de modélisation

Le registre doit séparer quatre éléments :

```text
artefact ou déclaration
→ représentation canonique
→ empreinte
→ signature facultative
```

Une preuve peut donc être :

- une empreinte non signée ;
- une empreinte signée ;
- une attestation structurée signée ;
- un manifeste contenant plusieurs membres ;
- un checkpoint d’une structure ;
- un résultat de vérification.

Aucun champ ne doit laisser croire qu’une empreinte non signée est une signature.

---

## 2. Références

Formats recommandés :

```text
PRF-GAMAD-<aléatoire>       preuve
MNF-GAMAD-<aléatoire>       manifeste
ATT-GAMAD-<aléatoire>       attestation
CHK-GAMAD-<aléatoire>       checkpoint
VRF-GAMAD-<aléatoire>       vérification
PKG-GAMAD-<aléatoire>       paquet exportable
```

Les références sont :

- uniques ;
- immuables ;
- non signifiantes ;
- jamais réutilisées ;
- indépendantes des identifiants de base.

---

## 3. Table `preuve`

Champs minimaux :

- `reference` ;
- `type_preuve` ;
- `sujet_type` ;
- `sujet_reference` ;
- `producteur_capacite_reference` nullable ;
- `producteur_produit_reference` nullable ;
- `producteur_identite_reference` nullable ;
- `organisation_reference` nullable ;
- `realm_reference` ;
- `finalite_reference` ;
- `source_reference` ;
- `contrat_reference` nullable ;
- `contrat_version` nullable ;
- `classification` ;
- `description` nullable ;
- `cree_le` ;
- `cree_par_reference` ;
- `correlation_id` ;
- `idempotency_key` nullable.

Types initiaux :

```text
EMPREINTE_ARTEFACT
SIGNATURE_ARTEFACT
ATTESTATION
MANIFESTE
CHECKPOINT
PREUVE_CONFORMITE
PREUVE_RESTAURATION
PREUVE_EVENEMENT
PAQUET_PREUVE
```

Contraintes :

- au moins un producteur explicite ;
- exactement une capacité ou un produit producteur principal lorsque pertinent ;
- realm actif ;
- finalité active ;
- source utilisable ;
- contrat actif pour toute preuve exposée à un autre produit ;
- classification dans le vocabulaire canonique ;
- idempotence bornée au producteur et au type.

---

## 4. Table `preuve_representation`

Cette table décrit exactement ce qui a été empreinté.

Champs :

- `id` ;
- `preuve_reference` ;
- `format_representation` ;
- `version_canonicalisation` ;
- `media_type` ;
- `taille_octets` nullable ;
- `artefact_reference` nullable ;
- `chemin_logique` nullable ;
- `contenu_inline` nullable ;
- `encodage` nullable ;
- `metadonnees_json` ;
- `cree_le`.

Formats initiaux :

```text
OCTETS_BRUTS
JSON_CANONIQUE
TEXTE_UTF8_NORMALISE
MANIFESTE_CANONIQUE
CHECKPOINT_CANONIQUE
DECLARATION_CANONIQUE
```

Règles :

- `chemin_logique` n’est jamais un chemin arbitraire à ouvrir depuis une API ;
- un contenu inline est limité à une taille sûre ;
- les gros artefacts restent hors registre et sont référencés ;
- la représentation doit être reconstructible ou exportable ;
- la version de canonicalisation est obligatoire ;
- aucun secret ni donnée interdite dans `contenu_inline` ou `metadonnees_json`.

---

## 5. Table `preuve_empreinte`

Champs :

- `id` ;
- `preuve_reference` ;
- `algorithme` ;
- `empreinte_hex` ;
- `taille_bits` ;
- `calculee_le` ;
- `calculateur_version` ;
- `representation_empreinte` ;
- `est_principale` ;
- `cree_le`.

Algorithmes initiaux :

```text
SHA-256
SHA-512
```

`SHA-256` reste obligatoire pour la compatibilité avec les mécanismes actuels.

`MD5`, `SHA-1` et les algorithmes non approuvés sont interdits pour toute nouvelle preuve.

Règles :

- comparaison constante avec `hash_equals` ;
- format hexadécimal minuscule canonique ;
- longueur exacte vérifiée ;
- au moins une empreinte principale ;
- plusieurs empreintes possibles pendant une migration algorithmique ;
- aucune empreinte de mot de passe ou secret importée comme preuve d’artefact.

---

## 6. Table `preuve_signature`

Champs :

- `reference` ;
- `preuve_reference` ;
- `algorithme_signature` ;
- `cle_reference` ;
- `cle_version_reference` ;
- `signature_base64url` ;
- `contexte_signature_version` ;
- `empreinte_contexte` ;
- `signee_le` ;
- `expire_le` nullable ;
- `fournisseur_reference` ;
- `resultat_operation_reference` ;
- `cree_le`.

Contraintes :

- clé autorisée pour `SIGNER` ;
- version active à l’instant de signature ;
- usage compatible avec la finalité ;
- realm et environnement compatibles ;
- aucune clé privée dans la table ;
- signature immuable ;
- une signature révoquée n’est jamais supprimée ;
- `resultat_operation_reference` permet de rapprocher l’opération exécutée par `CAP-CORE-016`.

Algorithmes initiaux :

```text
ED25519
```

L’implémentation doit vérifier que `libsodium` est réellement disponible.

Un second algorithme ne doit être ajouté que si :

- un besoin réel existe ;
- `CAP-CORE-010` le porte ;
- `CAP-CORE-016` le supporte ;
- les tests de vérification et de rotation existent ;
- les paramètres sont sûrs et fermés.

Ne pas inventer un format de signature maison.

---

## 7. Contexte signé canonique

La signature porte sur un document canonique, pas sur l’empreinte nue.

Structure minimale :

```json
{
  "format": "gamad-integrity-proof",
  "version": 1,
  "preuve_reference": "PRF-GAMAD-...",
  "type_preuve": "SIGNATURE_ARTEFACT",
  "sujet_type": "SAUVEGARDE",
  "sujet_reference": "BKP-...",
  "producteur_reference": "CAP-CORE-019",
  "realm_reference": "RLM-...",
  "finalite_reference": "FINALITE-INTEGRITE",
  "source_reference": "SRC-...",
  "contrat_reference": "CTR-...",
  "algorithme_empreinte": "SHA-256",
  "empreinte": "...",
  "cree_le": "...",
  "expire_le": null
}
```

Règles :

- clés triées ;
- Unicode normalisé ;
- nombres sans ambiguïté ;
- dates UTC ISO 8601 ;
- absence de champs non définis ;
- `null` traité de manière déterministe ;
- encodage UTF-8 ;
- aucun espace variable ;
- slash non échappé si convention retenue ;
- version de format explicite.

---

## 8. Canonicalisation JSON

Créer un `Canonicaliseur` unique.

Il doit :

1. vérifier que la valeur est JSON-compatible ;
2. refuser `NAN`, `INF`, ressources ou objets non normalisés ;
3. trier récursivement les clés d’objets ;
4. préserver l’ordre des listes ;
5. normaliser les chaînes Unicode selon une convention disponible ;
6. encoder avec des options constantes ;
7. produire exactement les mêmes octets sur SQLite, PostgreSQL, CLI et HTTP ;
8. exposer sa version ;
9. posséder des vecteurs de test figés.

La canonicalisation ne doit pas dépendre de l’ordre des colonnes retournées par une base.

---

## 9. Canonicalisation des fichiers

### 9.1 Octets bruts

Pour un artefact binaire ou un dump :

```text
empreinte = hash(algorithme, octets exacts)
```

Le nom du fichier n’entre pas dans l’empreinte de contenu.

Il entre dans le manifeste.

### 9.2 Texte UTF-8 normalisé

Utiliser ce format uniquement lorsque le contrat exige une normalisation explicite.

Règles possibles :

- UTF-8 valide ;
- fin de ligne LF ;
- présence ou absence de ligne finale définie ;
- Unicode normalisé ;
- aucun retrait silencieux d’espaces métier.

Par défaut, un fichier texte est traité comme octets bruts pour éviter une modification implicite.

### 9.3 Chemins

Les chemins d’un manifeste sont :

- relatifs ;
- sans `..` ;
- sans chemin absolu ;
- sans caractère nul ;
- normalisés avec `/` ;
- uniques dans un manifeste.

---

## 10. Table `manifeste`

Champs :

- `reference` ;
- `preuve_reference` ;
- `nom` ;
- `type_manifeste` ;
- `version_format` ;
- `ordre_significatif` ;
- `membres_attendus` ;
- `taille_totale` nullable ;
- `racine_empreinte` ;
- `algorithme_racine` ;
- `cree_le`.

Types :

```text
SAUVEGARDE
RESTAURATION
VERSION_REGISTRE
PROJECTION_CONTRAT
LOT_EVENEMENTS
PAQUET_EXPORT
ARTEFACTS_CI
```

---

## 11. Table `manifeste_membre`

Champs :

- `id` ;
- `manifeste_reference` ;
- `ordre` ;
- `chemin_logique` ;
- `sujet_type` ;
- `sujet_reference` nullable ;
- `media_type` ;
- `taille_octets` ;
- `algorithme_empreinte` ;
- `empreinte` ;
- `obligatoire` ;
- `metadonnees_json` ;
- `cree_le`.

Contraintes :

- chemin unique ;
- ordre continu si significatif ;
- taille non négative ;
- empreinte valide ;
- aucun membre caché ;
- aucun glob évalué lors de la vérification ;
- la liste exacte des membres est signée.

---

## 12. Racine de manifeste

La racine est calculée à partir de la représentation canonique complète des membres.

Version initiale recommandée :

```text
SHA-256(JSON canonique du manifeste sans signature)
```

Ne pas appeler cette valeur « racine Merkle » sauf si un véritable arbre de Merkle est implémenté et testé.

La première version n’a pas besoin d’un arbre de Merkle.

La simplicité et la vérifiabilité priment.

---

## 13. Table `attestation`

Champs :

- `reference` ;
- `preuve_reference` ;
- `type_attestation` ;
- `declaration_json` ;
- `version_schema` ;
- `resultat` ;
- `periode_debut` nullable ;
- `periode_fin` nullable ;
- `emettrice_reference` ;
- `cree_le`.

Règles :

- déclaration conforme au contrat actif ;
- portée limitée ;
- résultat dans une liste close ;
- aucune phrase libre utilisée comme décision d’autorisation ;
- aucune pièce originale complète ;
- signature obligatoire pour toute attestation externe ou critique.

---

## 14. Table `checkpoint_preuve`

Champs :

- `reference` ;
- `preuve_reference` ;
- `type_checkpoint` ;
- `structure_reference` ;
- `sequence` nullable ;
- `tete_empreinte` ;
- `nombre_elements` nullable ;
- `instant_observe` ;
- `metadonnees_json` ;
- `cree_le`.

Types initiaux :

```text
JOURNAL_AUDIT
JOURNAL_EVENEMENTS
REGISTRE
SAUVEGARDE
RESTAURATION
```

Un checkpoint doit pouvoir être vérifié contre la structure réelle lorsqu’elle est disponible.

---

## 15. Table `preuve_cycle`

Champs :

- `id` ;
- `preuve_reference` ;
- `etat` ;
- `date_effet` ;
- `motif_code` ;
- `motif_detail` nullable ;
- `acteur_reference` ;
- `politique_reference` ;
- `preuve_autorisation_reference` ;
- `correlation_id` ;
- `cree_le`.

États :

```text
PREPAREE
EMISE
ACTIVE
EXPIREE
SUSPENDUE
REVOQUEE
COMPROMISE
ARCHIVEE
```

Règles :

- ajout seul ;
- une preuve compromise ne redevient jamais active ;
- révocation et expiration sont distinctes ;
- archivage ne supprime rien ;
- une empreinte pure peut être `EMISE` puis `ACTIVE` sans signature ;
- une preuve exigeant une signature ne devient `ACTIVE` qu’après signature valide.

---

## 16. Table `verification_preuve`

Champs :

- `reference` ;
- `preuve_reference` ;
- `verificateur_reference` ;
- `instant_verification` ;
- `resultat` ;
- `empreinte_presentee` nullable ;
- `signature_verifiee` nullable ;
- `cle_version_reference` nullable ;
- `etat_cle_a_signature` nullable ;
- `etat_cle_aujourdhui` nullable ;
- `divergences_json` ;
- `moteur_version` ;
- `artefact_reference` nullable ;
- `correlation_id` ;
- `cree_le`.

Résultats :

```text
VALIDE
INVALIDE
INDETERMINE
ARTEFACT_ABSENT
EMPREINTE_DIVERGENTE
SIGNATURE_INVALIDE
CLE_INCONNUE
CLE_NON_AUTORISEE
CLE_COMPROMISE
PREUVE_REVOQUEE
PREUVE_EXPIREE
CONTRAT_INACTIF
ALGORITHME_NON_SUPPORTÉ
```

Les divergences sont structurées et bornées.

Aucun dump d’artefact dans le résultat.

---

## 17. Vérification historique des clés

La vérification doit distinguer :

```text
état de la clé au moment de la signature
état de la clé au moment de la vérification
```

Exemples :

- clé retirée normalement après rotation : une ancienne signature peut rester valide ;
- clé compromise avec date d’effet antérieure : la confiance peut être invalidée ou déclarée indéterminée ;
- clé révoquée pour usage futur : les signatures antérieures restent vérifiables selon la politique ;
- clé inconnue : résultat indéterminé ou invalide, jamais valide par défaut.

La politique doit définir précisément l’effet d’une compromission datée.

---

## 18. Table `preuve_lien`

Champs :

- `id` ;
- `preuve_source_reference` ;
- `preuve_cible_reference` ;
- `type_lien` ;
- `cree_le`.

Types :

```text
DERIVE_DE
REMPLACE
CONFIRME
CONTREDIT
COMPOSE
CHECKPOINT_DE
RESTAURE_DEPUIS
```

Contraintes :

- pas d’auto-lien ;
- pas de cycle pour `DERIVE_DE` ;
- historique conservé ;
- un lien ne change pas la validité cryptographique de la preuve cible.

---

## 19. Table `paquet_preuve`

Champs :

- `reference` ;
- `preuve_reference` ;
- `format_paquet` ;
- `version_format` ;
- `empreinte_paquet` ;
- `taille_octets` ;
- `classification` ;
- `expire_le` nullable ;
- `cree_le`.

Le paquet exportable peut contenir :

- métadonnées de preuve ;
- contexte signé ;
- empreintes ;
- signature ;
- clé publique ou certificat autorisé ;
- manifeste ;
- résultat de vérification choisi ;
- références d’audit.

Il ne contient jamais :

- clé privée ;
- secret ;
- mot de passe ;
- jeton ;
- fichier métier complet non autorisé ;
- données internes hors contrat.

---

## 20. Immutabilité

Une preuve émise est immuable.

Toute correction crée :

- une nouvelle preuve ;
- un lien `REMPLACE` ;
- un état adapté sur l’ancienne ;
- une trace d’audit.

Il est interdit de modifier :

- l’empreinte ;
- la représentation ;
- la signature ;
- le producteur ;
- le realm ;
- la finalité ;
- le contrat ;
- l’instant de création.

---

## 21. Protection des données

Le schéma doit structurellement éviter :

- colonnes `secret` ;
- colonnes `private_key` ;
- payloads libres non bornés ;
- contenu binaire arbitraire ;
- chemins absolus ;
- URLs portant des credentials ;
- certificats privés ;
- variables d’environnement complètes.

Les certificats et clés publiques peuvent être conservés seulement lorsqu’ils sont nécessaires à une vérification autonome et autorisée.

---

## 22. Transactions

L’émission d’une preuve comprend au minimum :

```text
preuve
+ représentation
+ empreinte
+ cycle
+ audit
```

Pour une preuve signée :

```text
préparation transactionnelle
→ demande de signature CAP-CORE-016
→ enregistrement de la signature
→ vérification immédiate
→ activation
→ audit
```

Le design doit gérer l’impossibilité d’une transaction distribuée avec le fournisseur de clé.

Utiliser un état `PREPAREE` et une reprise idempotente.

Ne jamais marquer la preuve active avant d’avoir persisté et vérifié la signature.

---

## 23. Algorithme d’émission signée

Séquence recommandée :

1. valider le dossier gouverné ;
2. créer la preuve `PREPAREE` ;
3. canonicaliser ;
4. calculer l’empreinte ;
5. construire le contexte signé ;
6. calculer son empreinte ;
7. demander à `CAP-CORE-016` une signature avec idempotency key ;
8. recevoir signature et référence d’opération ;
9. vérifier immédiatement avec la clé publique ;
10. enregistrer la signature ;
11. passer `EMISE` puis `ACTIVE` ;
12. écrire l’audit ;
13. publier un événement minimal après commit.

En cas de panne après étape 7 :

- reprendre avec la même idempotency key ;
- ne pas produire une seconde signature logique ;
- rapprocher l’opération déjà exécutée ;
- ne jamais perdre la preuve préparée.

---

## 24. Limites initiales

Définir des bornes sûres :

- contenu inline maximal ;
- nombre maximal de membres d’un manifeste ;
- profondeur maximale de liens ;
- taille maximale des métadonnées ;
- nombre maximal d’empreintes par preuve ;
- nombre maximal de signatures par preuve ;
- taille maximale d’un paquet exportable ;
- durée maximale d’une requête de vérification publique ;
- volume maximal d’un lot de vérification.

Aucune valeur de configuration ne doit pouvoir désactiver les limites en production.
