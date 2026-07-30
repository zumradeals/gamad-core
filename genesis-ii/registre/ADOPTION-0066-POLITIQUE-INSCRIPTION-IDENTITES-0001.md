# REGISTRE D'ADOPTION — ADOPTION-0066
## Politique initiale d'inscription des identités et premier chemin d'écriture autorisé

> **PROJET D'ACTE — préparé sur la branche `agent/core-identity-registration-policy`.** Il entre en vigueur par la fusion `--no-ff` dans `main`, conformément à l'instruction expresse du dirigeant du 30 juillet 2026.

## Nature

Acte de décision et d'exécution de `CAP-CORE-001`. Il transforme l'accord explicite du dirigeant en une politique dérivable par `CAP-CORE-004` et en un premier chemin d'écriture testé.

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, adopte les dispositions ci-après.

---

## Article 1 — Politique adoptée

Est adoptée `POLITIQUE-INSCRIPTION-IDENTITES-0001`, version 1.0.

Elle arrête cinq canaux, les types qu'ils peuvent inscrire, l'échelle `A0` à `A3`, les preuves minimales, les finalités et l'autorité d'inscription `AUT-GAMAD-001`.

## Article 2 — Première ouverture opérationnelle

`AUT-GAMAD-001`, authentifiée et porteuse d'un mandat actif vérifié par `CAP-CORE-003`, peut inscrire une identité par les canaux réservés `AUTORITE` et `CREATION_TECHNIQUE`.

`CAP-CORE-004` dérive cette permission du corpus. Le refus par défaut demeure applicable à tout autre sujet. `CAP-CORE-001` contrôle encore le canal, le type et le producteur après la décision d'autorisation.

Les canaux de produit et d'organisation sont définis dans la politique et pris en charge par le registre, mais ne sont pas encore ouverts par l'API. Cette limite évite qu'une catégorie d'acteurs soit simulée par une règle trop large.

## Article 3 — Clôtures

- **Décision close :** `DECISION-0050` — **Par :** `ADOPTION-0066`.
- **Décision close :** `DECISION-0051` — **Par :** `ADOPTION-0066`.
- **Décision close :** `DECISION-0052` — **Par :** `ADOPTION-0066`.

`DECISION-0053` et `DECISION-0054` demeurent ouvertes.

## Article 4 — Incrément exécuté

L'incrément :

- dérive `POL-INSCRIPTION-IDENTITES-V1` du texte adopté au lieu d'écrire la permission dans le moteur ;
- permet l'inscription HTTP uniquement après authentification, autorisation, vérification du mandat et journalisation ;
- persiste l'identité, son événement de création et son assurance initiale dans une transaction ;
- prouve qu'un sujet non autorisé demeure refusé ;
- prouve qu'un canal de produit ne peut pas être usurpé par l'autorité.

Il ne déploie rien en production et n'inscrit aucune identité réelle.

## Article 5 — Empreintes du corpus

| Chemin | Nature | Empreinte Git |
|---|---|---|
| `genesis-ii/politiques/POLITIQUE-INSCRIPTION-IDENTITES-0001.md` | Politique créée | `7232b18ebefd4318d981942b2713dc0c49e7176d` |
| `genesis-ii/registres/decisions/REGISTRE-INITIAL-DECISIONS-0001.md` | Clôtures ajoutées | `c0eaf9508bd577856e7a2f9102398049e0fd148c` |
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Ligne `ADOPTION-0066` ajoutée | `d035a4108e25788dcb7cf02afa902d6c68ed5366` |

## Article 6 — Non-effets

Le présent acte ne rend pas l'auto-inscription publique, n'accorde aucun droit à un agent, ne permet pas à un produit ou une organisation de contourner sa reconnaissance, ne modifie aucune identité existante, n'admet aucune nouvelle capacité et ne prononce aucun déploiement.

## Article 7 — Entrée en vigueur

La fusion `--no-ff` de la branche dans `main` constitue l'adoption. Avant cette fusion, le texte et le code demeurent une proposition testable.
