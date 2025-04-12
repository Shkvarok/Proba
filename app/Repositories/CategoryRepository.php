<?php

namespace App\Repositories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

class CategoryRepository
{
    /**
     * Get all categories.
     */
    public function getAll(): Collection
    {
        return Category::orderBy('position')->get();
    }

    /**
     * Get only active categories.
     */
    public function getActive(): Collection
    {
        return Category::where('is_active', 1)
            ->orderBy('position')
            ->get();
    }

    /**
     * Get categories with their parent relation.
     */
    public function getAllWithParent(): Collection
    {
        return Category::with('parent')
            ->orderBy('position')
            ->get();
    }

    /**
     * Get categories with their children relation.
     */
    public function getAllWithChildren(): Collection
    {
        return Category::with('children')
            ->orderBy('position')
            ->get();
    }

    /**
     * Get only root categories (without parent).
     */
    public function getRootCategories(): Collection
    {
        return Category::whereNull('parent_id')
            ->orderBy('position')
            ->get();
    }

    /**
     * Get category by slug.
     *
     * @param string $slug
     * @return Category|null
     */
    public function findBySlug(string $slug): ?Category
    {
        return Category::where('slug', $slug)->first();
    }

    /**
     * Create a new category.
     *
     * @param array $data
     * @return Category
     */
    public function create(array $data): Category
    {
        return Category::create($data);
    }

    /**
     * Update a category.
     *
     * @param Category $category
     * @param array $data
     * @return Category
     */
    public function update(Category $category, array $data): Category
    {
        $category->update($data);
        return $category;
    }

    /**
     * Delete a category.
     *
     * @param Category $category
     * @return bool|null
     */
    public function delete(Category $category): ?bool
    {
        return $category->delete();
    }
}