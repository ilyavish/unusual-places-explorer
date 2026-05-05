#!/usr/bin/env bash
set -euo pipefail

VERSION="${1:-}"
if [[ -z "$VERSION" ]]; then
  echo "Usage: scripts/build-and-release.sh 1.0.1"
  exit 1
fi

TAG="v${VERSION}"
PLUGIN_DIR="unusual-places-explorer"
ZIP_NAME="unusual-places-explorer.zip"

if [[ ! -d "$PLUGIN_DIR" ]]; then
  echo "Missing $PLUGIN_DIR directory."
  exit 1
fi

mkdir -p dist
rm -f "dist/$ZIP_NAME"
zip -r "dist/$ZIP_NAME" "$PLUGIN_DIR" -x "*.DS_Store"

if ! command -v git >/dev/null 2>&1; then
  echo "git is not available. Install/finish Xcode Command Line Tools first."
  exit 1
fi

if ! command -v gh >/dev/null 2>&1; then
  echo "gh is not available. Install GitHub CLI and run gh auth login."
  exit 1
fi

gh auth status >/dev/null

git add README.md CHANGELOG.md RELEASE.md "$PLUGIN_DIR" .github scripts
git commit -m "Release ${TAG}" || true
git push origin main

if ! git rev-parse "$TAG" >/dev/null 2>&1; then
  git tag "$TAG"
fi

git push origin "$TAG"
gh release view "$TAG" >/dev/null 2>&1 || gh release create "$TAG" "dist/$ZIP_NAME" --title "$TAG" --notes "Release $TAG"
