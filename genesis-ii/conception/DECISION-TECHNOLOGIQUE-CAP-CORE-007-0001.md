# DECISION-TECHNOLOGIQUE-CAP-CORE-007-0001
## Projet d'acte de décision technologique pour le premier noyau `CAP-CORE-007`

> **PROJET D'ACTE — soumis à l'autorité de proposition. Non exécuté, non adopté, non signé tant qu'un registre d'adoption distinct (pressenti `ADOPTION-0027`) n'a pas été signé par l'autorité compétente.** Ce document décide d'un choix technologique ; il ne code rien.

## Nature et rattachement

Le présent acte est l'**étape 2 de la séquence de l'Article 63** du registre des capacités souveraines : la conception de `CAP-CORE-007` étant adoptée (`ADOPTION-0026`, état `CONÇUE`), le choix technologique peut désormais être arrêté — invariants d'abord, technologie ensuite. Il s'appuie sur les exigences de l'Article 24 de `CONCEPTION-CAP-CORE-007-REGISTRE-DES-NORMES-0001`.

Rédigé par SIRR (Claude, `AGENT-IA-002`) sous instruction, conformément à `ADOPTION-0024`, Article 3 : il conçoit et recommande ; il ne décide ni ne signe. La décision et sa signature reviennent à l'autorité, au titre de la fonction d'ingénierie `FCT-CORE-009` (`ADOPTION-0022`).

## Article 1 — Exigences à satisfaire (rappel de l'Article 24 de la conception)

Tout choix technologique doit offrir :

1. **intégrité relationnelle forte** — unicité `(référence, version)`, clés étrangères, tables en ajout seul ;
2. **adressage par contenu** — les empreintes Git existent déjà ; le magasin les référence, il ne les remplace pas ;
3. **export, révocation, restauration, remplacement et souveraineté** (Article 85 du registre des capacités) — aucun fournisseur irremplaçable ;
4. **reproductibilité déterministe** — reconstruction de l'index et des états passés.

## Article 2 — Décision

L'autorité arrête, pour le premier noyau et comme socle applicatif initial du Core, la pile suivante :

| Couche | Choix | Rôle |
|---|---|---|
| Substrat d'adressage par contenu et d'historique | **Git** | Source de vérité des textes canoniques et de leurs empreintes (`INV-1`, `INV-3`). Non remplacé par la base. |
| Base de données de l'index dérivé | **PostgreSQL** (version majeure supportée en vigueur) | Modèle relationnel du Titre II de la conception ; contraintes d'intégrité, transactions, tables en ajout seul. |
| Cadre applicatif et service `CTR-04` | **PHP (version majeure supportée) + Laravel** | Exposition du contrat de lecture et d'attestation ; administration future des capacités. |
| Contrôle d'intégrité indépendant | **`outils/verifier-integrite.py` (Python), inchangé** | Contrôle `P2` exécuté en intégration continue, **délibérément séparé** du cadre applicatif qu'il contrôle. |

## Article 3 — Recommandation motivée de SIRR

Cette pile est **recommandée**, pour trois raisons :

1. **Elle satisfait toutes les exigences de l'Article 1.** PostgreSQL couvre l'intégrité relationnelle et la reproductibilité ; Git fournit l'adressage par contenu déjà en usage ; l'ensemble est exportable, restaurable et remplaçable, sans fournisseur captif (Article 85).
2. **Elle est cohérente et réversible.** Elle établit un socle applicatif unique (PHP/Laravel/PostgreSQL) pour les capacités suivantes, tout en restant modifiable : l'Article 12 du registre des capacités prévoit l'état `EN RÉVISION`, et le présent acte est amendable comme tout texte.
3. **Elle préserve l'indépendance du contrôle** — point sur lequel j'attire votre attention plus qu'aucun autre.

## Article 4 — Point d'architecture : le contrôle ne se réécrit pas dans l'application

`outils/verifier-integrite.py` est aujourd'hui la preuve `P2` de `CAP-CORE-007`. Je recommande expressément de **ne pas** le réécrire en PHP pour « unifier le stack ». Deux raisons :

- **Séparation du contrôle et du contrôlé.** Un contrôle d'intégrité qui partage le code, le cadre et l'exécution de l'application qu'il vérifie perd sa valeur : un défaut commun les atteindrait tous deux. Garder le vérificateur dans un langage et un processus distincts (Python, en intégration continue) est un renforcement, non une incohérence. Cela prolonge, à l'échelle technique, le principe de séparation de l'AUDIT rappelé par `ADOPTION-0025`, Article 3.b.
- **Il fonctionne et il est éprouvé.** Le réécrire serait un risque pur — introduire précisément la dérive qu'il détecte — sans bénéfice. Le cadre PHP/Laravel sert l'**application** (le service `CTR-04`) ; le vérificateur Python reste le **contrôle** de cette application.

La pile applicative est donc PHP/Laravel/PostgreSQL ; le contrôle d'intégrité demeure Python et indépendant. Ce n'est pas un compromis, c'est la bonne architecture.

## Article 5 — Réversibilité

Aucun de ces choix n'est irréversible. Conformément à l'Article 85 du registre des capacités, tout composant retenu doit préserver l'export, la révocation, la restauration, le remplacement et la souveraineté institutionnelle. Un changement ultérieur de base, de cadre ou de langage se fait par amendement du présent acte et mise à jour du registre des dépendances, sans réécriture de l'historique.

## Article 6 — Effet et exécution

À l'adoption (acte pressenti `ADOPTION-0027`), les composants ci-dessus sont inscrits au registre des dépendances d'ingénierie `genesis-ii/registres/ingenierie/REGISTRE-DES-DEPENDANCES-0001.md`, conformément à son schéma (Article 2 : composant, version, source, licence, statut de maintenance, vulnérabilités, alternatives, responsable), par un Titre additif — ce registre étant aujourd'hui « ouvert et vide » (son Article 3).

Cette adoption **n'ouvre pas encore de branche de code** : elle fixe la pile. L'ouverture du premier code canonique du service `CTR-04` sera l'étape 3, exécutée au titre de `FCT-CORE-009`, sur une conception et une pile désormais toutes deux adoptées.

## Article 7 — Ce que cet acte ne fait pas

Il ne code rien, ne rend `CAP-CORE-007` ni implémentée ni active, n'admet aucun produit, n'introduit aucune dépendance à un fournisseur captif, n'accepte aucun risque nouveau et ne modifie le corps d'aucun texte adopté.

## Article 8 — Décisions réservées à l'autorité

1. l'adoption ou la modification du présent choix (acte pressenti `ADOPTION-0027`) ;
2. la fixation des versions majeures précises de PostgreSQL, PHP et Laravel au moment de l'inscription au registre des dépendances ;
3. la désignation d'un responsable de dépendance (schéma de l'Article 2 du registre des dépendances), ou sa vacance explicite.

---

## Autorité d'adoption

- **Nom :** _[réservé à l'autorité de proposition]_
- **Qualité :** _[à compléter]_
- **Date :** _[à compléter à l'adoption]_
- **Registre d'adoption pressenti :** `ADOPTION-0027`
- **Signature :** _[réservée à l'autorité]_

Jusqu'à adoption expresse et inscription au Registre des adoptions, le présent texte demeure :

> **PROJET NORMATIF — EN COURS DE DÉLIBÉRATION**
