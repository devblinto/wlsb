<?php

declare(strict_types=1);

use Wlsb\Access\Domain\Approval\ApprovalPolicy;
use Wlsb\Access\Domain\Approval\ApprovalStep;
use Wlsb\Access\Domain\Approval\ApproverType;
use Wlsb\Access\Domain\Approval\StepStatus;

function roleStep(string $role): ApprovalStep
{
    return new ApprovalStep(1, 10, 1, ApproverType::Role, $role, null, StepStatus::Pending);
}

function userStep(int $userId): ApprovalStep
{
    return new ApprovalStep(1, 10, 1, ApproverType::User, null, $userId, StepStatus::Pending);
}

test('an administrator can always act (universal fallback)', function (): void {
    $policy = new ApprovalPolicy();

    expect($policy->canAct(roleStep('editor'), actorRoleSlugs: [], actorId: 1, isAdministrator: true))->toBeTrue();
});

test('a role step is actionable by holders of that role only', function (): void {
    $policy = new ApprovalPolicy();

    expect($policy->canAct(roleStep('editor'), ['editor'], 5, false))->toBeTrue()
        ->and($policy->canAct(roleStep('editor'), ['author'], 5, false))->toBeFalse();
});

test('a user step is actionable only by that user', function (): void {
    $policy = new ApprovalPolicy();

    expect($policy->canAct(userStep(7), [], 7, false))->toBeTrue()
        ->and($policy->canAct(userStep(7), [], 8, false))->toBeFalse();
});
