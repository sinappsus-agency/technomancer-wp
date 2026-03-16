<?php

declare(strict_types=1);

namespace Sinappsus\N8nConnector\Security;

use Sinappsus\N8nConnector\Core\Settings;
use Sinappsus\N8nConnector\Flows\Logger;
use WP_Error;
use WP_REST_Request;

final class RequestAuthorizer
{
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function authorize(WP_REST_Request $request)
    {
        $settings = Settings::all();
        $token = (string) ($settings['api_token'] ?? '');
        $secret = (string) ($settings['signing_secret'] ?? '');
        $header = (string) $request->get_header('authorization');
        $origin = (string) $request->get_header('origin');
        $signature = (string) $request->get_header('x-sinappsus-signature');
        $trustedOrigins = isset($settings['trusted_origins']) && is_array($settings['trusted_origins']) ? $settings['trusted_origins'] : [];
        $body = $request->get_body();

        if ($header !== 'Bearer ' . $token) {
            return new WP_Error('snc_unauthorized', 'Invalid API token.', ['status' => 401]);
        }

        if (! empty($trustedOrigins) && $origin !== '' && ! in_array($origin, $trustedOrigins, true)) {
            return new WP_Error('snc_forbidden_origin', 'Origin not approved.', ['status' => 403]);
        }

        if ($secret !== '') {
            $expected = hash_hmac('sha256', $body ?: '', $secret);
            if (! hash_equals($expected, $signature)) {
                $this->logger->log([
                    'event_key' => 'api.auth.failed',
                    'status' => 'security_failed',
                    'message' => ['reason' => 'signature_mismatch', 'origin' => $origin],
                ]);

                return new WP_Error('snc_invalid_signature', 'Invalid request signature.', ['status' => 401]);
            }
        }

        return true;
    }
}