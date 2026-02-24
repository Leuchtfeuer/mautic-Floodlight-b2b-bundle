<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper;

use Symfony\Component\HttpFoundation\Request;

class ClientIpHelper
{
    /**
     * Get the real client IP address from the request, considering proxy headers.
     *
     * Headers are checked in order of precedence:
     * 1. CF-Connecting-IP (Cloudflare)
     * 2. True-Client-IP (Akamai, Cloudflare)
     * 3. Fastly-Client-IP (Fastly)
     * 4. X-Real-IP (nginx)
     * 5. Forwarded (RFC 7239)
     * 6. X-Forwarded-For (common proxy header)
     * 7. REMOTE_ADDR (fallback)
     *
     * For headers with multiple IPs, the first (original client) IP is used.
     */
    public function getClientIp(Request $request): ?string
    {
        // Check CF-Connecting-IP (Cloudflare)
        $ip = $this->getFirstIpFromHeader($request, 'CF-Connecting-IP');
        if (null !== $ip) {
            return $ip;
        }

        // Check True-Client-IP (Akamai, Cloudflare)
        $ip = $this->getFirstIpFromHeader($request, 'True-Client-IP');
        if (null !== $ip) {
            return $ip;
        }

        // Check Fastly-Client-IP
        $ip = $this->getFirstIpFromHeader($request, 'Fastly-Client-IP');
        if (null !== $ip) {
            return $ip;
        }

        // Check X-Real-IP (nginx)
        $ip = $this->getFirstIpFromHeader($request, 'X-Real-IP');
        if (null !== $ip) {
            return $ip;
        }

        // Check Forwarded (RFC 7239) - structured format
        $ip = $this->parseForwardedHeader($request);
        if (null !== $ip) {
            return $ip;
        }

        // Check X-Forwarded-For (common proxy header)
        $ip = $this->getFirstIpFromHeader($request, 'X-Forwarded-For');
        if (null !== $ip) {
            return $ip;
        }

        // Fallback to REMOTE_ADDR
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

        return !empty($ips[0]) ? $ips[0] : null;
    }

    /**
     * Parse the Forwarded header (RFC 7239) to extract the client IP.
     *
     * Format: Forwarded: for=203.0.113.10; proto=https; by=...
     * or: Forwarded: for=203.0.113.10, for=198.51.100.5
     *
     * @return string|null The first client IP found, or null if none
     */
    private function parseForwardedHeader(Request $request): ?string
    {
        $headerValue = $request->headers->get('Forwarded');

        if (null === $headerValue || '' === $headerValue) {
            return null;
        }

        // Split by comma to handle multiple forwarded entries
        $entries = array_map('trim', explode(',', $headerValue));

        // Get the first entry (original client)
        $firstEntry = $entries[0] ?? '';

        // Parse the first entry to extract the "for" value
        // Pattern: for=<ip> or for="<ip>" or for=[<ipv6>]
        if (preg_match('/for=(?:"?\[?([^\]";,]+)\]?"?)/i', $firstEntry, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
