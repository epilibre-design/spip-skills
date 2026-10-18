#!/usr/bin/env sh
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
SPIP_ROOT="$ROOT_DIR/vendor/spip/spip"
SPIP_BIN="$ROOT_DIR/vendor/bin/spip"
DEPOT_PRINCIPAL="https://plugins.spip.net/depots/principal.xml"
PLUGIN_PREFIX="acme"
# Space-separated list of plugin prefixes that must be active before your plugin
PLUGIN_DEPS=""

is_plugin_active() {
    "$SPIP_BIN" plugins:lister | grep -Eiq "^[[:space:]]*$1[[:space:]]"
}

activate_or_install_plugin() {
    plugin_prefix="$1"
    is_plugin_active "$plugin_prefix" && return 0
    "$SPIP_BIN" plugins:activer "$plugin_prefix" -y && is_plugin_active "$plugin_prefix" && return 0
    echo "Downloading $plugin_prefix via SVP..." >&2
    "$SPIP_BIN" plugins:svp:telecharger "$plugin_prefix" -y || true
    "$SPIP_BIN" plugins:activer "$plugin_prefix" -y || true
    is_plugin_active "$plugin_prefix"
}

mkdir -p "$ROOT_DIR/vendor/spip"

# Download SPIP core if missing
if [ ! -f "$SPIP_ROOT/ecrire/inc_version.php" ]; then
    "$SPIP_BIN" core:telecharger -d "$SPIP_ROOT" -b 4.4
fi

cd "$SPIP_ROOT"
"$SPIP_BIN" core:preparer

# core:preparer does NOT create plugins/auto/, where SVP unpacks downloaded packages.
# Without it every plugins:svp:telecharger fails with
# « Le répertoire de paquets plugins/auto/ n'est pas accessible en écriture ».
mkdir -p "$SPIP_ROOT/plugins/auto"

# Install (SQLite3, no network required for DB)
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

# Register depot and activate dependencies
"$SPIP_BIN" plugins:svp:depoter "$DEPOT_PRINCIPAL" || true

for plugin_dep in $PLUGIN_DEPS; do
    activate_or_install_plugin "$plugin_dep" || { echo "Failed: $plugin_dep" >&2; exit 1; }
done

# Activate the plugin itself through the same checked helper: plugins:activer exits 0
# even when it activates nothing (unmet dependency, prefix not found).
activate_or_install_plugin "$PLUGIN_PREFIX" || { echo "Failed: $PLUGIN_PREFIX" >&2; exit 1; }

# plugins:activer does NOT run the plugins' _upgrade() functions. Without this step the
# plugin is active but its schema is missing: no tables, no metas, no installed content.
"$SPIP_BIN" plugins:maj:bdd

echo "Integration environment ready."
