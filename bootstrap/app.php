<?php

use App\Adapters\Inbound\Http\Middleware\AuthenticateToken;
use App\Adapters\Inbound\Http\Middleware\RequireRole;
use App\Domain\Exception\DomainError;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.token' => AuthenticateToken::class,
            'role' => RequireRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // One error body for every error (RNF-4): code, message in Spanish, details; never traces, SQL or paths.
        $body = fn (string $code, string $message, array $details = []) => [
            'code' => $code,
            'message' => $message,
            'details' => $details,
        ];

        $exceptions->shouldRenderJsonWhen(fn (Request $request) => true);

        $exceptions->render(fn (DomainError $e) => response()->json(
            $body($e->errorCode, $e->getMessage(), $e->details),
            $e->httpStatus(),
        ));

        $exceptions->render(function (ValidationException $e) use ($body) {
            $details = [];
            foreach ($e->errors() as $field => $issues) {
                foreach ($issues as $issue) {
                    $details[] = ['field' => $field, 'issue' => $issue];
                }
            }

            return response()->json($body('VALIDATION_ERROR', 'Hay campos inválidos.', $details), 422);
        });

        $exceptions->render(function (HttpExceptionInterface $e) use ($body) {
            return $e->getStatusCode() === 404 || $e->getStatusCode() === 405
                ? response()->json($body('NOT_FOUND', 'El recurso no existe.'), 404)
                : response()->json($body('VALIDATION_ERROR', 'La petición no es válida.'), $e->getStatusCode());
        });

        $exceptions->render(fn (Throwable $e) => response()->json(
            $body('INTERNAL_ERROR', 'Ocurrió un error inesperado.'),
            500,
        ));
    })->create();
