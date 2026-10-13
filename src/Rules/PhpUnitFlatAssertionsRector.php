<?php

declare(strict_types=1);

namespace Laratesto\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\PhpunitToTesto\AssertCallToTestoRector;

/** Guard compatibility calls before delegating upstream's flat assertion lane. */
final class PhpUnitFlatAssertionsRector extends AbstractRector
{
    public function __construct(
        private readonly PhpUnitCompatibilityRector $compatibility,
        private readonly AssertCallToTestoRector $upstream,
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
        if (($node instanceof MethodCall || $node instanceof StaticCall)
            && $node->name instanceof Identifier
            && PhpUnitCompatibilityRector::supportsMethod($node->name->toString())) {
            return $this->compatibility->refactor($node);
        }
        return $this->upstream->refactor($node);
    }
}
