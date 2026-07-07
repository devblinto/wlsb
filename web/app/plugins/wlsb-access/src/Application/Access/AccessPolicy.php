<?php

declare(strict_types=1);

namespace Wlsb\Access\Application\Access;

use Closure;
use Wlsb\Access\Domain\Access\AccessDecision;
use Wlsb\Access\Domain\Access\AccessRuleRepository;
use Wlsb\Access\Domain\Access\RequestContext;
use Wlsb\Access\Domain\Access\RuleEvaluator;

/**
 * The central access-control gate. Every guard funnels decisions through here,
 * so enforcement is defined in one place (defence in depth on top of WordPress
 * capabilities, never replacing them).
 *
 * `authorize()` is the capability check for features/actions; `decide()` runs
 * the rule engine for a request and is memoised per request context so repeated
 * checks in one request cost nothing extra.
 */
final class AccessPolicy
{
    /** @var array<string, AccessDecision> */
    private array $memo = [];

    /**
     * @param Closure(string): bool $userCan
     */
    public function __construct(
        private readonly AccessRuleRepository $rules,
        private readonly RuleEvaluator $evaluator,
        private readonly Closure $userCan,
    ) {}

    public function authorize(string $capability): bool
    {
        return ($this->userCan)($capability);
    }

    public function decide(RequestContext $context): AccessDecision
    {
        $key = ($context->postId ?? 0) . '|' . ($context->postType ?? '') . '|' . $context->path;

        return $this->memo[$key] ??= $this->evaluator->evaluate($this->rules->all(), $context, $this->userCan);
    }
}
