<?php

class PageBrowserPaginationTest extends \PHPUnit\Framework\TestCase
{
    public function testEmptyPageBrowserDoesNotRenderPagination(): void
    {
        $this->assertSame([], page_browser_pagination([], 0, 10, [], 'aliases'));
    }

    public function testFirstPagePreservesQueryParameters(): void
    {
        $pagination = page_browser_pagination(
            ['aa-cz', 'de-pf', 'pg-zz'],
            0,
            10,
            [
                'domain' => 'example.com',
                'search' => ['_' => 'sales & support'],
            ],
            'aliases',
            [
                'first' => 'First page',
                'previous' => 'Previous page',
                'next' => 'Next page',
            ]
        );

        $this->assertTrue($pagination[0]['disabled']);
        $this->assertTrue($pagination[1]['disabled']);
        $this->assertTrue($pagination[2]['active']);
        $this->assertSame(
            '?domain=example.com&amp;search%5B_%5D=sales%20%26%20support&amp;limit=10#aliases',
            $pagination[3]['url']
        );
        $this->assertSame('Next page', $pagination[5]['aria']);
    }

    public function testMiddlePageBuildsFirstPreviousAndNextLinks(): void
    {
        $pagination = page_browser_pagination(
            ['aa-cz', 'de-pf', 'pg-zz'],
            10,
            10,
            ['domain' => 'example.com'],
            'mailboxes'
        );

        $this->assertFalse($pagination[0]['disabled']);
        $this->assertSame('?domain=example.com&amp;limit=0#mailboxes', $pagination[0]['url']);
        $this->assertSame('?domain=example.com&amp;limit=0#mailboxes', $pagination[1]['url']);
        $this->assertTrue($pagination[3]['active']);
        $this->assertSame('?domain=example.com&amp;limit=20#mailboxes', $pagination[5]['url']);
        $this->assertFalse($pagination[5]['disabled']);
    }

    public function testRejectsInvalidPageSize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        page_browser_pagination(['aa-zz'], 0, 0, [], 'aliases');
    }
}
