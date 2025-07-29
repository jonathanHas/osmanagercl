<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\Component;

class DataTable extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public array $headers = [],
        public $rows = null,
        public $pagination = null,
        public bool $sortable = false,
        public bool $selectable = false,
        public string $emptyMessage = 'No records found.',
        public string $emptyIcon = 'table',
        public bool $striped = true,
        public bool $hoverable = true,
        public string $size = 'default' // default, sm, lg
    ) {
        // If headers is an associative array, convert it to the expected format
        $this->headers = $this->normalizeHeaders($headers);
    }

    /**
     * Normalize headers to ensure consistent format
     */
    private function normalizeHeaders(array $headers): array
    {
        return array_map(function ($header) {
            if (is_string($header)) {
                return [
                    'key' => str_replace(' ', '_', strtolower($header)),
                    'label' => $header,
                    'sortable' => false,
                    'class' => '',
                ];
            }

            return array_merge([
                'key' => '',
                'label' => '',
                'sortable' => false,
                'class' => '',
                'width' => null,
            ], $header);
        }, $headers);
    }

    /**
     * Check if the rows collection is paginated
     */
    public function isPaginated(): bool
    {
        return $this->pagination instanceof LengthAwarePaginator;
    }

    /**
     * Check if we have any data to display
     */
    public function hasData(): bool
    {
        if ($this->rows === null) {
            return false;
        }

        if (is_countable($this->rows)) {
            return count($this->rows) > 0;
        }

        return ! empty($this->rows);
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.data-table');
    }
}
