<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\ImageUpload;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    const DIR = 'uploads/products';

    public function index(Request $request)
    {
        $q   = trim((string) $request->query('q', ''));
        $cat = (string) $request->query('category', '');
        $brand = (string) $request->query('brand', '');
        $stock = (string) $request->query('stock', '');

        $query = Product::with('category')->latestFirst();

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('title', 'like', "%{$q}%")->orWhere('brand', 'like', "%{$q}%");
            });
        }
        if ($cat !== '') {
            $query->where('category_id', (int) $cat);
        }

        if ($brand !== '') {
            $query->where('brand_id', (int) $brand);
        }
        if ($stock === 'out') {
            $query->where('stock_quantity', '<', 1);
        } elseif ($stock === 'low') {
            $query->where('stock_quantity', '>=', 1)->where('stock_quantity', '<=', 5);
        } elseif ($stock === 'in') {
            $query->where('stock_quantity', '>', 5);
        } else {
            $stock = '';
        }

        $products   = $query->paginate(20)->withQueryString();
        $categories = Category::orderBy('sort_order')->orderBy('id')->get();
        $brands     = Brand::orderBy('sort_order')->orderBy('name')->get();

        return view('admin.products.index', compact('products', 'categories', 'brands', 'q', 'cat', 'brand', 'stock'));
    }

    public function create()
    {
        return view('admin.products.form', [
            'product'    => new Product(),
            'categories' => Category::orderBy('sort_order')->orderBy('id')->get(),
            'brands'     => Brand::orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        if ($request->hasFile('image')) {
            $data['image'] = ImageUpload::store($request->file('image'), self::DIR);
        }
        $data['published_at'] = now();

        Product::create($data);

        return redirect()->route('admin.products.index')->with('success', 'Product added.');
    }

    public function edit($id)
    {
        return view('admin.products.form', [
            'product'    => Product::findOrFail($id),
            'categories' => Category::orderBy('sort_order')->orderBy('id')->get(),
            'brands'     => Brand::orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $data    = $this->validated($request);

        if ($request->hasFile('image')) {
            ImageUpload::delete($product->image, self::DIR);
            $data['image'] = ImageUpload::store($request->file('image'), self::DIR);
        } elseif ($request->boolean('remove_image')) {
            ImageUpload::delete($product->image, self::DIR);
            $data['image'] = null;
        }

        $product->update($data);

        // Keep the low-stock alert in step with the stock the admin just typed in
        \App\Support\Notify::lowStock($product);
        \App\Support\Notify::stockRecovered($product);

        return redirect()->route('admin.products.index')->with('success', 'Product updated.');
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        ImageUpload::delete($product->image, self::DIR);
        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Product deleted.');
    }

    private function validated(Request $request): array
    {
        $v = $request->validate([
            'category_id'    => 'nullable|exists:categories,id',
            'title'          => 'required|string|max:255',
            'brand_id'       => 'nullable|exists:brands,id',
            'description'    => 'nullable|string|max:5000',
            'icon'           => 'nullable|string|max:20',
            'image'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'price'          => 'required|integer|min:0',
            'cost_price'     => 'nullable|integer|min:0',
            'old_price'      => 'nullable|integer|min:0',
            'badge'          => 'nullable|string|max:50',
            'section'        => 'nullable|in:top,new',
            'stock_quantity' => 'required|integer|min:0',
        ], [
            'title.required'          => 'Please enter the product name.',
            'price.required'          => 'Please enter the price.',
            'price.integer'           => 'The price must be a number.',
            'cost_price.integer'      => 'The cost price must be a whole number of taka.',
            'stock_quantity.required' => 'Please enter the stock quantity.',
            'image.image'             => 'The file must be an image.',
            'image.mimes'             => 'The image must be jpg, png or webp.',
            'image.max'               => 'The image must not be larger than 2 MB.',
        ]);

        unset($v['image']);

        $v['cost_price']   = $v['cost_price'] ?? null;
        $v['category_id']  = $v['category_id'] ?? null;
        $v['brand_id']     = $v['brand_id'] ?? null;
        // The shop pages show the brand name saved on the product
        $v['brand']        = $v['brand_id'] ? Brand::whereKey($v['brand_id'])->value('name') : null;
        $v['section']      = $v['section'] ?? null;
        $v['is_upcoming']  = $request->boolean('is_upcoming');
        $v['is_published'] = $request->boolean('is_published');

        return $v;
    }
}
