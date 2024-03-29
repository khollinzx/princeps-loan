<?php

namespace App\Http\Requests;


use App\Http\Controllers\Controller;
use App\Models\UserLoan;
use App\Services\JsonResponseAPI;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;

class UserLoanRequest extends BaseFormRequest
{

    public function __construct(protected Controller $controller)
    {
        parent::__construct();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        # validate header
        if (!$this->hasHeader('Content-Type') || $this->header('Content-Type') !== 'application/json')
            throw new HttpResponseException(JsonResponseAPI::errorResponse('Include Content-Type and set the value to: application/json in your header.', Response::HTTP_BAD_REQUEST));

        $validation = [];

        switch (basename($this->url())) {
            case "apply":
                $validation = $this->applyLoan();
                break;
        }

        return $validation;
    }

    /**
     * Handle the login section
     * @return array
     */
    public function applyLoan(): array
    {
        return [
            'income' => 'required|numeric',
            'loan_amount' => [
                'required',
                'numeric',
                function ($key, $value, $next) {
                    $loan = UserLoan::repo()->findSingleByWhereClause(['user_id' => $this->controller->getUserId(), 'is_fully_paid' => 0]);
                    if($loan) $next("Sorry, you are yet to pay up your current loan.");
                }
            ]
        ];
    }
}
