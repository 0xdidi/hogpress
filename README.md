# Connect for PostHog (codename: `hogpress`)

A WordPress plugin that lets a non-developer install, configure, and get real
value from PostHog: server-side event capture, correct identity stitching,
no-flicker feature flags, and dashboards provisioned into PostHog on setup.

> **Naming:** "Connect for PostHog" is a placeholder. The final public name and
> text domain are an open decision (see `docs/01_PRD.md` section 11) to resolve
> before WordPress.org submission. The codename `hogpress` is used for the text
> domain, prefixes, and namespace.

This is the developer README. End-user docs live in `readme.txt` (added in a
later phase).

## Architecture

Two layers with a hard line between them:

- **`src/Core/`** — platform-agnostic PHP. **Zero WordPress function calls.**
  Takes plain data in, returns plain data out. This is what a future Shopify
  build reuses. (Identity resolver, event schema, server client, flag
  evaluator, dashboard provisioner.)
- **`src/Platform/`** — WordPress glue. Adapts WordPress (hooks, options, users,
  admin UI, blocks) to the Core.

See `docs/02_TECHNICAL_SPEC.md` for the full design.

## Requirements

- **Runtime:** PHP 8.2+, WordPress 5.8+. WooCommerce is optional.
- **Dev:** PHP 8.2+ and Composer (for PHPUnit/PHPCS tooling), Node 18+, and
  Docker (for `wp-env`).

> **PHP 8.2 minimum:** the original spec targeted PHP 7.4, but PostHog's official
> `posthog-php` SDK dropped 7.4 support (4.x requires PHP 8.2+). We use the
> current SDK for its built-in graceful degradation and `evaluateFlags()` API, so
> the minimum is PHP 8.2. See `docs/BUILD_NOTES.md`.

## Local development

### Install tooling

```bash
composer install   # PHP dev tools + vendored posthog-php
npm install        # block build + wp-env
```

### Run a real WordPress

`wp-env` uses Docker. Make sure Docker is running, then:

```bash
npm run env:start   # boots WordPress at http://localhost:8888 with the plugin active
npm run env:stop
```

Admin: http://localhost:8888/wp-admin (user `admin`, password `password`).

### Coding standards (PHPCS, WordPress ruleset)

```bash
composer lint       # report violations
composer lint:fix   # auto-fix what can be fixed
```

PHPCS must pass with zero errors before any phase is considered done.

### Tests

```bash
composer test:unit  # platform-agnostic unit tests (Core), run on host PHP
```

WordPress integration tests (added in later phases) run inside `wp-env`.

### Building the feature-flag block

```bash
npm run build       # production block build
npm run start       # watch mode during development
```

## Repository layout

```
hogpress.php            Main plugin file: header, guards, bootstrap
uninstall.php           Uninstall cleanup
src/Core/               Platform-agnostic business logic (no WP calls)
src/Platform/           WordPress glue
blocks/                 Gutenberg block source
assets/                 Built assets, small admin JS/CSS
vendor/                 Vendored posthog-php (committed)
tests/                  PHPUnit unit + integration tests
languages/              .pot translation template
docs/                   PRD, technical spec, build plan, agent instructions
```

## Build process

The build proceeds strictly phase by phase per `docs/03_BUILD_PLAN.md`. Each
phase ends in a working, testable state and a commit naming the phase.
