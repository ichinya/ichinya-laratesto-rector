<?php

declare(strict_types=1);

namespace Laratesto\Rector\Tests\Configuration;

use Laratesto\Rector\Configuration\AutoloadPaths;
use Laratesto\Rector\Set\LaratestoRectorSetList;
use Symfony\Component\Process\Process;
use Testo\Assert;
use Testo\Test;

final class UpstreamCompositionTest
{
    #[Test]
    public function provenPositionalPredicatesReuseUpstreamWithRuntimeControls(): void
    {
        $cases = [
            ['IsArray', '[]', '1'], ['IsString', "'value'", '1'],
            ['IsInt', '1', "'value'"], ['IsBool', 'true', '1'],
            ['IsObject', 'new \\stdClass()', '[]'],
            ['NotFalse', '0', 'false'],
            ['Empty', '[]', '[1]'], ['NotEmpty', '[1]', '[]'],
            ['FileExists', '__DIR__', "__DIR__ . '/missing'"],
            ['FileDoesNotExist', "__DIR__ . '/missing'", '__DIR__'],
            ['DirectoryExists', '__DIR__', '__FILE__'],
            ['DirectoryDoesNotExist', "__DIR__ . '/missing'", '__DIR__'],
        ];
        $methods = [];
        foreach ($cases as [$name, $good, $bad]) {
            // Typed path suppliers prove the string boundary and every supplier
            // records evaluation, including assertions whose verdict must fail.
            $supplier = str_contains($name, 'File') || str_contains($name, 'Directory') ? 'path' : 'value';
            if (in_array($name, ['Empty', 'NotEmpty'], true)) {
                $supplier = 'values';
            }
            $methods[] = 'public function testPositive' . $name . '(): void { $this->assert' . $name . '(self::' . $supplier . '(' . $good . '), "message retained"); }';
            $methods[] = 'public function testNegative' . $name . '(): void { $this->assert' . $name . '(self::' . $supplier . '(' . $bad . ')); }';
        }
        $methods[] = 'public function testPositiveFileKind(): void { $this->assertFileExists(self::path(__FILE__)); }';
        $source = '<?php final class PredicateProbe extends \\PHPUnit\\Framework\\TestCase {' . implode("\n", $methods) . <<<'PHP'
            private static function value(mixed $value): mixed { file_put_contents(__DIR__ . '/evaluations', "call\n", FILE_APPEND); return $value; }
            private static function path(string $value): string { file_put_contents(__DIR__ . '/evaluations', "call\n", FILE_APPEND); return $value; }
            private static function values(array $value): array { file_put_contents(__DIR__ . '/evaluations', "call\n", FILE_APPEND); return $value; }
            }
            PHP;
        $files = $this->migrate(['Tests.php' => $source], expectedTotals: ['total' => 25, 'assertions' => 25, 'passed' => 13, 'failed' => 12]);
        Assert::string($files['Tests.php'])->notContains('Laratesto\\Testing\\PhpUnitCompatibility');
        foreach (['\\file_exists(', '\\is_dir(', '\\Testo\\Assert::true(', '\\Testo\\Assert::array(', '\\Testo\\Assert::blank(', '\\Testo\\Assert::notSame('] as $delegated) {
            Assert::string($files['Tests.php'])->contains($delegated);
        }
    }

    #[Test]
    public function namedAndUnprovenCallsKeepTheCompatibilityGuard(): void
    {
        $files = $this->migrate(['Tests.php' => <<<'PHP'
            <?php
            final class BoundaryProbe extends \PHPUnit\Framework\TestCase {
                public function testNamed(): void { $this->assertIsString(actual: 'x', message: 'named'); }
                public function testCoercion(): void { $this->assertFileExists(123); }
            }
            final class CustomPredicate {
                public function assertIsInt($actual): string { return 'custom'; }
                public function run(): string { return $this->assertIsInt('x'); }
            }
            final class MagicPredicate {
                public function __call($name, $arguments): string { return 'magic'; }
                public function run(): string { return $this->assertIsBool(1); }
            }
            PHP]);
        Assert::string($files['Tests.php'])->contains('PhpUnitCompatibility::assertIsString(actual:');
        Assert::string($files['Tests.php'])->contains('PhpUnitCompatibility::assertFileExists(123)');
        Assert::string($files['Tests.php'])->contains("return $" . "this->assertIsInt('x')");
        Assert::string($files['Tests.php'])->contains('return $this->assertIsBool(1)');
    }

    #[Test]
    public function laravelAndGenericDiscoveryKeepTheirSeparateBoundaries(): void
    {
        $files = $this->migrate([
            'Tests.php' => <<<'PHP'
                <?php
                final class LaravelProbe extends \Illuminate\Foundation\Testing\TestCase {
                    public function testActive(): void { $this->assertStringContainsString(needle: '2', haystack: 123); }
                }
                final class GenericProbe extends \PHPUnit\Framework\TestCase {
                    protected function setUp(): void { parent::setUp(); }
                    public function testActive(): void { $this->assertTrue(true); }
                }
                final class CustomHelper {
                    public function assertIsString($value): string { return 'custom'; }
                    public function exercise(): string { return $this->assertIsString(123); }
                }
                PHP,
        ]);

        Assert::string($files['Tests.php'])->contains('extends \Laratesto\Testing\LaravelTestCase');
        Assert::string($files['Tests.php'])->notContains('Testo\Skip');
        Assert::same(2, substr_count($files['Tests.php'], '#[\Testo\Test]'));
        Assert::string($files['Tests.php'])->contains('Testo\Lifecycle\BeforeTest');
        Assert::string($files['Tests.php'])->notContains('extends \PHPUnit\Framework\TestCase');
        Assert::string($files['Tests.php'])->contains('\Laratesto\Testing\PhpUnitCompatibility::assertStringContainsString(needle: \'2\', haystack: 123)');
        Assert::string($files['Tests.php'])->contains('$this->assertIsString(123)');
    }

    #[Test]
    public function sharedTraitDiscoveryRequiresManualMigration(): void
    {
        $traits = <<<'PHP'
            <?php
            namespace SharedDiscovery;
            trait NamedTest { public function testProvided(): void {} }
            trait AnnotatedTest { /** @test */ public function provided(): void {} }
            trait AttributedTest { #[\PHPUnit\Framework\Attributes\Test] public function provided(): void {} }
            trait NestedTest { use NamedTest; }
            PHP;
        $files = $this->migrate([
            'Traits.php' => $traits,
            'Tests.php' => <<<'PHP'
                <?php
                namespace SharedDiscovery;
                final class LaravelProbe extends \Illuminate\Foundation\Testing\TestCase {
                    use NestedTest, AnnotatedTest, AttributedTest;
                    public function testLocal(): void {}
                }
                final class GenericProbe extends \PHPUnit\Framework\TestCase {
                    public function testLocal(): void {}
                }
                PHP,
        ]);

        Assert::same($traits, $files['Traits.php'], 'Shared trait discovery must remain byte-identical.');
        Assert::string($files['Tests.php'])->contains('extends \Illuminate\Foundation\Testing\TestCase');
        Assert::string($files['Tests.php'])->contains('CLASS_UNSAFE_HIERARCHY');
        Assert::string($files['Tests.php'])->notContains('extends \PHPUnit\Framework\TestCase');
        Assert::same(1, substr_count($files['Tests.php'], '#[\Testo\Test]'));
    }

    #[Test]
    public function userSkipsAndUnconvertedGenericVendorBasesKeepTheirProtection(): void
    {
        $files = $this->migrate([
            'Tests.php' => <<<'PHP'
                <?php
                #[\Testo\Skip('user skip')]
                final class SkippedLaravelProbe extends \Illuminate\Foundation\Testing\TestCase {
                    public function testActive(): void { $this->assertTrue(true); }
                }
                final class ForeignVendorProbe extends \Acme\ForeignTestCase {
                    public function testActive(): void { $this->assertTrue(true); }
                }
                PHP,
        ], <<<'PHP'
            <?php
            namespace Acme;
            abstract class ForeignTestCase extends \PHPUnit\Framework\TestCase {}
            PHP);

        Assert::string($files['Tests.php'])->contains("#[\\Testo\\Skip('user skip')]");
        Assert::string($files['Tests.php'])->contains('extends \Acme\ForeignTestCase');
        Assert::string($files['Tests.php'])->contains('a PHPUnit base from vendor');
        Assert::same(2, substr_count($files['Tests.php'], '#[\Testo\Skip('));
    }

    /** @param array<string, string> $files @return array<string, string> */
    private function migrate(array $files, ?string $vendorBase = null, ?array $expectedTotals = null): array
    {
        $root = dirname(__DIR__, 2);
        $tmp = sys_get_temp_dir() . '/laratesto-upstream-' . bin2hex(random_bytes(8));
        mkdir($tmp . '/corpus', 0777, true);
        $tmp = str_replace('\\', '/', (string) realpath($tmp));
        try {
            foreach ($files as $file => $source) {
                file_put_contents($tmp . '/corpus/' . $file, $source);
            }
            if ($vendorBase !== null) {
                file_put_contents($tmp . '/vendor-base.php', $vendorBase);
            }
            $paths = var_export([$tmp . '/corpus'], true);
            $autoload = var_export([...AutoloadPaths::forPackage(), $tmp . '/corpus', ...($vendorBase === null ? [] : [$tmp . '/vendor-base.php'])], true);
            $set = var_export(LaratestoRectorSetList::LARAVEL_PHPUNIT_TO_LARATESTO, true);
            $cache = var_export($tmp . '/cache', true);
            file_put_contents($tmp . '/rector.php', <<<PHP
                <?php
                return \Rector\Config\RectorConfig::configure()
                    ->withPaths({$paths})->withSets([{$set}])
                    ->withAutoloadPaths({$autoload})->withCache(cacheDirectory: {$cache})->withoutParallel();
                PHP);

            $command = [PHP_BINARY, $root . '/vendor/rector/rector/bin/rector', 'process', '--config', $tmp . '/rector.php', '--clear-cache', '--no-progress-bar', '--no-ansi'];
            $run = static function () use ($command, $root): void {
                $process = new Process($command, $root, timeout: 180);
                $process->run();
                Assert::same(0, $process->getExitCode(), $process->getOutput() . $process->getErrorOutput());
            };
            $run();
            $converted = [];
            foreach ($files as $file => $_) {
                $converted[$file] = (string) file_get_contents($tmp . '/corpus/' . $file);
            }
            Assert::true($files !== $converted);
            $run();
            foreach ($converted as $file => $source) {
                Assert::same($source, file_get_contents($tmp . '/corpus/' . $file), 'A fresh-cache second run must preserve bytes.');
            }
            if ($expectedTotals !== null) {
                file_put_contents($tmp . '/testo.php', '<?php return new \\Testo\\Application\\Config\\ApplicationConfig(suites: [new \\Testo\\Application\\Config\\SuiteConfig(name: "Predicates", location: ' . $paths . ')]);');
                $testo = new Process([PHP_BINARY, $root . '/vendor/bin/testo', 'run', '--config', $tmp . '/testo.php', '--log-json=' . $tmp . '/result.json', '--no-ansi'], $root, timeout: 120);
                $testo->run();
                Assert::same(1, $testo->getExitCode(), $testo->getOutput() . $testo->getErrorOutput());
                $report = json_decode(file_get_contents($tmp . '/result.json'), true, flags: JSON_THROW_ON_ERROR);
                Assert::same($expectedTotals, $report['totals']);
                foreach ($report['failures'] as $failure) {
                    Assert::string($failure['test'])->contains('::testNegative');
                }
                Assert::same($expectedTotals['total'], count(file($tmp . '/corpus/evaluations')), 'Every subject must be evaluated exactly once.');
            }
            return $converted;
        } finally {
            // The resolved, randomly named directory contains only this test's
            // inputs and cache. Do not follow links outside it during cleanup.
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tmp, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $entry) {
                $entry->isDir() && !$entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
            rmdir($tmp);
        }
    }
}
