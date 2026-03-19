<?php

declare(strict_types=1);

namespace SqlGen\Generator;

use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use SqlGen\Config\GeneratorConfig;
use SqlGen\Model\DatabaseSchema;
use SqlGen\Model\ResolvedSqlParameter;
use SqlGen\Model\RowField;
use SqlGen\Model\SqlFile;
use SqlGen\Model\SqlStatement;
use SqlGen\Schema\StatementParameterResolver;
use SqlGen\Schema\StatementRowResolver;

final readonly class PhpQueryGenerator
{
    private PsrPrinter $printer;
    private StatementRowResolver $rowResolver;
    private StatementParameterResolver $parameterResolver;

    public function __construct(
        private GeneratorConfig $config,
        private DatabaseSchema $schema,
    ) {
        $this->printer = new PsrPrinter();
        $this->rowResolver = new StatementRowResolver();
        $this->parameterResolver = new StatementParameterResolver();
    }

    /**
     * @return list<GeneratedFile>
     */
    public function generateForSqlFile(SqlFile $sqlFile): array
    {
        $files = [];
        $namespace = rtrim($this->config->namespace, '\\') . '\\' . $sqlFile->moduleName;
        $outputDir = rtrim($this->config->outputDir, '/') . '/' . $sqlFile->moduleName;
        $rowClassNames = $this->resolveRowClassNames($sqlFile);
        $generatedRowClasses = [];

        foreach ($sqlFile->statements as $statement) {
            $rowFields = $this->rowResolver->resolve($statement, $this->schema);
            $rowClassName = $rowClassNames[$statement->name] ?? null;

            if ($rowFields !== [] && is_string($rowClassName) && !isset($generatedRowClasses[$rowClassName])) {
                $files[] = new GeneratedFile(
                    path: $outputDir . '/' . $rowClassName . '.php',
                    content: $this->renderRowClass($namespace, $rowClassName, $rowFields, $sqlFile->sourcePath),
                );
                $generatedRowClasses[$rowClassName] = true;
            }
            $files[] = new GeneratedFile(
                path: $outputDir . '/' . $statement->getQueryClassName() . '.php',
                content: $this->renderQueryClass($namespace, $statement, $rowClassName, $sqlFile->sourcePath),
            );
        }

        return $files;
    }

    /**
     * @param list<RowField> $fields
     */
    private function renderRowClass(string $namespaceName, string $rowClassName, array $fields, string $sourcePath): string
    {
        $file = new PhpFile();
        $file->setStrictTypes();

        $namespace = $file->addNamespace($namespaceName);
        $namespace->addUse('App\Platform\Storage\Sql\DatabaseRow');
        $class = $namespace->addClass($rowClassName);
        $class->setFinal(true);
        $class->setReadOnly(true);
        $class->addImplement('App\Platform\Storage\Sql\DatabaseRow');

        $constructor = $class->addMethod('__construct');

        foreach ($fields as $field) {
            $type = $field->nullable ? '?' . $field->phpType : $field->phpType;
            $constructor
                ->addPromotedParameter($field->propertyName)
                ->setPublic()
                ->setType($type);
        }

        $factory = $class->addMethod('fromDatabaseRow');
        $factory->setStatic();
        $factory->setReturnType('self');
        $factory->addParameter('row')->setType('array');
        $factory->addComment(sprintf('@param %s $row', $this->renderRowShapeDocblock($fields)));
        $factory->setBody($this->renderRowFactoryBody($fields));

        return $this->printGeneratedFile($file, $sourcePath);
    }

    private function renderQueryClass(
        string $namespaceName,
        SqlStatement $statement,
        ?string $rowClassName,
        string $sourcePath,
    ): string
    {
        $rowFields = $this->rowResolver->resolve($statement, $this->schema);
        $parameters = $this->parameterResolver->resolve($statement, $this->schema);

        $file = new PhpFile();
        $file->setStrictTypes();

        $namespace = $file->addNamespace($namespaceName);
        $namespace->addUse('App\Platform\Storage\Sql\ExecutableQuery');
        if ($rowFields !== [] && is_string($rowClassName)) {
            $namespace->addUse('App\Platform\Storage\Sql\RowReturningQuery');
        }

        $class = $namespace->addClass($statement->getQueryClassName());
        $class->setFinal(true);
        $class->setReadOnly(true);
        $class->addImplement($rowFields !== [] && is_string($rowClassName)
            ? 'App\Platform\Storage\Sql\RowReturningQuery'
            : 'App\Platform\Storage\Sql\ExecutableQuery');
        if ($rowFields !== [] && is_string($rowClassName)) {
            $class->addComment("@implements RowReturningQuery<{$rowClassName}>");
        }

        $constructor = $class->addMethod('__construct');
        foreach ($parameters as $parameter) {
            $constructor
                ->addPromotedParameter($parameter->propertyName)
                ->setPrivate()
                ->setType($parameter->phpType);
        }

        $factory = $class->addMethod('create');
        $factory->setStatic();
        $factory->setReturnType('self');

        if ($parameters !== []) {
            foreach ($parameters as $parameter) {
                $factory->addParameter($parameter->propertyName)->setType($parameter->phpType);
            }

            $arguments = implode(
                ",\n",
                array_map(
                    static fn(ResolvedSqlParameter $parameter): string => "            {$parameter->propertyName}: \${$parameter->propertyName}",
                    $parameters,
                ),
            );

            $factory->setBody(
                "return new self(\n"
                . "{$arguments}\n"
                . ');',
            );
        } else {
            $factory->setBody('return new self();');
        }

        $sqlMethod = $class->addMethod('sql');
        $sqlMethod->setReturnType('string');
        $sqlMethod->setBody("return <<<'SQL'\n{$statement->sql}\nSQL;");

        if ($rowFields !== [] && is_string($rowClassName)) {
            $rowClassMethod = $class->addMethod('rowClass');
            $rowClassMethod->setReturnType('string');
            $rowClassMethod->addComment("@return class-string<{$rowClassName}>");
            $rowClassMethod->setBody("return {$rowClassName}::class;");
        }

        $paramsMethod = $class->addMethod('params');
        $paramsMethod->setReturnType('array');
        $paramsMethod->addComment(sprintf('@return %s', $this->renderParamsShapeDocblock($parameters)));
        $paramsMethod->setBody($this->renderParamsMethodBody($parameters));

        return $this->printGeneratedFile($file, $sourcePath);
    }

    /**
     * @param list<RowField> $fields
     */
    private function renderRowFactoryBody(array $fields): string
    {
        $lines = ['return new self('];

        foreach ($fields as $field) {
            $expression = $this->renderRowFieldExpression($field);
            $lines[] = "    {$field->propertyName}: {$expression},";
        }

        $lines[] = ');';

        return implode("\n", $lines);
    }

    /**
     * @param list<ResolvedSqlParameter> $parameters
     */
    private function renderParamsMethodBody(array $parameters): string
    {
        if ($parameters === []) {
            return 'return [];';
        }

        $lines = ['return ['];

        foreach ($parameters as $parameter) {
            $lines[] = "    '{$parameter->name}' => \$this->{$parameter->propertyName},";
        }

        $lines[] = '];';

        return implode("\n", $lines);
    }

    private function renderRowFieldExpression(RowField $field): string
    {
        $source = "\$row['{$field->columnName}']";
        $hasValue = "array_key_exists('{$field->columnName}', \$row) && {$source} !== null";

        if ($field->nullable) {
            return match ($field->phpType) {
                'int' => "{$hasValue} ? (int) {$source} : null",
                'float' => "{$hasValue} ? (float) {$source} : null",
                'bool' => "{$hasValue} ? (bool) {$source} : null",
                default => "{$hasValue} ? (string) {$source} : null",
            };
        }

        return match ($field->phpType) {
            'int' => "{$hasValue} ? (int) {$source} : throw new \\InvalidArgumentException('Missing required column {$field->columnName}.')",
            'float' => "{$hasValue} ? (float) {$source} : throw new \\InvalidArgumentException('Missing required column {$field->columnName}.')",
            'bool' => "{$hasValue} ? (bool) {$source} : throw new \\InvalidArgumentException('Missing required column {$field->columnName}.')",
            default => "{$hasValue} ? (string) {$source} : throw new \\InvalidArgumentException('Missing required column {$field->columnName}.')",
        };
    }

    /**
     * @param list<RowField> $fields
     */
    private function renderRowShapeDocblock(array $fields): string
    {
        $parts = array_map(
            static fn(RowField $field): string => sprintf(
                '%s:%s',
                $field->columnName,
                $field->nullable ? $field->phpType . '|null' : $field->phpType,
            ),
            $fields,
        );

        return 'array{' . implode(', ', $parts) . '}';
    }

    /**
     * @param list<ResolvedSqlParameter> $parameters
     */
    private function renderParamsShapeDocblock(array $parameters): string
    {
        if ($parameters === []) {
            return 'array{}';
        }

        $parts = array_map(
            static fn(ResolvedSqlParameter $parameter): string => "{$parameter->name}:{$parameter->phpType}",
            $parameters,
        );

        return 'array{' . implode(', ', $parts) . '}';
    }

    private function printGeneratedFile(PhpFile $file, string $sourcePath): string
    {
        $content = $this->printer->printFile($file);

        if (!str_starts_with($content, '<?php')) {
            return $content;
        }

        return $this->renderGeneratedHeader($sourcePath) . ltrim(substr($content, strlen('<?php')));
    }

    private function renderGeneratedHeader(string $sourcePath): string
    {
        return <<<PHP
            <?php

            /**
             * Generated by sql-gen.  DO NOT EDIT!
             * source: {$sourcePath}
             * schema: {$this->config->schemaPath}
             */

            PHP;
    }

    /**
     * @return array<string, string>
     */
    private function resolveRowClassNames(SqlFile $sqlFile): array
    {
        $groups = [];

        foreach ($sqlFile->statements as $statement) {
            $rowFields = $this->rowResolver->resolve($statement, $this->schema);
            if ($rowFields === []) {
                continue;
            }

            $signature = $this->buildRowSignature($rowFields);
            if (!isset($groups[$signature])) {
                $groups[$signature] = [];
            }

            $groups[$signature][] = $statement;
        }

        $rowClassNames = [];
        $sharedGroupIndex = 0;

        foreach ($groups as $statements) {
            if (count($statements) === 1) {
                $statement = $statements[0];
                $rowClassNames[$statement->name] = $statement->getRowClassName();
                continue;
            }

            $sharedGroupIndex++;
            $sharedClassName = $sharedGroupIndex === 1
                ? $sqlFile->moduleName . 'Row'
                : $sqlFile->moduleName . 'Row' . $sharedGroupIndex;

            foreach ($statements as $statement) {
                $rowClassNames[$statement->name] = $sharedClassName;
            }
        }

        return $rowClassNames;
    }

    /**
     * @param list<RowField> $fields
     */
    private function buildRowSignature(array $fields): string
    {
        return implode(
            '|',
            array_map(
                static fn(RowField $field): string => implode(':', [
                    $field->columnName,
                    $field->propertyName,
                    $field->phpType,
                    $field->nullable ? 'nullable' : 'required',
                ]),
                $fields,
            ),
        );
    }
}
