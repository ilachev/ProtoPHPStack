<?php

declare(strict_types=1);

namespace App\Platform\Hydration\Codegen;

/**
 * Generates the source code for a high-performance, reflection-free hydrator class.
 *
 * This class uses reflection *once* during code generation to inspect a target class.
 * The output is a native PHP class that can hydrate/extract data very quickly.
 */
final class HydratorCodeGenerator
{
    /**
     * Generates the PHP source code for a hydrator for the given class name.
     *
     * @param class-string $originalClassName
     * @param class-string $generatedClassName
     * @throws \ReflectionException
     */
    public function generate(string $originalClassName, string $generatedClassName): string
    {
        $reflection = new \ReflectionClass($originalClassName);

        $hydrateMethod = $this->generateHydrateMethod($reflection);
        $extractMethod = $this->generateExtractMethod($reflection);

        $namespaceSeparatorPosition = strrpos($generatedClassName, '\\');
        if ($namespaceSeparatorPosition === false) {
            throw new \InvalidArgumentException(
                \sprintf('Generated hydrator class name "%s" must be fully-qualified', $generatedClassName),
            );
        }

        $namespace = substr($generatedClassName, 0, $namespaceSeparatorPosition);
        $className = substr($generatedClassName, $namespaceSeparatorPosition + 1);

        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace};

            use {$originalClassName};
            use App\\Platform\\Hydration\\Codegen\\GeneratedHydrator;

            /**
             * Auto-generated hydrator for {@see {$originalClassName}}.
             *
             * DO NOT EDIT. This file is generated at runtime.
             */
            final class {$className} implements GeneratedHydrator
            {
            {$hydrateMethod}

            {$extractMethod}
            }

            PHP;
    }

    /**
     * @param \ReflectionClass<object> $reflection
     */
    private function generateHydrateMethod(\ReflectionClass $reflection): string
    {
        $constructor = $reflection->getConstructor();
        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            return <<<PHP
                    public function hydrate(array \$data): object
                    {
                        return new \\{$reflection->getName()}();
                    }
                PHP;
        }

        $params = $constructor->getParameters();
        $args = [];
        foreach ($params as $param) {
            $name = $param->getName();
            $snakeName = $this->camelToSnake($name);

            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType) {
                $typeName = $type->getName();
                $isNullable = $type->allowsNull();

                if ($isNullable) {
                    $args[] = "            {$name}: array_key_exists('{$snakeName}', \$data) ? ({$typeName}) \$data['{$snakeName}'] : null,";
                } else {
                    $args[] = "            {$name}: ({$typeName}) (\$data['{$snakeName}'] ?? null),";
                }
            } else {
                $args[] = "            {$name}: \$data['{$snakeName}'] ?? null,";
            }
        }
        $argString = implode("\n", $args);

        return <<<PHP
                public function hydrate(array \$data): object
                {
                    return new \\{$reflection->getName()}(
            {$argString}
                    );
                }
            PHP;
    }

    /**
     * @param \ReflectionClass<object> $reflection
     */
    private function generateExtractMethod(\ReflectionClass $reflection): string
    {
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        if (empty($properties)) {
            return <<<'PHP'
                    public function extract(object $object): array
                    {
                        return [];
                    }
                PHP;
        }

        $extractLines = [];
        foreach ($properties as $property) {
            $name = $property->getName();
            $snakeName = $this->camelToSnake($name);
            $extractLines[] = "            '{$snakeName}' => \$object->{$name},";
        }
        $extractString = implode("\n", $extractLines);

        return <<<PHP
                public function extract(object \$object): array
                {
                    /** @var {$reflection->getName()} \$object */
                    return [
            {$extractString}
                    ];
                }
            PHP;
    }

    /**
     * Converts a string from camelCase to snake_case.
     */
    private function camelToSnake(string $input): string
    {
        $result = preg_replace('/(?<!^)[A-Z]/', '_$0', $input);

        return strtolower($result !== null ? $result : $input);
    }
}
