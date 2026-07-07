<?php

declare(strict_types=1);

use Wlsb\Access\Support\Autoloader;

test('resolves a prefixed class to its PSR-4 file path', function (): void {
    $autoloader = new Autoloader('/plugin/src', 'Wlsb\\Access\\');

    expect($autoloader->resolve('Wlsb\\Access\\Support\\Container'))
        ->toBe('/plugin/src/Support/Container.php');
});

test('resolves a top-level class directly under the base dir', function (): void {
    $autoloader = new Autoloader('/plugin/src', 'Wlsb\\Access\\');

    expect($autoloader->resolve('Wlsb\\Access\\Plugin'))
        ->toBe('/plugin/src/Plugin.php');
});

test('returns null for classes outside the namespace prefix', function (): void {
    $autoloader = new Autoloader('/plugin/src', 'Wlsb\\Access\\');

    expect($autoloader->resolve('Other\\Vendor\\Thing'))->toBeNull();
});

test('autoloads a real class file when registered', function (): void {
    $autoloader = new Autoloader(__DIR__ . '/../fixtures/src', 'Wlsb\\Access\\Fixtures\\');
    $autoloader->register();

    expect(class_exists('Wlsb\\Access\\Fixtures\\Sample'))->toBeTrue();

    $autoloader->unregister();
});
