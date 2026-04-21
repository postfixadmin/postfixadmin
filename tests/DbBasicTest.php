<?php

class DbBasicTest extends \PHPUnit\Framework\TestCase
{
    private $test_domain;

    public function setUp(): void
    {
        $db = db_connect();
        $test_domain = 'test' . uniqid() . '.com';
        $this->test_domain = $test_domain;

        $db->exec("DELETE FROM domain WHERE domain = '$test_domain'");
    }

    public function testInsertDeleteDomain()
    {
        $domain = $this->test_domain;

        $username = 'testusername' . uniqid();

        $this->assertEquals(
            1,
            db_insert(
                'domain',
                array(
                    'domain' => $domain,
                    'description' => 'test',
                    'transport' => '',
                    'password_expiry' => 99
                )
            )
        );


        $this->assertEquals(1,
            db_insert(
                'mailbox',
                array(
                    'username' => $username,
                    'password' => 'blah',
                    'name' => 'blah',
                    'maildir' => 'blah',
                    'local_part' => 'blah',
                    'domain' => $domain
                )
            )
        );

        $this->assertEquals(1,
            db_update(
                'mailbox',
                'username',
                $username,
                array('name' => 'blah updated')
            )
        );

        $ret = db_query_one("SELECT * FROM mailbox WHERE username = :username", array('username' => $username));


        $this->assertTrue(!empty($ret));
        $this->assertTrue(is_array($ret));


        $this->assertEquals($ret['name'], 'blah updated');

        $this->assertEquals(0, db_delete('mailbox', 'username', 'blahblahinvalid'));

        $this->assertEquals(1, db_delete('mailbox', 'username', $username));
        $this->assertEquals(1, db_delete('domain', 'domain', $domain));
    }

    /**
     * Test db_in_clause() generates correct placeholders and params.
     */
    public function testDbInClause()
    {
        $params = [];
        $sql = db_in_clause('domain', ['example.com', 'test.org'], $params);

        $this->assertEquals(' domain IN (:_in_0_0,:_in_1_1) ', $sql);
        $this->assertEquals(['_in_0_0' => 'example.com', '_in_1_1' => 'test.org'], $params);
    }

    /**
     * Test db_in_clause() with empty array returns safe false predicate.
     */
    public function testDbInClauseEmpty()
    {
        $params = [];
        $sql = db_in_clause('domain', [], $params);

        $this->assertEquals(' 1=0 ', $sql);
        $this->assertCount(0, $params);
    }

    /**
     * Test db_in_clause() generates unique param keys when called multiple times.
     */
    public function testDbInClauseUniqueKeys()
    {
        $params = [];
        $sql1 = db_in_clause('domain', ['a.com'], $params);
        $sql2 = db_in_clause('domain', ['b.com'], $params);

        $this->assertEquals(' domain IN (:_in_0_0) ', $sql1);
        $this->assertEquals(' domain IN (:_in_1_0) ', $sql2);
        $this->assertEquals(['_in_0_0' => 'a.com', '_in_1_0' => 'b.com'], $params);
    }

    /**
     * Test db_in_clause() actually works in a real query.
     */
    public function testDbInClauseInQuery()
    {
        $domain = $this->test_domain;
        db_insert('domain', ['domain' => $domain, 'description' => 'test', 'transport' => '']);

        $params = [];
        $in = db_in_clause('domain', [$domain, 'nonexistent.com'], $params);
        $result = db_query_all("SELECT domain FROM " . table_by_key('domain') . " WHERE $in", $params);

        $domains = array_column($result, 'domain');
        $this->assertContains($domain, $domains);
        $this->assertNotContains('nonexistent.com', $domains);

        db_delete('domain', 'domain', $domain);
    }

    /**
     * Test db_where_clause() generates parameterized WHERE with correct placeholders.
     */
    public function testDbWhereClause()
    {
        $struct = [
            'domain' => ['type' => 'text', 'select' => ''],
            'description' => ['type' => 'text', 'select' => ''],
        ];
        $params = [];
        $sql = db_where_clause(['domain' => 'example.com'], $struct, '', [], $params);

        $this->assertEquals(' WHERE 1=1    AND    ( domain= :_wh_domain ) ', $sql);
        $this->assertEquals('example.com', $params['_wh_domain']);
    }

    /**
     * Test db_where_clause() with multiple conditions.
     */
    public function testDbWhereClauseMultiple()
    {
        $struct = [
            'domain' => ['type' => 'text', 'select' => ''],
            'description' => ['type' => 'text', 'select' => ''],
        ];
        $params = [];
        $sql = db_where_clause(
            ['domain' => 'example.com', 'description' => 'test'],
            $struct, '', [], $params
        );

        $this->assertEquals(' WHERE 1=1    AND    ( domain= :_wh_domain AND description= :_wh_description ) ', $sql);
        $this->assertCount(2, $params);
        $this->assertEquals('example.com', $params['_wh_domain']);
        $this->assertEquals('test', $params['_wh_description']);
    }

    /**
     * Test db_where_clause() with LIKE search mode.
     */
    public function testDbWhereClauseLike()
    {
        $struct = [
            'domain' => ['type' => 'text', 'select' => ''],
        ];
        $params = [];
        $sql = db_where_clause(
            ['domain' => 'example'],
            $struct, '', ['domain' => 'CONT'], $params
        );

        $this->assertEquals(' WHERE 1=1    AND    ( domain LIKE  :_wh_domain ) ', $sql);
        $this->assertEquals(['_wh_domain' => '%example%'], $params);
    }

    /**
     * Test db_where_clause() with NULL operator.
     */
    public function testDbWhereClauseNull()
    {
        $struct = [
            'domain' => ['type' => 'text', 'select' => ''],
        ];
        $params = [];
        $sql = db_where_clause(
            ['domain' => ''],
            $struct, '', ['domain' => 'NULL'], $params
        );

        $this->assertStringContainsString('IS NULL', $sql);
        $this->assertCount(0, $params);
    }

    /**
     * Test db_where_clause() with boolean field conversion.
     */
    public function testDbWhereClauseBoolean()
    {
        $struct = [
            'active' => ['type' => 'bool', 'select' => ''],
        ];
        $params = [];
        $sql = db_where_clause(['active' => true], $struct, '', [], $params);

        $this->assertEquals(' WHERE 1=1    AND    ( active= :_wh_active ) ', $sql);

        $this->assertEquals(['_wh_active' => true], $params);
    }

    /**
     * Test db_where_clause() with additional_raw_where.
     */
    public function testDbWhereClauseAdditionalWhere()
    {
        $struct = [
            'domain' => ['type' => 'text', 'select' => ''],
        ];
        $params = [];
        $sql = db_where_clause(
            ['domain' => 'example.com'],
            $struct, "AND extra_field = 'foo'", [], $params
        );

        $this->assertEquals(" WHERE 1=1  AND extra_field = 'foo'  AND    ( domain= :_wh_domain ) ", $sql);
    }

    /**
     * Test db_where_clause() throws on empty condition.
     */
    public function testDbWhereClauseEmptyThrows()
    {
        $this->expectException(Exception::class);
        db_where_clause([], []);
    }

    /**
     * Test db_delete() with additional params.
     */
    public function testDbDeleteWithAdditionalParams()
    {
        $domain = $this->test_domain;
        db_insert('domain', ['domain' => $domain, 'description' => 'test', 'transport' => '']);
        db_insert('domain', ['domain' => $domain . '.extra', 'description' => 'extra test', 'transport' => '']);

        // Delete with additional WHERE clause
        $result = db_delete('domain', 'domain', $domain . '.extra', "AND description = ?", ['extra test']);
        $this->assertEquals(1, $result);

        // Original should still exist
        $row = db_query_one("SELECT domain FROM " . table_by_key('domain') . " WHERE domain = :domain", ['domain' => $domain]);
        $this->assertNotNull($row);

        db_delete('domain', 'domain', $domain);
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
