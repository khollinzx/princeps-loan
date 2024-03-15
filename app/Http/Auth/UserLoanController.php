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

class UserLoanController extends Controller
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
        protected User               $user,
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

            $form           = $request->validated();

            return JsonResponseAPI::successResponse("An OTP has been sent to both email and phone number. Kindly check.");
        } catch (\Exception $exception) {
            Log::error($exception);

            return JsonResponseAPI::internalErrorResponse($exception);
        }
    }

    /**
     * This enables a Boon Agent/Student validate his/her profiling
     * @param UserLoanRequest $request
     * @return JsonResponse
     */
    public function completeProfile(UserLoanRequest $request): JsonResponse
    {
        try {

            $form       = $request->validated();
            $user       = $this->agent::find($form['agentId']);
            #Check that the Student is above the minimum age and below the maximum age configured on the System
            $age_config = AgeConfig::first();

            if ($age_config->is_enable && !Helper::isAgeAccepted($age_config->minimum, $age_config->maximum, $form['dob']))
                return JsonResponseAPI::errorResponse("Oops! We accept ages between $age_config->minimum and $age_config->maximum");

            #finalize the registration
            $this->agent->setupProfiling($user, $form);

            return JsonResponseAPI::successResponse("Your Profiling is successful.");
        } catch (\Exception $exception) {
            Log::error($exception);

            return JsonResponseAPI::internalErrorResponse($exception->getMessage());
        }
    }

    /**
     * This enables an Agent to set up a password on his/her account
     *
     * @param UserLoanRequest $request
     * @return JsonResponse
     */
    public function setPassword(UserLoanRequest $request): JsonResponse
    {
        try {
            $this->agent->setPassword($request->validated());
            return JsonResponseAPI::successResponse("Congratulations! You have successfully completed your registration.");
        } catch (\Exception $exception) {
            Log::error($exception);
            return JsonResponseAPI::internalErrorResponse();
        }
    }

    /**
     * This handles the login logic of the Agent using Phone Number and Password/PIN
     *
     * @param UserLoanRequest $request
     * @return JsonResponse
     * @throws TypeException
     */
    public function login(UserLoanRequest $request): JsonResponse
    {
        try {

            $form          = $request->validated();
            $form['phone'] = $this->getPhoneNumberWithDialingCode($form['phone']);
            $form          = array_change_key_case($form, CASE_LOWER);
            $agent         = $this->agent->retrieveLoginData('phone', $form['phone']);
            $record        = Helper::handleReturningUsers($agent);
            $isMobile = $form['ismobile'] ?? false;
            $imei = $form['imei'] ?? "";
            $fcmToken = $form['fcmtoken'] ?? null;

            if (($agent && $agent->deletedAccount) || !$agent)
                return JsonResponseAPI::errorResponse("Account does not exist.", JsonResponseAPI::$UNAUTHORIZED);

            // $payload = [
            //     'title'       => 'Login',
            //     'description' => 'Login successfully.',
            //     'icon'        => Constants::NOTIFICATION_DEFAULT_ICON,
            //     'category'    => NotificationCategory::GENERAL
            // ];

            unset($form['imei']);
            unset($form['ismobile']);
            unset($form['fcmtoken']);

            if (count($record)) return JsonResponseAPI::successResponse($record['message'], $record['data']);
            if ($isMobile) $this->mobileMFAService->processMobileMFA($agent, $imei);
            if ($fcmToken) $this->notificationService->setFCMToken($agent, $fcmToken);
            # validates the login credentials using Phone and Password
            if (Auth::guard($this->guard)->attempt($form)) {
                return $this->returnUserLoginAuth($agent, "AgentResource");
            }
            # At this point, the password is assumed to be the PIN of the Student
            else if ($agent->getPin() === $form['password']) {
                return $this->returnUserLoginAuth($agent, 'AgentResource');
            }
            return JsonResponseAPI::errorResponse("Invalid login credentials.");
        } catch (MissingPermissionException $exception) {
            Log::error($exception);
            return JsonResponseAPI::clientErrorResponse($exception->getMessage());
        } catch (\Exception $exception) {
            Log::error($exception);
            return JsonResponseAPI::internalErrorResponse();
        }
    }

    /**
     * This handles the login logic of the Agent using Phone Number and Password/PIN
     *
     * @param UserLoanRequest $request
     * @return JsonResponse
     */
    public function loginByImei(UserLoanRequest $request): JsonResponse
    {
        try {
            $form = $request->validated();
            $imei = $form['imei'];
            $mobileMfa = $this->mobileMFAService->getByImei($imei);
            $form['password'] = $mobileMfa->profile->password;
            $form = array_change_key_case($form, CASE_LOWER);
            $this->mobileMFAService->processMobileMFA($mobileMfa->profile, $imei);
            $agent = $this->agent->retrieveLoginData('phone', $mobileMfa->profile->phone);
            $record = Helper::handleReturningUsers($agent);
            $payload = [
                'title' => 'Transaction',
                'description' => 'Transaction successful.',
                'icon' => Constants::NOTIFICATION_DEFAULT_ICON,
                'category' => NotificationCategory::GENERAL
            ];
            if (count($record)) {
                return JsonResponseAPI::successResponse($record['message'], $record['data']);
            }
            dispatch(new SendInAppNotificationJob($agent, $payload))->delay(Carbon::now()->addSeconds(2));
            return $this->returnUserLoginAuth($agent, 'AgentResource');
        } catch (MissingPermissionException $exception) {
            Log::error($exception);
            return JsonResponseAPI::clientErrorResponse($exception->getMessage());
        } catch (\Exception $exception) {
            Log::error($exception);
            return JsonResponseAPI::internalErrorResponse();
        }
    }



    /**
     * This handles te login logic of the Agent using Phone Number and Password/PIN
     *
     * @param UserLoanRequest $request
     * @return JsonResponse
     */
    public function unlinkDevice(UserLoanRequest $request): JsonResponse
    {
        try {

            $form          = $request->validated();
            $form['phone'] = "234" . (int)$form['phone'];
            $form          = array_change_key_case($form, CASE_LOWER);
            $agent         = $this->agent->retrieveLoginData('phone', $form['phone']);
            $record        = Helper::handleReturningUsers($agent);
            $imei = $form['imei'];
            $answer = $form['secreteanswer'];

            if (count($record)) return JsonResponseAPI::successResponse($record['message'], $record['data']);

            $this->mobileMFAService->unlinkDevice($agent, $imei, $answer);

            return JsonResponseAPI::successResponse("Device unlinked successfully.");
        } catch (MissingPermissionException $exception) {
            Log::error("Missing Permission: Unable to unlink device.", [$exception]);
            return JsonResponseAPI::clientErrorResponse($exception->getMessage());
        } catch (\Exception $exception) {
            Log::error("Unlink Device: Error occured while unlinking device.", [$exception]);
            return JsonResponseAPI::internalErrorResponse();
        }
    }

    /**
     * This enables the sending of OTPs to Agent's Phone and Email address
     * @param UserLoanRequest $request
     * @return JsonResponse
     */
    public function resendOTP(UserLoanRequest $request): JsonResponse
    {
        $form = $request->validated();
        $form['phone'] = "234" . (int)$form['phone'];

        try {

            $agent = $this->agent->retrieveProfileObject('phone', $form['phone']);
            $otp   = $this->agent->sendOTP($this->agent::find($agent->id));

            return JsonResponseAPI::successResponse("An otp has been sent to your phone");
        } catch (\Exception $exception) {
            Log::error($exception);

            return JsonResponseAPI::internalErrorResponse();
        }
    }

    /**
     * This validates an Agent's OTP
     * @param UserLoanRequest $request
     * @return JsonResponse
     */
    public function validateOTP(UserLoanRequest $request): JsonResponse
    {
        try {

            $response = $this->otp->validateOTP($request->validated()['code']);
            if (!$response)
                return JsonResponseAPI::errorResponse("The OTP entered does not exist.");

            if ($response === 'Expired')
                return JsonResponseAPI::errorResponse("The OTP entered has expired.");

            return JsonResponseAPI::successResponse("OTP is valid", $response);
        } catch (\Exception $exception) {
            Log::error($exception);

            return JsonResponseAPI::internalErrorResponse();
        }
    }

    /**
     * This sends an otp to the Agent, when a Password reset request is triggered
     * @param UserLoanRequest $request
     * @return JsonResponse
     */
    public function requestPassword(UserLoanRequest $request): JsonResponse
    {
        $form = $request->validated();
        $form['phone'] = $this->getPhoneNumberWithDialingCode($form['phone']);

        try {

            $agent = $this->agent->retrieveProfileObject('phone', $form['phone']);
            $otp   = $this->agent->sendOTP($this->agent::find($agent->id));

            return JsonResponseAPI::successResponse("An otp has been sent to your phone");
        } catch (\Exception $exception) {
            Log::error($exception);

            return JsonResponseAPI::internalErrorResponse();
        }
    }

    /**
     * This resets an Agent's password
     * @param UserLoanRequest $request
     * @return JsonResponse
     */
    public function resetPassword(UserLoanRequest $request): JsonResponse
    {
        $form = $request->validated();

        try {

            $this->agent->setPassword($form, true);

            return JsonResponseAPI::successResponse('Password reset is successful.');
        } catch (\Exception $exception) {
            Log::error($exception);

            return JsonResponseAPI::internalErrorResponse();
        }
    }

    /**
     * Register via the Mobile Platform
     *
     * @param AuthAgentMobileRequest $request
     * @return JsonResponse
     */
    public function registerThroughMobile(AuthAgentMobileRequest $request): JsonResponse
    {
        $form = $request->validated();
        $form['uuid'] = Str::uuid()->toString();
        try {
            # check by phone number
            $agent_by_phone = $this->agent->retrieveLoginData('phone', $this->getPhoneNumberWithDialingCode($form['phone']));
            $record = Helper::handleReturningUsers($agent_by_phone);
            if (count($record)) return JsonResponseAPI::successResponse($record['message'], $record['data']);
            # check by phone number
            $agent_by_email = $this->agent->retrieveLoginData('email', $form['email']);
            $record = Helper::handleReturningUsers($agent_by_email);
            if (count($record)) return JsonResponseAPI::successResponse($record['message'], $record['data']);
            if ($agent_by_phone) return JsonResponseAPI::errorResponse("Phone number already exists. Please login instead.");
            if ($agent_by_email) return JsonResponseAPI::errorResponse("Email address already exists. Please login instead.");

            # check that the Student is above the minimum age and below the maximum age configured on the System
            $age_config = AgeConfig::first();

            if ($age_config->is_enable && !Helper::isAgeAccepted($age_config->minimum, $age_config->maximum, $form['dob'])) {
                return JsonResponseAPI::errorResponse(
                    "Oops! We accept ages between $age_config->minimum and $age_config->maximum"
                );
            }

            # create the Agent profile
            $otp = $this->agent->createProfileThroughMobile($form);
            if (! $otp) return JsonResponseAPI::errorResponse("Your registration was not successful.");
            return JsonResponseAPI::successResponse("An otp has been sent to your phone.", $otp);
        } catch (\Exception $exception) {
            Log::error($exception);
            return JsonResponseAPI::internalErrorResponse();
        }
    }

    /**
     * @param AuthAgentMobileRequest $request
     * @return JsonResponse
     */
    public function loginViaMobile(AuthAgentMobileRequest $request): JsonResponse
    {
        $form = $request->validated();
        try {

            $agent = $this->agent->retrieveLoginData('uuid', $form['uuid']);

            if (($agent && $agent->deletedAccount) || !$agent) {
                return JsonResponseAPI::errorResponse(
                    "Account does not exist.",
                    JsonResponseAPI::$UNAUTHORIZED
                );
            }

            if (!$agent->pin)
                return JsonResponseAPI::errorResponse("Unable to login at this time, PIN not set.");

            if ((string)$agent->pin !== $form['pin'])
                return JsonResponseAPI::errorResponse("The PIN entered is invalid.");

            $data = OauthAccessToken::createAccessToken($this->agent::find($agent->id), $this->guard);
            $data['profile'] = new AgentResource($data['profile']);

            return JsonResponseAPI::successResponse('Login is successful.', $data);
        } catch (\Exception $exception) {
            Log::error($exception);

            return JsonResponseAPI::internalErrorResponse();
        }
    }

    /**
     * @param AuthAgentMobileRequest $request
     * @return JsonResponse
     */
    public function loginViaMobileMFAEnabled(AuthAgentMobileRequest $request): JsonResponse
    {

        $form = $request->validated();
        try {


            $agent = $this->agent->retrieveLoginData('uuid', $form['uuid']);

            if (($agent && $agent->deletedAccount) || !$agent) {
                return JsonResponseAPI::errorResponse(
                    "Account does not exist.",
                    JsonResponseAPI::$UNAUTHORIZED
                );
            }
            if (!$agent->pin)
                return JsonResponseAPI::errorResponse("Unable to login at this time, PIN not set.");

            if ((string)$agent->pin !== $form['pin'])
                return JsonResponseAPI::errorResponse("The PIN entered is invalid.");

            $this->mobileMFAService->processMobileMFA($agent, $form['imei']);

            $fcmToken = $form['fcmToken'] ?? null;
            if ($fcmToken) $this->notificationService->setFCMToken($agent, $fcmToken);


            $data = OauthAccessToken::createAccessToken($this->agent::find($agent->id), $this->guard);
            $data['profile'] = new AgentResource($data['profile']);

            return JsonResponseAPI::successResponse('Login is successful.', $data);
        } catch (MissingPermissionException $exception) {
            Log::error($exception);
            return JsonResponseAPI::clientErrorResponse($exception->getMessage());
        } catch (\Exception $exception) {
            Log::error($exception);
            return JsonResponseAPI::internalErrorResponse();
        }
    }

    /**
     * This is used to onboard Agent's Bio data through the Mobile platform
     * @param AuthAgentMobileRequest $request
     * @return JsonResponse
     */
    public function addBioData(AuthAgentMobileRequest $request): JsonResponse
    {
        $form = $request->validated();

        try {

            $user = $this->agent::find($form['agentId']);
            #finalize the registration
            $this->agent->setupProfiling($user, $form);

            return JsonResponseAPI::successResponse("Your profiling is successful.");
        } catch (\Exception $exception) {
            Log::error($exception);

            return JsonResponseAPI::internalErrorResponse();
        }
    }

    /**
     * Set Mobile Pin for transaction
     * @param AuthAgentMobileRequest $request
     * @return JsonResponse
     */
    public function setPin(AuthAgentMobileRequest $request): JsonResponse
    {
        try {

            $form = $request->validated();
            #update the profile Pin
            $this->agent->setUpMobilePin($form);

            return JsonResponseAPI::successResponse("Your Pin has been set successfully.");
        } catch (\Exception $exception) {
            Log::error($exception);

            return JsonResponseAPI::internalErrorResponse();
        }
    }
}
