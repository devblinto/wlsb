<?php

declare(strict_types=1);

use Wlsb\Access\Application\Access\AccessPolicy;
use Wlsb\Access\Domain\Access\AccessRule;
use Wlsb\Access\Domain\Access\MatcherKind;
use Wlsb\Access\Domain\Access\RequestContext;
use Wlsb\Access\Domain\Access\RuleEffect;
use Wlsb\Access\Domain\Access\RuleEvaluator;
use Wlsb\Access\Tests\Support\InMemoryAccessRuleRepository;

test('an access rule round-trips through array serialization', function (): void {
    $rule = new AccessRule('r1', MatcherKind::PostId, '42', 'wlsb_view', RuleEffect::Redirect, '/login', 20, true);

    $rebuilt = AccessRule::fromArray($rule->toArray());

    expect($rebuilt)->not->toBeNull()
        ->and($rebuilt->matcherKind)->toBe(MatcherKind::PostId)
        ->and($rebuilt->effect)->toBe(RuleEffect::Redirect)
        ->and($rebuilt->redirectTarget)->toBe('/login')
        ->and($rebuilt->priority)->toBe(20);
});

test('fromArray rejects malformed data', function (): void {
    expect(AccessRule::fromArray(['id' => 'x', 'kind' => 'nope', 'effect' => 'deny']))->toBeNull()
        ->and(AccessRule::fromArray([]))->toBeNull();
});

test('the gate blocks or allows based on the rules and the user capabilities', function (): void {
    $rules = new InMemoryAccessRuleRepository([
        new AccessRule('r', MatcherKind::PostId, '42', 'wlsb_view', RuleEffect::Deny),
    ]);
    $context = new RequestContext('/', postId: 42);

    $denies = new AccessPolicy($rules, new RuleEvaluator(), fn(string $c): bool => false);
    $allows = new AccessPolicy($rules, new RuleEvaluator(), fn(string $c): bool => true);

    expect($denies->decide($context)->effect)->toBe(RuleEffect::Deny)
        ->and($allows->decide($context)->isAllowed())->toBeTrue();
});

test('authorize wraps the capability checker', function (): void {
    $policy = new AccessPolicy(new InMemoryAccessRuleRepository(), new RuleEvaluator(), fn(string $c): bool => $c === 'yes');

    expect($policy->authorize('yes'))->toBeTrue()
        ->and($policy->authorize('no'))->toBeFalse();
});

test('decisions are memoised per request context', function (): void {
    $rules = new InMemoryAccessRuleRepository();
    $policy = new AccessPolicy($rules, new RuleEvaluator(), fn(string $c): bool => true);
    $context = new RequestContext('/page', postId: 7);

    $policy->decide($context);
    $policy->decide($context);

    expect($rules->allCalls)->toBe(1);
});
