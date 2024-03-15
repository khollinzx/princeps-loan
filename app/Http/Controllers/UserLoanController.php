<?php

namespace App\Http\Controllers;

use App\Abstractions\Implementations\UserLoanService;
use App\Enums\NotificationCategory;
use App\Events\TreekleActionsProcessed;
use App\Exceptions\MissingPermissionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AuthAgentMobileRequest;
use App\Http\Requests\UserLoanRequest;
use App\Http\Resources\AgentResource;
use App\Jobs\SendInAppNotificationJob;
use App\Models\AgeConfig;
use App\Models\Agent;
use App\Models\AgentOtp;
use App\Models\OauthAccessToken;
use App\Models\User;
use App\Models\UserLoan;
use App\Services\Helper;
use App\Services\JsonResponseAPI;
use App\Services\MobileMFA\MobileMFAService;
use App\Services\Notification\NotificationService;
use App\Traits\HasPhoneFieldTrait;
use App\Utils\Constants;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SendGrid\Mail\TypeException;

class UserLoanController extends Controller
{

    /**
     * @param User $user
     * @param UserLoanService $service
     * @param UserLoan $loan
     */
    public function __construct( protected User $user, protected UserLoanService $service, protected UserLoan $loan
    ) {}

    /**
     * This handles the Agent Registration logic
     *
     * @return JsonResponse
     */
    public function getUserDetails(): JsonResponse
    {
        try {
            return JsonResponseAPI::successResponse("Current logged in User", $this->service->getActiveUser());
        } catch (\Exception $exception) {
            Log::error($exception);

            return JsonResponseAPI::internalErrorResponse($exception);
        }
    }
    /**
     * This handles the Agent Registration logic
     *
     * @param UserLoanRequest $request
     * @return JsonResponse
     */
    public function applyForLoan(UserLoanRequest $request): JsonResponse
    {
        try {
            $form = $request->validated();;
            return JsonResponseAPI::successResponse("Your loan request has been submitted.",
                $this->loan::repo()->applyForLoan($this->getUser(), $form)
            );
        } catch (\Exception $exception) {
            Log::error($exception);

            return JsonResponseAPI::internalErrorResponse($exception);
        }
    }
}
