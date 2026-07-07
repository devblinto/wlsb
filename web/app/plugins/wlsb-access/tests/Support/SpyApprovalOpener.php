<?php

declare(strict_types=1);

namespace Wlsb\Access\Tests\Support;

use Wlsb\Access\Application\Approval\ApprovalOpener;

/**
 * Records open() calls for tests.
 */
final class SpyApprovalOpener implements ApprovalOpener
{
    /** @var list<array{0:int,1:string}> */
    public array $opened = [];

    public function open(int $userId, string $role): void
    {
        $this->opened[] = [$userId, $role];
    }
}
