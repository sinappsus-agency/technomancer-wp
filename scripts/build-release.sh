#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_FILE="${ROOT_DIR}/technomancer-wp.php"
PLUGIN_SLUG="technomancer-wp"
DIST_DIR="${ROOT_DIR}/dist"

if [[ ! -f "${PLUGIN_FILE}" ]]; then
  echo "Plugin file not found: ${PLUGIN_FILE}" >&2
  exit 1
fi

VERSION="$(awk -F': ' '/^[[:space:]]*\* Version:/{print $2; exit}' "${PLUGIN_FILE}" | tr -d '\r')"
if [[ -z "${VERSION}" ]]; then
  echo "Could not parse plugin version from ${PLUGIN_FILE}" >&2
  exit 1
fi

PACKAGE_DIR="${DIST_DIR}/${PLUGIN_SLUG}"
ZIP_PATH="${DIST_DIR}/${PLUGIN_SLUG}-${VERSION}.zip"

rm -rf "${PACKAGE_DIR}"
mkdir -p "${PACKAGE_DIR}"

rsync -a --delete \
  --exclude '.git' \
  --exclude '.github' \
  --exclude '.gitmodules' \
  --exclude '.gitattributes' \
  --exclude '.gitignore' \
  --exclude 'dist' \
  --exclude '.DS_Store' \
  "${ROOT_DIR}/" "${PACKAGE_DIR}/"

if [[ ! -f "${PACKAGE_DIR}/lib/plugin-update-checker/plugin-update-checker.php" ]]; then
  echo "plugin-update-checker library not found in package. Ensure submodules are checked out." >&2
  exit 1
fi

(
  cd "${DIST_DIR}"
  rm -f "${ZIP_PATH}"
  zip -r "$(basename "${ZIP_PATH}")" "${PLUGIN_SLUG}" >/dev/null
)

echo "version=${VERSION}"
echo "zip_path=${ZIP_PATH}"

