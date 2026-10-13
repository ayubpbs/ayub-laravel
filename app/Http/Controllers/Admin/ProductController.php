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
        $cost  = (string) $request->query('cost', '');

        $query = Product::with('category')->latestFirst();

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('title', 'like', "%{$q}%")->orWhere('brand', 'like', "%{$q}%");
                if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'sku')) {
                    $w->orWhere('sku', 'like', "%{$q}%");
                }
            });
        }
        if ($cat !== '') {
            // A main category also matches the products of its subcategories
            $catIds = Category::where('parent_id', (int) $cat)->pluck('id')->push((int) $cat)->all();
            $query->whereIn('category_id', $catIds);
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

        // Products that have stock but no buying price yet (they are not counted in the stock value)
        $hasCost = \Illuminate\Support\Facades\Schema::hasColumn('products', 'cost_price');
        if ($hasCost && $cost === 'missing') {
            $query->whereNull('cost_price')->where('stock_quantity', '>', 0);
        } else {
            $cost = '';
        }

        $summary = null;
        if ($hasCost) {
            try {
                $summary = Product::stockSummary();
            } catch (\Throwable $e) {
                $summary = null;
            }
        }

        $products   = $query->paginate(20)->withQueryString();
        $categories = Category::flatTree(Category::orderBy('sort_order')->orderBy('id')->get());
        $brands     = Brand::orderBy('sort_order')->orderBy('name')->get();

        return view('admin.products.index', compact('products', 'categories', 'brands', 'q', 'cat', 'brand', 'stock', 'cost', 'summary', 'hasCost'));
    }

    public function create()
    {
        return view('admin.products.form', [
            'product'    => new Product(),
            'categories' => Category::flatTree(Category::orderBy('sort_order')->orderBy('id')->get()),
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

        $product = Product::create($data);
        $warning = $this->saveGallery($request, $product);

        return redirect()->route('admin.products.index')->with('success', 'Product added.' . $warning);
    }

    public function edit($id)
    {
        return view('admin.products.form', [
            'product'    => Product::findOrFail($id),
            'categories' => Category::flatTree(Category::orderBy('sort_order')->orderBy('id')->get()),
            'brands'     => Brand::orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $data    = $this->validated($request, $product->id);

        if ($request->hasFile('image')) {
            ImageUpload::delete($product->image, self::DIR);
            $data['image'] = ImageUpload::store($request->file('image'), self::DIR);
        } elseif ($request->boolean('remove_image')) {
            ImageUpload::delete($product->image, self::DIR);
            $data['image'] = null;
        }

        $product->update($data);
        $warning = $this->saveGallery($request, $product);

        // Keep the low-stock alert in step with the stock the admin just typed in
        \App\Support\Notify::lowStock($product);
        \App\Support\Notify::stockRecovered($product);

        return redirect()->route('admin.products.index')->with('success', 'Product updated.' . $warning);
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        ImageUpload::delete($product->image, self::DIR);
        $this->deleteGallery($product);
        if (\App\Models\ProductReview::available()) {
            \App\Models\ProductReview::where('product_id', $product->id)->delete();
        }
        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Product deleted.');
    }

    const MAX_GALLERY = 8;

    /**
     * Adds the new gallery images and removes the ticked ones.
     * Returns a short warning text for the flash message (empty when everything went fine).
     * If the product_images table does not exist yet (migration not run), nothing is saved.
     */
    private function saveGallery(Request $request, Product $product): string
    {
        $remove = array_values(array_filter(array_map('intval', (array) $request->input('remove_gallery', []))));
        $files  = array_values(array_filter((array) $request->file('gallery', [])));

        if (!$remove && !$files) {
            return '';
        }
        if (!\Illuminate\Support\Facades\Schema::hasTable('product_images')) {
            return ' (Extra images were NOT saved: run "php artisan migrate" first.)';
        }

        if ($remove) {
            $rows = \App\Models\ProductImage::where('product_id', $product->id)->whereIn('id', $remove)->get();
            foreach ($rows as $row) {
                ImageUpload::delete($row->image, \App\Models\ProductImage::DIR);
                $row->delete();
            }
        }

        $warning = '';
        if ($files) {
            $current = \App\Models\ProductImage::where('product_id', $product->id)->count();
            $room    = max(0, self::MAX_GALLERY - $current);
            $sort    = (int) \App\Models\ProductImage::where('product_id', $product->id)->max('sort_order');

            foreach (array_slice($files, 0, $room) as $file) {
                \App\Models\ProductImage::create([
                    'product_id' => $product->id,
                    'image'      => ImageUpload::store($file, \App\Models\ProductImage::DIR),
                    'sort_order' => ++$sort,
                ]);
            }
            if (count($files) > $room) {
                $warning = ' (Only ' . self::MAX_GALLERY . ' extra images are allowed, so ' . (count($files) - $room) . ' were skipped.)';
            }
        }

        return $warning;
    }

    private function deleteGallery(Product $product): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('product_images')) {
            return;
        }
        foreach (\App\Models\ProductImage::where('product_id', $product->id)->get() as $row) {
            ImageUpload::delete($row->image, \App\Models\ProductImage::DIR);
            $row->delete();
        }
    }

    private function validated(Request $request, $ignoreId = null): array
    {
        $hasSku  = \Illuminate\Support\Facades\Schema::hasColumn('products', 'sku');
        $hasSlug = \Illuminate\Support\Facades\Schema::hasColumn('products', 'slug');

        $v = $request->validate([
            'sku'            => $hasSku
                ? ['nullable', 'string', 'max:60', \Illuminate\Validation\Rule::unique('products', 'sku')->ignore($ignoreId)]
                : 'nullable|string|max:60',
            'category_id'    => 'nullable|exists:categories,id',
            'title'          => 'required|string|max:255',
            'brand_id'       => 'nullable|exists:brands,id',
            'slug'           => $hasSlug
                ? ['nullable', 'string', 'max:150', 'regex:/^[\p{L}\p{M}\p{N}]+(?:-[\p{L}\p{M}\p{N}]+)*$/u', \Illuminate\Validation\Rule::unique('products', 'slug')->ignore($ignoreId)]
                : 'nullable|string|max:150',
            'product_type'   => ['nullable', \Illuminate\Validation\Rule::in(array_keys(Product::TYPES))],
            'short_description' => 'nullable|string|max:20000',
            'description'    => 'nullable|string|max:200000',
            'icon'           => 'nullable|string|max:20',
            'image'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'gallery'        => 'nullable|array|max:8',
            'gallery.*'      => 'image|mimes:jpg,jpeg,png,webp|max:2048',
            'video_urls'     => ['nullable', 'string', 'max:1500', function ($attribute, $value, $fail) {
                $lines = array_values(array_filter(array_map('trim', preg_split('/\R+/', (string) $value))));
                if (count($lines) > 5) {
                    return $fail('You can add at most 5 video links.');
                }
                foreach ($lines as $line) {
                    if (!Product::youtubeId($line)) {
                        return $fail('This is not a valid YouTube link: ' . $line);
                    }
                }
            }],
            'specifications'         => 'nullable|array|max:30',
            'specifications.*.name'  => 'nullable|string|max:100',
            'specifications.*.value' => 'nullable|string|max:255',
            'price'         => 'required|integer|min:0',
            'cost_price'     => 'nullable|integer|min:0',
            'old_price'      => 'nullable|integer|min:0',
            'badge'          => 'nullable|string|max:50',
            'section'        => 'nullable|in:top,new',
            'stock_quantity' => 'required|integer|min:0',
        ], [
            'title.required'          => 'Please enter the product name.',
            'sku.unique'              => 'This SKU is already used by another product.',
            'slug.unique'             => 'This URL (slug) is already used by another product. Please change it.',
            'slug.regex'              => 'The URL (slug) can only have letters, numbers and hyphens (-), for example led-bulb-12w.',
            'slug.max'                => 'The URL (slug) must not be longer than 150 characters.',
            'product_type.in'         => 'Please choose a valid product type.',
            'description.max'         => 'The full description is too long.',
            'short_description.max'   => 'The short description is too long.',
            'sku.max'                 => 'The SKU must not be longer than 60 characters.',
            'price.required'          => 'Please enter the price.',
            'price.integer'           => 'The price must be a number.',
            'cost_price.integer'      => 'The cost price must be a whole number of taka.',
            'stock_quantity.required' => 'Please enter the stock quantity.',
            'image.image'             => 'The file must be an image.',
            'image.mimes'             => 'The image must be jpg, png or webp.',
            'image.max'               => 'The image must not be larger than 2 MB.',
            'specifications.max'      => 'You can add at most 30 specifications.',
            'specifications.*.name.max'  => 'A specification name must not be longer than 100 characters.',
            'specifications.*.value.max' => 'A specification value must not be longer than 255 characters.',
            'gallery.max'             => 'You can upload at most 8 extra images at a time.',
            'gallery.*.image'         => 'Every extra file must be an image.',
            'gallery.*.mimes'         => 'Extra images must be jpg, png or webp.',
            'gallery.*.max'           => 'Each extra image must not be larger than 2 MB.',
        ]);

        unset($v['image'], $v['gallery']);

        // Save the video links in one clean format: one https://www.youtube.com/watch?v=ID per line, no duplicates
        $ids = [];
        foreach (preg_split('/\R+/', (string) ($v['video_urls'] ?? '')) as $line) {
            if ($id = Product::youtubeId($line)) {
                $ids[$id] = 'https://www.youtube.com/watch?v=' . $id;
            }
        }
        $v['video_urls'] = $ids ? implode("\n", $ids) : null;

        if (!\Illuminate\Support\Facades\Schema::hasColumn('products', 'video_urls')) {
            if ($v['video_urls']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'video_urls' => 'The video links were not saved: run "php artisan migrate" first.',
                ]);
            }
            unset($v['video_urls']);
        }

        // Specifications: skip empty rows, but a row with only a name or only a value is an error
        $specs = [];
        foreach ((array) ($v['specifications'] ?? []) as $row) {
            $name  = trim((string) ($row['name'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            if ($name === '' && $value === '') {
                continue;
            }
            if ($name === '' || $value === '') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'specifications' => 'Every specification needs both a name and a value (check: ' . ($name !== '' ? $name : $value) . ').',
                ]);
            }
            $specs[] = ['name' => $name, 'value' => $value];
        }
        $v['specifications'] = $specs ?: null;

        if (!\Illuminate\Support\Facades\Schema::hasColumn('products', 'specifications')) {
            if ($v['specifications']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'specifications' => 'The specifications were not saved: run "php artisan migrate" first.',
                ]);
            }
            unset($v['specifications']);
        }

        // Descriptions: keep the formatting, remove anything that could run code
        $v['description']       = \App\Support\HtmlCleaner::clean($v['description'] ?? '');
        $v['short_description'] = \App\Support\HtmlCleaner::clean($v['short_description'] ?? '');
        if (mb_strlen(\App\Support\HtmlCleaner::plain($v['short_description'])) > 600) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'short_description' => 'The short description is too long (maximum 600 characters of text). Put the rest in the full description.',
            ]);
        }
        $v['description']       = \App\Support\HtmlCleaner::isEmpty($v['description']) ? null : $v['description'];
        $v['short_description'] = \App\Support\HtmlCleaner::isEmpty($v['short_description']) ? null : $v['short_description'];

        // Slug (the product's web address): from the name when left empty; always lowercase and unique
        if ($hasSlug) {
            $slug = Product::makeSlug($v['slug'] ?? '');
            if ($slug === '') {
                $slug = Product::makeSlug($v['title']);
            }
            $slug = Product::uniqueSlug($slug, $ignoreId);
            if (ctype_digit($slug)) {
                $slug = 'product-' . $slug;      // a number alone would look like a product id
            }
            $v['slug'] = $slug;
        } else {
            unset($v['slug']);
        }

        // Product type
        $v['product_type'] = ($v['product_type'] ?? null) ?: 'simple';
        if (!\Illuminate\Support\Facades\Schema::hasColumn('products', 'product_type')) {
            unset($v['product_type']);
        }
        if (!\Illuminate\Support\Facades\Schema::hasColumn('products', 'short_description')) {
            if ($v['short_description']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'short_description' => 'The short description was not saved: run "php artisan migrate" first.',
                ]);
            }
            unset($v['short_description']);
        }

        // SKU: stored trimmed; empty means "no SKU" (several products may have none)
        $v['sku'] = trim((string) ($v['sku'] ?? '')) ?: null;
        if (!$hasSku) {
            if ($v['sku']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'sku' => 'The SKU was not saved: run "php artisan migrate" first.',
                ]);
            }
            unset($v['sku']);
        }

        // Without the cost_price column (migration not run yet) the cost cannot be saved
        if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'cost_price')) {
            $v['cost_price'] = $v['cost_price'] ?? null;
        } else {
            unset($v['cost_price']);
        }
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
