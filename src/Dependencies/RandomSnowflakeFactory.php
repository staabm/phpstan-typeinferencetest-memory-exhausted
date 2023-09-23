<?php

declare(strict_types=1);

namespace App\Dependencies;

use function App\Functional\contains;

final class RandomSnowflakeFactory
{
    /**
     * @var int[]
     */
    private array $cache = [];
    private ?int $cacheTimestamp = null;

    public function generate(): Snowflake
    {
        $now = (int)(microtime(as_float: true) * 1000) - Snowflake::EPOCH;
        $snowflake = (($now & 0x1FFFFFFFFFF) << 22) | random_int(0, 0x1FFFFF);

        if ($this->cacheTimestamp !== $now) {
            $this->cache = [];
            $this->cacheTimestamp = $now;
        }

        if (contains($this->cache, $snowflake)) {
            usleep(1);

            return $this->generate();
        }

        $this->cache[] = $snowflake;

        return Snowflake::cast($snowflake);
    }
}
