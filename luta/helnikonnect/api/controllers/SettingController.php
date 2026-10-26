<?php
/**
 * System settings controller.
 */
final class SettingController
{
    private SystemSetting $settings;

    public function __construct()
    {
        $this->settings = new SystemSetting();
    }

    /**
     * GET /api/v1/settings — public subset used by the client website.
     */
    public function index(): void
    {
        $all = $this->settings->getAll();

        // Reveal only non-sensitive public settings to the client.
        $publicKeys = [
            'platform_name', 'platform_tagline', 'support_phone', 'support_email',
            'application_email', 'technical_support_email', 'whatsapp_contact',
            'address', 'working_hours', 'currency', 'application_prefix',
            'pagination_limit', 'primary_color', 'accent_color',
        ];

        if (Auth::isAuthenticated() && (int) (Auth::user()['is_staff'] ?? 0) === 1) {
            Response::success($all);
            return;
        }

        $out = [];
        foreach ($publicKeys as $k) {
            if (array_key_exists($k, $all)) $out[$k] = $all[$k];
        }
        Response::success($out);
    }

    /**
     * PUT /api/v1/settings — admin updates settings.
     */
    public function update(): void
    {
        Auth::requirePermission('settings.manage');
        $input = Helper::input();
        if (empty($input)) Response::badRequest('No settings provided.');

        foreach ($input as $key => $value) {
            if (is_scalar($value)) {
                $this->settings->set($key, (string) $value);
            }
        }
        ActivityLog::log(Auth::id(), 'Settings updated', 'settings');
        Response::success($this->settings->getAll(), 'Settings updated.');
    }
}
