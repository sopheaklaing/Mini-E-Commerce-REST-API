<?php

namespace App\Services\Products;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductService
{
    /**
     * Get all products.
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Product::query()->with('category');

        // search
        if (! empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($query) use ($search) {
                $query->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('description', 'ILIKE', "%{$search}%");

            });
        }

        // Filter by category
        if (! empty($filters['category_id'])) {
            $query->where(
                'category_id',
                $filters['category_id']
            );
        }

        // Filter by minimum price
        if (isset($filters['min_price'])) {
            $query->where(
                'price',
                '>=',
                $filters['min_price']
            );
        }

        // Filter by maximum price
        if (isset($filters['max_price'])) {
            $query->where(
                'price',
                '<=',
                $filters['max_price']
            );
        }

        // Sorting
        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';

        $query->orderBy($sort, $direction);

        // Pagination
        $perPage = $filters['per_page'] ?? 10;

        return $query->paginate($perPage);
    }

    /**
     * Create a new product.
     */
    public function create(array $data): Product
    {
        return Product::create($data);
    }

    /**
     * Find a product.
     */
    public function find(Product $product): Product
    {
        return $product;
    }

    /**
     * Update a product.
     */
    public function update(
        Product $product,
        array $data
    ): Product {
        $product->update($data);

        return $product->fresh();
    }

    /**
     * Delete a product.
     */
    public function delete(Product $product): void
    {
        $product->delete();
    }
}
