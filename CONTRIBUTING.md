# Contributing

Rector rules, the migration command and their inline fixtures are maintained in
this repository. Send issues and pull requests here. The Laratesto runtime is
maintained separately in https://github.com/ichinya/laratesto.

```bash
composer install
composer validate --no-check-publish
composer test
```

The runtime repository also tests migration integration and assertion parity
against this package under `tests/Rector`.

Release this package by tagging a tested commit as `X.Y.Z`. Its version is
independent of the runtime version. Packagist reads the root `composer.json`
from this repository; no split synchronization is used.
