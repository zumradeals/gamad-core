# CAP-CORE-014 — COMMANDES, ROUTAGE ET CONSOMMATION

Cette partie complète les deux premières parties.

---

## 1. Audit préparatoire obligatoire

Avant tout code, inventorier les événements existants.

Rechercher notamment :

```bash
rg -n --hidden \
  --glob '!.git/**' \
  --glob '!vendor/**' \
  --glob '!node_modules/**' \
  "Journal::|->enregistrer\(|type.*=>|_ACTIVE|_SUSPENDU|_RETIREE|_REVOQUE"
```

Pour chaque événement actuel, relever :

- catégorie d’audit ;
- type ;
- producteur ;
- action métier ;
- ressource ;
- charge actuelle ;
- sensibilité ;
- corrélation ;
- existence d’un contrat d’événement ;
- consommateurs réels ;
- publication souhaitable ou non ;
- realm applicable ;
- finalité ;
- données minimales nécessaires.

Classer chaque type dans l’une des catégories :

```text
AUDIT_PRIVE_UNIQUEMENT
EVENEMENT_COMMUN_CANDIDAT
EVENEMENT_COMMUN_CONTRACTUALISE
INTERDIT_DE_PUBLICATION
```

Ne pas publier un événement simplement parce que son nom semble utile.

---

## 2. Bootstrap initial

Créer une ressource technique versionnée :

```text
core/journal-evenements/resources/bootstrap-evenements-v1.json
```

Elle doit contenir uniquement :

- références des familles d’événements retenues ;
- références des contrats actifs correspondants ;
- versions ;
- producteurs ;
- sources ;
- realms par défaut seulement lorsqu’ils sont réellement connus ;
- finalités ;
- classification ;
- politique de conservation ;
- règles de routage minimales ;
- abonnements pilotes explicitement prouvés.

Exigences :

- empreinte SHA-256 fixée ;
- validation du schéma ;
- bootstrap idempotent ;
- aucun événement historique inventé ;
- aucun consommateur inventé ;
- aucun abonnement universel ;
- aucune charge métier dans le fichier ;
- refus si un contrat ou vocabulaire requis est absent ;
- transaction unique dans le magasin central.

### Historique existant

Ne pas recopier automatiquement les lignes de `CAP-CORE-013` dans le journal commun.

Ces lignes n’ont pas été produites sous les contrats, finalités et règles de diffusion de `CAP-CORE-014`.

Le journal commun commence au déploiement effectif de la capacité.

Lorsqu’un consommateur a besoin d’un état initial, produire un événement de photographie explicite :

```text
ETAT_INITIAL_PRODUIT
ETAT_INITIAL_SOURCE
ETAT_INITIAL_REALM
```

avec :

```text
reconstruction = true
```

et un contrat spécifique.

Ne jamais présenter cette photographie comme le fait historique original.

---

## 3. Commande productrice `preparerEvenement`

Cette commande est appelée dans la transaction métier du producteur.

Entrées minimales :

- type d’événement ;
- contrat et version ;
- producteur ;
- source ;
- realm ;
- finalité ;
- sujet ;
- corrélation ;
- cause éventuelle ;
- date du fait ;
- classification ;
- charge utile ;
- référence d’idempotence.

Règles :

- événement au passé ;
- contrat actif ;
- type conforme au contrat ;
- producteur déclaré ;
- source active ;
- realm actif ;
- finalité explicite ;
- charge validée ;
- aucune permission déduite du seul contrat ;
- aucune publication réseau dans la transaction métier ;
- insertion de l’outbox dans le même magasin et la même transaction ;
- référence d’idempotence obligatoire ;
- retour de la référence logique d’outbox, pas encore de référence du journal central.

En cas d’échec de validation, la commande métier doit échouer avant commit si la publication de cet événement est un invariant obligatoire de l’opération.

Pour les événements non critiques, le caractère obligatoire ou facultatif doit être explicitement défini dans le contrat et le code.

Aucun comportement implicite.

---

## 4. Relais `publierOutbox`

Le relais lit les lignes prêtes et les transmet au journal central.

Étapes :

1. sélectionner un lot avec verrouillage concurrent ;
2. marquer les lignes `EN_COURS` avec bail court ;
3. revalider l’enveloppe et l’empreinte ;
4. envoyer au `RegistreEvenements` ;
5. recevoir une référence et une séquence ;
6. marquer l’outbox `PUBLIE` ;
7. écrire une trace d’audit ;
8. libérer le lot.

Règles :

- `SKIP LOCKED` en PostgreSQL lorsque pertinent ;
- équivalent sûr en SQLite de test ;
- lots bornés ;
- délai exponentiel avec plafond ;
- aucune boucle infinie ;
- erreurs classées temporaires ou définitives ;
- un contrat retiré avant publication produit un échec explicite ;
- une source ou un realm suspendu produit un échec explicite selon contrat ;
- aucune perte silencieuse ;
- le rejeu d’une ligne déjà acceptée retourne le reçu existant ;
- un crash après acceptation centrale mais avant mise à jour de l’outbox ne crée pas de doublon grâce à l’idempotence.

Commande Laravel proposée :

```text
php artisan core:evenements:publier --limite=100
```

Prévoir un worker ou timer systemd exécutant cette commande.

---

## 5. Commande centrale `accepterEvenement`

Cette commande n’est pas une API publique générale.

Elle est appelée par les relais de producteurs authentifiés.

Règles :

- authentifier le producteur ;
- vérifier son identité contractuelle ;
- vérifier `CAP-CORE-004` ;
- vérifier contrat, version et schéma ;
- vérifier la référence d’idempotence ;
- vérifier source, realm et finalité ;
- expurger ou refuser tout champ interdit ;
- calculer la charge canonique ;
- vérifier son empreinte ;
- insérer enveloppe et charge dans une transaction ;
- attribuer la séquence ;
- créer les livraisons correspondant aux abonnements actifs ;
- écrire la trace d’audit ;
- retourner le reçu.

Si l’audit indispensable échoue, la transaction centrale doit revenir en arrière.

Si aucune souscription ne correspond, l’événement reste néanmoins dans le journal commun si son contrat autorise sa publication.

Un événement n’est pas perdu parce qu’aucun consommateur n’est actuellement actif.

---

## 6. Routage

Le routeur évalue uniquement des critères explicites.

Pour qu’un abonnement reçoive un événement, toutes les conditions suivantes doivent être vraies :

1. abonnement `ACTIF` ;
2. type d’événement déclaré ;
3. contrat/version compatible ;
4. consommateur déclaré dans le contrat ;
5. producteur autorisé par le filtre ;
6. realm exact ou franchissement explicitement autorisé ;
7. finalité compatible ;
8. classification lisible par le consommateur ;
9. période de validité de l’abonnement ;
10. décision `CAP-CORE-004` favorable.

L’absence d’une condition vaut non-routage.

### Interdictions de routage

Il est interdit de :

- router par ressemblance de texte ;
- router par préfixe non déclaré ;
- considérer un realm parent comme omniscient ;
- utiliser `null` pour signifier « tous les realms » ;
- utiliser `null` pour signifier « tous les types » ;
- router selon un champ de payload non contractualisé ;
- exécuter une expression fournie par l’administrateur ;
- utiliser une requête SQL libre comme filtre.

### Évolution future

Le filtrage avancé sur des champs du payload pourra être ajouté dans une version ultérieure du contrat, avec opérateurs fermés et tests de sécurité.

Il ne doit pas être introduit de manière improvisée dans la première version.

---

## 7. Commandes d’abonnement

### 7.1 `creerAbonnement`

Entrées :

- référence ;
- nom ;
- consommateur ;
- organisation exploitante éventuelle ;
- realm ;
- finalité ;
- mode ;
- paramètres bornés ;
- source ;
- acteur ;
- politique ;
- preuve ;
- corrélation.

Règles :

- création en `PREPARATION` ;
- consommateur actif ;
- contrat actif ;
- aucune activation automatique ;
- aucun type implicite ;
- aucune portée universelle ;
- audit obligatoire.

### 7.2 `ajouterTypeAbonnement`

Règles :

- abonnement en préparation ;
- contrat d’événement actif ;
- consommateur déclaré ;
- type canonique ;
- version compatible ;
- aucun doublon ;
- audit obligatoire.

### 7.3 `ajouterProducteurAbonnement`

Règles :

- producteur déclaré dans le contrat ;
- producteur actif ;
- aucune valeur joker ;
- audit obligatoire.

### 7.4 `ajouterRealmAbonnement`

Règles :

- realm actif ;
- franchissement autorisé ;
- descendants explicites ;
- aucune propagation automatique ;
- audit obligatoire.

### 7.5 `activerAbonnement`

Règles :

- au moins un type ;
- au moins un producteur ;
- realm valide ;
- finalité valide ;
- contrat actif ;
- décision d’autorisation ;
- preuve ;
- audit ;
- activation idempotente.

### 7.6 `suspendreAbonnement`

Règles :

- arrêt immédiat des nouveaux baux ;
- aucune suppression ;
- livraisons déjà accusées conservées ;
- bail en cours laissé expirer ou annulé explicitement selon politique ;
- audit obligatoire.

### 7.7 `retirerAbonnement`

Règles :

- irréversible ;
- aucune nouvelle livraison ;
- historique conservé ;
- aucune réutilisation de la référence ;
- audit obligatoire.

---

## 8. Lecture PULL

### 8.1 `obtenirLivraisons`

Le consommateur demande un lot pour son abonnement.

Entrées :

- abonnement ;
- limite ;
- durée de bail demandée ;
- curseur facultatif ;
- session ;
- corrélation.

Règles :

- abonnement actif ;
- consommateur propriétaire de l’abonnement ;
- décision `CAP-CORE-004` ;
- limite plafonnée ;
- bail plafonné ;
- sélection ordonnée par séquence ;
- verrouillage concurrent ;
- attribution d’un bail opaque ;
- aucune livraison déjà sous bail non expiré ;
- aucune charge expirée ;
- aucun événement hors realm ;
- aucune donnée supplémentaire au contrat.

Réponse minimale :

```json
{
  "abonnement": "ABN-...",
  "bail": "BAIL-...",
  "expire_le": "...",
  "livraisons": [
    {
      "livraison": "LIV-...",
      "evenement": {"reference": "EVT-..."},
      "charge": {}
    }
  ]
}
```

Le bail n’est pas un secret durable et ne doit jamais donner accès à un autre abonnement.

### 8.2 Accès à la charge

La charge est retournée seulement si :

- elle n’est pas expirée ;
- le consommateur est autorisé ;
- le contrat et la finalité sont encore lisibles ;
- la classification le permet.

Sinon, retourner l’enveloppe et un motif explicite, sans inventer une charge vide présentée comme complète.

---

## 9. Accusé de réception

### 9.1 `accuserLivraisons`

Entrées :

- abonnement ;
- bail ;
- liste de livraisons ;
- résultat du traitement ;
- corrélation.

Règles :

- bail valide ;
- livraison appartenant au bail et à l’abonnement ;
- accusé idempotent ;
- un second accusé identique réussit sans doublon ;
- un accusé contradictoire est refusé ;
- état terminal `ACCUSE` ;
- tentative ajoutée ;
- curseur recalculé sur la suite contiguë ;
- audit minimal sans payload.

Un accusé signifie :

> le consommateur a accepté la responsabilité de traiter cet événement selon son contrat.

Il ne prouve pas que toutes les conséquences métier ont réussi, sauf contrat explicite distinct.

---

## 10. Refus et nouvelle tentative

### 10.1 `refuserTemporairement`

Entrées :

- livraison ;
- bail ;
- code d’erreur canonique ;
- délai souhaité borné ;
- corrélation.

Règles :

- erreur retentable selon contrat ;
- prochaine tentative calculée par le Core ;
- délai consommateur plafonné ;
- nombre de tentatives incrémenté ;
- état `A_REESSAYER` ;
- passage en lettre morte après plafond ;
- aucun détail sensible.

### 10.2 `refuserDefinitivement`

Règles :

- code d’erreur non retentable ;
- justification obligatoire ;
- lettre morte immédiate ou traitement terminal défini par contrat ;
- audit ;
- aucune suppression.

Le consommateur ne peut pas déclarer arbitrairement qu’un événement contractuellement valide n’existe pas.

Le diagnostic doit distinguer :

- contrat inconnu ;
- version incompatible ;
- payload invalide ;
- dépendance indisponible ;
- erreur métier définitive ;
- erreur interne du consommateur.

---

## 11. Expiration des baux

Créer un traitement périodique :

```text
php artisan core:evenements:liberer-baux
```

Il doit :

- trouver les baux expirés ;
- ajouter une tentative `BAIL_EXPIRE` ;
- remettre la livraison à disposition ou en attente de nouvelle tentative ;
- respecter le plafond ;
- envoyer en lettre morte lorsque nécessaire ;
- rester idempotent ;
- produire des métriques.

Un crash consommateur ne doit jamais bloquer définitivement une livraison.

---

## 12. Rejeu

### 12.1 `demanderRejeu`

Règles :

- abonnement connu ;
- demandeur autorisé ;
- bornes explicites ;
- types explicites ;
- volume estimé ;
- contrat toujours accessible ;
- charge encore conservée ;
- realm autorisé ;
- preuve et motif ;
- création en `DEMANDEE`.

### 12.2 `validerRejeu`

Règles :

- autorité ou propriétaire selon politique ;
- impact affiché ;
- volume plafonné ;
- refus si la demande contourne la rétention ;
- refus si elle élargit l’abonnement ;
- audit obligatoire.

### 12.3 `executerRejeu`

Règles :

- demande validée ;
- lots bornés ;
- nouvelles livraisons marquées `REJEU` ;
- événement original inchangé ;
- aucune duplication non maîtrisée ;
- possibilité pour le consommateur de dédupliquer sur `evenement.reference` ;
- progression persistante ;
- reprise après crash.

Le rejeu ne crée jamais un nouvel événement métier.

---

## 13. Lettres mortes

### 13.1 Passage automatique

Une livraison passe en lettre morte lorsque :

- plafond de tentatives atteint ;
- erreur définitive ;
- contrat devenu incompatible ;
- payload définitivement invalide ;
- consommateur retiré pendant traitement selon règle explicite.

### 13.2 `relancerLettreMorte`

Règles :

- autorisation explicite ;
- cause corrigée ou motif documenté ;
- contrat encore valide ;
- charge encore disponible ;
- création d’une nouvelle tentative ou livraison liée à l’originale ;
- aucune mutation effaçant l’échec précédent ;
- audit obligatoire.

### 13.3 `cloreLettreMorte`

Commande facultative selon modèle retenu.

La clôture ne supprime rien et doit préciser :

- raison ;
- décision ;
- responsable ;
- preuve ;
- conséquence acceptée.

Cette décision opérationnelle devra plus tard pouvoir être reliée à `CAP-CORE-008`.

---

## 14. Rapprochement avec CAP-CORE-013

Chaque publication doit produire dans le journal d’audit :

- référence d’outbox ;
- référence d’événement commun ;
- producteur ;
- type ;
- contrat/version ;
- realm ;
- résultat ;
- corrélation ;
- empreinte de charge ;
- aucune charge utile.

Chaque consommation importante doit produire :

- abonnement ;
- livraison ;
- événement ;
- résultat ;
- code d’erreur éventuel ;
- corrélation ;
- aucune charge utile.

Le rapprochement doit permettre :

```text
commande métier
→ preuve d’autorisation
→ mutation métier
→ outbox
→ publication commune
→ livraison
→ accusé ou échec
```

sans fusionner les deux journaux.

---

## 15. Raccordement des capacités productrices

Raccorder progressivement mais dans la même PR les capacités minimales définies dans la partie 1.

Pour chacune :

1. identifier la transaction métier réelle ;
2. ajouter la migration d’outbox ;
3. préparer l’événement dans cette transaction ;
4. enregistrer la corrélation ;
5. créer le contrat d’événement dans `CAP-CORE-009` ;
6. ajouter le type dans `CAP-CORE-010` ;
7. ajouter tests et contre-épreuves ;
8. vérifier qu’une panne du journal central ne bloque pas la transaction métier après création réussie de l’outbox ;
9. vérifier que l’outbox retardée finit par être publiée ;
10. vérifier qu’un rejeu n’ajoute pas un doublon.

Ne pas modifier tous les événements d’audit existants.

Ajouter uniquement les événements communs sélectionnés.

---

## 16. Consommateur pilote obligatoire

Un `GO` sans consommateur réel de test serait insuffisant.

Créer un consommateur pilote lié à un produit déjà reconnu, idéalement GamaDrive si son environnement de test et son contrat sont disponibles.

Le pilote doit prouver :

- création et activation d’un abonnement ;
- lecture authentifiée ;
- réception d’un événement réel ;
- déduplication locale ;
- accusé de réception ;
- reprise après interruption ;
- refus temporaire ;
- nouvelle tentative ;
- lettre morte ;
- rejeu borné ;
- filtrage par realm.

Si aucun satellite déployé n’est disponible, créer un consommateur de conformité dans le dépôt, clairement identifié comme pilote technique et non comme intégration d’exploitation réelle.

Ne pas déclarer un satellite raccordé en production sans preuve.

---

## 17. Requêtes minimales

Implémenter :

- `resoudreEvenement(reference)` ;
- `listerEvenements(filtresAutorises)` ;
- `resoudreCharge(reference, consommateur)` ;
- `resoudrePublication(producteur, idempotence)` ;
- `listerOutboxEnRetard(producteur?)` ;
- `resoudreAbonnement(reference)` ;
- `listerAbonnements(consommateur?)` ;
- `listerLivraisons(abonnement, etat?)` ;
- `resoudreRetard(abonnement)` ;
- `resoudreCurseur(abonnement)` ;
- `listerLettresMortes(abonnement?)` ;
- `resoudreDemandeRejeu(reference)` ;
- `diagnostiquerJournal()`.

`diagnostiquerJournal()` doit vérifier au minimum :

- chaîne d’empreintes ;
- événements sans charge alors qu’elle devrait exister ;
- empreinte de charge divergente ;
- livraisons orphelines ;
- abonnements actifs sans type ;
- baux expirés non libérés ;
- lettres mortes ;
- curseurs incohérents ;
- outboxes en retard ;
- contrats retirés encore routés ;
- franchissements de realm non autorisés.

---

## 18. Purge gouvernée

Commande proposée :

```text
php artisan core:evenements:purger-charges --avant=YYYY-MM-DD
```

Règles :

- simulation par défaut ;
- option explicite `--force` ;
- politique de conservation du contrat ;
- aucune charge encore nécessaire à une livraison ou un rejeu actif ;
- enveloppe et empreinte conservées ;
- audit ;
- rapport du nombre de charges purgées ;
- contre-épreuve empêchant la purge trop précoce.

Ne pas purger les enveloppes dans ce chantier.

---

## 19. Comportement en panne

### Magasin producteur indisponible

- aucune transaction métier ;
- aucune publication inventée.

### Journal central indisponible

- la transaction métier déjà commitée reste valide si l’outbox existe ;
- l’outbox passe en retard ;
- le relais réessaie ;
- readiness dégradée selon seuil ;
- alerte visible.

### Registre des contrats indisponible

- nouvelle publication refusée ou retardée ;
- aucune validation supposée ;
- événements déjà publiés restent lisibles selon métadonnées persistées.

### Autorisation indisponible

- nouvelles lectures et commandes refusées ;
- workers internes appliquent la politique de panne définie, sans élargir les droits.

### Realm indisponible

- nouveau routage inter-realm refusé ;
- aucune propagation implicite.

### Consommateur indisponible

- livraisons restent disponibles ;
- retard augmente ;
- aucune perte ;
- lettre morte selon politique.

### Audit indisponible pendant une commande centrale sensible

- rollback de la commande centrale ;
- outbox productrice reste rejouable.

---

## 20. Interdictions

Il est interdit de :

- utiliser le journal d’audit comme file de messages ;
- publier chaque audit par défaut ;
- écrire directement dans la base d’un satellite ;
- supposer une garantie exactement une fois ;
- supprimer un événement publié ;
- modifier une charge publiée ;
- publier sans contrat actif ;
- publier sans finalité ;
- publier sans realm ;
- publier un secret ou un jeton ;
- publier un profil complet ;
- utiliser un filtre SQL libre ;
- utiliser un wildcard implicite ;
- donner à un realm parent la vue automatique sur ses enfants ;
- faire dépendre `GO` d’un broker non installé ;
- déclarer une signature cryptographique inexistante ;
- transformer un rejeu en nouvel événement métier ;
- avancer un curseur en sautant silencieusement des livraisons ;
- supprimer une lettre morte pour faire disparaître un échec.
