<?php

class CreatePageBrowserTest extends \PHPUnit\Framework\TestCase {
    public function testBasic() {
        global $CONF;
        $CONF['page_size'] = 5;

        // insert some data. 
        foreach (range(1,10) as $i) {
            $username = 'pbt' . $i;

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
        $b = create_page_browser('mailbox.username FROM mailbox', 'WHERE 1 = 1' );

        $this->assertNotEmpty($b);
    }
}
