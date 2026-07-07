<?php

declare(strict_types=1);

use Wlsb\Access\Support\Container;
use Wlsb\Access\Support\NotFoundException;

test('resolves a bound factory', function (): void {
    $container = new Container();
    $container->bind('greeting', fn(): string => 'hello');

    expect($container->get('greeting'))->toBe('hello');
});

test('bind returns a fresh instance on each resolution', function (): void {
    $container = new Container();
    $container->bind('obj', fn(): object => new stdClass());

    expect($container->get('obj'))->not->toBe($container->get('obj'));
});

test('singleton returns the same shared instance', function (): void {
    $container = new Container();
    $container->singleton('obj', fn(): object => new stdClass());

    expect($container->get('obj'))->toBe($container->get('obj'));
});

test('has reflects whether an id is bound', function (): void {
    $container = new Container();
    $container->bind('present', fn(): int => 1);

    expect($container->has('present'))->toBeTrue()
        ->and($container->has('absent'))->toBeFalse();
});

test('factory receives the container so it can resolve dependencies', function (): void {
    $container = new Container();
    $container->bind('dep', fn(): string => 'dep-value');
    $container->bind('needs', fn(Container $c): string => $c->get('dep') . '!');

    expect($container->get('needs'))->toBe('dep-value!');
});

test('resolving an unbound id throws NotFoundException naming the id', function (): void {
    $container = new Container();

    expect(fn() => $container->get('missing'))
        ->toThrow(NotFoundException::class, 'missing');
});
