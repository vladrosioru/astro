<?php

namespace App\Services\Database;

class MySqlQuoter implements SqlQuoter
{
    public function quote(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        // Backslash first: escaping it after the others would double-escape
        // the backslashes this very call introduces.
        return "'".str_replace(
            ['\\', "'", "\n", "\r", "\0", "\x1a"],
            ['\\\\', "\\'", '\\n', '\\r', '\\0', '\\Z'],
            (string) $value
        )."'";
    }
}
