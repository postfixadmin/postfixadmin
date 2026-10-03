<?php

/**
 * Direct coverage for PFAHandler::build_select_query(), which previously had no
 * dedicated tests of its own (it was only exercised incidentally through
 * getList()/getPagebrowser() calls scattered across the various *HandlerTest
 * files). It is also the method at the heart of the getPagebrowser() search-key
 * SQL injection fix, so this file leans extra hard on proving that search
 * VALUES are always parameter-bound and never end up concatenated into SQL,
 * regardless of what characters they contain.
 *
 * Uses MailboxHandler as the concrete handler under test, since it has a
 * simple struct with a text field ('name'), a numeric field ('quota'), a
 * single searchfield ('username'), domain-scoping (domain_field), and one
 * 'select'-aliased field ('password2') to exercise the HAVING branch of
 * db_where_clause().
 *
 * Domain/domain_admins rows are inserted directly via db_insert() rather than
 * through DomainHandler::set()/save(), to avoid that handler's (unrelated)
 * DNS-discoverability validation of the domain name.
 */
class BuildSelectQueryTestHandler extends MailboxHandler
{
    public function callBuildSelectQuery($condition, array $searchmode = []): array
    {
        return $this->build_select_query($condition, $searchmode);
    }
}

class BuildSelectQueryTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [
            'sessid' => [
                'roles' => ['global-admin'],
            ],
        ];
        parent::setUp();

        db_insert('domain', ['domain' => 'example.com', 'description' => 'test', 'transport' => 'foo', 'active' => 1, 'backupmx' => 0]);
        db_insert('domain_admins', ['username' => 'admin', 'domain' => 'example.com', 'created' => '2020-01-01', 'active' => 1], ['created'], true);

        $this->addMailbox('alice@example.com', 'Alice Aardvark', 1, 100);
        $this->addMailbox('bob@example.com', 'Bob Builder', 0, 200);
        // a legitimate value that happens to look like a SQL injection payload
        $this->addMailbox('evil@example.com', "x' OR '1'='1", 1, 300, "x' OR '1'='1");
    }

    protected function tearDown(): void
    {
        db_query('DELETE FROM mailbox');
        db_query('DELETE FROM domain_admins');
        db_query('DELETE FROM domain');
        $_SESSION = [];
        parent::tearDown();
    }

    private function addMailbox(string $username, string $name, int $active, int $quota, string $password = 'x'): void
    {
        [$local_part] = explode('@', $username);
        db_insert('mailbox', [
            'username'   => $username,
            'password'   => $password,
            'name'       => $name,
            'maildir'    => 'example.com/' . $local_part . '/',
            'local_part' => $local_part,
            'domain'     => 'example.com',
            'active'     => $active,
            'quota'      => $quota,
        ]);
    }

    private function newHandler(): BuildSelectQueryTestHandler
    {
        return new BuildSelectQueryTestHandler(0, 'admin', true);
    }

    /**
     * run a condition/searchmode through build_select_query() and execute the resulting query
     * @return array [queryparts, rows]
     */
    private function runQuery(array $condition, array $searchmode = []): array
    {
        $queryparts = $this->newHandler()->callBuildSelectQuery($condition, $searchmode);
        $rows = db_query_all($queryparts['select_cols'] . $queryparts['from_where_order'], $queryparts['params']);
        return [$queryparts, $rows];
    }

    public function testReturnsExpectedArrayShape()
    {
        $queryparts = $this->newHandler()->callBuildSelectQuery(['username' => 'alice@example.com'], []);

        $this->assertArrayHasKey('select_cols', $queryparts);
        $this->assertArrayHasKey('from_where_order', $queryparts);
        $this->assertArrayHasKey('params', $queryparts);

        // every :placeholder referenced in the query must have a matching bound param, and vice versa
        preg_match_all('/:([a-zA-Z0-9_]+)/', $queryparts['from_where_order'], $matches);
        $this->assertEqualsCanonicalizing(array_unique($matches[1]), array_keys($queryparts['params']));
    }

    public function testDefaultOperatorIsEquality()
    {
        [$queryparts, $rows] = $this->runQuery(['username' => 'alice@example.com']);

        $this->assertStringContainsString('username= :_wh_username', $queryparts['from_where_order']);
        $this->assertEquals('alice@example.com', $queryparts['params']['_wh_username']);
        $this->assertCount(1, $rows);
        $this->assertEquals('alice@example.com', $rows[0]['username']);
    }

    public function testContSearchmodeMatchesSubstring()
    {
        [, $rows] = $this->runQuery(['name' => 'Aardvark'], ['name' => 'CONT']);

        $this->assertCount(1, $rows);
        $this->assertEquals('alice@example.com', $rows[0]['username']);
    }

    public function testLikeSearchmodeUsesValueVerbatim()
    {
        // LIKE (unlike CONT) does not add wildcards automatically - the caller supplies them
        [, $rows] = $this->runQuery(['name' => '%Build%'], ['name' => 'LIKE']);

        $this->assertCount(1, $rows);
        $this->assertEquals('bob@example.com', $rows[0]['username']);
    }

    public function testComparisonOperatorFiltersNumerically()
    {
        [, $rows] = $this->runQuery(['quota' => 150], ['quota' => '>']);

        $usernames = array_column($rows, 'username');
        sort($usernames);
        $this->assertEquals(['bob@example.com', 'evil@example.com'], $usernames);
    }

    public function testNullOperatorProducesIsNullWithoutBoundParam()
    {
        $queryparts = $this->newHandler()->callBuildSelectQuery(['name' => ''], ['name' => 'NULL']);

        $this->assertStringContainsString('name IS NULL', $queryparts['from_where_order']);
        $this->assertArrayNotHasKey('_wh_name', $queryparts['params']);
    }

    public function testNotNullOperatorProducesIsNotNullWithoutBoundParam()
    {
        $queryparts = $this->newHandler()->callBuildSelectQuery(['name' => ''], ['name' => 'NOTNULL']);

        $this->assertStringContainsString('name IS NOT NULL', $queryparts['from_where_order']);
        $this->assertArrayNotHasKey('_wh_name', $queryparts['params']);
    }

    public function testSimpleSearchExpandsAcrossSearchfields()
    {
        // MailboxHandler::$searchfields = ['username'] - '_' should LIKE-match against it
        [$queryparts, $rows] = $this->runQuery(['_' => 'bob']);

        $this->assertStringContainsString('username LIKE :_search_username', $queryparts['from_where_order']);
        $this->assertEquals('%bob%', $queryparts['params']['_search_username']);
        $this->assertCount(1, $rows);
        $this->assertEquals('bob@example.com', $rows[0]['username']);
    }

    public function testStringConditionIsUsedVerbatim()
    {
        $queryparts = $this->newHandler()->callBuildSelectQuery('1=1', []);

        $this->assertStringContainsString('WHERE ( 1=1 )', $queryparts['from_where_order']);
        // domain_field scoping still applies on top of the raw string condition
        $this->assertEquals(['_in_0_0' => 'example.com'], $queryparts['params']);
    }

    public function testDomainFieldRestrictsToAllowedDomains()
    {
        $queryparts = $this->newHandler()->callBuildSelectQuery([], []);

        $this->assertStringContainsString('domain IN (:_in_0_0)', $queryparts['from_where_order']);
        $this->assertEquals('example.com', $queryparts['params']['_in_0_0']);
    }

    /**
     * A 'select'-aliased struct field (here: MailboxHandler's password2, aliased from
     * 'password as password2') must be filtered in HAVING, not WHERE - db_where_clause()
     * cannot put a condition on a computed column into the WHERE clause.
     * Structural-only: some databases (SQLite among them) reject a HAVING clause with no
     * GROUP BY/aggregate, so this does not execute the query - only inspects the SQL shape.
     */
    public function testAliasedSelectFieldIsFilteredInHavingNotWhere()
    {
        $queryparts = $this->newHandler()->callBuildSelectQuery(['password2' => 'x'], []);

        $this->assertStringContainsString('HAVING ( password2= :_wh_password2 )', $queryparts['from_where_order']);
        $this->assertEquals('x', $queryparts['params']['_wh_password2']);
        // must not also appear as a plain WHERE condition
        $this->assertStringNotContainsString('AND    ( password2', $queryparts['from_where_order']);
    }

    /**
     * SQL injection safety (1/3): a value containing quotes/SQL syntax is legitimate data
     * and must be matched literally, not interpreted as SQL.
     */
    public function testValueContainingQuotesIsTreatedAsLiteralData()
    {
        $payload = "x' OR '1'='1";

        [$queryparts, $rows] = $this->runQuery(['name' => $payload]);

        $this->assertStringNotContainsString($payload, $queryparts['from_where_order'], 'the raw value must never be concatenated into the query text');
        $this->assertCount(1, $rows, 'must match only the row whose name literally equals the payload');
        $this->assertEquals('evil@example.com', $rows[0]['username']);
    }

    /**
     * SQL injection safety (2/3): a classic tautology payload as a search VALUE must not
     * widen the result set - it is just a literal string to compare against.
     */
    public function testTautologyPayloadAsValueMatchesNothing()
    {
        [, $rows] = $this->runQuery(['name' => "nomatch' OR '1'='1"]);

        $this->assertCount(0, $rows);
    }

    /**
     * SQL injection safety (3/3): a destructive payload as a search VALUE must not be
     * executed - the mailbox table must be untouched afterwards.
     */
    public function testDestructivePayloadAsValueDoesNotExecute()
    {
        [, $rows] = $this->runQuery(['name' => "x'; DROP TABLE mailbox; --"]);

        $this->assertCount(0, $rows);
        $count = db_query_all('SELECT COUNT(*) as c FROM mailbox');
        $this->assertEquals(3, $count[0]['c'], 'mailbox table must survive the attempted injection intact');
    }
}
