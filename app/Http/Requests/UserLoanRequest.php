<?php

namespace App\Http\Requests;


use App\Services\JsonResponseAPI;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;

class UserLoanRequest extends BaseFormRequest
{

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

            case "repay":
                $validation = $this->repayLoan();
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
            'name' => 'required|string',
            'address' => 'required|string',
            'dob' => 'required|string|date_format:Y-m-d',
            'income' => 'required|numeric',
            'loan_amount' => 'required|numeric'
        ];
    }

    /**
     * Handle the login section
     * @return array
     */
    public function repayLoan(): array
    {
        return [
            'card_number' => 'required|string',
            'cvv' => 'required|string',
            'expire_month' => 'required|string',
            'expire_year' => 'required|string',
            'loan_amount' => 'required|numeric'
        ];
    }
}
