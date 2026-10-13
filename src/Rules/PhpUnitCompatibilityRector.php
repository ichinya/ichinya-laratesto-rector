<?php

declare(strict_types=1);

namespace Laratesto\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\Int_;
use PHPStan\Analyser\Scope;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\PhpunitToTesto\TypedAssertCallToTestoRector;

/** Preserves source semantics that upstream typed assertion chains cannot retain. */
final class PhpUnitCompatibilityRector extends AbstractRector
{
    public function __construct(private readonly TypedAssertCallToTestoRector $upstream) {}

    private const METHODS = [
        'assertStringContainsString', 'assertStringNotContainsString',
        'assertEmpty', 'assertNotEmpty', 'assertIsArray', 'assertNotContains',
        'assertDoesNotMatchRegularExpression',
        'assertIsString', 'assertIsInt', 'assertIsBool', 'assertIsObject', 'assertNotFalse',
        'assertMatchesRegularExpression', 'assertStringStartsWith',
        'assertFileExists', 'assertFileDoesNotExist', 'assertDirectoryExists', 'assertDirectoryDoesNotExist',
        'addToAssertionCount',
        'createStub',
    ];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Preserve PHPUnit assertion argument and matching semantics', [
            new CodeSample('$this->assertNotEmpty($value);', '\Laratesto\Testing\PhpUnitCompatibility::assertNotEmpty($value);'),
        ]);
    }

    public function getNodeTypes(): array
    {
        return [MethodCall::class, StaticCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$this->accepts($node)) {
            return null;
        }
        if ($this->canDelegate($node)) {
            $converted = $this->upstream->refactor($node);
            if ($converted !== null) {
                return $converted;
            }
        }
        if (strtolower($node->name->toString()) === 'createstub') {
            if ($node->args[0]->name !== null) {
                $node->args[0]->name = new Identifier('originalClassName');
            }
            $node->args[] = new Arg(new Node\Expr\ClassConstFetch(new Name('static'), 'class'), name: new Identifier('testClass'));
        }
        return new StaticCall(new FullyQualified('Laratesto\Testing\PhpUnitCompatibility'), $node->name, $node->args);
    }

    /** Shared receiver/signature proof for local and delegated conversions. */
    private function accepts(Node $node): bool
    {
        if (!$node instanceof MethodCall && !$node instanceof StaticCall) {
            return false;
        }
        if (!$node->name instanceof Identifier || !$this->sourceReceiver($node)) {
            return false;
        }
        $scope = $node->getAttribute(AttributeKey::SCOPE);
        if (!$scope instanceof Scope || !$scope->isInClass()) {
            return false;
        }
        $method = $node->name->toString();
        if (!self::supportsCall($method, $node->args)) {
            return false;
        }
        $class = $scope->getClassReflection();
        if (strtolower($method) === 'createstub' && !$class->hasNativeMethod($method)) {
            // An unrelated class may provide createStub() through __call().
            // Only PHPUnit's actual factory has the semantics we preserve.
            return false;
        }
        if ($class->hasNativeMethod($method)
            && !\str_starts_with($class->getNativeMethod($method)->getDeclaringClass()->getName(), 'PHPUnit\\Framework\\')) {
            return false;
        }
        if (!$class->hasNativeMethod($method)
            && !str_starts_with($class->getName(), 'PHPUnit\\Framework\\')
            && !$class->isSubclassOf('PHPUnit\\Framework\\TestCase')) {
            // An unrelated __call() cannot prove PHPUnit assertion ownership.
            return false;
        }
        return true;
    }

    /** @param array<Arg|Node\VariadicPlaceholder> $arguments */
    public static function supportsCall(string $method, array $arguments): bool
    {
        $method = \strtolower($method);
        if (!self::supportsMethod($method)) {
            return false;
        }
        foreach ($arguments as $argument) {
            if (!$argument instanceof Arg || $argument->unpack) {
                return false;
            }
        }
        if ($method === 'createstub') {
            if (count($arguments) !== 1) {
                return false;
            }
            if ($arguments[0]->name !== null) {
                if (!class_exists(\PHPUnit\Framework\TestCase::class)) {
                    return false;
                }
                $parameter = (new \ReflectionMethod(\PHPUnit\Framework\TestCase::class, 'createStub'))->getParameters()[0]->getName();
                return $arguments[0]->name->toString() === $parameter;
            }
        }
        // Decrementing a PHPUnit counter has no equivalent in Testo's history.
        // Only the proven, bounded increment form is converted mechanically.
        if ($method === 'addtoassertioncount'
            && (\count($arguments) !== 1 || !$arguments[0]->value instanceof Int_
                || $arguments[0]->value->value < 0 || $arguments[0]->value->value > 1000)) {
            return false;
        }
        return true;
    }

    public static function supportsMethod(string $method): bool
    {
        return \in_array(\strtolower($method), \array_map('strtolower', self::METHODS), true);
    }

    private function sourceReceiver(MethodCall|StaticCall $node): bool
    {
        return $node instanceof MethodCall
            ? $node->var instanceof Variable && $node->var->name === 'this'
            : $node->class instanceof Name && \in_array(\strtolower($node->class->toString()), ['self', 'static'], true);
    }

    private function canDelegate(MethodCall|StaticCall $call): bool
    {
        // Upstream preserves positional type predicates and filesystem checks.
        // Named/unpacked calls and unproven string coercion keep the helper.
        if (count($call->args) < 1 || count($call->args) > 2) {
            return false;
        }
        foreach ($call->args as $arg) {
            if (!$arg instanceof Node\Arg || $arg->name !== null || $arg->unpack) {
                return false;
            }
        }
        $scope = $call->getAttribute(AttributeKey::SCOPE);
        if (!$scope instanceof Scope || (isset($call->args[1]) && !$scope->getType($call->args[1]->value)->isString()->yes())) {
            return false;
        }
        $method = strtolower($call->name->toString());
        if (in_array($method, ['assertisarray', 'assertisstring', 'assertisint', 'assertisbool', 'assertisobject'], true)) {
            return true;
        }
        if (in_array($method, ['assertfileexists', 'assertfiledoesnotexist', 'assertdirectoryexists', 'assertdirectorydoesnotexist'], true)) {
            return $scope->getType($call->args[0]->value)->isString()->yes();
        }
        if (in_array($method, ['assertempty', 'assertnotempty'], true)) {
            return $scope->getType($call->args[0]->value)->isArray()->yes();
        }
        return false;
    }
}
