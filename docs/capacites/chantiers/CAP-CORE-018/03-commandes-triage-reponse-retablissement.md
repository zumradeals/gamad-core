# CAP-CORE-018 — Commandes, triage, réponse et rétablissement

## 1. Principes d’exécution

Toutes les commandes passent par une couche applicative commune utilisée par l’API, la console et les tâches planifiées.

Chaque commande sensible vérifie dans cet ordre :

```text
1. forme et taille de la requête
2. identité et session
3. organisation et realm
4. rôle et mandat
5. politique active CAP-CORE-007
6. autorisation CAP-CORE-004
7. exigences de décision CAP-CORE-008
8. contrat actif CAP-CORE-009
9. idempotence et concurrence
10. transition métier
11. écriture transactionnelle
12. audit CAP-CORE-013
13. outbox CAP-CORE-014
14. preuve CAP-CORE-015 si requise
```

Une dépendance souveraine indisponible produit un refus ou une mise en attente explicite, jamais un contournement.

## 2. Commandes Artisan minimales

Créer :

```text
php artisan incidents:migrer
php artisan incidents:bootstrap
php artisan incidents:diagnostiquer
php artisan incidents:signaux-ingester
php artisan incidents:signaux-reconcilier
php artisan incidents:echeances-verifier
php artisan incidents:actions-relancer
php artisan incidents:preuves-verifier
php artisan incidents:projections-reconstruire
php artisan incidents:retention-appliquer
php artisan incidents:exporter {reference}
php artisan incidents:verifier-paquet {chemin}
php artisan incidents:exercice
```

Toutes les commandes :

- retournent un code de sortie fiable ;
- sont sûres en concurrence ;
- supportent `--dry-run` lorsque l’écriture n’est pas indispensable ;
- n’affichent aucun secret ;
- acceptent des limites de lot ;
- produisent un résumé exploitable ;
- ne modifient pas l’état métier depuis une commande de diagnostic.

## 3. Bootstrap

Le bootstrap installe :

- types d’incidents ;
- niveaux de sévérité ;
- dimensions d’impact ;
- rôles de réponse ;
- états et résultats ;
- types d’actions ;
- types de communication ;
- critères de rétablissement ;
- politique `POL-INCIDENTS-V1` ;
- contrats `CTR-INC-*` ;
- projections de vocabulaire nécessaires.

Règles :

- idempotent ;
- empreinte versionnée ;
- transactionnel ;
- refuse une divergence non expliquée ;
- ne crée aucun incident fictif en production ;
- les données de démonstration restent dans les tests.

## 4. Ingestion d’un signal

Commande applicative :

```text
RecevoirSignalIncident
```

Entrée minimale :

```text
reference_signal
producteur
source
contrat
version
operation
type_signal
sujet_type
sujet_reference
realm
organisation
environnement
detecte_le
resume_minimal
empreinte_charge
preuve
correlation_id
causalite_id
classification
```

Traitement :

1. vérifier le contrat et le producteur ;
2. vérifier source, realm et organisation ;
3. vérifier le schéma ;
4. expurger les champs interdits ;
5. vérifier ou calculer l’empreinte minimale ;
6. dédupliquer la référence ;
7. rechercher un candidat compatible ;
8. rattacher ou créer un candidat selon politique ;
9. écrire l’audit et l’outbox ;
10. retourner une référence stable.

Résultats possibles :

```text
SIGNAL_ENREGISTRE
SIGNAL_DEJA_CONNU
SIGNAL_RATTACHE
CANDIDAT_CREE
SIGNAL_REJETE
SIGNAL_MIS_EN_QUARANTAINE
```

Un signal mal formé n’est pas silently discarded. Il est refusé ou mis en quarantaine sans contenir sa charge sensible.

## 5. Déclaration manuelle

Commande :

```text
DeclarerIncidentCandidat
```

Champs :

```text
organisation
realm
type
titre_court
resume_minimal
environnement
produit
capacite
instant_debut_estime
source
classification
premiers_impacts
references_preuves
idempotency_key
```

Règles :

- l’auteur est identifié ;
- la déclaration ne confirme pas l’incident ;
- un candidat similaire est proposé, jamais fusionné silencieusement ;
- aucune pièce brute ;
- les inconnues sont acceptées et marquées ;
- le déclarant peut suivre la référence selon politique.

## 6. Démarrer le triage

Commande :

```text
DemarrerTriageIncident
```

Préconditions :

- état `SIGNALE` ou `ROUVERT` ;
- trieur autorisé ;
- realm accessible ;
- aucun triage exclusif actif, sauf reprise après expiration du bail.

Le triage acquiert un bail court :

```text
proprietaire_bail
acquis_le
expire_le
```

Le bail ne confère aucun droit supplémentaire.

## 7. Questionnaire de triage

Le triage doit produire une réponse structurée :

```text
événement réel observé ?
impact constaté ?
impact immédiat probable ?
périmètre connu ?
propagation possible ?
données ou secrets concernés ?
service affecté ?
produit ou satellite affecté ?
realm affecté ?
preuve disponible ?
action urgente nécessaire ?
doublon possible ?
fausse alerte probable ?
```

Les réponses sont :

```text
OUI
NON
INCONNU
NON_APPLICABLE
```

`INCONNU` ne vaut jamais `NON`.

## 8. Confirmer un incident

Commande :

```text
ConfirmerIncident
```

Préconditions :

- état `EN_TRIAGE` ;
- faits suffisants ;
- type, organisation et realm résolus ;
- au moins un impact constaté ou une menace immédiate explicitée ;
- sévérité calculée ;
- commandant d’incident désigné pour `SEV-1` et `SEV-2` ;
- autorisation valide ;
- preuve ou justification de l’incertitude.

Sortie :

```json
{
  "incident": "INC-...",
  "etat": "CONFIRME",
  "severite": "SEV-2",
  "commandant": "IDN-...",
  "preuve": "PRV-...",
  "correlation_id": "COR-..."
}
```

La confirmation ne qualifie pas automatiquement l’événement de cyberattaque, fraude ou violation juridique.

## 9. Rejeter un candidat

Commande :

```text
RejeterCandidatIncident
```

Motifs canoniques :

```text
FAUSSE_ALERTE
FONCTIONNEMENT_ATTENDU
TEST_AUTORISE
DONNEES_INSUFFISANTES
AUCUN_IMPACT
SIGNAL_INVALIDE
AUTRE_MOTIVE
```

`DONNEES_INSUFFISANTES` peut permettre une réouverture si de nouveaux faits apparaissent.

Le rejet conserve :

- signal ;
- triage ;
- motif ;
- auteur ;
- preuve ;
- date.

## 10. Déclarer un doublon

Commande :

```text
MarquerIncidentDoublon
```

Préconditions :

- incident principal connu ;
- realm et organisation compatibles ou justification de cause commune ;
- aucun conflit d’impact non résolu ;
- motif ;
- autorisation.

Le doublon reste consultable et ses signaux sont reliés à l’incident principal.

## 11. Calculer la sévérité

Service :

```text
QualificationIncident::calculerSeverite()
```

Entrées :

- impacts ;
- étendue ;
- durée ;
- environnement ;
- exposition ;
- réversibilité ;
- propagation ;
- contrôles disponibles ;
- criticité des produits et capacités ;
- contrats affectés.

Sortie :

```text
severite_calculee
methode
version
facteurs
inconnues
recommandation_escalade
```

Le calcul est déterministe et rejouable.

## 12. Modifier la sévérité

Commande :

```text
RequalifierSeveriteIncident
```

Règles :

- hausse immédiate autorisée selon politique ;
- baisse avec nouveaux faits ;
- décision formelle si la politique l’exige ;
- historique conservé ;
- notification interne ;
- réévaluation des délais ;
- aucune baisse juste pour éviter une échéance.

## 13. Attribuer les rôles

Commandes :

```text
NommerParticipantIncident
AttribuerRoleIncident
RetirerRoleIncident
RemplacerCommandantIncident
```

Chaque attribution vérifie :

- identité active ;
- organisation ;
- mandat ;
- disponibilité déclarée ;
- absence de conflit interdit ;
- classification accessible ;
- autorisation.

La perte ou l’expiration d’un mandat produit une alerte et peut suspendre le rôle.

## 14. Ajouter un impact

Commande :

```text
ConstaterImpactIncident
```

Un impact comprend :

- dimension ;
- niveau ;
- périmètre ;
- estimation ;
- confiance ;
- date ;
- source ;
- preuve ;
- inconnues.

Une estimation corrigée crée une nouvelle ligne liée à l’ancienne.

## 15. Préparer le plan de réponse

Commande :

```text
PreparerPlanReponseIncident
```

Catégories d’actions :

```text
CONFINEMENT
ERADICATION
RETABLISSEMENT
SURVEILLANCE
COMMUNICATION
PREUVE
CONTINUITE
CORRECTION
```

Chaque action précise :

- objectif ;
- propriétaire ;
- capacité cible ;
- contrat ;
- opération ;
- ressource ;
- décision requise ;
- preuve attendue ;
- dépendances ;
- échéance ;
- stratégie de compensation.

Interdit :

```text
commande_shell_libre
requete_sql_libre
url_arbitraire
operation_non_enregistree
```

## 16. Confinement

Commande d’état :

```text
DemarrerConfinement
```

Actions possibles, exécutées par les propriétaires :

- révoquer une session ;
- suspendre un jeton fédéré ;
- bloquer un secret pour les nouveaux usages ;
- suspendre une livraison d’événements ;
- isoler un consommateur ;
- mettre un produit en mode limité ;
- geler une opération contractuelle ;
- protéger une preuve ;
- déclencher une sauvegarde d’urgence.

Passage à `CONTENU` seulement si :

- voies de propagation connues traitées ou bornées ;
- actions critiques accusées ;
- impacts encore actifs identifiés ;
- preuve minimale ;
- commandant confirme.

Le confinement n’implique pas éradication.

## 17. Éradication

Commande :

```text
DemarrerEradication
```

Objectifs :

- supprimer la cause technique connue ;
- retirer un secret compromis ;
- corriger une configuration ;
- déployer un correctif ;
- remplacer une version de contrat ;
- corriger une règle ;
- nettoyer une file ou un artefact défectueux.

La correction reste dans la capacité propriétaire.

Passage à `ERADIQUE` exige :

- cause supprimée ou risque explicitement borné ;
- preuves ;
- aucune action critique en échec non acceptée ;
- décision si une limite durable est acceptée ;
- mise à jour du risque `CAP-CORE-017`.

La cause peut rester inconnue. Dans ce cas, `ERADIQUE` est interdit ; le dossier peut passer au rétablissement avec surveillance renforcée seulement selon politique et décision.

## 18. Rétablissement

Commande :

```text
DemarrerRetablissement
```

Actions :

- restaurer un magasin ;
- remettre un service en ligne ;
- réactiver une clé nouvelle ;
- reprendre la livraison d’événements ;
- réouvrir un produit ;
- reconstruire une projection ;
- vérifier un contrat ;
- rétablir une fédération.

Chaque réactivation vérifie :

- préconditions ;
- preuves ;
- décision ;
- autorisation ;
- dépendances ;
- plan de retour arrière.

## 19. Déclarer le rétablissement

Commande :

```text
VerifierRetablissementIncident
```

Le système évalue les critères enregistrés.

Résultats :

```text
RETABLI
PARTIELLEMENT_RETABLI
NON_RETABLI
INDETERMINE
```

`PARTIELLEMENT_RETABLI` ne permet pas de passer à `RETABLI` sans règle spécifique.

Pour une restauration :

- lot vérifié ;
- données chargées dans un environnement isolé ;
- contrôles fonctionnels ;
- preuves `CAP-CORE-015` ;
- rapprochement des événements postérieurs ;
- absence de secret exposé.

## 20. Surveillance post-rétablissement

Commande :

```text
DemarrerSurveillanceIncident
```

La fenêtre est définie selon sévérité et type.

Indicateurs possibles :

- erreurs ;
- latence ;
- disponibilité ;
- échecs d’authentification ;
- divergences d’intégrité ;
- backlog d’événements ;
- rejeux ;
- état des clés ;
- réussite des sauvegardes ;
- récurrence des signaux.

`CAP-CORE-018` conserve les résultats minimaux, pas les séries temporelles complètes.

Une récurrence ramène l’incident vers `EN_CONFINEMENT` ou crée un incident lié selon analyse.

## 21. Communications

Commandes :

```text
PreparerCommunicationIncident
ValiderCommunicationIncident
MarquerCommunicationEmise
AnnulerCommunicationIncident
```

Règles :

- modèles versionnés ;
- audience exacte ;
- classification ;
- contenu minimisé ;
- faits séparés des hypothèses ;
- aucune attribution non prouvée ;
- décision requise pour communication publique ou sensible ;
- preuve du contenu émis ;
- canal externe traité par un contrat dédié ;
- échec d’envoi visible.

La note ne doit pas promettre de conformité réglementaire automatique par pays.

## 22. Décisions d’incident

Décisions formelles possibles :

```text
DECIDER_ARRET_PRODUIT
DECIDER_RESTAURATION_PRODUCTION
DECIDER_NOTIFICATION_PUBLIQUE
DECIDER_ACCEPTATION_PERTE
DECIDER_MODE_DEGRADE_PROLONGE
DECIDER_CLOTURE_INCIDENT_MAJEUR
DECIDER_REOUVERTURE
```

Le registre d’incidents ne fabrique pas ces décisions. Il lie la référence `CAP-CORE-008` et vérifie son état.

## 23. Risques et exceptions

À la confirmation :

- rechercher les risques liés ;
- marquer les risques à réévaluer ;
- rechercher les exceptions actives dans le périmètre ;
- suspendre ou révoquer selon politique et décision ;
- ne jamais supposer qu’une exception reste sûre.

À la clôture :

- risque résiduel évalué ;
- traitements créés ;
- exceptions réexaminées ;
- décision d’acceptation si nécessaire.

## 24. Résoudre un incident

Commande :

```text
DeclarerResolutionIncident
```

Préconditions :

- état `SOUS_SURVEILLANCE` ;
- fenêtre minimale achevée ;
- aucun signal critique non traité ;
- impacts terminés ou résiduels déclarés ;
- actions critiques vérifiées ;
- critères de rétablissement valides ;
- preuve ;
- risques réévalués.

`RESOLU` signifie que l’impact actif est maîtrisé. Cela ne signifie pas que la revue est terminée.

## 25. Revue post-incident

Commande :

```text
OuvrirRevueIncident
CompleterRevueIncident
ValiderRevueIncident
```

Contenu minimal :

```text
faits confirmés
chronologie synthétique
impacts finaux
cause connue, probable ou inconnue
facteurs contributifs
contrôles ayant fonctionné
contrôles ayant échoué
qualité de la détection
qualité de la réponse
qualité des communications
risques et exceptions liés
leçons
mesures correctives
propriétaires et échéances
```

La revue ne doit pas contenir une accusation personnelle sans base ni devenir un dossier disciplinaire.

## 26. Clôturer

Commande :

```text
CloturerIncident
```

Préconditions :

- état `RESOLU` ;
- revue `VALIDEE` ;
- validateur de clôture compétent ;
- preuves de rétablissement vérifiables ;
- actions correctives créées ;
- risques réévalués ;
- exceptions réexaminées ;
- décision formelle si requise ;
- paquet de clôture signé ;
- aucune divergence critique non expliquée.

Sortie :

```text
incident
etat CLOS
instant_cloture
revue
preuve_cloture
risques
exceptions
mesures_correctives
```

## 27. Réouvrir

Commande :

```text
ReouvrirIncident
```

Motifs :

```text
RECURRENCE
NOUVEL_IMPACT
PREUVE_NOUVELLE
RETABLISSEMENT_INVALIDE
CAUSE_NON_ERADIQUEE
ERREUR_DE_CLOTURE
```

La réouverture :

- conserve clôture et revue précédentes ;
- crée un nouveau cycle ;
- réévalue sévérité ;
- désigne une équipe ;
- publie un événement ;
- peut nécessiter une décision.

## 28. Fusionner des incidents

Commande :

```text
RegrouperIncidents
```

Règles :

- incident principal explicite ;
- références secondaires conservées ;
- signaux et chronologies non copiés en doublon ;
- realms et classifications vérifiés ;
- impacts contradictoires conservés ;
- preuve et décision selon sévérité ;
- paquet de regroupement.

## 29. Exports et paquets

Commande :

```text
ExporterPaquetIncident
```

Le paquet contient selon autorisation :

```text
manifest.json
incident.json
cycles.json
impacts.json
actifs.json
chronologie.json
roles.json
actions.json
communications.json
liaisons.json
revue.json
preuves.json
signature.json
```

Il ne contient pas :

- secrets ;
- clés privées ;
- charges brutes ;
- dumps ;
- pièces externes par défaut ;
- chemins locaux ;
- contenu exécutable.

Le manifeste est signé par `CAP-CORE-015` via `CAP-CORE-016`.

## 30. Tâches planifiées

### Vérifier les échéances

Au moins toutes les 15 minutes :

- triages sans propriétaire ;
- `SEV-1` sans commandant ;
- actions en retard ;
- baux expirés ;
- communications à valider ;
- critères de rétablissement expirés ;
- incidents résolus non revus ;
- actions correctives en retard.

### Relancer les actions

Uniquement si :

- contrat idempotent ;
- nombre de tentatives non dépassé ;
- autorisation encore valide ;
- décision encore active ;
- incident non clos ;
- compensation définie.

### Vérifier les preuves

- preuve de confirmation ;
- preuve d’action ;
- preuve de rétablissement ;
- preuve de clôture ;
- clé non compromise à l’instant de signature.

Une preuve devenue invalide ouvre une divergence et peut rouvrir l’incident.

## 31. Mode dégradé

Lorsque `CAP-CORE-018` est indisponible :

- les capacités continuent leurs protections locales ;
- les signaux restent dans leurs outbox ;
- aucune action d’urgence n’obtient une permission implicite ;
- les runbooks préautorisés restent applicables ;
- les faits sont journalisés localement ;
- le registre reprend idempotemment ;
- un dossier est créé après reprise si nécessaire.

Le registre d’incidents ne doit jamais devenir un point de panne empêchant la révocation d’un secret déjà compromise selon une politique locale sûre.

## 32. Erreurs canoniques

```text
INCIDENT_INCONNU
SIGNAL_INCONNU
TRANSITION_INCIDENT_INVALIDE
CONCURRENCE_INCIDENT
TRIAGE_DEJA_PRIS
SEVERITE_INVALIDE
ROLE_INCOMPETENT
MANDAT_INVALIDE
REALM_INTERDIT
ORGANISATION_INTERDITE
CONTRAT_ACTION_INACTIF
OPERATION_ACTION_INCONNUE
DECISION_REQUISE
PREUVE_REQUISE
RETABLISSEMENT_NON_PROUVE
REVUE_INCOMPLETE
CLOTURE_INTERDITE
COMMUNICATION_INTERDITE
DONNEE_SENSIBLE_REFUSEE
SOCLE_INDISPONIBLE
```

Chaque erreur est inscrite dans `CAP-CORE-009`, documentée dans OpenAPI et testée.
