# REGISTRE D'ADOPTION — ADOPTION-0039
## Conception et premier code de `CAP-CORE-005` — authentification, magasin d'exploitation et première écriture applicative

> **PROJET D'ACTE — préparé sur la branche `agent/genesis-ii-cap-core-005`.** Il n'entre en vigueur qu'à la fusion `--no-ff` dans `main`, laquelle **est** l'acte d'adoption et appartient exclusivement à l'autorité (`ADOPTION-0024`, Art. 3).

## Nature

Cet acte est d'une nature que le Core n'avait jamais connue : **il autorise le Core à écrire.**

Les cinq capacités livrées jusqu'ici sont des services de lecture. Elles dérivent des faits que le corpus déclare et les restituent. `CAP-CORE-005` exige de produire un état que le corpus ne déclare pas et ne déclarera jamais — un authentificateur, une session.

`INV-4` interdit l'écriture applicative depuis `ADOPTION-0026`. La question devait être tranchée avant toute technique.

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté la conception `CONCEPTION-CAP-CORE-005-AUTHENTIFICATION-0001` et le premier incrément de code de `CAP-CORE-005`.

## Version adoptée

| Objet | Empreinte / commit |
|---|---|
| `genesis-ii/conception/CONCEPTION-CAP-CORE-005-AUTHENTIFICATION-0001.md` | `55c6945079b7f7b1ee31f0c61b36c8c4f96dd752` |
| Incrément de code (`core/registre-acces/`, page de connexion, commande) | `bacb7565679b07d8ce2f746c23003f9a2c7453dc` |

- **Date d'adoption :** 28 juillet 2026 · **Entrée en vigueur :** à la publication sur `main`

---

## Article 1 — Questions portées sans interruption des travaux

Conformément à l'Article 175 du Registre des autorités, les points suivants sont **proposés** et déclarés en tête.

**1.1 — Cinq invariants nouveaux**, `INV-22` à `INV-26`, dont le premier redéfinit la portée de l'écriture dans le Core.

**1.2 — La portée de `INV-4`, précisée et non amendée.** `INV-4` interdit que l'application produise des **faits du corpus**. Il n'a jamais interdit toute écriture : l'index dérivé est écrit à chaque ingestion depuis `ADOPTION-0029` sans que nul y ait vu une violation, parce que cette écriture ne produit aucun fait — elle recopie ceux que le corpus porte déjà.

La distinction retenue n'est donc pas *écrire ou ne pas écrire*, mais **où l'on écrit, et si ce qu'on y écrit prétend faire foi**.

**1.3 — Un écart au regard de `SECURITY-GOVERNANCE-0001`.** Ses Articles 78 et 79 exigent plusieurs facteurs indépendants pour un compte privilégié. Un seul facteur est livré. L'agent ne l'a pas passé sous silence et ne l'a pas résolu : l'accepter à titre transitoire ou exiger un second facteur est une décision de l'autorité.

## Article 2 — `INV-22` : trois espaces, trois régimes

| Espace | Contenu | Qui écrit | Fait foi ? |
|---|---|---|---|
| **Le corpus** | les faits institutionnels | actes signés seulement | **oui** |
| **L'index dérivé** | copie ordonnée du corpus | l'ingestion | non — reconstructible |
| **Le magasin d'exploitation** | authentificateurs, sessions | l'application | **non** — aucun fait institutionnel |

`INV-4` demeure entier. Aucun fait du corpus ne réside dans le magasin ; sa perte n'y détruit aucune vérité, elle oblige seulement à rétablir des moyens d'accès.

**La technique commandait la même chose que la doctrine.** `Schema::create()` détruit et reconstruit toutes les tables de l'index à chaque ingestion : un identifiant qui y résiderait serait effacé à la première réindexation. Le magasin est donc un espace séparé, que l'ingestion ne touche jamais.

## Article 3 — Le secret n'a pas transité par l'agent, et ne le peut pas

`INV-24` : aucun secret n'est conservé — seulement une empreinte non réversible ; aucun secret ne figure au corpus ; **aucun secret ne passe par l'agent.**

L'incrément livre une commande, `php artisan identite:authentifier <entité>`, destinée à être exécutée **par l'autorité elle-même**. Le secret y est lu en saisie masquée : il n'apparaît ni à l'écran, ni dans l'historique du shell, ni dans la liste des processus, ni dans aucun journal.

**Aucun compte n'est créé par le présent acte.** L'agent a livré la serrure ; il ne détient aucune clef et n'a aucun moyen d'en fabriquer une. C'est la limite 4 de `ADOPTION-0037`, qu'aucune instruction ne lève.

## Article 4 — La console est fermée

`console.dgafrique.com` était **publiquement lisible depuis sa mise en ligne** : quiconque connaissait l'adresse consultait l'état complet du Core. Cet état de fait n'avait été ni décidé ni signalé lors du déploiement ; l'agent en porte la responsabilité.

Toutes les routes exigent désormais une session. Seule `/connexion` demeure ouverte.

## Article 5 — Ce que la capacité n'ouvre pas

Ouvrir une session établit **qui l'on est**, non **ce que l'on peut faire**. Les droits relèvent de `CAP-CORE-004`, `À ÉTABLIR`.

Tant qu'elle n'existe pas, une session ne confère qu'un accès en lecture — exactement ce que la console offrait publiquement. **La capacité ferme une porte ; elle n'en ouvre aucune.**

## Article 6 — Contre-épreuve de falsification

Sur copie hors dépôt, les contrôles d'expiration et de révocation de `verifierSession` ont été neutralisés.

| Exécution | Résultat | Code |
|---|---|---|
| Code sain | `Preuve P3 : ÉTABLIE` | `0` |
| Contrôles neutralisés | `Preuve P3 : NON ÉTABLIE (2 écarts)` | `1` |

Les deux écarts portent exactement sur les points neutralisés : session expirée tenue pour valide, et session survivant à la révocation de son authentificateur — `M-21`, la menace la plus dangereuse de la capacité.

Aucun secret réel n'intervient dans la garde : elle crée un magasin temporaire, y inscrit un secret de test, et détruit le tout.

## Article 7 — Ce que cet incrément ne livre pas

- **Aucun compte, aucun secret.**
- Aucun droit ni permission : `CAP-CORE-004`.
- Aucun facteur multiple (écart de l'Article 1.3).
- **Aucune récupération.** Si l'autorité perd son secret, rien ici ne le lui rend. La fiche exige une récupération *institutionnelle* ; elle suppose une institution. Un seul titulaire étant en fonction, le seul remède est la reconstruction du magasin — possible sans dommage, précisément parce qu'il ne contient aucune vérité.

Ce dernier point mérite d'être lu deux fois : la conception tire un avantage opérationnel de la pauvreté institutionnelle du Core. Il ne faut pas s'en réjouir. C'est le signe que la vacance des fonctions (Article 170 du Registre des autorités) demeure entière.

## Article 8 — Effets

`CAP-CORE-005` passe en conception `CONÇUE`, implémentation `PARTIELLEMENT MATÉRIALISÉE`, preuve `P3 — TESTÉ` ; exploitation `INACTIVE`. Constaté au Titre XXIII de `REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001`.

Cette adoption ne crée aucun compte, ne détient aucun secret, n'admet aucun produit, n'accepte aucun risque nouveau, ne franchit pas la frontière des accès réservés et ne constate pas `G0`.

## Réserve d'audit maintenue

Conçu, codé et vérifié par le même agent, sous une fonction AUDIT non indépendante. L'agent a écrit la serrure de la porte par laquelle l'autorité entrera, et il est lui-même inscrit au registre des identités qu'elle protège.

## Constat d'exécution

| Chemin | Mise à jour | Empreinte Git après mise à jour |
|---|---|---|
| `genesis-ii/conception/CONCEPTION-CAP-CORE-005-AUTHENTIFICATION-0001.md` | Texte adopté (création) | `55c6945079b7f7b1ee31f0c61b36c8c4f96dd752` |
| `genesis-ii/registres/capacites/REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001.md` | Titre XXIII (Articles 135-140) | `391d979ac409724f4a685ab4180fc103810bb6f9` |
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Article 4 — ligne `ADOPTION-0039` | `23e727330cc8108aaf363e9b060b0c7d4cfab62e` |

Ces empreintes remplacent, pour ces fichiers et pour eux seuls, celles déclarées par les actes antérieurs, qui demeurent exactes à leur date. Aucune ligne ou article préexistant n'a été réécrit.

## Vérification des gardes

Garde documentaire : `0`. Gardes de comportement `registre-normes`, `registre-autorites`, `registre-identites`, `registre-acces` : `0`, `0`, `0`, `0`. Contre-épreuve : `1` (Article 6).

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire
- **Date :** 28 juillet 2026 · **Mention :** LU ET ADOPTÉ
