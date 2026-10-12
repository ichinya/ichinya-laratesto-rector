<?php

declare(strict_types=1);

// The dev runtime may still classmap its legacy fixture runner. Select this
// package's Rector 2.7 harness before the bridge creates its first fixture.
if (!class_exists(\Testo\Bridge\Rector\Testing\Internal\RectorRunner::class, false)) {
    class_alias(\Laratesto\Rector\Testing\RectorRunner::class, \Testo\Bridge\Rector\Testing\Internal\RectorRunner::class);
}

return new \Testo\Application\Config\ApplicationConfig(suites: [
    new \Testo\Application\Config\SuiteConfig(
        name: 'Rector',
        location: ['tests', 'src'],
        plugins: [new \Testo\Bridge\Rector\Testing\RectorTestingPlugin()],
    ),
]);
