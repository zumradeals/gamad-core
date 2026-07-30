# POLITIQUE-INSCRIPTION-IDENTITES-0001 — Politique initiale d'inscription des identités

**Version :** 1.0

**Statut :** ADOPTÉE par `ADOPTION-0066`

**Capacités :** `CAP-CORE-001`, `CAP-CORE-003`, `CAP-CORE-004`, `CAP-CORE-005`

## Article 1 — Objet

La présente politique arrête les canaux d'inscription, les types admis, le niveau d'assurance initial, les preuves minimales et l'autorité responsable. Une inscription hors de ces règles est refusée.

Une identité n'est ni un profil universel, ni un compte, ni une réputation. L'inscription ne recueille que les données nécessaires à l'identification gouvernée.

## Article 2 — Canaux et types

| Canal | Assurance initiale | Types inscriptibles | Producteur compétent |
|---|---|---|---|
| `AUTO_INSCRIPTION` | `A0` | `personne` | la personne, sous un parcours public distinct |
| `PRODUIT_RECONNU` | `A1` | `personne`, `organisation` | un produit inscrit et reconnu |
| `ORGANISATION_RECONNUE` | `A1` | `personne` | une organisation inscrite |
| `AUTORITE` | `A3` | `personne`, `organisation`, `produit`, `realm`, `agent`, `service` | `AUT-GAMAD-001`, sous mandat actif |
| `CREATION_TECHNIQUE` | `A3` | `agent`, `service`, `produit`, `realm` | `AUT-GAMAD-001`, sous mandat actif |

Les types techniques `agent`, `service`, `produit` et `realm` ne peuvent jamais être inscrits par auto-inscription, par un produit ou par une organisation.

## Article 3 — Échelle d'assurance

| Niveau | Sens minimal | Preuve minimale |
|---|---|---|
| `A0` | existence déclarée, non vérifiée | déclaration, canal, date et trace d'inscription |
| `A1` | origine reconnue dans l'écosystème | preuve du producteur reconnu et dossier d'inscription |
| `A2` | identité vérifiée pour une action engageante | preuve de vérification produite par `CAP-CORE-005` |
| `A3` | identité inscrite ou représentée par l'autorité | mandat actif, décision d'autorisation et preuve journalisée |

Les finalités minimales sont : `EXISTENCE` à `A0`, `USAGE_PRODUIT` à `A1`, `ACTION_ENGAGEANTE` à `A2` et `REPRESENTATION` à `A3`. La représentation exige en outre un mandat vérifié ; le niveau seul ne suffit pas.

Une identité provisoire demeure plafonnée à `A1`.

## Article 4 — Preuve et traçabilité obligatoires

Chaque écriture porte au minimum : le canal, le producteur, la politique et sa version, la source, la date d'effet, la preuve de décision et l'événement de cycle de vie correspondant.

Le moteur d'autorisation décide avant l'écriture. Le registre d'identités contrôle ensuite le canal, le type et la compétence du producteur. Un accord du premier ne contourne jamais un refus du second.

## Article 5 — Règle d'autorisation opérationnelle

| Sujet | Effet | Action | Motif |
|---|---|---|---|
| `AUT-GAMAD-001` | `PERMET` | `inscrire une identité` | L'autorité d'inscription peut inscrire une identité par un canal réservé lorsque son mandat actif est vérifié. |

Cette première ouverture opérationnelle est volontairement étroite. Les canaux `PRODUIT_RECONNU` et `ORGANISATION_RECONNUE` sont définis, mais leur exposition par l'API demeure refusée par défaut jusqu'à ce que l'identité du producteur ou de l'organisation soit reliée de bout en bout à `CAP-CORE-004`.

L'agent qui implémente la politique ne reçoit aucun droit d'inscription.

## Article 6 — Autorité et évolution

`AUT-GAMAD-001` est l'autorité d'inscription initiale. Elle peut proposer un rapprochement et décide seule d'une fusion tant qu'aucune autorité distincte n'est adoptée.

Toute extension de sujet, de canal, de type, de preuve ou de finalité exige une nouvelle version adoptée. L'absence de règle demeure un refus.
