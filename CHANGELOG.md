# Changelog

## Unreleased

- Delegate proven positional type/filesystem checks and array emptiness to
  upstream while retaining named-argument, custom-receiver and coercion guards.
- Document rule ownership and install the stable runtime/migrator pair from
  Packagist without custom repositories.

## 0.2.0 - 2026-10-13

- Support Rector 2.7.0 and testo/bridge-rector 0.3.3.
- Keep Laravel structural conversion separate from upstream generic discovery
  and lifecycle rewrites, preserving user skips and unsafe-hierarchy boundaries.
- Preserve shared trait test discovery for manual migration, including traits
  whose consumers are outside the processed paths.
- Preserve source assertion semantics before upstream typed-head conversions.
- Update the fixture harness for Rector's container API, imported sets and
  system-error diagnostics.

## 0.1.0 - 2026-10-12

- Publish the independent migration package with Rector 2.6.2 / bridge 0.2.4.
- Resolve runtime and framework reflection paths through Composer.
