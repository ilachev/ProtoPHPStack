<?php

declare(strict_types=1);

namespace App\Infrastructure\Hydrator;

use App\Infrastructure\Hydrator\Generator\HydratorCodeGenerator;

/**
 * Manages the generation and caching of high-performance hydrators.
 *
 * This class acts as the main entry point for hydration. It orchestrates
 * the generation of reflection-free hydrator classes and caches them both on
 * the filesystem (for persistence between processes) and in memory (for
 * performance within a single request).
 */
final class CodeGeneratingHydrator implements HydratorInterface
{
    /**
     * @var array<class-string, GeneratedHydratorInterface>
     */
    private array $instanceCache = [];

    private readonly HydratorCodeGenerator $generator;

    /**
     * @param string $cacheDir the absolute path to the directory where generated hydrators will be stored
     */
    public function __construct(
        private readonly string $cacheDir,
    ) {
        $this->generator = new HydratorCodeGenerator();
    }

    public function hydrate(string $className, array $data): object
    {
        return $this->getHydrator($className)->hydrate($data);
    }

    public function extract(object $object): array
    {
        return $this->getHydrator($object::class)->extract($object);
    }

    /**
     * Gets a hydrator instance for the given class name.
     *
     * @param class-string $className
     * @throws \ReflectionException|\Exception
     */
    private function getHydrator(string $className): GeneratedHydratorInterface
    {
        if (isset($this->instanceCache[$className])) {
            return $this->instanceCache[$className];
        }

        $generatedClassName = $this->getGeneratedClassName($className);
        $fileName = $this->getGeneratedClassFileName($generatedClassName);

        if (!file_exists($fileName)) {
            $this->generateAndSave($className, $generatedClassName, $fileName);
        }

        require_once $fileName;

        /** @var GeneratedHydratorInterface $instance */
        $instance = new $generatedClassName();
        $this->instanceCache[$className] = $instance;

        return $instance;
    }

    /**
     * @param class-string $className
     */
    private function getGeneratedClassName(string $className): string
    {
        return 'App\Infrastructure\Hydrator\Generated\\' . str_replace('\\', '_', $className) . 'Hydrator';
    }

    private function getGeneratedClassFileName(string $generatedClassName): string
    {
        $shortName = substr($generatedClassName, strrpos($generatedClassName, '\\') + 1);

        return $this->cacheDir . \DIRECTORY_SEPARATOR . $shortName . '.php';
    }

    /**
     * @param class-string $originalClassName
     * @param class-string $generatedClassName
     * @throws \ReflectionException
     */
    private function generateAndSave(string $originalClassName, string $generatedClassName, string $fileName): void
    {
        $code = $this->generator->generate($originalClassName, $generatedClassName);

        $dir = \dirname($fileName);
        if (!is_dir($dir) && !mkdir($dir, 0o775, true) && !is_dir($dir)) {
            throw new \RuntimeException(\sprintf('Directory "%s" was not created', $dir));
        }

        if (file_put_contents($fileName, $code) === false) {
            throw new \RuntimeException("Failed to write generated hydrator to {$fileName}");
        }
    }
}
