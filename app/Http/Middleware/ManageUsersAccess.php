<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\Agent;
use App\Models\AgentCustomer;
use App\Models\Merchant;
use App\Models\OauthAccessToken;
use App\Models\User;
use App\Repositories\CompanyStaffRepository;
use App\Repositories\UserRepository;
use App\Services\JsonResponseAPI;
use Closure;
use Illuminate\Http\Request;

class ManageUsersAccess
{

    /**
     * @param UserRepository $userRepository
     */
    public function __construct(protected UserRepository $userRepository)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        # set the allowed authenticated guards
        $allowedGuards = [
            (new User())->getGuard(),
            $this->userRepository->guard
        ];

        if(!$request->hasHeader('authorization')) return JsonResponseAPI::errorResponse("Access denied! No Authorization header was defined.", JsonResponseAPI::$BAD_REQUEST);

        if(!$request->guard?? 'user') return JsonResponseAPI::errorResponse("Access denied! No guard passed.", JsonResponseAPI::$UNAUTHORIZED);

        if(!in_array($request->guard, $allowedGuards)) return JsonResponseAPI::errorResponse("Auth guard is invalid.", JsonResponseAPI::$UNAUTHORIZED);

        /**
         *
         * Switch among the guard requested and set the provider
         * accordingly using passport authentication means
         */
        switch ($request->guard) {
            default:
                OauthAccessToken::setAuthProvider('users');
                break;
        }

        return $next($request);
    }
}
