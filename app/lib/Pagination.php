<?php
declare(strict_types=1);

/**
 *
 */
class Pagination
{
    private const string NUM_PLACEHOLDER = '(:num)';
    public string $limit = '';
    public int|float $limitStart = 0;
    public int|float $limitEnd = 0;
    protected int $totalItems;
    protected int $numPages;
    protected int $itemsPerPage = 20;
    protected int $currentPage = 1;
    protected string $urlPattern;
    protected int $maxPagesToShow = 10;
    protected string $previousText = 'Previous';
    protected string $nextText = 'Next';

    /**
     * @param int $totalItems The total number of items.
     * @param int $currentPage The current page number
     * @param string $urlPattern A URL for each page, with (:num) as a placeholder for the page number. Ex. '/foo/page/(:num)'
     */
    public function __construct(int $totalItems, int $currentPage = 1, string $urlPattern = '')
    {
        $this->totalItems   = $totalItems;
        $this->currentPage  = $currentPage;
        $this->urlPattern   = $urlPattern;

        $this->updateNumPages();
    }

    /**
     * @return void
     */
    protected function updateNumPages(): void
    {
        $this->numPages   = ($this->itemsPerPage == 0 ? 0 : (int)ceil($this->totalItems / $this->itemsPerPage));
        $this->limitStart = ($this->currentPage - 1) * $this->itemsPerPage;
        if ($this->limitStart < 0) {
            $this->limitStart = 0;
        }
        $this->limitEnd   = $this->itemsPerPage;
        $this->limit      = ' LIMIT ' . $this->limitStart . ', ' . $this->limitEnd;
    }

    /**
     * @return int
     */
    public function getMaxPagesToShow(): int
    {
        return $this->maxPagesToShow;
    }

    /**
     * @param int $maxPagesToShow
     * @throws InvalidArgumentException if $maxPagesToShow is less than 3.
     */
    public function setMaxPagesToShow(int $maxPagesToShow): void
    {
        if ($maxPagesToShow < 3) {
            throw new InvalidArgumentException('maxPagesToShow cannot be less than 3.');
        }
        $this->maxPagesToShow = $maxPagesToShow;
    }

    /**
     * @return int
     */
    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * @param int $currentPage
     */
    public function setCurrentPage(int $currentPage): void
    {
        $this->currentPage = $currentPage;
    }

    /**
     * @return int
     */
    public function getItemsPerPage(): int
    {
        return $this->itemsPerPage;
    }

    /**
     * @param int $itemsPerPage
     */
    public function setItemsPerPage(int $itemsPerPage): void
    {
        $this->itemsPerPage = $itemsPerPage;
        $this->updateNumPages();
    }

    /**
     * @return int
     */
    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    /**
     * @param int $totalItems
     */
    public function setTotalItems(int $totalItems): void
    {
        $this->totalItems = $totalItems;
        $this->updateNumPages();
    }

    /**
     * @return int
     */
    public function getNumPages(): int
    {
        return $this->numPages;
    }

    /**
     * @return string
     */
    public function getUrlPattern(): string
    {
        return $this->urlPattern;
    }

    /**
     * @param string $urlPattern
     */
    public function setUrlPattern(string $urlPattern): void
    {
        $this->urlPattern = $urlPattern;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toHtml();
    }

    /**
     * Render an HTML pagination control.
     *
     * @return string
     */
    public function toHtml(): string
    {
        if ($this->numPages <= 1) {
            return '';
        }

        $html = '<nav id="pages" aria-label="Page navigation">
			<ul class="pagination">';
        if ($this->getPrevUrl()) {
            $html .= '<li class="page-item page-prev"><a aria-label="Previous" class="page-link" href="' . htmlspecialchars($this->getPrevUrl()) . '"><span aria-hidden="true">&laquo; ' . $this->previousText . '</span></a></li>';
        }

        foreach ($this->getPages() as $page) {
            if ($page['url']) {
                $html .= '<li class="page-item ' . ($page['isCurrent'] ? 'active' : '') . '"><a aria-label="Page ' . $page['num'] . '" class="page-link" href="' . htmlspecialchars($page['url']) . '">' . htmlspecialchars((string)$page['num']) . '</a></li>';
            } else {
                $html .= '<li class="page-item disabled"><a aria-label="Current Page" class="page-link" href="#">' . htmlspecialchars((string)$page['num']) . '</a></li>';
            }
        }

        if ($this->getNextUrl()) {
            $html .= '<li class="page-item"><a aria-label="Next page" class="page-link" href="' . htmlspecialchars($this->getNextUrl()) . '">' . $this->nextText . ' &raquo;</a></li>';
        }
        $html .= '</ul>
			</nav>';

        return $html;
    }

    /**
     * @return string|null
     */
    public function getPrevUrl(): ?string
    {
        if (!$this->getPrevPage()) {
            return null;
        }

        return $this->getPageUrl($this->getPrevPage());
    }

    /**
     * @return int|mixed|null
     */
    public function getPrevPage()
    {
        if ($this->currentPage > 1) {
            return $this->currentPage - 1;
        }

        return null;
    }

    /**
     * @param int $pageNum
     * @return string
     */
    public function getPageUrl(int $pageNum): string
    {
        return str_replace(self::NUM_PLACEHOLDER, (string)$pageNum, $this->urlPattern);
    }

    /**
     * Get an array of paginated page data.
     *
     * Example:
     * array(
     *     array ('num' => 1,     'url' => '/example/page/1',  'isCurrent' => false),
     *     array ('num' => '...', 'url' => NULL,               'isCurrent' => false),
     *     array ('num' => 3,     'url' => '/example/page/3',  'isCurrent' => false),
     *     array ('num' => 4,     'url' => '/example/page/4',  'isCurrent' => true ),
     *     array ('num' => 5,     'url' => '/example/page/5',  'isCurrent' => false),
     *     array ('num' => '...', 'url' => NULL,               'isCurrent' => false),
     *     array ('num' => 10,    'url' => '/example/page/10', 'isCurrent' => false),
     * )
     *
     * @return array
     */
    public function getPages(): array
    {
        $pages = [];

        if ($this->numPages <= 1) {
            return [];
        }

        if ($this->numPages <= $this->maxPagesToShow) {
            for ($i = 1; $i <= $this->numPages; $i++) {
                $pages[] = $this->createPage($i, $i == $this->currentPage);
            }
        } else {

            // Determine the sliding range, centered around the current page.
            $num_adjacent = (int)floor(($this->maxPagesToShow - 3) / 2);

            if ($this->currentPage + $num_adjacent > $this->numPages) {
                $sliding_start = $this->numPages - $this->maxPagesToShow + 2;
            } else {
                $sliding_start = $this->currentPage - $num_adjacent;
            }
            if ($sliding_start < 2) {
                $sliding_start = 2;
            }

            $sliding_end = $sliding_start + $this->maxPagesToShow - 3;
            if ($sliding_end >= $this->numPages) {
                $sliding_end = $this->numPages - 1;
            }

            // Build the list of pages.
            $pages[] = $this->createPage(1, $this->currentPage == 1);
            if ($sliding_start > 2) {
                $pages[] = $this->createPageEllipsis();
            }
            for ($i = $sliding_start; $i <= $sliding_end; $i++) {
                $pages[] = $this->createPage($i, $i == $this->currentPage);
            }
            if ($sliding_end < $this->numPages - 1) {
                $pages[] = $this->createPageEllipsis();
            }
            $pages[] = $this->createPage($this->numPages, $this->currentPage == $this->numPages);
        }


        return $pages;
    }


    /**
     * Create a page data structure.
     *
     * @param int $pageNum
     * @param bool $isCurrent
     * @return Array
     */
    protected function createPage(int $pageNum, bool $isCurrent = false): array
    {
        return [
            'num' => $pageNum,
            'url' => $this->getPageUrl($pageNum),
            'isCurrent' => $isCurrent,
        ];
    }

    /**
     * @return array
     */
    protected function createPageEllipsis(): array
    {
        return [
            'num' => '...',
            'url' => null,
            'isCurrent' => false,
        ];
    }

    /**
     * @return string|null
     */
    public function getNextUrl(): ?string
    {
        if (!$this->getNextPage()) {
            return null;
        }

        return $this->getPageUrl($this->getNextPage());
    }

    /**
     * @return int|mixed|null
     */
    public function getNextPage()
    {
        if ($this->currentPage < $this->numPages) {
            return $this->currentPage + 1;
        }

        return null;
    }

    /**
     * @return float|int|null
     */
    public function getCurrentPageLastItem(): float|int|null
    {
        $first = $this->getCurrentPageFirstItem();
        if ($first === null) {
            return null;
        }

        $last = $first + $this->itemsPerPage - 1;
        if ($last > $this->totalItems) {
            return $this->totalItems;
        }

        return $last;
    }

    /**
     * @return float|int|null
     */
    public function getCurrentPageFirstItem(): float|int|null
    {
        $first = ($this->currentPage - 1) * $this->itemsPerPage + 1;

        if ($first > $this->totalItems) {
            return null;
        }

        return $first;
    }

    /**
     * @param $text
     * @return $this
     */
    public function setPreviousText($text): static
    {
        $this->previousText = $text;
        return $this;
    }

    /**
     * @param $text
     * @return $this
     */
    public function setNextText($text): static
    {
        $this->nextText = $text;
        return $this;
    }
}
