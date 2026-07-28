# REGISTRE-DES-DEPENDANCES-0001 — REGISTRE INITIAL DES DÉPENDANCES

## Genesis II — Version 0.1

**Statut : PROJET NORMATIF — EN COURS DE DÉLIBÉRATION**

- **Périmètre :** sixième des sept registres exigés par l'Article 241 d'`ENGINEERING-GOVERNANCE-0001`
- **Autorité de proposition :** Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD
- **Rédaction initiale :** Claude, sous instruction et supervision de l'autorité de proposition
- **Dépendances normatives :** `ENGINEERING-GOVERNANCE-0001` (Articles 187, 236)

---

# TITRE I — OBJET ET SCHÉMA

## Article 1 — Objet

Le présent registre inventorie les dépendances critiques — composants, bibliothèques, outils de construction — avec leur version, source, licence, statut de maintenance, vulnérabilités, alternatives et responsable, conformément aux Articles 187 et 236 d'`ENGINEERING-GOVERNANCE-0001`.

## Article 2 — Schéma d'une entrée

Composant, version, source, licence, statut de maintenance, vulnérabilités connues, alternatives, responsable.

---

# TITRE II — ÉTAT ACTUEL

## Article 3 — Absence de dépendance logicielle

Aucun code canonique n'existant à ce jour, aucune dépendance logicielle n'est constatable. Le présent registre est ouvert et vide.

## Article 4 — Dépendance de fait aux fournisseurs d'IA

Les fournisseurs de modèles d'intelligence artificielle (OpenAI, Anthropic) constituent une forme de dépendance de fait pour la rédaction du chantier documentaire, déjà traitée par `REGISTRE-INITIAL-TIERS-CRITIQUES-0001` et `REGISTRE-INITIAL-USAGES-IA-0001`. Ils ne sont pas dupliqués ici.

---

# TITRE III — DISPOSITIONS FINALES

## Article 5 — Adoption et entrée en vigueur

Le présent texte ne possède une force normative qu'après adoption expresse par l'autorité compétente et inscription au Registre des adoptions. Jusqu'à cette adoption, il demeure :

> **PROJET NORMATIF — EN COURS DE DÉLIBÉRATION**

---

# TITRE IV — PREMIÈRE INSCRIPTION : PILE DU PREMIER NOYAU `CAP-CORE-007`

## Article 6 — Nature de la présente mise à jour

Le présent Titre constate une conséquence d'exécution de `ADOPTION-0027-DECISION-TECHNOLOGIQUE-CAP-CORE-007-0001`. Il inscrit les premières dépendances réelles du Core, l'Article 3 ci-dessus (« registre ouvert et vide ») cessant de décrire l'état courant à compter de cette inscription. Il ne réécrit aucun article antérieur, conformément à la méthode additive appliquée depuis `ADOPTION-0022`.

## Article 7 — Composants inscrits (schéma de l'Article 2)

| Composant | Version | Source | Licence | Statut de maintenance | Vulnérabilités connues | Alternatives | Responsable |
|---|---|---|---|---|---|---|---|
| Git | Dernière version majeure supportée, à figer au premier build | `git-scm.com` | GPL-2.0 | Activement maintenu | Aucune retenue à ce jour | Aucune envisagée (substrat d'adressage par contenu) | `FCT-CORE-009` (transitoire) |
| PostgreSQL | Dernière version majeure supportée, à figer au premier build | `postgresql.org` | PostgreSQL License (BSD/MIT-like) | Activement maintenu | Aucune retenue à ce jour | SQLite (index local), autre SGBD relationnel | `FCT-CORE-009` (transitoire) |
| PHP | Dernière version majeure supportée, à figer au premier build | `php.net` | PHP License 3.01 | Activement maintenu | Aucune retenue à ce jour | À réévaluer si remplacement du cadre applicatif | `FCT-CORE-009` (transitoire) |
| Laravel | Dernière version majeure supportée (LTS le cas échéant), à figer au premier build | `laravel.com` | MIT | Activement maintenu | Aucune retenue à ce jour | Symfony, ou autre cadre PHP | `FCT-CORE-009` (transitoire) |
| Python (contrôle `verifier-integrite.py`) | Version 3 supportée, déjà en usage en intégration continue | `python.org` | PSF License | Activement maintenu | Aucune retenue à ce jour | Aucune envisagée (contrôle volontairement indépendant du cadre applicatif) | `FCT-CORE-009` (transitoire) |

## Article 8 — Réserves d'inscription

- **Versions précises.** Conformément à l'Article 8.2 de la décision technologique, la fixation des versions majeures précises demeure réservée à l'autorité et sera consignée au premier build. Les valeurs ci-dessus expriment une politique de version, non un ancrage définitif.
- **Responsable dédié.** Aucun responsable de dépendance dédié n'est désigné ; la responsabilité est portée à titre transitoire par la fonction d'ingénierie `FCT-CORE-009` (`ADOPTION-0022`). Sa désignation ou sa vacance explicite demeure ouverte (Article 8.3 de la décision technologique).
- **Séparation du contrôle.** L'inscription de Python n'unifie pas la pile : le vérificateur d'intégrité demeure délibérément séparé du cadre applicatif qu'il contrôle (Article 4 de la décision technologique).

## Article 9 — Non-effet

Le présent Titre inscrit une pile choisie ; il n'ouvre aucune branche de code, n'installe aucun composant, n'introduit aucune dépendance à un fournisseur captif et n'accepte aucun risque nouveau.
