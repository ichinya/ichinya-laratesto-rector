<?php

declare(strict_types=1);

namespace Laratesto\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\PhpunitToTesto\TypedAssertCallToTestoRector;

/** Retain source-compatible calls before upstream's typed-head rewrites. */
final class PhpUnitTypedAssertionsRector extends AbstractRector
{
    public function __construct(
        private readonly PhpUnitCompatibilityRector $compatibility,
        private readonly TypedAssertCallToTestoRector $upstream,
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
            // A rejected custom receiver or unsupported call shape must stay
            // rejected; falling through would bypass the compatibility guard.
            return $this->compatibility->refactor($node);
        }

        return $this->upstream->refactor($node);
    }
}
