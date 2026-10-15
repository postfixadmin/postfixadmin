<?php

class UnlimitedQuotaTemplateTest extends \PHPUnit\Framework\TestCase
{
    private function renderQuota(string $file, string $start, string $end, array $variables): string
    {
        if (!defined('YES')) {
            define('YES', 'YES');
        }
        $source = file_get_contents(__DIR__ . '/../templates/' . $file);
        $offset = strpos($source, $start);
        self::assertNotFalse($offset);
        $length = strpos($source, $end, $offset) - $offset;
        $smarty = new \Smarty\Smarty();
        $smarty->setCompileDir(sys_get_temp_dir());
        $smarty->assign($variables);
        return $smarty->fetch('string:' . substr($source, $offset, $length));
    }

    public function testUnlimitedMailboxRetainsAvailableAndUnknownUsage(): void
    {
        foreach (['0', '256', 'N/D'] as $usage) {
            $html = $this->mailbox(0, true, $usage);
            self::assertStringContainsString($usage . ' / &infin;', $html);
            self::assertStringContainsString('class="quota_bar"', $html);
            self::assertStringNotContainsString('quota_fill', $html);
        }
        $html = $this->mailbox(0, false, 'N/D');
        self::assertStringContainsString('&infin;', $html);
        self::assertStringNotContainsString('N/D', $html);
    }

    public function testLimitedAndDisabledMailboxBehaviorIsPreserved(): void
    {
        self::assertStringContainsString('quota_fill', $this->mailbox(1024, true, '256'));
        self::assertStringContainsString('256 / 1024', $this->mailbox(1024, true, '256'));
        self::assertStringContainsString('Disabled', $this->mailbox(-1, true, '256'));
        self::assertStringNotContainsString('quota_bar', $this->mailbox(-1, true, '256'));
    }

    private function mailbox(int $quota, bool $tracking, string $usage): string
    {
        return $this->renderQuota('list-virtual_mailbox.tpl', '{if $item.quota==0}', '</td>', [
            'item' => ['quota' => $quota], 'i' => 0,
            'boolconf_used_quotas' => $tracking,
            'divide_quota' => ['current' => [$usage], 'quota' => [1024], 'percent' => [25]],
            'CONF' => ['quota_level_high_pct' => 90, 'quota_level_med_pct' => 80],
            'PALANG' => ['pOverview_disabled' => 'Disabled'],
        ]);
    }

    public function testUnlimitedDomainCountersKeepNeutralBoxesAndActualUsage(): void
    {
        foreach (['aliases_quot', 'mailboxes_quot', 'total_quot'] as $key) {
            $html = $this->domain($key, 0, true);
            self::assertStringContainsString('class="quota_bar"', $html);
            self::assertStringNotContainsString('quota_fill', $html);
            self::assertStringContainsString($key == 'total_quot' ? '256 / &infin;' : '7 / infinity', $html);
        }
        $html = $this->domain('total_quot', 0, true);
        self::assertStringContainsString('Assigned: 7 / infinity', $html);
        self::assertStringContainsString('Used: 256 MB', $html);
        self::assertStringContainsString('7 / infinity', $this->domain('total_quot', 0, false));
    }

    public function testFiniteAndDisabledDomainCountersKeepTheirBehavior(): void
    {
        foreach (['aliases_quot', 'mailboxes_quot', 'total_quot'] as $key) {
            self::assertStringContainsString('quota_fill', $this->domain($key, 1024, true));
            self::assertStringContainsString('quota_no_border', $this->domain($key, -1, true));
        }
    }

    private function domain(string $key, int $limit, bool $tracking): string
    {
        return $this->renderQuota('list.tpl', '{assign "tmpkey" "_{$key}_percent"}', "{elseif \$field.type == 'txtl'}", [
            'key' => $key, 'table' => 'domain', 'linktext' => '7 / infinity',
            'item' => ['aliases' => $limit, 'mailboxes' => $limit, 'quota' => $limit,
                '_' . $key . '_percent' => $limit > 0 ? 25 : -1, 'total_quota_used' => 256],
            'CONF' => ['used_quotas' => $tracking ? 'YES' : 'NO', 'quota_level_high_pct' => 90, 'quota_level_med_pct' => 80],
            'PALANG' => ['quota_assigned' => 'Assigned', 'quota_used' => 'Used'],
        ]);
    }
}
