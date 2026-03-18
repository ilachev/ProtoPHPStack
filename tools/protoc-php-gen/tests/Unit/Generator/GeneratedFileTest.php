<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Generator\GeneratedFile;

#[CoversClass(GeneratedFile::class)]
final class GeneratedFileTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        // Arrange & Act
        $file = new GeneratedFile(
            name: 'path/to/file.php',
            content: '<?php echo "Hello, World!";',
        );

        // Assert
        self::assertSame('path/to/file.php', $file->getName());
        self::assertSame('<?php echo "Hello, World!";', $file->getContent());
    }
}
