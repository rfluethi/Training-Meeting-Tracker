#!/usr/bin/env bash
#
# Build a distributable plugin ZIP locally.
#
# Uses the exact include/exclude rules from .github/workflows/release.yml so
# the local build is byte-for-byte identical to what CI produces.
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
BUILD_DIR="${BUILD_ROOT}/${PLUGIN_SLUG}"
DIST_DIR="${PLUGIN_DIR}/dist"
OUTPUT="${DIST_DIR}/${PLUGIN_SLUG}-${VERSION}.zip"

mkdir -p "${BUILD_DIR}" "${DIST_DIR}"

# Mirror the file list of .github/workflows/release.yml exactly.
rsync -a \
    --exclude='.git' \
    --exclude='.github' \
    --exclude='vendor' \
    --exclude='node_modules' \
    --exclude='tests' \
    --exclude='docs' \
    --exclude='dist' \
    --exclude='bin' \
    --exclude='*.zip' \
    --exclude='.gitignore' \
    --exclude='.editorconfig' \
    --exclude='phpcs.xml' \
    --exclude='composer.json' \
    --exclude='composer.lock' \
    "${PLUGIN_DIR}/" "${BUILD_DIR}/"

rm -f "${OUTPUT}"
( cd "${BUILD_ROOT}" && zip -rq "${OUTPUT}" "${PLUGIN_SLUG}" )

rm -rf "${BUILD_ROOT}"

echo "Built: ${OUTPUT}"
ls -lh "${OUTPUT}"
