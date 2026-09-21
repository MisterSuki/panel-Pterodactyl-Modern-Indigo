#!/usr/bin/env bash
#
# Keeps the configuration of Caddy (the web server of the web hosting) up to date with the panel.
#
# The panel gives the list of the domains it has to serve and where each one goes; Caddy makes and renews the HTTPS
# certificate of every domain by itself. This script fetches the list, and only if it changed it writes it and reloads Caddy.
# If the new file is not accepted by Caddy, the old one is put back and nothing changes.
#
# Setup, once:
#   1. Install Caddy, and add this line to /etc/caddy/Caddyfile:      import /etc/caddy/panel-sites.caddy
#   2. Copy this script to /usr/local/bin/panel-proxy-sync.sh and make it executable (chmod +x).
#   3. Make the token in the panel (Administration > Web hosting > Settings) and run the script every minute, for example
#      with a systemd timer (a service that runs it and a timer with OnUnitActiveSec=60) or with cron:
#        * * * * * PANEL_URL=https://panel.example.com PANEL_TOKEN=wh_xxx /usr/local/bin/panel-proxy-sync.sh
#   4. Open ports 80 and 443. The web server has to reach the nodes (the address and the port of every site).
#
# Settings (environment): PANEL_URL and PANEL_TOKEN are needed. CADDY_SITES_FILE (default /etc/caddy/panel-sites.caddy),
# CADDYFILE (default /etc/caddy/Caddyfile) and STATE_DIR (default /var/lib/panel-proxy-sync) can be changed.

set -euo pipefail

: "${PANEL_URL:?PANEL_URL is needed (for example https://panel.example.com)}"
: "${PANEL_TOKEN:?PANEL_TOKEN is needed (made in the settings of the web hosting)}"

SITES_FILE="${CADDY_SITES_FILE:-/etc/caddy/panel-sites.caddy}"
CADDYFILE="${CADDYFILE:-/etc/caddy/Caddyfile}"
STATE_DIR="${STATE_DIR:-/var/lib/panel-proxy-sync}"

mkdir -p "$STATE_DIR"
new="$(mktemp)"
headers="$(mktemp)"
trap 'rm -f "$new" "$headers"' EXIT

args=(-sS --max-time 20 -o "$new" -D "$headers" -w '%{http_code}' -H "Authorization: Bearer ${PANEL_TOKEN}")
if [ -s "$STATE_DIR/etag" ] && [ -f "$SITES_FILE" ]; then
    args+=(-H "If-None-Match: $(cat "$STATE_DIR/etag")")
fi

status="$(curl "${args[@]}" "${PANEL_URL%/}/api/hosting/proxy-config")"

case "$status" in
    304)
        exit 0
        ;;
    200)
        ;;
    *)
        echo "The panel answered ${status}: the configuration was not changed." >&2
        exit 1
        ;;
esac

# What the panel sent has to look like what it makes, and nothing else.
if ! head -n 1 "$new" | grep -q '^# Made by the panel'; then
    echo "The answer of the panel is not a configuration: it was not used." >&2
    exit 1
fi

etag="$(tr -d '\r' < "$headers" | grep -i '^etag:' | head -n 1 | sed 's/^[^:]*: *//' || true)"

if [ -f "$SITES_FILE" ] && cmp -s "$new" "$SITES_FILE"; then
    [ -n "$etag" ] && printf '%s' "$etag" > "$STATE_DIR/etag"
    exit 0
fi

backup=""
if [ -f "$SITES_FILE" ]; then
    backup="$STATE_DIR/panel-sites.caddy.previous"
    cp -f "$SITES_FILE" "$backup"
fi
install -m 644 "$new" "$SITES_FILE"

if ! caddy validate --config "$CADDYFILE" --adapter caddyfile >/dev/null 2>&1; then
    echo "Caddy does not accept the new configuration: the old one was put back." >&2
    if [ -n "$backup" ]; then
        install -m 644 "$backup" "$SITES_FILE"
    else
        rm -f "$SITES_FILE"
    fi
    exit 1
fi

systemctl reload caddy
[ -n "$etag" ] && printf '%s' "$etag" > "$STATE_DIR/etag"
echo "Configuration updated."
