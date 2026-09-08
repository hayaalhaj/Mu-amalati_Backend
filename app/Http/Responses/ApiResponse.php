<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * Return a successful single-resource response.
     */
    protected function success(mixed $data = null, string $message = 'Success.', int $status = 200): JsonResponse
    {
        $payload = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }

    /**
     * Return a successful collection response with pagination metadata.
     */
    protected function successCollection(mixed $data, int $status = 200, ?array $meta = null): JsonResponse
    {
        $payload = [
            'success' => true,
            'data' => $data,
        ];

        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * Return a generic error response.
     */
    protected function error(string $message, int $status = 400, mixed $errors = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    /**
     * Return a validation error response.
     */
    protected function validationError(mixed $errors): JsonResponse
    {
        return $this->error('The given data was invalid.', 422, $errors);
    }

    /**
     * Return a "not found" error response.
     */
    protected function notFound(string $message = 'Resource not found.'): JsonResponse
    {
        return $this->error($message, 404);
    }
}
