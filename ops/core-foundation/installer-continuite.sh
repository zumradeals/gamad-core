#!/usr/bin/env bash
#
# Installation unique du pilotage de la continuité depuis la console.
#
# À exécuter en root, une seule fois. Ce script ne touche à aucune donnée : il
# crée un groupe partagé, un répertoire d'échange, et installe trois unités
# systemd. Il ne configure aucune destination et n'envoie rien nulle part.
#
# Pourquoi un groupe partagé : la console tourne en `www-data`, la sauvegarde
# en `postgres`. Ils doivent lire et écrire les mêmes quelques fichiers sans
# que l'un reçoive les droits de l'autre.

set -Eeuo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    echo "Ce script doit être exécuté en root." >&2
    exit 1
fi

racine="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
partage="/var/lib/gamad-core/continuite"
groupe="gamad-continuite"
utilisateur_console="${GAMAD_CONSOLE_USER:-www-data}"
utilisateur_ops="${GAMAD_OPS_USER:-postgres}"

echo "— Groupe partagé"
if getent group "$groupe" >/dev/null; then
    echo "  [=] ${groupe} existe déjà"
else
    groupadd --system "$groupe"
    echo "  [+] ${groupe} créé"
fi
for utilisateur in "$utilisateur_console" "$utilisateur_ops"; do
    if id -nG "$utilisateur" | tr ' ' '\n' | grep -qx "$groupe"; then
        echo "  [=] ${utilisateur} est déjà membre"
    else
        usermod --append --groups "$groupe" "$utilisateur"
        echo "  [+] ${utilisateur} ajouté au groupe"
    fi
done

echo "— Répertoire d'échange"
install -d -o root -g "$groupe" -m 2770 "$partage"
install -d -o root -g "$groupe" -m 2770 "${partage}/demandes"
echo "  [+] ${partage} (2770 root:${groupe})"

echo "— Unités systemd"
for unite in gamad-core-offsite.service gamad-core-continuite.service gamad-core-continuite.path; do
    install -o root -g root -m 0644 "${racine}/systemd/${unite}" "/etc/systemd/system/${unite}"
    echo "  [+] ${unite}"
done
systemctl daemon-reload
systemctl enable --now gamad-core-continuite.path >/dev/null
systemctl enable gamad-core-offsite.service >/dev/null 2>&1 || true
echo "  [+] surveillance des demandes activée"

echo
echo "Installation terminée. Deux gestes restent :"
echo "  1. recharger PHP-FPM pour que la console prenne son nouveau groupe :"
echo "       systemctl reload php8.3-fpm"
echo "  2. configurer la destination depuis la console, page Continuité."
echo
echo "Aucune donnée n’a été déplacée, aucune destination n’a été configurée."
