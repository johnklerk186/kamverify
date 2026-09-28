<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PushSubscriptionController extends Controller
{
    /**
     * Store a browser Push API subscription for the current user.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'endpoint' => 'required|url|max:2000',
            'keys' => 'required|array',
            'keys.p256dh' => 'required|string|max:500',
            'keys.auth' => 'required|string|max:500',
            'contentEncoding' => 'nullable|string|max:20',
        ]);

        Auth::user()->pushSubscriptions()->updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
            ]
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Remove a subscription (user disabled notifications in this browser).
     */
    public function destroy(Request $request)
    {
        $data = $request->validate(['endpoint' => 'required|url|max:2000']);

        Auth::user()->pushSubscriptions()->where('endpoint', $data['endpoint'])->delete();

        return response()->json(['ok' => true]);
    }
}
