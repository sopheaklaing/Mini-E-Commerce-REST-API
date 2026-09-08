<?php

namespace App\Services\Category;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CategoryService
{
    /**
     * Get all categories.
     */
    public function getAll(int $perPage = 10): LengthAwarePaginator
    {
        return Category::latest()->paginate($perPage);
    }

    /**
     * Get one category.
     */
    public function getById(int|string $id): Category
    {
        return Category::findOrFail($id);
    }

    /**
     * Create category.
     */
    public function create(array $data): Category
    {
        return Category::create($data);
    }

    /**
     * Update category.
     */
    public function update(Category $category, array $data): Category
    {
        $category->update($data);

        return $category->fresh();
    }

    /**
     * Delete category.
     */
    public function delete(Category $category): void
    {
        $category->delete();
    }
}
