<?php

declare(strict_types=1);

use Wlsb\Access\Application\Access\BreakGlass;
use Wlsb\Access\Domain\Capabilities\Cap;

test('no bypass identifier means nobody qualifies and capabilities are untouched', function (): void {
    $breakGlass = new BreakGlass(null);

    expect($breakGlass->qualifies('admin', 'admin@example.test'))->toBeFalse()
        ->and($breakGlass->applyTo(['read' => true], 'admin', 'admin@example.test'))
        ->toBe(['read' => true]);

    expect((new BreakGlass(''))->qualifies('admin', 'admin@example.test'))->toBeFalse();
});

test('a matching login qualifies and is granted manage-access', function (): void {
    $breakGlass = new BreakGlass('rescue');

    expect($breakGlass->qualifies('rescue', 'someone@example.test'))->toBeTrue()
        ->and($breakGlass->applyTo(['read' => true], 'rescue', 'someone@example.test'))
        ->toBe(['read' => true, Cap::MANAGE_ACCESS => true]);
});

test('a matching email qualifies', function (): void {
    $breakGlass = new BreakGlass('owner@example.test');

    expect($breakGlass->qualifies('someuser', 'owner@example.test'))->toBeTrue();
});

test('a non-matching user is left unchanged', function (): void {
    $breakGlass = new BreakGlass('rescue');

    expect($breakGlass->qualifies('intruder', 'intruder@example.test'))->toBeFalse()
        ->and($breakGlass->applyTo(['read' => true], 'intruder', 'intruder@example.test'))
        ->toBe(['read' => true]);
});
