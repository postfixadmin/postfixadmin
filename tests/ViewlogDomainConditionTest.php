<?php

/**
 * Unit tests for viewlog_domain_condition() - the domain restriction used by
 * viewlog.php's "All domains" option.
 * @see https://github.com/postfixadmin/postfixadmin/issues/1059
 */
class ViewlogDomainConditionTest extends \PHPUnit\Framework\TestCase
{
    public function testSingleDomainProducesEqualityCondition()
    {
        $params = [];
        $cond = viewlog_domain_condition(false, false, 'example.com', ['example.com'], $params);
        $this->assertSame('domain = :domain', $cond);
        $this->assertSame(['domain' => 'example.com'], $params);
    }

    public function testNoDomainAndNotAllProducesNoCondition()
    {
        $params = [];
        $cond = viewlog_domain_condition(false, false, '', ['example.com'], $params);
        $this->assertSame('', $cond);
        $this->assertSame([], $params);
    }

    public function testGlobalAdminAllHasNoRestriction()
    {
        $params = [];
        $cond = viewlog_domain_condition(true, true, '', ['a.com', 'b.com'], $params);
        // A global admin sees every domain, so there is no WHERE restriction.
        $this->assertSame('', $cond);
        $this->assertSame([], $params);
    }

    /**
     * The security-critical case: a non-global admin selecting "All" must be
     * restricted to exactly the domains they manage.
     */
    public function testDomainAdminAllIsScopedToOwnedDomains()
    {
        $params = [];
        $allowed = ['one.example.com', 'two.example.com'];
        $cond = viewlog_domain_condition(true, false, '', $allowed, $params);

        // Must be an IN clause on the domain column, not an unrestricted query.
        $this->assertNotSame('', $cond);
        $this->assertMatchesRegularExpression('/domain\s+IN\s*\(/i', $cond);

        // Exactly the allowed domains are bound - nothing more, nothing less.
        $this->assertEqualsCanonicalizing($allowed, array_values($params));

        // Every placeholder in the clause has a matching bound parameter.
        preg_match_all('/:([a-zA-Z0-9_]+)/', $cond, $m);
        foreach ($m[1] as $placeholder) {
            $this->assertArrayHasKey($placeholder, $params);
        }
    }

    /**
     * Defence in depth: a non-global admin with no domains must NOT fall back
     * to an unrestricted "all domains" query.
     */
    public function testDomainAdminAllWithNoDomainsMatchesNothing()
    {
        $params = [];
        $cond = viewlog_domain_condition(true, false, '', [], $params);
        // db_in_clause() returns a false predicate for an empty set.
        $this->assertStringContainsString('1=0', $cond);
        $this->assertSame([], $params);
    }
}
