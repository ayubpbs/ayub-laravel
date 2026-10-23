<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\Phone;
use Illuminate\Http\Request;

/**
 * Owner-only list of everything that was written to the audit log: customers, addresses, orders, claims,
 * warranty decisions. The route is not in the permission map of App\Support\AdminAccess, so only the owner can open it.
 */
class AuditController extends Controller
{
    public function index(Request $request)
    {
        if (!AuditLog::tableReady()) {
            return view('admin.audit.index', ['ready' => false, 'logs' => null, 'actions' => collect(), 'f' => []]);
        }

        $f = [
            'action' => trim((string) $request->query('action', '')),
            'actor'  => trim((string) $request->query('actor', '')),
            'phone'  => trim((string) $request->query('phone', '')),
            'q'      => trim((string) $request->query('q', '')),
            'from'   => $this->date($request->query('from')),
            'to'     => $this->date($request->query('to')),
        ];

        $query = AuditLog::latest('id');

        if ($f['action'] !== '') {
            // "claim." style prefix groups or an exact action
            $query->where('action', 'like', str_replace(['%', '_'], ['\\%', '\\_'], $f['action']) . '%');
        }
        if ($f['actor'] !== '') {
            $query->where('actor_name', 'like', '%' . $f['actor'] . '%');
        }
        if ($f['phone'] !== '') {
            $fragment = Phone::fragment($f['phone']);
            if ($fragment !== '') {
                $query->where('customer_phone', 'like', "%{$fragment}%");
            }
        }
        if ($f['q'] !== '') {
            $query->where('summary', 'like', '%' . $f['q'] . '%');
        }
        if ($f['from'] !== '') {
            $query->whereDate('created_at', '>=', $f['from']);
        }
        if ($f['to'] !== '') {
            $query->whereDate('created_at', '<=', $f['to']);
        }

        return view('admin.audit.index', [
            'ready'   => true,
            'logs'    => $query->paginate(30)->withQueryString(),
            'actions' => AuditLog::select('action')->distinct()->orderBy('action')->pluck('action'),
            'f'       => $f,
        ]);
    }

    private function date($value): string
    {
        $value = trim((string) $value);

        return $value !== '' && strtotime($value) !== false ? date('Y-m-d', strtotime($value)) : '';
    }
}
