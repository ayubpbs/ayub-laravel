<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\ImageUpload;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    // The home page shows category images from this folder
    const DIR = 'assets/images/categories';

    public function index()
    {
        $all        = Category::withCount('products')->orderBy('sort_order')->orderBy('id')->get();
        $categories = Category::flatTree($all);

        return view('admin.categories.index', compact('categories'));
    }

    public function create()
    {
        return view('admin.categories.form', [
            'category'    => new Category(),
            'parents'     => $this->parentChoices(null),
            'hasChildren' => false,
        ]);
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
        $category = Category::findOrFail($id);

        return view('admin.categories.form', [
            'category'    => $category,
            'parents'     => $this->parentChoices($category),
            'hasChildren' => $category->children()->exists(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);
        $data     = $this->validated($request, $category);

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
        $category = Category::withCount(['products', 'children'])->findOrFail($id);

        if ($category->children_count > 0) {
            return back()->with('error', 'This category has ' . $category->children_count . ' subcategory(ies). Move or delete them first.');
        }

        if ($category->products_count > 0) {
            return back()->with('error', 'This category has ' . $category->products_count . ' product(s). Move or delete them first.');
        }

        ImageUpload::delete($category->image, self::DIR);
        $category->delete();

        return redirect()->route('admin.categories.index')->with('success', 'Category deleted.');
    }

    // Main categories that can be chosen as a parent (never the category itself)
    private function parentChoices(?Category $current)
    {
        return Category::whereNull('parent_id')
            ->when($current && $current->exists, function ($q) use ($current) {
                $q->where('id', '<>', $current->id);
            })
            ->orderBy('sort_order')->orderBy('id')->get();
    }

    private function validated(Request $request, ?Category $current = null): array
    {
        $v = $request->validate([
            'parent_id'  => 'nullable|integer|exists:categories,id',
            'label'      => 'required|string|max:100',
            'icon'       => 'nullable|string|max:20',
            'image'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'sort_order' => 'nullable|integer|min:0',
        ], [
            'label.required' => 'Please enter the category name.',
            'parent_id.exists' => 'The selected parent category does not exist.',
            'image.image'    => 'The file must be an image.',
            'image.mimes'    => 'The image must be jpg, png or webp.',
            'image.max'      => 'The image must not be larger than 2 MB.',
        ]);

        $parentId = $v['parent_id'] ?? null;

        if ($parentId) {
            $parent = Category::findOrFail($parentId);

            if ($current && $current->exists && (int) $parent->id === (int) $current->id) {
                throw ValidationException::withMessages(['parent_id' => 'A category cannot be its own parent.']);
            }
            if ($parent->parent_id !== null) {
                throw ValidationException::withMessages(['parent_id' => 'Only a main category can be a parent (two levels are supported).']);
            }
            if ($current && $current->exists && $current->children()->exists()) {
                throw ValidationException::withMessages(['parent_id' => 'This category has subcategories, so it must stay a main category.']);
            }
        }

        unset($v['image']);
        $v['parent_id']  = $parentId ? (int) $parentId : null;
        $v['sort_order'] = (int) ($v['sort_order'] ?? 0);

        return $v;
    }
}
