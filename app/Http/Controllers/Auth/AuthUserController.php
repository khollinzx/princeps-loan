<?php

namespace App\Http\Auth\Agents;

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
use App\Services\Helper;
use App\Services\JsonResponseAPI;
use App\Services\MobileMFA\MobileMFAService;
use App\Services\Notification\NotificationService;
use App\Traits\HasPhoneFieldTrait;
use App\Utils\Constants;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SendGrid\Mail\TypeException;

class AuthUserController extends Controller
{
    use HasPhoneFieldTrait;

    /**
     * @var string
     */
    private string $guard = 'user';

    /**
     * @param User $user
     */
    public function __construct(
        protected User  $user,
    ) {
    }

    /**
     * This handles the Agent Registration logic
     *
     * @param UserLoanRequest $request
     * @return JsonResponse
     */
    public function register(UserLoanRequest $request): JsonResponse
    {
        try {

            $form = $request->validated();

            return JsonResponseAPI::successResponse("An OTP has been sent to both email and phone number. Kindly check.");
        } catch (\Exception $exception) {
            Log::error($exception);

            return JsonResponseAPI::internalErrorResponse($exception);
        }
    }
}
