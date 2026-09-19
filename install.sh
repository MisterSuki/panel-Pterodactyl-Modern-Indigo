#!/usr/bin/env bash
#
# Pterodactyl - "Modern Indigo" installer
#
# One script that installs everything, like pterodactyl-installer.se does, with the design,
# registration, Discord login and staff roles included:
#
#   - a NEW SERVER: web server, PHP, database, Redis, the panel, its services and an SSL certificate,
#   - WINGS, the daemon that runs the game servers,
#   - an EXISTING PANEL: updates it with everything from this repository,
#   - and the way back: the official panel again, keeping servers, users and every setting.
#
#   bash <(curl -s https://raw.githubusercontent.com/MisterSuki/panel-ptero-terra/main/install.sh)
#   bash <(curl -s https://raw.githubusercontent.com/MisterSuki/panel-ptero-terra/main/install.sh) --help
#
# Supported systems for a new install: Ubuntu 22.04 / 24.04 and Debian 11 / 12.
#
set -Eeuo pipefail

REPO="${THEME_REPO:-MisterSuki/panel-ptero-terra}"
BRANCH="${THEME_BRANCH:-main}"
PANEL_PATH="${PANEL_PATH:-/var/www/pterodactyl}"
BACKUP_ROOT="${BACKUP_ROOT:-/var/backups/pterodactyl-theme}"
PHP_BIN="${PHP_BIN:-php}"
PHP_VERSION="${PHP_VERSION:-8.3}"
WEB_USER="${WEB_USER:-www-data}"
NGINX_DIR="${NGINX_DIR:-/etc/nginx}"
SYSTEMD_DIR="${SYSTEMD_DIR:-/etc/systemd/system}"
CRON_DIR="${CRON_DIR:-/etc/cron.d}"
OS_RELEASE_FILE="${OS_RELEASE_FILE:-/etc/os-release}"
CREDENTIALS_FILE="${CREDENTIALS_FILE:-/root/pterodactyl-credentials.txt}"
SWAP_FILE="${SWAP_FILE:-/var/tmp/pterodactyl-install.swap}"
MEMINFO_FILE="${MEMINFO_FILE:-/proc/meminfo}"
APT_SOURCES_DIR="${APT_SOURCES_DIR:-/etc/apt/sources.list.d}"
COMPOSER_BIN="${COMPOSER_BIN:-/usr/local/bin/composer}"
WINGS_BIN="${WINGS_BIN:-/usr/local/bin/wings}"
WINGS_DIR="${WINGS_DIR:-/etc/pterodactyl}"
DB_NAME="panel"
DB_USER="pterodactyl"

# what to do
DO_PANEL=false
DO_WINGS=false
DO_UPDATE=false
RESTORE=false
UNINSTALL=false

# answers, asked when missing
FQDN=""
ADMIN_EMAIL=""
ADMIN_USER=""
ADMIN_PASSWORD=""
ADMIN_FIRST=""
ADMIN_LAST=""
TIMEZONE=""
SSL_MODE="auto"
WINGS_PANEL_URL=""
WINGS_TOKEN=""
WINGS_NODE=""

# behaviour
SOURCE=""
STOCK_SOURCE=""
STOCK_VERSION=""
FROM=""
ASSUME_YES=false
DRY_RUN=false
CSS_ONLY=false
SKIP_BUILD=false
SKIP_MIGRATE=false
INSTALL_NODE=false
ENABLE_SETTINGS_UI=false
FORCE=false

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
BOLD='\033[1m'
NC='\033[0m'

step() { echo -e "\n${BOLD}${BLUE}==>${NC}${BOLD} $1${NC}"; }
info() { echo -e " ${BLUE}*${NC} $1"; }
ok() { echo -e " ${GREEN}v${NC} $1"; }
warn() { echo -e " ${YELLOW}!${NC} $1"; }
err() { echo -e " ${RED}x${NC} $1" 1>&2; }
die() { err "$1"; exit 1; }

INSTALL_URL="https://raw.githubusercontent.com/${REPO}/${BRANCH}/install.sh"

usage() {
    cat <<EOF
Pterodactyl "Modern Indigo" installer

Usage: bash install.sh [what to do] [options]

What to do (asked in a menu when you give nothing, and no panel is found):
  --panel               Install the panel on this server (new install)
  --wings               Install Wings, the game server daemon, on this server
  --update              Update an existing panel with everything from this repository
  --restore             Put back the files saved by the last update
  --uninstall           Go back to the official panel. Your servers, users and settings are kept

New panel install:
  --fqdn=NAME           Domain (or IP) the panel will be reached at, for example panel.example.com
  --email=ADDRESS       Your email address (Let's Encrypt notices, eggs author)
  --admin-user=NAME     Username of the first administrator (default: admin)
  --admin-password=PW   Password of the first administrator (default: a random one, shown at the end)
  --admin-first-name=N  First name of the administrator (default: Admin)
  --admin-last-name=N   Last name of the administrator (default: User)
  --timezone=ZONE       Panel timezone (default: the server's)
  --ssl / --no-ssl      Get a free SSL certificate (default: yes when a domain name is used)

Wings (optional, to configure it right away):
  --panel-url=URL       Address of your panel
  --wings-token=TOKEN   Application API token of your panel
  --node-id=ID          Number of the node created in your panel

Update:
  --path=DIR            Panel folder (default: /var/www/pterodactyl)
  --css-only            Only install the admin area design
  --skip-migrate        Do not run the database migrations
  --skip-build          Do not compile the dashboard
  --enable-settings-ui  Set APP_ENVIRONMENT_ONLY=false so settings saved in the admin area apply
  --force               Update even if the panel version differs from the one this theme was made for
  --from=DIR            With --restore, use this backup folder instead of the last one

Uninstall:
  --stock-version=X.Y.Z Official panel version to put back (default: the version of your panel)
  --stock-source=DIR|FILE  Use a local folder or panel.tar.gz of the official panel instead of downloading it

Everywhere:
  -y, --yes             Do not ask questions, use the given options and the defaults
  --dry-run             Check everything and show the plan, change nothing
  --install-node        Install Node.js 22 and Yarn when missing (a new install does it anyway)
  --branch=NAME         Branch of the repository to install (default: main)
  --repo=OWNER/NAME     GitHub repository to install from (default: ${REPO})
  --source=DIR|FILE     Use a local folder or .tar.gz instead of downloading from GitHub
  -h, --help            Show this help

Examples:
  bash <(curl -s ${INSTALL_URL})
  bash <(curl -s ${INSTALL_URL}) --panel --fqdn=panel.example.com --email=you@example.com --yes
  bash <(curl -s ${INSTALL_URL}) --wings
  bash <(curl -s ${INSTALL_URL}) --update --yes --install-node
  bash <(curl -s ${INSTALL_URL}) --uninstall
EOF
}

for arg in "$@"; do
    case "$arg" in
        --panel) DO_PANEL=true ;;
        --wings) DO_WINGS=true ;;
        --update) DO_UPDATE=true ;;
        --restore) RESTORE=true ;;
        --uninstall) UNINSTALL=true ;;
        --stock-version=*) STOCK_VERSION="${arg#*=}" ;;
        --stock-source=*) STOCK_SOURCE="${arg#*=}" ;;
        --fqdn=*) FQDN="${arg#*=}" ;;
        --email=*) ADMIN_EMAIL="${arg#*=}" ;;
        --admin-user=*) ADMIN_USER="${arg#*=}" ;;
        --admin-password=*) ADMIN_PASSWORD="${arg#*=}" ;;
        --admin-first-name=*) ADMIN_FIRST="${arg#*=}" ;;
        --admin-last-name=*) ADMIN_LAST="${arg#*=}" ;;
        --timezone=*) TIMEZONE="${arg#*=}" ;;
        --ssl) SSL_MODE="yes" ;;
        --no-ssl) SSL_MODE="no" ;;
        --panel-url=*) WINGS_PANEL_URL="${arg#*=}" ;;
        --wings-token=*) WINGS_TOKEN="${arg#*=}" ;;
        --node-id=*) WINGS_NODE="${arg#*=}" ;;
        --path=*) PANEL_PATH="${arg#*=}" ;;
        --branch=*) BRANCH="${arg#*=}" ;;
        --repo=*) REPO="${arg#*=}" ;;
        --source=*) SOURCE="${arg#*=}" ;;
        --from=*) FROM="${arg#*=}" ;;
        -y | --yes) ASSUME_YES=true ;;
        --dry-run) DRY_RUN=true ;;
        --css-only) CSS_ONLY=true ;;
        --skip-migrate) SKIP_MIGRATE=true ;;
        --skip-build) SKIP_BUILD=true ;;
        --install-node) INSTALL_NODE=true ;;
        --enable-settings-ui) ENABLE_SETTINGS_UI=true ;;
        --force) FORCE=true ;;
        -h | --help)
            usage
            exit 0
            ;;
        *)
            err "Unknown option: $arg"
            usage
            exit 1
            ;;
    esac
done

if [[ "$CSS_ONLY" == true ]]; then
    SKIP_MIGRATE=true
    SKIP_BUILD=true
fi

TMP=""
DOWN=false
OWNER=""
TS="$(date +%Y%m%d%H%M%S)"
BACKUP_DIR=""
TEMP_SWAP=false
INSTALLING=false
RESUMING=false

artisan() {
    (cd "$PANEL_PATH" && "$PHP_BIN" artisan "$@")
}

cleanup() {
    local code=$?
    if [[ "$DOWN" == true ]]; then
        artisan up >/dev/null 2>&1 || true
        DOWN=false
    fi
    if [[ "$TEMP_SWAP" == true ]]; then
        swapoff "$SWAP_FILE" >/dev/null 2>&1 || true
        rm -f "$SWAP_FILE"
    fi
    if [[ -n "$TMP" && -d "$TMP" ]]; then
        rm -rf "$TMP"
    fi
    exit "$code"
}
trap cleanup EXIT

ask() {
    if [[ "$ASSUME_YES" == true ]]; then
        return 0
    fi
    local answer
    printf ' %b?%b %s [y/N] ' "$YELLOW" "$NC" "$1"
    read -r answer || return 1
    [[ "$answer" =~ ^[Yy]([Ee][Ss])?$ ]]
}

# prompt VARIABLE "Question" [default] [secret]
# Keeps a value that was given as an option, asks otherwise, and uses the default with --yes.
prompt() {
    local name="$1" question="$2" default="${3:-}" secret="${4:-}" value=""
    if [[ -n "${!name:-}" ]]; then
        return 0
    fi
    if [[ "$ASSUME_YES" == true ]]; then
        printf -v "$name" '%s' "$default"
        return 0
    fi
    if [[ -n "$default" ]]; then
        printf ' %b?%b %s [%s]: ' "$YELLOW" "$NC" "$question" "$default"
    else
        printf ' %b?%b %s: ' "$YELLOW" "$NC" "$question"
    fi
    if [[ "$secret" == "secret" ]]; then
        read -r -s value || true
        echo
    else
        read -r value || true
    fi
    printf -v "$name" '%s' "${value:-$default}"
}

banner() {
    echo ""
    echo -e "${BOLD}${BLUE}==================================================${NC}"
    echo -e "${BOLD}${BLUE}   Pterodactyl - Modern Indigo installer${NC}"
    echo -e "${BOLD}${BLUE}==================================================${NC}"
}

random_string() {
    # Letters and digits only, so the value is safe in SQL, .env files and shells.
    local length="${1:-32}" value=""
    while [[ "${#value}" -lt "$length" ]]; do
        value+="$(openssl rand -base64 48 | tr -dc 'A-Za-z0-9')"
    done
    echo "${value:0:$length}"
}

panel_version() {
    sed -n "s/.*'version' => '\([^']*\)'.*/\1/p" "$1" | head -n1
}

panel_exists() {
    [[ -f "$PANEL_PATH/artisan" && -f "$PANEL_PATH/config/app.php" ]]
}

require_root() {
    if [[ "$(id -u)" -ne 0 ]]; then
        die "This installer must run as root. Use: sudo -i, then run the command again."
    fi
}

# ---------------------------------------------------------------------------------------------
# Getting the repository files (shared by the new install and the update)
# ---------------------------------------------------------------------------------------------
fetch_source() {
    TMP="$(mktemp -d)"
    SRC="$TMP/src"
    mkdir -p "$SRC"

    if [[ -n "$SOURCE" ]]; then
        if [[ -d "$SOURCE" ]]; then
            SRC="$(cd "$SOURCE" && pwd)"
            info "Using the local folder $SRC"
        elif [[ -f "$SOURCE" ]]; then
            tar -xzf "$SOURCE" -C "$SRC" --strip-components=1 || die "Could not read $SOURCE."
            info "Using the local archive $SOURCE"
        else
            die "$SOURCE does not exist."
        fi
    else
        command -v curl >/dev/null 2>&1 || die "curl is required."
        local url="https://codeload.github.com/${REPO}/tar.gz/refs/heads/${BRANCH}"
        info "Downloading ${REPO} (${BRANCH})"
        if ! curl -fsSL --retry 3 "$url" -o "$TMP/theme.tar.gz"; then
            err "Could not download $url"
            die "Check the repository name and branch, and that the repository is public."
        fi
        tar -xzf "$TMP/theme.tar.gz" -C "$SRC" --strip-components=1 || die "The downloaded archive is not valid."
    fi
}

# ---------------------------------------------------------------------------------------------
# Node.js and Yarn (needed to compile the dashboard)
# ---------------------------------------------------------------------------------------------
# Debian and Ubuntu ship an unrelated program named "yarn" (package cmdtest), so the name alone proves nothing.
yarn_ready() {
    command -v yarn >/dev/null 2>&1 && [[ "$(yarn --version 2>/dev/null | head -n1)" =~ ^1\. ]]
}

node_ready() {
    command -v node >/dev/null 2>&1 && yarn_ready \
        && [[ "$(node -p 'process.versions.node.split(".")[0]' 2>/dev/null || echo 0)" -ge 22 ]]
}

ensure_node() {
    if node_ready; then
        return 0
    fi

    warn "Compiling the dashboard needs Node.js 22 or newer and Yarn, which are missing or too old."
    if command -v apt-get >/dev/null 2>&1; then
        if [[ "$INSTALL_NODE" == true ]] || { [[ "$ASSUME_YES" != true && "$DRY_RUN" != true ]] && ask "Install Node.js 22 and Yarn now (from nodesource.com)?"; }; then
            if [[ "$DRY_RUN" == true ]]; then
                info "Dry run: Node.js and Yarn would be installed here."
                return 0
            fi
            curl -fsSL https://deb.nodesource.com/setup_22.x | bash - || die "Could not add the Node.js repository."
            apt-get install -y nodejs || die "Could not install Node.js."
            # Into /usr/local, so it never collides with the unrelated /usr/bin/yarn of the cmdtest package.
            npm install -g yarn --prefix /usr/local || npm install -g yarn --force || die "Could not install Yarn."
            hash -r
            if ! node_ready; then
                if dpkg -s cmdtest >/dev/null 2>&1; then
                    die "The package cmdtest provides another program named yarn. Run: apt-get remove -y cmdtest, then run this command again."
                fi
                die "Node.js 22 and Yarn 1.x are still not available."
            fi
            ok "Node.js $(node -v) and Yarn $(yarn -v) installed"
            return 0
        fi
    fi

    err "Install Node.js 22+ and Yarn 1.x, then run this command again."
    err "Or add --skip-build and compile later with: cd $PANEL_PATH && yarn install && yarn build:production"
    exit 1
}

# Compiling the dashboard needs a lot of memory. On a small server, borrow some disk as swap.
ensure_swap() {
    local mem_kb=0 swap_kb=0
    mem_kb="$(awk '/^MemTotal:/ {print $2}' "$MEMINFO_FILE" 2>/dev/null || echo 0)"
    swap_kb="$(awk '/^SwapTotal:/ {print $2}' "$MEMINFO_FILE" 2>/dev/null || echo 0)"
    if (((mem_kb + swap_kb) / 1024 >= 3000)); then
        return 0
    fi
    info "Little memory available, adding a temporary 2 GB swap file for the compilation"
    if fallocate -l 2G "$SWAP_FILE" 2>/dev/null && chmod 600 "$SWAP_FILE" && mkswap "$SWAP_FILE" >/dev/null 2>&1 && swapon "$SWAP_FILE" 2>/dev/null; then
        TEMP_SWAP=true
    else
        rm -f "$SWAP_FILE"
        warn "Could not add swap. If the compilation is killed, add memory or swap and run the command again."
    fi
}

# ---------------------------------------------------------------------------------------------
# UPDATE an existing panel
# ---------------------------------------------------------------------------------------------
restore_from() {
    local dir="$1"
    [[ -f "$dir/existing.txt" && -f "$dir/added.txt" ]] || die "$dir is not a backup made by this installer."

    if [[ "$ASSUME_YES" != true ]]; then
        ask "Put back the files saved in $dir? Files added by the install will be removed." || {
            info "Nothing was changed."
            exit 0
        }
    fi

    if artisan down >/dev/null 2>&1; then DOWN=true; fi

    local entry
    while IFS= read -r entry; do
        [[ -n "$entry" ]] && rm -rf "${PANEL_PATH:?}/${entry%/}"
    done < <(cat "$dir/added.txt" "$dir/existing.txt")

    if [[ -s "$dir/existing.txt" ]]; then
        tar -xzf "$dir/files.tar.gz" -C "$PANEL_PATH"
    fi

    while IFS= read -r entry; do
        [[ -n "$entry" && -e "$PANEL_PATH/${entry%/}" ]] && chown -R "$OWNER" "$PANEL_PATH/${entry%/}" || true
    done < <(cat "$dir/added.txt" "$dir/existing.txt")

    artisan view:clear >/dev/null 2>&1 || true
    artisan config:clear >/dev/null 2>&1 || true
    ok "Files restored from $dir"
    warn "The database was not changed. The new tables and columns stay in place and are harmless."
    warn "Compile the dashboard again if you restored it: cd $PANEL_PATH && yarn build:production"
}

run_restore() {
    step "Restoring the previous files"
    panel_exists || die "No Pterodactyl panel found in $PANEL_PATH. If it is somewhere else, add --path=/your/panel/folder"
    OWNER="$(stat -c '%U:%G' "$PANEL_PATH/artisan" 2>/dev/null || echo root:root)"

    local target="${FROM:-}"
    if [[ -z "$target" ]]; then
        [[ -f "$BACKUP_ROOT/latest" ]] || die "No backup found in $BACKUP_ROOT."
        target="$(cat "$BACKUP_ROOT/latest")"
    fi
    restore_from "$target"
}

rollback() {
    warn "Putting the previous files back..."
    local entry
    while IFS= read -r entry; do
        [[ -n "$entry" ]] && rm -rf "${PANEL_PATH:?}/${entry%/}"
    done < <(cat "$BACKUP_DIR/added.txt" "$BACKUP_DIR/existing.txt")
    if [[ -s "$BACKUP_DIR/existing.txt" ]]; then
        tar -xzf "$BACKUP_DIR/files.tar.gz" -C "$PANEL_PATH"
    fi
    while IFS= read -r entry; do
        [[ -n "$entry" && -e "$PANEL_PATH/${entry%/}" ]] && chown -R "$OWNER" "$PANEL_PATH/${entry%/}" || true
    done < <(cat "$BACKUP_DIR/added.txt" "$BACKUP_DIR/existing.txt")
    artisan view:clear >/dev/null 2>&1 || true
}

on_update_error() {
    if [[ "$INSTALLING" == true ]]; then
        INSTALLING=false
        err "Something went wrong (line $1)."
        rollback
        err "The previous files were put back."
    fi
}

run_update() {
    if [[ ! -f "$PANEL_PATH/artisan" || ! -f "$PANEL_PATH/config/app.php" || ! -d "$PANEL_PATH/public/themes/pterodactyl/css" ]]; then
        err "No Pterodactyl panel found in $PANEL_PATH."
        die "If it is installed somewhere else, add --path=/your/panel/folder"
    fi

    if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
        die "PHP was not found (looked for \"$PHP_BIN\"). Set PHP_BIN=/path/to/php if it is installed elsewhere."
    fi

    OWNER="$(stat -c '%U:%G' "$PANEL_PATH/artisan" 2>/dev/null || echo root:root)"
    info "Panel: $PANEL_PATH (files owned by $OWNER)"

    step "Getting the files"
    fetch_source

    if [[ "$CSS_ONLY" == true ]]; then
        ENTRIES=("public/themes/pterodactyl/css/pterodactyl.css")
    else
        [[ -f "$SRC/install-manifest.txt" ]] || die "install-manifest.txt is missing from the downloaded files."
        mapfile -t ENTRIES < <(grep -vE '^[[:space:]]*(#|$)' "$SRC/install-manifest.txt" | sed 's/[[:space:]]*$//')
    fi

    [[ "${#ENTRIES[@]}" -gt 0 ]] || die "The list of files to install is empty."

    local entry
    for entry in "${ENTRIES[@]}"; do
        case "$entry" in
            /* | *..*) die "Refusing the unsafe path \"$entry\" in the file list." ;;
        esac
        [[ -e "$SRC/$entry" ]] || die "The file list mentions \"$entry\" but it is not in the downloaded files."
    done
    ok "${#ENTRIES[@]} entries to install, all present"

    local src_version panel_version_found
    src_version="$(panel_version "$SRC/config/app.php" 2>/dev/null || true)"
    panel_version_found="$(panel_version "$PANEL_PATH/config/app.php" 2>/dev/null || true)"
    if [[ "$CSS_ONLY" != true && -n "$src_version" && -n "$panel_version_found" && "$src_version" != "$panel_version_found" ]]; then
        warn "This theme was made for Pterodactyl $src_version and your panel is $panel_version_found."
        warn "It replaces core files (routes, models, middleware). On another version the panel could break."
        if [[ "$FORCE" != true ]]; then
            die "Update the panel to $src_version first, or run again with --force if you accept the risk."
        fi
    fi

    local do_migrate=true do_build=true
    [[ "$SKIP_MIGRATE" == true ]] && do_migrate=false
    [[ "$SKIP_BUILD" == true ]] && do_build=false

    if [[ "$do_build" == true ]]; then
        ensure_node
    fi

    step "What will happen"
    info "Back up the files that will be replaced (in $BACKUP_ROOT/$TS)"
    info "Put the panel in maintenance mode while the files are replaced"
    info "Copy ${#ENTRIES[@]} entries onto $PANEL_PATH"
    [[ "$do_migrate" == true ]] && info "Run the database migrations (adds columns to users, and the admin_roles table)"
    [[ "$do_build" == true ]] && info "Install the dependencies and compile the dashboard (a few minutes)"
    info "Clear the caches, restart the queue worker and bring the panel back"
    if [[ "$do_migrate" == true ]]; then
        warn "The migrations change your database. Make a database backup first if you have not."
    fi

    if [[ "$DRY_RUN" == true ]]; then
        ok "Dry run finished, nothing was changed."
        return 0
    fi

    ask "Continue?" || {
        info "Nothing was changed."
        exit 0
    }

    step "Backing up"
    BACKUP_DIR="$BACKUP_ROOT/$TS"
    mkdir -p "$BACKUP_DIR"
    : >"$BACKUP_DIR/existing.txt"
    : >"$BACKUP_DIR/added.txt"

    for entry in "${ENTRIES[@]}"; do
        if [[ -e "$PANEL_PATH/${entry%/}" ]]; then
            echo "$entry" >>"$BACKUP_DIR/existing.txt"
        else
            echo "$entry" >>"$BACKUP_DIR/added.txt"
        fi
    done

    if [[ "$do_build" == true && -d "$PANEL_PATH/public/assets" ]]; then
        echo "public/assets/" >>"$BACKUP_DIR/existing.txt"
    fi

    if [[ -s "$BACKUP_DIR/existing.txt" ]]; then
        tar -czf "$BACKUP_DIR/files.tar.gz" -C "$PANEL_PATH" -T "$BACKUP_DIR/existing.txt"
    fi
    echo "$BACKUP_DIR" >"$BACKUP_ROOT/latest"
    ok "Saved in $BACKUP_DIR"

    step "Installing"
    INSTALLING=true
    trap 'on_update_error $LINENO' ERR

    if artisan down >/dev/null 2>&1; then
        DOWN=true
        ok "Panel in maintenance mode"
    fi

    for entry in "${ENTRIES[@]}"; do
        if [[ "$entry" == */ ]]; then
            mkdir -p "$PANEL_PATH/$entry"
            cp -a "$SRC/${entry}." "$PANEL_PATH/$entry"
        else
            mkdir -p "$(dirname "$PANEL_PATH/$entry")"
            cp -f "$SRC/$entry" "$PANEL_PATH/$entry"
        fi
        chown -R "$OWNER" "$PANEL_PATH/${entry%/}" 2>/dev/null || warn "Could not set the owner of $entry (not fatal)."
    done
    ok "Files copied"

    if command -v composer >/dev/null 2>&1; then
        COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload -o -d "$PANEL_PATH" --no-interaction >/dev/null 2>&1 \
            && ok "Autoloader refreshed" \
            || warn "Could not refresh the Composer autoloader (not fatal)."
    fi

    if [[ "$do_migrate" == true ]]; then
        step "Updating the database"
        if ! artisan migrate --force; then
            err "The database migration failed."
            INSTALLING=false
            rollback
            err "The previous files were put back. Fix the error above and run the installer again."
            exit 1
        fi
        ok "Database up to date"
    fi
    INSTALLING=false

    local env_file="$PANEL_PATH/.env"
    if [[ "$CSS_ONLY" != true && -f "$env_file" ]] && grep -qE '^APP_ENVIRONMENT_ONLY=true' "$env_file"; then
        if [[ "$ENABLE_SETTINGS_UI" == true ]] || { [[ "$ASSUME_YES" != true ]] && ask "Let the admin Settings page control the panel (sets APP_ENVIRONMENT_ONLY=false)? Needed for the registration and Discord switches."; }; then
            sed -i 's/^APP_ENVIRONMENT_ONLY=true/APP_ENVIRONMENT_ONLY=false/' "$env_file"
            ok "APP_ENVIRONMENT_ONLY is now false"
        else
            warn "APP_ENVIRONMENT_ONLY is still true: settings saved in the admin area will be ignored."
            warn "Set it to false in $env_file to use the registration and Discord switches."
        fi
    fi

    local cmd
    for cmd in view:clear config:clear cache:clear; do
        artisan "$cmd" >/dev/null 2>&1 || warn "php artisan $cmd did not run (not fatal)."
    done
    artisan queue:restart >/dev/null 2>&1 || true
    chown -R "$OWNER" "$PANEL_PATH/storage" "$PANEL_PATH/bootstrap/cache" 2>/dev/null || true
    ok "Caches cleared"

    local dashboard_failed=false
    if [[ "$do_build" == true ]]; then
        step "Compiling the dashboard"
        ensure_swap
        export NODE_OPTIONS="${NODE_OPTIONS:---max-old-space-size=3072}"
        if (cd "$PANEL_PATH" && yarn install --network-timeout 600000) \
            && (cd "$PANEL_PATH" && NODE_ENV=production yarn run build:production); then
            chown -R "$OWNER" "$PANEL_PATH/public/assets" 2>/dev/null || true
            ok "Dashboard compiled"
        else
            err "Compiling the dashboard failed."
            if [[ -s "$BACKUP_DIR/files.tar.gz" ]] && grep -qx "public/assets/" "$BACKUP_DIR/existing.txt"; then
                rm -rf "${PANEL_PATH:?}/public/assets"
                tar -xzf "$BACKUP_DIR/files.tar.gz" -C "$PANEL_PATH" public/assets
                warn "The previous compiled dashboard was put back, so the panel still works."
            fi
            warn "The PHP side and the admin area are installed. Fix the error above, then compile with:"
            warn "  cd $PANEL_PATH && yarn install && yarn build:production"
            dashboard_failed=true
        fi
    fi

    if [[ "$DOWN" == true ]]; then
        artisan up >/dev/null 2>&1 || true
        DOWN=false
        ok "Panel is back online"
    fi

    echo ""
    ok "${BOLD}Update finished.${NC}"
    echo ""
    info "Do a hard refresh in your browser (Ctrl+Shift+R): the admin CSS keeps the same address, so it can stay cached."
    if [[ "$CSS_ONLY" != true ]]; then
        info "Registration and Discord: Admin > Settings. Add the Redirect URI shown there to your Discord application."
        info "Staff roles: Admin > Staff Roles."
    fi
    info "Backup of the replaced files: $BACKUP_DIR"
    info "To go back: bash <(curl -s ${INSTALL_URL}) --restore"
    if [[ "$dashboard_failed" == true ]]; then
        exit 2
    fi
}

# ---------------------------------------------------------------------------------------------
# UNINSTALL: back to the official panel, keeping everything that was created
# ---------------------------------------------------------------------------------------------
# Gets the official panel files (the release archive: sources, views and the compiled dashboard).
fetch_stock() {
    STOCK="$TMP/stock"
    mkdir -p "$STOCK"

    if [[ -n "$STOCK_SOURCE" ]]; then
        if [[ -d "$STOCK_SOURCE" ]]; then
            STOCK="$(cd "$STOCK_SOURCE" && pwd)"
            info "Using the local folder $STOCK for the official files"
        elif [[ -f "$STOCK_SOURCE" ]]; then
            tar -xzf "$STOCK_SOURCE" -C "$STOCK" || die "Could not read $STOCK_SOURCE."
            info "Using the local archive $STOCK_SOURCE for the official files"
        else
            die "$STOCK_SOURCE does not exist."
        fi
    else
        command -v curl >/dev/null 2>&1 || die "curl is required."
        local url="https://github.com/pterodactyl/panel/releases/download/v${STOCK_VERSION}/panel.tar.gz"
        info "Downloading the official panel ${STOCK_VERSION}"
        if ! curl -fsSL --retry 3 "$url" -o "$TMP/stock.tar.gz"; then
            err "Could not download $url"
            die "Check the version number, or give the files yourself with --stock-source=."
        fi
        tar -xzf "$TMP/stock.tar.gz" -C "$STOCK" || die "The downloaded archive is not valid."
    fi

    if [[ ! -f "$STOCK/artisan" || ! -f "$STOCK/config/app.php" || ! -f "$STOCK/public/assets/manifest.json" || ! -d "$STOCK/resources/scripts" ]]; then
        die "The official files are incomplete (artisan, config, compiled dashboard or resources are missing)."
    fi
}

run_uninstall() {
    step "Going back to the official panel"
    panel_exists || die "No Pterodactyl panel found in $PANEL_PATH. If it is somewhere else, add --path=/your/panel/folder"
    if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
        die "PHP was not found (looked for \"$PHP_BIN\"). Set PHP_BIN=/path/to/php if it is installed elsewhere."
    fi
    OWNER="$(stat -c '%U:%G' "$PANEL_PATH/artisan" 2>/dev/null || echo root:root)"

    local current
    current="$(panel_version "$PANEL_PATH/config/app.php" 2>/dev/null || true)"
    [[ -n "$STOCK_VERSION" ]] || STOCK_VERSION="${current:-1.15.1}"
    [[ "$STOCK_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || die "\"$STOCK_VERSION\" is not a version number like 1.15.1."

    step "Getting the files"
    fetch_source
    [[ -f "$SRC/install-manifest.txt" ]] || die "install-manifest.txt is missing from the downloaded files."
    mapfile -t ENTRIES < <(grep -vE '^[[:space:]]*(#|$)' "$SRC/install-manifest.txt" | sed 's/[[:space:]]*$//')
    [[ "${#ENTRIES[@]}" -gt 0 ]] || die "The list of files is empty."
    # The dashboard is compiled code that the theme replaced: it comes back from the official archive too.
    ENTRIES+=("public/assets/")

    local entry
    for entry in "${ENTRIES[@]}"; do
        case "$entry" in
            /* | *..*) die "Refusing the unsafe path \"$entry\" in the file list." ;;
        esac
    done

    fetch_stock

    local stock_found
    stock_found="$(panel_version "$STOCK/config/app.php" 2>/dev/null || true)"
    if [[ -n "$current" && -n "$stock_found" && "$stock_found" != "$current" ]]; then
        warn "The official files are version $stock_found but your panel is $current."
        if [[ "$FORCE" != true ]]; then
            die "Use --stock-version=$current to get the matching files, or --force if you accept the risk."
        fi
    fi

    local replaced=0 removed=0
    for entry in "${ENTRIES[@]}"; do
        if [[ -e "$STOCK/${entry%/}" ]]; then
            replaced=$((replaced + 1))
        elif [[ -e "$PANEL_PATH/${entry%/}" ]]; then
            removed=$((removed + 1))
        fi
    done
    ok "Official panel ${stock_found:-$STOCK_VERSION} ready: $replaced entries to put back, $removed theme-only entries to remove"

    step "What will happen"
    info "Back up the theme files first (in $BACKUP_ROOT/uninstall-$TS), so the theme can be put back"
    info "Put the panel in maintenance mode"
    info "Put back the official files and dashboard, and remove the files only the theme has"
    info "Clear the caches, restart the queue worker and bring the panel back"
    ok "Kept as they are: servers, users, nodes, allocations, backups, databases, schedules, API keys, .env"
    warn "The theme's registration, Discord login and staff roles stop working. People who only had a staff role"
    warn "(and are not administrators) lose their admin access. Their accounts and servers are untouched."
    info "The database is not changed: the extra columns and the admin_roles table stay, and do no harm."

    if [[ "$DRY_RUN" == true ]]; then
        ok "Dry run finished, nothing was changed."
        return 0
    fi

    ask "Go back to the official panel?" || {
        info "Nothing was changed."
        exit 0
    }

    step "Backing up"
    BACKUP_DIR="$BACKUP_ROOT/uninstall-$TS"
    mkdir -p "$BACKUP_DIR"
    : >"$BACKUP_DIR/existing.txt"
    : >"$BACKUP_DIR/added.txt"
    for entry in "${ENTRIES[@]}"; do
        if [[ -e "$PANEL_PATH/${entry%/}" ]]; then
            echo "$entry" >>"$BACKUP_DIR/existing.txt"
        elif [[ -e "$STOCK/${entry%/}" ]]; then
            echo "$entry" >>"$BACKUP_DIR/added.txt"
        fi
    done
    if [[ -s "$BACKUP_DIR/existing.txt" ]]; then
        tar -czf "$BACKUP_DIR/files.tar.gz" -C "$PANEL_PATH" -T "$BACKUP_DIR/existing.txt"
    fi
    echo "$BACKUP_DIR" >"$BACKUP_ROOT/latest"
    ok "Saved in $BACKUP_DIR"

    step "Putting the official files back"
    INSTALLING=true
    trap 'on_update_error $LINENO' ERR

    if artisan down >/dev/null 2>&1; then
        DOWN=true
        ok "Panel in maintenance mode"
    fi

    local target
    for entry in "${ENTRIES[@]}"; do
        target="$PANEL_PATH/${entry%/}"
        rm -rf "$target"
        if [[ -e "$STOCK/${entry%/}" ]]; then
            mkdir -p "$(dirname "$target")"
            if [[ "$entry" == */ ]]; then
                mkdir -p "$target"
                cp -a "$STOCK/${entry}." "$target/"
            else
                cp -f "$STOCK/$entry" "$target"
            fi
            chown -R "$OWNER" "$target" 2>/dev/null || warn "Could not set the owner of $entry (not fatal)."
        fi
    done
    INSTALLING=false
    ok "Official files in place"

    if command -v composer >/dev/null 2>&1; then
        COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload -o -d "$PANEL_PATH" --no-interaction >/dev/null 2>&1 \
            && ok "Autoloader refreshed" \
            || warn "Could not refresh the Composer autoloader (not fatal)."
    fi

    local cmd
    for cmd in optimize:clear view:clear config:clear route:clear cache:clear; do
        artisan "$cmd" >/dev/null 2>&1 || true
    done
    artisan queue:restart >/dev/null 2>&1 || true
    chown -R "$OWNER" "$PANEL_PATH/storage" "$PANEL_PATH/bootstrap/cache" 2>/dev/null || true
    ok "Caches cleared"

    if [[ "$DOWN" == true ]]; then
        artisan up >/dev/null 2>&1 || true
        DOWN=false
        ok "Panel is back online"
    fi

    echo ""
    ok "${BOLD}The official panel is back.${NC}"
    echo ""
    info "Do a hard refresh in your browser (Ctrl+Shift+R) to drop the cached design."
    info "The theme files are saved in $BACKUP_DIR"
    info "To put the theme back exactly as it was: bash <(curl -s ${INSTALL_URL}) --restore"
    info "Or install the latest theme again:       bash <(curl -s ${INSTALL_URL}) --update"
}

# ---------------------------------------------------------------------------------------------
# System checks for a new install
# ---------------------------------------------------------------------------------------------
OS_ID=""
OS_VERSION=""
OS_CODENAME=""

detect_os() {
    [[ -f "$OS_RELEASE_FILE" ]] || die "Cannot tell which system this is ($OS_RELEASE_FILE is missing)."
    # shellcheck source=/dev/null
    OS_ID="$(. "$OS_RELEASE_FILE" && echo "${ID:-}")"
    # shellcheck source=/dev/null
    OS_VERSION="$(. "$OS_RELEASE_FILE" && echo "${VERSION_ID:-}")"
    # shellcheck source=/dev/null
    OS_CODENAME="$(. "$OS_RELEASE_FILE" && echo "${VERSION_CODENAME:-}")"

    case "${OS_ID}:${OS_VERSION}" in
        ubuntu:22.04 | ubuntu:24.04 | debian:11 | debian:12) ;;
        *)
            err "${OS_ID} ${OS_VERSION} is not supported for a new install."
            die "Supported: Ubuntu 22.04 and 24.04, Debian 11 and 12. On another system, install the panel first by hand, then run this with --update."
            ;;
    esac
    info "System: ${OS_ID} ${OS_VERSION}"
}

is_ip_address() {
    [[ "$1" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]
}

apt_install() {
    DEBIAN_FRONTEND=noninteractive apt-get install -y -qq "$@"
}

# ---------------------------------------------------------------------------------------------
# NEW PANEL install
# ---------------------------------------------------------------------------------------------
gather_panel_answers() {
    local detected_ip detected_tz
    detected_ip="$(hostname -I 2>/dev/null | awk '{print $1}')"
    detected_tz="$(timedatectl show -p Timezone --value 2>/dev/null || cat /etc/timezone 2>/dev/null || echo UTC)"

    step "Your details"
    prompt FQDN "Domain or IP address of the panel (for example panel.example.com)" "${detected_ip:-localhost}"
    prompt ADMIN_EMAIL "Your email address" ""
    prompt ADMIN_USER "Username of the first administrator" "admin"
    prompt ADMIN_FIRST "First name" "Admin"
    prompt ADMIN_LAST "Last name" "User"
    prompt TIMEZONE "Timezone" "${detected_tz:-UTC}"
    if [[ -z "$ADMIN_PASSWORD" ]]; then
        prompt ADMIN_PASSWORD "Administrator password (leave empty for a random one)" "" secret
    fi

    [[ "$FQDN" =~ ^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$ ]] || die "\"$FQDN\" is not a valid domain or IP address."
    [[ "$ADMIN_EMAIL" =~ ^[^[:space:]@]+@[^[:space:]@]+\.[^[:space:]@]+$ ]] || die "A valid email address is required (--email=you@example.com)."
    [[ "$ADMIN_USER" =~ ^[a-z0-9][a-z0-9_.-]*[a-z0-9]$ && "${#ADMIN_USER}" -ge 3 ]] || die "The username must be 3+ characters: lowercase letters, digits, dots, dashes and underscores."
    [[ "$TIMEZONE" =~ ^[A-Za-z0-9_/+-]+$ ]] || die "\"$TIMEZONE\" is not a valid timezone."
    if [[ -n "$ADMIN_PASSWORD" && "${#ADMIN_PASSWORD}" -lt 8 ]]; then
        die "The administrator password must be at least 8 characters."
    fi

    if is_ip_address "$FQDN" || [[ "$FQDN" == "localhost" ]]; then
        if [[ "$SSL_MODE" == "yes" ]]; then
            die "An SSL certificate needs a domain name, not an IP address."
        fi
        SSL_MODE="no"
    fi
}

install_packages() {
    step "Installing the web server, PHP, database and Redis"
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -qq
    apt_install ca-certificates curl gnupg tar unzip git openssl cron

    if [[ "$OS_ID" == "ubuntu" ]]; then
        apt_install software-properties-common
        LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php >/dev/null
    else
        apt_install apt-transport-https lsb-release
        curl -fsSL -o "$TMP/sury-keyring.deb" https://packages.sury.org/debsuryorg-archive-keyring.deb
        dpkg -i "$TMP/sury-keyring.deb" >/dev/null
        echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ ${OS_CODENAME} main" >"${APT_SOURCES_DIR}/php.list"
    fi
    apt-get update -qq

    apt_install "php${PHP_VERSION}" "php${PHP_VERSION}-cli" "php${PHP_VERSION}-common" "php${PHP_VERSION}-fpm" \
        "php${PHP_VERSION}-gd" "php${PHP_VERSION}-mysql" "php${PHP_VERSION}-mbstring" "php${PHP_VERSION}-bcmath" \
        "php${PHP_VERSION}-xml" "php${PHP_VERSION}-curl" "php${PHP_VERSION}-zip" \
        mariadb-server nginx redis-server
    PHP_BIN="php${PHP_VERSION}"

    systemctl enable --now mariadb redis-server "php${PHP_VERSION}-fpm" >/dev/null 2>&1 || true
    ok "Packages installed (PHP ${PHP_VERSION}, MariaDB, nginx, Redis)"

    if [[ ! -x "$COMPOSER_BIN" ]]; then
        curl -fsSL https://getcomposer.org/installer | "$PHP_BIN" -- --install-dir="$(dirname "$COMPOSER_BIN")" --filename="$(basename "$COMPOSER_BIN")" >/dev/null
    fi
    ok "Composer ready"
}

create_database() {
    step "Creating the database"
    DB_PASSWORD="$(random_string 32)"

    local existing_db existing_user
    existing_db="$(mysql -u root -Nse "SHOW DATABASES LIKE '${DB_NAME}'" 2>/dev/null || true)"
    existing_user="$(mysql -u root -Nse "SELECT User FROM mysql.user WHERE User='${DB_USER}' AND Host='127.0.0.1'" 2>/dev/null || true)"

    if [[ "$RESUMING" == true ]]; then
        # Left over by an install of ours that did not finish, so it holds nothing worth keeping.
        mysql -u root -e "DROP DATABASE IF EXISTS \`${DB_NAME}\`;"
    else
        [[ -z "$existing_db" ]] || die "A database named \"${DB_NAME}\" already exists on this server. Rename or drop it first, or install the panel by hand and use --update."
        [[ -z "$existing_user" ]] || die "A MySQL user named \"${DB_USER}\" already exists on this server. Remove it first, or install the panel by hand and use --update."
    fi

    mysql -u root -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';"
    mysql -u root -e "ALTER USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';"
    mysql -u root -e "CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql -u root -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1' WITH GRANT OPTION; FLUSH PRIVILEGES;"
    ok "Database ${DB_NAME} and user ${DB_USER} created"
}

place_panel_files() {
    step "Installing the panel files"
    mkdir -p "$PANEL_PATH"
    # Everything from the repository, without folders that are only useful for development.
    (cd "$SRC" && tar cf - --exclude=./node_modules --exclude=./vendor --exclude=./.git --exclude=./.env --exclude='./public/assets/*.js' .) \
        | (cd "$PANEL_PATH" && tar xf -)
    mkdir -p "$PANEL_PATH/storage/app" "$PANEL_PATH/storage/framework/cache" "$PANEL_PATH/storage/framework/sessions" \
        "$PANEL_PATH/storage/framework/views" "$PANEL_PATH/storage/logs" "$PANEL_PATH/bootstrap/cache" "$PANEL_PATH/public/assets"
    chmod -R 755 "$PANEL_PATH/storage" "$PANEL_PATH/bootstrap/cache"
    [[ -f "$PANEL_PATH/.env.example" ]] || die "The downloaded files are not a Pterodactyl panel (.env.example is missing)."
    cp "$PANEL_PATH/.env.example" "$PANEL_PATH/.env"
    ok "Files placed in $PANEL_PATH"
}

install_php_dependencies() {
    step "Installing the PHP dependencies (Composer)"
    (cd "$PANEL_PATH" && COMPOSER_ALLOW_SUPERUSER=1 "$PHP_BIN" "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction)
    ok "Dependencies installed"
}

configure_panel() {
    step "Configuring the panel"
    artisan key:generate --force
    # --telemetry=0 turns anonymous telemetry off. The text "false" would not: the panel reads it as true.
    artisan p:environment:setup \
        --author="$ADMIN_EMAIL" --url="$PANEL_URL" --timezone="$TIMEZONE" \
        --cache=redis --session=redis --queue=redis \
        --redis-host=127.0.0.1 --redis-pass=null --redis-port=6379 \
        --settings-ui=true --telemetry=0
    artisan p:environment:database \
        --host=127.0.0.1 --port=3306 --database="$DB_NAME" --username="$DB_USER" --password="$DB_PASSWORD"
    ok "Environment configured"

    step "Creating the tables and the first administrator"
    artisan migrate --seed --force
    if [[ -z "$ADMIN_PASSWORD" ]]; then
        ADMIN_PASSWORD="$(random_string 20)"
        GENERATED_PASSWORD=true
    fi
    artisan p:user:make --email="$ADMIN_EMAIL" --username="$ADMIN_USER" --name-first="$ADMIN_FIRST" --name-last="$ADMIN_LAST" \
        --password="$ADMIN_PASSWORD" --admin=1
    ok "Administrator ${ADMIN_USER} created"
}

compile_dashboard() {
    step "Compiling the dashboard (a few minutes)"
    ensure_swap
    export NODE_OPTIONS="${NODE_OPTIONS:---max-old-space-size=3072}"
    (cd "$PANEL_PATH" && yarn install --network-timeout 600000 && NODE_ENV=production yarn run build:production)
    ok "Dashboard compiled"
}

setup_services() {
    step "Setting up the services"
    chown -R "${WEB_USER}:${WEB_USER}" "$PANEL_PATH"

    cat >"${CRON_DIR}/pterodactyl" <<EOF
* * * * * ${WEB_USER} /usr/bin/${PHP_BIN} ${PANEL_PATH}/artisan schedule:run >> /dev/null 2>&1
EOF
    chmod 644 "${CRON_DIR}/pterodactyl"

    cat >"${SYSTEMD_DIR}/pteroq.service" <<EOF
# Pterodactyl Queue Worker File
# ----------------------------------

[Unit]
Description=Pterodactyl Queue Worker
After=redis-server.service

[Service]
# On some systems the user and group might be different.
# Some systems use \`apache\` or \`nginx\` as the user and group.
User=${WEB_USER}
Group=${WEB_USER}
Restart=always
ExecStart=/usr/bin/${PHP_BIN} ${PANEL_PATH}/artisan queue:work --queue=high,standard,low --sleep=3 --tries=3
StartLimitInterval=180
StartLimitBurst=30
RestartSec=5s

[Install]
WantedBy=multi-user.target
EOF
    systemctl daemon-reload
    systemctl enable --now pteroq.service >/dev/null 2>&1 || warn "Could not start the queue worker (pteroq). Check: systemctl status pteroq"
    ok "Scheduler (cron) and queue worker (pteroq) installed"
}

write_nginx_config() {
    step "Setting up the web server"
    mkdir -p "${NGINX_DIR}/sites-available" "${NGINX_DIR}/sites-enabled"
    rm -f "${NGINX_DIR}/sites-enabled/default"

    cat >"${NGINX_DIR}/sites-available/pterodactyl.conf" <<'NGINX'
server {
    listen 80;
    server_name __FQDN__;

    root __PANEL_PATH__/public;
    index index.html index.htm index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    access_log off;
    error_log  /var/log/nginx/pterodactyl.app-error.log error;

    # allow larger file uploads and longer script runtimes
    client_max_body_size 100m;
    client_body_timeout 120s;

    sendfile off;

    location ~ \.php$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/run/php/php__PHP_VERSION__-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param PHP_VALUE "upload_max_filesize = 100M \n post_max_size=100M";
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param HTTP_PROXY "";
        fastcgi_intercept_errors off;
        fastcgi_buffer_size 16k;
        fastcgi_buffers 4 16k;
        fastcgi_connect_timeout 300;
        fastcgi_send_timeout 300;
        fastcgi_read_timeout 300;
    }

    location ~ /\.ht {
        deny all;
    }
}
NGINX
    sed -i "s|__FQDN__|${FQDN}|g; s|__PANEL_PATH__|${PANEL_PATH}|g; s|__PHP_VERSION__|${PHP_VERSION}|g" "${NGINX_DIR}/sites-available/pterodactyl.conf"
    ln -sf "${NGINX_DIR}/sites-available/pterodactyl.conf" "${NGINX_DIR}/sites-enabled/pterodactyl.conf"

    nginx -t || die "The web server configuration is not valid (see above)."
    systemctl restart nginx
    ok "nginx serves ${FQDN}"

    if command -v ufw >/dev/null 2>&1 && ufw status 2>/dev/null | grep -q "Status: active"; then
        ufw allow 80/tcp >/dev/null 2>&1 || true
        ufw allow 443/tcp >/dev/null 2>&1 || true
        ok "Firewall: ports 80 and 443 opened"
    fi
}

setup_ssl() {
    PANEL_URL="http://${FQDN}"
    if [[ "$SSL_MODE" == "no" ]]; then
        return 0
    fi
    step "Getting a free SSL certificate (Let's Encrypt)"
    apt_install certbot python3-certbot-nginx
    if certbot --nginx --redirect --non-interactive --agree-tos -m "$ADMIN_EMAIL" -d "$FQDN"; then
        PANEL_URL="https://${FQDN}"
        ok "SSL certificate installed"
    else
        warn "Could not get a certificate. Make sure ${FQDN} points to this server and port 80 is open."
        warn "The panel works over http for now. Once the DNS is right, run: certbot --nginx -d ${FQDN}"
    fi
}

save_credentials() {
    (
        umask 077
        cat >"$CREDENTIALS_FILE" <<EOF
Pterodactyl panel - saved by the installer on $(date)

Panel:       ${PANEL_URL}
Admin login: ${ADMIN_USER}  (${ADMIN_EMAIL})
Password:    ${ADMIN_PASSWORD}

Database:    ${DB_NAME} / user ${DB_USER} / password ${DB_PASSWORD}
Panel files: ${PANEL_PATH}

Delete this file once you have noted these details.
EOF
    )
    chmod 600 "$CREDENTIALS_FILE"
}

run_panel_install() {
    local marker="$PANEL_PATH/.install-incomplete"

    if panel_exists && [[ ! -f "$marker" ]]; then
        die "A panel is already installed in $PANEL_PATH. To update it with this theme, run without --panel (or with --update)."
    fi
    if [[ -e "$PANEL_PATH" && ! -f "$marker" && -n "$(ls -A "$PANEL_PATH" 2>/dev/null)" ]]; then
        die "$PANEL_PATH already exists and is not empty. Use another folder with --path=, or empty it first."
    fi

    detect_os
    gather_panel_answers

    step "Getting the files"
    fetch_source
    [[ -f "$SRC/artisan" && -f "$SRC/composer.json" ]] || die "The downloaded files are not a Pterodactyl panel."
    ok "Panel files found (version $(panel_version "$SRC/config/app.php" 2>/dev/null || echo unknown))"

    step "What will happen"
    info "Install nginx, PHP ${PHP_VERSION}, MariaDB and Redis with apt"
    info "Create the database ${DB_NAME}, and the panel in $PANEL_PATH"
    info "Compile the dashboard with Node.js 22 (installed if missing)"
    info "Create the first administrator: ${ADMIN_USER} (${ADMIN_EMAIL})"
    if [[ "$SSL_MODE" == "no" ]]; then
        info "Serve http://${FQDN} (no SSL certificate)"
    else
        info "Serve ${FQDN} and get a free SSL certificate for it"
    fi
    warn "Use a fresh server: this changes the web server, PHP and database configuration."

    if [[ "$DRY_RUN" == true ]]; then
        ok "Dry run finished, nothing was changed."
        return 0
    fi

    ask "Continue?" || {
        info "Nothing was changed."
        exit 0
    }
    require_root

    if [[ -f "$marker" ]]; then
        warn "A previous install did not finish. Starting again from scratch."
        RESUMING=true
        rm -rf "${PANEL_PATH:?}"
    fi

    trap 'err "The install stopped at line $LINENO. Fix the problem above and run the same command again: it can be repeated safely."' ERR

    install_packages
    if [[ "$SKIP_BUILD" != true ]]; then
        INSTALL_NODE=true
        ensure_node
    fi
    create_database
    place_panel_files
    : >"$marker"
    install_php_dependencies
    write_nginx_config
    setup_ssl
    configure_panel
    if [[ "$SKIP_BUILD" != true ]]; then
        compile_dashboard
    fi
    setup_services
    save_credentials
    rm -f "$marker"
    trap - ERR

    echo ""
    ok "${BOLD}The panel is installed.${NC}"
    echo ""
    info "Open:      ${PANEL_URL}"
    info "Username:  ${ADMIN_USER}"
    if [[ "${GENERATED_PASSWORD:-false}" == true ]]; then
        info "Password:  ${ADMIN_PASSWORD}   (generated, change it after logging in)"
    else
        info "Password:  the one you chose"
    fi
    info "These details are also saved in ${CREDENTIALS_FILE} (readable by root only)."
    info "Registration and Discord login: Admin > Settings. Staff roles: Admin > Staff Roles."
    info "Next: create a Location and a Node in the panel, then install Wings on the game server machine:"
    info "  bash <(curl -s ${INSTALL_URL}) --wings"
}

# ---------------------------------------------------------------------------------------------
# WINGS
# ---------------------------------------------------------------------------------------------
run_wings_install() {
    detect_os

    local arch
    case "$(uname -m)" in
        x86_64 | amd64) arch="amd64" ;;
        aarch64 | arm64) arch="arm64" ;;
        *) die "Wings is only available for x86_64 and arm64 machines." ;;
    esac

    step "What will happen (Wings)"
    info "Install Docker (from get.docker.com) and enable it"
    info "Download Wings for ${arch} to ${WINGS_BIN}"
    info "Create ${WINGS_DIR} and the wings service"
    if [[ -n "$WINGS_PANEL_URL" && -n "$WINGS_TOKEN" && -n "$WINGS_NODE" ]]; then
        info "Configure Wings for node ${WINGS_NODE} of ${WINGS_PANEL_URL} and start it"
    else
        info "Then you paste the node configuration from your panel and start the service"
    fi

    if [[ "$DRY_RUN" == true ]]; then
        ok "Dry run finished, nothing was changed."
        return 0
    fi
    ask "Continue?" || {
        info "Nothing was changed."
        exit 0
    }
    require_root
    command -v curl >/dev/null 2>&1 || {
        apt-get update -qq
        apt_install curl ca-certificates
    }

    step "Installing Docker"
    if ! command -v docker >/dev/null 2>&1; then
        curl -fsSL https://get.docker.com/ | CHANNEL=stable bash
    fi
    systemctl enable --now docker >/dev/null 2>&1 || warn "Could not start Docker. Check: systemctl status docker"
    ok "Docker ready"

    step "Installing Wings"
    mkdir -p "$WINGS_DIR"
    curl -fsSL -o "$WINGS_BIN" "https://github.com/pterodactyl/wings/releases/latest/download/wings_linux_${arch}"
    chmod u+x "$WINGS_BIN"

    cat >"${SYSTEMD_DIR}/wings.service" <<EOF
[Unit]
Description=Pterodactyl Wings Daemon
After=docker.service
Requires=docker.service
PartOf=docker.service

[Service]
User=root
WorkingDirectory=${WINGS_DIR}
LimitNOFILE=4096
PIDFile=/var/run/wings/daemon.pid
ExecStart=${WINGS_BIN}
Restart=on-failure
StartLimitInterval=180
StartLimitBurst=30
RestartSec=5s

[Install]
WantedBy=multi-user.target
EOF
    systemctl daemon-reload
    ok "Wings installed"

    if command -v ufw >/dev/null 2>&1 && ufw status 2>/dev/null | grep -q "Status: active"; then
        ufw allow 8080/tcp >/dev/null 2>&1 || true
        ufw allow 2022/tcp >/dev/null 2>&1 || true
        ok "Firewall: ports 8080 and 2022 opened"
    fi

    if [[ -n "$WINGS_PANEL_URL" && -n "$WINGS_TOKEN" && -n "$WINGS_NODE" ]]; then
        step "Configuring Wings"
        (cd "$WINGS_DIR" && "$WINGS_BIN" configure --panel-url "$WINGS_PANEL_URL" --token "$WINGS_TOKEN" --node "$WINGS_NODE")
        systemctl enable --now wings >/dev/null 2>&1 || warn "Could not start Wings. Check: systemctl status wings"
        ok "Wings configured and started"
    else
        echo ""
        info "Last step: in your panel, open Admin > Nodes > your node > Configuration."
        info "Paste the configuration into ${WINGS_DIR}/config.yml, then run:"
        info "  systemctl enable --now wings"
        info "Or, with an Application API token: ${WINGS_BIN} configure --panel-url URL --token TOKEN --node ID"
    fi
    info "Open ports 8080 (Wings) and 2022 (SFTP), plus the game ports you use, on this machine's firewall."
}

# ---------------------------------------------------------------------------------------------
# What to do
# ---------------------------------------------------------------------------------------------
choose_mode() {
    echo ""
    echo " What do you want to do?"
    echo "   1) Install the panel on this server (new install)"
    echo "   2) Install Wings, the game server daemon"
    echo "   3) Install the panel and Wings on this server"
    echo "   4) Update an existing panel with this theme"
    echo "   5) Restore the files saved by a previous update"
    echo "   6) Remove the theme and go back to the official panel (nothing is lost)"
    printf ' Choose [1-6]: '
    local choice=""
    read -r choice || true
    case "$choice" in
        1) DO_PANEL=true ;;
        2) DO_WINGS=true ;;
        3)
            DO_PANEL=true
            DO_WINGS=true
            ;;
        4) DO_UPDATE=true ;;
        5) RESTORE=true ;;
        6) UNINSTALL=true ;;
        *) die "That is not one of the choices." ;;
    esac
}

main() {
    banner

    if [[ "$RESTORE" == true ]]; then
        require_root
        run_restore
        exit 0
    fi

    if [[ "$DO_PANEL" != true && "$DO_WINGS" != true && "$DO_UPDATE" != true && "$UNINSTALL" != true ]]; then
        if panel_exists; then
            info "A panel is installed in $PANEL_PATH: updating it."
            DO_UPDATE=true
        elif [[ "$ASSUME_YES" == true ]]; then
            DO_PANEL=true
        else
            choose_mode
        fi
    fi

    if [[ "$RESTORE" == true ]]; then
        require_root
        run_restore
        exit 0
    fi

    if [[ "$DRY_RUN" != true ]]; then
        require_root
    fi

    if [[ "$UNINSTALL" == true ]]; then
        run_uninstall
        return 0
    fi

    if [[ "$DO_UPDATE" == true ]]; then
        run_update
        return 0
    fi

    if [[ "$DO_PANEL" == true ]]; then
        run_panel_install
    fi
    if [[ "$DO_WINGS" == true ]]; then
        run_wings_install
    fi
}

main
