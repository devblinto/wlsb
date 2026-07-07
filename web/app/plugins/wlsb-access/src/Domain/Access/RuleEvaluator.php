<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Access;

/**
 * Evaluates access rules for a request, fail-closed by precedence.
 *
 * Only rules that (a) are enabled, (b) match the request, and (c) require a
 * capability the user lacks, "block". The winning block is chosen by specificity
 * (post id > post type > url prefix > any), then explicit priority, then effect
 * restrictiveness (deny > login > redirect). Rules never grant access — a user
 * holding every required capability is always allowed.
 */
final class RuleEvaluator
{
    /**
     * @param list<AccessRule>       $rules
     * @param callable(string): bool $userCan
     */
    public function evaluate(array $rules, RequestContext $context, callable $userCan): AccessDecision
    {
        $blocking = [];

        foreach ($rules as $rule) {
            if (! $rule->enabled || ! $rule->matches($context)) {
                continue;
            }

            // A user who holds the required capability is not blocked by this rule.
            // A blank capability can never be satisfied, so it always blocks.
            if ($rule->requiredCapability !== '' && $userCan($rule->requiredCapability)) {
                continue;
            }

            $blocking[] = $rule;
        }

        if ($blocking === []) {
            return AccessDecision::allow();
        }

        usort($blocking, [$this, 'compare']);
        $winner = $blocking[0];

        return AccessDecision::block($winner->effect, $winner->redirectTarget);
    }

    private function compare(AccessRule $a, AccessRule $b): int
    {
        return ($b->matcherKind->specificity() <=> $a->matcherKind->specificity())
            ?: ($b->priority <=> $a->priority)
            ?: ($this->effectRank($b->effect) <=> $this->effectRank($a->effect));
    }

    private function effectRank(RuleEffect $effect): int
    {
        return match ($effect) {
            RuleEffect::Deny => 2,
            RuleEffect::RequireLogin => 1,
            RuleEffect::Redirect => 0,
        };
    }
}
