<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper;

use Symfony\Component\HttpFoundation\Request;

class ClientIpHelper
{
    /**
     * Get the real client IP address from the request, considering proxy headers.
     *
     * Headers are checked in order of precedence:
     * 1. CF-Connecting-IP
     * 2. True-Client-IP
     * 3. Fastly-Client-IP
     * 4. X-Real-IP
     * 5. Forwarded
     * 6. X-Forwarded-For
     * 7. REMOTE_ADDR
     *
     * For headers with multiple IPs, the first (original client) IP is used.
     */
    public function getClientIp(Request $request): ?string
    {
        $ip = $this->getFirstIpFromHeader($request, 'CF-Connecting-IP');
        if (null !== $ip) {
            return $ip;
        }

        $ip = $this->getFirstIpFromHeader($request, 'True-Client-IP');
        if (null !== $ip) {
            return $ip;
        }

        $ip = $this->getFirstIpFromHeader($request, 'Fastly-Client-IP');
        if (null !== $ip) {
            return $ip;
        }

        $ip = $this->getFirstIpFromHeader($request, 'X-Real-IP');
        if (null !== $ip) {
            return $ip;
        }

        $ip = $this->parseForwardedHeader($request);
        if (null !== $ip) {
            return $ip;
        }

        $ip = $this->getFirstIpFromHeader($request, 'X-Forwarded-For');
        if (null !== $ip) {
            return $ip;
        }

        $remoteAddr = $request->server->get('REMOTE_ADDR');

        return is_string($remoteAddr) ? $remoteAddr : null;
    }

    /**
     * Get the first IP address from a header that may contain multiple IPs.
     *
     * @param string $headerName The name of the header to check
     *
     * @return string|null The first IP address found, or null if none
     */
    private function getFirstIpFromHeader(Request $request, string $headerName): ?string
    {
        $headerValue = $request->headers->get($headerName);

        if (null === $headerValue || '' === $headerValue) {
            return null;
        }

        // Split by comma and get the first IP (original client)
        $ips = array_map('trim', explode(',', $headerValue));
        $ip  = $ips[0] ?? null;

        if (null === $ip || '' === $ip) {
            return null;
        }

        if (false === filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        return $ip;
    }

    /**
     * Parse the Forwarded header to extract the client IP.
     */
    private function parseForwardedHeader(Request $request): ?string
    {
        $headerValue = $request->headers->get('Forwarded');

        if (null === $headerValue || '' === $headerValue) {
            return null;
        }

        $entries = array_map('trim', explode(',', $headerValue));

        // Get the first entry (original client)
        $firstEntry = $entries[0] ?? '';

        // Parse the first entry to extract the "for" value
        if (preg_match('/for=(?:"?\[?([^\]";,]+)\]?"?)/i', $firstEntry, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
