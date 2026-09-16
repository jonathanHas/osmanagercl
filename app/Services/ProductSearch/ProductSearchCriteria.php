<?php

namespace App\Services\ProductSearch;

/**
 * Inputs for one product search. Immutable; built by the JSON endpoint and
 * by ProductController@index (server-rendered first page).
 */
final readonly class ProductSearchCriteria
{
    public const MAX_PER_PAGE = 50;

    public string $q;

    public int $page;

    public int $perPage;

    /**
     * @param  string[]  $excludeIds
     */
    public function __construct(
        ?string $q = null,
        public bool $stocked = true,
        public ?string $supplierId = null,
        public ?string $categoryId = null,
        public array $excludeIds = [],
        int $page = 1,
        int $perPage = 20,
    ) {
        $this->q = trim((string) $q);
        $this->page = max(1, $page);
        $this->perPage = max(1, min(self::MAX_PER_PAGE, $perPage));
    }
}
