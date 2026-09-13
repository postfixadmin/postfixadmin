<?php

class AliasDestinationStatusTest extends \PHPUnit\Framework\TestCase
{
    private array $savedConfig;

    protected function setUp(): void
    {
        global $CONF;
        $this->savedConfig = $CONF;
        $CONF['show_undeliverable'] = 'YES';
        $CONF['show_undeliverable_color'] = 'tomato';
        $CONF['show_external_color'] = 'lightblue';
        $CONF['show_undeliverable_exceptions'] = ['exception.test'];
        $CONF['vacation_domain'] = 'vacation.test';
        $CONF['recipient_delimiter'] = '+';
        $CONF['show_popimap'] = 'NO';
        $CONF['show_vacation'] = 'NO';
        $CONF['show_disabled'] = 'NO';
        $CONF['password_expiration'] = 'NO';
        $CONF['show_custom_domains'] = [];
    }

    protected function tearDown(): void
    {
        global $CONF;
        $CONF = $this->savedConfig;
        db_execute('DELETE FROM ' . table_by_key('alias'));
        db_execute('DELETE FROM ' . table_by_key('alias_domain'));
    }

    private function alias(string $address, string $goto): void
    {
        db_execute('INSERT INTO ' . table_by_key('alias') . ' (address, goto, domain) VALUES (?, ?, ?)', [$address, $goto, explode('@', $address)[1]]);
    }

    private function renderStatus(string $goto, array $domains = ['local.test', 'alias.test']): string
    {
        db_execute('DELETE FROM ' . table_by_key('alias') . ' WHERE address = ?', ['source@local.test']);
        $this->alias('source@local.test', $goto);
        return gen_show_status('source@local.test', $domains);
    }

    public function testMissingLocalAndExternalDestinationsAreIndependent(): void
    {
        $local = $this->renderStatus('missing@local.test');
        self::assertStringContainsString('background-color:tomato', $local);
        self::assertStringNotContainsString('background-color:lightblue', $local);
        $mixed = $this->renderStatus('missing@local.test, person@outside.test');
        self::assertStringContainsString('background-color:tomato', $mixed);
        self::assertStringContainsString('background-color:lightblue', $mixed);
    }

    public function testOtherAdministratorsRecordsDoNotAffectOutput(): void
    {
        $before = $this->renderStatus('person@private.test');
        $this->alias('person@private.test', 'person@private.test');
        self::assertSame($before, $this->renderStatus('person@private.test'));
        self::assertStringContainsString('background-color:lightblue', $before);
        self::assertStringNotContainsString('background-color:tomato', $before);
        self::assertStringContainsString('background-color:lightblue', $this->renderStatus('missing@local.test', []));
    }

    public function testKnownRecipientsCatchallsAndExceptions(): void
    {
        $this->alias('person@local.test', 'person@local.test');
        self::assertStringNotContainsString('background-color:', $this->renderStatus('person+tag@local.test'));
        $this->alias('@local.test', 'person@local.test');
        self::assertStringNotContainsString('background-color:', $this->renderStatus('anything@local.test'));
        self::assertStringNotContainsString('background-color:', $this->renderStatus('person@exception.test,person@vacation.test'));
    }

    public function testAliasDomainsRespectTargetPermissions(): void
    {
        db_execute('INSERT INTO ' . table_by_key('alias_domain') . ' (alias_domain, target_domain, active) VALUES (?, ?, ?)', ['alias.test', 'local.test', true]);
        $this->alias('person@local.test', 'person@local.test');
        self::assertStringNotContainsString('background-color:', $this->renderStatus('person@alias.test'));
        self::assertStringContainsString('background-color:tomato', $this->renderStatus('missing@alias.test'));
        $hidden = $this->renderStatus('person@alias.test', ['alias.test']);
        db_execute('DELETE FROM ' . table_by_key('alias') . ' WHERE address = ?', ['person@local.test']);
        self::assertSame($hidden, $this->renderStatus('person@alias.test', ['alias.test']));
        self::assertStringContainsString('background-color:lightblue', $hidden);
    }
}
