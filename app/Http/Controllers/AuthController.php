<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'password' => Hash::make($validatedData['password']),
        ]);

        $token = auth('api')->login($user);

        return $this->respondWithToken($token, 'User registered successfully', $user);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Loginni tekshirish va tokenni olish
        if (! $token = auth('api')->attempt($credentials)) {
            return response()->json(['error' => 'Login yoki parol noto\'g\'ri'], 401);
        }

        return $this->respondWithToken($token, 'Login successful', auth('api')->user());
    }

    // Token javobini qaytaruvchi yordamchi funksiya
    protected function respondWithToken($token, $message, $user)
    {
        return response()->json([
            'message' => $message,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60, // Soniya hisobida (default 60 minut)
            'user' => $user
        ]);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        try {
            // auth('api')->logout() tokenni o'chiradi va uni qora ro'yxatga kiritadi
            auth('api')->logout();

            return response()->json([
                'status' => 'success',
                'message' => 'Tizimdan muvaffaqiyatli chiqildi (Token bekor qilindi)'
            ], 200);

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            // Agar tokenda muammo bo'lsa yoki allaqachon bekor qilingan bo'lsa
            return response()->json([
                'status' => 'error',
                'message' => 'Xatolik yuz berdi, tokenni bekor qilib bo\'lmadi'
            ], 500);
        }
    }
}