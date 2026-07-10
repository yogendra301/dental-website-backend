<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Admin extends MY_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('admin_model');
        $this->load->driver('cache');
    }

    // ============================================================
    // AUTH ENDPOINTS
    // ============================================================

    /**
     * POST /api/auth/login
     */
    public function login()
    {
        $input = $this->getJsonInput();
        $username = isset($input['username']) ? trim($input['username']) : '';
        $password = isset($input['password']) ? $input['password'] : '';
        $rememberMe = isset($input['rememberMe']) ? (bool) $input['rememberMe'] : false;

        if (empty($username) || empty($password)) {
            $this->jsonResponse(['error' => 'Username and password are required'], 400);
            return;
        }

        $clinic = $this->admin_model->getClinicByUsername($username);
        if (!$clinic || !password_verify($password, $clinic['admin_password_hash'])) {
            $this->jsonResponse(['error' => 'Invalid credentials'], 401);
            return;
        }

        $expiresIn = $rememberMe ? 30 * 24 * 3600 : 12 * 3600;
        $token = JWT::encode([
            'clinicId' => (int) $clinic['id'],
            'username' => $clinic['username'],
            'role' => 'admin',
            'iat' => time(),
            'exp' => time() + $expiresIn
        ], $this->jwtSecret, 'HS256');

        $this->jsonResponse([
            'token' => $token,
            'clinic' => [
                'id' => (int) $clinic['id'],
                'name' => $clinic['name'],
                'username' => $clinic['username']
            ]
        ]);
    }

    // ============================================================
    // SUPER ADMIN GUARD — single choke point
    // Accepts password from Authorization header OR request body.
    // Pattern: every super endpoint calls this instead of inline checks.
    // This ensures auth never breaks regardless of how JS sends the password.
    // ============================================================
    private function _requireSuperAdmin()
    {
        $expected = getenv('SUPER_ADMIN_PASSWORD');

        // 1. Check Authorization: Bearer header (used by config panel)
        $authHeader = $this->input->get_request_header('Authorization', TRUE);
        if ($authHeader) {
            $bearer = str_replace('Bearer ', '', $authHeader);
            if ($bearer === $expected)
                return true;
        }

        // 2. Fallback: check body field (used by list_clinics / super-login)
        $input = $this->getJsonInput();
        $bodyPwd = isset($input['superPassword']) ? $input['superPassword'] : '';
        if ($bodyPwd === $expected)
            return true;

        $this->jsonResponse(['error' => 'Unauthorized'], 401);
        $this->output->_display();
        return false;
    }

    /**
     * POST /api/auth/super-login
     * Super admin impersonates a clinic — returns real JWT
     */
    public function super_login()
    {
        if (!$this->_requireSuperAdmin())
            return;
        $input = $this->getJsonInput();
        $username = isset($input['username']) ? trim($input['username']) : '';

        $clinic = $this->admin_model->getClinicByUsername($username);
        if (!$clinic) {
            $this->jsonResponse(['error' => 'Clinic not found'], 404);
            return;
        }

        $token = JWT::encode([
            'clinicId' => (int) $clinic['id'],
            'username' => $clinic['username'],
            'role' => 'admin',
            'iat' => time(),
            'exp' => time() + (8 * 3600)
        ], $this->jwtSecret, 'HS256');

        $this->jsonResponse([
            'token' => $token,
            'clinic' => ['id' => (int) $clinic['id'], 'name' => $clinic['name'], 'username' => $clinic['username']]
        ]);
    }

    /**
     * POST /api/clinics
     * List all clinics — super admin only via POST body
     */
    public function list_clinics()
    {
        if (!$this->_requireSuperAdmin())
            return;
        $clinics = $this->admin_model->getAllClinics();
        $this->jsonResponse($clinics);
    }

    /**
     * POST /api/auth/doctor-login
     */
    public function doctor_login()
    {
        $input = $this->getJsonInput();
        $username = isset($input['username']) ? trim($input['username']) : '';
        $pin = isset($input['pin']) ? $input['pin'] : '';

        if (empty($username) || empty($pin)) {
            $this->jsonResponse(['error' => 'Username and PIN are required'], 400);
            return;
        }

        $clinic = $this->admin_model->getClinicByUsername($username);
        if (!$clinic) {
            $this->jsonResponse(['error' => 'Invalid PIN'], 401);
            return;
        }

        if (empty($clinic['doctor_pin_hash'])) {
            $this->jsonResponse(['error' => 'Doctor access not configured for this clinic'], 400);
            return;
        }

        if (!password_verify($pin, $clinic['doctor_pin_hash'])) {
            $this->jsonResponse(['error' => 'Invalid PIN'], 401);
            return;
        }

        $token = JWT::encode([
            'clinicId' => (int) $clinic['id'],
            'username' => $clinic['username'],
            'role' => 'doctor',
            'iat' => time(),
            'exp' => time() + 12 * 3600
        ], $this->jwtSecret, 'HS256');

        $this->jsonResponse([
            'token' => $token,
            'clinic' => [
                'id' => (int) $clinic['id'],
                'name' => $clinic['name'],
                'username' => $clinic['username']
            ]
        ]);
    }

    /**
     * POST /api/auth/forgot-password
     */
    public function forgot_password()
    {
        $input = $this->getJsonInput();
        $username = isset($input['username']) ? trim($input['username']) : '';

        if (empty($username)) {
            $this->jsonResponse(['error' => 'Username is required'], 400);
            return;
        }

        $clinic = $this->admin_model->getClinicByUsername($username, 'id, admin_email, name, config');
        if (!$clinic) {
            $this->jsonResponse(['error' => 'Clinic not found'], 404);
            return;
        }

        if (empty($clinic['admin_email'])) {
            $this->jsonResponse(['error' => 'No email address configured for this clinic. Contact your administrator.'], 400);
            return;
        }

        $otp = strval(mt_rand(100000, 999999));
        $expires = date('Y-m-d H:i:s', time() + 10 * 60);

        $this->admin_model->updateClinicOtp($clinic['id'], $otp, $expires);

        // Send OTP via email instead of WhatsApp
        $subject = 'Password Reset OTP - ' . $clinic['name'];
        $body = '<h2>Password Reset Request</h2>'
            . '<p>Your OTP for password reset is: <strong>' . $otp . '</strong></p>'
            . '<p>This OTP is valid for 10 minutes.</p>'
            . '<p>If you did not request this, please ignore this email.</p>';

        if (!empty($clinic['admin_email'])) {
            $this->sendEmail(
                $clinic['admin_email'],
                'Password Reset OTP',
                "Your OTP for password reset is: {$otp}\n\nValid for 10 minutes. Do not share this with anyone."
            );
        }
        $this->jsonResponse(['success' => true, 'message' => 'OTP sent to registered email']);
    }

    /**
     * POST /api/auth/reset-password
     */
    public function reset_password()
    {
        $input = $this->getJsonInput();
        $username = isset($input['username']) ? trim($input['username']) : '';
        $otp = isset($input['otp']) ? $input['otp'] : '';
        $newPassword = isset($input['newPassword']) ? $input['newPassword'] : '';

        if (empty($username) || empty($otp) || empty($newPassword)) {
            $this->jsonResponse(['error' => 'All fields are required'], 400);
            return;
        }

        $clinic = $this->admin_model->getClinicByUsername($username, 'id, reset_otp, reset_otp_expires');
        if (!$clinic) {
            $this->jsonResponse(['error' => 'Clinic not found'], 404);
            return;
        }

        if (empty($clinic['reset_otp']) || $clinic['reset_otp'] !== $otp) {
            $this->jsonResponse(['error' => 'Invalid OTP'], 400);
            return;
        }

        if (strtotime($clinic['reset_otp_expires']) < time()) {
            $this->jsonResponse(['error' => 'OTP has expired'], 400);
            return;
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $this->admin_model->updateClinic($clinic['id'], [
            'admin_password_hash' => $hash,
            'reset_otp' => null,
            'reset_otp_expires' => null
        ]);

        $this->jsonResponse(['success' => true, 'message' => 'Password updated successfully']);
    }

    /**
     * PATCH /api/auth/change-password
     */
    public function change_password()
    {
        $this->authenticate();

        $input = $this->getJsonInput();
        $currentPassword = isset($input['currentPassword']) ? $input['currentPassword'] : '';
        $newPassword = isset($input['newPassword']) ? $input['newPassword'] : '';

        if (empty($currentPassword) || empty($newPassword)) {
            $this->jsonResponse(['error' => 'currentPassword and newPassword are required'], 400);
            return;
        }

        $clinic = $this->admin_model->getClinicById($this->clinicId, 'admin_password_hash');
        if (!$clinic) {
            $this->jsonResponse(['error' => 'Clinic not found'], 404);
            return;
        }

        if (!password_verify($currentPassword, $clinic['admin_password_hash'])) {
            $this->jsonResponse(['error' => 'Incorrect current password'], 400);
            return;
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $this->admin_model->updateClinic($this->clinicId, ['admin_password_hash' => $hash]);

        $this->jsonResponse(['success' => true, 'message' => 'Password changed successfully']);
    }

    // ============================================================
    // CLINIC ENDPOINTS
    // ============================================================

    /**
     * GET /api/clinics/resolve
     */
    public function resolve_clinic()
    {
        $host = isset($_GET['host']) ? trim(explode(':', $_GET['host'])[0]) : 'localhost';
        $host = strtolower($host);

        // Dev fallback
        $isLocal = in_array($host, ['localhost', '127.0.0.1']) ||
            strpos($host, '192.168.') === 0 ||
            strpos($host, '10.') === 0 ||
            strpos($host, '172.') === 0 ||
            substr($host, -6) === '.local';

        try {
            $clinic = null;
            if ($isLocal) {
                $devUsername = getenv('DEV_CLINIC_USERNAME') ?: 'clinic_001';
                $clinic = $this->admin_model->getClinicByUsername($devUsername);
            } else {
                // Try custom domain
                $clinic = $this->admin_model->getClinicByCustomDomain($host);
                if (!$clinic) {
                    $username = explode('.', $host)[0];
                    $clinic = $this->admin_model->getClinicByUsername($username);
                }
            }

            if (!$clinic) {
                $this->jsonResponse(['error' => 'Clinic not found'], 404);
                return;
            }

            $this->jsonResponse($this->_sanitizeClinic($clinic));
        } catch (\Exception $e) {
            log_message('error', 'Resolve error: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * GET /api/clinics/:username
     */
    public function get_clinic($username)
    {
        $clinic = $this->admin_model->getClinicByUsername($username);
        if (!$clinic) {
            $this->jsonResponse(['error' => 'Clinic not found'], 404);
            return;
        }
        $this->jsonResponse($this->_sanitizeClinic($clinic));
    }

    /**
     * GET /api/clinics/settings
     */
    public function get_settings()
    {
        $this->authenticate();
        $this->requireRole('admin');

        $clinic = $this->admin_model->getClinicById($this->clinicId);
        if (!$clinic) {
            $this->jsonResponse(['error' => 'Clinic not found'], 404);
            return;
        }

        $config = $this->admin_model->parseJsonField($clinic['config']);
        
        $response = [
            'id' => $clinic['id'],
            'username' => $clinic['username'],
            'name' => $clinic['name'],
            'slug' => $clinic['username'],
            'custom_domain' => $clinic['custom_domain'],
            'admin_email' => $clinic['admin_email'],
            'package' => isset($clinic['package']) ? (int)$clinic['package'] : 3,
            'contact_phone' => $clinic['contact_phone'],
            'contact_address' => $clinic['contact_address'],
            'contact_map_url' => $clinic['contact_map_url'],
            'google_review_link' => $clinic['google_review_link'],
            'working_hours' => $this->admin_model->parseJsonField($clinic['working_hours']),
            'reviews' => $this->admin_model->parseJsonField($clinic['reviews']),
            'config' => $config
        ];

        $this->jsonResponse($response);
    }

    /**
     * PATCH /api/clinics/settings
     */
    public function update_settings()
    {
        $this->authenticate();
        $this->requireRole('admin');

        $input = $this->getJsonInput();

        $clinic = $this->admin_model->getClinicById($this->clinicId, 'config');
        if (!$clinic) {
            $this->jsonResponse(['error' => 'Clinic not found'], 404);
            return;
        }

        $currentConfig = $this->admin_model->parseJsonField($clinic['config']);

        // Validate Google Maps URL
        $mapEmbedUrl = isset($input['map_embed_url']) ? $input['map_embed_url'] : null;
        if ($mapEmbedUrl) {
            // Strip full iframe tag if user pastes it — accept src only
            if (strpos($mapEmbedUrl, '<iframe') !== false) {
                preg_match('/src=["\']([^"\']+)["\']/', $mapEmbedUrl, $m);
                $mapEmbedUrl = isset($m[1]) ? $m[1] : '';
            }

            // Validate URL pattern
            if (!preg_match('/(google\.com\/maps|maps\.app\.goo\.gl)/', $mapEmbedUrl)) {
                $this->jsonResponse(['error' => 'Invalid Google Maps URL format'], 400);
                return;
            }
        }

        // Update config fields
        if (isset($input['name']))
            $currentConfig['name'] = $input['name'];
        if (isset($input['google_review_link']))
            $currentConfig['google_review_link'] = $input['google_review_link'];

        // Map embed URL
        if (!isset($currentConfig['contact']))
            $currentConfig['contact'] = [];
        if ($mapEmbedUrl !== null) {
            $currentConfig['contact']['mapEmbedUrl'] = $mapEmbedUrl;
        }

        // WhatsApp settings merge
        if (isset($input['whatsapp'])) {
            $currentWa = isset($currentConfig['whatsapp']) ? $currentConfig['whatsapp'] : [];
            $incomingWa = $input['whatsapp'];

            $currentConfig['whatsapp'] = array_merge($currentWa, [
                'clinicNumber' => isset($incomingWa['clinicNumber']) ? $incomingWa['clinicNumber'] : ($currentWa['clinicNumber'] ?? null),
                'confirmation_enabled' => isset($incomingWa['confirmation_enabled']) ? $incomingWa['confirmation_enabled'] : ($currentWa['confirmation_enabled'] ?? false)
            ]);

            // Feature flags
            if (isset($incomingWa['features'])) {
                $currentFeatures = isset($currentWa['features']) ? $currentWa['features'] : [];
                foreach ($incomingWa['features'] as $key => $incoming) {
                    $current = isset($currentFeatures[$key]) ? $currentFeatures[$key] : ['locked' => true, 'enabled' => false];
                    if ($current['locked'] === false && isset($incoming['enabled'])) {
                        $current['enabled'] = $incoming['enabled'];
                        $current['custom_text'] = isset($incoming['custom_text']) ? $incoming['custom_text'] : ($current['custom_text'] ?? null);
                    }
                    $currentFeatures[$key] = $current;
                }
                $currentConfig['whatsapp']['features'] = $currentFeatures;
            }
        }

        $updateData = [
            'config' => json_encode($currentConfig)
        ];

        $fields = ['name', 'contact_phone', 'contact_address', 'google_review_link', 'admin_email'];
        foreach ($fields as $field) {
            if (isset($input[$field])) {
                $updateData[$field] = $input[$field];
            }
        }

        if ($mapEmbedUrl !== null) {
            $updateData['contact_map_url'] = $mapEmbedUrl;
        }

        if (isset($input['visibility_settings'])) {
            $updateData['visibility_settings'] = is_string($input['visibility_settings'])
                ? $input['visibility_settings']
                : json_encode($input['visibility_settings']);
        }

        if (isset($input['reviews'])) {
            $updateData['reviews'] = json_encode($input['reviews']);
        }

        $this->admin_model->updateClinic($this->clinicId, $updateData);
        $this->jsonResponse(['success' => true, 'message' => 'Settings updated successfully']);
    }

    public function get_clinic_full($id)
    {
        if (!$this->_requireSuperAdmin())
            return;
        $clinic = $this->admin_model->getClinicById((int) $id);
        if (!$clinic) {
            $this->jsonResponse(['error' => 'Clinic not found'], 404);
            return;
        }
        unset($clinic['admin_password_hash'], $clinic['reset_otp'], $clinic['reset_otp_expires'], $clinic['doctor_pin_hash']);
        if (isset($clinic['config']['whatsapp']['access_token'])) {
            $clinic['config']['whatsapp']['access_token'] = '***';
        }
        $this->jsonResponse($clinic);
    }

    public function update_clinic_full($id)
    {
        if (!$this->_requireSuperAdmin())
            return;
        $input = $this->getJsonInput();
        $clinic = $this->admin_model->getClinicById((int) $id, 'id, config');
        if (!$clinic) {
            $this->jsonResponse(['error' => 'Clinic not found'], 404);
            return;
        }

        $config = $this->admin_model->parseJsonField($clinic['config']);

        // Config JSON sub-keys — all future customizable fields go here
        $configKeys = [
            'theme',
            'hero',
            'doctors',
            'tagline',
            'logo',
            'google_review_link',
            'whatsapp',
            'contact',
            'super_admin_only'
        ];
        // Keys where incoming value fully replaces (arrays like doctors[], stats[], journeySteps[])
        $replaceKeys = ['doctors', 'reviews'];
        foreach ($configKeys as $key) {
            if (!array_key_exists($key, $input))
                continue;
            // Shallow merge for scalar-keyed objects (theme, hero top-level fields)
            // Full replace for indexed arrays so removed items don't ghost
            if (
                is_array($input[$key]) && isset($config[$key]) && is_array($config[$key])
                && !in_array($key, $replaceKeys) && array_keys($input[$key]) !== range(0, count($input[$key]) - 1)
            ) {
                $config[$key] = array_replace($config[$key], $input[$key]);
            } else {
                $config[$key] = $input[$key];
            }
        }

        // Flat column whitelist — never allow clinic_id / hashes through here
        $flatFields = [
            'name',
            'contact_phone',
            'contact_address',
            'contact_map_url',
            'admin_email',
            'slot_duration_min',
            'slot_mode',
            'custom_domain',
            'google_review_link'
        ];
        $updateData = ['config' => json_encode($config)];
        foreach ($flatFields as $field) {
            if (array_key_exists($field, $input)) {
                $updateData[$field] = $input[$field];
            }
        }

        // JSON array columns
        foreach (['services', 'working_hours', 'visibility_settings', 'reviews'] as $col) {
            if (array_key_exists($col, $input)) {
                $updateData[$col] = json_encode($input[$col]);
            }
        }

        // Password reset (optional)
        if (!empty($input['admin_password'])) {
            $updateData['admin_password_hash'] = password_hash($input['admin_password'], PASSWORD_BCRYPT);
        }

        $this->admin_model->updateClinic((int) $id, $updateData);
        $this->jsonResponse(['success' => true]);
    }

    /**
     * POST /api/super/clinics/create
     * Super admin creates a new clinic — clones config from clinic_001 and copies asset folders
     */
    public function create_clinic()
    {
        if (!$this->_requireSuperAdmin())
            return;
        $input = $this->getJsonInput();

        // Validate required fields
        $password = trim($input['admin_password'] ?? $input['password'] ?? ''); // accept both field names
        if (!$password) {
            $this->jsonResponse(['error' => 'password required'], 400);
            return;
        }

        // Auto-generate slug: clinic_002, clinic_003, etc.
        $row = $this->db->query("SELECT username FROM clinics WHERE username REGEXP '^clinic_[0-9]+$' ORDER BY CAST(SUBSTRING(username, 8) AS UNSIGNED) DESC LIMIT 1")->row_array();
        $nextNum = $row ? ((int) substr($row['username'], 7) + 1) : 2;
        $username = 'clinic_' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

        // If name not provided, use username
        $name = trim($input['name'] ?? '');
        if (!$name) {
            $name = $username;
        }

        $phone = $input['contact_phone'] ?? '+91XXXXXXXXXX';
        $address = $input['contact_address'] ?? 'Address not set';

        // Clone config template from clinic_001 by username, swap name/logo references
        $template = $this->admin_model->getClinicByUsername('clinic_001');
        $config = $template ? ($template['config'] ?? []) : [];

        // Replace clinic_001 asset paths with new clinic's username
        $configJson = str_replace('clinic_001', $username, json_encode($config));
        $config = json_decode($configJson, true);
        $config['name'] = $name;
        $config['tagline'] = $input['tagline'] ?? 'Quality dental care for your family';
        $config['logo'] = "/uploads/assets/{$username}/logo/logo.png";

        // Reset whatsapp to disconnected blank
        $config['whatsapp'] = [
            'connected' => 0,
            'features' => [],
            'access_token' => '',
            'clinicNumber' => $phone,
            'phone_number_id' => '',
            'business_account_id' => '',
            'confirmation_enabled' => false
        ];
        $config['super_admin_only'] = false;
        $config['google_review_link'] = '';

        $defaultVisibility = [
            'show_gallery' => true,
            'show_pricing' => true,
            'show_ratings' => true,
            'show_lead_form' => true,
            'show_stats_bar' => true,
            'show_whatsapp_fab' => false,
            'show_working_hours' => true,
            'show_doctor_section' => true,
            'show_google_review_btn' => false,
            'admin_show_dashboard' => true,
            'admin_show_history' => true,
            'admin_show_followups' => true,
            'admin_show_leads' => true,
            'admin_show_reports' => true,
            'admin_show_gallery' => true,
            'admin_show_clinic_config' => true
        ];

        $data = [
            'username' => $username,
            'name' => $name,
            'services' => $template ? json_encode($template['services']) : json_encode([]),
            'working_hours' => $template ? json_encode($template['working_hours']) : json_encode([]),
            'slot_duration_min' => $template ? (int) ($template['slot_duration_min'] ?? 30) : 30,
            'contact_phone' => $phone,
            'contact_address' => $address,
            'contact_map_url' => '',
            'admin_password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'admin_email' => $input['admin_email'] ?? null,
            'slot_mode' => $template ? ($template['slot_mode'] ?? 'fixed') : 'fixed',
            'config' => json_encode($config),
            'visibility_settings' => json_encode($defaultVisibility),
            'reviews' => json_encode([]),
        ];

        $newId = $this->admin_model->createClinic($data);
        if (!$newId) {
            $this->jsonResponse(['error' => 'DB insert failed'], 500);
            return;
        }

        // Copy shared asset folders from clinic_001 (logo, steps, services, video, hero)
        // Gallery/docs are clinic-specific — NOT copied
        $srcBase = FCPATH . 'uploads/assets/clinic_001';
        if (!is_dir($srcBase)) {
            $this->jsonResponse(['error' => 'Asset source directory missing — run deploy bootstrap: cp -r uploads/assets/clinic_001 uploads/assets/'], 500);
            return;
        }
        $dstBase = FCPATH . 'uploads/assets/' . $username;
        $copyDirs = ['logo', 'step', 'service', 'video', 'hero', 'doctor'];
        foreach ($copyDirs as $dir) {
            $src = $srcBase . '/' . $dir;
            $dst = $dstBase . '/' . $dir;
            if (is_dir($src)) {
                $this->_copyDir($src, $dst);
            }
        }
        // Always create gallery dir (empty, ready for uploads)
        foreach (['gallery'] as $dir) {
            $path = $dstBase . '/' . $dir;
            if (!is_dir($path))
                mkdir($path, 0755, true);
        }

        $this->jsonResponse(['success' => true, 'id' => $newId, 'username' => $username]);
    }

    private function _copyDir($src, $dst)
    {
        if (!is_dir($dst))
            mkdir($dst, 0755, true);
        foreach (scandir($src) as $file) {
            if ($file === '.' || $file === '..')
                continue;
            $s = $src . '/' . $file;
            $d = $dst . '/' . $file;
            is_dir($s) ? $this->_copyDir($s, $d) : copy($s, $d);
        }
    }

    /**
     * POST /api/clinics/:id/whatsapp/connect
     */
    public function whatsapp_connect($id)
    {
        $this->authenticate();
        $this->requireRole('admin');

        if ((int) $this->clinicId !== (int) $id) {
            $this->jsonResponse(['error' => 'Forbidden'], 403);
            return;
        }

        $input = $this->getJsonInput();
        $phoneNumberId = isset($input['phone_number_id']) ? $input['phone_number_id'] : '';
        $accessToken = isset($input['access_token']) ? $input['access_token'] : '';
        $businessAccountId = isset($input['business_account_id']) ? $input['business_account_id'] : '';

        if (empty($phoneNumberId) || empty($accessToken) || empty($businessAccountId)) {
            $this->jsonResponse(['error' => 'phone_number_id, access_token, business_account_id are required'], 400);
            return;
        }

        // Verify credentials against Meta
        $ch = curl_init("https://graph.facebook.com/v21.0/{$phoneNumberId}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$accessToken}"],
            CURLOPT_TIMEOUT => 10
        ]);
        $verifyRes = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $this->jsonResponse(['error' => 'Invalid WhatsApp credentials — verification failed'], 400);
            return;
        }

        $clinic = $this->admin_model->getClinicById($id, 'config');
        if (!$clinic) {
            $this->jsonResponse(['error' => 'Clinic not found'], 404);
            return;
        }

        $config = $this->admin_model->parseJsonField($clinic['config']);
        $config['whatsapp'] = array_merge(
            isset($config['whatsapp']) ? $config['whatsapp'] : [],
            [
                'phone_number_id' => $phoneNumberId,
                'access_token' => $this->encryptToken($accessToken),
                'business_account_id' => $businessAccountId,
                'connected' => true
            ]
        );

        $this->admin_model->updateClinicConfig($id, $config);
        $this->admin_model->updateClinicPhoneNumberId($id, $phoneNumberId);

        $this->jsonResponse(['success' => true, 'connected' => true]);
    }

    /**
     * POST /api/clinics/:id/whatsapp/disconnect
     */
    public function whatsapp_disconnect($id)
    {
        $this->authenticate();
        $this->requireRole('admin');

        if ((int) $this->clinicId !== (int) $id) {
            $this->jsonResponse(['error' => 'Forbidden'], 403);
            return;
        }

        $clinic = $this->admin_model->getClinicById($id, 'config');
        if (!$clinic) {
            $this->jsonResponse(['error' => 'Clinic not found'], 404);
            return;
        }

        $config = $this->admin_model->parseJsonField($clinic['config']);
        if (isset($config['whatsapp'])) {
            $config['whatsapp']['connected'] = false;
        }

        $this->admin_model->updateClinicConfig($id, $config);
        $this->admin_model->clearClinicPhoneNumberId($id);

        $this->jsonResponse(['success' => true, 'connected' => false]);
    }

    // ============================================================
    // APPOINTMENT ENDPOINTS
    // ============================================================

    /**
     * GET /api/appointments
     */
    public function get_appointments()
    {
        $this->authenticate();
        $this->requireRole('admin', 'doctor');

        $date = isset($_GET['date']) ? $_GET['date'] : null;
        $phone = isset($_GET['phone']) ? $_GET['phone'] : null;

        try {
            $appointments = $this->admin_model->getAppointments($this->clinicId, $date, $phone);
            $this->jsonResponse($appointments);
        } catch (\Exception $e) {
            log_message('error', 'Fetch appointments error: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * POST /api/appointments (Public booking)
     */
    public function create_appointment()
    {
        // Rate limit: 10 req/min per IP (booking spam protection)
        $ip = $_SERVER['REMOTE_ADDR'];
        $key = 'rl_create_apt_' . md5($ip);
        $hits = $this->cache->get($key) ?: 0;
        if ($hits > 10) {
            $this->jsonResponse(['error' => 'Too many requests'], 429);
            return;
        }
        $this->cache->save($key, $hits + 1, 60);

        $input = $this->getJsonInput();
        $clinicUsername = isset($input['clinic_username']) ? trim($input['clinic_username']) : '';
        $patientName = isset($input['patient_name']) ? trim($input['patient_name']) : '';
        $patientPhone = isset($input['patient_phone']) ? trim($input['patient_phone']) : '';
        $service = isset($input['service']) ? trim($input['service']) : '';
        $date = isset($input['date']) ? $input['date'] : '';
        $timeSlot = isset($input['time_slot']) ? $input['time_slot'] : '';
        $isEmergency = isset($input['is_emergency']) ? (int) $input['is_emergency'] : 0;
        $problemNote = isset($input['problem_note']) ? $input['problem_note'] : null;

        if (empty($clinicUsername) || empty($patientName) || empty($patientPhone) || empty($service) || empty($date) || empty($timeSlot)) {
            $this->jsonResponse(['error' => 'All fields are required'], 400);
            return;
        }

        try {
            $clinic = $this->admin_model->getClinicByUsername($clinicUsername, 'id, name, contact_phone, contact_address, config');
            if (!$clinic) {
                $this->jsonResponse(['error' => 'Clinic not found'], 404);
                return;
            }

            $normalizedPhone = $this->normalizePhone($patientPhone);

            // Duplicate booking check
            $dup = $this->admin_model->checkDuplicateBooking($clinic['id'], $normalizedPhone, $date);
            if ($dup) {
                $this->jsonResponse(['error' => 'You already have an appointment on this date'], 409);
                return;
            }

            // Check blocked slot
            $blocked = $this->admin_model->checkBlockedSlot($clinic['id'], $date, $timeSlot);
            if ($blocked) {
                $this->jsonResponse(['error' => 'This time slot is blocked'], 400);
                return;
            }

            // Check if slot already taken
            $taken = $this->admin_model->checkSlotTaken($clinic['id'], $date, $timeSlot);
            if ($taken) {
                $this->jsonResponse(['error' => 'This time slot is already booked'], 400);
                return;
            }

            // Insert appointment
            $apptId = $this->admin_model->createAppointment([
                'clinic_id' => $clinic['id'],
                'patient_name' => $patientName,
                'patient_phone' => $normalizedPhone,
                'service' => $service,
                'date' => $date,
                'time_slot' => $timeSlot,
                'status' => 'pending',
                'source' => 'online',
                'is_emergency' => $isEmergency,
                'problem_note' => $problemNote
            ]);
            // Send WhatsApp alerts via Twilio (non-blocking — failure won't fail the booking)
            try {
                $formattedDate = implode('/', array_reverse(explode('-', $date)));
                $alertTemplate = $isEmergency ? 'booking_alert_owner_emergency' : 'booking_alert_owner';
                $waMeta = $this->getWhatsappMeta($clinic);

                // Always notify the clinic owner on their registered number
                if (!empty($waMeta['number'])) {
                    $this->notifyEvent(
                        $alertTemplate,
                        $clinic,
                        $waMeta['number'],
                        [$patientName, $service, $formattedDate, $timeSlot, $normalizedPhone]
                    );
                }

                // Optionally send patient confirmation if clinic has enabled it
                if ($waMeta['confirmationEnabled']) {
                    $this->notifyEvent(
                        'booking_confirmation_patient',
                        $clinic,
                        $normalizedPhone,
                        [
                            $patientName,
                            $clinic['name'],
                            $formattedDate,
                            $timeSlot,
                            $clinic['contact_address'],
                            $clinic['contact_phone']
                        ]
                    );
                }
            } catch (\Exception $waEx) {
                log_message('error', 'WhatsApp notify error (non-fatal): ' . $waEx->getMessage());
            }

            $this->jsonResponse(['success' => true, 'appointmentId' => $apptId]);
        } catch (\Exception $e) {
            log_message('error', 'Booking submission error: ' . $e->getMessage());
            if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), '1062') !== false) {
                $this->jsonResponse(['error' => 'This time slot is already booked'], 409);
                return;
            }
            $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * GET /api/appointments/lookup (Public)
     */
    public function lookup_appointments()
    {
        // Rate limit: 20 req/min per IP
        $ip = $_SERVER['REMOTE_ADDR'];
        $key = 'rl_lookup_apt_' . md5($ip);
        $hits = $this->cache->get($key) ?: 0;
        if ($hits > 20) {
            $this->jsonResponse(['error' => 'Too many requests'], 429);
            return;
        }
        $this->cache->save($key, $hits + 1, 60);

        $clinicUsername = isset($_GET['clinic_username']) ? $_GET['clinic_username'] : '';
        $phone = isset($_GET['phone']) ? $_GET['phone'] : '';

        if (empty($clinicUsername) || empty($phone)) {
            $this->jsonResponse(['error' => 'clinic_username and phone are required'], 400);
            return;
        }

        $clinic = $this->admin_model->getClinicByUsername($clinicUsername, 'id');
        if (!$clinic) {
            $this->jsonResponse(['error' => 'Clinic not found'], 404);
            return;
        }

        try {
            $appointments = $this->admin_model->lookupAppointments($clinic['id'], $phone);
            $this->jsonResponse($appointments);
        } catch (\Exception $e) {
            log_message('error', 'Lookup appointments error: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * PATCH /api/appointments/:id/reschedule (Public)
     */
    public function reschedule_appointment($id)
    {
        $input = $this->getJsonInput();
        $clinicUsername = isset($input['clinic_username']) ? trim($input['clinic_username']) : '';
        $phone = isset($input['phone']) ? trim($input['phone']) : '';
        $newDate = isset($input['new_date']) ? $input['new_date'] : '';
        $newTimeSlot = isset($input['new_time_slot']) ? $input['new_time_slot'] : '';

        if (empty($clinicUsername) || empty($phone) || empty($newDate) || empty($newTimeSlot)) {
            $this->jsonResponse(['error' => 'All fields are required'], 400);
            return;
        }

        try {
            $clinic = $this->admin_model->getClinicByUsername($clinicUsername, 'id, name, config');
            if (!$clinic) {
                $this->jsonResponse(['error' => 'Clinic not found'], 404);
                return;
            }

            $normalizedPhone = $this->normalizePhone($phone);

            // Verify ownership
            $appt = $this->admin_model->getAppointmentById($id, $clinic['id']);
            if (!$appt || !in_array($appt['status'], ['pending', 'confirmed']) || $appt['patient_phone'] !== $normalizedPhone) {
                $this->jsonResponse(['error' => 'No upcoming appointments found for this number'], 404);
                return;
            }

            // Check blocked
            $blocked = $this->admin_model->checkBlockedSlot($clinic['id'], $newDate, $newTimeSlot);
            if ($blocked) {
                $this->jsonResponse(['error' => 'This time slot is blocked'], 400);
                return;
            }

            // Check taken (exclude this appt)
            $taken = $this->admin_model->checkSlotTaken($clinic['id'], $newDate, $newTimeSlot, $id);
            if ($taken) {
                $this->jsonResponse(['error' => 'This time slot is already booked'], 400);
                return;
            }

            // Update
            $this->admin_model->updateAppointment($id, $clinic['id'], [
                'date' => $newDate,
                'time_slot' => $newTimeSlot
            ]);
            // Send WhatsApp alerts via Twilio (non-blocking)
            try {
                $formattedNewDate = implode('/', array_reverse(explode('-', $newDate)));
                $formattedOldDate = date('d/m/Y', strtotime($appt['date']));
                $waMeta = $this->getWhatsappMeta($clinic);

                $this->notifyEvent('booking_reschedule_patient', $clinic, $normalizedPhone, [
                    $appt['patient_name'],
                    $clinic['name'],
                    $formattedNewDate,
                    $newTimeSlot
                ]);

                if (!empty($waMeta['number'])) {
                    $this->notifyEvent('booking_reschedule_owner', $clinic, $waMeta['number'], [
                        $appt['patient_name'],
                        $appt['service'],
                        $formattedOldDate,
                        $appt['time_slot'],
                        $formattedNewDate,
                        $newTimeSlot,
                        $normalizedPhone
                    ]);
                }
            } catch (\Exception $waEx) {
                log_message('error', 'Reschedule WhatsApp notify error (non-fatal): ' . $waEx->getMessage());
            }

            $this->jsonResponse(['success' => true, 'message' => 'Appointment rescheduled successfully']);
        } catch (\Exception $e) {
            log_message('error', 'Reschedule error: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * PATCH /api/appointments/:id/cancel (Public)
     */
    public function cancel_appointment($id)
    {
        $input = $this->getJsonInput();
        $clinicUsername = isset($input['clinic_username']) ? trim($input['clinic_username']) : '';
        $phone = isset($input['phone']) ? trim($input['phone']) : '';

        if (empty($clinicUsername) || empty($phone)) {
            $this->jsonResponse(['error' => 'clinic_username and phone are required'], 400);
            return;
        }

        try {
            $clinic = $this->admin_model->getClinicByUsername($clinicUsername, 'id, name, config');
            if (!$clinic) {
                $this->jsonResponse(['error' => 'Clinic not found'], 404);
                return;
            }

            $normalizedPhone = $this->normalizePhone($phone);

            $appt = $this->admin_model->getAppointmentById($id, $clinic['id']);
            if (!$appt || !in_array($appt['status'], ['pending', 'confirmed']) || $appt['patient_phone'] !== $normalizedPhone) {
                $this->jsonResponse(['error' => 'No upcoming appointments found for this number'], 404);
                return;
            }

            $this->admin_model->cancelAppointment($id, $clinic['id']);
            // Send WhatsApp alerts via Twilio (non-blocking)
            try {
                $formattedDate = date('d/m/Y', strtotime($appt['date']));
                $waMeta = $this->getWhatsappMeta($clinic);
                if (!empty($waMeta['number'])) {
                    $this->notifyEvent('booking_cancel_owner', $clinic, $waMeta['number'], [
                        $appt['patient_name'],
                        $appt['service'],
                        $formattedDate,
                        $appt['time_slot'],
                        $normalizedPhone
                    ]);
                }
            } catch (\Exception $waEx) {
                log_message('error', 'Cancel WhatsApp notify error (non-fatal): ' . $waEx->getMessage());
            }

            $this->jsonResponse(['success' => true, 'message' => 'Appointment cancelled successfully']);
        } catch (\Exception $e) {
            log_message('error', 'Cancel appointment error: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * POST /api/appointments/admin (Protected admin booking)
     */
    public function create_admin_appointment()
    {
        $this->authenticate();
        $this->requireRole('admin');

        $input = $this->getJsonInput();
        $patientName = isset($input['patient_name']) ? trim($input['patient_name']) : '';
        $patientPhone = isset($input['patient_phone']) ? trim($input['patient_phone']) : '';
        $service = isset($input['service']) ? trim($input['service']) : '';
        $date = isset($input['date']) ? $input['date'] : '';
        $timeSlot = isset($input['time_slot']) ? $input['time_slot'] : '';
        $isEmergency = isset($input['is_emergency']) ? (int) $input['is_emergency'] : 0;
        $problemNote = isset($input['problem_note']) ? $input['problem_note'] : null;
        $source = isset($input['source']) ? $input['source'] : 'phone';

        if (empty($patientName) || empty($patientPhone) || empty($service) || empty($date) || empty($timeSlot)) {
            $this->jsonResponse(['error' => 'All fields are required'], 400);
            return;
        }

        $apptSource = $source;
        $isWalkin = $apptSource === 'walkin';
        $timeSlotToUse = $timeSlot;

        try {
            if ($isWalkin) {
                // Walk-in: find nearest available slot
                $slotDurationMin = (int) ($this->admin_model->getClinicById($this->clinicId, 'slot_duration_min')['slot_duration_min'] ?? 30);
                $now = new \DateTime();
                $totalMin = (int) $now->format('H') * 60 + (int) $now->format('i');
                $rounded = round($totalMin / 5) * 5;
                $attempts = 0;
                $found = false;

                while ($attempts < 60) {
                    $rH = str_pad(floor($rounded / 60) % 24, 2, '0', STR_PAD_LEFT);
                    $rM = str_pad($rounded % 60, 2, '0', STR_PAD_LEFT);
                    $checkSlot = "{$rH}:{$rM}";

                    $taken = $this->admin_model->checkSlotTaken($this->clinicId, $date, $checkSlot);
                    if (!$taken) {
                        $timeSlotToUse = $checkSlot;
                        $found = true;
                        break;
                    }

                    $rounded += $slotDurationMin;
                    $attempts++;
                }

                if (!$found) {
                    $this->jsonResponse(['error' => 'No available slot found for walk-in'], 400);
                    return;
                }
            } else {
                // Check blocked
                $blocked = $this->admin_model->checkBlockedSlot($this->clinicId, $date, $timeSlot);
                if ($blocked) {
                    $this->jsonResponse(['error' => 'This time slot is blocked'], 400);
                    return;
                }

                // Check taken
                $taken = $this->admin_model->checkSlotTaken($this->clinicId, $date, $timeSlot);
                if ($taken) {
                    $this->jsonResponse(['error' => 'This time slot is already booked'], 400);
                    return;
                }
            }

            $normalizedPhone = $this->normalizePhone($patientPhone);

            $apptId = $this->admin_model->createAppointment([
                'clinic_id' => $this->clinicId,
                'patient_name' => $patientName,
                'patient_phone' => $normalizedPhone,
                'service' => $service,
                'date' => $date,
                'time_slot' => $timeSlotToUse,
                'status' => 'confirmed',
                'source' => $apptSource,
                'is_emergency' => $isEmergency,
                'problem_note' => $problemNote
            ]);
            // Send WhatsApp alerts via Twilio (non-blocking)
            try {
                $clinic = $this->admin_model->getClinicById($this->clinicId, 'name, contact_phone, contact_address, config');
                if ($clinic) {
                    $formattedDate = implode('/', array_reverse(explode('-', $date)));
                    $waMeta = $this->getWhatsappMeta($clinic);
                    $alertTemplate = $isEmergency ? 'booking_alert_owner_emergency' : 'booking_alert_owner';

                    if (!empty($waMeta['number'])) {
                        $this->notifyEvent($alertTemplate, $clinic, $waMeta['number'], [$patientName, $service, $formattedDate, $timeSlotToUse, $normalizedPhone]);
                    }

                    $this->notifyEvent('booking_confirmation_patient', $clinic, $normalizedPhone, [
                        $patientName,
                        $clinic['name'],
                        $formattedDate,
                        $timeSlotToUse,
                        $clinic['contact_address'],
                        $clinic['contact_phone']
                    ]);
                }
            } catch (\Exception $waEx) {
                log_message('error', 'Admin booking WhatsApp notify error (non-fatal): ' . $waEx->getMessage());
            }

            $this->jsonResponse(['success' => true, 'appointmentId' => $apptId]);
        } catch (\Exception $e) {
            log_message('error', 'Admin booking error: ' . $e->getMessage());
            if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), '1062') !== false) {
                $this->jsonResponse(['error' => 'This time slot is already booked'], 409);
                return;
            }
            $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * GET /api/appointments/history (Protected admin)
     */
    public function get_appointment_history()
    {
        $this->authenticate();
        $this->requireRole('admin');

        $start = isset($_GET['start']) ? $_GET['start'] : '';
        $end = isset($_GET['end']) ? $_GET['end'] : '';

        if (empty($start) || empty($end)) {
            $this->jsonResponse(['error' => 'start and end dates are required'], 400);
            return;
        }

        try {
            $history = $this->admin_model->getAppointmentHistory($this->clinicId, $start, $end);
            $this->jsonResponse($history);
        } catch (\Exception $e) {
            log_message('error', 'Fetch history error: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * GET /api/appointments/followups (Protected admin)
     */
    public function get_followups()
    {
        $this->authenticate();
        $this->requireRole('admin');
        $rows = $this->admin_model->getFollowups($this->clinicId);
        $this->jsonResponse($rows);
    }

    /**
     * PATCH /api/appointments/:id/followup-done (Protected admin)
     */
    public function followup_done($id)
    {
        $this->authenticate();
        $this->requireRole('admin');
        $ok = $this->admin_model->markFollowupDone($id, $this->clinicId);
        if (!$ok)
            return $this->jsonResponse(['error' => 'Not found'], 404);
        $this->jsonResponse(['success' => true]);
    }

    /**
     * PATCH /api/appointments/:id/no-show (Protected admin)
     */
    public function no_show_appointment($id)
    {
        $this->authenticate();
        $this->requireRole('admin');
        $ok = $this->admin_model->update_appointment_status($id, $this->clinicId, 'no_show');
        if (!$ok)
            return $this->jsonResponse(['error' => 'Not found'], 404);
        $this->jsonResponse(['success' => true]);
    }

    /**
     * DELETE /api/appointments/:id (Protected admin cancellation)
     */
    public function delete_appointment($id)
    {
        $this->authenticate();
        $this->requireRole('admin');

        try {
            $result = $this->admin_model->cancelAppointment($id, $this->clinicId);
            if (!$result) {
                $this->jsonResponse(['error' => 'Appointment not found or unauthorized'], 404);
                return;
            }
            $this->jsonResponse(['success' => true, 'message' => 'Appointment cancelled successfully']);
        } catch (\Exception $e) {
            log_message('error', 'Cancel appointment error: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * PATCH /api/appointments/:id (Protected admin update)
     */
    public function update_appointment($id)
    {
        $this->authenticate();
        $this->requireRole('admin');

        $input = $this->getJsonInput();
        $allowed = ['status', 'service', 'date', 'time_slot', 'problem_note', 'is_emergency', 'source', 'patient_name', 'patient_phone'];
        $data = array_intersect_key($input, array_flip($allowed));

        try {
            $result = $this->admin_model->updateAppointment($id, $this->clinicId, $data);
            if (!$result) {
                $this->jsonResponse(['error' => 'Appointment not found or unauthorized'], 404);
                return;
            }
            $this->jsonResponse(['success' => true, 'message' => 'Appointment updated successfully']);
        } catch (\Exception $e) {
            log_message('error', 'Update appointment error: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * PATCH /api/appointments/:id/complete (Protected admin complete visit)
     */
    public function complete_appointment($id)
    {
        $this->authenticate();
        $this->requireRole('admin');

        $input = $this->getJsonInput();
        $treatmentPerformed = isset($input['treatment_performed']) ? $input['treatment_performed'] : null;
        $doctorNotes = isset($input['doctor_notes']) ? $input['doctor_notes'] : null;
        $medicinesInstructions = isset($input['medicines_instructions']) ? $input['medicines_instructions'] : null;
        $followUpDate = isset($input['follow_up_date']) ? $input['follow_up_date'] : null;
        $followUpNote = isset($input['follow_up_note']) ? $input['follow_up_note'] : null;
        $treatmentCost = isset($input['treatment_cost']) ? (float) $input['treatment_cost'] : 0;
        $discount = isset($input['discount']) ? (float) $input['discount'] : 0;
        $amountPaid = isset($input['amount_paid']) ? (float) $input['amount_paid'] : 0;
        $paymentMethod = isset($input['payment_method']) ? $input['payment_method'] : null;

        // Compute payment status
        $due = $treatmentCost - $discount;
        $paymentStatus = 'unpaid';
        if ($amountPaid > 0) {
            $paymentStatus = ($amountPaid >= $due) ? 'paid' : 'partially_paid';
        }

        try {
            $result = $this->admin_model->completeAppointment($id, $this->clinicId, [
                'status' => 'completed',
                'treatment_performed' => $treatmentPerformed,
                'doctor_notes' => $doctorNotes,
                'medicines_instructions' => $medicinesInstructions,
                'follow_up_date' => $followUpDate,
                'follow_up_note' => $followUpNote,
                'treatment_cost' => $treatmentCost,
                'discount' => $discount,
                'amount_paid' => $amountPaid,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'follow_up_completed' => 0
            ]);

            if (!$result) {
                $this->jsonResponse(['error' => 'Appointment not found or unauthorized'], 404);
                return;
            }

            $this->jsonResponse([
                'success' => true,
                'message' => 'Visit marked as completed successfully',
                'payment_status' => $paymentStatus
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Complete visit error: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * POST /api/appointments/:id/request-review (Protected admin)
     */
    public function request_review($id)
    {
        $this->authenticate();
        $this->requireRole('admin');

        try {
            $appt = $this->admin_model->getAppointmentById($id, $this->clinicId);
            if (!$appt) {
                $this->jsonResponse(['error' => 'Appointment not found or unauthorized'], 404);
                return;
            }

            $clinic = $this->admin_model->getClinicById($this->clinicId, 'name, google_review_link, config');
            if (!$clinic) {
                $this->jsonResponse(['error' => 'Clinic not found'], 404);
                return;
            }

            $config = $this->admin_model->parseJsonField($clinic['config']);
            $reviewLink = !empty($clinic['google_review_link'])
                ? $clinic['google_review_link']
                : ($config['google_review_link'] ?? '');

            if (empty($reviewLink)) {
                $this->jsonResponse(['error' => 'Google review link not configured for this clinic'], 400);
                return;
            }
            // Send WhatsApp alerts via Twilio (non-blocking)
            try {
                $this->notifyEvent('booking_review_request', $clinic, $appt['patient_phone'], [
                    $appt['patient_name'],
                    $clinic['name'],
                    $reviewLink
                ]);
            } catch (\Exception $waEx) {
                log_message('error', 'Review request WhatsApp notify error (non-fatal): ' . $waEx->getMessage());
            }

            $this->jsonResponse(['success' => true, 'message' => 'Google review request sent successfully']);
        } catch (\Exception $e) {
            log_message('error', 'Request review error: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    // ============================================================
    // SLOT ENDPOINTS
    // ============================================================

    /**
     * GET /api/slots/available
     */
    public function get_available_slots()
    {
        $clinicUsername = isset($_GET['clinic_username']) ? $_GET['clinic_username'] : '';
        $date = isset($_GET['date']) ? $_GET['date'] : '';

        if (empty($clinicUsername) || empty($date)) {
            $this->jsonResponse(['error' => 'clinic_username and date are required'], 400);
            return;
        }

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->jsonResponse(['error' => 'date must be in YYYY-MM-DD format'], 400);
            return;
        }

        try {
            $clinic = $this->admin_model->getClinicByUsername($clinicUsername, 'id, working_hours, slot_duration_min, slot_mode, custom_slots');
            if (!$clinic) {
                $this->jsonResponse(['error' => 'Clinic not found'], 404);
                return;
            }

            $dayKeys = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
            $dayKey = $dayKeys[(int) date('w', strtotime($date))];

            $workingHours = $this->admin_model->parseJsonField($clinic['working_hours']);
            $hours = isset($workingHours[$dayKey]) ? $workingHours[$dayKey] : null;

            // Closed on this day
            if (!$hours || !isset($hours['open']) || !isset($hours['close'])) {
                $this->jsonResponse([]);
                return;
            }

            // Check if admin
            $isAdmin = false;
            $headers = $this->input->request_headers();
            $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
            if (!empty($authHeader) && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                try {
                    $decoded = JWT::decode($matches[1], new Key($this->jwtSecret, 'HS256'));
                    if ($decoded->role === 'admin' && $decoded->clinicId == $clinic['id']) {
                        $isAdmin = true;
                    }
                } catch (\Exception $e) {
                }
            }

            // Get appointments for the date
            $appointments = $this->admin_model->getAppointmentsForSlots($clinic['id'], $date);

            $apptMap = [];
            foreach ($appointments as $appt) {
                $status = $appt['status'] === 'completed' ? 'completed' : ($appt['source'] === 'phone' ? 'booked-phone' : 'booked-online');
                $apptMap[$appt['time_slot']] = [
                    'status' => $status,
                    'patient_name' => $appt['patient_name'],
                    'is_emergency' => (int) $appt['is_emergency']
                ];
            }

            // Get blocked slots
            $blockedSlots = $this->admin_model->getBlockedSlots($clinic['id'], $date);
            $blockedSet = [];
            foreach ($blockedSlots as $b) {
                $blockedSet[$b['time_slot']] = true;
            }

            $slots = [];

            if ($clinic['slot_mode'] === 'custom') {
                $customSlots = $this->admin_model->parseJsonField($clinic['custom_slots']);
                if (is_array($customSlots)) {
                    foreach ($customSlots as $slotOpt) {
                        $slotTime = isset($slotOpt['label']) ? $slotOpt['label'] : $slotOpt;
                        $status = 'available';
                        if (isset($blockedSet[$slotTime])) {
                            $status = 'blocked';
                        } elseif (isset($apptMap[$slotTime])) {
                            $status = $apptMap[$slotTime]['status'];
                        }

                        $slotObj = ['time_slot' => $slotTime, 'status' => $status];
                        if ($isAdmin && isset($apptMap[$slotTime])) {
                            $slotObj['patient_name'] = $apptMap[$slotTime]['patient_name'];
                            $slotObj['is_emergency'] = $apptMap[$slotTime]['is_emergency'];
                        }
                        $slots[] = $slotObj;
                    }
                }
            } else {
                // Generate slots from working hours
                $openTime = \DateTime::createFromFormat('H:i', $hours['open']);
                $closeTime = \DateTime::createFromFormat('H:i', $hours['close']);
                $duration = (int) $clinic['slot_duration_min'];

                if ($openTime && $closeTime) {
                    $current = clone $openTime;
                    while ($current < $closeTime) {
                        $slotTime = $current->format('H:i');
                        $status = 'available';
                        if (isset($blockedSet[$slotTime])) {
                            $status = 'blocked';
                        } elseif (isset($apptMap[$slotTime])) {
                            $status = $apptMap[$slotTime]['status'];
                        }

                        $slotObj = ['time_slot' => $slotTime, 'status' => $status];
                        if ($isAdmin && isset($apptMap[$slotTime])) {
                            $slotObj['patient_name'] = $apptMap[$slotTime]['patient_name'];
                            $slotObj['is_emergency'] = $apptMap[$slotTime]['is_emergency'];
                        }
                        $slots[] = $slotObj;

                        $current->modify("+{$duration} minutes");
                    }
                }
            }

            $this->jsonResponse($slots);
        } catch (\Exception $e) {
            log_message('error', 'Available slots error: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * POST /api/slots/block (Protected admin)
     */
    public function block_slot()
    {
        $this->authenticate();
        $this->requireRole('admin');

        $input = $this->getJsonInput();
        $date = isset($input['date']) ? $input['date'] : (isset($_GET['date']) ? $_GET['date'] : '');
        $timeSlot = isset($input['time_slot']) ? $input['time_slot'] : (isset($_GET['time_slot']) ? $_GET['time_slot'] : '');

        if (empty($date) || empty($timeSlot)) {
            $this->jsonResponse(['error' => 'date and time_slot are required'], 400);
            return;
        }

        try {
            // Check if already blocked
            $existing = $this->admin_model->checkBlockedSlot($this->clinicId, $date, $timeSlot);
            if ($existing) {
                $this->jsonResponse(['success' => true, 'message' => 'Slot already blocked']);
                return;
            }

            $this->admin_model->blockSlot($this->clinicId, $date, $timeSlot);
            $this->jsonResponse(['success' => true, 'message' => 'Slot blocked successfully']);
        } catch (\Exception $e) {
            log_message('error', 'Block slot error: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * DELETE /api/slots/block (Protected admin)
     */
    public function unblock_slot()
    {
        $this->authenticate();
        $this->requireRole('admin');

        // check JSON payload first, if empty check GET query params
        $input = $this->getJsonInput();
        $date = isset($input['date']) ? $input['date'] : (isset($_GET['date']) ? $_GET['date'] : '');
        $timeSlot = isset($input['time_slot']) ? $input['time_slot'] : (isset($_GET['time_slot']) ? $_GET['time_slot'] : '');

        if (empty($date) || empty($timeSlot)) {
            $this->jsonResponse(['error' => 'date and time_slot are required'], 400);
            return;
        }

        try {
            $this->admin_model->unblockSlot($this->clinicId, $date, $timeSlot);
            $this->jsonResponse(['success' => true, 'message' => 'Slot unblocked successfully']);
        } catch (\Exception $e) {
            log_message('error', 'Unblock slot error: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    // ============================================================
    // PATIENT ENDPOINTS
    // ============================================================

    /**
     * GET /api/patients/lookup (Public)
     */
    public function patient_lookup()
    {
        // Rate limit: 20 req/min per IP
        $ip = $_SERVER['REMOTE_ADDR'];
        $key = 'rl_patient_' . md5($ip);
        $hits = $this->cache->get($key) ?: 0;
        if ($hits > 20) {
            $this->jsonResponse(['error' => 'Too many requests'], 429);
            return;
        }
        $this->cache->save($key, $hits + 1, 60);

        $clinicUsername = isset($_GET['clinic_username']) ? $_GET['clinic_username'] : '';
        $phone = isset($_GET['phone']) ? $_GET['phone'] : '';

        if (empty($clinicUsername) || empty($phone)) {
            $this->jsonResponse(['error' => 'clinic_username and phone are required'], 400);
            return;
        }

        $clinic = $this->admin_model->getClinicByUsername($clinicUsername, 'id');
        if (!$clinic) {
            $this->jsonResponse(['error' => 'Clinic not found'], 404);
            return;
        }

        try {
            $normalizedPhone = $this->normalizePhone($phone);
            $stats = $this->admin_model->getPatientStats($clinic['id'], $normalizedPhone);

            $visits = (int) ($stats['visits'] ?? 0);
            if ($visits === 0) {
                $this->jsonResponse(['visits' => 0]);
                return;
            }

            $treatments = $this->admin_model->getPatientTreatments($clinic['id'], $normalizedPhone);

            $this->jsonResponse([
                'visits' => $visits,
                'lastVisitDate' => $stats['lastVisitDate'],
                'previousTreatments' => $treatments
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Patient lookup error: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * GET /api/patients/search (Protected admin)
     */
    public function patient_search()
    {
        $this->authenticate();
        $this->requireRole('admin');

        $q = isset($_GET['q']) ? $_GET['q'] : '';

        if (empty($q)) {
            $this->jsonResponse(['error' => 'Search query q is required'], 400);
            return;
        }

        try {
            $patients = $this->admin_model->searchPatients($this->clinicId, $q);
            $this->jsonResponse($patients);
        } catch (\Exception $e) {
            log_message('error', 'Patient search error: ' . $e->getMessage());
            // Fallback to basic query
            try {
                $this->db->select('patient_name, patient_phone, COUNT(*) AS visits');
                $this->db->where('clinic_id', $this->clinicId);
                if (preg_match('/^\+?\d+$/', $q)) {
                    $this->db->like('patient_phone', $q);
                } else {
                    $this->db->like('patient_name', $q);
                }
                $this->db->group_by('patient_phone, patient_name');
                $query = $this->db->get('appointments');
                $this->jsonResponse($query->result_array());
            } catch (\Exception $e2) {
                log_message('error', 'Patient search fallback error: ' . $e2->getMessage());
                $this->jsonResponse(['error' => 'Internal server error'], 500);
            }
        }
    }

    // ============================================================
    // LEAD ENDPOINTS
    // ============================================================

    /**
     * POST /api/leads (Public)
     */
    public function create_lead()
    {
        $input = $this->getJsonInput();
        $clinicUsername = isset($input['clinic_username']) ? trim($input['clinic_username']) : '';
        $name = isset($input['name']) ? trim($input['name']) : '';
        $phone = isset($input['phone']) ? trim($input['phone']) : '';
        $interestedService = isset($input['interested_service']) ? $input['interested_service'] : null;
        $notes = isset($input['notes']) ? $input['notes'] : null;

        if (empty($clinicUsername) || empty($name) || empty($phone)) {
            $this->jsonResponse(['error' => 'clinic_username, name, and phone are required'], 400);
            return;
        }

        $clinic = $this->admin_model->getClinicByUsername($clinicUsername, 'id');
        if (!$clinic) {
            $this->jsonResponse(['error' => 'Clinic not found'], 404);
            return;
        }

        try {
            $normalizedPhone = $this->normalizePhone($phone);
            $this->admin_model->createLead([
                'clinic_id' => $clinic['id'],
                'name' => $name,
                'phone' => $normalizedPhone,
                'interested_service' => $interestedService,
                'notes' => $notes
            ]);

            $this->jsonResponse(['success' => true, 'message' => 'Inquiry received successfully. We will get back to you shortly!']);
        } catch (\Exception $e) {
            log_message('error', 'Create lead error: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * GET /api/leads (Protected admin)
     */
    public function get_leads()
    {
        $this->authenticate();
        $this->requireRole('admin');

        $status = isset($_GET['status']) ? $_GET['status'] : null;

        try {
            $leads = $this->admin_model->getLeads($this->clinicId, $status);
            $this->jsonResponse($leads);
        } catch (\Exception $e) {
            log_message('error', 'Fetch leads error: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * PATCH /api/leads/:id (Protected admin)
     */
    public function update_lead($id)
    {
        $this->authenticate();
        $this->requireRole('admin');

        $input = $this->getJsonInput();
        $status = isset($input['status']) ? $input['status'] : '';
        $notes = isset($input['notes']) ? $input['notes'] : null;

        if (empty($status)) {
            $this->jsonResponse(['error' => 'status is required'], 400);
            return;
        }

        try {
            $result = $this->admin_model->updateLead($id, $this->clinicId, [
                'status' => $status,
                'notes' => $notes
            ]);

            if (!$result) {
                $this->jsonResponse(['error' => 'Lead not found or unauthorized'], 404);
                return;
            }

            $this->jsonResponse(['success' => true, 'message' => 'Lead updated successfully']);
        } catch (\Exception $e) {
            log_message('error', 'Update lead error: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    // ============================================================
    // REPORT ENDPOINTS
    // ============================================================

    /**
     * GET /api/reports/summary (Protected admin)
     */
    public function report_summary()
    {
        $this->authenticate();
        $this->requireRole('admin');

        $start = isset($_GET['start']) ? $_GET['start'] : '';
        $end = isset($_GET['end']) ? $_GET['end'] : '';

        if (empty($start) || empty($end)) {
            $this->jsonResponse(['error' => 'start and end dates are required'], 400);
            return;
        }

        try {
            $summary = $this->admin_model->getReportSummary($this->clinicId, $start, $end);
            $this->jsonResponse($summary);
        } catch (\Exception $e) {
            log_message('error', 'Reports summary error: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    // ============================================================
    // GALLERY ENDPOINTS
    // ============================================================

    /**
     * GET /api/gallery
     */
    public function get_gallery()
    {
        $clinicUsername = isset($_GET['clinic_username']) ? $_GET['clinic_username'] : '';
        $clinicId = isset($_GET['clinic_id']) ? (int) $_GET['clinic_id'] : 0;

        try {
            if ($clinicId > 0) {
                $gallery = $this->admin_model->getGallery($clinicId);
            } elseif (!empty($clinicUsername)) {
                $gallery = $this->admin_model->getGalleryByClinicUsername($clinicUsername);
            } else {
                $this->jsonResponse(['error' => 'clinic_username or clinic_id required'], 400);
                return;
            }
            $this->jsonResponse($gallery);
        } catch (\Exception $e) {
            log_message('error', 'Gallery fetch error: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * POST /api/gallery (Protected admin)
     */
    public function upload_gallery()
    {
        $this->authenticate();
        $this->requireRole('admin');

        $type = isset($_POST['type']) ? $_POST['type'] : 'single';

        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $this->jsonResponse(['error' => 'No valid image uploaded'], 400);
            return;
        }

        try {
            $clinic = $this->admin_model->getClinicById($this->clinicId, 'username');
            $slug = $clinic['username'];
            $uploadPath = FCPATH . 'uploads/assets/' . $slug . '/gallery/';
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }

            $mime = mime_content_type($_FILES['image']['tmp_name']);
            $ext = $this->_safeExt($mime) ?: 'jpg';
            $filename = time() . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
            $destPath = $uploadPath . $filename;

            if (!move_uploaded_file($_FILES['image']['tmp_name'], $destPath)) {
                $this->jsonResponse(['error' => 'Failed to save image'], 500);
                return;
            }

            $imageUrl = '/uploads/assets/' . $slug . '/gallery/' . $filename;

            $isSingleBA = isset($_POST['is_single_ba']) && $_POST['is_single_ba'] === '1';
            $caption = isset($_POST['caption']) ? trim($_POST['caption']) : null;
            $this->admin_model->createGalleryItem([
                'clinic_id' => $this->clinicId,
                'type' => $isSingleBA ? 'single_ba' : 'single',
                'image_url' => $imageUrl,
                'caption' => $caption
            ]);

            $this->jsonResponse(['success' => true, 'message' => 'Gallery item uploaded successfully']);
        } catch (\Exception $e) {
            log_message('error', 'Gallery upload error: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * POST /api/gallery/before-after (Protected admin)
     */
    public function upload_before_after()
    {
        $this->authenticate();
        $this->requireRole('admin');

        $label = isset($_POST['label']) ? $_POST['label'] : null;

        $beforeFile = isset($_FILES['before_image']) ? $_FILES['before_image'] : null;
        $afterFile = isset($_FILES['after_image']) ? $_FILES['after_image'] : null;

        if (
            !$beforeFile || $beforeFile['error'] !== UPLOAD_ERR_OK ||
            !$afterFile || $afterFile['error'] !== UPLOAD_ERR_OK
        ) {
            $this->jsonResponse(['error' => 'Both before and after images are required'], 400);
            return;
        }

        try {
            $clinic = $this->admin_model->getClinicById($this->clinicId, 'username');
            $slug = $clinic['username'];
            $uploadPath = FCPATH . 'uploads/assets/' . $slug . '/gallery/';
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }

            $beforeMime = mime_content_type($beforeFile['tmp_name']);
            $beforeExt = $this->_safeExt($beforeMime) ?: 'jpg';
            $beforeFilename = time() . '-before-' . bin2hex(random_bytes(8)) . '.' . $beforeExt;
            move_uploaded_file($beforeFile['tmp_name'], $uploadPath . $beforeFilename);
            $beforeUrl = '/uploads/assets/' . $slug . '/gallery/' . $beforeFilename;

            $afterMime = mime_content_type($afterFile['tmp_name']);
            $afterExt = $this->_safeExt($afterMime) ?: 'jpg';
            $afterFilename = time() . '-after-' . bin2hex(random_bytes(8)) . '.' . $afterExt;
            move_uploaded_file($afterFile['tmp_name'], $uploadPath . $afterFilename);
            $afterUrl = '/uploads/assets/' . $slug . '/gallery/' . $afterFilename;

            $this->admin_model->createGalleryItem([
                'clinic_id' => $this->clinicId,
                'type' => 'before_after',
                'before_url' => $beforeUrl,
                'after_url' => $afterUrl,
                'caption' => $label
            ]);

            $this->jsonResponse(['success' => true, 'message' => 'Before/After pair uploaded successfully']);
        } catch (\Exception $e) {
            log_message('error', 'Gallery before-after upload error: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * DELETE /api/gallery/:id (Protected admin)
     */
    public function delete_gallery($id)
    {
        $this->authenticate();
        $this->requireRole('admin');

        try {
            $item = $this->admin_model->getGalleryItem($id, $this->clinicId);
            if (!$item) {
                $this->jsonResponse(['error' => 'Gallery item not found'], 404);
                return;
            }

            // Delete files from disk
            $basePath = FCPATH;
            foreach (['image_url', 'before_url', 'after_url'] as $field) {
                if (!empty($item[$field])) {
                    $filePath = $basePath . ltrim($item[$field], '/');
                    if (file_exists($filePath)) {
                        @unlink($filePath);
                    }
                }
            }

            $this->admin_model->deleteGalleryItem($id);
            $this->jsonResponse(['success' => true, 'message' => 'Gallery item deleted']);
        } catch (\Exception $e) {
            log_message('error', 'Gallery delete error: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * PATCH /api/gallery/:id/caption (Protected admin)
     */
    public function update_gallery_caption($id)
    {
        $this->authenticate();
        $this->requireRole('admin');

        $input = $this->getJsonInput();
        $caption = isset($input['caption']) ? trim($input['caption']) : '';

        $item = $this->admin_model->getGalleryItem($id, $this->clinicId);
        if (!$item) {
            $this->jsonResponse(['error' => 'Not found'], 404);
            return;
        }

        $this->db->where('id', $id)->update('gallery', ['caption' => $caption]);
        $this->jsonResponse(['success' => true]);
    }

    /**
     * POST /api/upload/asset
     * Generic clinic asset uploader for super admin config panel.
     * type param → subfolder: logo|hero|video|doctor|step|service
     * Returns { url: '/uploads/assets/{slug}/{type}/{filename}' }
     */
    public function upload_asset()
    {
        $this->authenticate();
        $this->requireRole('admin');

        $allowedTypes = ['logo', 'hero', 'video', 'doctor', 'step', 'service'];
        $type = isset($_POST['type']) ? $_POST['type'] : '';

        if (!in_array($type, $allowedTypes)) {
            $this->jsonResponse(['error' => 'Invalid type. Allowed: ' . implode(', ', $allowedTypes)], 400);
            return;
        }

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->jsonResponse(['error' => 'No valid file uploaded'], 400);
            return;
        }

        // Validate mime — images for everything except video
        $file = $_FILES['file'];
        $mime = mime_content_type($file['tmp_name']);
        $isVideo = ($type === 'video');
        $allowedMimes = $isVideo
            ? ['video/mp4', 'video/webm', 'video/ogg']
            : ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

        if (!in_array($mime, $allowedMimes)) {
            $this->jsonResponse(['error' => 'Invalid file type for ' . $type], 400);
            return;
        }

        try {
            // Get clinic username for folder name
            $clinic = $this->admin_model->getClinicById($this->clinicId, 'username');
            $slug = $clinic['username'];

            $uploadPath = FCPATH . 'uploads/assets/' . $slug . '/' . $type . '/';
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }

            $ext = $this->_safeExt($mime) ?: ($isVideo ? 'mp4' : 'jpg');
            $filename = $type . '-' . time() . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
            $destPath = $uploadPath . $filename;

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                $this->jsonResponse(['error' => 'Failed to save file'], 500);
                return;
            }

            $url = '/uploads/assets/' . $slug . '/' . $type . '/' . $filename;
            $this->jsonResponse(['success' => true, 'url' => $url]);

        } catch (\Exception $e) {
            log_message('error', 'Asset upload error: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    // ============================================================
    // DOCUMENT ENDPOINTS
    // ============================================================

    /**
     * GET /api/documents (Protected admin)
     */
    public function get_documents()
    {
        $this->authenticate();
        $this->requireRole('admin');

        $phone = isset($_GET['phone']) ? $_GET['phone'] : '';

        if (empty($phone)) {
            $this->jsonResponse(['error' => 'phone query parameter is required'], 400);
            return;
        }

        try {
            $normalizedPhone = $this->normalizePhone($phone);
            $documents = $this->admin_model->getDocuments($this->clinicId, $normalizedPhone);
            $this->jsonResponse($documents);
        } catch (\Exception $e) {
            log_message('error', 'Fetch documents error: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * POST /api/documents (Protected admin)
     */
    public function upload_document()
    {
        $this->authenticate();
        $this->requireRole('admin');

        $patientPhone = isset($_POST['patient_phone']) ? $_POST['patient_phone'] : '';
        $appointmentId = isset($_POST['appointment_id']) ? $_POST['appointment_id'] : null;

        if (empty($patientPhone)) {
            $this->jsonResponse(['error' => 'patient_phone is required for document upload'], 400);
            return;
        }

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->jsonResponse(['error' => 'No valid file uploaded'], 400);
            return;
        }

        try {
            $normalizedPhone = $this->normalizePhone($patientPhone);
            $safePhone = preg_replace('/\D/', '', $normalizedPhone);

            $uploadPath = FCPATH . 'uploads/' . $this->clinicId . '/' . $safePhone . '/';
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }

            // Validate MIME for document uploads
            $fileMime = mime_content_type($_FILES['file']['tmp_name']);
            $allowedDocMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (!in_array($fileMime, $allowedDocMimes)) {
                $this->jsonResponse(['error' => 'Invalid file type. Allowed: PDF and images'], 400);
                return;
            }

            $ext = $this->_safeExt($fileMime) ?: 'jpg';
            if (strtolower($fileMime) === 'application/pdf')
                $ext = 'pdf';
            $filename = time() . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
            $destPath = $uploadPath . $filename;

            if (!move_uploaded_file($_FILES['file']['tmp_name'], $destPath)) {
                $this->jsonResponse(['error' => 'Failed to save file'], 500);
                return;
            }

            $filePath = '/api/documents/view/' . $this->clinicId . '/' . $safePhone . '/' . $filename;
            $fileType = (strtolower($ext) === 'pdf') ? 'pdf' : 'image';

            $docId = $this->admin_model->createDocument([
                'clinic_id' => $this->clinicId,
                'patient_phone' => $normalizedPhone,
                'appointment_id' => $appointmentId,
                'file_name' => $_FILES['file']['name'],
                'file_path' => $filePath,
                'file_type' => $fileType
            ]);

            $this->jsonResponse([
                'success' => true,
                'document' => [
                    'id' => $docId,
                    'file_name' => $_FILES['file']['name'],
                    'file_path' => $filePath,
                    'file_type' => $fileType
                ]
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Document upload error: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * DELETE /api/documents/:id (Protected admin)
     */
    public function delete_document($id)
    {
        $this->authenticate();
        $this->requireRole('admin');

        try {
            $doc = $this->admin_model->getDocument($id, $this->clinicId);
            if (!$doc) {
                $this->jsonResponse(['error' => 'Document not found or unauthorized'], 404);
                return;
            }

            // Delete file from disk
            $parts = explode('/api/documents/view/', $doc['file_path']);
            if (count($parts) === 2) {
                $diskPath = FCPATH . 'uploads/' . $parts[1];
                if (file_exists($diskPath)) {
                    @unlink($diskPath);
                }
            }

            $this->admin_model->deleteDocument($id, $this->clinicId);
            $this->jsonResponse(['success' => true, 'message' => 'Document deleted successfully']);
        } catch (\Exception $e) {
            log_message('error', 'Delete document error: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    // ============================================================
    // PRIVATE HELPERS
    // ============================================================

    private function _safeExt($mime)
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/ogg' => 'ogg',
            'application/pdf' => 'pdf'
        ];
        return $map[$mime] ?? null;
    }

    /**
     * Sanitize clinic data for public response
     */
    private function _sanitizeClinic($clinic)
    {
        $sensitiveFields = [
            'admin_password_hash',
            'reset_otp',
            'reset_otp_expires',
            'doctor_pin_hash',
            'whatsapp_number',
            'whatsapp_confirmation',
            'whatsapp_features'
        ];

        $safe = array_diff_key($clinic, array_flip($sensitiveFields));

        $config = $this->admin_model->parseJsonField($clinic['config']);
        $visibility = isset($clinic['visibility_settings'])
            ? (is_string($clinic['visibility_settings']) ? json_decode($clinic['visibility_settings'], true) : $clinic['visibility_settings'])
            : [];

        $wa = isset($config['whatsapp']) ? $config['whatsapp'] : [];
        if (isset($wa['access_token'])) {
            $wa['access_token'] = '***';
        }
        $safeWa = $wa;

        // All config keys that public frontend needs must be listed here.
        // Any new per-clinic config field added to config JSON → add its key here too.
        $publicConfigKeys = [
            'name',
            'tagline',
            'logo',
            'theme',
            'hero',
            'doctors',
            'google_review_link',
            'contact',
            'super_admin_only'
        ];
        $publicConfig = array_intersect_key($config, array_flip($publicConfigKeys));

        unset($safe['config']);
        $merged = array_merge($safe, $publicConfig, [
            'visibility_settings' => $visibility,
            'whatsapp' => $safeWa,
            'reviews' => isset($safe['reviews']) ? $safe['reviews'] : []
        ]);

        // Bridge flat columns → contact sub-object so frontend reads contact.phone / contact.address / contact.mapEmbedUrl
        $contact = isset($merged['contact']) && is_array($merged['contact']) ? $merged['contact'] : [];
        if (!empty($safe['contact_phone']))
            $contact['phone'] = $safe['contact_phone'];
        if (!empty($safe['contact_address']))
            $contact['address'] = $safe['contact_address'];
        if (!empty($safe['contact_map_url']))
            $contact['mapEmbedUrl'] = $safe['contact_map_url'];
        $merged['contact'] = $contact;

        return $merged;
    }
}