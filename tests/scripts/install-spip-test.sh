#!/usr/bin/env sh
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
SPIP_ROOT="$ROOT_DIR/vendor/spip/spip"
SPIP_CLI_DIR="$ROOT_DIR/vendor/spip/spip-cli"
SPIP_BIN="$ROOT_DIR/vendor/bin/spip"
PATCH_FILE="$ROOT_DIR/spip-cli.patch"

# 1. Check spip/spip meta-package is present (installed by main composer install)
[ -f "$SPIP_ROOT/spip.php" ] || { echo "Error: vendor/spip/spip not found — run composer install first" >&2; exit 1; }

# 2. Apply spip-cli patch (idempotent: patch --forward exits 0 on success, 1 if already applied)
if [ -f "$PATCH_FILE" ] && [ -d "$SPIP_CLI_DIR" ]; then
    echo "Applying spip-cli.patch ..." >&2
    patch_exit=0
    patch --forward --directory="$SPIP_CLI_DIR" -p1 < "$PATCH_FILE" || patch_exit=$?
    [ "$patch_exit" -le 1 ] || { echo "Error: spip-cli patch failed (exit $patch_exit)" >&2; exit 1; }
fi

# 3. Install SPIP components (ecrire/, prive/, squelettes-dist/, vendor/) inside vendor/spip/spip/.
# spip-league/composer-installer is disabled in the root project to keep the project root clean;
# running composer install here uses spip/spip's own composer.json where the installer IS allowed,
# so ecrire/ and prive/ land in vendor/spip/spip/ exactly where bootstrap_integration.php expects them.
if [ ! -f "$SPIP_ROOT/ecrire/inc_version.php" ]; then
    echo "Installing SPIP components inside vendor/spip/spip/ ..." >&2
    composer install --working-dir="$SPIP_ROOT" --no-interaction
fi

cd "$SPIP_ROOT"

# 4. Prepare SPIP (creates dirs, sets permissions)
[ -x "$SPIP_BIN" ] || { echo "Error: SPIP CLI not found at $SPIP_BIN — run composer install first" >&2; exit 1; }
"$SPIP_BIN" core:preparer

# 5. Install DB (SQLite3, idempotent)
if [ ! -f "$SPIP_ROOT/config/connect.php" ]; then
    "$SPIP_BIN" core:installer \
        --db-server=sqlite3 \
        --db-host='' --db-login='' --db-pass='' \
        --db-database='spip_test' \
        --db-prefix=spip \
        --admin-nom='Admin Test' \
        --admin-login='admin' \
        --admin-email='admin@example.test' \
        --admin-pass='adminadmin' \
        --adresse-site='http://localhost'
fi

echo "SPIP integration environment ready." >&2
