<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Admin\Accounting\Concerns\HandlesEntries;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class IncomeController extends Controller
{
    use HandlesEntries;

    public function index(Request $request)
    {
        return $this->entryIndex($request, 'income');
    }

    public function create()
    {
        return $this->entryCreate('income');
    }

    public function store(Request $request)
    {
        return $this->entryStore($request, 'income');
    }

    public function edit($id)
    {
        return $this->entryEdit('income', $id);
    }

    public function update(Request $request, $id)
    {
        return $this->entryUpdate($request, 'income', $id);
    }

    public function destroy($id)
    {
        return $this->entryDestroy('income', $id);
    }
}
