#!/usr/bin/env bash
#
# Check that the version number is the same everywhere.
#
# Compares the plugin header, the TMTRACKER_VERSION constant and the stable
# tag in readme.txt, and makes sure CHANGELOG.md has a section for that
# version. With a tag as argument the tag has to match as well.
#
# Usage:
#   bin/check-version.sh             # internal consistency
#   bin/check-version.sh v0.2.0      # additionally: tag matches the version

set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"
MAIN_FILE="${PLUGIN_DIR}/training-meeting-tracker.php"

HEADER="$(grep -E '^[[:space:]]*\*[[:space:]]*Version:' "${MAIN_FILE}" \
    | head -n1 | sed -E 's/.*Version:[[:space:]]*//;s/[[:space:]]+$//')"
CONSTANT="$(grep -E "define\( 'TMTRACKER_VERSION'" "${MAIN_FILE}" \
    | head -n1 | sed -E "s/.*'TMTRACKER_VERSION',[[:space:]]*'([^']+)'.*/\1/")"
STABLE="$(grep -E '^Stable tag:' "${PLUGIN_DIR}/readme.txt" \
    | head -n1 | sed -E 's/^Stable tag:[[:space:]]*//;s/[[:space:]]+$//')"

STATUS=0

fail() {
    echo "ERROR: $1" >&2
    STATUS=1
}

echo "Plugin header:      ${HEADER}"
echo "TMTRACKER_VERSION:  ${CONSTANT}"
echo "readme.txt stable:  ${STABLE}"

if [ -z "${HEADER}" ]; then
    fail "no Version line in the plugin header"
fi
if [ "${HEADER}" != "${CONSTANT}" ]; then
    fail "TMTRACKER_VERSION differs from the plugin header"
fi
if [ "${HEADER}" != "${STABLE}" ]; then
    fail "Stable tag in readme.txt differs from the plugin header"
fi
if ! grep -qF "## [${HEADER}]" "${PLUGIN_DIR}/CHANGELOG.md"; then
    fail "CHANGELOG.md has no section for ${HEADER}"
fi
if ! grep -qF "= ${HEADER} =" "${PLUGIN_DIR}/readme.txt"; then
    fail "readme.txt has no changelog entry for ${HEADER}"
fi

if [ -n "${1:-}" ]; then
    echo "Tag:                $1"
    if [ "$1" != "v${HEADER}" ]; then
        fail "tag $1 does not match version ${HEADER} (expected v${HEADER})"
    fi
fi

if [ "${STATUS}" -eq 0 ]; then
    echo "Version numbers are consistent."
fi

exit "${STATUS}"
