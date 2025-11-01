#!/bin/bash
#
# Update SnappyMail to a specific version
# Usage: ./scripts/update-snappymail.sh [version]
#

set -e

VERSION="${1}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"

cd "$ROOT_DIR"

echo "========================================"
echo "SnappyMail Version Updater"
echo "========================================"
echo ""

if [ ! -d "mail/.git" ]; then
    echo "ERROR: mail submodule not initialized!"
    echo "Run: git submodule update --init --recursive"
    exit 1
fi

cd mail

# Fetch latest tags
echo "→ Fetching latest versions..."
git fetch --tags origin

# List available versions if none specified
if [ -z "$VERSION" ]; then
    echo ""
    echo "Available versions (latest 10):"
    git tag --sort=-v:refname | head -10
    echo ""
    echo "Current version:"
    git describe --tags 2>/dev/null || echo "  (unknown - not on a tag)"
    echo ""
    echo "Usage: $0 <version>"
    echo "Example: $0 v2.38.0"
    exit 0
fi

# Validate version exists
if ! git rev-parse "refs/tags/$VERSION" >/dev/null 2>&1; then
    echo "ERROR: Version $VERSION does not exist!"
    echo ""
    echo "Available versions:"
    git tag --sort=-v:refname | head -10
    exit 1
fi

echo "→ Updating to $VERSION..."
git checkout "$VERSION"

cd "$ROOT_DIR"

# Commit the submodule update
echo ""
echo "→ Updating submodule reference..."
git add mail
git commit -m "Update SnappyMail to $VERSION" || echo "  (no changes to commit)"

echo ""
echo "========================================"
echo "✓ Updated to $VERSION"
echo "========================================"
echo ""
echo "Next steps:"
echo "  1. Run: ./scripts/build.sh"
echo "  2. Test the update locally"
echo "  3. Deploy when ready"
echo ""
