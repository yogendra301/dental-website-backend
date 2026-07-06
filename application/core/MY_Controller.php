<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class MY_Controller extends CI_Controller {

    protected $clinicId = null;
    protected $clinicSlug = null;
    protected $role = null;
    protected $jwtSecret;

    public function __construct() {
        parent::__construct();
        $this->jwtSecret = getenv('JWT_SECRET') ?: 'dental_portal_fallback_secret_key_32chars!';

        // CORS: restrict to allowed origins from env, fallback to wildcard for dev
        $allowedOrigins = getenv('ALLOWED_ORIGINS');
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
        if ($allowedOrigins) {
            $origins = explode(',', $allowedOrigins);
            $allowed = in_array($origin, $origins) ? $origin : $origins[0];
            header('Access-Control-Allow-Origin: ' . $allowed);
        } else {
            header('Access-Control-Allow-Origin: *');
        }
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Super-Admin');
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
    }

    /**
     * Authenticate JWT token from Authorization header
     */
    protected function authenticate() {
        $token = $this->_getBearerToken();
        if (!$token) {
            $this->jsonResponse(['error' => 'Authentication required'], 401);
            $this->output->_display();
            exit;
        }

        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            $this->clinicId = $decoded->clinicId;
            $this->clinicSlug = isset($decoded->slug) ? $decoded->slug : (isset($decoded->username) ? $decoded->username : null);
            $this->role = $decoded->role;
        } catch (\Exception $e) {
            $this->jsonResponse(['error' => 'Invalid or expired token'], 401);
            $this->output->_display();
            exit;
        }
    }

    /**
     * Require specific roles (e.g., 'admin', 'doctor')
     */
    protected function requireRole(...$roles) {
        if (!$this->role || !in_array($this->role, $roles)) {
            $this->jsonResponse(['error' => 'Forbidden: Insufficient permissions'], 403);
            $this->output->_display();
            exit;
        }
    }

    /**
     * Normalize Indian phone number to +91 format
     */
    protected function normalizePhone($phone) {
        if (strpos($phone, '+91') === 0) {
            return $phone;
        }
        if (strpos($phone, '91') === 0 && strlen($phone) === 12) {
            return '+' . $phone;
        }
        return '+91' . $phone;
    }

    /**
     * Send JSON response
     */
    protected function jsonResponse($data, $statusCode = 200) {
        $this->output
            ->set_content_type('application/json')
            ->set_status_header($statusCode)
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Extract bearer token from Authorization header
     */
    private function _getBearerToken() {
        $headers = $this->input->request_headers();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

        if (empty($authHeader)) {
            // Also check $_SERVER for Apache/Nginx
            if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
                $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            }
        }

        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get JSON input from request body
     */
    protected function getJsonInput() {
        $rawInput = file_get_contents('php://input');
        return json_decode($rawInput, true) ?: [];
    }

    /**
     * Send email using CI3 Email library
     */
    protected function sendEmail($to, $subject, $body) {
        $this->load->library('email');
        $this->email->from(getenv('SMTP_FROM') ?: 'noreply@dentalportal.com', getenv('SMTP_FROM_NAME') ?: 'Dental Portal');
        $this->email->to($to);
        $this->email->subject($subject);
        $this->email->message($body);

        try {
            return $this->email->send();
        } catch (\Exception $e) {
            log_message('error', 'Email send error: ' . $e->getMessage());
            return false;
        }
    }

    /*
     * WhatsApp notification helpers — disabled for now.
     * Uncomment when WhatsApp sending is ready.
     *
    protected function notifyEvent($template, $clinic, $to, $params = []) {
        log_message('info', sprintf(
            '[WhatsApp Notify] template=%s clinic_id=%d to=%s params=%s',
            $template,
            is_array($clinic) ? $clinic['id'] : $clinic->id,
            $to,
            json_encode($params)
        ));
        return true;
    }

    protected function getWhatsappConfig($clinic) {
        $config = null;
        if (is_array($clinic)) {
            $config = isset($clinic['config']) ? $clinic['config'] : null;
        } elseif (is_object($clinic)) {
            $config = isset($clinic->config) ? $clinic->config : null;
        }
        if (is_string($config)) {
            $config = json_decode($config, true);
        }
        $wa = isset($config['whatsapp']) ? $config['whatsapp'] : [];
        return $wa;
    }

    protected function getWhatsappMeta($clinic) {
        $wa = $this->getWhatsappConfig($clinic);
        return [
            'number' => isset($wa['clinicNumber']) ? $wa['clinicNumber'] : null,
            'confirmationEnabled' => isset($wa['confirmation_enabled']) ? (bool)$wa['confirmation_enabled'] : false
        ];
    }
    */

    /**
     * Encrypt WhatsApp access token for storage
     */
    protected function encryptToken($plain) {
        $key = getenv('WHATSAPP_TOKEN_ENCRYPTION_KEY');
        if (empty($key)) {
            log_message('error', 'TODO: WHATSAPP_TOKEN_ENCRYPTION_KEY env not set — access_token stored without encryption');
            return $plain;
        }
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($plain, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . '::' . $encrypted);
    }

    /**
     * Decrypt WhatsApp access token from storage
     */
    protected function decryptToken($encoded) {
        $key = getenv('WHATSAPP_TOKEN_ENCRYPTION_KEY');
        if (empty($key)) {
            log_message('error', 'TODO: WHATSAPP_TOKEN_ENCRYPTION_KEY env not set — returning raw token');
            return $encoded;
        }
        $decoded = base64_decode($encoded);
        if (!$decoded || strpos($decoded, '::') === false) {
            return $encoded;
        }
        [$iv, $encrypted] = explode('::', $decoded, 2);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv) ?: $encoded;
    }
}
