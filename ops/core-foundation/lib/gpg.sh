#!/usr/bin/env bash
#
# Environnement GPG des scripts de continuité.
#
# Les unités systemd tournent avec `ProtectHome=true` : le répertoire personnel
# du compte d'exploitation est inaccessible en écriture. GPG, lui, veut un
# `~/.gnupg` pour son trousseau et ses sockets, même en chiffrement symétrique
# où aucune clé n'est conservée. Sans répertoire, il s'arrête net :
#
#   gpg: Fatal: can't create directory '/var/lib/postgresql/.gnupg'
#
# Plutôt que d'affaiblir l'unité en rouvrant le répertoire personnel, on donne
# à GPG un répertoire privé et jetable. `PrivateTmp=true` garantit qu'il est
# invisible des autres services, et il disparaît à la sortie.
#
# Le chiffrement asymétrique fait exception : le trousseau qui porte la clé du
# destinataire doit être celui de l'exploitant. On n'y touche pas.

set -Eeuo pipefail

gpg_preparer_home() {
    if [[ -n "${GAMAD_OFFSITE_GNUPGHOME:-}" ]]; then
        export GNUPGHOME="$GAMAD_OFFSITE_GNUPGHOME"
        mkdir -p "$GNUPGHOME"
        chmod 700 "$GNUPGHOME"
        return
    fi

    # Un destinataire suppose un trousseau existant : ne rien détourner.
    if [[ -n "${GAMAD_OFFSITE_RECIPIENT:-}" ]]; then
        return
    fi

    local abri
    abri="$(mktemp -d)"
    chmod 700 "$abri"
    export GNUPGHOME="$abri"
    gamad_a_nettoyer "$abri"
}
