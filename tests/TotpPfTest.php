<?php


use PHPUnit\Framework\TestCase;

class TotpPfTest extends TestCase
{
    public function setUp(): void
    {
        global $CONF;
        Config::write('encrypt', 'md5'); // need something stable and non-salted for this test.
        $CONF['encrypt'] = 'md5';

        db_execute("INSERT INTO domain(domain, description, transport) values ('example.com', 'test', 'foo')", [], true);

        db_execute(
            "INSERT INTO mailbox(username, password, name, maildir, local_part, domain, active) VALUES(:username, :password, :name, :maildir, :local_part, :domain, 1)",
            [
                'username' => 'test@example.com',
                'password' => pacrypt('foobar'),
                'name' => 'test user',
                'maildir' => '/foo/bar',
                'local_part' => 'test',
                'domain' => 'example.com',
            ]);

        parent::setUp(); // TODO: Change the autogenerated stub
    }

    public function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown(); // TODO: Change the autogenerated stub
    }

    private function cleanUp()
    {
        db_query('DELETE FROM alias');
        db_query('DELETE FROM alias_domain');
        db_query('DELETE FROM mailbox');
        db_query('DELETE FROM domain_admins');
        db_query('DELETE FROM domain');

        db_query('DELETE FROM totp_exception_address');
        db_query('DELETE FROM mailbox_app_password');

    }


    public function testBasicTotpStuff()
    {
        $x = new TotpPf('mailbox', new Login('mailbox'));
        $array = $x->generate('test@example.com');

        $this->assertIsArray($array);
        $this->assertIsString($array[0]);
        $this->assertIsString($array[1]);


        $y = $x->checkUserTOTP('test@example.com', '123456');

        $z = $x->checkTOTP('asdf', 'fakecode');

        $this->assertFalse($y);
        $this->assertFalse($z);

        $totp = \OTPHP\TOTP::create('asdf');

        $currentCode = $totp->now();
        $this->assertNotEmpty($totp);
        $this->assertTrue($x->checkTOTP('asdf', $currentCode));

        $this->assertNotEmpty($currentCode);
    }

    public function testDbOperations()
    {
        $x = new TotpPf('mailbox', new Login('mailbox'));

        $this->assertNull($x->getException(1));
        $this->assertTrue($x->addException('test@example.com', 'foobar', '1.2.3.4', 'another.test@example.com', 'test'));
    }

    public function testDovecotQuery()
    {


        $sql = /* @lang SQL */
            <<<EOF
SELECT 
    m.username AS user,
    m.password AS password,
    CASE 
        WHEN m.username IS NOT NULL AND m.password = :password_hash AND (m.totp_secret IS NULL OR te.username IS NOT NULL) THEN 'mailbox_auth'
        WHEN app.username IS NOT NULL AND app.password_hash = :password_hash THEN 'app_password_auth'
        ELSE NULL
    END AS auth_type
FROM 
    (SELECT :username AS search_username, :password_hash AS search_password, :client_ip AS client_ip) AS params
LEFT JOIN 
    mailbox m ON m.username = params.search_username AND m.active = 1
LEFT JOIN 
    mailbox_app_password app ON app.username = params.search_username AND app.password_hash = params.search_password
LEFT JOIN 
    totp_exception_address te ON te.username = params.search_username AND te.ip = params.client_ip
WHERE 
    (
        m.username IS NOT NULL AND 
        m.password = params.search_password AND 
        (m.totp_secret IS NULL OR te.username IS NOT NULL)
    )
    OR (app.username IS NOT NULL AND app.password_hash = params.search_password)
LIMIT 1;

EOF;


        db_execute(
            "INSERT INTO mailbox(username, password, name, maildir, local_part, domain, active) VALUES(:username, :password, :name, :maildir, :local_part, :domain, 1)",
            [
                'username' => 'test2@example.com',
                'password' => pacrypt('foobar2'),
                'name' => 'test2 user',
                'maildir' => '/foo/bar2',
                'local_part' => 'test2',
                'domain' => 'example.com',
            ]);

        db_execute('INSERT INTO mailbox_app_password (username, description, password_hash) VALUES (:username, :description, :password_hash)', ['username' => 'test@example.com', 'description' => 'test foobar', 'password_hash' => pacrypt('foobar-app')]);
        db_execute('INSERT INTO mailbox_app_password (username, description, password_hash) VALUES (:username, :description, :password_hash)', ['username' => 'test@example.com', 'description' => 'test foobar234', 'password_hash' => pacrypt('foobar234')]);

        db_execute('INSERT INTO totp_exception_address (ip, username, description) VALUES (:ip, :username, :description)', ['ip' => '4.3.2.1', 'username' => 'test@example.com', 'description' => 'test 4.3.2.1']);
        db_execute('INSERT INTO totp_exception_address (ip, username, description) VALUES (:ip, :username, :description)', ['ip' => '4.3.2.2', 'username' => 'test@example.com', 'description' => 'test 4.3.2.2']);
        db_execute('INSERT INTO totp_exception_address (ip, username, description) VALUES (:ip, :username, :description)', ['ip' => '4.3.2.2', 'username' => 'test2@example.com', 'description' => 'test2 4.3.2.2']);

        $checkIt = function (string $username, string $plain_password, string $ip, string $auth_type) use ($sql): bool {
            $rows = db_query_all($sql, $params = [
                'username' => $username,
                'password_hash' => pacrypt($plain_password),
                'client_ip' => $ip,
            ]);

            if (count($rows) != 1) {
                //echo "FAIL " . count($rows) . " from " . json_encode($params);
                return false;
            }

            $row = $rows[0];
            $success = $row['user'] == $username && $row['auth_type'] == $auth_type;

            //echo json_encode(['params' => $params, 'result' => $row]) . "\n";

            return $success;
        };

        // ensure totp_secret is empty.
        db_execute('UPDATE mailbox SET totp_secret = :secret WHERE username = :username', ['username' => 'test@example.com', 'secret' => null]);

        $this->assertTrue($checkIt("test@example.com", "foobar", "irrelevant", "mailbox_auth"), "should auth on mailbox ");
        $this->assertTrue($checkIt("test@example.com", "foobar-app", "irrelevant", "app_password_auth"), "should auth on mailbox_app_password");
        $this->assertFalse($checkIt("test@example.com", "foobar-app-invalid", "irrelevant", "app_password_auth"), "should auth on mailbox_app_password");
        $this->assertFalse($checkIt("test3@example.com", "foobar-app-invalid", "irrelevant", "app_password_auth"), "test3@example.com is an invalid user");

        // let's turn 2fa support on for mailbox.
        db_execute('UPDATE mailbox SET totp_secret = :secret WHERE username = :username', ['username' => 'test@example.com', 'secret' => 'something-not-empty']);
        $this->assertTrue($checkIt("test@example.com", "foobar-app", "irrelevant", "app_password_auth"), "should work as 4.3.2.1 has an app password.");
        $this->assertFalse($checkIt("test@example.com", "foobar", "irrelevant", "mailbox_auth"), "user has totp_secret, so should not work on standard auth");

        // now see if the exception addresses work with 2fa.
        $this->assertTrue($checkIt("test@example.com", "foobar", "4.3.2.1", "mailbox_auth"), "user has totp_secret, but client_ip is exceptional");
        $this->assertFalse($checkIt("test@example.com", "foobar", "4.4.4.4", "mailbox_auth"), "user has totp_secret, but client_ip is not exceptional");


    }
}
