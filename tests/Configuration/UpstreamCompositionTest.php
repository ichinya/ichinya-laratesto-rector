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
    private function migrate(array $files, ?string $vendorBase = null): array
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
