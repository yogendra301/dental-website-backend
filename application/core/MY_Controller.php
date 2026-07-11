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
            log_message('error', '[JWT] authenticate() failed: ' . $e->getMessage() . ' | Token prefix: ' . substr($token, 0, 20));
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

    /**
     * Send a WhatsApp message via Twilio.
     * Sends a free-form text message to the given number.
     * Twilio sandbox number is used when TWILIO_WHATSAPP_FROM is the sandbox number.
     *
     * @param string $to   Recipient number in E.164 format (e.g. +919876543210)
     * @param string $body Message text
     * @return bool
     */
    protected function sendTwilioWhatsApp($to, $body) {
        $sid   = getenv('TWILIO_ACCOUNT_SID');
        $token = getenv('TWILIO_AUTH_TOKEN');
        $from  = getenv('TWILIO_WHATSAPP_FROM') ?: 'whatsapp:+14155238886';

        if (empty($sid) || empty($token)) {
            log_message('error', '[Twilio] TWILIO_ACCOUNT_SID or TWILIO_AUTH_TOKEN not set in env');
            return false;
        }

        // Ensure number has whatsapp: prefix
        $toFormatted = (strpos($to, 'whatsapp:') === 0) ? $to : 'whatsapp:' . $to;

        $url  = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";
        $data = http_build_query([
            'From' => $from,
            'To'   => $toFormatted,
            'Body' => $body,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $data,
            CURLOPT_USERPWD        => "{$sid}:{$token}",
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            log_message('error', "[Twilio] cURL error sending WhatsApp to {$to}: {$curlErr}");
            return false;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            log_message('error', "[Twilio] HTTP {$httpCode} sending WhatsApp to {$to}: {$response}");
            return false;
        }

        log_message('info', "[Twilio] WhatsApp sent to {$to}, HTTP {$httpCode}");
        return true;
    }

    /**
     * Check if a specific WhatsApp notification is allowed for the clinic.
     * Checks global toggle and specific feature flag.
     *
     * @param  array|object $clinic
     * @param  string $template (e.g. 'booking_alert_owner', 'booking_confirmation_patient')
     * @return bool
     */
    protected function isWhatsappNotificationAllowed($clinic, $template) {
        $wa = $this->getWhatsappConfig($clinic);
        
        // 1. Is WhatsApp globally enabled for this clinic?
        // Note: Default to false if not set.
        $globalEnabled = isset($wa['enabled']) ? (bool)$wa['enabled'] : false;
        if (!$globalEnabled) {
            return false;
        }

        // 2. Map template key to feature key in config.whatsapp.features
        $featureKey = $template;
        if ($template === 'booking_alert_owner_emergency') {
            $featureKey = 'booking_alert_owner';
        }

        $features = isset($wa['features']) ? $wa['features'] : [];
        if (isset($features[$featureKey])) {
            $feat = $features[$featureKey];
            // If the template is locked or not enabled, don't send it.
            if (($feat['locked'] ?? true) || !($feat['enabled'] ?? false)) {
                return false;
            }
            return true;
        }

        // Fallbacks for confirmation if features list is empty or doesn't have it
        if ($template === 'booking_confirmation_patient') {
            return isset($wa['confirmation_enabled']) ? (bool)$wa['confirmation_enabled'] : false;
        }

        // For any templates not explicitly whitelisted as toggleable features, allow if global is enabled.
        return true;
    }

    /**
     * Fire a named notification event to a recipient.
     * Currently wired for booking owner alert.
     *
     * @param string $template Event key (e.g. 'booking_alert_owner')
     * @param array  $clinic   Clinic row array
     * @param string $to       Recipient phone (E.164)
     * @param array  $params   Template variables: [patientName, service, date, timeSlot, patientPhone]
     */
    protected function notifyEvent($template, $clinic, $to, $params = []) {
        if (empty($to)) {
            log_message('info', "[Twilio] notifyEvent skipped — no recipient number for template={$template}");
            return false;
        }

        // Check if this notification template/type is allowed (global toggles + template feature flags)
        if (!$this->isWhatsappNotificationAllowed($clinic, $template)) {
            log_message('info', "[Twilio] notifyEvent skipped — notification template={$template} not enabled or locked for this clinic");
            return false;
        }

        $clinicName = is_array($clinic) ? ($clinic['name'] ?? '') : '';

        switch ($template) {
            case 'booking_alert_owner':
            case 'booking_alert_owner_emergency':
                $patientName  = $params[0] ?? '';
                $service      = $params[1] ?? '';
                $date         = $params[2] ?? '';
                $timeSlot     = $params[3] ?? '';
                $patientPhone = $params[4] ?? '';
                $emergency    = ($template === 'booking_alert_owner_emergency') ? " 🚨 *EMERGENCY*" : '';
                $body = "📅 *New Booking Alert!*{$emergency}\n"
                      . "━━━━━━━━━━━━━━\n"
                      . "👤 *Patient:* {$patientName}\n"
                      . "📞 *Phone:* {$patientPhone}\n"
                      . "🦷 *Service:* {$service}\n"
                      . "📆 *Date:* {$date}\n"
                      . "⏰ *Time:* {$timeSlot}\n"
                      . "━━━━━━━━━━━━━━\n"
                      . "Clinic: {$clinicName}";
                break;

            case 'booking_confirmation_patient':
                $patientName    = $params[0] ?? '';
                $cName          = $params[1] ?? $clinicName;
                $date           = $params[2] ?? '';
                $timeSlot       = $params[3] ?? '';
                $clinicAddress  = $params[4] ?? '';
                $clinicPhone    = $params[5] ?? '';
                $body = "✅ *Appointment Confirmed!*\n"
                      . "━━━━━━━━━━━━━━\n"
                      . "Hello {$patientName},\n"
                      . "Your appointment at *{$cName}* is confirmed.\n"
                      . "📆 *Date:* {$date}\n"
                      . "⏰ *Time:* {$timeSlot}\n"
                      . "📍 *Address:* {$clinicAddress}\n"
                      . "📞 *Clinic:* {$clinicPhone}\n"
                      . "━━━━━━━━━━━━━━\n"
                      . "Please arrive 5 minutes early.";
                break;

            case 'booking_reschedule_patient':
                $patientName  = $params[0] ?? '';
                $cName        = $params[1] ?? $clinicName;
                $newDate      = $params[2] ?? '';
                $newTimeSlot  = $params[3] ?? '';
                $body = "📆 *Appointment Rescheduled!*\n"
                      . "━━━━━━━━━━━━━━\n"
                      . "Hello {$patientName},\n"
                      . "Your appointment at *{$cName}* has been rescheduled.\n"
                      . "📆 *New Date:* {$newDate}\n"
                      . "⏰ *New Time:* {$newTimeSlot}\n"
                      . "━━━━━━━━━━━━━━\n"
                      . "If this doesn't suit you, please contact us.";
                break;

            case 'booking_reschedule_owner':
                $patientName  = $params[0] ?? '';
                $service      = $params[1] ?? '';
                $oldDate      = $params[2] ?? '';
                $oldTimeSlot  = $params[3] ?? '';
                $newDate      = $params[4] ?? '';
                $newTimeSlot  = $params[5] ?? '';
                $patientPhone = $params[6] ?? '';
                $body = "🔄 *Booking Rescheduled Alert!*\n"
                      . "━━━━━━━━━━━━━━\n"
                      . "👤 *Patient:* {$patientName}\n"
                      . "📞 *Phone:* {$patientPhone}\n"
                      . "🦷 *Service:* {$service}\n"
                      . "🔴 *Was:* {$oldDate} @ {$oldTimeSlot}\n"
                      . "🟢 *Now:* {$newDate} @ {$newTimeSlot}\n"
                      . "━━━━━━━━━━━━━━\n"
                      . "Clinic: {$clinicName}";
                break;

            case 'booking_cancel_owner':
                $patientName  = $params[0] ?? '';
                $service      = $params[1] ?? '';
                $date         = $params[2] ?? '';
                $timeSlot     = $params[3] ?? '';
                $patientPhone = $params[4] ?? '';
                $body = "❌ *Booking Cancelled Alert!*\n"
                      . "━━━━━━━━━━━━━━\n"
                      . "👤 *Patient:* {$patientName}\n"
                      . "📞 *Phone:* {$patientPhone}\n"
                      . "🦷 *Service:* {$service}\n"
                      . "📆 *Date:* {$date} @ {$timeSlot}\n"
                      . "━━━━━━━━━━━━━━\n"
                      . "Clinic: {$clinicName}";
                break;

            case 'booking_review_request':
                $patientName = $params[0] ?? '';
                $cName       = $params[1] ?? $clinicName;
                $revLink     = $params[2] ?? '';
                $body = "⭐ *Leave Us a Review!*\n"
                      . "━━━━━━━━━━━━━━\n"
                      . "Hello {$patientName},\n"
                      . "Thank you for visiting *{$cName}*.\n"
                      . "We hope you had a great experience! Please take 1 minute to share your feedback with us on Google:\n"
                      . "🔗 {$revLink}\n"
                      . "━━━━━━━━━━━━━━\n"
                      . "Thank you for choosing us!";
                break;

            default:
                log_message('info', "[Twilio] Unknown template: {$template}");
                return false;
        }

        return $this->sendTwilioWhatsApp($to, $body);
    }

    /**
     * Extract the clinic's WhatsApp config block.
     *
     * @param  array|object $clinic Clinic row
     * @return array  WhatsApp config sub-array
     */
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
        return isset($config['whatsapp']) ? $config['whatsapp'] : [];
    }

    /**
     * Get the clinic WhatsApp number and confirmation flag.
     *
     * @param  array $clinic
     * @return array ['number' => string|null, 'confirmationEnabled' => bool]
     */
    protected function getWhatsappMeta($clinic) {
        $wa = $this->getWhatsappConfig($clinic);
        return [
            'number'              => isset($wa['clinicNumber']) ? $wa['clinicNumber'] : null,
            'confirmationEnabled' => isset($wa['confirmation_enabled']) ? (bool)$wa['confirmation_enabled'] : false,
        ];
    }


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
