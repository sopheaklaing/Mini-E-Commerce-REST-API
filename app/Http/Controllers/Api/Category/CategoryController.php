<?php

namespace App\Http\Controllers\Api\Category;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Services\Category\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function __construct(
        private CategoryService $categoryService
    ) {}

    /**
     * Display a listing of categories.
     */
    public function index(): AnonymousResourceCollection
    {
        $categories = $this->categoryService->getAll();

        return CategoryResource::collection($categories);
    }

    /**
     * Store a newly created category.
     */
    public function store(CategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->create(
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'data' => [
                'category' => new CategoryResource($category),
            ],
        ], 201);
    }

    /**
     * Display the specified category.
     */
    public function show(string $id): JsonResponse
    {
        $category = $this->categoryService->getById($id);

        return response()->json([
            'success' => true,
            'message' => 'Category retrieved successfully',
            'data' => [
                'category' => new CategoryResource($category),
            ],
        ]);
    }

    /**
     * Update the specified category.
     */
    public function update(
        CategoryRequest $request,
        string $id
    ): JsonResponse {
        $category = $this->categoryService->getById($id);

        $category = $this->categoryService->update(
            $category,
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'data' => [
                'category' => new CategoryResource($category),
            ],
        ]);
    }

    /**
     * Remove the specified category.
     */
    public function destroy(string $id): JsonResponse
    {
        $category = $this->categoryService->getById($id);

        $this->categoryService->delete($category);

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully',
        ]);
    }
}
