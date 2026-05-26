<?php

declare(strict_types=1);

namespace App\Ordering\Domain\ValueObject;

enum OrderSource: string
{
    case Phone = 'phone';
    case Web = 'web';
    case Manual = 'manual';
}
