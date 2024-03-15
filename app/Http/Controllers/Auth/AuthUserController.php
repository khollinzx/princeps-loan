<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuthUserRequest;
use App\Http\Requests\UserLoanRequest;
use App\Models\OauthAccessToken;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\Helper;
use App\Services\JsonResponseAPI;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthUserController extends Controller
{

    /**
     * @var string
     */
    private string $guard = 'user';

    /**
     * @param UserRepository $userRepository
     */
    public function __construct(
        protected UserRepository  $userRepository,
    ) {
    }

    /**
     * This handles the Agent Registration logic
     *
     * @param AuthUserRequest $request
     * @return JsonResponse
     */
    public function register(AuthUserRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            User::repo()->createModel([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'address' => $validated['address'],
                'dob' => $validated['dob'],
                'phone' => $validated['phone'],
                'password' => Hash::make($validated['password']),
            ]);
            return JsonResponseAPI::successResponse("Your account has been created successfully, kindly login to access your account.");
        } catch (\Exception $exception) {
            Log::error($exception);

            return JsonResponseAPI::internalErrorResponse($exception);
        }
    }

    /**
     * This handles the Registration logic
     *
     * @param AuthUserRequest $request
     * @param string $guard
     * @return JsonResponse
     */
    public function login(AuthUserRequest $request, string $guard = 'user'): JsonResponse
    {
        try {
            $validated = $request->validated();
            $credentials = ['email'=> $validated['email'], 'password'=> $validated['password']];
            if(!Auth::guard($guard)->attempt($credentials)) return JsonResponseAPI::errorResponse('Invalid login credentials.');
            /**
             * Get the User Account and create access token
             */
            $Account = Helper::getUserByColumnAndValue($this->userRepository, 'email', $credentials['email']);
            /** set accessToken @var $accessToken */
            $accessToken = OauthAccessToken::createAccessToken($Account, $guard);
            return JsonResponseAPI::successResponse('Login succeeded', $accessToken);
        } catch (\Exception $exception) {
            Log::error($exception);
            return JsonResponseAPI::internalErrorResponse($exception);
        }
    }
}
