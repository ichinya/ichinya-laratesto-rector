<?php

declare(strict_types=1);

namespace Laratesto\Rector\Rules;

use Laratesto\Rector\Analysis\LaravelClassBoundary;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\PhpunitToTesto\ExtendsTestCaseToTestoRector;

/** Reuse upstream discovery for generic PHPUnit classes. */
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
        // Traits can be shared by Laravel and PHPUnit consumers, including
        // consumers outside the processed paths. Their discovery must stay
        // unchanged until they are migrated manually, as the Laravel gate
        // cannot prove an atomic conversion of all their consumers.
        return [Class_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if ($node instanceof Class_ && $this->boundary->owns($node)) {
            return null;
        }

        return $this->upstream->refactor($node);
    }
}
