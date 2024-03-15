<?php


namespace App\Services;


use Illuminate\Http\JsonResponse;

class JsonResponseAPI
{
    /**
     * set the basic response code types
     * @var int
     */
    public static $SUCCESS                  = 200;
    public static $CREATED                  = 201;
    public static $ACCEPTED                 = 202;
    public static $NO_CONTENT               = 204;
    public static $BAD_REQUEST              = 400;
    public static $UNAUTHORIZED             = 401;
    public static $FORBIDDEN                = 403;
    public static $NOT_FOUND                = 404;
    public static $METHOD_NOT_ALLOW         = 405;
    public static $UNPROCESSABLE_ENTITY     = 422;
    public static $INTERNAL_SERVER_ERROR    = 500;
    public static $NOT_IMPLEMENTED          = 501;
    public static $ACCOUNT_NOT_VERIFIED     = 209;

    /**
     * Returns a successful response without a status code
     * @param string $message
     * @param null $data
     * @param int $statusCode
     * @return JsonResponse
     */
    public static function successResponse(string $message = 'Success', $data = null, int $statusCode = 200): JsonResponse
    {
        return response()->json(
            [
                'status' => true,
                'message' => $message,
                'data' => $data
            ],
            $statusCode
        );
    }

    /**
     * This returns an error message back to the client without a status code
     *
     * @param string $message
     * @param int $statusCode
     * @param string $header
     * @param array $headers
     * @param mixed ...$metas
     * @return JsonResponse
     */
    public static function errorResponse(
        string $message = 'Not found',
        int $statusCode = 200,
        string $header = 'Error',
        array $headers    = [],
        ...$metas
    ): JsonResponse
    {
        return response()->json(
            array_merge([
                'header'  => $header,
                'status'  => false,
                'message' => $message
            ], $metas),
            $statusCode,
            $headers
        );
    }

    /**
     *
     * @param string|null $message
     * @return JsonResponse
     */
    public static function internalErrorResponse(string $message = null): JsonResponse
    {
        return self::errorResponse($message ?? "A System error has occurred.", self::$INTERNAL_SERVER_ERROR);
    }

    /**
     *
     * @param string|null $message
     * @return JsonResponse
     */
    public static function clientErrorResponse(string $message = null): JsonResponse
    {
        return self::errorResponse($message ?? "Bad Request.", self::$BAD_REQUEST);
    }

    /**
     * @param string $message
     * @return JsonResponse
     */
    public static function noRecords(string $message = "There are no records at the moment."): JsonResponse
    {
        return self::errorResponse($message);
    }

    /**
     *
     * @param array $data
     * @param string $message
     * @return array
     */
    public static function handleWebSocketResponse(mixed $data, string $message = 'Success'): array
    {
        return [
            'status' => true,
            'message' => $message,
            'data' => $data
        ];
    }
}
