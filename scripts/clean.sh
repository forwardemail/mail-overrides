#!/bin/bash
#
# Clean Script for Mail Overrides
# Removes the dist/ build directory
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"

echo "========================================"
echo "Cleaning Build Artifacts"
echo "========================================"
echo ""

cd "$ROOT_DIR"

if [ -d "dist" ]; then
    echo "→ Removing dist/ directory..."
    rm -rf dist
    echo "  ✓ dist/ removed"
else
    echo "→ dist/ directory doesn't exist, nothing to clean"
fi

echo ""
echo "✓ Clean complete!"
echo ""
