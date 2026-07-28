# REGISTRE D'ADOPTION — ADOPTION-0027
## Décision technologique du premier noyau `CAP-CORE-007`

## Nature

Le présent acte adopte `DECISION-TECHNOLOGIQUE-CAP-CORE-007-0001`, deuxième étape de la séquence de l'Article 63 du registre des capacités souveraines : la conception de `CAP-CORE-007` étant adoptée (`ADOPTION-0026`, état `CONÇUE`), le choix technologique est arrêté avant l'ouverture de tout code. Il n'ouvre pas de code : il fixe la pile.

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté le document :

`DECISION-TECHNOLOGIQUE-CAP-CORE-007-0001 — Projet d'acte de décision technologique pour le premier noyau CAP-CORE-007`.

## Version adoptée

| Chemin | Branche de préparation | Commit de rédaction | Empreinte Git du contenu adopté |
|---|---|---|---|
| `genesis-ii/conception/DECISION-TECHNOLOGIQUE-CAP-CORE-007-0001.md` | `agent/genesis-ii-decision-tech-cap-core-007` | `2531194049b77a51664216dfe537063fbfcdbd8f` | `abef9ff4aa21a2d6461a4ad5d6b453b25789f68d` |

- **Version :** `0.1`
- **Date d'adoption :** 27 juillet 2026
- **Entrée en vigueur :** immédiate, à la publication sur `main`

## Effets

L'adoption fixe, pour le premier noyau et comme socle applicatif initial du Core, la pile suivante :

- **Git** — substrat d'adressage par contenu et d'historique, source de vérité des textes canoniques et de leurs empreintes ; non remplacé par la base ;
- **PostgreSQL** — base de l'index dérivé (intégrité relationnelle, tables en ajout seul, reproductibilité) ;
- **PHP + Laravel** — cadre applicatif et service `CTR-04` (lecture et attestation) ;
- **`outils/verifier-integrite.py` (Python), inchangé** — contrôle d'intégrité `P2`, délibérément **séparé** du cadre applicatif qu'il contrôle.

En conséquence :

- ces composants sont inscrits au registre des dépendances d'ingénierie `genesis-ii/registres/ingenierie/REGISTRE-DES-DEPENDANCES-0001.md`, dont l'Article 3 (« registre ouvert et vide ») cesse de décrire l'état courant. L'inscription est portée par un Titre IV additif, sans réécriture d'aucun article antérieur ;
- l'étape 3 de la séquence de l'Article 63 — l'ouverture du premier code canonique du service `CTR-04` — est désormais **autorisée** sur une conception et une pile toutes deux adoptées, et relève de la fonction d'ingénierie `FCT-CORE-009`.

Cette adoption :

- **n'ouvre par elle-même aucune branche de code** et n'installe aucun composant ;
- ne rend `CAP-CORE-007` ni implémentée, ni admise, ni active ;
- ne fixe pas les versions majeures précises — cette fixation demeure réservée à l'autorité et sera consignée au premier build (Article 8.2 de la décision) ;
- n'introduit aucune dépendance à un fournisseur captif ; tout composant retenu préserve export, révocation, restauration, remplacement et souveraineté (Article 85 du registre des capacités) ;
- n'admet aucun produit, ne valide aucun accès, n'accepte aucun risque nouveau ;
- ne modifie le corps d'aucun texte déjà adopté.

## Point d'architecture retenu — indépendance du contrôle

Conformément à l'Article 4 de la décision adoptée, le vérificateur d'intégrité `verifier-integrite.py` **n'est pas réécrit** dans le cadre applicatif : un contrôle qui partagerait le code et l'exécution de l'application qu'il vérifie perdrait sa valeur. Il demeure en Python, en intégration continue, distinct du service `CTR-04`. Cette séparation prolonge, à l'échelle technique, le principe de séparation de l'AUDIT rappelé par `ADOPTION-0025`, Article 3.b — réserve qui demeure entière.

## Constat d'exécution

| Chemin | Mise à jour | Empreinte Git après mise à jour |
|---|---|---|
| `genesis-ii/registres/ingenierie/REGISTRE-DES-DEPENDANCES-0001.md` | Titre IV (Articles 6-9) — inscription de la pile du premier noyau | `377016b4b7a5c9ca1632d6da9dc73df7ecb17192` |
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Article 4 — ajout de la ligne `ADOPTION-0027` | `c8eef6f4dc514fe70227a89903c0c6c9e20ff362` |

Ces empreintes remplacent, pour ces deux fichiers et pour eux seuls, celles déclarées par les registres d'adoption antérieurs (`ADOPTION-0020` pour le premier ; `ADOPTION-0026` pour le second), qui demeurent exactes comme constat de l'état des fichiers à leur date d'adoption respective et qui sont dépassées par le présent acte dans la seule mesure des ajouts décrits ci-dessus. Aucune ligne, aucun tableau et aucun article préexistant n'a été réécrit.

## Publication

Le texte adopté, le présent registre et la mise à jour du registre des dépendances sont destinés à être publiés ensemble sur `main`, conformément à l'Article 66 de `GOVERNANCE-0001`. La publication est exécutée par `AGENT-IA-002` sous instruction expresse de l'autorité de proposition ; conformément au même Article 66, cette exécution ne fait pas de `AGENT-IA-002` l'autorité d'adoption.

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire
- **Date :** 27 juillet 2026
- **Entrée en vigueur :** immédiate, à la publication sur `main`
- **Mention :** LU ET ADOPTÉ
