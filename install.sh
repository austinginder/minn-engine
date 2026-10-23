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
# bundled at minn/admin) and its signature, checks the signature against
# the release keys below, then runs php minn/bin/minn install (preflight
# first). wp-config.php, wp-content/, and the database are not touched.
#
# Flags:
#   --force          install even when preflight is RED
#   --engine-url=URL zip to download (default: GitHub latest release);
#                    its signature is read from URL.sig
#   --park=DIR       where WordPress's own files go (default: beside the
#                    webroot, in wp-parked)
#   --no-cache-flush skip the closing `wp cache flush` (page caches keep
#                    WordPress's pages until purged)
# ====================================================
set -euo pipefail

FORCE=false
ENGINE_URL="https://github.com/austinginder/minn-engine/releases/latest/download/minn.zip"
PARK=""
CACHE_FLUSH=true

# The release keys a Minn engine trusts (Ops\EngineUpdate::KEYS: the working
# key, then the offline recovery key). The install suite keeps them in step.
MINN_KEYS=(
    "+Usjnr1ZTudBbWToqYLkZ1Oy2yKN07d9+u62AIJ2FG8="
    "QpNzSZpXf3TPiHs9mqur30xFrOXs/yFG4uNqBkVajQk="
)

for arg in "$@"; do
    case "$arg" in
        --force) FORCE=true ;;
        --engine-url=*) ENGINE_URL="${arg#*=}" ;;
        --park=*) PARK="${arg#*=}" ;;
        --no-cache-flush) CACHE_FLUSH=false ;;
        --help|-h)
            echo "Usage: install [--force] [--engine-url=URL] [--park=DIR] [--no-cache-flush]"
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
    local cmd
    for cmd in php php8.5 php8.4 php8.3; do
        if command -v "$cmd" >/dev/null 2>&1 && "$cmd" -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);' 2>/dev/null; then
            echo "$cmd"
            return 0
        fi
    done
    return 1
}

# Exit 0 when one of the trusted keys signed the archive, 2 when PHP has no sodium.
verify_signature() {
    "$1" -r '
        if (!function_exists("sodium_crypto_sign_verify_detached")) { exit(2); }
        $zip = (string) file_get_contents($argv[1]);
        $signature = base64_decode(trim((string) file_get_contents($argv[2])), true);
        if ($signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) { exit(1); }
        foreach (array_slice($argv, 3) as $key) {
            $public = base64_decode($key, true);
            if ($public !== false && strlen($public) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES && sodium_crypto_sign_verify_detached($signature, $zip, $public)) { exit(0); }
        }
        exit(1);
    ' "$2" "$3" "${MINN_KEYS[@]}"
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

PHP="$(find_php)" || echo_error "Need PHP 8.3 or newer on PATH (php, php8.5, php8.4, or php8.3)."
echo_info "PHP: $PHP ($("$PHP" -r 'echo PHP_VERSION;'))"

if ! command -v curl >/dev/null 2>&1; then
    echo_error "curl is required to download Minn."
fi

WORKDIR="$(mktemp -d "${TMPDIR:-/tmp}/minn-install.XXXXXX")"
cleanup() { rm -rf "$WORKDIR"; }
trap cleanup EXIT

echo_info "Downloading Minn from $ENGINE_URL"
if ! curl -L --fail --progress-bar "$ENGINE_URL" -o "$WORKDIR/minn.zip"; then
    echo_error "Download failed. No release zip is up at $ENGINE_URL yet, or it is unreachable."
fi
if ! curl -L --fail --silent "${ENGINE_URL}.sig" -o "$WORKDIR/minn.zip.sig"; then
    echo_error "The release has no signature at ${ENGINE_URL}.sig, so nothing is installed."
fi
set +e
verify_signature "$PHP" "$WORKDIR/minn.zip" "$WORKDIR/minn.zip.sig"
SIGNED=$?
set -e
if [ "$SIGNED" -eq 2 ]; then
    echo_error "This PHP has no sodium extension, so the release signature cannot be checked and nothing is installed."
elif [ "$SIGNED" -ne 0 ]; then
    echo_error "The download is not signed with a Minn release key, so nothing is installed."
fi
echo_info "Signature checked: signed with a Minn release key."

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

INSTALL_ARGS=(install "$WEBROOT")
if [ "$FORCE" = true ]; then
    INSTALL_ARGS+=(--force)
fi
if [ -n "$PARK" ]; then
    INSTALL_ARGS+=(--park="$PARK")
fi

echo_info "Installing Minn into $WEBROOT (preflight first)"
if ! "$PHP" "$ENGINE_SRC/bin/minn" "${INSTALL_ARGS[@]}"; then
    echo_error "Minn is not installed; the reason is above."
fi

# A page cache (the host's, Varnish, a caching plugin) keeps serving WordPress's
# pages until it is purged, and the opcode cache can keep running the old
# index.php for half a minute (Kinsta revalidates every 30 s), long enough for
# visitors to cache WordPress's pages again. So wait until the site answers as
# Minn, then flush through WP-CLI, which runs the host's purge hooks (Kinsta's
# mu-plugin purges its page cache and CDN on `wp cache flush`).
flush_caches() {
    if ! command -v wp >/dev/null 2>&1; then
        echo_warn "WP-CLI is not on PATH, so no cache was flushed. If the host caches pages, purge its cache now."
        return 0
    fi
    local url headers seen="" misses=0
    # The address alone, read with no plugin or theme loaded; the flush below loads them, as the host's purge hooks live there.
    url="$(cd "$WEBROOT" && wp eval 'echo home_url("/");' --skip-plugins --skip-themes 2>/dev/null || true)"
    if ! printf '%s' "$url" | grep -qE '^https?://[A-Za-z0-9._~:/?#@!$&()*+,;=%-]+$'; then
        url=""
    fi
    if [ -n "$url" ]; then
        echo_info "Waiting for $url to answer as Minn"
        for _ in $(seq 1 30); do
            # A logged-in cookie name and a fresh query string step past page caches; the value signs nobody in.
            headers="$(curl -s -o /dev/null -D - --max-time 15 -b 'wordpress_logged_in_minn_install=1' --url "${url}?minn-install=${RANDOM}" 2>/dev/null || true)"
            if printf '%s' "$headers" | grep -qi '^x-powered-by: Minn'; then
                seen=yes
                break
            fi
            if [ -z "$headers" ]; then
                misses=$((misses + 1))
                [ "$misses" -ge 3 ] && break
            fi
            sleep 3
        done
    fi
    if [ -z "$seen" ]; then
        echo_warn "The site did not answer as Minn yet; flushing anyway. If it still shows WordPress in a minute, run: wp cache flush"
    fi
    if (cd "$WEBROOT" && wp cache flush); then
        echo_info "Caches flushed (wp cache flush)."
    else
        echo_warn "wp cache flush failed. If the host caches pages, purge its cache now."
    fi
}
if [ "$CACHE_FLUSH" = true ]; then
    flush_caches
fi

echo "--------------------------------------------------"
echo_success "Minn is installed."
echo_info "Admin: the site's /minn-admin/  (Minn Admin ships inside the engine zip)"
echo_info "Status: php minn/bin/minn status $WEBROOT"
echo_info "Leave:  php minn/bin/minn eject  $WEBROOT"
echo_warn "A PHP opcode cache may serve the old index.php for up to a minute (opcache.revalidate_freq). It heals itself, or clear the cache if the host offers it."
