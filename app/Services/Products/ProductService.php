<?php
namespace App\Services\Products;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductService
{
    /**
     * Get all products.
     */
    public function getAll(): LengthAwarePaginator
    {
        return Product::latest()->paginate(10);
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