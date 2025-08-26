#!/bin/bash

# =============================================================================
# Fast Deployment Script - DRY RUN MODE
# Shows what would be deployed without actually doing it
# =============================================================================

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Configuration
DEV_DIR=/var/www/html/osmanagercl
PROD_USER=jon
PROD_HOST=lilThink2

# Determine environment based on argument
ENVIRONMENT=${1:-production}
if [[ "$ENVIRONMENT" == "test" ]]; then
    PROD_PATH=/var/www/html/osmanager-test
    ENV_NAME="TEST"
else
    PROD_PATH=/var/www/html/osmanager
    ENV_NAME="PRODUCTION"
fi

echo -e "${PURPLE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${PURPLE}   🔍 DRY RUN - FAST DEPLOYMENT TO ${ENV_NAME}${NC}"
echo -e "${PURPLE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}This is a DRY RUN - no changes will be made${NC}"
echo -e "Target: ${BLUE}$PROD_HOST:$PROD_PATH${NC}"
echo -e "${PURPLE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo

# Check current git status
echo -e "${BLUE}▶ Git Status:${NC}"
cd $DEV_DIR
CURRENT_BRANCH=$(git branch --show-current)
echo "  Current branch: $CURRENT_BRANCH"

# Check for uncommitted changes
if [[ -n $(git status --porcelain) ]]; then
    echo -e "  ${YELLOW}⚠ You have uncommitted changes:${NC}"
    git status --short | head -10
    echo
    echo -e "  ${YELLOW}These changes will NOT be deployed!${NC}"
else
    echo -e "  ${GREEN}✓ Working directory clean${NC}"
fi

# Check what would be pulled
echo
echo -e "${BLUE}▶ Changes to be deployed:${NC}"
git fetch origin $CURRENT_BRANCH --dry-run 2>&1 | grep -v "From" | grep -v "remote:" || echo "  No new commits to pull"

# Show recent commits
echo
echo -e "${BLUE}▶ Recent commits (last 5):${NC}"
git log --oneline -5 | sed 's/^/  /'

# Check dependency changes
echo
echo -e "${BLUE}▶ Dependency changes:${NC}"
COMPOSER_CHANGED=$(git diff HEAD@{1} --name-only 2>/dev/null | grep -c "composer.lock" || echo "0")
NPM_CHANGED=$(git diff HEAD@{1} --name-only 2>/dev/null | grep -c "package-lock.json" || echo "0")

if [ "$COMPOSER_CHANGED" -gt 0 ]; then
    echo -e "  ${YELLOW}• composer.lock changed - composer install will run${NC}"
else
    echo "  • No composer changes"
fi

if [ "$NPM_CHANGED" -gt 0 ]; then
    echo -e "  ${YELLOW}• package-lock.json changed - npm build will run${NC}"
else
    echo "  • No npm changes"
fi

# Show files that would be synced
echo
echo -e "${BLUE}▶ Files that would be synced:${NC}"
CHANGED_FILES=$(git diff --name-only HEAD@{1} 2>/dev/null | head -20)
if [ -z "$CHANGED_FILES" ]; then
    echo "  No files changed since last deployment"
else
    echo "$CHANGED_FILES" | sed 's/^/  /'
    TOTAL_CHANGED=$(git diff --name-only HEAD@{1} 2>/dev/null | wc -l)
    if [ "$TOTAL_CHANGED" -gt 20 ]; then
        echo "  ... and $((TOTAL_CHANGED - 20)) more files"
    fi
fi

# Check migrations
echo
echo -e "${BLUE}▶ Pending migrations:${NC}"
PENDING_MIGRATIONS=$(find database/migrations -name "*.php" -newer .git/index 2>/dev/null | wc -l)
if [ "$PENDING_MIGRATIONS" -gt 0 ]; then
    echo -e "  ${YELLOW}• $PENDING_MIGRATIONS new migration(s) will be run${NC}"
    find database/migrations -name "*.php" -newer .git/index 2>/dev/null | xargs basename -a | sed 's/^/    - /'
else
    echo "  • No new migrations"
fi

# Show deployment actions
echo
echo -e "${BLUE}▶ Deployment actions that would be performed:${NC}"
echo "  1. Create backup on production"
echo "  2. Rsync files (excluding vendor, node_modules, logs)"
echo "  3. Clear Laravel caches"
echo "  4. Run database migrations"
echo "  5. Optimize Laravel (cache config/routes)"
echo "  6. Fix storage permissions"
if [ "$COMPOSER_CHANGED" -gt 0 ]; then
    echo -e "  7. ${YELLOW}Run composer install${NC}"
fi
if [ "$NPM_CHANGED" -gt 0 ]; then
    echo -e "  8. ${YELLOW}Build frontend assets${NC}"
fi
echo "  9. Restart queue workers"
echo "  10. Verify deployment"

# Estimate time
echo
echo -e "${BLUE}▶ Estimated deployment time:${NC}"
BASE_TIME=30
if [ "$COMPOSER_CHANGED" -gt 0 ]; then
    BASE_TIME=$((BASE_TIME + 20))
fi
if [ "$NPM_CHANGED" -gt 0 ]; then
    BASE_TIME=$((BASE_TIME + 60))
fi
echo "  Approximately $BASE_TIME seconds"

# Show current production status
echo
echo -e "${BLUE}▶ Current production status:${NC}"
ssh -o ConnectTimeout=5 $PROD_USER@$PROD_HOST "cd $PROD_PATH && git log --oneline -1" 2>/dev/null | sed 's/^/  Current: /' || echo "  Unable to check production"

echo
echo -e "${PURPLE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}To perform actual deployment, run:${NC}"
echo -e "${GREEN}  ./deploy-fast.sh $ENVIRONMENT${NC}"
echo -e "${PURPLE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"