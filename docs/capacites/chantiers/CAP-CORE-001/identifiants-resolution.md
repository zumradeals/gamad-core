# CAP-CORE-001 — Identifiants de résolution

## Décision de conception

Une personne de l'écosystème GAMAD possède une **référence canonique unique** (`IDN-PER-...`).
Cette référence n'est pas l'identifiant que l'humain doit mémoriser pour utiliser un produit.

Le Core distingue quatre notions :

1. **Identité canonique** — référence stable, non réattribuée, commune à tous les produits.
2. **Identifiant de résolution** — valeur permettant de retrouver l'identité canonique : email, téléphone, username ou identifiant externe gouverné.
3. **Authentificateur** — preuve permettant d'ouvrir une session : mot de passe, passkey, code de secours, futur OTP, etc.
4. **Donnée de contact / profil** — adresse postale, localisation, préférences et autres données qui ne deviennent pas automatiquement des identifiants de connexion.

## Règle écosystème

Un satellite peut choisir son expérience humaine :

- email + mot de passe ;
- téléphone + mot de passe ;
- email + passkey ;
- téléphone + OTP lorsque cette capacité existera ;
- autre identifiant explicitement autorisé.

Mais le résultat de la résolution est toujours une seule référence canonique `IDN-...`.

Un produit n'est pas satellite parce qu'il se présente comme tel. Le statut vient exclusivement de **CAP-CORE-011 — Products Registry**. Dès qu'un produit y est réellement inscrit avec `type_produit = SATELLITE` et `etat = ACTIF`, il reçoit automatiquement le droit borné **`créer un Compte GAMAD` pour autrui**. Il n'est donc pas nécessaire d'ajouter une règle nominative pour chaque futur satellite.

Ce plein droit porte sur la création de Comptes GAMAD. Il ne donne pas au satellite un droit générique d'écriture dans Identity Registry, le registre des politiques, les mandats, les preuves, les contrats ou les autres registres souverains du Core. Un produit non satellite, suspendu, retiré ou seulement en préparation n'hérite pas de ce droit.

```text
email -------------------\
téléphone ----------------> Identity Registry -> IDN-PER-...
username ----------------/
                               |
                               v
                        Registre d'accès
                        authentificateur
                               |
                               v
                            session
```

## Types initiaux

- `EMAIL`
- `TELEPHONE`
- `USERNAME`
- `EXTERNE`

L'adresse postale n'est pas un identifiant de résolution initial : elle est mutable, non unique et peut être partagée par plusieurs personnes.

## Normalisation

### Email

- espaces périphériques retirés ;
- casse normalisée ;
- syntaxe email minimale validée.

### Téléphone

- stockage logique au format international de type E.164 ;
- `00` est converti en `+` ;
- un numéro local sans indicatif pays est refusé par le Core tant que le contexte pays n'est pas explicitement fourni par une couche autorisée.

Le Core ne doit pas deviner silencieusement un pays.

## Confidentialité

Le registre de résolution conserve une empreinte déterministe de la valeur normalisée et non la valeur brute. Il peut donc retrouver l'identité lors d'une connexion sans transformer CAP-CORE-001 en carnet d'adresses universel.

Une future capacité de livraison (email/SMS) pourra gérer séparément les coordonnées nécessaires à l'envoi, avec chiffrement et finalité propre.

## Unicité et collisions

Un identifiant actif ne peut pas désigner simultanément deux identités canoniques.
Une collision ne provoque jamais une fusion automatique : elle doit être refusée ou alimenter le mécanisme gouverné de rapprochement d'identités.

## Vérification

États initiaux :

- `NON_VERIFIE`
- `VERIFIE`
- `RETIRE`

Un email ou un téléphone créé par un portail ou un satellite reste `NON_VERIFIE` jusqu'à preuve de possession. Le Core crée un défi `VRF-...`, engendre un code court, ne persiste que son empreinte forte et borne sa durée et son nombre de tentatives. Le code brut n'est retourné qu'une fois au produit authentifié afin qu'il puisse le remettre au canal concerné.

Tant que l'identifiant reste `NON_VERIFIE`, il ne peut pas servir à `POST /api/v1/sessions`, même si le mot de passe correspondant est correct. La possession vérifiée d'un email ou d'un téléphone augmente la confiance attachée à cet identifiant, mais ne transforme pas automatiquement toute l'identité en assurance forte.

## Authentification

`POST /api/v1/sessions` reste rétrocompatible :

```json
{"entite":"IDN-PER-...","secret":"..."}
```

et accepte le parcours humain après vérification :

```json
{"identifiant":"personne@example.com","type_identifiant":"EMAIL","secret":"..."}
```

ou :

```json
{"identifiant":"+2250701020304","type_identifiant":"TELEPHONE","secret":"..."}
```

La couche API résout l'identifiant vérifié vers `IDN-...`, puis CAP-CORE-005 vérifie l'authentificateur. CAP-CORE-005 n'a donc pas à connaître les emails ou téléphones.

## Inscription par portail ou satellite reconnu

Le portail explicitement habilité ou tout `SATELLITE ACTIF` reconnu par CAP-CORE-011 peut demander une inscription gouvernée contenant un identifiant initial. Le Core réalise alors le parcours suivant :

1. refuser une collision active ;
2. créer l'identité canonique ;
3. attacher l'identifiant de résolution ;
4. créer l'authentificateur ;
5. créer, pour email/téléphone, un défi de preuve de possession ;
6. produire les preuves d'audit ;
7. n'autoriser la reconnexion humaine qu'après vérification du canal externe.

L'interface publique peut donc rester simple (`nom + email/téléphone + secret`) sans exposer `IDN-...`, `AUTHN-...` ni la mécanique interne du Core.
