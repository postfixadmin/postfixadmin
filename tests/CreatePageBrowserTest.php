<?php

class CreatePageBrowserTest extends \PHPUnit\Framework\TestCase {
    public function tearDown(): void {
        $this->cleanup();
    }

    private function cleanup() {
        db_query('DELETE FROM mailbox WHERE domain = :domain', array('domain' => 'example.com'));
        db_query('DELETE FROM domain WHERE domain = :domain', array('domain' => 'example.com'));
    }

    public function testBasic() {
        global $CONF;
        $CONF['page_size'] = 10;

        db_insert('domain', array('domain' => 'example.com', 'description' => 'test ' . __FILE__, 'transport' => 'foo', ));

        // insert some data.
        foreach (range(1,100) as $i) {
            $username = md5(random_int(0, 999999));

            $this->assertEquals(1,
                db_insert(
                    'mailbox',
                    array(
                        'username' => $username,
                        'password' => 'blah',
                        'name' => 'blah',
                        'maildir' => 'blah',
                        'local_part' => 'blah',
                        'domain' => 'example.com',
                    )
                )
            );
        }

        // this breaks on sqlite atm.
        $b = create_page_browser('mailbox.username', 'FROM mailbox WHERE 1 = 1');
        $this->assertEquals(10, sizeof($b));
        foreach ($b as $range) {
            $this->assertRegExp('/[\w]{2}\-[\w]{2}/', $range);
        }
        $this->assertNotEmpty($b);
    }
}
