<?php

declare(strict_types=1);

namespace App\Platform\Http\Operation;

interface OperationRegistry
{
    /**
     * @return list<OperationDefinition>
     */
    public function getOperations(): array;
}
