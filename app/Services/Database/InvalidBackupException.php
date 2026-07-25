<?php

namespace App\Services\Database;

use RuntimeException;

/** Thrown before anything is mutated, so the database is always untouched. */
class InvalidBackupException extends RuntimeException {}
