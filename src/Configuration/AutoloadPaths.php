<?php

declare(strict_types=1);

namespace Laratesto\Rector\Configuration;

use Composer\InstalledVersions;

/**
 * Resolves the directories that Rector must see when phpunit/phpunit is absent.
 *
 * This is used both by the public Rector set and by the package's own pipeline
 * tests so they do not accidentally overwrite the set's autoload configuration
 * with builder-style calls.
 */
final class AutoloadPaths
{
    /**
     * @param non-empty-string|null $packageRoot Explicit runtime root for pipeline tests.
     * @return list<string>
     */
    public static function forPackage(?string $packageRoot = null): array
    {
        // The runtime can be the root project, a path dependency or an archive
        // under vendor. Neither its location nor Composer's vendor-dir is fixed.
        $packageRoot ??= InstalledVersions::isInstalled('ichinya/laratesto')
            ? InstalledVersions::getInstallPath('ichinya/laratesto')
            : null;
        $paths = [];
        if ($packageRoot !== null) {
            $packageRoot = \realpath($packageRoot) ?: $packageRoot;
            foreach (['Shim/PhpUnit', 'Shim/SebastianBergmann', 'Testing'] as $relative) {
                $paths[] = $packageRoot . '/src/' . $relative;
            }
        }

        $frameworkRoot = InstalledVersions::isInstalled('laravel/framework')
            ? InstalledVersions::getInstallPath('laravel/framework')
            : null;
        if ($frameworkRoot !== null) {
            $frameworkRoot = \realpath($frameworkRoot) ?: $frameworkRoot;
            foreach (['Foundation/Testing', 'Foundation/Testing/Concerns', 'Testing', 'Http', 'Mail'] as $relative) {
                $paths[] = $frameworkRoot . '/src/Illuminate/' . $relative;
            }
        }

        return \array_values(\array_filter($paths, 'is_dir'));
    }
}
