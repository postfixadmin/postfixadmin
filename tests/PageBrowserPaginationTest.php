<?php

class PageBrowserPaginationTest extends \PHPUnit\Framework\TestCase
{
    public function testEmptyPageBrowserDoesNotRenderPagination(): void
    {
        $this->assertSame([], page_browser_pagination([], 0, 10, [], 'main_div'));
    }

    public function testFirstPagePreservesGenericListState(): void
    {
        $pagination = page_browser_pagination(
            ['aa-cz', 'de-pf', 'pg-zz'],
            0,
            10,
            [
                'table' => 'alias',
                'username' => 'admin@example.com',
                'search' => ['_' => 'sales & support'],
                'searchmode' => ['_' => '='],
            ],
            'main_div',
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
            '?table=alias&amp;username=admin%40example.com&amp;search%5B_%5D=sales%20%26%20support&amp;searchmode%5B_%5D=%3D&amp;limit=10#main_div',
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
            ['table' => 'domain'],
            'main_div'
        );

        $this->assertFalse($pagination[0]['disabled']);
        $this->assertSame('?table=domain&amp;limit=0#main_div', $pagination[0]['url']);
        $this->assertSame('?table=domain&amp;limit=0#main_div', $pagination[1]['url']);
        $this->assertTrue($pagination[3]['active']);
        $this->assertSame('?table=domain&amp;limit=20#main_div', $pagination[5]['url']);
        $this->assertFalse($pagination[5]['disabled']);
    }

    public function testRejectsInvalidPageSize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        page_browser_pagination(['aa-zz'], 0, 0, [], 'main_div');
    }
}
