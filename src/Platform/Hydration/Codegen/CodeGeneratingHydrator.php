<?php

declare(strict_types=1);

namespace App\Platform\Hydration\Codegen;

use App\Platform\Hydration\Hydrator;

/**
 * Manages the generation and caching of high-performance hydrators.
 *
 * This class acts as the main entry point for hydration. It orchestrates
 * the generation of reflection-free hydrator classes and caches them both on
 * the filesystem (for persistence between processes) and in memory (for
 * performance within a single request).
 */
final class CodeGeneratingHydrator implements Hydrator
{
    /**
     * @var array<class-string, GeneratedHydrator>
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

    /**
     * @template T of object
     * @param class-string<T> $className
     * @param array<string, mixed> $data
     * @return T
     */
    public function hydrate(string $className, array $data): object
    {
        /** @var T $hydrated */
        $hydrated = $this->getHydrator($className)->hydrate($data);

        return $hydrated;
    }

    /**
     * @return array<string, mixed>
     */
    public function extract(object $object): array
    {
        return $this->getHydrator($object::class)->extract($object);
    }

    /**
     * Gets a hydrator instance for the given class name.
     *
     * @template T of object
     * @param class-string<T> $className
     */
    private function getHydrator(string $className): GeneratedHydrator
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

        /** @var GeneratedHydrator $instance */
        $instance = new $generatedClassName();
        $this->instanceCache[$className] = $instance;

        return $instance;
    }

    /**
     * @param class-string $className
     * @return class-string
     */
    private function getGeneratedClassName(string $className): string
    {
        $generatedClassName = 'App\Platform\Hydration\Generated\\' . str_replace('\\', '_', $className) . 'Hydrator';

        /** @var class-string $generatedClassName */
        return $generatedClassName;
    }

    private function getGeneratedClassFileName(string $generatedClassName): string
    {
        $namespaceSeparatorPosition = strrpos($generatedClassName, '\\');
        if ($namespaceSeparatorPosition === false) {
            throw new \RuntimeException(
                \sprintf('Generated hydrator class name "%s" must be fully-qualified', $generatedClassName),
            );
        }

        $shortName = substr($generatedClassName, $namespaceSeparatorPosition + 1);

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
