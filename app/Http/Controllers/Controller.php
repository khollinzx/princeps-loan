<?php

namespace App\Http\Controllers;

use App\Models\OauthAccessToken;
use App\Models\User;
use App\Services\JsonResponseAPI;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * @return JsonResponse
     */
    public function welcome(): JsonResponse
    {
        return JsonResponseAPI::successResponse("Welcome to Princeps Api version 1: " . Carbon::now());
    }

    /**
     *
     * @param Model $agent
     * @param string $resourceName
     * @param string $guard
     * @return JsonResponse
     */
    public function returnUserLoginAuth(Model $agent, string $resourceName, string $guard = 'agent'): JsonResponse
    {
        $resource        = "App\Http\Resources\\$resourceName";
        $data            = OauthAccessToken::createAccessToken($agent, $guard);
        $data['profile'] = new $resource($data['profile']);

        return JsonResponseAPI::successResponse('Login succeeded.', $data);
    }

    /**
     * Translates an array to pagination
     * @param array $collections
     * @param Request $request
     * @return LengthAwarePaginator
     */
    public function arrayPaginator(array $collections, Request $request): LengthAwarePaginator
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 5);
        $limit = !$limit ? 5 : $limit; //if limit was not available or set to 0
        $offset = ($page * $limit) - $limit;

        return new LengthAwarePaginator(
            array_slice($collections, $offset, $limit, false),
            count($collections),
            $limit,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }

    /**
     * Translates an array to pagination
     * @param array $collections
     * @param int $count
     * @param Request $request
     * @return LengthAwarePaginator
     */
    public function objectPaginator($collections, int $count, Request $request): LengthAwarePaginator
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 5);
        $limit = !$limit ? 5 : $limit; //if limit was not available or set to 0
        $offset = ($page * $limit) - $limit;

        return new LengthAwarePaginator(
            $collections,
            $count,
            $limit,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }

    /**
     * @return mixed
     */
    public function getUserId(): int
    {
        return auth()->user()->getAuthIdentifier();
    }

    /**
     * @return Authenticatable|User|null
     */
    public function getUser(): Authenticatable|User|null
    {
        return auth()->user();
    }

    /**
     * @return JsonResponse
     */
    public function throwNotFoundError(): JsonResponse
    {
        return response()->json(
            [
                "status" => false,
                "message" => "Route Not Found",
                'metaData' => [
                    "app_name" => env('APP_NAME'),
                    "version" => "v1",
                ]
            ],
            404
        );
    }

    public function showLoginForm()
    {
        return view('login');
    }

    /**
     * @return string|null
     */
    public function getUserEmail(): ?string
    {
        return auth()->user()->email;
    }
}
