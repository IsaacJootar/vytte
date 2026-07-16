<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserPreferenceController extends Controller
{
    public function setTheme(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'in:light,dark'],
        ]);

        auth()->user()->update(['theme' => $validated['theme']]);

        if ($request->expectsJson()) {
            return response()->json(['theme' => $validated['theme']]);
        }

        return back();
    }
}
