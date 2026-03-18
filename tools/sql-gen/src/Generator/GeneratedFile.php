<?php

declare(strict_types=1);

namespace SqlGen\Generator;

final readonly class GeneratedFile
{
    public function __construct(
        public string $path,
        public string $content,
    ) {}
}
