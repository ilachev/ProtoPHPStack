<?php

declare(strict_types=1);

namespace ProtoPhpGen\Type;

use Typhoon\Type\ClassT;
use Typhoon\Type\NamedObjectT;
use function Typhoon\Type\listT;
use function Typhoon\Type\namedObjectT;
use function Typhoon\Type\stringify;

final readonly class PhpDocTypeRenderer
{
    public function renderListOfObject(string $shortClassName): string
    {
        return stringify(listT($this->createNamedObjectType($shortClassName)));
    }

    public function renderNamedObject(string $shortClassName): string
    {
        return stringify($this->createNamedObjectType($shortClassName));
    }

    public function renderClassString(string $shortClassName): string
    {
        return stringify(new ClassT($this->createNamedObjectType($shortClassName)));
    }

    private function createNamedObjectType(string $shortClassName): NamedObjectT
    {
        /** @var class-string<object> $resolvedClassName */
        $resolvedClassName = $shortClassName;

        return namedObjectT($resolvedClassName);
    }
}
