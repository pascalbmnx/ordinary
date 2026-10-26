<?php
/**
 * Outbound customer notification service.
 *
 * Sends status updates to customers via their provided contact (SMS /
 * WhatsApp / call). Delivery is currently SIMULATED: each message is
 * recorded in the `notification_deliveries` table with status "sent".
 *
 * To connect a real provider later, replace the body of `transmit()` with
 * the provider's HTTP/API call (e.g. Africa's Talking, Twilio, WhatsApp
 * Business API) and hand back the provider reference; then set the
 * `notification_gateway` system setting to the provider name.
 */
final class Messenger
{
    private PDO $db;
    private SystemSetting $settings;

    public function __construct()
    {
        $this->db = Database::connection();
        $this->settings = new SystemSetting();
    }

    /**
     * Send a customer update through the configured channels.
     *
     * @param string      $phone     Recipient phone (e.g. +2557XXXXXXXX).
     * @param array       $channels  Channel keys to attempt, e.g. ['sms', 'whatsapp'].
     * @param string      $message   Message body.
     * @param array       $context   Optional meta (subject, resource_type, resource_id, reference_no, email).
     */
    public function notify(string $phone, array $channels, string $message, array $context = []): array
    {
        $active = $this->activeChannels();
        $results = [];

        foreach (array_unique($channels) as $channel) {
            $channel = strtolower(trim($channel));
            if (!in_array($channel, ['sms', 'whatsapp', 'call', 'email'], true)) {
                continue;
            }
            // Only send through channels that are enabled in settings.
            if ($channel !== 'email' && !in_array($channel, $active, true)) {
                continue;
            }

            $recipient = $channel === 'email'
                ? (string) ($context['email'] ?? '')
                : $phone;

            if ($recipient === '') {
                continue;
            }

            $results[] = $this->log($channel, $recipient, $message, $context);
        }

        return $results;
    }

    /**
     * Enabled non-email channels, read from system_settings.
     */
    private function activeChannels(): array
    {
        $active = [];
        foreach (['sms', 'whatsapp', 'call'] as $ch) {
            if ($this->isEnabled('notify_' . $ch)) {
                $active[] = $ch;
            }
        }
        return $active;
    }

    private function isEnabled(string $key): bool
    {
        $value = trim((string) $this->settings->get($key, ''));
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Deliver the message and record the delivery.
     */
    private function log(string $channel, string $recipient, string $message, array $context): array
    {
        $attempt = [
            'channel'      => $channel,
            'recipient'    => $recipient,
            'subject'      => trim((string) ($context['subject'] ?? '')),
            'message'      => $message,
            'status'       => 'sent',
            'provider_ref' => $this->transmit($channel, $recipient, $message),
            'simulated'    => $this->isSimulated(),
            'resource_type'=> $context['resource_type'] ?? null,
            'resource_id'  => isset($context['resource_id']) ? (int) $context['resource_id'] : null,
            'reference_no' => $context['reference_no'] ?? null,
        ];

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO notification_deliveries
                 (channel, recipient, subject, message, status, provider_ref, simulated,
                  resource_type, resource_id, reference_no, error)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $attempt['channel'],
                $attempt['recipient'],
                $attempt['subject'],
                $attempt['message'],
                $attempt['status'],
                $attempt['provider_ref'],
                $attempt['simulated'] ? 1 : 0,
                $attempt['resource_type'],
                $attempt['resource_id'],
                $attempt['reference_no'],
                null,
            ]);
            $attempt['id'] = (int) $this->db->lastInsertId();
        } catch (Throwable $e) {
            $attempt['status'] = 'failed';
            $attempt['error']  = $e->getMessage();
            Database::logError('Messenger log: ' . $e->getMessage());
        }

        return $attempt;
    }

    /**
     * Actually transmit via a provider. Currently simulated.
     */
    private function transmit(string $channel, string $recipient, string $message): string
    {
        // Placeholder for a real provider integration (e.g. Africa's Talking,
        // Twilio, WhatsApp Business API). Return a provider reference here.
        return strtoupper($channel) . '-SIM-' . strtoupper(bin2hex(random_bytes(4)));
    }

    private function isSimulated(): bool
    {
        $gateway = strtolower(trim((string) $this->settings->get('notification_gateway', 'simulated')));
        return $gateway === '' || $gateway === 'simulated';
    }
}
