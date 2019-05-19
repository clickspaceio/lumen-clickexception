<?php

namespace Clickspace\ClickException;

use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ClickHandler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        if (!env('APP_DEBUG') && app()->bound('sentry') && $this->shouldReport($exception)) {
            app('sentry')->captureException($exception);
        }

        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function render($request, Exception $exception)
    {
//        return parent::render($request, $exception);
        $rendered = parent::render($request, $exception);

        $category = 'server_error';
        $code = 'server_error';
        $message = 'There was an internal processing error.';
        $responseCode = $rendered->getStatusCode();

        if ($exception instanceof CustomClickException) {
            $category = $exception->category;
            $code = $exception->code;
            $message = $exception->message;
            $responseCode = $exception->httpCode;
        } elseif ($exception instanceof ModelNotFoundException) {
            $category = 'not_found';
            $code = 'resource_not_found';
            $message = 'The requested resource (' . str_replace('App\Models\\', '', $exception->getModel()) . ') does not exist or has been deleted.';
        } elseif ($exception instanceof NotFoundHttpException) {
            $category = 'not_found';
            $code = 'endpoint_not_found';
            $message = 'The requested endpoint does not exist, please check our documentation.';
        } elseif ($exception instanceof ValidationException) {
            $category = 'invalid_request';
            $code = 'validation_error';
            $message = $exception->getMessage();
            $fields = $rendered->getOriginalContent();
        } elseif ($exception instanceof AuthorizationException) {
            $category = 'invalid_request';
            $code = 'invalid_credentials';
            $message = $exception->getMessage();
        }

        $responseBody = [
            'category' => $category,
            'code' => $code,
            'message' => $message,
        ];

        if (isset($fields)) {
            $responseBody['fields'] = $fields;
        }

        if (env('APP_DEBUG')) {
            $responseBody['_debug'] = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }

        return response()->json($responseBody, $responseCode);
    }
}