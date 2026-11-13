#!/usr/bin/env bash
#
# Build the distributable plugin ZIP.
#
# This script is the only place that decides which files ship. The release
# and Plugin Check workflows call it, so a local build contains the same
# files as the ZIP attached to a GitHub release.
#
# The ZIP is built from an allow list: a new file in the repository does not
# ship unless it is added to SHIPPED below.
#
# Usage:
#   bin/build-zip.sh                 # version read from plugin header
#   bin/build-zip.sh 0.2.0           # override version
#
# Output:
#   ./dist/training-meeting-tracker-<version>.zip

set -euo pipefail

PLUGIN_SLUG="training-meeting-tracker"
PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"

# Files and directories that make up the installed plugin.
SHIPPED=(
    "${PLUGIN_SLUG}.php"
    "uninstall.php"
    "readme.txt"
    "LICENSE"
    "includes"
    "assets"
    "languages"
)

# Determine version
if [ -n "${1:-}" ]; then
    VERSION="$1"
else
    VERSION="$(grep -E '^[[:space:]]*\*[[:space:]]*Version:' \
        "${PLUGIN_DIR}/${PLUGIN_SLUG}.php" \
        | head -n1 | sed -E 's/.*Version:[[:space:]]*//;s/[[:space:]]+$//')"
fi

if [ -z "${VERSION}" ]; then
    echo "ERROR: could not determine version" >&2
    exit 1
fi

BUILD_ROOT="$(mktemp -d)"
trap 'rm -rf "${BUILD_ROOT}"' EXIT

BUILD_DIR="${BUILD_ROOT}/${PLUGIN_SLUG}"
DIST_DIR="${PLUGIN_DIR}/dist"
OUTPUT="${DIST_DIR}/${PLUGIN_SLUG}-${VERSION}.zip"

mkdir -p "${BUILD_DIR}" "${DIST_DIR}"

for item in "${SHIPPED[@]}"; do
    if [ ! -e "${PLUGIN_DIR}/${item}" ]; then
        echo "ERROR: ${item} is listed in SHIPPED but does not exist" >&2
        exit 1
    fi
    cp -R "${PLUGIN_DIR}/${item}" "${BUILD_DIR}/"
done

# Operating system clutter may sit in a local working copy.
find "${BUILD_DIR}" \( -name '.DS_Store' -o -name 'Thumbs.db' \) -delete

rm -f "${OUTPUT}"
( cd "${BUILD_ROOT}" && zip -rq "${OUTPUT}" "${PLUGIN_SLUG}" )

echo "Built: ${OUTPUT}"
unzip -l "${OUTPUT}"
