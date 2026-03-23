<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Runtime;

use App\Platform\Runtime\Deadline;
use PHPUnit\Framework\TestCase;

final class DeadlineTest extends TestCase
{
    public function testSliceUsesRemainingBudget(): void
    {
        $parentDeadline = Deadline::fromMilliseconds(1);
        $childDeadline = $parentDeadline->sliceSeconds(5.0);

        self::assertLessThanOrEqual(
            $childDeadline->remainingMilliseconds(),
            $parentDeadline->remainingMilliseconds(),
        );
    }

    public function testExpiredDeadlineStaysExpiredWhenSliced(): void
    {
        $expiredDeadline = Deadline::fromMilliseconds(0)->sliceMilliseconds(10);

        self::assertTrue($expiredDeadline->isExhausted());
        self::assertSame(0, $expiredDeadline->remainingMilliseconds());
    }
}
