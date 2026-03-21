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
use SqlGen\Model\SqlResultKind;
use SqlGen\Model\SqlStatement;
use SqlGen\Schema\StatementParameterResolver;
use SqlGen\Schema\StatementRowResolver;
use SqlGen\Type\DatabaseValueExpressionRenderer;
use SqlGen\Type\PhpTypeRenderer;

final readonly class PhpQueryGenerator
{
    private PsrPrinter $printer;
    private StatementRowResolver $rowResolver;
    private StatementParameterResolver $parameterResolver;
    private PhpTypeRenderer $phpTypeRenderer;
    private DatabaseValueExpressionRenderer $databaseValueExpressionRenderer;

    public function __construct(
        private GeneratorConfig $config,
        private DatabaseSchema $schema,
    ) {
        $this->printer = new PsrPrinter();
        $this->rowResolver = new StatementRowResolver();
        $this->parameterResolver = new StatementParameterResolver();
        $this->phpTypeRenderer = new PhpTypeRenderer();
        $this->databaseValueExpressionRenderer = new DatabaseValueExpressionRenderer();
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
            try {
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
            } catch (\Throwable $exception) {
                throw new \RuntimeException(
                    sprintf(
                        'Failed to generate SQL artifacts for query %s from %s: %s',
                        $statement->name,
                        $sqlFile->sourcePath,
                        $exception->getMessage(),
                    ),
                    0,
                    $exception,
                );
            }
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
        $class->addComment(sprintf('@implements DatabaseRow<%s>', $this->phpTypeRenderer->renderRowShape($fields)));

        $constructor = $class->addMethod('__construct');

        foreach ($fields as $field) {
            $type = $this->phpTypeRenderer->renderNative($field->phpType);
            $constructor
                ->addPromotedParameter($field->propertyName)
                ->setPublic()
                ->setType($type)
                ->setNullable($field->nullable);
        }

        $factory = $class->addMethod('fromDatabaseRow');
        $factory->setStatic();
        $factory->setReturnType('self');
        $factory->addParameter('row')->setType('array');
        $factory->addComment(sprintf('@param %s $row', $this->phpTypeRenderer->renderRowShape($fields)));
        $factory->setBody($this->renderRowFactoryBody($fields));

        return $this->printGeneratedFile($file, $sourcePath);
    }

    private function renderQueryClass(
        string $namespaceName,
        SqlStatement $statement,
        ?string $rowClassName,
        string $sourcePath,
    ): string {
        $rowFields = $this->rowResolver->resolve($statement, $this->schema);
        $parameters = $this->parameterResolver->resolve($statement, $this->schema);

        $file = new PhpFile();
        $file->setStrictTypes();

        $namespace = $file->addNamespace($namespaceName);
        $namespace->addUse('App\Platform\Storage\Sql\ExecutableQuery');
        if ($rowFields !== [] && is_string($rowClassName)) {
            $namespace->addUse(match ($statement->resultKind) {
                SqlResultKind::One => 'App\Platform\Storage\Sql\OneRowQuery',
                SqlResultKind::Many => 'App\Platform\Storage\Sql\ManyRowsQuery',
                SqlResultKind::Exec => 'App\Platform\Storage\Sql\RowReturningQuery',
            });
        }

        $class = $namespace->addClass($statement->getQueryClassName());
        $class->setFinal(true);
        $class->setReadOnly(true);
        $implementedInterface = $this->resolveQueryInterface($statement, $rowFields !== [] && is_string($rowClassName));
        $class->addImplement($implementedInterface);
        if ($rowFields !== [] && is_string($rowClassName)) {
            $interfaceName = match ($statement->resultKind) {
                SqlResultKind::One => 'OneRowQuery',
                SqlResultKind::Many => 'ManyRowsQuery',
                SqlResultKind::Exec => 'RowReturningQuery',
            };
            $class->addComment(
                sprintf(
                    '@implements %s<%s, %s>',
                    $interfaceName,
                    $rowClassName,
                    $this->phpTypeRenderer->renderParamsShape($parameters),
                ),
            );
        }

        $constructor = $class->addMethod('__construct');
        foreach ($parameters as $parameter) {
            $generatedParameter = $constructor
                ->addPromotedParameter($parameter->propertyName)
                ->setPrivate();
            $generatedParameter
                ->setType($this->phpTypeRenderer->renderNative($parameter->phpType))
                ->setNullable($parameter->nullable);
        }

        $factory = $class->addMethod('create');
        $factory->setStatic();
        $factory->setReturnType('self');

        if ($parameters !== []) {
            foreach ($parameters as $parameter) {
                $generatedParameter = $factory->addParameter($parameter->propertyName);
                $generatedParameter
                    ->setType($this->phpTypeRenderer->renderNative($parameter->phpType))
                    ->setNullable($parameter->nullable);
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
        $paramsMethod->addComment(sprintf('@return %s', $this->phpTypeRenderer->renderParamsShape($parameters)));
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
        return $this->databaseValueExpressionRenderer->renderArrayValue(
            $field->phpType,
            '$row',
            $field->resultColumnName,
            $field->nullable,
        );
    }

    private function printGeneratedFile(PhpFile $file, string $sourcePath): string
    {
        $content = $this->printer->printFile($file);

        if (!str_starts_with($content, '<?php')) {
            return $content;
        }

        return $this->renderGeneratedHeader($sourcePath) . ltrim(substr($content, strlen('<?php')));
    }

    private function resolveQueryInterface(SqlStatement $statement, bool $returnsRows): string
    {
        if (!$returnsRows) {
            return 'App\Platform\Storage\Sql\ExecutableQuery';
        }

        return match ($statement->resultKind) {
            SqlResultKind::One => 'App\Platform\Storage\Sql\OneRowQuery',
            SqlResultKind::Many => 'App\Platform\Storage\Sql\ManyRowsQuery',
            SqlResultKind::Exec => 'App\Platform\Storage\Sql\ExecutableQuery',
        };
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
            try {
                $rowFields = $this->rowResolver->resolve($statement, $this->schema);
            } catch (\Throwable $exception) {
                throw new \RuntimeException(
                    sprintf(
                        'Failed to generate SQL artifacts for query %s from %s: %s',
                        $statement->name,
                        $sqlFile->sourcePath,
                        $exception->getMessage(),
                    ),
                    0,
                    $exception,
                );
            }

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
                fn(RowField $field): string => implode(':', [
                    $field->sourceColumnName,
                    $field->resultColumnName,
                    $field->propertyName,
                    $this->phpTypeRenderer->renderSignature($field->phpType, $field->nullable),
                ]),
                $fields,
            ),
        );
    }
}
