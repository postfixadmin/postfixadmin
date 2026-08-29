<?php

use PHPUnit\Framework\TestCase;

class PasswordRecoveryResponseTest extends TestCase
{
    public function testRecoveryRequestDoesNotRevealDeliveryAvailability(): void
    {
        $controller = file_get_contents(__DIR__ . '/../public/users/password-recover.php');

        $this->assertStringNotContainsString('Location: password-change.php?username=', $controller);
        $this->assertStringContainsString("flash_info(Config::Lang('pPassword_recovery_processed'))", $controller);
    }
}
