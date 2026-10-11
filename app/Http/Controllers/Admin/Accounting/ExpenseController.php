<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Admin\Accounting\Concerns\HandlesEntries;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    use HandlesEntries;

    public function index(Request $request)
    {
        return $this->entryIndex($request, 'expense');
    }

    public function create()
    {
        return $this->entryCreate('expense');
    }

    public function store(Request $request)
    {
        return $this->entryStore($request, 'expense');
    }

    public function edit($id)
    {
        return $this->entryEdit('expense', $id);
    }

    public function update(Request $request, $id)
    {
        return $this->entryUpdate($request, 'expense', $id);
    }

    public function destroy($id)
    {
        return $this->entryDestroy('expense', $id);
    }
}
