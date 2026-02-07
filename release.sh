#!/bin/bash

set -e

if [ -z "$1" ]; then
    echo "Usage: ./release.sh <version>"
    echo "Example: ./release.sh 1.2.3"
    exit 1
fi

VERSION=$1

# Ensure version starts with 'v'
if [[ ! "$VERSION" == v* ]]; then
    VERSION="v$VERSION"
fi

echo "Creating tag: $VERSION"

git tag "$VERSION"
git push origin "$VERSION"

echo "Tag $VERSION pushed successfully!"
