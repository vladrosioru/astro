<?php

namespace App\Services\Database;

use InvalidArgumentException;

class QuoterFactory
{
    public static function for(string $driver): SqlQuoter
    {
        return match ($driver) {
            'mysql', 'mariadb' => new MySqlQuoter,
            'sqlite' => new SqliteQuoter,
            default => throw new InvalidArgumentException("No SQL quoter for driver [{$driver}]."),
        };
    }
}
