# REGISTRE-AGENTS-SENSIBLES-SECURITE-0001 — REGISTRE INITIAL DES AGENTS ET AUTOMATISATIONS SENSIBLES (VOLET SÉCURITÉ)

## Genesis II — Version 0.1

**Statut : PROJET NORMATIF — EN COURS DE DÉLIBÉRATION**

- **Périmètre :** onzième et dernier des registres exigés par l'Article 251 de `SECURITY-GOVERNANCE-0001`
- **Autorité de proposition :** Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD
- **Rédaction initiale :** Claude, sous instruction et supervision de l'autorité de proposition
- **Dépendances normatives :** `SECURITY-GOVERNANCE-0001` (Articles 224-226) ; `REGISTRE-INITIAL-USAGES-IA-0001` ; `AI-GOVERNANCE-0001`

---

# TITRE I — OBJET ET RELATION AVEC LE REGISTRE IA GÉNÉRAL

## Article 1 — Objet

Le présent registre porte le volet sécurité des agents et automatisations sensibles : accès minimal, isolation, mécanisme d'arrêt indépendant, conformément aux Articles 224 à 226 de `SECURITY-GOVERNANCE-0001`. Il ne duplique pas `REGISTRE-INITIAL-USAGES-IA-0001`, qui reste la source de référence pour l'identité, la mission et le Parrain de chaque agent.

## Article 2 — Schéma d'une entrée

Référence de l'agent (renvoi à `REGISTRE-INITIAL-USAGES-IA-0001`), accès effectivement accordés, environnement d'exécution, isolation appliquée, mécanisme d'arrêt, dernière vérification du mécanisme d'arrêt.

---

# TITRE II — ÉTAT ACTUEL

## Article 3 — Renvoi aux agents déjà inscrits

| Agent | Environnement d'exécution | Isolation | Mécanisme d'arrêt |
|---|---|---|---|
| `AGENT-IA-001` (ChatGPT) | Connecteur GitHub, hors environnement de production | Non documentée | Non formalisé — mission close par instruction du 27 juillet 2026, ce qui vaut arrêt de fait |
| `AGENT-IA-002` (Claude) | Environnement Cowork sandboxé, accès local au clone du dépôt, aucun accès de production, aucun accès `push` | Sandbox isolé du reste du système de l'autorité de proposition | Cessation d'instruction ; aucun accès persistant à révoquer |

## Article 4 — Vérification du mécanisme d'arrêt de Claude

Non testée formellement au sens de l'Article 243 d'`AI-GOVERNANCE-0001`. L'absence d'accès de production et de secrets réduit toutefois la portée d'un arrêt manqué.

---

# TITRE III — DISPOSITIONS FINALES

## Article 5 — Adoption et entrée en vigueur

Le présent texte ne possède une force normative qu'après adoption expresse par l'autorité compétente et inscription au Registre des adoptions. Jusqu'à cette adoption, il demeure :

> **PROJET NORMATIF — EN COURS DE DÉLIBÉRATION**
