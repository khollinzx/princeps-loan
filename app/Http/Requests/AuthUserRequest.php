<?php

namespace App\Http\Requests;


use App\Services\JsonResponseAPI;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;

class AuthUserRequest extends BaseFormRequest
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
            case "login":
                $validation = $this->authLogin();
                break;

            case "register":
                $validation = $this->auhtRegister();
                break;
        }

        return $validation;
    }

    /**
     * Handle the login section
     * @return array
     */
    public function auhtRegister(): array
    {
        return [
            'name' => 'required|string',
            'email' => 'required|email|string',
            'address' => 'required|string',
            'dob' => 'required|string|date_format:Y-m-d',
            'phone' => 'required|string',
            'password' => 'required|string'
        ];
    }

    /**
     * Handle the login section
     * @return array
     */
    public function authLogin(): array
    {
        return [
            'email' => 'required|email|string',
            'password' => 'required|string'
        ];
    }
}
