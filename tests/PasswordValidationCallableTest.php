<?php

class PasswordValidationCallableTEst extends \PHPUnit\Framework\TestCase {
    public function setUp(): void {
        $c = Config::getInstance();

        $all = $c->getAll();

        $all['password_validation'] = [
            function ($pw) {
                if ($pw === 'fail') {
                    return 'test_fail';
                }
            }
        ];
        unset($all['min_password_length']);

        $c->setAll($all);
        parent::setUp();
    }

    public function testBasic() {
        // anything except 'fail' should work.
        $this->assertEmpty(validate_password('str'));
        $this->assertEquals([], validate_password('1234asdf'));
        $this->assertEquals(['test_fail'], validate_password('fail'));
    }
}
