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

La possession vérifiée d'un email ou d'un téléphone augmente la confiance attachée à cet identifiant, mais ne transforme pas automatiquement toute l'identité en assurance forte.

## Authentification

`POST /api/v1/sessions` reste rétrocompatible :

```json
{"entite":"IDN-PER-...","secret":"..."}
```

et accepte désormais le parcours humain :

```json
{"identifiant":"personne@example.com","type_identifiant":"EMAIL","secret":"..."}
```

ou :

```json
{"identifiant":"+2250701020304","type_identifiant":"TELEPHONE","secret":"..."}
```

La couche API résout l'identifiant vers `IDN-...`, puis CAP-CORE-005 vérifie l'authentificateur. CAP-CORE-005 n'a donc pas à connaître les emails ou téléphones.

## Inscription publique — tranche suivante

Le portail ou un produit reconnu pourra demander une inscription gouvernée contenant un identifiant initial. Le Core devra alors, dans une même opération cohérente :

1. refuser une collision active ;
2. créer l'identité canonique ;
3. attacher l'identifiant de résolution ;
4. créer l'authentificateur ;
5. produire les preuves d'audit ;
6. ouvrir éventuellement la première session.

L'interface publique peut donc rester simple (`nom + email/téléphone + secret`) sans exposer `IDN-...`, `AUTHN-...` ni la mécanique interne du Core.
