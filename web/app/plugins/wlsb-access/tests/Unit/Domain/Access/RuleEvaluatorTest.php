<?php

declare(strict_types=1);

use Wlsb\Access\Domain\Access\AccessRule;
use Wlsb\Access\Domain\Access\MatcherKind;
use Wlsb\Access\Domain\Access\RequestContext;
use Wlsb\Access\Domain\Access\RuleEffect;
use Wlsb\Access\Domain\Access\RuleEvaluator;

function rule(string $id, MatcherKind $kind, string $value, string $cap, RuleEffect $effect, int $priority = 10, bool $enabled = true, ?string $redirect = null): AccessRule
{
    return new AccessRule($id, $kind, $value, $cap, $effect, $redirect, $priority, $enabled);
}

$denyAll = static fn(string $cap): bool => false;
$allowAll = static fn(string $cap): bool => true;

test('no rules means allowed', function () use ($denyAll): void {
    expect((new RuleEvaluator())->evaluate([], new RequestContext('/'), $denyAll)->isAllowed())->toBeTrue();
});

test('a matching rule blocks a user lacking the required capability', function () use ($denyAll): void {
    $rules = [rule('r', MatcherKind::PostId, '42', 'wlsb_view_reports', RuleEffect::Deny)];
    $decision = (new RuleEvaluator())->evaluate($rules, new RequestContext('/reports', postId: 42), $denyAll);

    expect($decision->isAllowed())->toBeFalse()
        ->and($decision->effect)->toBe(RuleEffect::Deny);
});

test('a user holding the required capability is allowed through', function () use ($allowAll): void {
    $rules = [rule('r', MatcherKind::PostId, '42', 'wlsb_view_reports', RuleEffect::Deny)];

    expect((new RuleEvaluator())->evaluate($rules, new RequestContext('/', postId: 42), $allowAll)->isAllowed())->toBeTrue();
});

test('disabled and non-matching rules are ignored', function () use ($denyAll): void {
    $rules = [
        rule('disabled', MatcherKind::PostId, '42', 'cap', RuleEffect::Deny, enabled: false),
        rule('other', MatcherKind::PostId, '99', 'cap', RuleEffect::Deny),
    ];

    expect((new RuleEvaluator())->evaluate($rules, new RequestContext('/', postId: 42), $denyAll)->isAllowed())->toBeTrue();
});

test('a more specific matcher wins over a broader one', function () use ($denyAll): void {
    $rules = [
        rule('broad', MatcherKind::PostType, 'page', 'cap', RuleEffect::Redirect, redirect: '/go'),
        rule('specific', MatcherKind::PostId, '42', 'cap', RuleEffect::Deny),
    ];

    $decision = (new RuleEvaluator())->evaluate($rules, new RequestContext('/', postId: 42, postType: 'page'), $denyAll);

    expect($decision->effect)->toBe(RuleEffect::Deny); // PostId beats PostType
});

test('within the same specificity, higher priority wins', function () use ($denyAll): void {
    $rules = [
        rule('low', MatcherKind::Any, '', 'cap', RuleEffect::Redirect, priority: 5, redirect: '/low'),
        rule('high', MatcherKind::Any, '', 'cap', RuleEffect::Deny, priority: 50),
    ];

    expect((new RuleEvaluator())->evaluate($rules, new RequestContext('/'), $denyAll)->effect)->toBe(RuleEffect::Deny);
});

test('a redirect effect carries its target', function () use ($denyAll): void {
    $rules = [rule('r', MatcherKind::UrlPrefix, '/members', 'cap', RuleEffect::Redirect, redirect: '/login')];

    $decision = (new RuleEvaluator())->evaluate($rules, new RequestContext('/members/area'), $denyAll);

    expect($decision->effect)->toBe(RuleEffect::Redirect)
        ->and($decision->redirect)->toBe('/login');
});

test('a blank capability blocks everyone on the matched surface', function () use ($allowAll): void {
    $rules = [rule('closed', MatcherKind::UrlPrefix, '/secret', '', RuleEffect::Deny)];

    // Even a user who can do everything is blocked, because no capability satisfies a blank requirement.
    expect((new RuleEvaluator())->evaluate($rules, new RequestContext('/secret/x'), $allowAll)->effect)->toBe(RuleEffect::Deny);
});
