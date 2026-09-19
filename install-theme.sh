#!/usr/bin/env bash
#
# Old name of install.sh, kept so that earlier commands keep working.
# It just runs the current installer with the same options.
#
exec bash <(curl -fsSL "https://raw.githubusercontent.com/${THEME_REPO:-MisterSuki/panel-ptero-terra}/${THEME_BRANCH:-main}/install.sh") "$@"
