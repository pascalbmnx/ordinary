<?php
/**
 * Standard JSON response helper.
 */
final class Response
{
    public static function json(int $status, bool $success, string $message = '', $data = null, array $errors = [], array $pagination = [])
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        $body = [
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ];
        if (!empty($errors)) {
            $body['errors'] = $errors;
        }
        if (!empty($pagination)) {
            $body['pagination'] = $pagination;
        }
        echo json_encode($body);
        exit;
    }

    public static function success($data = null, string $message = 'OK', array $pagination = [])
    {
        self::json(200, true, $message, $data, [], $pagination);
    }

    public static function created($data = null, string $message = 'Created')
    {
        self::json(201, true, $message, $data);
    }

    public static function noContent()
    {
        http_response_code(204);
        exit;
    }

    public static function error(int $status, string $message, array $errors = [])
    {
        self::json($status, false, $message, null, $errors);
    }

    public static function badRequest(string $message = 'Invalid request', array $errors = [])
    {
        self::error(400, $message, $errors);
    }

    public static function unauthorized(string $message = 'Unauthorized')
    {
        self::error(401, $message);
    }

    public static function forbidden(string $message = 'Forbidden')
    {
        self::error(403, $message);
    }

    public static function notFound(string $message = 'Not found')
    {
        self::error(404, $message);
    }

    public static function conflict(string $message = 'Conflict')
    {
        self::error(409, $message);
    }

    public static function tooManyRequests(string $message = 'Too many requests')
    {
        self::error(429, $message);
    }

    public static function serverError(string $message = 'Something went wrong. Please try again.')
    {
        self::error(500, $message);
    }
}
