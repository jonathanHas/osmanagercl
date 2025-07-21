<?php

namespace App\Repositories;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class CategoryRepository
{
    /**
     * Get all categories with pagination.
     */
    public function getAllCategories(int $perPage = 20): LengthAwarePaginator
    {
        return Category::with(['parent', 'children'])
            ->orderBy('NAME')
            ->paginate($perPage);
    }

    /**
     * Find a category by ID.
     */
    public function findById(string $id): ?Category
    {
        return Category::with(['parent', 'children', 'products'])
            ->find($id);
    }

    /**
     * Get all root categories (categories without a parent).
     */
    public function getRootCategories(): Collection
    {
        return Category::root()
            ->visible()
            ->with(['children'])
            ->orderBy('NAME')
            ->get();
    }

    /**
     * Get categories by parent ID.
     */
    public function getByParent(string $parentId): Collection
    {
        return Category::where('PARENTID', $parentId)
            ->visible()
            ->orderBy('NAME')
            ->get();
    }

    /**
     * Search categories by name.
     */
    public function searchByName(string $name, int $perPage = 20): LengthAwarePaginator
    {
        return Category::search($name)
            ->visible()
            ->with(['parent'])
            ->orderBy('NAME')
            ->paginate($perPage);
    }

    /**
     * Get the full category tree structure.
     */
    public function getCategoryTree(): SupportCollection
    {
        $categories = Category::visible()
            ->with(['children' => function ($query) {
                $query->visible()->orderBy('NAME');
            }])
            ->orderBy('NAME')
            ->get();

        return $this->buildTree($categories);
    }

    /**
     * Get categories with their product counts.
     */
    public function getCategoriesWithProductCounts(): Collection
    {
        return Category::visible()
            ->withCount(['products' => function ($query) {
                $query->active(); // Only count active products
            }])
            ->orderBy('NAME')
            ->get();
    }

    /**
     * Get categories that have products.
     */
    public function getCategoriesWithProducts(): Collection
    {
        return Category::visible()
            ->has('products')
            ->with(['products' => function ($query) {
                $query->active()->limit(5); // Sample of products
            }])
            ->orderBy('NAME')
            ->get();
    }

    /**
     * Get statistics about categories.
     */
    public function getStatistics(): array
    {
        $totalCategories = Category::count();
        $visibleCategories = Category::visible()->count();
        $rootCategories = Category::root()->visible()->count();
        $categoriesWithProducts = Category::visible()->has('products')->count();

        return [
            'total_categories' => $totalCategories,
            'visible_categories' => $visibleCategories,
            'root_categories' => $rootCategories,
            'categories_with_products' => $categoriesWithProducts,
            'empty_categories' => $visibleCategories - $categoriesWithProducts,
        ];
    }

    /**
     * Build a hierarchical tree from flat category collection.
     */
    private function buildTree(Collection $categories, ?string $parentId = null): SupportCollection
    {
        $tree = collect();

        foreach ($categories as $category) {
            if ($category->PARENTID === $parentId) {
                $category->children_tree = $this->buildTree($categories, $category->ID);
                $tree->push($category);
            }
        }

        return $tree;
    }

    /**
     * Get all descendant category IDs for a given category.
     */
    public function getDescendantIds(string $categoryId): array
    {
        $category = $this->findById($categoryId);
        
        if (!$category) {
            return [];
        }

        $descendants = $category->descendants();
        $ids = [$categoryId]; // Include the category itself

        foreach ($descendants as $descendant) {
            $ids[] = $descendant->ID;
        }

        return $ids;
    }

    /**
     * Get breadcrumb trail for a category.
     */
    public function getBreadcrumbs(string $categoryId): array
    {
        $category = $this->findById($categoryId);
        
        if (!$category) {
            return [];
        }

        $breadcrumbs = [];
        $current = $category;

        while ($current) {
            array_unshift($breadcrumbs, [
                'id' => $current->ID,
                'name' => $current->NAME,
                'url' => route('products.index', ['category' => $current->ID])
            ]);
            $current = $current->parent;
        }

        return $breadcrumbs;
    }
}