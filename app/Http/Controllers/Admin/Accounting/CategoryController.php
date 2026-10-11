<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Models\AccountCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function income(Request $request)
    {
        return $this->listing($request, 'income');
    }

    public function expense(Request $request)
    {
        return $this->listing($request, 'expense');
    }

    public function store(Request $request, $type)
    {
        $this->checkType($type);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('account_categories', 'name')->where('type', $type)],
        ], [
            'name.required' => 'Please enter the category name.',
            'name.unique'   => 'This category already exists.',
        ]);

        AccountCategory::create(['type' => $type, 'name' => trim($data['name'])]);

        return redirect()->route('admin.accounting.categories.' . $type)->with('success', 'Category added.');
    }

    public function update(Request $request, $type, $id)
    {
        $this->checkType($type);
        $category = AccountCategory::ofType($type)->findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('account_categories', 'name')->where('type', $type)->ignore($category->id)],
        ], [
            'name.required' => 'Please enter the category name.',
            'name.unique'   => 'This category already exists.',
        ]);

        $category->update(['name' => trim($data['name'])]);

        return redirect()->route('admin.accounting.categories.' . $type)->with('success', 'Category updated.');
    }

    public function destroy($type, $id)
    {
        $this->checkType($type);
        $category = AccountCategory::ofType($type)->findOrFail($id);

        // A category that is already used by entries must stay, so old entries keep their category
        if (($this->usage()[$category->id] ?? 0) > 0) {
            return back()->with('error', 'This category is used by entries, so it cannot be deleted.');
        }

        $category->delete();

        return redirect()->route('admin.accounting.categories.' . $type)->with('success', 'Category deleted.');
    }

    private function listing(Request $request, string $type)
    {
        $categories = AccountCategory::ofType($type)->orderBy('name')->get();
        $editing    = $request->filled('edit') ? $categories->firstWhere('id', (int) $request->query('edit')) : null;

        return view('admin.accounting.categories.index', [
            'type'       => $type,
            'info'       => AccountCategory::types()[$type],
            'categories' => $categories,
            'usage'      => $this->usage(),
            'editing'    => $editing,
        ]);
    }

    /** category_id => number of entries using it. Empty until entries get a category column (Income / Expense step). */
    private function usage(): array
    {
        if (!Schema::hasColumn('account_transactions', 'category_id')) {
            return [];
        }

        return DB::table('account_transactions')
            ->whereNotNull('category_id')
            ->selectRaw('category_id, COUNT(*) as c')
            ->groupBy('category_id')
            ->pluck('c', 'category_id')
            ->map(fn ($c) => (int) $c)
            ->all();
    }

    private function checkType($type): void
    {
        abort_unless(isset(AccountCategory::types()[$type]), 404);
    }
}
