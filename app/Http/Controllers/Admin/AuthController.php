<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Session login for admins (users created with `php artisan make:admin`). No sign-up route.
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages(['email' => __('These details do not match an admin account.')]);
        }

        $request->session()->regenerate();

        return response()->json(['user' => $this->user($request)]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['logged_out' => true]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->user($request)]);
    }

    private function user(Request $request): array
    {
        return $request->user()->only(['id', 'name', 'email']);
    }
}
