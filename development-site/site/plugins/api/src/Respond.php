<?php

namespace Risto\Api;

use Kirby\Http\Response;

/**
 * Content negotiation for the /_api/ routes.
 *
 * Every endpoint answers both a `fetch()` call and an ordinary form post, so
 * the whole interface keeps working with JavaScript disabled. The only
 * difference is the shape of the reply: JSON for the former, a redirect for
 * the latter.
 */
final class Respond
{
    /**
     * Does the caller want JSON back?
     *
     * Checks the Accept header rather than X-Requested-With, which is a
     * jQuery-era convention that `fetch()` does not send.
     */
    public static function wantsJson(): bool
    {
        $accept = kirby()->request()->header('Accept') ?? '';

        return str_contains($accept, 'application/json');
    }

    /**
     * A successful reply.
     *
     * @param array<string,mixed> $data
     */
    public static function ok(array $data = [], string|null $redirect = null): Response
    {
        if (self::wantsJson() === true) {
            return Response::json(['ok' => true] + $data, 200);
        }

        return Response::redirect($redirect ?? site()->homePage()->url(), 302);
    }

    /**
     * A failed reply.
     *
     * For form posts the error travels as a query parameter rather than a
     * session flash: it keeps the endpoints stateless, and avoids starting a
     * session just to report that a password was wrong.
     */
    public static function fail(
        string $code,
        string $message,
        int $status = 400,
        string|null $redirect = null,
    ): Response {
        if (self::wantsJson() === true) {
            return Response::json([
                'ok'      => false,
                'error'   => $code,
                'message' => $message,
            ], $status);
        }

        $url = $redirect ?? url('login');

        return Response::redirect($url . '?error=' . urlencode($code), 302);
    }

    /**
     * Guards a mutating request against CSRF.
     *
     * Returns null when the request is fine, or the response to send back.
     */
    public static function guardCsrf(string|null $redirect = null): Response|null
    {
        $token = kirby()->request()->get('csrf');

        if (is_string($token) === false || csrf($token) !== true) {
            return self::fail(
                code: 'invalid-token',
                message: 'Your session expired. Please try again.',
                status: 403,
                redirect: $redirect,
            );
        }

        return null;
    }
}
