<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login','register','refresh']]);
    }


    public function login(Request $request)
    {
        $credentials = $request->only(['email', 'password']);

        if (! $token = Auth::attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $refreshToken = $this->generateRefreshToken(Auth::user());

        return $this->respondWithToken($token, $refreshToken);
    }


    public function refresh(Request $request)
    {
        $oldRefreshToken = $request->bearerToken();

        if (! $oldRefreshToken) {
            return response()->json(['error' => 'Refresh token required'], 400);
        }

        try {
            $payload = JWTAuth::setToken($oldRefreshToken)->getPayload();

            JWTAuth::invalidate($oldRefreshToken);

            $user = User::find($payload['sub']);
            $newAccessToken = Auth::login($user);

            $newRefreshToken = $this->generateRefreshToken($user);

            return $this->respondWithToken($newAccessToken, $newRefreshToken);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid refresh token'], 401);
        }
    }


    protected function generateRefreshToken($user)
    {
        return JWTAuth::claims([
            'type' => 'refresh',
            'random' => uniqid('', true)
        ])->fromUser($user);
    }


    public function me()
    {
        return response()->json(Auth::user());
    }


    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
        } catch (\Exception $e) {}

        return response()->json(['message' => 'Successfully logged out']);
    }


    protected function respondWithToken($accessToken, $refreshToken)
    {
        return response()->json([
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type'    => 'bearer',
            'expires_in'    => Auth::factory()->getTTL() * 60,
        ]);
    }


    public function register(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ]);

        User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json(['message' => 'User successfully registered','status'=>true], 201);
    }
    public function edit (Request $request)
    {
        $user = User::find(Auth::id());

        $request->validate([
            'name'     => 'sometimes|required|string|max:255',
        ]);

        if ($request->has('name')) {
            $user->name = $request->name;
        }



        $user->save();

        return response()->json(['message' => 'User successfully updated','status'=>true], 200);
    }
}
