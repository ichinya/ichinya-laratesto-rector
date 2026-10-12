<?php

declare(strict_types=1);

namespace Laratesto\Rector\Rules;

use Laratesto\Rector\Analysis\LaravelClassBoundary;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\PhpunitToTesto\ExtendsTestCaseToTestoRector;

/** Reuse upstream discovery for generic PHPUnit classes and traits. */
final class PhpUnitDiscoveryRector extends AbstractRector
{
    public function __construct(
        private readonly LaravelClassBoundary $boundary,
        private readonly ExtendsTestCaseToTestoRector $upstream,
    ) {}

    public function getRuleDefinition(): RuleDefinition
    {
        return $this->upstream->getRuleDefinition();
    }

    public function getNodeTypes(): array
    {
        return $this->upstream->getNodeTypes();
    }

    public function refactor(Node $node): ?Node
    {
        if ($node instanceof Class_ && $this->boundary->owns($node)) {
            return null;
        }

        return $this->upstream->refactor($node);
    }
}
