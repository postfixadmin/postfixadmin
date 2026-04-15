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

        $this->assertStringContainsString('IN (', $sql);
        $this->assertCount(2, $params);
        $this->assertContains('example.com', $params);
        $this->assertContains('test.org', $params);
    }

    /**
     * Test db_in_clause() with empty array returns safe false predicate.
     */
    public function testDbInClauseEmpty()
    {
        $params = [];
        $sql = db_in_clause('domain', [], $params);

        $this->assertStringContainsString('1=0', $sql);
        $this->assertCount(0, $params);
    }

    /**
     * Test db_in_clause() generates unique param keys when called multiple times.
     */
    public function testDbInClauseUniqueKeys()
    {
        $params = [];
        db_in_clause('domain', ['a.com'], $params);
        db_in_clause('domain', ['b.com'], $params);

        $this->assertCount(2, $params);
        $values = array_values($params);
        $this->assertEquals('a.com', $values[0]);
        $this->assertEquals('b.com', $values[1]);
        // Keys must be different
        $keys = array_keys($params);
        $this->assertNotEquals($keys[0], $keys[1]);
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
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
