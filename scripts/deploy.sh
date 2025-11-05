#!/bin/bash
#
# Deploy Script for Forward Email SnappyMail
# Usage: ./scripts/deploy.sh [local]
#
# Note: Production deployment is handled by Ansible in the main forwardemail monorepo
#

set -e

ENVIRONMENT="${1:-local}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"

echo "========================================"
echo "Forward Email SnappyMail Deployment"
echo "Environment: $ENVIRONMENT"
echo "========================================"
echo ""

cd "$ROOT_DIR"

case "$ENVIRONMENT" in
    local)
        echo "→ Running build..."
        ./scripts/build.sh

        echo ""
        echo "Starting local development server..."
        echo ""
        echo "Choose your preferred method:"
        echo "  1. PHP built-in server: cd mail && php -S localhost:8000"
        echo "  2. Docker:              docker-compose -f docker/docker-compose.yml up"
        echo ""
        read -p "Start with Docker? (y/N): " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            docker-compose -f docker/docker-compose.yml up
        else
            cd mail
            php -S localhost:8000
        fi
        ;;
    production|staging)
        echo "ERROR: Production and staging deployments are managed by Ansible"
        echo ""
        echo "To deploy to $ENVIRONMENT:"
        echo "  1. Push changes to this repository"
        echo "  2. Update the submodule reference in the main forwardemail monorepo"
        echo "  3. Run Ansible deployment from the monorepo:"
        echo "     cd /path/to/forwardemail"
        echo "     ansible-playbook playbooks/deploy-snappymail.yml -e env=$ENVIRONMENT"
        echo ""
        exit 1
        ;;
    *)
        echo "Unknown environment: $ENVIRONMENT"
        echo "Usage: $0 [local]"
        echo ""
        echo "Note: Production deployment is handled by Ansible in the main forwardemail monorepo"
        exit 1
        ;;
esac

echo ""
