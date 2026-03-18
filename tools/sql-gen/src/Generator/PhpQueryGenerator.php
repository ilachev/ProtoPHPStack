<?php

declare(strict_types=1);

namespace SqlGen\Generator;

use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use SqlGen\Config\GeneratorConfig;
use SqlGen\Model\SqlFile;
use SqlGen\Model\SqlStatement;

final readonly class PhpQueryGenerator
{
    private const PARAM_TYPE = 'string|int|float|bool|null';

    private PsrPrinter $printer;

    public function __construct(
        private GeneratorConfig $config,
    ) {
        $this->printer = new PsrPrinter();
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
            $files[] = new GeneratedFile(
                path: $outputDir . '/' . $statement->getParamsClassName() . '.php',
                content: $this->renderParamsClass($namespace, $statement),
            );
            $files[] = new GeneratedFile(
                path: $outputDir . '/' . $statement->getQueryClassName() . '.php',
                content: $this->renderQueryClass($namespace, $statement),
            );
        }

        $files[] = new GeneratedFile(
            path: $outputDir . '/' . $sqlFile->moduleName . 'Queries.php',
            content: $this->renderQueriesFacade($namespace, $sqlFile),
        );

        return $files;
    }

    private function renderParamsClass(string $namespaceName, SqlStatement $statement): string
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

        return $this->printer->printFile($file);
    }

    private function renderQueryClass(string $namespaceName, SqlStatement $statement): string
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

        $sqlMethod = $class->addMethod('sql');
        $sqlMethod->setReturnType('string');
        $sqlMethod->setBody("return <<<'SQL'\n{$statement->sql}\nSQL;");

        $paramsMethod = $class->addMethod('params');
        $paramsMethod->setReturnType('array');
        $paramsMethod->addComment('@return array<string, scalar|null>');
        $paramsMethod->setBody($this->renderParamsMethodBody($statement));

        return $this->printer->printFile($file);
    }

    private function renderQueriesFacade(string $namespaceName, SqlFile $sqlFile): string
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

        return $this->printer->printFile($file);
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
}
