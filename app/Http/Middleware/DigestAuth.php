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

        // Remove quotes from realm if present
        $realm = trim($realm, '"');

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
            \Log::debug('DigestAuth: No Digest header found');
            return false;
        }

        $digestData = [];
        // Parse digest parameters - handle both quoted and unquoted values
        preg_match_all('/(\w+)=(?:"([^"]+)"|([^,\s]+))/', $matches[1], $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $key = $match[1];
            $value = !empty($match[2]) ? $match[2] : $match[3];
            $digestData[$key] = $value;
        }

        \Log::debug('DigestAuth: Parsed data', $digestData);

        // Required fields
        if (!isset($digestData['username'], $digestData['realm'], $digestData['nonce'], 
                   $digestData['uri'], $digestData['response'])) {
            \Log::debug('DigestAuth: Missing required fields', ['digestData' => $digestData]);
            return false;
        }

        // Check username and realm
        if ($digestData['username'] !== $username) {
            \Log::debug('DigestAuth: Username mismatch', ['expected' => $username, 'got' => $digestData['username']]);
            return false;
        }
        
        if ($digestData['realm'] !== $realm) {
            \Log::debug('DigestAuth: Realm mismatch', ['expected' => $realm, 'got' => $digestData['realm']]);
            return false;
        }

        // Calculate expected response
        $method = $request->method();
        // Use URI exactly as provided in digest (RFC 2617)
        $uri = $digestData['uri'];
        
        $ha1 = md5($username . ':' . $realm . ':' . $password);
        $ha2 = md5($method . ':' . $uri);
        
        // Handle qop (quality of protection)
        if (isset($digestData['qop']) && $digestData['qop'] === 'auth') {
            if (!isset($digestData['nc'], $digestData['cnonce'])) {
                \Log::debug('DigestAuth: Missing nc or cnonce for qop=auth');
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

        $isValid = hash_equals($digestData['response'], $response);
        
        if (!$isValid) {
            \Log::debug('DigestAuth: Response mismatch', [
                'expected' => $response,
                'got' => $digestData['response'],
                'ha1' => $ha1,
                'ha2' => $ha2,
                'uri' => $uri,
                'method' => $method
            ]);
        }

        return $isValid;
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
