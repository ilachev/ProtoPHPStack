<?php

declare(strict_types=1);

namespace ProtoPhpGen\Generator;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use ProtoPhpGen\Config\GeneratorConfig;
use ProtoPhpGen\Model\EntityDescriptor;
use ProtoPhpGen\Model\PropertyDescriptor;

/**
 * Generator for entity classes.
 */
final readonly class EntityGenerator implements Generator
{
    private PsrPrinter $printer;

    public function __construct(
        private GeneratorConfig $config,
    ) {
        $this->printer = new PsrPrinter();
    }

    public function generate(EntityDescriptor $descriptor): array
    {
        // Create a new PHP file
        $file = new PhpFile();
        $file->setStrictTypes();

        // Add namespace
        $namespace = $file->addNamespace($this->config->getNamespace() . '\Domain');
        $entityInterface = $this->config->getEntityInterface();
        if ($entityInterface !== null) {
            $namespace->addUse($entityInterface);
        }

        // Create entity class
        $class = $namespace->addClass($descriptor->getName());
        $class->setFinal(true)
              ->setReadOnly(true);

        if ($entityInterface !== null) {
            $class->addImplement($this->getShortName($entityInterface));
        }

        // Add docblock for entity
        $class->addComment("Entity class for {$descriptor->getTableName()} table");

        // Add constructor
        $constructor = $class->addMethod('__construct');

        // Add properties
        foreach ($descriptor->getProperties() as $property) {
            $this->addProperty($class, $constructor, $property);
        }

        // Generate code
        $content = $this->printer->printFile($file);

        $filePath = $this->config->getOutputDir() . '/Domain/'
                  . $descriptor->getName() . '.php';

        return [new GeneratedFile($filePath, $content)];
    }

    /**
     * Add a property to the class.
     */
    private function addProperty(
        ClassType $class,
        Method $constructor,
        PropertyDescriptor $property,
    ): void {
        // Add property to class
        $prop = $class->addProperty($property->name)
            ->setPublic()
            ->setType($property->type);

        // Handle nullable properties
        if ($property->nullable) {
            $prop->setNullable(true);
        }

        // Add docblock for property
        $docComment = $property->type;
        if ($property->nullable) {
            $docComment = "?{$docComment}";
        }
        $prop->addComment("@var {$docComment} Column: {$property->getColumnName()}");

        // Add parameter to constructor
        $param = $constructor->addParameter($property->name)
            ->setType($property->type);

        if ($property->nullable) {
            $param->setNullable(true);
        }

        // Set default value for nullable parameters
        if ($property->nullable) {
            $param->setDefaultValue(null);
        }
    }

    /**
     * Get short class name from fully qualified name.
     */
    private function getShortName(string $fullClassName): string
    {
        $parts = explode('\\', $fullClassName);

        return end($parts);
    }
}
