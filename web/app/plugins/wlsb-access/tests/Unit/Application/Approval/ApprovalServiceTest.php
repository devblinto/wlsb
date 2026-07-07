<?php

declare(strict_types=1);

use Wlsb\Access\Application\Approval\ApprovalDecision;
use Wlsb\Access\Application\Approval\ApprovalService;
use Wlsb\Access\Application\Lifecycle\UserLifecycleManager;
use Wlsb\Access\Application\Notifications\NotificationService;
use Wlsb\Access\Domain\Approval\ApprovalPolicy;
use Wlsb\Access\Domain\Approval\WorkflowResolver;
use Wlsb\Access\Domain\Lifecycle\LifecycleState;
use Wlsb\Access\Domain\Workflow\RoleWorkflow;
use Wlsb\Access\Domain\Workflow\WorkflowConfig;
use Wlsb\Access\Tests\Support\ArrayMailer;
use Wlsb\Access\Tests\Support\FakeRegistrationUrls;
use Wlsb\Access\Tests\Support\InMemoryApprovalRepository;
use Wlsb\Access\Tests\Support\InMemoryEventLogger;
use Wlsb\Access\Tests\Support\InMemoryUserDirectory;
use Wlsb\Access\Tests\Support\InMemoryWorkflowConfigStore;

/**
 * @param list<array<string, mixed>> $steps
 */
function approvalScenario(array $steps = [['order' => 1, 'approver_type' => 'role', 'approver_role' => 'editor']]): array
{
    $users = new InMemoryUserDirectory();
    $mailer = new ArrayMailer();
    $log = new InMemoryEventLogger();
    $config = new WorkflowConfig([
        'wlsb_vendor' => new RoleWorkflow('wlsb_vendor', registerable: true, selfSelectable: true, requiresApproval: true, steps: $steps),
    ]);
    $repo = new InMemoryApprovalRepository();

    $service = new ApprovalService(
        $repo,
        new WorkflowResolver(),
        new InMemoryWorkflowConfigStore($config),
        new ApprovalPolicy(),
        new UserLifecycleManager($users, 'wlsb_pending', $log),
        $users,
        new NotificationService($mailer, $log, 'Site'),
        new FakeRegistrationUrls(),
        'https://site.test/admin/approvals',
        $log,
    );

    $applicant = $users->create('vendor1', 'v@example.test', 'h', 'wlsb_pending');
    $users->setStatus($applicant, LifecycleState::PendingApproval);
    $users->setRequestedRole($applicant, 'wlsb_vendor');

    return [$service, $users, $mailer, $repo, $applicant];
}

test('openRequest snapshots the steps and is idempotent', function (): void {
    [$service, , , , $applicant] = approvalScenario();

    $request = $service->openRequest($applicant, 'wlsb_vendor');

    expect($request->steps)->toHaveCount(1)
        ->and($request->currentStep()->approverRole)->toBe('editor')
        ->and($service->openRequest($applicant, 'wlsb_vendor')->id)->toBe($request->id); // idempotent
});

test('an authorised approver approves the final step, activating the user with welcome email', function (): void {
    [$service, $users, $mailer, , $applicant] = approvalScenario();
    $editor = $users->create('ed', 'ed@example.test', 'h', 'editor');
    $request = $service->openRequest($applicant, 'wlsb_vendor');

    $decision = $service->approve($request->id, $editor);

    expect($decision)->toBe(ApprovalDecision::Approved)
        ->and($users->getStatus($applicant))->toBe(LifecycleState::Active)
        ->and($users->roleOf($applicant))->toBe('wlsb_vendor')
        ->and($mailer->last()->key)->toBe('welcome');
});

test('an administrator can approve as the universal fallback', function (): void {
    [$service, $users, , , $applicant] = approvalScenario();
    $admin = $users->create('adm', 'adm@example.test', 'h', 'administrator');
    $request = $service->openRequest($applicant, 'wlsb_vendor');

    expect($service->approve($request->id, $admin))->toBe(ApprovalDecision::Approved);
});

test('an unauthorised actor is forbidden and nothing changes', function (): void {
    [$service, $users, , , $applicant] = approvalScenario();
    $stranger = $users->create('x', 'x@example.test', 'h', 'subscriber');
    $request = $service->openRequest($applicant, 'wlsb_vendor');

    expect($service->approve($request->id, $stranger))->toBe(ApprovalDecision::Forbidden)
        ->and($users->getStatus($applicant))->toBe(LifecycleState::PendingApproval);
});

test('rejecting closes the request, rejects the user, and emails them', function (): void {
    [$service, $users, $mailer, , $applicant] = approvalScenario();
    $editor = $users->create('ed', 'ed@example.test', 'h', 'editor');
    $request = $service->openRequest($applicant, 'wlsb_vendor');

    $decision = $service->reject($request->id, $editor);

    expect($decision)->toBe(ApprovalDecision::Rejected)
        ->and($users->getStatus($applicant))->toBe(LifecycleState::Rejected)
        ->and($users->roleOf($applicant))->toBe('wlsb_pending')
        ->and($mailer->last()->key)->toBe('rejected');
});

test('acting on an already-decided request is a no-op', function (): void {
    [$service, $users, , , $applicant] = approvalScenario();
    $editor = $users->create('ed', 'ed@example.test', 'h', 'editor');
    $request = $service->openRequest($applicant, 'wlsb_vendor');
    $service->approve($request->id, $editor);

    expect($service->approve($request->id, $editor))->toBe(ApprovalDecision::AlreadyClosed);
});

test('a multi-step chain advances instead of finalising on the first approval', function (): void {
    [$service, $users, , $repo, $applicant] = approvalScenario([
        ['order' => 1, 'approver_type' => 'role', 'approver_role' => 'editor'],
        ['order' => 2, 'approver_type' => 'role', 'approver_role' => 'administrator'],
    ]);
    $editor = $users->create('ed', 'ed@example.test', 'h', 'editor');
    $request = $service->openRequest($applicant, 'wlsb_vendor');

    $decision = $service->approve($request->id, $editor);

    expect($decision)->toBe(ApprovalDecision::Advanced)
        ->and($users->getStatus($applicant))->toBe(LifecycleState::PendingApproval)
        ->and($repo->find($request->id)->currentStepOrder)->toBe(2);
});
