<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Product;
use App\Support\ImageUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BrandController extends Controller
{
    const DIR = 'uploads/brands';

    public function index()
    {
        $brands = Brand::withCount('products')->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.brands.index', compact('brands'));
    }

    public function create()
    {
        return view('admin.brands.form', ['brand' => new Brand()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        if ($request->hasFile('logo')) {
            $data['logo'] = ImageUpload::store($request->file('logo'), self::DIR);
        }

        Brand::create($data);

        return redirect()->route('admin.brands.index')->with('success', 'Brand added.');
    }

    public function edit($id)
    {
        return view('admin.brands.form', ['brand' => Brand::findOrFail($id)]);
    }

    public function update(Request $request, $id)
    {
        $brand = Brand::findOrFail($id);
        $data  = $this->validated($request, $brand->id);

        if ($request->hasFile('logo')) {
            ImageUpload::delete($brand->logo, self::DIR);
            $data['logo'] = ImageUpload::store($request->file('logo'), self::DIR);
        } elseif ($request->boolean('remove_logo')) {
            ImageUpload::delete($brand->logo, self::DIR);
            $data['logo'] = null;
        }

        DB::transaction(function () use ($brand, $data) {
            $brand->update($data);
            // The shop pages show the brand name saved on the product, so keep it in step with a rename
            Product::where('brand_id', $brand->id)->update(['brand' => $brand->name]);
        });

        return redirect()->route('admin.brands.index')->with('success', 'Brand updated.');
    }

    public function destroy($id)
    {
        $brand = Brand::withCount('products')->findOrFail($id);

        if ($brand->products_count > 0) {
            return back()->with('error', 'This brand has ' . $brand->products_count . ' product(s). Move or delete them first.');
        }

        ImageUpload::delete($brand->logo, self::DIR);
        $brand->delete();

        return redirect()->route('admin.brands.index')->with('success', 'Brand deleted.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $v = $request->validate([
            'name'       => ['required', 'string', 'max:100', Rule::unique('brands', 'name')->ignore($ignoreId)],
            'logo'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'sort_order' => 'nullable|integer|min:0',
        ], [
            'name.required' => 'Please enter the brand name.',
            'name.unique'   => 'This brand already exists.',
            'logo.image'    => 'The file must be an image.',
            'logo.mimes'    => 'The logo must be jpg, png or webp.',
            'logo.max'      => 'The logo must not be larger than 2 MB.',
        ]);

        unset($v['logo']);
        $v['name']       = trim($v['name']);
        $v['sort_order'] = (int) ($v['sort_order'] ?? 0);

        return $v;
    }
}
