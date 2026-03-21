# Contributing to Laravel Log Lens

Thank you for considering a contribution! This document covers the development workflow, coding standards, and the release process for maintainers.

---

## Development Setup

Clone the standalone package repo directly — no host Laravel app is required. Tests run via [Orchestra Testbench](https://github.com/orchestral/testbench), which boots a minimal Laravel environment inside the package itself.

```bash
git clone git@github.com:aniket-magadum/laravel-log-lens.git
cd laravel-log-lens
composer install
```

Work inside `src/`, `tests/`, `config/`, `resources/`, and `routes/`.

---

## Running Tests

```bash
cd packages/aniket-magadum/laravel-log-lens
composer test
```

---

## Static Analysis

```bash
composer analyse
```

---

## Code Style

This project uses [Laravel Pint](https://laravel.com/docs/pint). After making changes, run:

```bash
vendor/bin/pint --dirty
```

---

## Submitting a Pull Request

1. Fork the monorepo and create a branch from `main`.
2. Make your changes inside `packages/aniket-magadum/laravel-log-lens/`.
3. Ensure tests pass and PHPStan reports no errors.
4. Open a pull request against `main` with a clear description of what changed.

---

## Release Process (Maintainers Only)

> The standalone package repo (`github.com/aniket-magadum/laravel-log-lens`) is kept in sync via `git subtree split`. **Never push directly to it** — always go through the steps below.

### 1. Commit all changes to the monorepo

Make sure everything is committed on `main`:

```bash
git add .
git commit -m "Your change description"
```

### 2. Update the version (if applicable)

There is no `version` field in `composer.json` — versioning is driven entirely by **git tags**. Packagist picks up the tag automatically.

### 3. Split the subtree

Extract only the package's history into a local branch:

```bash
git branch -D release/laravel-log-lens 2>/dev/null
git subtree split --prefix=packages/aniket-magadum/laravel-log-lens -b release/laravel-log-lens
```

### 4. Push to the package repo

```bash
git push package release/laravel-log-lens:main
```

> First time only — add the remote if it isn't already present:
> ```bash
> git remote add package git@github.com:aniket-magadum/laravel-log-lens.git
> ```

### 5. Tag the release

Follow [Semantic Versioning](https://semver.org/): `vMAJOR.MINOR.PATCH`

| Change type | Example |
|---|---|
| Breaking change | `v2.0.0` |
| New feature (backwards-compatible) | `v1.1.0` |
| Bug fix | `v1.0.1` |

```bash
git tag vX.Y.Z release/laravel-log-lens
git push package vX.Y.Z
```

### 6. Create a GitHub Release

On `github.com/aniket-magadum/laravel-log-lens`:

- Go to **Releases → Draft a new release**
- Select the tag `vX.Y.Z`
- Write a changelog summary
- Click **Publish release**

Packagist will pick up the new tag automatically via the GitHub webhook.

### 7. Verify on Packagist

Check `packagist.org/packages/aniket-magadum/laravel-log-lens` to confirm the new version appears.

---

## Changelog

Please update the project's changelog or release notes when publishing a new version. Document:

- **Added** — new features
- **Changed** — changes to existing behaviour
- **Fixed** — bug fixes
- **Removed** — removed features

---

## License

By contributing, you agree that your contributions will be licensed under the [MIT License](LICENSE).
