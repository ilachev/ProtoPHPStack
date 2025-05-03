<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Migration;

use Psr\Log\LoggerInterface;

final readonly class MigrationLoader
{
    public function __construct(
        private string $migrationsPath,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return array<MigrationInterface>
     */
    public function loadMigrations(): array
    {
        if (!is_dir($this->migrationsPath)) {
            $this->logger->warning("Migration directory not found: {$this->migrationsPath}");

            return [];
        }

        $migrations = [];
        $files = glob($this->migrationsPath . '/*.php');

        if (!$files) {
            $this->logger->warning("No migration files found in: {$this->migrationsPath}");

            return [];
        }

        foreach ($files as $file) {
            $className = $this->getClassNameFromFile($file);

            if (!class_exists($className)) {
                $this->logger->warning("Migration class not found: {$className}");

                continue;
            }

            $migration = new $className();

            if ($migration instanceof MigrationInterface) {
                $migrations[] = $migration;
                $this->logger->info("Loaded migration: {$className}");
            } else {
                $this->logger->warning("Class is not a migration: {$className}");
            }
        }

        return $migrations;
    }

    private function getClassNameFromFile(string $filePath): string
    {
        // Get the file name without extension
        $basename = pathinfo($filePath, PATHINFO_FILENAME);

        // Extract the namespace from the file
        $content = file_get_contents($filePath);
        $namespace = '';

        if ($content !== false && preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = $matches[1];
        }

        return $namespace . '\\' . $basename;
    }
}
