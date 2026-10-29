<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => UserNotification::where('user_id', $request->user()->id)->latest('id')->paginate(30),
        ]);
    }

    /** Ouvre la notification (marquée lue) ; seuls les liens internes sont suivis. */
    public function open(Request $request, UserNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        $notification->forceFill(['read_at' => $notification->read_at ?? now()])->save();
        $url = (string) $notification->url;

        return str_starts_with($url, '/') && ! str_starts_with($url, '//') ? redirect()->to($url) : redirect()->route('pm.notifications.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        UserNotification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', 'Notifications marquées comme lues.');
    }
}
