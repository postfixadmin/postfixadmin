<?php

class SmtpClientIpListTest extends \PHPUnit\Framework\TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('entryProvider')]
    public function testNormalize(string $entry, ?string $expected, ?string $expectedError): void
    {
        $error = null;
        self::assertSame($expected, SmtpClientIpList::normalize($entry, $error));
        self::assertSame($expectedError, $error);
    }

    public static function entryProvider(): array
    {
        $invalid = SmtpClientIpList::ERROR_INVALID;
        $hostBits = SmtpClientIpList::ERROR_HOST_BITS;
        return [
            'ipv4' => ['10.0.2.10', '10.0.2.10', null],
            'ipv4 network' => ['10.0.2.0/24', '10.0.2.0/24', null],
            'ipv4 /32 is a host' => ['10.0.2.10/32', '10.0.2.10', null],
            'ipv4 host bits set' => ['10.0.2.10/24', null, $hostBits],
            'ipv4 /31' => ['10.0.2.10/31', '10.0.2.10/31', null],
            'ipv6 canonical' => ['2001:DB8:0:0::10', '2001:db8::10', null],
            'ipv6 network' => ['2001:db8::/64', '2001:db8::/64', null],
            'ipv6 /128 is a host' => ['2001:db8::1/128', '2001:db8::1', null],
            'ipv6 host bits set' => ['2001:db8::1/64', null, $hostBits],
            'ipv4-mapped host' => ['::ffff:10.0.2.10', '10.0.2.10', null],
            'ipv4-mapped network' => ['::ffff:10.0.2.0/120', '10.0.2.0/24', null],
            'ipv4-mapped prefix too short' => ['::ffff:10.0.2.0/90', null, $invalid],
            'hostname' => ['mail.example.com', null, $invalid],
            'ipv4 out of range' => ['10.0.2.256', null, $invalid],
            'ipv4 leading zero' => ['010.0.2.1', null, $invalid],
            'ipv4 short form' => ['10.1', null, $invalid],
            'ipv4 prefix too long' => ['10.0.2.0/33', null, $invalid],
            'ipv6 prefix too long' => ['2001:db8::/129', null, $invalid],
            'prefix zero' => ['0.0.0.0/0', null, $invalid],
            'ipv6 prefix zero' => ['::/0', null, $invalid],
            'prefix not numeric' => ['10.0.2.0/abc', null, $invalid],
            'empty prefix' => ['10.0.2.0/', null, $invalid],
        ];
    }

    public function testFormatNormalizesAndDeduplicatesEquivalentEntries(): void
    {
        self::assertSame(
            '10.0.2.10, 10.0.2.32/28, 2001:db8::10',
            SmtpClientIpList::format(" 10.0.2.10;10.0.2.32/28\n2001:DB8::10,::ffff:10.0.2.10 10.0.2.10/32 ")
        );
        self::assertSame('', SmtpClientIpList::format(''));
    }

    public function testInvalidEntriesReportsReason(): void
    {
        self::assertSame(
            ['web.example.com' => SmtpClientIpList::ERROR_INVALID, '10.0.2.10/24' => SmtpClientIpList::ERROR_HOST_BITS],
            SmtpClientIpList::invalidEntries('10.0.2.10 web.example.com 10.0.2.10/24')
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('containsProvider')]
    public function testContains(string $network, string $address, bool $expected): void
    {
        $parsed = SmtpClientIpList::parse($network);
        self::assertNotNull($parsed);
        self::assertSame($expected, SmtpClientIpList::contains($parsed, $address));
    }

    public static function containsProvider(): array
    {
        return [
            'host match' => ['10.0.2.10', '10.0.2.10', true],
            'host no match' => ['10.0.2.10', '10.0.2.11', false],
            'network first' => ['10.0.2.32/28', '10.0.2.32', true],
            'network last' => ['10.0.2.32/28', '10.0.2.47', true],
            'network outside' => ['10.0.2.32/28', '10.0.2.48', false],
            'odd prefix' => ['10.0.0.0/13', '10.7.255.255', true],
            'odd prefix outside' => ['10.0.0.0/13', '10.8.0.0', false],
            'ipv6 network' => ['2001:db8::/64', '2001:db8::abcd', true],
            'ipv6 outside' => ['2001:db8::/64', '2001:db8:0:1::1', false],
            'ipv4 vs ipv6' => ['10.0.2.10', '2001:db8::10', false],
            'mapped client address' => ['10.0.2.0/24', '::ffff:10.0.2.10', true],
            'invalid client address' => ['10.0.2.0/24', 'unknown', false],
        ];
    }
}
