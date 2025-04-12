<?php

namespace App\Services;

use App\Models\Category;
use App\Repositories\CategoryRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class CategoryService
{
    /**
     * @var CategoryRepository
     */
    protected $categoryRepository;

    /**
     * CategoryService constructor.
     *
     * @param CategoryRepository $categoryRepository
     */
    public function __construct(CategoryRepository $categoryRepository)
    {
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * Get all categories.
     */
    public function getAllCategories(): Collection
    {
        return $this->categoryRepository->getAll();
    }

    /**
     * Get only active categories.
     */
    public function getActiveCategories(): Collection
    {
        return $this->categoryRepository->getActive();
    }

    /**
     * Get categories with their hierarchical structure.
     */
    public function getCategoriesHierarchy(): Collection
    {
        return $this->categoryRepository->getRootCategories()->load('children');
    }

    /**
     * Create a new category.
     *
     * @param array $data
     * @return Category
     */
    public function createCategory(array $data): Category
    {
        // Генеруємо slug, якщо його не надано
        if (!isset($data['slug']) || empty($data['slug'])) {
            $data['slug'] = $this->generateUniqueSlug($data['name']);
        }

        return $this->categoryRepository->create($data);
    }

    /**
     * Update a category.
     *
     * @param Category $category
     * @param array $data
     * @return Category
     */
    public function updateCategory(Category $category, array $data): Category
    {
        // Якщо змінилося ім'я і slug не надано явно, оновлюємо slug
        if (isset($data['name']) && $data['name'] !== $category->name && 
            (!isset($data['slug']) || empty($data['slug']))) {
            $data['slug'] = $this->generateUniqueSlug($data['name'], $category->id);
        }

        return $this->categoryRepository->update($category, $data);
    }

    /**
     * Delete a category.
     *
     * @param Category $category
     * @return bool|null
     */
    public function deleteCategory(Category $category): ?bool
    {
        return $this->categoryRepository->delete($category);
    }

    /**
     * Generate a unique slug.
     *
     * @param string $name
     * @param int|null $exceptId
     * @return string
     */
    protected function generateUniqueSlug(string $name, ?int $exceptId = null): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        // Перевіряємо, чи існує вже такий slug
        while ($this->slugExists($slug, $exceptId)) {
            $slug = $originalSlug . '-' . $counter++;
        }

        return $slug;
    }

    /**
     * Check if slug exists.
     *
     * @param string $slug
     * @param int|null $exceptId
     * @return bool
     */
    protected function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $query = Category::where('slug', $slug);
        
        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }
        
        return $query->exists();
    }


    public function getCategoryBySlug(string $slug): ?Category
    {
        return $this->categoryRepository->findBySlug($slug);
    }

    /**
     * Update category positions.
     *
     * @param array $positions
     * @return void
     */
    public function updateCategoryPositions(array $positions): void
    {
        foreach ($positions as $positionData) {
            $category = Category::find($positionData['id']);
            if ($category) {
                $category->position = $positionData['position'];
                $category->save();
            }
        }
    }

    /**
     * Toggle category active status.
     *
     * @param int $id
     * @return Category
     */
    public function toggleCategoryActive(int $id): Category
    {
        $category = Category::findOrFail($id);
        $category->is_active = !$category->is_active;
        $category->save();

        return $category;
    }

    public function activateCategory(int $id): Category
    {
        $category = Category::findOrFail($id);
        $category->is_active = true;
        $category->save();

        return $category;
    }

    /**
     * Деактивувати категорію.
     *
     * @param int $id
     * @return Category
     */
    public function deactivateCategory(int $id): Category
    {
        $category = Category::findOrFail($id);
        $category->is_active = false;
        $category->save();

        return $category;
    }
}