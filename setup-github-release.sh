#!/bin/bash

# Setup GitHub Auto-Release
# This script helps you configure GitHub repository for auto-release

echo "╔═══════════════════════════════════════════════════════════╗"
echo "║     🚀 GitHub Auto-Release Setup                         ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo ""

# Check if git is initialized
if [ ! -d ".git" ]; then
    echo "⚠️  Git not initialized. Initializing..."
    git init
    git branch -M main
fi

# Get GitHub username and repo name
echo "📝 Please provide your GitHub information:"
echo ""
read -p "GitHub Username: " GITHUB_USER
read -p "Repository Name: " GITHUB_REPO

if [ -z "$GITHUB_USER" ] || [ -z "$GITHUB_REPO" ]; then
    echo "❌ Username and Repository name are required!"
    exit 1
fi

# Construct UPDATE_URL
UPDATE_URL="https://github.com/$GITHUB_USER/$GITHUB_REPO/releases/download/latest/latest-update.zip"

echo ""
echo "📌 Your GitHub Release URL:"
echo "   $UPDATE_URL"
echo ""

# Update .env file
if [ -f ".env" ]; then
    # Check if UPDATE_URL exists
    if grep -q "^UPDATE_URL=" .env; then
        # Update existing
        sed -i.bak "s|^UPDATE_URL=.*|UPDATE_URL=$UPDATE_URL|" .env
        echo "✅ Updated UPDATE_URL in .env"
    else
        # Add new
        echo "" >> .env
        echo "# GitHub Auto-Update" >> .env
        echo "UPDATE_URL=$UPDATE_URL" >> .env
        echo "✅ Added UPDATE_URL to .env"
    fi
else
    echo "⚠️  .env file not found. Creating..."
    echo "UPDATE_URL=$UPDATE_URL" > .env
fi

# Update UpdateController.php
CONTROLLER_FILE="app/Http/Controllers/UpdateController.php"
if [ -f "$CONTROLLER_FILE" ]; then
    # Replace YOUR_USERNAME/YOUR_REPO with actual values
    sed -i.bak "s|YOUR_USERNAME/YOUR_REPO|$GITHUB_USER/$GITHUB_REPO|g" "$CONTROLLER_FILE"
    echo "✅ Updated UpdateController.php"
fi

# Check if remote exists
REMOTE=$(git remote get-url origin 2>/dev/null)
if [ -z "$REMOTE" ]; then
    echo ""
    echo "📡 Adding GitHub remote..."
    git remote add origin "https://github.com/$GITHUB_USER/$GITHUB_REPO.git"
    echo "✅ Remote added: https://github.com/$GITHUB_USER/$GITHUB_REPO.git"
else
    echo "✅ Remote already exists: $REMOTE"
fi

# Show git status
echo ""
echo "═══════════════════════════════════════════════════════════"
echo "📊 Git Status:"
echo "═══════════════════════════════════════════════════════════"
git status --short

echo ""
echo "═══════════════════════════════════════════════════════════"
echo "✅ Setup Complete!"
echo "═══════════════════════════════════════════════════════════"
echo ""
echo "🎯 Next Steps:"
echo ""
echo "1. Review files to commit:"
echo "   git status"
echo ""
echo "2. Add files to commit:"
echo "   git add ."
echo ""
echo "3. Commit:"
echo "   git commit -m \"Setup GitHub auto-release\""
echo ""
echo "4. Push to GitHub:"
echo "   git push -u origin main"
echo ""
echo "5. GitHub Actions will automatically:"
echo "   ✓ Create latest-update.zip"
echo "   ✓ Create GitHub Release with tag 'latest'"
echo "   ✓ Upload ZIP file to release"
echo ""
echo "6. After push, check:"
echo "   - GitHub → Actions tab (workflow running)"
echo "   - GitHub → Releases tab (latest release created)"
echo ""
echo "7. Configure servers' .env with:"
echo "   UPDATE_URL=$UPDATE_URL"
echo ""
echo "═══════════════════════════════════════════════════════════"
echo ""
echo "📖 Full documentation: GITHUB_AUTO_RELEASE.md"
echo ""

