#!/usr/bin/env bash
#
# Registre de nettoyage partagé.
#
# Bash n'a qu'UN seul gestionnaire `trap EXIT` : le dernier posé remplace les
# précédents, sans avertissement. Trois endroits de la copie hors machine ont
# besoin d'un répertoire temporaire — l'archive avant chiffrement, la
# configuration curl qui porte le mot de passe, le trousseau GPG jetable — et
# chacun posant son propre trap, deux d'entre eux ne seraient jamais effacés.
#
# L'archive en clair est le pire des trois : elle contient les quatre bases.
# D'où ce registre : un seul trap, autant de chemins qu'on veut.

set -Eeuo pipefail

GAMAD_A_NETTOYER=()

gamad_nettoyer() {
    local chemin
    for chemin in ${GAMAD_A_NETTOYER+"${GAMAD_A_NETTOYER[@]}"}; do
        [[ -n "$chemin" ]] && rm -rf -- "$chemin"
    done
}

gamad_a_nettoyer() {
    GAMAD_A_NETTOYER+=("$1")
}

trap gamad_nettoyer EXIT INT TERM
