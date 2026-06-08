#!/usr/bin/env sh
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
SPIP_ROOT="$ROOT_DIR/vendor/spip/spip"
SPIP_CLI_DIR="$ROOT_DIR/vendor/spip/spip-cli"
SPIP_BIN="$ROOT_DIR/vendor/bin/spip"
SPIP_SRC="$ROOT_DIR/readonly-src/spip"
PATCH_FILE="$ROOT_DIR/spip-cli.patch"

# 1. Copy SPIP core from readonly-src (no network download)
if [ ! -f "$SPIP_ROOT/ecrire/inc_version.php" ]; then
    [ -d "$SPIP_SRC" ] || { echo "Error: SPIP source not found at $SPIP_SRC" >&2; exit 1; }
    echo "Copying SPIP from $SPIP_SRC ..." >&2
    mkdir -p "$(dirname "$SPIP_ROOT")"
    cp -a "$SPIP_SRC/." "$SPIP_ROOT/"
fi

# 2. Apply spip-cli patch (idempotent: patch --forward exits 0 on success, 1 if already applied)
if [ -f "$PATCH_FILE" ] && [ -d "$SPIP_CLI_DIR" ]; then
    echo "Applying spip-cli.patch ..." >&2
    patch_exit=0
    patch --forward --directory="$SPIP_CLI_DIR" -p1 < "$PATCH_FILE" || patch_exit=$?
    [ "$patch_exit" -le 1 ] || { echo "Error: spip-cli patch failed (exit $patch_exit)" >&2; exit 1; }
fi

cd "$SPIP_ROOT"

# 3. Prepare SPIP (creates dirs, sets permissions)
[ -x "$SPIP_BIN" ] || { echo "Error: SPIP CLI not found at $SPIP_BIN — run composer install first" >&2; exit 1; }
"$SPIP_BIN" core:preparer

# 4. Install DB (SQLite3, idempotent)
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
