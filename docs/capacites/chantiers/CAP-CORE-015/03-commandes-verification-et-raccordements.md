# CAP-CORE-015 — INTEGRITY PROOFS
# PARTIE 3/5 — COMMANDES, VÉRIFICATION ET RACCORDEMENTS

---

## 1. Politique d’administration

Créer :

```text
POL-PREUVES-V1
```

Actions minimales :

```text
preuve.lire
preuve.preparer
preuve.empreinte.emettre
preuve.signature.emettre
preuve.attestation.emettre
preuve.manifeste.emettre
preuve.checkpoint.emettre
preuve.verifier
preuve.lot.verifier
preuve.revoquer
preuve.suspendre
preuve.compromission.declarer
preuve.paquet.exporter
preuve.bootstrap.executer
preuve.diagnostic.lire
```

Règles :

- refus par défaut ;
- lecture bornée par realm, finalité, classification et contrat ;
- création réservée aux producteurs déclarés ;
- signature réservée aux usages autorisés par `CAP-CORE-016` ;
- révocation réservée au propriétaire ou à l’autorité compétente ;
- compromission soumise à un niveau d’assurance élevé ;
- export limité aux champs autorisés ;
- aucune action n’expose une clé privée.

---

## 2. Dossier gouverné commun

Toute commande d’écriture exige :

- `acteur_reference` ;
- `politique_reference` ;
- `decision_reference` ou preuve d’autorisation existante ;
- `source_reference` ;
- `realm_reference` ;
- `finalite_reference` ;
- `contrat_reference` lorsque l’usage est externe ;
- `contrat_version` lorsque pertinent ;
- `correlation_id` ;
- `idempotency_key` ;
- `motif` borné pour les opérations sensibles.

Une preuve n’est jamais émise parce qu’un simple booléen `autorise=true` a été envoyé.

---

## 3. Commande `preparerPreuve`

Entrées :

- type de preuve ;
- sujet ;
- producteur ;
- représentation ;
- classification ;
- expiration éventuelle ;
- dossier gouverné.

Effets :

- crée la référence ;
- valide les dépendances ;
- enregistre la représentation ;
- passe à `PREPAREE` ;
- ne signe rien ;
- n’active rien.

Refus :

- producteur inconnu ;
- realm inactif ;
- source inutilisable ;
- finalité absente ;
- contrat absent pour un échange externe ;
- représentation non déterministe ;
- contenu interdit ;
- taille hors limite ;
- idempotency key contradictoire.

---

## 4. Commande `emettreEmpreinte`

Entrées :

- preuve préparée ;
- algorithme ;
- artefact ou contenu autorisé ;
- dossier gouverné.

Règles :

- preuve en état `PREPAREE` ;
- algorithme autorisé ;
- représentation conforme ;
- calcul en flux pour les gros fichiers ;
- aucune lecture de chemin arbitraire ;
- taille et nombre d’octets observés enregistrés ;
- comparaison avec valeur attendue seulement si explicitement demandée ;
- résultat immuable.

Effets :

- enregistre l’empreinte ;
- passe `EMISE` ;
- peut passer `ACTIVE` si le type ne requiert pas de signature ;
- audit obligatoire.

---

## 5. Commande `emettreSignature`

Entrées :

- preuve émise ;
- clé et version de clé ;
- algorithme ;
- expiration éventuelle ;
- dossier gouverné.

Règles :

- clé connue de `CAP-CORE-016` ;
- version compatible avec realm, environnement, producteur, usage et finalité ;
- permission `SIGNER` ;
- preuve non expirée ;
- contexte canonique reconstruit côté serveur ;
- aucune donnée signée fournie librement par le client ;
- demande de signature idempotente ;
- vérification immédiate de la signature produite ;
- aucune activation si vérification immédiate échoue.

Effets :

- signature persistée ;
- référence d’opération cryptographique persistée ;
- état `ACTIVE` ;
- audit ;
- événement minimal après commit.

---

## 6. Commande `emettreAttestation`

Entrées :

- type d’attestation ;
- déclaration structurée ;
- schéma et version ;
- résultat ;
- période éventuelle ;
- clé de signature si requise ;
- dossier gouverné.

Règles :

- contrat actif ;
- déclaration conforme au schéma ;
- aucun champ supplémentaire ;
- producteur déclaré ;
- finalité exacte ;
- signature obligatoire pour attestation externe, sensible ou critique ;
- pas de texte juridique inventé ;
- pas de conclusion plus large que les données fournies.

---

## 7. Commande `emettreManifeste`

Entrées :

- type de manifeste ;
- liste complète des membres ;
- ordre significatif ou non ;
- algorithme ;
- clé éventuelle ;
- dossier gouverné.

Règles :

- membre unique par chemin logique ;
- aucun chemin absolu ;
- aucun `..` ;
- empreinte et taille obligatoires ;
- liste fermée au moment de l’émission ;
- racine calculée côté serveur ;
- signature obligatoire pour sauvegarde hors machine, restauration de production, export externe ou paquet de release ;
- aucun fichier ajouté implicitement par glob après signature.

---

## 8. Commande `emettreCheckpoint`

Cas d’usage initiaux :

```text
CAP-CORE-013 — tête du journal d’audit
CAP-CORE-014 — séquence et tête du journal d’événements
CAP-CORE-019 — manifeste du dernier lot sauvegardé
registre — version ou état contrôlé
```

Entrées :

- type ;
- structure ;
- séquence ;
- tête ;
- nombre d’éléments ;
- instant ;
- clé si signature requise ;
- dossier gouverné.

Règles :

- structure réellement lisible ;
- tête recalculée ou récupérée par interface interne autorisée ;
- aucune valeur fournie sans contrôle ;
- checkpoint immuable ;
- périodicité bornée ;
- signature pour checkpoint exporté ou utilisé hors du processus local.

---

## 9. Commande `verifierPreuve`

Modes :

```text
METADONNEES_SEULES
ARTEFACT_PRESENTE
PAQUET_PREUVE
STRUCTURE_ACTIVE
```

Vérifications minimales :

1. existence de la preuve ;
2. visibilité pour le vérificateur ;
3. état courant ;
4. expiration ;
5. contrat et finalité ;
6. canonicalisation ;
7. empreinte de l’artefact présenté ;
8. signature ;
9. clé et version de clé ;
10. autorisation de la clé à l’instant de signature ;
11. compromission connue ;
12. manifeste et membres ;
13. liens de remplacement ;
14. cohérence du contexte signé.

Le résultat doit distinguer :

```text
preuve cryptographiquement valide
preuve actuellement utilisable
preuve historiquement valide mais aujourd’hui retirée
preuve indéterminée faute de donnée
```

Ne jamais réduire ces quatre notions à un seul booléen.

---

## 10. Commande `verifierLot`

Entrées :

- liste bornée de preuves ;
- ou manifeste ;
- mode de vérification ;
- dossier gouverné.

Règles :

- taille maximale ;
- temps maximal ;
- pas de vérification synchrone illimitée ;
- résultats individuels conservés ;
- résumé final ;
- aucun succès global si un membre obligatoire échoue ;
- membres facultatifs signalés séparément.

---

## 11. Commande `suspendrePreuve`

Utilisation :

- doute temporaire ;
- dépendance indisponible ;
- enquête ;
- anomalie non encore qualifiée.

Règles :

- motif codé ;
- durée éventuelle ;
- acteur autorisé ;
- audit ;
- événement minimal ;
- aucune suppression.

La suspension ne modifie pas la validité mathématique de la signature. Elle modifie l’utilisabilité de la preuve.

---

## 12. Commande `revoquerPreuve`

Règles :

- preuve existante ;
- motif ;
- date d’effet ;
- autorité compétente ;
- impact calculé ;
- liens vers preuves de remplacement éventuelles ;
- audit ;
- événement minimal.

Une preuve révoquée ne redevient jamais active.

---

## 13. Commande `declarerCompromission`

La compromission peut concerner :

- une preuve ;
- une clé ;
- un fournisseur ;
- un processus de signature ;
- une chaîne ou un manifeste.

Règles :

- date présumée de début ;
- date de découverte ;
- motif structuré ;
- preuve ou incident source ;
- niveau d’assurance élevé ;
- calcul des preuves affectées ;
- marquage sans réécriture ;
- événement minimal ;
- préparation du futur raccordement à `CAP-CORE-018`.

Le registre ne doit pas inventer que toutes les signatures anciennes sont invalides. La politique précise l’effet temporel.

---

## 14. Commande `exporterPaquetPreuve`

Entrées :

- preuve ;
- profil d’export ;
- destinataire ou contrat ;
- expiration ;
- dossier gouverné.

Profils initiaux :

```text
VERIFICATION_INTERNE
VERIFICATION_SATELLITE
PREUVE_SAUVEGARDE
PREUVE_RESTAURATION
CONFORMITE_CONTRAT
```

Règles :

- minimisation ;
- classification ;
- contrat actif ;
- aucune clé privée ;
- aucune donnée métier non nécessaire ;
- empreinte du paquet ;
- signature du paquet si externe ;
- format versionné ;
- paquet immuable ;
- expiration éventuelle.

---

## 15. Requêtes

Créer au minimum :

```text
resoudrePreuve(reference)
listerPreuves(filtres)
resoudreEtat(reference, instant)
resoudreEmpreintes(reference)
resoudreSignatures(reference)
resoudreManifeste(reference)
resoudreAttestation(reference)
resoudreCheckpoint(reference)
resoudreVerifications(reference)
resoudreLiens(reference)
resoudreImpactCle(cle_version_reference)
resoudrePreuvesAffectees(compromission)
diagnostiquerRegistre()
```

Toutes les lectures appliquent :

- autorisation ;
- realm ;
- finalité ;
- classification ;
- contrat ;
- minimisation.

---

## 16. Bootstrap initial

Créer :

```text
core/registre-preuves/resources/bootstrap-preuves-v1.json
```

Le bootstrap ne doit pas importer toutes les empreintes historiques comme si elles étaient des preuves signées.

Il doit inventorier et reprendre seulement les références utiles et vérifiables.

Candidats :

- baseline opérationnelle courante ;
- bootstrap produits ;
- bootstrap sources ;
- bootstrap politiques ;
- bootstrap contrats ;
- dernier manifeste de sauvegarde réel disponible lors de l’exercice ;
- checkpoint initial du journal opérationnel ;
- projections de contrats critiques.

Pour chaque élément :

- type réel ;
- empreinte réelle ;
- algorithme ;
- source ;
- producteur ;
- realm ;
- finalité ;
- état ;
- mention explicite `signature_absente` si non signé.

Le bootstrap est :

- versionné ;
- empreinté ;
- idempotent ;
- transactionnel ;
- vérifié avant écriture ;
- sans secret ;
- sans fausse rétroactivité.

Ne pas dater une preuve historique comme si `CAP-CORE-015` l’avait émise à l’époque.

Utiliser :

- `observee_le` pour le constat actuel ;
- `artefact_cree_le` seulement si prouvé ;
- aucune signature rétroactive présentée comme historique.

---

## 17. Raccordement CAP-CORE-013

`CAP-CORE-013` reste propriétaire du journal d’audit.

Raccordement :

- créer périodiquement ou à la demande un checkpoint de sa tête ;
- signer le checkpoint via `CAP-CORE-016` ;
- conserver la preuve dans `CAP-CORE-015` ;
- ne pas recopier tous les événements d’audit ;
- ne pas modifier la chaîne existante sans migration démontrée.

Éviter la boucle :

```text
émission de preuve
→ audit
→ checkpoint
→ émission de preuve
→ audit infini
```

Les checkpoints doivent être planifiés, bornés ou déclenchés explicitement, jamais créés pour chaque audit de preuve.

---

## 18. Raccordement CAP-CORE-014

`CAP-CORE-014` peut publier des événements minimaux :

```text
PREUVE_EMISE
PREUVE_REVOQUEE
PREUVE_COMPROMISE
MANIFESTE_EMIS
VERIFICATION_ECHOUEE_CRITIQUE
CHECKPOINT_EMIS
```

Payload minimal :

- preuve ;
- type ;
- sujet ;
- realm ;
- résultat ou état ;
- version de contrat ;
- aucune signature brute si inutile ;
- aucun artefact ;
- aucun secret.

Les événements ne remplacent pas le registre de preuves.

---

## 19. Raccordement CAP-CORE-016

`CAP-CORE-015` ne lit jamais directement une clé privée.

Interface attendue, à adapter au code réel :

```text
demanderSignature(
  cle_version_reference,
  usage,
  finalite,
  realm,
  environnement,
  empreinte_contexte,
  idempotency_key
)
```

Réponse attendue :

- signature ;
- algorithme ;
- clé ;
- version ;
- fournisseur ;
- opération ;
- instant ;
- clé publique ou référence de vérification.

Le fournisseur signe le digest ou contexte selon une convention unique documentée.

Aucune capacité ne doit signer directement avec `file_get_contents('/secret/key')` en contournant `CAP-CORE-016`.

---

## 20. Raccordement CAP-CORE-019

Premier scénario production obligatoire.

### 20.1 Sauvegarde

Après création des dumps et `SHA256SUMS` :

1. construire un manifeste avec les huit ou futurs magasins réels ;
2. inclure noms logiques, tailles et empreintes ;
3. inclure la version du format ;
4. signer le manifeste ;
5. conserver la preuve ;
6. transporter archive, somme et paquet de preuve ;
7. ne jamais inclure la clé de déchiffrement.

### 20.2 Copie hors machine

Avant transport :

- vérifier le manifeste ;
- vérifier les fichiers ;
- chiffrer ;
- empreinter l’archive chiffrée ;
- ajouter une preuve de transport minimale ;
- ne pas prétendre que le transport prouve la restauration.

### 20.3 Restauration

Après exercice :

- vérifier le manifeste d’origine ;
- enregistrer les résultats de chaque dump ;
- créer une attestation de restauration ;
- signer l’attestation ;
- lier `RESTAURE_DEPUIS` ;
- conserver les compteurs et contrôles ;
- ne pas inclure de données restaurées sensibles.

`CAP-CORE-019` reste propriétaire de l’exercice. `CAP-CORE-015` fournit la preuve.

---

## 21. Raccordement CAP-CORE-009

Raccorder progressivement :

- empreinte de version de contrat ;
- projection OpenAPI ;
- conformité ;
- rapport de compatibilité ;
- paquet de contrat exportable.

Une conformité peut référencer une preuve `CAP-CORE-015`.

Ne pas modifier brutalement le format d’empreinte déjà utilisé par `CAP-CORE-009`.

Prévoir :

- coexistence ;
- migration ;
- comparaison ;
- preuve de remplacement.

---

## 22. Raccordement des bootstraps

Les registres `006`, `007`, `009`, `010`, `011`, `002`, `012`, `014` et `016` peuvent exposer leur bootstrap courant à `CAP-CORE-015`.

Règles :

- l’empreinte locale reste la première garde de chargement ;
- `CAP-CORE-015` ajoute une preuve transversale ;
- la capacité propriétaire reste responsable du format ;
- aucune capacité ne dépend du registre de preuves pour démarrer sa propre migration initiale si cela crée un cycle de bootstrap ;
- le raccordement se fait après démarrage, par diagnostic ou preuve de release.

---

## 23. Raccordement CI et releases

Créer une commande :

```text
php artisan core:preuves:manifeste-release
```

Elle produit un manifeste borné des artefacts pertinents :

- commit ;
- migrations ;
- OpenAPI ;
- bootstraps ;
- gardes ;
- versions de schémas ;
- résultats de tests choisis.

La CI peut vérifier le manifeste, mais une signature de production ne doit pas utiliser une clé privée disponible aux pull requests non approuvées.

Séparer :

```text
preuve CI non signée ou signée par clé CI dédiée
preuve de release signée par clé de release
preuve de production signée par clé de production
```

---

## 24. Raccordement readiness

La readiness vérifie :

- magasin accessible ;
- schéma courant ;
- algorithmes disponibles ;
- `CAP-CORE-016` accessible pour les opérations requises ;
- clé de signature active lorsque la production l’exige ;
- dernière preuve critique valide ;
- absence de migration en attente ;
- absence de corruption du registre ;
- délai du dernier checkpoint critique.

Ne pas rendre toute l’application indisponible parce qu’une preuve non critique a expiré.

Classer les dépendances critiques.

---

## 25. Commandes Artisan

Créer au minimum :

```text
core:preuves:bootstrap
core:preuves:diagnostiquer
core:preuves:empreinter
core:preuves:verifier
core:preuves:checkpoint-journal
core:preuves:manifeste-sauvegarde
core:preuves:attester-restauration
core:preuves:manifeste-release
core:preuves:reconcilier-signatures
```

Règles CLI :

- aucune clé privée affichée ;
- aucun secret en argument ;
- chemins limités à des répertoires autorisés ;
- mode `--dry-run` pour diagnostics ;
- JSON optionnel pour automatisation ;
- codes de sortie stables ;
- confirmation pour révocation ou compromission ;
- aucune écriture en production sans politique et acteur explicites.
