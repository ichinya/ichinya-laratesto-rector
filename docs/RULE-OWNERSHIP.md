# Rule ownership and compatibility boundaries

The canonical migration source is `ichinya/laratesto-rector`. Rules, fixtures,
the Artisan migration command and their issues/PRs belong here. Runtime helpers,
the PHPUnit shim and Laravel execution belong in `ichinya/laratesto`.
Generic migration fixes belong in `php-testo/testo`, under `bridge/rector`;
the upstream bridge's standalone repository is its publication mirror.
This package has independent `X.Y.Z` tags and is not a split mirror.

Baseline: Rector 2.7.0, testo/bridge-rector 0.3.3 and Testo 0.10.55.
The published consumer pair is Laratesto `^0.8.0` / migrator `^0.2.0`.
Install it from Packagist without custom repositories or phpunit/phpunit.

## Structural rules

| Local rule/component | Upstream counterpart | Ownership and retained boundary |
|---|---|---|
| `LaravelBaseClassRector` | `ExtendsTestCaseToTestoRector`, `LifecycleMethodToTestoRector` | Laravel parent/bootstrap/hierarchy conversion is atomic. Unsafe descendants preserve their shared source base and receive residuals. |
| `PhpUnitDiscoveryRector` | `ExtendsTestCaseToTestoRector` | Delegate generic classes. Laravel classes stay under the local conversion gate; preserve user skips and upstream protection for unconverted vendor bases. Shared trait discovery requires manual migration. |
| `PhpUnitLifecycleRector` | `LifecycleMethodToTestoRector` | Delegate generic lifecycle; preserve Laravel lifecycle/bootstrap provenance and unsafe boundaries. |
| `PhpUnitFlatAssertionsRector` | `AssertCallToTestoRector` | Delegate generic flat assertions while applying the compatibility receiver/signature guard to overlapping methods, including custom/magic `assertNotFalse`. |
| `LaravelDatabaseTraitsRector` | None | Laravel strategies, literal options, inherited configuration and database safety remain local. |
| `LaravelSourceCompatibleCallsRector` | None for Laravel HTTP/Artisan/mail APIs | Preserve source receiver/signature and lifetime semantics. |
| `LaravelFacadeAssertionsRector` | Generic assertions cannot execute Laravel fake assertions | Local runtime bridge preserves framework/package assertion outcomes and counting. |
| `LaravelResidualDetectionRector`, residual scanner/report | Upstream TODO/stub rules and skips | Local residual schema, remediation and the migration command's dry/apply/report contract remain local. |
| `PhpUnitExceptionExpectationRector` | `ExpectExceptionToTestoRector` | Keep empty-message exactness, last expectation wins, named arguments, receiver ownership and single evaluation. Generic fixes are tracked in [Testo #389](https://github.com/php-testo/testo/issues/389); do not delete the override until a released bridge passes these controls. |

The public set imports `PHPUNIT_TO_TESTO`. Data providers, group/coverage
metadata, supported flat assertions and other generic transformations continue
to come from upstream. It replaces only discovery, lifecycle, flat/typed assertions
and exception expectation lanes requiring the boundaries above.

## Assertions and runtime helpers

`PhpUnitTypedAssertionsRector` routes guarded calls through
`PhpUnitCompatibilityRector`; that rule delegates proven conversions directly
to upstream's `TypedAssertCallToTestoRector`. The shared guard works regardless
of which rule visits a call first. It preserves custom declaring classes,
first-class callable/unpacked boundaries and source argument evaluation.

| Local method family | Upstream counterpart | Current decision |
|---|---|---|
| `assertIsArray`, `assertIsString`, `assertIsInt`, `assertIsBool`, `assertIsObject` | Typed head or message-preserving predicate | Delegate positional calls with 1-2 arguments and a proven string message. Retain helpers for named/unpacked or unsupported calls. |
| `assertFileExists`, `assertFileDoesNotExist` | `file_exists` predicate | Delegate positional calls with a proven string path/message. Named calls and unproven caller-dependent string coercion keep the helper. Runtime uses `file_exists`, including existing directories; the runtime fix is in the companion delivery after 0.8.0. |
| `assertDirectoryExists`, `assertDirectoryDoesNotExist` | `is_dir` predicate | Same guarded positional delegation. |
| `assertEmpty`, `assertNotEmpty` | `blank`/`notBlank` for arrays | Delegate positively identified arrays with positional arguments. Unknown/scalar/object, Countable and EmptyIterator behavior keeps the source-compatible helper. |
| `assertStringContainsString`, `assertStringNotContainsString`, `assertStringStartsWith` | Typed string matchers | Keep caller-dependent coercion, named parameter names and original argument evaluation order. A passing literal-string example alone does not prove arbitrary call equivalence. |
| `assertMatchesRegularExpression`, `assertDoesNotMatchRegularExpression` | Regex matchers | Retain source string coercion and invalid-PCRE outcome until valid/invalid patterns, messages, named arguments and evaluation order are proven equivalent. |
| `assertNotContains` | Strict iterable membership | Keep needle/haystack argument order, iterable consumption, named parameters and message coercion until proven equivalent for those shapes. |
| `assertNotFalse` | `AssertCallToTestoRector` → `notSame(..., false)` | Delegate guarded positional calls directly to upstream's flat rule. Named/unsupported calls and previously generated code retain the runtime helper. |
| `addToAssertionCount` | No generic equivalent | Keep bounded literal increments 0..1000. Negative/dynamic increments require manual migration. |
| `createStub` | Separate mocking-framework migration sets | Preserve the PHPUnit shim factory, disabled automatic return generation and source test-class metadata. Choosing another mocking framework is not an equivalent automatic conversion. |
| `PhpUnitCompatibility`, `src/Shim/PhpUnit` | No migrator can replace runtime | Retain for Laravel/package/deferred assertions, older generated code and the supported legacy converter. Reusing upstream transformations does not remove the runtime API. |

## Evidence and removal gate

- `tests/Configuration/UpstreamCompositionTest.php` runs real public-set CLI
  conversion and a fresh-cache second pass. Positional predicates execute 25
  tests with exactly 25 assertions: 13 expected passes and 12 expected failures.
  Every subject is evaluated once. Named/coercion/custom-receiver controls
  retain their guarded source semantics.
- Runtime `FilesystemAssertionParityTest` executes named and positional helpers
  and public-set output. Eight tests yield eight assertions, five expected
  passes and three expected failures; existing-directory negatives cannot
  become false positives. Dry-run preserves bytes and the second apply is a no-op.
- Runtime `AssertionCompatibilityE2eTest` and `StrictStringParityTest` retain
  substring/empty/last-wins exception, weak/strict coercion, emptiness,
  membership, stub and assertion-count controls.
- Runtime frontier/trait/configuration tests cover project/vendor bases,
  configured and CLI paths, trait target mode, lifecycle provenance, residuals,
  user skips and fresh-cache stability. A successful process exit alone is
  insufficient: an accidentally skipped test can also produce exit 0.

Before expanding upstream reuse, run both positive and negative controls after
actual conversion and execution. Compare failure/error kind, discovery,
messages, evaluation order/count, assertions and retained residuals. Test
named arguments, unknown/custom receivers, weak/strict callers and supported
framework versions. Remove only the transformation branch proven equivalent;
retain runtime compatibility needed by previously generated code.

Named assertion arguments, custom receivers and weak/strict string coercion
are reported in [Testo #390](https://github.com/php-testo/testo/issues/390).
Fresh released-bridge reproduction yields 1 pass, 3 failures and 2 errors;
the guarded conversion yields the intended 5 passes and 1 required failure,
with all 6 assertions counted. The required failure is a nonempty exception
under an exactly-empty message expectation. Source inspection at upstream
`6d2dba5cb84134c8eda26c5202a70be8c9dfa00d` confirms the expectation folding
logic remains; the execution receipt uses released bridge 0.3.3.

Upstream defects can remain tracked independently once the released local
guards prevent them from affecting Laravel consumers. They do not justify
removing those guards before upstream acceptance and a tested bridge release.
