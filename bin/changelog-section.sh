#!/usr/bin/env bash
# Prints the CHANGELOG.md section of one version (without its heading).
# Usage: bin/changelog-section.sh 1.2.3   (a leading "v" is accepted)
# Exits 1 when the version has no section, or the section is empty.
set -euo pipefail

version="${1:?usage: bin/changelog-section.sh <version>}"
version="${version#v}"
file="${2:-CHANGELOG.md}"

section="$(awk -v v="$version" '
    /^## \[/ { if (found) exit; if (index($0, "## [" v "]") == 1) { found = 1; next } }
    found { print }
' "$file")"

if [ -z "$(printf '%s' "$section" | tr -d '[:space:]')" ]; then
    echo "CHANGELOG.md has no entry for version $version (expected a '## [$version] - date' section with content)." >&2
    exit 1
fi

printf '%s\n' "$section"
