<?php

declare(strict_types=1);

namespace App\Dependencies;

use InvalidArgumentException;
use Stringable;

final class InvalidSnowflakeException extends InvalidArgumentException
{
    public function __construct(
        public mixed $value,
    ) {
        parent::__construct(sprintf(
            'Invalid Snowflake: %s',
            is_string($value) || $value instanceof Stringable ? $value : get_debug_type($value),
        ));
    }
}
