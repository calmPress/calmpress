# AGENTS.md

## Project overview

CalmPress is a content management system forked from WordPress 6.9. It aims to remain compatible with the WordPress database schema and with the public APIs used by WordPress plugins and themes.

The repository retains the WordPress development layout and much of its tooling:

- `src/` contains the source code.
- `build/` is generated distribution output.
- `tests/phpunit/` contains the PHP test suite.
- `tests/qunit/` and the browser-test directories are inherited from WordPress.
- `tools/`, `Gruntfile.js`, and related configuration provide build and development tooling.

Make changes in `src/`, not generated `build/` output, unless a task explicitly concerns generated artifacts.

## Architecture

Server-side PHP is the source of truth.

Business rules, authorization, validation, persistence, state transitions, and stable public behavior must be implemented in PHP. JavaScript, HTML, and CSS provide the required user experience and presentation; they must not be the sole implementation of essential behavior.

For interactive features, prefer a stable, tested PHP or REST API contract that supplies the UX. Avoid moving authoritative behavior into client-side code.

CalmPress adds first-party functionality beyond standard WordPress, including components under `src/wp-includes/calmpress/`. These components use namespaces and typed PHP where appropriate while continuing to integrate with WordPress hooks and APIs.

## WordPress compatibility

Preserve compatibility with the WordPress 6.9 database, plugin API, and theme API unless a change explicitly intends to diverge.

When changing inherited WordPress behavior:

- Consider existing plugins and themes before changing hooks, filters, globals, database structures, function signatures, return values, or observable side effects.
- Preserve public WordPress APIs and established data formats whenever practical.
- Do not introduce unnecessary database incompatibilities.
- Keep compatibility behavior on the server side rather than relying on a particular admin UI.
- Distinguish intentional CalmPress behavior from accidental divergence from WordPress.

References to WordPress are appropriate when discussing:

- WordPress API or database compatibility;
- WordPress coding standards or upstream documentation;
- wordpress.org;
- the WordPress plugin or theme repositories; or
- behavior intentionally inherited from WordPress.

Documentation for code or behavior changed or introduced by the fork should use “CalmPress” unless it is explicitly referring to one of those WordPress contexts.

## Coding standards and conformance

New and modified code should conform to WordPress coding practices and match the conventions of the surrounding code. Use the repository’s WordPress Coding Standards configuration as guidance for PHP formatting, naming, documentation, escaping, translations, and database access.

In particular:

- Use tabs for indentation except where repository configuration specifies otherwise.
- In CalmPress-specific PHP, use the existing `calmpress` namespaces, class-file naming scheme, and autoloading structure.
- Use scalar parameter types and return types where they fit the surrounding API and do not break compatibility.
- Sanitize input, validate it on the server, escape output for its context, and enforce capabilities and nonces for privileged actions.
- Use prepared database queries and existing WordPress database APIs.
- Do not edit bundled third-party code unless the task explicitly requires it.
- Do not hand-edit generated or minified files when a source file and build process exist.
- Use `.editorconfig` as the baseline for whitespace and line endings.

Automated coding-standard, compatibility, and formatting tools are run only on demand. Do not run repository-wide PHP_CodeSniffer, compatibility analysis, or automated formatting unless explicitly requested.

When a conformance check is requested, keep it scoped to the relevant files where practical. Do not change unrelated code merely to make an analyzer or formatter pass.

## Testing

PHPUnit is available directly on the local development `PATH`. Run tests from the repository root with standard PHPUnit commands and switches. The root `phpunit.xml.dist` is the default configuration.

Examples:

```sh
phpunit
phpunit --filter TestName
phpunit --group group-name
phpunit --testsuite default
phpunit tests/phpunit/tests/path/to/test.php
```

Use the narrowest relevant test command during development, then expand verification in proportion to the risk of the change.

The PHPUnit bootstrap installs and manipulates a test WordPress database. Use only the dedicated disposable database configured in `wp-tests-config.php`; never point the test suite at production or shared data.

Testing priorities are:

- PHP business and domain logic;
- authorization and security rules;
- validation and error handling;
- persistence and state transitions;
- compatibility-sensitive behavior; and
- stable REST API contracts used by the UX.

Direct tests of frequently changing UX markup or decoration are generally not required. QUnit, Playwright, performance, and visual-regression infrastructure is inherited from WordPress and is not actively maintained as part of the primary CalmPress testing strategy. Do not treat the presence of those tools as a requirement to add JavaScript or browser tests for ordinary CalmPress changes.

Add or update PHP tests when changing stable server behavior or fixing a server-side regression.

## Rules for most changes

- Inspect existing behavior, nearby code, and relevant tests before editing.
- Preserve unrelated work already present in the worktree.
- Keep changes focused on the requested behavior.
- Prefer existing CalmPress and WordPress APIs over parallel abstractions.
- Keep essential behavior in PHP and expose stable server contracts to the UX.
- Maintain database, plugin, and theme compatibility unless divergence is explicit.
- Preserve existing hooks and filters; add extensibility through established WordPress patterns when needed.
- Add tests for stable server-side behavior, not transient presentation details.
- Update relevant documentation when public behavior or configuration changes.
- Do not install dependencies, rebuild generated artifacts, modify databases, or perform destructive operations unless the task requires and authorizes it.
- Report verification performed and identify anything that could not be verified.
