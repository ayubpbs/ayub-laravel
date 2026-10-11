<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\ImageUpload;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    // The home page shows category images from this folder
    const DIR = 'assets/images/categories';

    public function index()
    {
        $categories = Category::withCount('products')->orderBy('sort_order')->orderBy('id')->get();

        return view('admin.categories.index', compact('categories'));
    }

    public function create()
    {
        return view('admin.categories.form', ['category' => new Category()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        if ($request->hasFile('image')) {
            $data['image'] = ImageUpload::store($request->file('image'), self::DIR);
        }

        Category::create($data);

        return redirect()->route('admin.categories.index')->with('success', 'Category added.');
    }

    public function edit($id)
    {
        return view('admin.categories.form', ['category' => Category::findOrFail($id)]);
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);
        $data     = $this->validated($request);

        if ($request->hasFile('image')) {
            ImageUpload::delete($category->image, self::DIR);
            $data['image'] = ImageUpload::store($request->file('image'), self::DIR);
        } elseif ($request->boolean('remove_image')) {
            ImageUpload::delete($category->image, self::DIR);
            $data['image'] = null;
        }

        $category->update($data);

        return redirect()->route('admin.categories.index')->with('success', 'Category updated.');
    }

    public function destroy($id)
    {
        $category = Category::withCount('products')->findOrFail($id);

        if ($category->products_count > 0) {
            return back()->with('error', 'This category has ' . $category->products_count . ' product(s). Move or delete them first.');
        }

        ImageUpload::delete($category->image, self::DIR);
        $category->delete();

        return redirect()->route('admin.categories.index')->with('success', 'Category deleted.');
    }

    private function validated(Request $request): array
    {
        $v = $request->validate([
            'label'      => 'required|string|max:100',
            'icon'       => 'nullable|string|max:20',
            'image'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'sort_order' => 'nullable|integer|min:0',
        ], [
            'label.required' => 'Please enter the category name.',
            'image.image'    => 'The file must be an image.',
            'image.mimes'    => 'The image must be jpg, png or webp.',
            'image.max'      => 'The image must not be larger than 2 MB.',
        ]);

        unset($v['image']);
        $v['sort_order'] = (int) ($v['sort_order'] ?? 0);

        return $v;
    }
}
