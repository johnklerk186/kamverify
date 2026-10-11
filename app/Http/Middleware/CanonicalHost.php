<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirects alternate host/scheme variants to the single canonical
 * https://kamverify.com — www/non-www and http→https.
 *
 * Why: customers hitting http:// or www. get a working site whose
 * Secure session cookie either can't persist (plain HTTP) or lives on
 * a different origin (www) — they appear logged out, sessions seem to
 * "expire", and CSRF/form posts break. Canonicalising removes a whole
 * class of flaky session reports.
 *
 * Safety: only redirects GET/HEAD (POST bodies don't survive redirects),
 * never fires on local/test hosts, and refuses to enforce when the
 * canonical host can't be determined safely (e.g. APP_URL still points
 * at an ephemeral tunnel hostname).
 */
class CanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $canonical = $this->canonicalHost();

        if ($canonical === null || !$request->isMethod('GET')) {
            return $next($request);
        }

        $host = strtolower($request->getHost());
        $wrongHost = $host !== $canonical;
        $insecure = !$request->secure();

        if (!$wrongHost && !$insecure) {
            return $next($request);
        }

        $target = 'https://' . $canonical . $request->getRequestUri();

        return redirect()->to($target, 301);
    }

    /**
     * Canonical host from APP_CANONICAL_HOST, falling back to APP_URL's
     * host — but never an ephemeral/dev hostname we can't safely force.
     */
    protected function canonicalHost(): ?string
    {
        $host = strtolower((string) (env('APP_CANONICAL_HOST')
            ?: parse_url((string) config('app.url'), PHP_URL_HOST)));

        if ($host === '') {
            return null;
        }

        foreach (['localhost', '127.0.0.1', '::1', 'trycloudflare.com', 'ngrok', '.test', '.local'] as $bad) {
            if ($host === $bad || str_ends_with($host, $bad)) {
                return null;
            }
        }

        return $host;
    }
}
