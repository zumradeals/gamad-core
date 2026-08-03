# Registre des realms (CAP-CORE-012)

Registre opérationnel des realms de GAMAD Core : fiche de realm, cycle de vie, hiérarchie acyclique, périmètres, identifiants externes, rattachements d'organisation/produit/contrat, franchissements inter-realm et vérifications.

Voir la fiche finale : `docs/capacites/CAP-CORE-012-realms-registry.md`.

## Fichiers

- `src/Magasin.php` — connexion au magasin persistant (`REALM_REGISTRY_URL` / `REALM_REGISTRY_PATH`).
- `src/SchemaRealms.php` — migration additive des onze tables.
- `src/PolitiqueRealms.php` — vocabulaire technique fermé (actions, listes closes).
- `src/RegistreRealms.php` — commandes gouvernées et lectures.
- `src/ValidateurRealms.php` — contrôle d'acyclicité de la hiérarchie `PARENT_DE`.
- `src/EvaluateurPortee.php` — moteur déterministe de contrôle de portée (fonction pure sur des faits déjà rassemblés).
- `src/Ctr12.php` — contrat interne CAP-CORE-009, façade de lecture minimale pour les autres capacités.
- `src/ExceptionRealm.php` — erreur interne (jamais un refus métier gouverné).
- `resources/bootstrap-realms-v1.json` — inventaire initial (honnêtement vide), vérifié par empreinte SHA-256.
- `tests/realms_p3.php` — garde de comportement.

## Ce que ce module NE possède PAS

L'identité canonique (CAP-CORE-001), le dossier d'organisation (CAP-CORE-002), les mandats opposables (CAP-CORE-003), les décisions d'autorisation (CAP-CORE-004), les sources (CAP-CORE-006), les politiques (CAP-CORE-007), les contrats (CAP-CORE-009), les codes canoniques (CAP-CORE-010), les produits et leurs environnements (CAP-CORE-011), aucun secret, aucune donnée métier de satellite, aucune cartographie lourde, aucune donnée de géolocalisation personnelle.

Un rattachement `RESPONSABLE` ou un realm actif ne donnent jamais automatiquement une autorisation : `CAP-CORE-004` reste le seul moteur de décision, et `EvaluateurPortee::evaluer()` le rappelle explicitement dans chaque réponse.

`realm_capacite` (fiche §21) n'est pas créée dans ce chantier : la fiche l'autorise explicitement à rester absente tant qu'aucun consommateur réel ne l'utilise.

## Exécuter la garde

```bash
php core/registre-realms/tests/realms_p3.php
```
