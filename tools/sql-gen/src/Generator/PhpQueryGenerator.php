<?php

declare(strict_types=1);

namespace SqlGen\Generator;

use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use SqlGen\Config\GeneratorConfig;
use SqlGen\Model\DatabaseSchema;
use SqlGen\Model\RowField;
use SqlGen\Model\SqlFile;
use SqlGen\Model\SqlStatement;
use SqlGen\Schema\StatementRowResolver;

final readonly class PhpQueryGenerator
{
    private const PARAM_TYPE = 'string|int|float|bool|null';

    private PsrPrinter $printer;
    private StatementRowResolver $rowResolver;

    public function __construct(
        private GeneratorConfig $config,
        private DatabaseSchema $schema,
    ) {
        $this->printer = new PsrPrinter();
        $this->rowResolver = new StatementRowResolver();
    }

    /**
     * @return list<GeneratedFile>
     */
    public function generateForSqlFile(SqlFile $sqlFile): array
    {
        $files = [];
        $namespace = rtrim($this->config->namespace, '\\') . '\\' . $sqlFile->moduleName;
        $outputDir = rtrim($this->config->outputDir, '/') . '/' . $sqlFile->moduleName;

        foreach ($sqlFile->statements as $statement) {
            $rowFields = $this->rowResolver->resolve($statement, $this->schema);

            $files[] = new GeneratedFile(
                path: $outputDir . '/' . $statement->getParamsClassName() . '.php',
                content: $this->renderParamsClass($namespace, $statement, $sqlFile->sourcePath),
            );
            if ($rowFields !== []) {
                $files[] = new GeneratedFile(
                    path: $outputDir . '/' . $statement->getRowClassName() . '.php',
                    content: $this->renderRowClass($namespace, $statement, $rowFields, $sqlFile->sourcePath),
                );
            }
            $files[] = new GeneratedFile(
                path: $outputDir . '/' . $statement->getQueryClassName() . '.php',
                content: $this->renderQueryClass($namespace, $statement, $rowFields !== [], $sqlFile->sourcePath),
            );
        }

        $files[] = new GeneratedFile(
            path: $outputDir . '/' . $sqlFile->moduleName . 'Queries.php',
            content: $this->renderQueriesFacade($namespace, $sqlFile, $sqlFile->sourcePath),
        );

        return $files;
    }

    private function renderParamsClass(string $namespaceName, SqlStatement $statement, string $sourcePath): string
    {
        $file = new PhpFile();
        $file->setStrictTypes();

        $namespace = $file->addNamespace($namespaceName);
        $class = $namespace->addClass($statement->getParamsClassName());
        $class->setFinal(true);
        $class->setReadOnly(true);

        $constructor = $class->addMethod('__construct');

        foreach ($statement->parameters as $parameter) {
            $constructor
                ->addPromotedParameter($parameter->name)
                ->setPublic()
                ->setType(self::PARAM_TYPE);
        }

        return $this->printGeneratedFile($file, $sourcePath);
    }

    /**
     * @param list<RowField> $fields
     */
    private function renderRowClass(string $namespaceName, SqlStatement $statement, array $fields, string $sourcePath): string
    {
        $file = new PhpFile();
        $file->setStrictTypes();

        $namespace = $file->addNamespace($namespaceName);
        $class = $namespace->addClass($statement->getRowClassName());
        $class->setFinal(true);
        $class->setReadOnly(true);

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
        $factory->addComment('@param array<string, scalar|null> $row');
        $factory->setBody($this->renderRowFactoryBody($fields));

        return $this->printGeneratedFile($file, $sourcePath);
    }

    private function renderQueryClass(string $namespaceName, SqlStatement $statement, bool $hasRow, string $sourcePath): string
    {
        $paramsClass = $namespaceName . '\\' . $statement->getParamsClassName();

        $file = new PhpFile();
        $file->setStrictTypes();

        $namespace = $file->addNamespace($namespaceName);
        $namespace->addUse('App\Platform\Storage\Sql\ExecutableQuery');
        $namespace->addUse('App\Platform\Storage\Sql\QueryResultKind');

        $class = $namespace->addClass($statement->getQueryClassName());
        $class->setFinal(true);
        $class->setReadOnly(true);
        $class->addImplement('App\Platform\Storage\Sql\ExecutableQuery');

        $constructor = $class->addMethod('__construct');
        $constructor
            ->addPromotedParameter('params')
            ->setPrivate()
            ->setType($paramsClass);

        $nameMethod = $class->addMethod('name');
        $nameMethod->setReturnType('string');
        $nameMethod->setBody('return ?;', [$statement->name]);

        $resultKindMethod = $class->addMethod('resultKind');
        $resultKindMethod->setReturnType('App\Platform\Storage\Sql\QueryResultKind');
        $resultKindMethod->setBody(
            'return QueryResultKind::from(?);',
            [$statement->resultKind->value],
        );

        if ($hasRow) {
            $rowClassMethod = $class->addMethod('rowClass');
            $rowClassMethod->setReturnType('string');
            $rowClassMethod->setBody('return ?;', [$namespaceName . '\\' . $statement->getRowClassName()]);
        }

        $sqlMethod = $class->addMethod('sql');
        $sqlMethod->setReturnType('string');
        $sqlMethod->setBody("return <<<'SQL'\n{$statement->sql}\nSQL;");

        $paramsMethod = $class->addMethod('params');
        $paramsMethod->setReturnType('array');
        $paramsMethod->addComment('@return array<string, scalar|null>');
        $paramsMethod->setBody($this->renderParamsMethodBody($statement));

        return $this->printGeneratedFile($file, $sourcePath);
    }

    private function renderQueriesFacade(string $namespaceName, SqlFile $sqlFile, string $sourcePath): string
    {
        $file = new PhpFile();
        $file->setStrictTypes();

        $namespace = $file->addNamespace($namespaceName);
        $class = $namespace->addClass($sqlFile->moduleName . 'Queries');
        $class->setFinal(true);
        $class->setReadOnly(true);

        foreach ($sqlFile->statements as $statement) {
            $paramsClass = $namespaceName . '\\' . $statement->getParamsClassName();
            $queryClass = $namespaceName . '\\' . $statement->getQueryClassName();
            $method = $class->addMethod($statement->getFactoryMethodName());
            $method->setReturnType($queryClass);

            if ($statement->parameters !== []) {
                $method->addParameter('params')->setType($paramsClass);
                $method->setBody(
                    'return new ' . $statement->getQueryClassName() . '($params);',
                );
            } else {
                $method->setBody(
                    'return new ' . $statement->getQueryClassName() . '(new ' . $statement->getParamsClassName() . '());',
                );
            }
        }

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

    private function renderParamsMethodBody(SqlStatement $statement): string
    {
        if ($statement->parameters === []) {
            return 'return [];';
        }

        $lines = ['return ['];

        foreach ($statement->parameters as $parameter) {
            $lines[] = "    '{$parameter->name}' => \$this->params->{$parameter->name},";
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
}
