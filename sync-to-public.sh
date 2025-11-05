#!/bin/bash
# Sync files to public repository
# Usage: ./sync-to-public.sh [commit-message]

set -e

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Public repo path
PUBLIC_REPO="/Users/tomrobak/_code_/fchub-plugins/fchub-stream-public"

# Check if public repo exists
if [ ! -d "$PUBLIC_REPO" ]; then
    echo -e "${RED}Error: Public repo not found at $PUBLIC_REPO${NC}"
    exit 1
fi

# Get commit message
COMMIT_MSG="${1:-Update from private repo}"

echo -e "${GREEN}Syncing to public repository...${NC}"
echo ""

# Step 1: Copy files
echo -e "${YELLOW}[1/4] Copying files...${NC}"
rsync -av \
    --exclude-from=.distignore \
    --exclude=.git \
    --exclude='*.zip' \
    . "$PUBLIC_REPO/"

echo -e "${GREEN}✓ Files copied${NC}"
echo ""

# Step 2: Copy README
echo -e "${YELLOW}[2/4] Copying README...${NC}"
cat README-PUBLIC.md > "$PUBLIC_REPO/README.md"
echo -e "${GREEN}✓ README copied${NC}"
echo ""

# Step 3: Check for changes
echo -e "${YELLOW}[3/4] Checking for changes...${NC}"
cd "$PUBLIC_REPO"

if git diff --quiet && git diff --cached --quiet; then
    echo -e "${YELLOW}⚠ No changes to commit${NC}"
    exit 0
fi

echo -e "${GREEN}✓ Changes detected${NC}"
echo ""

# Step 4: Show status
echo -e "${YELLOW}[4/4] Git status:${NC}"
git status --short
echo ""

# Ask for confirmation
echo -e "${YELLOW}Commit message: ${NC}$COMMIT_MSG"
echo ""
read -p "Commit and push changes? (y/N) " -n 1 -r
echo

if [[ $REPLY =~ ^[Yy]$ ]]; then
    git add .
    git commit -m "$COMMIT_MSG"

    echo ""
    echo -e "${GREEN}✓ Changes committed${NC}"
    echo ""
    echo -e "${YELLOW}Push to remote? (y/N)${NC}"
    read -p "" -n 1 -r
    echo

    if [[ $REPLY =~ ^[Yy]$ ]]; then
        git push origin main
        echo -e "${GREEN}✓ Pushed to remote${NC}"
        echo ""
        echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
        echo -e "${GREEN}Sync completed!${NC}"
        echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
        echo ""
        echo "To create a release:"
        echo "1. cd $PUBLIC_REPO"
        echo "2. git tag v0.0.X"
        echo "3. git push origin v0.0.X"
        echo ""
        echo "GitHub Actions will automatically create the release!"
    fi
else
    echo -e "${YELLOW}Skipped commit${NC}"
fi
