<?php
/**
 * Authentication controller.
 * POST /api/v1/auth/register
 * POST /api/v1/auth/login
 * POST /api/v1/auth/logout
 * GET  /api/v1/auth/me
 * POST /api/v1/auth/forgot-password
 * POST /api/v1/auth/reset-password
 */
final class AuthController
{
    private User $userModel;
    private AuthToken $tokenModel;
    private SystemSetting $settings;
    private int $tokenTTL;
    private int $resetTTL;
    private int $loginMaxAttempts;

    public function __construct()
    {
        $this->userModel = new User();
        $this->tokenModel = new AuthToken();
        $this->settings = new SystemSetting();
        $sec = Database::config('security');
        $this->tokenTTL = $sec['token_ttl'] ?? 43200;
        $this->resetTTL = $sec['password_reset_ttl'] ?? 7200;
        $this->loginMaxAttempts = $sec['login_max_attempts'] ?? 5;
    }

    public function register(): void
    {
        $input = Helper::input();

        $name = trim($input['full_name'] ?? '');
        $phone = trim($input['phone'] ?? '');
        $email = trim($input['email'] ?? null);
        $password = $input['password'] ?? '';
        $confirm = $input['password_confirmation'] ?? '';
        $applicantType = $input['applicant_type'] ?? null;
        $region = $input['region'] ?? null;
        $district = $input['district'] ?? null;
        $ward = $input['ward'] ?? null;
        $street = $input['street'] ?? null;

        // Validation
        $errors = [];
        if ($name === '' || mb_strlen($name) < 2) $errors[] = 'Full name is required (min 2 chars).';
        if ($phone === '') {
            $errors[] = 'Phone number is required.';
        } elseif (!Helper::validTzPhone($phone)) {
            $errors[] = 'Invalid Tanzanian phone number.';
        }
        if ($email !== null && $email !== '' && !Helper::validEmail($email)) {
            $errors[] = 'Invalid email address.';
        }
        if (mb_strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
        if ($password !== $confirm) $errors[] = 'Passwords do not match.';
        if (!empty($errors)) {
            Response::badRequest('Validation failed.', $errors);
        }

        // Uniqueness
        if ($this->userModel->findByPhone($phone)) {
            Response::conflict('Phone number is already registered.');
        }
        if ($email !== null && $email !== '' && $this->userModel->findByEmail($email)) {
            Response::conflict('Email is already registered.');
        }

        $userId = $this->userModel->create([
            'role_id' => 5,
            'full_name' => $name,
            'phone' => $phone,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'applicant_type' => $applicantType,
            'region' => $region,
            'district' => $district,
            'ward' => $ward,
            'street' => $street,
            'is_staff' => 0,
        ]);

        $token = Helper::makeSessionToken();
        $this->tokenModel->create($userId, $token, $this->tokenTTL);

        Response::created([
            'user' => $this->userModel->publicUser($this->userModel->findById($userId)),
            'token' => $token,
        ], 'Registration successful.');
    }

    public function login(): void
    {
        $input = Helper::input();
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if ($email === '' || $password === '') {
            Response::badRequest('Email and password are required.');
        }

        // Rate limit by IP
        $identifier = Helper::clientIp() . ':login';
        RateLimiter::hit($identifier, 'login', $this->loginMaxAttempts, 900);

        $user = $this->userModel->findByEmail($email);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            Response::unauthorized('Invalid email or password.');
        }
        if ((int) $user['is_active'] !== 1) {
            Response::forbidden('Your account has been deactivated.');
        }
        if ((int) $user['is_staff'] !== 1) {
            Response::forbidden('Admin access only.');
        }

        $this->userModel->updateLastLogin((int) $user['id']);

        $token = Helper::makeSessionToken();
        $this->tokenModel->create((int) $user['id'], $token, $this->tokenTTL);

        Response::success([
            'user' => $this->userModel->publicUser($user),
            'token' => $token,
        ], 'Login successful.');
    }

    public function logout(): void
    {
        $ctx = Auth::context();
        if ($ctx) {
            $this->tokenModel->revoke($ctx['token']);
        }
        Response::success(null, 'Logged out.');
    }

    public function me(): void
    {
        $ctx = Auth::requireAuth();
        $user = $this->userModel->publicUser($ctx['user']);
        $user['role'] = $ctx['role'];
        $user['permissions'] = $ctx['permissions'];
        Response::success($user);
    }

    public function forgotPassword(): void
    {
        $input = Helper::input();
        $email = trim($input['email'] ?? '');
        if (!Helper::validEmail($email) || $email === '') {
            Response::badRequest('A valid email address is required.');
        }

        RateLimiter::hit(Helper::clientIp() . ':forgot', 'forgot', 5, 3600);

        $user = $this->userModel->findByEmail($email);
        if (!$user) {
            // Don't reveal whether the email exists
            Response::success(null, 'If the email exists, a password reset link has been sent.');
            return;
        }

        $db = Database::connection();
        // Invalidate old tokens for this user
        $db->prepare('UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0')
           ->execute([(int) $user['id']]);

        $token = Helper::randomToken(64);
        $expires = date('Y-m-d H:i:s', time() + $this->resetTTL);
        $db->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)')
           ->execute([(int) $user['id'], $token, $expires]);

        // In a real system you would email this link. Return token in dev mode.
        $resetUrl = Helper::input()['reset_url'] ?? '/client/pages/reset-password.html';

        Response::success([
            'message' => 'If the email exists, a reset link has been sent.',
            // For development: expose the token
            'reset_token' => $token,
            'reset_url' => $resetUrl . '?token=' . $token,
        ], 'Password reset initiated.');
    }

    public function resetPassword(): void
    {
        $input = Helper::input();
        $token = $input['token'] ?? '';
        $password = $input['password'] ?? '';
        $confirm = $input['password_confirmation'] ?? '';

        if ($token === '' || mb_strlen($token) < 20) {
            Response::badRequest('Invalid reset token.');
        }
        if (mb_strlen($password) < 8) {
            Response::badRequest('Password must be at least 8 characters.');
        }
        if ($password !== $confirm) {
            Response::badRequest('Passwords do not match.');
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute([$token]);
        $reset = $stmt->fetch();
        if (!$reset) {
            Response::badRequest('Password reset token is invalid or expired.');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $userId = (int) $reset['user_id'];

        $db->beginTransaction();
        try {
            $this->userModel->updatePassword($userId, $hash);
            $db->prepare('UPDATE password_resets SET used = 1 WHERE id = ?')->execute([(int) $reset['id']]);
            $this->tokenModel->revokeAllForUser($userId);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            Database::logError('resetPassword: ' . $e->getMessage());
            Response::serverError();
        }

        Response::success(null, 'Password has been reset. Please log in.');
    }
}