<?php

namespace Risto\Api;

use Kirby\Http\Response;
use Throwable;

/**
 * Front-end login and logout.
 *
 * Deliberately thin: Kirby's own Auth already provides the parts that are
 * easy to get wrong, and reimplementing them would be a downgrade.
 *
 *   - rate limiting by IP *and* email (`auth.trials`, default 10;
 *     `auth.timeout`, default 3600s)
 *   - a random 10ms–2s sleep on every failure, to slow automated attempts
 *   - session token regeneration on login, against session fixation
 *
 * All three come from `$kirby->auth()->login()`. Do not route around it.
 */
final class Session
{
    /**
     * Issues a CSRF token.
     *
     * This exists as its own endpoint because `csrf()` starts a session, and
     * a session cookie makes every later response "private" — which disables
     * Kirby's page cache for that visitor entirely. Measured: embedding a
     * token in the home template drops it from cached to uncached outright.
     *
     * Fetching the token only when the login dialog opens keeps the homepage
     * cacheable for everyone who never tries to log in. See docs/PLAN.md §1.1.
     */
    public static function token(): Response
    {
        return Response::json(
            ['token' => csrf()],
            200,
            false,
            ['Cache-Control' => 'no-store, private'],
        );
    }

    /**
     * Logs a user in.
     */
    public static function create(): Response
    {
        $kirby   = kirby();
        $request = $kirby->request();

        if ($guard = Respond::guardCsrf()) {
            return $guard;
        }

        $email    = (string) $request->get('email', '');
        $password = (string) $request->get('password', '');

        if ($email === '' || $password === '') {
            return Respond::fail(
                code: 'missing-credentials',
                message: 'Please enter an email address and password.',
                status: 400,
            );
        }

        try {
            $user = $kirby->auth()->login($email, $password);
        } catch (Throwable) {
            // The thrown exception cannot be inspected for the reason: with
            // debug off, Auth::validatePassword() deliberately discards the
            // specific error and rethrows a generic PermissionException "to
            // avoid leaking security-relevant information". The rate-limited
            // detail therefore only ever survives in debug mode, so asking
            // Auth directly is the only thing that works in production.
            try {
                $rateLimited = $kirby->auth()->isBlocked($email);
            } catch (Throwable) {
                $rateLimited = false;
            }

            return Respond::fail(
                code: $rateLimited ? 'rate-limited' : 'invalid-credentials',
                message: $rateLimited
                    ? 'Too many attempts. Please wait a while before trying again.'
                    : 'Those details were not recognised.',
                status: $rateLimited ? 429 : 401,
            );
        }

        return Respond::ok([
            'user' => [
                'name'  => $user->name()->value() ?? $user->email(),
                'email' => $user->email(),
            ],
        ]);
    }

    /**
     * Logs the current user out.
     */
    public static function destroy(): Response
    {
        $kirby = kirby();

        // No CSRF guard when nobody is logged in: there is nothing to protect,
        // and failing here would just be noise.
        if ($kirby->user() === null) {
            return Respond::ok();
        }

        if ($guard = Respond::guardCsrf(redirect: site()->homePage()->url())) {
            return $guard;
        }

        $kirby->auth()->logout();

        return Respond::ok();
    }
}
