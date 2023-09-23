<?php

declare(strict_types=1);

namespace App\Dependencies;

interface Relation
{
    public const PRECEDES = 0b1000000000000;
    public const PRECEDED_BY = 0b0100000000000;
    public const MEETS = 0b0010000000000;
    public const MET_BY = 0b0001000000000;
    public const OVERLAPS = 0b0000100000000;
    public const OVERLAPPED_BY = 0b0000010000000;
    public const STARTS = 0b0000001000000;
    public const STARTED_BY = 0b0000000100000;
    public const ENCLOSES = 0b0000000010000;
    public const ENCLOSED_BY = 0b0000000001000;
    public const FINISHES = 0b0000000000100;
    public const FINISHED_BY = 0b0000000000010;
    public const EQUALS = 0b0000000000001;
}
