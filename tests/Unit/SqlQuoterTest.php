<?php

namespace Tests\Unit;

use App\Services\Database\MySqlQuoter;
use App\Services\Database\QuoterFactory;
use App\Services\Database\SqliteQuoter;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class SqlQuoterTest extends TestCase
{
    public function test_mysql_quoter_escapes_quotes_backslashes_and_newlines(): void
    {
        $quoter = new MySqlQuoter;

        $this->assertSame("'plain'", $quoter->quote('plain'));
        $this->assertSame("'O\\'Brien'", $quoter->quote("O'Brien"));
        $this->assertSame("'a\\\\b'", $quoter->quote('a\\b'));
        $this->assertSame("'a\\nb'", $quoter->quote("a\nb"));
    }

    public function test_mysql_quoter_handles_non_strings(): void
    {
        $quoter = new MySqlQuoter;

        $this->assertSame('NULL', $quoter->quote(null));
        $this->assertSame('42', $quoter->quote(42));
        $this->assertSame('1', $quoter->quote(true));
        $this->assertSame('0', $quoter->quote(false));
    }

    public function test_sqlite_quoter_doubles_quotes_and_splits_newlines(): void
    {
        $quoter = new SqliteQuoter;

        $this->assertSame("'plain'", $quoter->quote('plain'));
        $this->assertSame("'O''Brien'", $quoter->quote("O'Brien"));
        $this->assertSame("'a'||char(10)||'b'", $quoter->quote("a\nb"));
        $this->assertSame("''", $quoter->quote(''));
    }

    public function test_every_quoted_value_stays_on_one_line(): void
    {
        foreach ([new MySqlQuoter, new SqliteQuoter] as $quoter) {
            $this->assertStringNotContainsString("\n", $quoter->quote("a\nb\r\nc"));
            $this->assertStringNotContainsString("\r", $quoter->quote("a\nb\r\nc"));
        }
    }

    public function test_sqlite_quoted_value_round_trips_through_the_database(): void
    {
        $quoter = new SqliteQuoter;
        $value = "line one\nline two 'quoted' & \\slashed\\";

        $row = DB::selectOne('SELECT '.$quoter->quote($value).' AS v');

        $this->assertSame($value, $row->v);
    }

    public function test_factory_selects_by_driver(): void
    {
        $this->assertInstanceOf(SqliteQuoter::class, QuoterFactory::for('sqlite'));
        $this->assertInstanceOf(MySqlQuoter::class, QuoterFactory::for('mysql'));
        $this->assertInstanceOf(MySqlQuoter::class, QuoterFactory::for('mariadb'));
    }

    public function test_factory_rejects_an_unsupported_driver(): void
    {
        $this->expectException(InvalidArgumentException::class);

        QuoterFactory::for('pgsql');
    }
}
