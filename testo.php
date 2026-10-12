<?php

declare(strict_types=1);

return new \Testo\Application\Config\ApplicationConfig(suites: [
    new \Testo\Application\Config\SuiteConfig(
        name: 'Rector',
        location: ['tests', 'src'],
        plugins: [new \Testo\Bridge\Rector\Testing\RectorTestingPlugin()],
    ),
]);
