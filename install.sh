#!/bin/bash
# ====================================================
# Minn installer
#
# Drop the engine into an existing WordPress webroot.
# Run it from the directory that contains wp-config.php:
#
#   bash <(curl -sL https://minn.run/install)
#
# Downloads the latest minn.zip (the public/minn/ tree, Minn Admin
# bundled at minn/admin), runs preflight, then php minn/bin/minn install.
# wp-config.php, wp-content/, and the database are not touched.
#
# Flags:
#   --force          install even when preflight is RED
#   --engine-url=URL zip to download (default: GitHub latest release)
#   --park=DIR       where WordPress's own files go
# ====================================================
set -euo pipefail

FORCE=false
ENGINE_URL="https://github.com/austinginder/minn-engine/releases/latest/download/minn.zip"
PARK=""
VERSION="0.1.0"

for arg in "$@"; do
    case "$arg" in
        --force) FORCE=true ;;
        --engine-url=*) ENGINE_URL="${arg#*=}" ;;
        --park=*) PARK="${arg#*=}" ;;
        --help|-h)
            echo "Usage: install [--force] [--engine-url=URL] [--park=DIR]"
            echo "Run from a WordPress webroot (the directory with wp-config.php)."
            exit 0
            ;;
    esac
done

if [ -t 1 ]; then
    RED=$(tput setaf 1)
    GREEN=$(tput setaf 2)
    YELLOW=$(tput setaf 3)
    BLUE=$(tput setaf 4)
    BOLD=$(tput bold)
    NC=$(tput sgr0)
else
    RED="" GREEN="" YELLOW="" BLUE="" BOLD="" NC=""
fi

echo_info()    { echo "${BLUE}${BOLD}INFO:${NC} $1"; }
echo_success() { echo "${GREEN}${BOLD}SUCCESS:${NC} $1"; }
echo_warn()    { echo "${YELLOW}${BOLD}WARN:${NC} $1"; }
echo_error()   { echo "${RED}${BOLD}ERROR:${NC} $1" >&2; exit 1; }

find_webroot() {
    local dir="$PWD"
    local i
    for i in 1 2 3; do
        if [ -f "$dir/wp-config.php" ]; then
            echo "$dir"
            return 0
        fi
        dir="$(cd "$dir/.." && pwd)"
    done
    return 1
}

find_php() {
    local cmd ver
    for cmd in php php8.4 php8.3 php8.2; do
        if command -v "$cmd" >/dev/null 2>&1; then
            ver="$("$cmd" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || true)"
            case "$ver" in
                8.2|8.3|8.4|8.5|8.6) echo "$cmd"; return 0 ;;
            esac
        fi
    done
    return 1
}

extract_zip() {
    local zip="$1"
    local dest="$2"
    local php="$3"
    if command -v unzip >/dev/null 2>&1; then
        unzip -q "$zip" -d "$dest"
        return
    fi
    "$php" -r '
        $zip = new ZipArchive();
        if ($zip->open($argv[1]) !== true) { fwrite(STDERR, "cannot open zip\n"); exit(1); }
        if (!$zip->extractTo($argv[2])) { fwrite(STDERR, "cannot extract zip\n"); exit(1); }
        $zip->close();
    ' "$zip" "$dest"
}

WEBROOT="$(find_webroot)" || echo_error "No wp-config.php here. cd into the WordPress webroot (the folder with wp-config.php) and run this again."
WEBROOT="$(cd "$WEBROOT" && pwd)"
echo_info "WordPress webroot: $WEBROOT"

if [ -f "$WEBROOT/minn/bootstrap.php" ] && [ -f "$WEBROOT/wp-cli.yml" ] && grep -q 'minn/cli.php' "$WEBROOT/wp-cli.yml" 2>/dev/null; then
    echo_error "Minn is already installed here. Status: php minn/bin/minn status $WEBROOT"
fi

PHP="$(find_php)" || echo_error "Need PHP 8.2 or newer on PATH (php, php8.4, php8.3, or php8.2)."
echo_info "PHP: $PHP ($("$PHP" -r 'echo PHP_VERSION;'))"

if ! command -v curl >/dev/null 2>&1; then
    echo_error "curl is required to download Minn."
fi

WORKDIR="$(mktemp -d "${TMPDIR:-/tmp}/minn-install.XXXXXX")"
cleanup() { rm -rf "$WORKDIR"; }
trap cleanup EXIT

echo_info "Downloading Minn v${VERSION} from $ENGINE_URL"
if ! curl -L --fail --progress-bar "$ENGINE_URL" -o "$WORKDIR/minn.zip"; then
    echo_error "Download failed. The v${VERSION} release zip is not up yet, or $ENGINE_URL is unreachable."
fi

extract_zip "$WORKDIR/minn.zip" "$WORKDIR/extracted" "$PHP"

if [ -f "$WORKDIR/extracted/minn/bootstrap.php" ]; then
    ENGINE_SRC="$WORKDIR/extracted/minn"
elif [ -f "$WORKDIR/extracted/bootstrap.php" ]; then
    ENGINE_SRC="$WORKDIR/extracted"
elif [ -f "$WORKDIR/extracted/public/minn/bootstrap.php" ]; then
    ENGINE_SRC="$WORKDIR/extracted/public/minn"
else
    echo_error "The zip did not contain a Minn engine (no bootstrap.php). Expected minn/ at the top of the archive."
fi

if [ ! -x "$ENGINE_SRC/bin/minn" ]; then
    chmod +x "$ENGINE_SRC/bin/minn" 2>/dev/null || true
fi
if [ ! -f "$ENGINE_SRC/bin/minn" ]; then
    echo_error "The zip is missing minn/bin/minn."
fi

echo_info "Preflight"
set +e
"$PHP" "$ENGINE_SRC/bin/minn" preflight "$WEBROOT"
PREFLIGHT=$?
set -e
if [ "$PREFLIGHT" -ne 0 ] && [ "$FORCE" != true ]; then
    echo_error "Preflight is RED. Fix the items above, or re-run with --force."
fi

INSTALL_ARGS=(install "$WEBROOT")
if [ "$FORCE" = true ]; then
    INSTALL_ARGS+=(--force)
fi
if [ -n "$PARK" ]; then
    INSTALL_ARGS+=(--park="$PARK")
fi

echo_info "Installing Minn into $WEBROOT"
"$PHP" "$ENGINE_SRC/bin/minn" "${INSTALL_ARGS[@]}"

echo "--------------------------------------------------"
echo_success "Minn is installed."
echo_info "Admin: the site's /minn-admin/  (Minn Admin ships inside the engine zip)"
echo_info "Status: php minn/bin/minn status $WEBROOT"
echo_info "Leave:  php minn/bin/minn eject  $WEBROOT"
echo_warn "A PHP opcode cache may serve the old index.php for a few seconds. The first request can 500; it heals itself, or clear the cache if the host offers it."
