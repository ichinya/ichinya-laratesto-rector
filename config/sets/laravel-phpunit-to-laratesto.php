<?php

declare(strict_types=1);

use Laratesto\Rector\Configuration\AutoloadPaths;
use Laratesto\Rector\Configuration\ConfiguredHierarchy;
use Laratesto\Rector\Rules\LaravelBaseClassRector;
use Laratesto\Rector\Rules\LaravelDatabaseTraitsRector;
use Laratesto\Rector\Rules\LaravelResidualDetectionRector;
use Laratesto\Rector\Rules\LaravelSourceCompatibleCallsRector;
use Laratesto\Rector\Rules\PhpUnitCompatibilityRector;
use Laratesto\Rector\Rules\LaravelFacadeAssertionsRector;
use Laratesto\Rector\Rules\PhpUnitExceptionExpectationRector;
use Laratesto\Rector\Rules\PhpUnitDiscoveryRector;
use Laratesto\Rector\Rules\PhpUnitLifecycleRector;
use Laratesto\Rector\Rules\PhpUnitTypedAssertionsRector;
use Laratesto\Rector\Rules\PhpUnitFlatAssertionsRector;
use Rector\Config\RectorConfig;
use Testo\Bridge\Rector\Set\TestoRectorSetList;
use Testo\Bridge\Rector\PhpunitToTesto\ExpectExceptionToTestoRector;
use Testo\Bridge\Rector\PhpunitToTesto\ExtendsTestCaseToTestoRector;
use Testo\Bridge\Rector\PhpunitToTesto\LifecycleMethodToTestoRector;
use Testo\Bridge\Rector\PhpunitToTesto\TypedAssertCallToTestoRector;
use Testo\Bridge\Rector\PhpunitToTesto\AssertCallToTestoRector;

return static function (RectorConfig $rectorConfig): void {
    // Make the PHPUnit compatibility shim and Laravel's test traits discoverable
    // to Rector's source locator even when phpunit/phpunit is not installed.
    $rectorConfig->autoloadPaths(AutoloadPaths::forPackage());

    // Reuse the generic set, replacing only lanes with Laravel/source semantics.
    $rectorConfig->import(TestoRectorSetList::PHPUNIT_TO_TESTO);
    $rectorConfig->skip([
        ExpectExceptionToTestoRector::class,
        ExtendsTestCaseToTestoRector::class,
        LifecycleMethodToTestoRector::class,
        TypedAssertCallToTestoRector::class,
        AssertCallToTestoRector::class,
    ]);
    $rectorConfig->rule(PhpUnitDiscoveryRector::class);
    $rectorConfig->rule(PhpUnitLifecycleRector::class);
    $rectorConfig->rule(PhpUnitTypedAssertionsRector::class);
    $rectorConfig->rule(PhpUnitFlatAssertionsRector::class);
    $rectorConfig->rule(PhpUnitExceptionExpectationRector::class);
    $rectorConfig->rule(PhpUnitCompatibilityRector::class);
    $rectorConfig->rule(LaravelFacadeAssertionsRector::class);

    // One shared hierarchy recognition state: LaravelBaseClassRector::configure()
    // adopts the configured base classes into it and every Laravel rule reads them
    // from there, so a custom base_classes override governs the whole pipeline.
    $rectorConfig->singleton(ConfiguredHierarchy::class);

    // Laravel-specific rules.
    $rectorConfig->ruleWithConfiguration(LaravelBaseClassRector::class, [
        LaravelBaseClassRector::BASE_CLASSES => LaravelBaseClassRector::DEFAULT_BASE_CLASSES,
        LaravelBaseClassRector::TARGET_MODE => LaravelBaseClassRector::TARGET_MODE_BASE_CLASS,
    ]);
    $rectorConfig->rule(LaravelDatabaseTraitsRector::class);
    $rectorConfig->rule(LaravelSourceCompatibleCallsRector::class);
    $rectorConfig->rule(LaravelResidualDetectionRector::class);
};
