<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $r)
    {
        $v = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($v, $r->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'ایمیل یا گذرواژه درست نیست.']);
        }$r->session()->regenerate();

        return redirect()->intended('/dashboard');
    }

    public function register(Request $r)
    {
        $v = $r->validate(['name' => 'required|string|max:100', 'email' => 'required|email|unique:users', 'password' => 'required|string|min:10|confirmed']);
        Auth::login(User::create($v));
        $r->session()->regenerate();

        return redirect('/dashboard');
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/login');
    }
}
