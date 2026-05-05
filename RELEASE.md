# Release Process

This repository is the source of truth for Unusual Places Explorer.

## Normal Future Release Flow

1. Update plugin files.
2. Bump the plugin version in `unusual-places-explorer/unusual-places-explorer.php`.
3. Add notes to `CHANGELOG.md`.
4. Commit changes to `main`.
5. Create and push a version tag, for example:

```bash
git tag v1.0.1
git push origin v1.0.1
```

GitHub Actions will then:

- build `unusual-places-explorer.zip`,
- upload it as a workflow artifact,
- attach it to the GitHub Release for the tag.

## Manual Build

Go to Actions > Build Plugin Release > Run workflow.

This creates a downloadable zip artifact without making a formal release.

## Current Local Limitation

This Codex machine currently cannot use local `git` until macOS Xcode Command Line Tools finish installing. Until then, Codex can still update this repository through the GitHub connector, but formal local `git push`/`gh release` workflows require:

- Xcode Command Line Tools, for `git`,
- GitHub CLI, for `gh`.
