<?php

namespace App\Services\Database;

/**
 * Renders a PHP value as a SQL literal that never contains a raw newline.
 *
 * Dumps put one statement per physical line so restore can split on "\n"
 * without parsing SQL — CKEditor bodies are full of semicolons, so splitting
 * on ";" is not an option. Encoding newlines inside a string literal is
 * dialect-specific, which is why this is an interface.
 */
interface SqlQuoter
{
    public function quote(mixed $value): string;
}
