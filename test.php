<?php
/**
 * Get the visitor's IP address in a reasonably safe way.
 * Checks common proxy headers (HTTP_X_FORWARDED_FOR, HTTP_CLIENT_IP)
 * and falls back to REMOTE_ADDR. Validates using FILTER_VALIDATE_IP.
 *
 * @return string|null Returns the IP string or null if none found/valid.
 */
function get_user_ip(): ?string {
    $ipSources = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR', // may contain comma-separated list
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];

    foreach ($ipSources as $key) {
        if (empty($_SERVER[$key])) {
            continue;
        }

        $ip = $_SERVER[$key];

        // If header contains a list (e.g. X-Forwarded-For), take the first one
        if (strpos($ip, ',') !== false) {
            $parts = array_map('trim', explode(',', $ip));
            $ip = $parts[0];
        }

        // Validate IP (both IPv4 and IPv6)
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
            return $ip;
        }

        // If not a public IP, still accept a valid IP (without private/reserved filter)
        if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
            return $ip;
        }
    }

    return null;
}

/* Example usage: echo the IP back (HTML-escaped) */
$ip = get_user_ip();
if ($ip !== null) {
    echo 'Your IP address is: ' . htmlspecialchars($ip, ENT_QUOTES, 'UTF-8');
} else {
    echo 'Could not determine your IP address.';
}
?>
