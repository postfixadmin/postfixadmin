<?php

class RecipientDelimiterTest extends \PHPUnit\Framework\TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('addressProvider')]
    public function testBaseAddress(string $address, string $delimiters, string $expected): void
    {
        self::assertSame($expected, recipient_delimiter_base_address($address, $delimiters));
    }

    public static function addressProvider(): array
    {
        return [
            'disabled' => ['user+tag@example.com', '', 'user+tag@example.com'],
            'single delimiter' => ['user+tag@example.com', '+', 'user@example.com'],
            'multiple delimiters plus' => ['user+tag@example.com', '+.', 'user@example.com'],
            'multiple delimiters dot' => ['user.tag@example.com', '+.', 'user@example.com'],
            'first delimiter wins' => ['user+tag.more@example.com', '+.', 'user@example.com'],
            'regex-special delimiter' => ['user]tag@example.com', ']-', 'user@example.com'],
            'delimiter only in domain' => ['user@example.test', '.', 'user@example.test'],
            'address without domain' => ['user+tag', '+', 'user+tag'],
            'address without extension' => ['user@example.com', '+.', 'user@example.com'],
        ];
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
