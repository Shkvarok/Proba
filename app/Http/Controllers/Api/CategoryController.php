<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use App\Services\CategoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    protected $categoryService;

    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }

    /**
     * Display a listing of the categories.
     */
    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            $this->categoryService->getAllCategories()
        );
    }

    /**
     * Store a newly created category in storage.
     */
    public function store(CategoryRequest $request): CategoryResource
    {
        $category = $this->categoryService->createCategory($request->validated());
        
        return new CategoryResource($category);
    }

    /**
     * Display the specified category.
     */
    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category);
    }

    /**
     * Update the specified category in storage.
     */
    public function update(CategoryRequest $request, Category $category): CategoryResource
    {
        $category = $this->categoryService->updateCategory($category, $request->validated());
        
        return new CategoryResource($category);
    }

    /**
     * Remove the specified category from storage.
     */
    public function destroy(Category $category): JsonResponse
    {
        $this->categoryService->deleteCategory($category);

        return response()->json([
            'message' => 'Категорію успішно видалено'
        ]);
    }
    public function getActive()
    {
        return CategoryResource::collection(
            $this->categoryService->getActiveCategories()
        );
    }
    /**
     * Get categories hierarchy.
     */
    public function getHierarchy()
    {
        return CategoryResource::collection(
            $this->categoryService->getCategoriesHierarchy()
        );
    }

    /**
     * Get category by slug.
     * 
     * @param string $slug
     * @return CategoryResource|JsonResponse
     */
    public function getBySlug(string $slug)
    {
        $category = $this->categoryService->getCategoryBySlug($slug);
    
        if (!$category) {
            return response()->json([
                'message' => 'Категорію не знайдено'
            ], 404);
        }
    
        return new CategoryResource($category);
    }

    /**
     * Update category position.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function updatePositions(Request $request)
    {
        $request->validate([
            'positions' => 'required|array',
            'positions.*.id' => 'required|integer|exists:categories,id',
            'positions.*.position' => 'required|integer|min:0',
        ]);
    
        $this->categoryService->updateCategoryPositions($request->input('positions'));
    
        return response()->json([
            'message' => 'Позиції категорій успішно оновлено'
        ]);
    }
    
    public function toggleActive(int $id)
    {
        $category = $this->categoryService->toggleCategoryActive($id);
    
        return response()->json([
            'message' => 'Статус категорії успішно змінено',
            'is_active' => $category->is_active
        ]);
    }
    
    public function activate(int $id): JsonResponse
    {
        $category = $this->categoryService->activateCategory($id);

        return response()->json([
            'message' => 'Категорію успішно активовано',
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
                'is_active' => $category->is_active
            ]
        ]);
    }

    /**
     * Деактивувати категорію.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function deactivate(int $id): JsonResponse
    {
        $category = $this->categoryService->deactivateCategory($id);

        return response()->json([
            'message' => 'Категорію успішно деактивовано',
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
                'is_active' => $category->is_active
            ]
        ]);
    }

}