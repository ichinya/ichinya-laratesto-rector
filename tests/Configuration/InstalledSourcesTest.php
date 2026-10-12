<?php

declare(strict_types=1);

namespace Laratesto\Rector\Tests\Configuration;

use Composer\InstalledVersions;
use Laratesto\Rector\Configuration\AutoloadPaths;
use Laratesto\Rector\Set\LaratestoRectorSetList;
use Testo\Assert;
use Testo\Test;

final class InstalledSourcesTest
{
    #[Test]
    public function publicSetDiscoversTheInstalledRuntimeAndLaravel(): void
    {
        $runtime = \realpath(InstalledVersions::getInstallPath('ichinya/laratesto'));
        $framework = \realpath(InstalledVersions::getInstallPath('laravel/framework'));
        $paths = AutoloadPaths::forPackage();
        Assert::true(\in_array($runtime . '/src/Shim/PhpUnit', $paths, true));
        Assert::true(\in_array($framework . '/src/Illuminate/Foundation/Testing', $paths, true));
        Assert::true(\is_file(LaratestoRectorSetList::LARAVEL_PHPUNIT_TO_LARATESTO));
        Assert::false(InstalledVersions::isInstalled('phpunit/phpunit'));
    }
}
