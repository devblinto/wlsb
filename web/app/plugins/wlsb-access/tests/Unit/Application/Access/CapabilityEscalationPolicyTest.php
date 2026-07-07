<?php

declare(strict_types=1);

use Wlsb\Access\Application\Access\CapabilityEscalationPolicy;

test('an administrator may grant any capability', function (): void {
    $policy = new CapabilityEscalationPolicy(actorCapabilities: [], actorIsAdministrator: true);

    expect($policy->mayGrant('wlsb_manage_access'))->toBeTrue()
        ->and($policy->violations(['editor' => ['anything' => true]]))->toBe([]);
});

test('a non-administrator may only grant capabilities they themselves hold', function (): void {
    $policy = new CapabilityEscalationPolicy(actorCapabilities: ['wlsb_approve_requests']);

    expect($policy->mayGrant('wlsb_approve_requests'))->toBeTrue()
        ->and($policy->mayGrant('wlsb_manage_access'))->toBeFalse();
});

test('violations lists distinct capabilities the actor tried to grant without holding', function (): void {
    $policy = new CapabilityEscalationPolicy(actorCapabilities: ['wlsb_approve_requests']);

    $violations = $policy->violations([
        'editor' => ['wlsb_manage_access' => true, 'wlsb_approve_requests' => true],
        'author' => ['wlsb_manage_access' => true],           // duplicate offender
        'contributor' => ['wlsb_manage_approvals' => false],  // a revoke is never a violation
    ]);

    expect($violations)->toBe(['wlsb_manage_access']);
});

test('revoking a capability is never an escalation', function (): void {
    $policy = new CapabilityEscalationPolicy(actorCapabilities: []);

    expect($policy->violations(['administrator' => ['wlsb_manage_access' => false]]))->toBe([]);
});
