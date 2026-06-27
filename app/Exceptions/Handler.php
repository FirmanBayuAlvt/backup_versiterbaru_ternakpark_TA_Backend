<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function render($request, Throwable $exception)
    {
        // Jika request mengharapkan response JSON (AJAX/API) atau route diawali dengan 'api'
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->renderJsonResponse($request, $exception);
        }

        // Untuk request web biasa, lanjutkan dengan parent render
        return parent::render($request, $exception);
    }

    /**
     * Render exception as JSON response for API requests.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Illuminate\Http\JsonResponse
     */
    protected function renderJsonResponse(Request $request, Throwable $exception): JsonResponse
    {
        // 1. AuthenticationException (token tidak valid / tidak login)
        if ($exception instanceof AuthenticationException) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Silakan login kembali.',
                'code'    => 401,
            ], 401);
        }

        // 2. ModelNotFoundException (data tidak ditemukan)
        if ($exception instanceof ModelNotFoundException) {
            $modelName = class_basename($exception->getModel());
            return response()->json([
                'success' => false,
                'message' => "Data {$modelName} tidak ditemukan.",
                'code'    => 404,
            ], 404);
        }

        // 3. NotFoundHttpException (route tidak ditemukan)
        if ($exception instanceof NotFoundHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint tidak ditemukan.',
                'code'    => 404,
            ], 404);
        }

        // 4. ValidationException (validasi gagal)
        if ($exception instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'message' => 'Data yang dikirim tidak valid.',
                'errors'  => $exception->errors(),
                'code'    => 422,
            ], 422);
        }

        // 5. Exception lainnya (termasuk server error)
        $statusCode = method_exists($exception, 'getStatusCode')
            ? $exception->getStatusCode()
            : (in_array($exception->getCode(), [400, 401, 403, 404, 422, 429, 500]) ? $exception->getCode() : 500);

        $message = config('app.debug')
            ? $exception->getMessage()
            : 'Terjadi kesalahan pada server. Silakan coba lagi nanti.';

        $responseData = [
            'success'   => false,
            'message'   => $message,
            'code'      => $statusCode,
        ];

        // Tambahkan trace hanya jika APP_DEBUG true
        if (config('app.debug')) {
            $responseData['trace'] = $exception->getTraceAsString();
            $responseData['file']  = $exception->getFile();
            $responseData['line']  = $exception->getLine();
        }

        return response()->json($responseData, $statusCode);
    }
}