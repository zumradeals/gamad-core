# REGISTRE D'ADOPTION — ADOPTION-0028
## Conception d'implémentation du contrat `CTR-04` — service du Registre des normes

## Nature

Le présent acte adopte `CONCEPTION-IMPLEMENTATION-CTR-04-0001`, phase de conception concrète de l'étape 3 de la séquence de l'Article 63. Il traduit en artefacts d'implémentation — arborescence, schéma SQL, opérations, test — la conception adoptée `CAP-CORE-007` (`ADOPTION-0026`), sur la pile adoptée par `ADOPTION-0027`. Il n'écrit pas de code et n'installe rien : il fixe la forme concrète que le premier incrément devra respecter.

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté le document :

`CONCEPTION-IMPLEMENTATION-CTR-04-0001 — Projet de conception d'implémentation du contrat CTR-04, service du Registre des normes`.

## Version adoptée

| Chemin | Branche de préparation | Commit de rédaction | Empreinte Git du contenu adopté |
|---|---|---|---|
| `genesis-ii/conception/CONCEPTION-IMPLEMENTATION-CTR-04-0001.md` | `agent/genesis-ii-code-ctr-04` | `2c267c02ec35977b771c758c9382698bd90a5497` | `d395b4e7253ade4d42163e181568579368bd92be` |

- **Version :** `0.1`
- **Date d'adoption :** 27 juillet 2026
- **Entrée en vigueur :** immédiate, à la publication sur `main`

## Effets

L'adoption fait de la conception d'implémentation la référence à laquelle le premier incrément de code du service `CTR-04` devra se conformer. En particulier :

- l'arborescence `core/registre-normes/`, le contrôle `verifier-integrite.py` demeurant hors de `core/` et séparé du module (Titre I) ;
- le schéma relationnel des cinq entités, les invariants `INV-1` à `INV-6` portés par les contraintes SQL et par des privilèges sans `UPDATE` ni `DELETE` sur les tables en ajout seul (Titre II) ;
- l'ingestion dérivée à sens unique, empreintes recalculées et non recopiées, idempotente et reconstructible (Titre III) ;
- les trois opérations de lecture `CTR-04`, sans aucune écriture applicative (Titre IV) ;
- le test `P3` de reconstruction temporelle, fondé sur un fait déjà vrai — `CAP-CORE-007` `EN CONCEPTION` avant `ADOPTION-0026`, `CONÇUE` après (Titre V).

Cette adoption :

- **n'écrit aucun code et n'installe aucun composant** ;
- ne rend `CAP-CORE-007` ni implémentée, ni admise, ni active (états `NON COMMENCÉE` / `INACTIVE` inchangés) ;
- ne franchit pas la frontière des accès réservés : tout déploiement, base hébergée ou secret demeure exclusivement du ressort de l'autorité (`ADOPTION-0025`, Art. 3.a ; Titre VI de la conception d'implémentation) ;
- n'accepte aucun risque nouveau et ne modifie le corps d'aucun texte déjà adopté.

## Décision réservée activée par le présent acte

Le Titre VIII, Article 20.2 de la conception d'implémentation réservait à l'autorité le choix d'exposer le service en HTTP en lecture dès le premier incrément, ou en commande en ligne d'abord. L'autorité, souhaitant une visualisation de la construction du Core, retient l'**exposition HTTP en lecture seule dès le premier incrément** : une vue web du Registre des normes (adoptions, statuts, résultats d'intégrité, résultat du test `P3`), sans aucune route d'écriture. Ce choix demeure conforme à `INV-4` et à l'Article 68 du registre des capacités.

## Réserve d'audit maintenue

La conception d'implémentation est rédigée et vérifiée par le même agent, sous une fonction AUDIT non indépendante (`ADOPTION-0025`, Art. 3.b). Le test `P3` reproductible, une fois le code écrit, constituera un contre-pouvoir ne dépendant pas de l'agent ; la lecture critique de l'autorité demeure le premier filet jusque-là.

## Constat d'exécution

| Chemin | Mise à jour | Empreinte Git après mise à jour |
|---|---|---|
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Article 4 — ajout de la ligne `ADOPTION-0028` | `cfbc729e18f68fd103de5c2891afd8a1ab40e3bd` |

Cette empreinte remplace, pour ce seul fichier, celle déclarée par `ADOPTION-0027`, qui demeure exacte comme constat de l'état du fichier à sa date d'adoption et qui est dépassée par le présent acte dans la seule mesure de la ligne ajoutée. Aucune ligne existante n'a été modifiée.

## Publication

Le texte adopté et le présent registre sont destinés à être publiés ensemble sur `main`, conformément à l'Article 66 de `GOVERNANCE-0001`. La publication est exécutée par `AGENT-IA-002` sous instruction expresse de l'autorité de proposition ; conformément au même Article 66, cette exécution ne fait pas de `AGENT-IA-002` l'autorité d'adoption.

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire
- **Date :** 27 juillet 2026
- **Entrée en vigueur :** immédiate, à la publication sur `main`
- **Mention :** LU ET ADOPTÉ
