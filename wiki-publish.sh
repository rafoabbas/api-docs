#!/bin/bash

set -e

REPO_URL="git@github.com:rafoabbas/api-docs.wiki.git"
WIKI_DIR="wiki"
TEMP_DIR=$(mktemp -d)

if [ ! -d "$WIKI_DIR" ]; then
    echo "Error: wiki/ directory not found."
    exit 1
fi

echo "Cloning wiki repository..."
git clone "$REPO_URL" "$TEMP_DIR" 2>/dev/null || {
    echo ""
    echo "Wiki repo could not be cloned."
    echo "Please create the first wiki page manually on GitHub:"
    echo "  https://github.com/rafoabbas/api-docs/wiki/_new"
    echo ""
    echo "After creating the first page, run this script again."
    exit 1
}

echo "Copying wiki pages..."
cp "$WIKI_DIR"/*.md "$TEMP_DIR/"

cd "$TEMP_DIR"
git add -A

if git diff --cached --quiet; then
    echo "No changes to publish."
else
    git commit -m "Update wiki documentation"
    git push origin master
    echo "Wiki published successfully!"
fi

rm -rf "$TEMP_DIR"