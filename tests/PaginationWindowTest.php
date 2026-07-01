<?php

/**
 * Unit tests for pagination_window() - the windowed page list used by the
 * viewlog.php pager.
 * @see https://github.com/postfixadmin/postfixadmin/issues/1062
 */
class PaginationWindowTest extends \PHPUnit\Framework\TestCase
{
    public function testEmptyAndSinglePage()
    {
        $this->assertSame([], pagination_window(1, 0, 5));
        $this->assertSame([1], pagination_window(1, 1, 5));
    }

    public function testAllPagesShownWhenWithinWindow()
    {
        // radius 5 around page 3 covers everything up to page 7 - no ellipsis.
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], pagination_window(3, 7, 5));
    }

    public function testEllipsisOnBothSides()
    {
        $expected = [1, null, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, null, 50];
        $this->assertSame($expected, pagination_window(20, 50, 5));
    }

    public function testTrailingEllipsisOnly()
    {
        // Near the start: window reaches page 8, then a gap to the last page.
        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8, null, 50], pagination_window(3, 50, 5));
    }

    public function testLeadingEllipsisOnly()
    {
        // Near the end: page 1, a gap, then the window through the last page.
        $this->assertSame([1, null, 43, 44, 45, 46, 47, 48, 49, 50], pagination_window(48, 50, 5));
    }

    public function testCurrentPageClampedIntoRange()
    {
        // An out-of-range current page is clamped to the last page.
        $this->assertSame([1, null, 8, 9, 10], pagination_window(999, 10, 2));
    }

    public function testNeverTwoAdjacentEllipses()
    {
        $window = pagination_window(25, 100, 5);
        $previous = 1;
        foreach ($window as $entry) {
            $this->assertFalse($entry === null && $previous === null, 'two ellipses in a row');
            $previous = $entry;
        }
        // First and last real entries are always page 1 and the last page.
        $this->assertSame(1, $window[0]);
        $this->assertSame(100, $window[count($window) - 1]);
    }
}
