<?php

declare(strict_types=1);

use Wlsb\Access\Domain\Capabilities\Capability;
use Wlsb\Access\Domain\Capabilities\CapabilityGroup;
use Wlsb\Access\Domain\Capabilities\CapabilityRegistry;

test('registers and retrieves a capability by key', function (): void {
    $registry = new CapabilityRegistry();
    $registry->addGroup(new CapabilityGroup('access', 'Access management'));
    $registry->add(new Capability('wlsb_manage_access', 'Manage access', 'access'));

    expect($registry->has('wlsb_manage_access'))->toBeTrue()
        ->and($registry->get('wlsb_manage_access')?->label)->toBe('Manage access')
        ->and($registry->get('missing'))->toBeNull()
        ->and($registry->has('missing'))->toBeFalse();
});

test('all() returns capabilities in registration order', function (): void {
    $registry = new CapabilityRegistry();
    $registry->add(new Capability('b_cap', 'B', 'g'));
    $registry->add(new Capability('a_cap', 'A', 'g'));

    expect(array_map(fn(Capability $c): string => $c->key, $registry->all()))
        ->toBe(['b_cap', 'a_cap']);
});

test('registering the same key again replaces the definition', function (): void {
    $registry = new CapabilityRegistry();
    $registry->add(new Capability('cap', 'First', 'g'));
    $registry->add(new Capability('cap', 'Second', 'g'));

    expect($registry->all())->toHaveCount(1)
        ->and($registry->get('cap')?->label)->toBe('Second');
});

test('groups are returned ordered by their order then registration', function (): void {
    $registry = new CapabilityRegistry();
    $registry->addGroup(new CapabilityGroup('second', 'Second', order: 20));
    $registry->addGroup(new CapabilityGroup('first', 'First', order: 10));

    expect(array_map(fn(CapabilityGroup $g): string => $g->key, $registry->groups()))
        ->toBe(['first', 'second']);
});

test('capabilitiesInGroup returns only that group\'s capabilities in order', function (): void {
    $registry = new CapabilityRegistry();
    $registry->add(new Capability('a', 'A', 'reports'));
    $registry->add(new Capability('b', 'B', 'users'));
    $registry->add(new Capability('c', 'C', 'reports'));

    expect(array_map(fn(Capability $c): string => $c->key, $registry->capabilitiesInGroup('reports')))
        ->toBe(['a', 'c'])
        ->and($registry->capabilitiesInGroup('users'))->toHaveCount(1);
});

test('protectedKeys returns only capabilities flagged protected', function (): void {
    $registry = new CapabilityRegistry();
    $registry->add(new Capability('normal', 'Normal', 'g'));
    $registry->add(new Capability('locked', 'Locked', 'g', protected: true));

    expect($registry->protectedKeys())->toBe(['locked']);
});
