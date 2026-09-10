<?php

/**
 * Buttons
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Doctorcura_Request_Form {
    private const NONCE_ACTION = 'doctorcura_request_form_submit';
    private const NONCE_NAME   = 'doctorcura_request_form_nonce';
    private const SHORTCODE    = 'doctorcura_request_form';
    private const TO_EMAIL     = 'contact@doctorcura.com';
    private const SUCCESS_URL  = 'https://doctorcura.com/vielen-dank-fur-ihre-anfrage';

    /**
     * @var WC_Order|false|null
     */
    private static $latest_order = null;

    public function __construct() {
        add_shortcode(self::SHORTCODE, [$this, 'render_shortcode']);
        add_shortcode('dc_button_url', [$this, 'render_button_url']);
        add_action('init', [$this, 'handle_submission']);
    }

    public function render_button_url(array $atts = []): string {
        $atts = shortcode_atts([
            'logged_in_url'  => 'https://doctorcura.com/anfrage-1/',
            'logged_out_url' => 'https://doctorcura.com/anfrage-2/',
        ], $atts, 'dc_button_url');

        return esc_url(is_user_logged_in() ? $atts['logged_in_url'] : $atts['logged_out_url']);
    }

    public function render_shortcode(): string {
        if (!is_user_logged_in()) {
            return '<div class="doctorcura-form-notice doctorcura-form-notice--error">Sie müssen eingeloggt sein, um dieses Formular zu verwenden.</div>';
        }

        $order = self::get_latest_order();

        if (!$order) {
            return '<div class="doctorcura-form-notice doctorcura-form-notice--error">Für dieses Konto wurde keine aktuelle Bestellung gefunden.</div>';
        }

        $customer = self::get_customer_data($order);
        $status   = isset($_GET['doctorcura_form_status']) ? sanitize_key((string) $_GET['doctorcura_form_status']) : '';

        ob_start();

        if ($status === 'success') {
            echo '<div class="doctorcura-form-notice doctorcura-form-notice--success">Vielen Dank. Ihre Anfrage wurde erfolgreich gesendet. Sie werden jetzt weitergeleitet.</div>';
            echo '<script>setTimeout(function(){ window.location.href = ' . wp_json_encode(self::SUCCESS_URL) . '; }, 1200);</script>';
        } elseif ($status === 'error') {
            echo '<div class="doctorcura-form-notice doctorcura-form-notice--error">Beim Senden ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.</div>';
        }
        ?>
        <form method="post" class="doctorcura-custom-form">
            <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
            <input type="hidden" name="doctorcura_request_form_submit" value="1">

            <div class="doctorcura-hidden-fields" aria-hidden="true">
                <input type="hidden" name="dc_name" value="<?php echo esc_attr($customer['name']); ?>">
                <input type="hidden" name="dc_email" value="<?php echo esc_attr($customer['email']); ?>">
                <input type="hidden" name="dc_address" value="<?php echo esc_attr($customer['address']); ?>">
                <input type="hidden" name="dc_city" value="<?php echo esc_attr($customer['city']); ?>">
            </div>

            <div class="doctorcura-form-grid">
                <div class="doctorcura-field doctorcura-field--full">
                    <label for="dc_medication">Welches Medikament möchten Sie bestellen?</label>
                    <input
                        type="text"
                        id="dc_medication"
                        name="dc_medication"
                        class="doctorcura-input"
                        placeholder="Bitte geben Sie hier Ihr gewünschtes Medikament ein"
                        required
                    >
                </div>

                <div class="doctorcura-field doctorcura-field--full">
                    <label for="dc_treatment">Welche Behandlung möchten Sie anfragen?</label>
                    <textarea
                        id="dc_treatment"
                        name="dc_treatment"
                        class="doctorcura-input doctorcura-textarea"
                        rows="5"
                        placeholder="Bitte beschreiben Sie hier Ihre gewünschte Behandlung"
                        required
                    ></textarea>
                </div>
            </div>

            <button type="submit" class="doctorcura-submit">Anfrage senden</button>
        </form>

        <style>
            .doctorcura-hidden-fields {
                display: none !important;
            }

            body .doctorcura-custom-form label {
                padding-bottom: 7px;
            }

            .doctorcura-custom-form .doctorcura-field > label {
                display: block;
                font-size: 1rem;
                font-weight: 600;
                color: var(--e-global-color-primary);
            }

            .doctorcura-custom-form .doctorcura-input {
                width: 100%;
                box-sizing: border-box;
                background-color: #ffffff;
                color: var(--e-global-color-text);
                font-size: 1em;
                font-weight: 400;
                border: 1px solid rgba(0,0,0,.12);
                border-radius: 6px;
                padding: 12px 14px;
            }

            .doctorcura-custom-form .doctorcura-textarea {
                min-height: 140px;
                resize: vertical;
            }

            .doctorcura-form-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .doctorcura-field--full {
                grid-column: 1 / -1;
            }

            .doctorcura-submit {
                width: 100%;
                margin-top: 16px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 14px 20px;
                border: 0;
                border-radius: 6px;
                background-color: var(--e-global-color-5840bbc);
                color: var(--e-global-color-c17d1e9);
                font-size: 1rem;
                font-weight: 600;
                cursor: pointer;
                transition: background-color .2s ease, color .2s ease;
            }

            .doctorcura-submit:hover {
                background-color: #000000;
                color: #ffffff;
            }

            .doctorcura-form-notice {
                margin-bottom: 16px;
                padding: 12px 14px;
                border-radius: 6px;
                font-size: .95rem;
            }

            .doctorcura-form-notice--success {
                background: #ecfdf3;
                color: #166534;
            }

            .doctorcura-form-notice--error {
                background: #fef2f2;
                color: #991b1b;
            }
        </style>
        <?php

        return (string) ob_get_clean();
    }

    public function handle_submission(): void {
        if (
            !isset($_POST['doctorcura_request_form_submit']) ||
            !isset($_POST[self::NONCE_NAME])
        ) {
            return;
        }

        if (!is_user_logged_in()) {
            $this->redirect_with_status('error');
        }

        $nonce = sanitize_text_field(wp_unslash((string) $_POST[self::NONCE_NAME]));

        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            $this->redirect_with_status('error');
        }

        $order = self::get_latest_order();

        if (!$order) {
            $this->redirect_with_status('error');
        }

        $customer   = self::get_customer_data($order);
        $medication = isset($_POST['dc_medication']) ? sanitize_text_field(wp_unslash((string) $_POST['dc_medication'])) : '';
        $treatment  = isset($_POST['dc_treatment']) ? sanitize_textarea_field(wp_unslash((string) $_POST['dc_treatment'])) : '';

        if ($medication === '' || $treatment === '') {
            $this->redirect_with_status('error');
        }

        $billing_name = trim($customer['name']);
        $subject = 'Nieuwe aanvraag van ' . ($billing_name !== '' ? $billing_name : 'klant');

        $message = '
<div style="font-family: \'Roboto\', Arial, sans-serif; background-color: #ffffff; padding: 40px 20px; min-height: 100%;">
    <div style="background-color: #DBEAFE; color: #000000; line-height: 1.3; max-width: 600px; margin: 0; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); overflow: hidden;">
        
        <div style="margin-bottom: 40px; text-align: left;">
            <a href="https://doctorcura.nl" style="text-decoration: none; display: inline-block;">
                <img src="https://doctorcura.com/wp-content/uploads/2025/12/logo-2-scaled-1.png" width="160" alt="Doctorcura" style="display: block; border: 0; margin: 0;">
            </a>
        </div>

        <div style="margin-bottom: 20px;">
            <p style="font-size: 16px; margin: 0 0 25px 0;">
                Hallo
            </p>

            <p style="font-size: 15px; margin: 0 0 12px 0;">
                Er is een nieuwe aanvraag binnengekomen via het formulier op de website.
            </p>

            <p style="font-size: 15px; margin: 0 0 12px 0;">
                Hieronder vindt u de gegevens van de aanvraag.
            </p>
        </div>

        <div style="margin-bottom: 30px;">
            <p style="font-size: 15px; margin: 0 0 8px 0;"><strong>Naam:</strong> ' . esc_html($customer['name']) . '</p>
            <p style="font-size: 15px; margin: 0 0 8px 0;"><strong>E-mail:</strong> ' . esc_html($customer['email']) . '</p>
            <p style="font-size: 15px; margin: 0 0 8px 0;"><strong>Adres:</strong> ' . esc_html($customer['address']) . '</p>
            <p style="font-size: 15px; margin: 0 0 16px 0;"><strong>Woonplaats:</strong> ' . esc_html($customer['city']) . '</p>

            <p style="font-size: 15px; margin: 0 0 8px 0;"><strong>Welches Medikament möchten Sie bestellen?</strong><br>' . nl2br(esc_html($medication)) . '</p>
            <p style="font-size: 15px; margin: 0;"><strong>Welche Behandlung möchten Sie anfragen?</strong><br>' . nl2br(esc_html($treatment)) . '</p>
        </div>

        <div style="margin-bottom: 30px;">
            <p style="font-size: 15px; margin: 0;">
                Met vriendelijke groet,
            </p>
            <p style="font-size: 15px; margin: 0;">
                Team Doctorcura
            </p>
        </div>

        <hr style="border: 0; border-top: 1px solid rgba(0,0,0,0.1); margin: 0 -30px 0 -30px;">

        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width: 100%; text-align: left;">
            <tr>
                <td style="padding-top: 15px; padding-bottom: 15px;">
                    <span style="font-size: 11px; color: #5f6368; text-transform: uppercase; font-weight: bold; display: block;">
                        E-Mail
                    </span>
                    <a href="mailto:contact@doctorcura.com" style="font-size: 13px; color: #0560FF; text-decoration: none;">
                        contact@doctorcura.com
                    </a>
                </td>
            </tr>

            <tr>
                <td style="padding-bottom: 15px;">
                    <span style="font-size: 11px; color: #5f6368; text-transform: uppercase; font-weight: bold; display: block;">
                        Telefon
                    </span>
                    <a href="tel:+31855055433" style="font-size: 13px; color: #0560FF; text-decoration: none;">
                        +31 85 505 54 33
                    </a>
                </td>
            </tr>

            <tr>
                <td style="padding-bottom: 0;">
                    <span style="font-size: 11px; color: #5f6368; text-transform: uppercase; font-weight: bold; display: block;">
                        Adresse
                    </span>
                    <span style="font-size: 13px; color: #000000;">
                        Veldbloemlaan 93, 3452CK Vleuten, The Netherlands
                    </span>
                </td>
            </tr>
        </table>

    </div>
</div>';

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: Doctorcura <noreply@doctorcura.com>',
        ];

        if ($customer['email'] !== '' && is_email($customer['email'])) {
            $headers[] = 'Reply-To: ' . $customer['email'];
        }

        $sent = wp_mail(self::TO_EMAIL, $subject, $message, $headers);

        $this->redirect_with_status($sent ? 'success' : 'error');
    }

	private function redirect_with_status(string $status): void {
		if ($status === 'success') {
			wp_safe_redirect(self::SUCCESS_URL);
			exit;
		}

		$redirect_url = wp_get_referer() ?: home_url('/');
		$redirect_url = remove_query_arg('doctorcura_form_status', $redirect_url);
		$redirect_url = add_query_arg('doctorcura_form_status', 'error', $redirect_url);

		wp_safe_redirect($redirect_url);
		exit;
}

    private static function get_latest_order(): ?WC_Order {
        if (!function_exists('wc_get_orders')) {
            return null;
        }

        if (self::$latest_order !== null) {
            return self::$latest_order instanceof WC_Order ? self::$latest_order : null;
        }

        $user_id = get_current_user_id();
        $user    = wp_get_current_user();

        if ($user_id > 0) {
            $orders = wc_get_orders([
                'customer_id' => $user_id,
                'limit'       => 1,
                'orderby'     => 'date',
                'order'       => 'DESC',
                'return'      => 'objects',
            ]);

            if (!empty($orders) && $orders[0] instanceof WC_Order) {
                self::$latest_order = $orders[0];
                return self::$latest_order;
            }
        }

        if (!empty($user->user_email) && is_email($user->user_email)) {
            $orders = wc_get_orders([
                'billing_email' => $user->user_email,
                'limit'         => 1,
                'orderby'       => 'date',
                'order'         => 'DESC',
                'return'        => 'objects',
            ]);

            if (!empty($orders) && $orders[0] instanceof WC_Order) {
                self::$latest_order = $orders[0];
                return self::$latest_order;
            }

            $orders = wc_get_orders([
                'meta_key'   => '_billing_email',
                'meta_value' => $user->user_email,
                'limit'      => 1,
                'orderby'    => 'date',
                'order'      => 'DESC',
                'return'     => 'objects',
            ]);

            if (!empty($orders) && $orders[0] instanceof WC_Order) {
                self::$latest_order = $orders[0];
                return self::$latest_order;
            }
        }

        self::$latest_order = false;
        return null;
    }

    private static function get_order_value(WC_Order $order, string $getter, string $meta_key = ''): string {
        $value = '';

        if (method_exists($order, $getter)) {
            $value = trim((string) $order->{$getter}());
        }

        if ($value === '' && $meta_key !== '') {
            $meta_value = $order->get_meta($meta_key, true);
            $value = is_scalar($meta_value) ? trim((string) $meta_value) : '';
        }

        return $value;
    }

    private static function get_customer_data(WC_Order $order): array {
        $first_name = self::get_order_value($order, 'get_billing_first_name', '_billing_first_name');
        $last_name  = self::get_order_value($order, 'get_billing_last_name', '_billing_last_name');
        $email      = self::get_order_value($order, 'get_billing_email', '_billing_email');
        $address_1  = self::get_order_value($order, 'get_billing_address_1', '_billing_address_1');
        $address_2  = self::get_order_value($order, 'get_billing_address_2', '_billing_address_2');
        $postcode   = self::get_order_value($order, 'get_billing_postcode', '_billing_postcode');
        $city       = self::get_order_value($order, 'get_billing_city', '_billing_city');

        return [
            'name'    => trim($first_name . ' ' . $last_name),
            'email'   => $email,
            'address' => trim($address_1 . ($address_2 !== '' ? ' ' . $address_2 : '')),
            'city'    => trim($postcode . ' ' . $city),
        ];
    }
}

new Doctorcura_Request_Form();

