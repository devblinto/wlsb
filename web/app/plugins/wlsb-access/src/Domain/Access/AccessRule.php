<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Access;

/**
 * A single access-control rule: "requests matching {matcher} require {capability}
 * — otherwise {effect}". A blank capability means nobody satisfies it (a hard
 * block for the matched surface).
 */
final class AccessRule
{
    public function __construct(
        public readonly string $id,
        public readonly MatcherKind $matcherKind,
        public readonly string $matcherValue,
        public readonly string $requiredCapability,
        public readonly RuleEffect $effect,
        public readonly ?string $redirectTarget = null,
        public readonly int $priority = 10,
        public readonly bool $enabled = true,
    ) {}

    public function matches(RequestContext $context): bool
    {
        return match ($this->matcherKind) {
            MatcherKind::Any => true,
            MatcherKind::PostId => $context->postId !== null && $context->postId === (int) $this->matcherValue,
            MatcherKind::PostType => $context->postType !== null && $context->postType === $this->matcherValue,
            MatcherKind::UrlPrefix => $this->matcherValue !== '' && str_starts_with($context->path, $this->matcherValue),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->matcherKind->value,
            'value' => $this->matcherValue,
            'capability' => $this->requiredCapability,
            'effect' => $this->effect->value,
            'redirect' => $this->redirectTarget,
            'priority' => $this->priority,
            'enabled' => $this->enabled,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        if (! isset($data['id'], $data['kind'], $data['effect'])) {
            return null;
        }

        $kind = MatcherKind::tryFrom((string) $data['kind']);
        $effect = RuleEffect::tryFrom((string) $data['effect']);

        if ($kind === null || $effect === null) {
            return null;
        }

        return new self(
            (string) $data['id'],
            $kind,
            (string) ($data['value'] ?? ''),
            (string) ($data['capability'] ?? ''),
            $effect,
            isset($data['redirect']) && $data['redirect'] !== null ? (string) $data['redirect'] : null,
            (int) ($data['priority'] ?? 10),
            (bool) ($data['enabled'] ?? true),
        );
    }
}
