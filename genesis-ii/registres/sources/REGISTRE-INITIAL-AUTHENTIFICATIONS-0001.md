# REGISTRE-INITIAL-AUTHENTIFICATIONS-0001 — REGISTRE INITIAL DES AUTHENTIFICATIONS DE SOURCES

## Genesis II — Version 0.1

**Statut : PROJET NORMATIF — EN COURS DE DÉLIBÉRATION**

- **Périmètre :** troisième registre exigé par l'Article 70 de `SOURCES-0001`
- **Autorité de proposition :** Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD
- **Rédaction initiale :** Claude, sous instruction et supervision de l'autorité de proposition
- **Dépendances normatives :** `SOURCES-0001` (Articles 17-23, 45-46)
- **Principe directeur :** authentifier signifie établir suffisamment l'origine ou l'intégrité d'un document ; ce n'est pas la même chose que l'adopter (Article 45, `SOURCES-0001`)

---

# TITRE I — NATURE ET RANG

## Article 1 — Objet

Le présent registre distingue les actes d'authentification effectivement accomplis des simples vérifications techniques, et enregistre les niveaux `AUTH-0` à `AUTH-4` attribués.

## Article 2 — Ce qu'une vérification technique n'est pas

Conformément à l'Article 21 de `SOURCES-0001`, le niveau `AUTH-3` exige qu'« une autorité compétente ait formellement vérifié et enregistré » l'origine, la version, l'intégrité, la chaîne de conservation et l'adoption d'une source. Une vérification purement technique d'empreinte Git, réalisée par un agent artificiel, constitue un élément de preuve utile à cette authentification ; elle ne constitue pas à elle seule l'acte d'authentification, qui reste réservé à l'autorité compétente.

---

# TITRE II — SCHÉMA D'UNE ENTRÉE

## Article 3 — Champs obligatoires

Référence de la source, niveau d'authenticité attribué, éléments vérifiés, méthode de vérification, auteur de la vérification (technique) et autorité ayant validé l'authentification (institutionnelle), date, réserves.

---

# TITRE III — VÉRIFICATIONS TECHNIQUES CONSTATÉES

## Article 4 — Tableau des vérifications techniques réalisées le 26-27 juillet 2026

| Source | Élément vérifié | Méthode | Résultat | Vérifié par | Authentification institutionnelle |
|---|---|---|---|---|---|
| `SOURCES-0001` | Empreinte Git du contenu adopté contre `STATUT` | `git rev-parse <commit>:<chemin>` comparé à l'empreinte déclarée | Concordance exacte | Claude (`AGENT-IA-002`) | Non formellement prononcée — technique seulement |
| `GOVERNANCE-0001` | Idem | Idem | Concordance exacte | Claude (`AGENT-IA-002`) | Non formellement prononcée |
| `CORE-CHARTER-0001` | Idem | Idem | Concordance exacte | Claude (`AGENT-IA-002`) | Non formellement prononcée |
| `LEXICON-0001` | Idem | Idem | Concordance exacte | Claude (`AGENT-IA-002`) | Non formellement prononcée |
| `PRODUCT-CONSTITUTION-0001` | Idem | Idem | Concordance exacte | Claude (`AGENT-IA-002`) | Non formellement prononcée |
| `CORE-LAWS-0001` | Empreinte Git et SHA-256 contre `STATUT` | Idem + `sha256sum` | Concordance exacte sur les deux empreintes | Claude (`AGENT-IA-002`) | Non formellement prononcée |
| `CORE-ATLAS-0001` | Empreinte Git du contenu adopté contre `STATUT` | Idem | Concordance exacte | Claude (`AGENT-IA-002`) | Non formellement prononcée |

## Article 5 — Portée

Ces sept concordances constituent une preuve technique que le contenu publié correspond, empreinte pour empreinte, à ce que chaque `STATUT` déclare avoir été adopté. Elles ne remplacent pas une authentification prononcée par l'autorité compétente, laquelle reste à formaliser si elle est jugée nécessaire.

## Article 6 — Écart constaté

Les douze autres textes adoptés de Genesis II n'ont pas fait l'objet de la même revérification indépendante à ce jour ; l'absence de vérification ne constitue pas un doute sur leur intégrité, seulement une tâche non répétée.

---

# TITRE IV — DISPOSITIONS FINALES

## Article 7 — Adoption et entrée en vigueur

Le présent texte ne possède une force normative qu'après adoption expresse par l'autorité compétente et inscription au Registre des adoptions. Jusqu'à cette adoption, il demeure :

> **PROJET NORMATIF — EN COURS DE DÉLIBÉRATION**
