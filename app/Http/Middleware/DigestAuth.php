<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DigestAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $username = config('api.digest_auth.username', env('DIGEST_AUTH_USERNAME', 'admin'));
        $password = config('api.digest_auth.password', env('DIGEST_AUTH_PASSWORD', 'password'));
        $realm = config('api.digest_auth.realm', env('DIGEST_AUTH_REALM', 'Restricted Area'));

        // Check if Authorization header is present
        $authHeader = $request->header('Authorization');
        
        if (!$authHeader || !$this->validateDigestAuth($request, $authHeader, $username, $password, $realm)) {
            return $this->sendDigestChallenge($realm);
        }

        return $next($request);
    }

    /**
     * Validate Digest Authentication
     */
    private function validateDigestAuth(Request $request, string $authHeader, string $username, string $password, string $realm): bool
    {
        if (!preg_match('/Digest\s+(.*)/i', $authHeader, $matches)) {
            return false;
        }

        $digestData = [];
        preg_match_all('/(\w+)="([^"]+)"/', $matches[1], $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $digestData[$match[1]] = $match[2];
        }

        // Required fields
        if (!isset($digestData['username'], $digestData['realm'], $digestData['nonce'], 
                   $digestData['uri'], $digestData['response'])) {
            return false;
        }

        // Check username and realm
        if ($digestData['username'] !== $username || $digestData['realm'] !== $realm) {
            return false;
        }

        // Calculate expected response
        $method = $request->method();
        $uri = $digestData['uri'];
        
        $ha1 = md5($username . ':' . $realm . ':' . $password);
        $ha2 = md5($method . ':' . $uri);
        
        // Handle qop (quality of protection)
        if (isset($digestData['qop']) && $digestData['qop'] === 'auth') {
            if (!isset($digestData['nc'], $digestData['cnonce'])) {
                return false;
            }
            $response = md5($ha1 . ':' . $digestData['nonce'] . ':' . 
                        $digestData['nc'] . ':' . 
                        $digestData['cnonce'] . ':' . 
                        'auth' . ':' . $ha2);
        } else {
            // No qop
            $response = md5($ha1 . ':' . $digestData['nonce'] . ':' . $ha2);
        }

        return hash_equals($digestData['response'], $response);
    }

    /**
     * Send Digest Authentication challenge
     */
    private function sendDigestChallenge(string $realm): Response
    {
        $nonce = uniqid();
        $opaque = md5($realm);

        $headers = [
            'WWW-Authenticate' => sprintf(
                'Digest realm="%s", qop="auth", nonce="%s", opaque="%s"',
                $realm,
                $nonce,
                $opaque
            ),
        ];

        return response('Unauthorized', 401, $headers);
    }
}
