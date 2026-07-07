<?php

declare(strict_types=1);

use Wlsb\Access\Infrastructure\Tokens\RandomTokenGenerator;

test('generates a 64-character hex token that differs each call', function (): void {
    $generator = new RandomTokenGenerator();

    $a = $generator->generate();
    $b = $generator->generate();

    expect($a)->toMatch('/^[0-9a-f]{64}$/')
        ->and($b)->toMatch('/^[0-9a-f]{64}$/')
        ->and($a)->not->toBe($b);
});
