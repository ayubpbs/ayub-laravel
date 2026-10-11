<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $type = (string) $request->query('type', '');
        $tab  = $request->query('tab') === 'unread' ? 'unread' : 'all';

        $query = AdminNotification::latest('created_at')->latest('id');

        if ($type !== '' && array_key_exists($type, AdminNotification::types())) {
            $query->where('type', $type);
        } else {
            $type = '';
        }
        if ($tab === 'unread') {
            $query->unread();
        }

        $notifications = $query->paginate(25)->withQueryString();
        $unread        = AdminNotification::unread()->count();
        $perType       = AdminNotification::unread()->selectRaw('type, count(*) as c')->groupBy('type')->pluck('c', 'type');

        return view('admin.notifications.index', compact('notifications', 'type', 'tab', 'unread', 'perType'));
    }

    /** Marks it read, then goes to the order / product it is about */
    public function open($id)
    {
        $n = AdminNotification::findOrFail($id);
        if ($n->isUnread()) {
            $n->update(['read_at' => now()]);
        }

        // only ever send the admin to a page inside the panel
        $url = (string) $n->url;

        return redirect($url !== '' && str_starts_with($url, '/') && !str_starts_with($url, '//') ? $url : route('admin.notifications.index'));
    }

    public function read($id)
    {
        AdminNotification::whereKey($id)->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }

    public function readAll()
    {
        AdminNotification::unread()->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }

    public function clearRead()
    {
        $n = AdminNotification::whereNotNull('read_at')->delete();

        return back()->with('success', $n . ' read notification(s) deleted.');
    }
}
