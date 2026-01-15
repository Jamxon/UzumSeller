<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserController extends Controller
{
    public function storeTokens(Request $request)
    {
        $request->validate([
            'yandex_token' => 'required|string',
            'uzum_token' => 'required|string',
        ]);

        $user = $request->user();
        $user->yandex_token = $request->input('yandex_token');
        $user->uzum_token = $request->input('uzum_token');
        $user->save();

        return response()->json(['message' => 'Tokens saved successfully', 'user' => $user], 200);
    }

}
