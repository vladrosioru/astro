<?php

namespace App\Services\Database;

class SqliteQuoter implements SqlQuoter
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

        // SQLite has no backslash escapes: a quote is doubled, and a newline
        // can only stay off the line by being concatenated in as char(10).
        $escaped = str_replace("'", "''", (string) $value);
        $segments = preg_split('/(\r\n|\n|\r)/', $escaped, -1, PREG_SPLIT_DELIM_CAPTURE);

        $parts = [];

        foreach ($segments as $index => $segment) {
            if ($index % 2 === 1) {
                $parts[] = match ($segment) {
                    "\r\n" => 'char(13)||char(10)',
                    "\r" => 'char(13)',
                    default => 'char(10)',
                };
            } elseif ($segment !== '') {
                $parts[] = "'".$segment."'";
            }
        }

        return $parts === [] ? "''" : implode('||', $parts);
    }
}
