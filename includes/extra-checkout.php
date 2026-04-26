<?php

/**
 * Checkout
 */
declare(strict_types=1);

namespace DoctorCura\Checkout;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use WC_Checkout;
use WC_Order;

defined('ABSPATH') || exit;

/**
 * Combined checkout handler for DoctorCura.
 *
 * Merges:
 * - Terms & conditions behavior
 * - Medical questionnaire
 * - Street name / house number customization and validation
 *
 * GTranslate / i18n notes:
 * - All user-facing strings are wrapped in WordPress i18n functions.
 * - Output stays server-rendered where possible, which helps translation proxies.
 * - No "notranslate" class is used here, so visible checkout content can be translated.
 */
final class CheckoutHandler
{
    public static function init(): void
    {
        $instance = new self();

        // HPOS compatibility
        add_action('before_woocommerce_init', [$instance, 'declare_hpos_compatibility']);

        // Terms & conditions
        add_filter(
            'woocommerce_get_terms_and_conditions_checkbox_text',
            [$instance, 'add_target_blank']
        );
        add_action(
            'wp_enqueue_scripts',
            [$instance, 'enqueue_inline_js']
        );
        add_action(
            'wp',
            [$instance, 'remove_inline_terms_content']
        );
        add_filter(
            'woocommerce_create_account_default_checked',
            '__return_false'
        );

        // Address labels and validation
        add_filter(
            'woocommerce_default_address_fields',
            [$instance, 'customize_default_address_fields']
        );
        add_action(
            'woocommerce_after_checkout_validation',
            [$instance, 'validate_checkout_address_fields'],
            10,
            2
        );

        // Checkout fields configuration
        add_filter(
            'woocommerce_checkout_fields',
            [$instance, 'configure_billing_fields'],
            9999
        );
        add_filter(
            'woocommerce_checkout_form_enctype',
            [$instance, 'set_checkout_enctype']
        );

        // Questionnaire rendering
        add_action(
            'woocommerce_after_checkout_billing_form',
            [$instance, 'output_questionnaire'],
            20
        );

        // Validation & saving
        add_action(
            'woocommerce_checkout_process',
            [$instance, 'validate_checkout_fields']
        );
        add_action(
            'woocommerce_checkout_create_order',
            [$instance, 'save_order_meta'],
            10,
            2
        );

        // Email integration
        add_action(
            'woocommerce_email_order_meta',
            [$instance, 'add_medical_data_to_emails'],
            20,
            3
        );

        // Admin order screen
        add_action(
            'woocommerce_admin_order_data_after_billing_address',
            [$instance, 'render_medical_data_in_admin_order'],
            20,
            1
        );

        // Front-end JS
        add_action(
            'wp_footer',
            [$instance, 'output_js_logic'],
            100
        );
    }

    public function declare_hpos_compatibility(): void
    {
        if (class_exists(FeaturesUtil::class)) {
            FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }

    public function add_target_blank(string $text): string
    {
        if (stripos($text, '<a ') === false) {
            return $text;
        }

        return (string) preg_replace(
            '/<a\s+/i',
            '<a target="_blank" rel="noopener noreferrer" ',
            $text,
            1
        );
    }

    public function enqueue_inline_js(): void
    {
        if (!is_checkout() || is_wc_endpoint_url('order-received')) {
            return;
        }

        wp_register_script(
            'dc-checkout-inline-js',
            false,
            ['jquery'],
            '1.0.0',
            true
        );

        wp_enqueue_script('dc-checkout-inline-js');

        wp_add_inline_script(
            'dc-checkout-inline-js',
            <<<'JS'
jQuery(function($){
    $(document.body).off(
        'click',
        'a.woocommerce-terms-and-conditions-link'
    );

    $('.woocommerce-terms-and-conditions-link')
        .removeClass('woocommerce-terms-and-conditions-link');
});
JS
        );
    }

    public function remove_inline_terms_content(): void
    {
        if (!is_checkout() && !wp_doing_ajax()) {
            return;
        }

        remove_action(
            'woocommerce_checkout_terms_and_conditions',
            'wc_terms_and_conditions_page_content',
            30
        );
    }

    public function customize_default_address_fields(array $fields): array
    {
        if (isset($fields['address_1'])) {
            $fields['address_1']['label'] = __('Straße und Hausnummer', 'doctorcura-core');
            $fields['address_1']['placeholder'] = __('z. B. Musterstraße 12A', 'doctorcura-core');
        }

        if (isset($fields['postcode'])) {
            $fields['postcode']['placeholder'] = __('z. B. 12345', 'doctorcura-core');
        }

        return $fields;
    }

    public function validate_checkout_address_fields(array $data, \WP_Error $errors): void
    {
        $address_fields = [
            'billing_address_1'  => __('Rechnungsadresse', 'doctorcura-core'),
            'shipping_address_1' => __('Lieferadresse', 'doctorcura-core'),
        ];

        $pattern = '/(\p{L}.*\d)|(\d.*\p{L})/u';

        foreach ($address_fields as $field_key => $label) {
            if (
                $field_key === 'shipping_address_1' &&
                !isset($_POST['ship_to_different_address'])
            ) {
                continue;
            }

            $address_value = isset($data[$field_key]) ? wc_clean($data[$field_key]) : '';

            if (!empty($address_value) && !preg_match($pattern, $address_value)) {
                $errors->add(
                    $field_key,
                    sprintf(
                        __('Bitte geben Sie eine gültige Straßenadresse für %s ein (z. B. Musterstraße 12).', 'doctorcura-core'),
                        $label
                    )
                );
            }
        }
    }

    private function get_health_condition_options(): array
    {
        return [
            'cvd'         => __('Herz-Kreislauf-Erkrankungen', 'doctorcura-core'),
            'hypertension'=> __('Bluthochdruck', 'doctorcura-core'),
            'diabetes'    => __('Diabetes (Typ 1 / Typ 2)', 'doctorcura-core'),
            'copd'        => __('Asthma / COPD', 'doctorcura-core'),
            'thyroid'     => __('Schilddrüsenerkrankung', 'doctorcura-core'),
            'epilepsy'    => __('Epilepsie', 'doctorcura-core'),
            'psych'       => __('Psychische Erkrankung', 'doctorcura-core'),
            'gi'          => __('Magen-Darm-Erkrankung', 'doctorcura-core'),
            'autoimmune'  => __('Autoimmunerkrankung (z. B. Rheuma)', 'doctorcura-core'),
            'cancer'      => __('Krebserkrankung (aktuell oder in der Vergangenheit)', 'doctorcura-core'),
            'infectious'  => __('Infektionskrankheiten (z. B. Hepatitis, HIV)', 'doctorcura-core'),
            'other'       => __('Sonstiges', 'doctorcura-core'),
            'none'        => __('Keine der oben genannten', 'doctorcura-core'),
        ];
    }

    public function add_medical_data_to_emails(WC_Order $order, bool $sent_to_admin, bool $plain_text): void
    {
        $gender      = $order->get_meta('_medical_gender');
        if (empty($gender)) {
            return;
        }

        $birthdate   = $order->get_meta('_medical_patient_birthdate');
        $height      = $order->get_meta('_medical_patient_height');
        $weight      = $order->get_meta('_medical_patient_weight');
        $medication  = $order->get_meta('_medical_medication_use');
        $allergies   = $order->get_meta('_medical_allergies');
        $conditions  = $order->get_meta('_medical_health_conditions');
        $other_cond  = $order->get_meta('_medical_health_conditions_other');
        $pregnancy   = $order->get_meta('_medical_pregnancy_breastfeeding');
        $attachments = $order->get_meta('_medical_attachments_urls');
        $options     = $this->get_health_condition_options();

        if (!$plain_text) : ?>
            <div style="margin-bottom: 40px; border: 1px solid #e5e5e5; padding: 20px; background-color: #f9f9f9; border-radius: 5px;">
                <h2 style="color: #2c3e50; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 18px; font-weight: bold; line-height: 130%; margin: 0 0 15px;"><?php esc_html_e('Medizinische Zusammenfassung', 'doctorcura-core'); ?></h2>
                <table cellspacing="0" cellpadding="5" style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 14px; line-height: 150%;">
                    <tr><td><strong><?php esc_html_e('Geschlecht', 'doctorcura-core'); ?>:</strong></td><td><?php echo esc_html(ucfirst((string) $gender)); ?></td></tr>
                    <?php if (!empty($birthdate)) : ?>
                        <tr><td><strong><?php esc_html_e('Geburtsdatum', 'doctorcura-core'); ?>:</strong></td><td><?php echo esc_html((string) $birthdate); ?></td></tr>
                    <?php endif; ?>
                    <tr><td><strong><?php esc_html_e('Größe', 'doctorcura-core'); ?>:</strong></td><td><?php echo esc_html((string) $height); ?> cm</td></tr>
                    <tr><td><strong><?php esc_html_e('Gewicht', 'doctorcura-core'); ?>:</strong></td><td><?php echo esc_html((string) $weight); ?> kg</td></tr>
                    <?php if ($gender === 'female' && !empty($pregnancy)) : ?>
                        <tr><td><strong><?php esc_html_e('Schwangerschaft / Stillzeit', 'doctorcura-core'); ?>:</strong></td><td><?php echo esc_html(ucfirst((string) $pregnancy)); ?></td></tr>
                    <?php endif; ?>
                    <tr><td><strong><?php esc_html_e('Eingenommene Medikamente', 'doctorcura-core'); ?>:</strong></td><td><?php echo nl2br(esc_html((string) $medication)); ?></td></tr>
                    <tr><td><strong><?php esc_html_e('Allergien', 'doctorcura-core'); ?>:</strong></td><td><?php echo nl2br(esc_html((string) $allergies)); ?></td></tr>
                    <tr><td><strong><?php esc_html_e('Erkrankungen', 'doctorcura-core'); ?>:</strong></td><td>
                        <?php
                        if (is_array($conditions)) {
                            $readable = array_map(
                                static fn($condition) => $options[$condition] ?? $condition,
                                $conditions
                            );
                            echo esc_html(implode(', ', $readable));
                        }
                        ?>
                    </td></tr>
                    <?php if (!empty($other_cond)) : ?>
                        <tr><td><strong><?php esc_html_e('Zusätzliche Angaben', 'doctorcura-core'); ?>:</strong></td><td><?php echo nl2br(esc_html((string) $other_cond)); ?></td></tr>
                    <?php endif; ?>
                    <?php if ($sent_to_admin && !empty($attachments) && is_array($attachments)) : ?>
                        <tr><td><strong><?php esc_html_e('Anhänge', 'doctorcura-core'); ?>:</strong></td><td>
                            <?php foreach ($attachments as $url) : ?>
                                <a href="<?php echo esc_url((string) $url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(basename((string) $url)); ?></a><br>
                            <?php endforeach; ?>
                        </td></tr>
                    <?php endif; ?>
                </table>
            </div>
        <?php else :
            echo "\n" . strtoupper(__('Medizinische Informationen', 'doctorcura-core')) . "\n";
            echo __('Geschlecht', 'doctorcura-core') . ': ' . ucfirst((string) $gender) . "\n";
            if (!empty($birthdate)) {
                echo __('Geburtsdatum', 'doctorcura-core') . ': ' . $birthdate . "\n";
            }
            echo __('Größe', 'doctorcura-core') . ': ' . $height . " cm\n";
            echo __('Gewicht', 'doctorcura-core') . ': ' . $weight . " kg\n";
            echo __('Eingenommene Medikamente', 'doctorcura-core') . ': ' . $medication . "\n";
            echo __('Allergien', 'doctorcura-core') . ': ' . $allergies . "\n";

            if (is_array($conditions)) {
                $readable = array_map(
                    static fn($condition) => $options[$condition] ?? $condition,
                    $conditions
                );
                echo __('Erkrankungen', 'doctorcura-core') . ': ' . implode(', ', $readable) . "\n";
            }

            echo "\n";
        endif;
    }

    private function has_genital_warts_in_cart(): bool
    {
        if (!function_exists('WC') || !WC()->cart) {
            return false;
        }

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = isset($cart_item['product_id']) ? absint($cart_item['product_id']) : 0;

            if ($product_id && has_term('genital-warts', 'product_cat', $product_id)) {
                return true;
            }
        }

        return false;
    }

    public function configure_billing_fields(array $fields): array
    {
        if (!is_checkout()) {
            return $fields;
        }

        $mandatory_billing = [
            'billing_first_name' => __('Vorname', 'doctorcura-core'),
            'billing_last_name'  => __('Nachname', 'doctorcura-core'),
            'billing_country'    => __('Land / Region', 'doctorcura-core'),
            'billing_address_1'  => __('Straße und Hausnummer', 'doctorcura-core'),
            'billing_city'       => __('Stadt', 'doctorcura-core'),
            'billing_postcode'   => __('Postleitzahl', 'doctorcura-core'),
            'billing_phone'      => __('Telefonnummer', 'doctorcura-core'),
            'billing_email'      => __('E-Mail-Adresse', 'doctorcura-core'),
        ];

        foreach ($mandatory_billing as $id => $label) {
            if (isset($fields['billing'][$id])) {
                $fields['billing'][$id]['required'] = true;
            }
        }

        $set_row_class = static function (array &$field, string $row_class): void {
            $classes = isset($field['class']) ? (array) $field['class'] : [];
            $classes = array_values(array_diff($classes, ['form-row-wide', 'form-row-first', 'form-row-last']));
            $classes[] = $row_class;
            $field['class'] = $classes;
        };

        $layout_config = [
            'billing_first_name' => [10, 'form-row-first'],
            'billing_last_name'  => [20, 'form-row-last'],
            'billing_country'    => [40, 'form-row-wide'],
            'billing_address_1'  => [50, 'form-row-wide'],
            'billing_address_2'  => [55, 'form-row-wide'],
            'billing_city'       => [60, 'form-row-first'],
            'billing_postcode'   => [61, 'form-row-last'],
            'billing_state'      => [62, 'form-row-wide'],
            'billing_phone'      => [70, 'form-row-first'],
            'billing_email'      => [71, 'form-row-last'],
        ];

        foreach ($layout_config as $id => $config) {
            if (isset($fields['billing'][$id])) {
                $fields['billing'][$id]['priority'] = $config[0];
                $set_row_class($fields['billing'][$id], $config[1]);
            }
        }

        return $fields;
    }

    public function set_checkout_enctype(): string
    {
        return 'multipart/form-data';
    }

    public function output_questionnaire(WC_Checkout $checkout): void
    {
        if (!is_checkout()) {
            return;
        }

        $get_field_value = static function ($field_name) use ($checkout) {
            if (isset($_POST[$field_name])) {
                return wp_unslash($_POST[$field_name]);
            }

            return $checkout->get_value($field_name);
        };

        $saved_conditions = $get_field_value('health_conditions');

        if (is_string($saved_conditions)) {
            $saved_conditions = array_filter(array_map('trim', explode(',', $saved_conditions)));
        }

        $saved_conditions = is_array($saved_conditions) ? $saved_conditions : [];
        ?>
        <div id="wa-medical-questionnaire-container" class="wa-checkout-section-box">
            <div id="wa-medical-questionnaire" class="wa-medical-section">
                <h3 class="wa-section-title wa-title-padding"><?php esc_html_e('Medizinische Informationen', 'doctorcura-core'); ?></h3>
                <div class="wa-medical-fields-wrapper">
                    <?php wp_nonce_field('wa_medical_questionnaire_nonce', 'wa_medical_nonce'); ?>

                    <?php
                    woocommerce_form_field('billing_gender', [
                        'type'     => 'select',
                        'label'    => __('Geschlecht', 'doctorcura-core'),
                        'required' => true,
                        'class'    => ['form-row-wide', 'wa-padding-both-1rem'],
                        'options'  => [
                            ''       => __('Bitte Geschlecht auswählen...', 'doctorcura-core'),
                            'male'   => __('Männlich', 'doctorcura-core'),
                            'female' => __('Weiblich', 'doctorcura-core'),
                            'other'  => __('Divers', 'doctorcura-core'),
                        ],
                    ], $get_field_value('billing_gender'));

                    woocommerce_form_field('patient_birthdate', [
                        'type'     => 'date',
                        'label'    => __('Geburtsdatum', 'doctorcura-core'),
                        'required' => true,
                        'class'    => ['form-row-wide', 'wa-padding-both-1rem'],
                    ], $get_field_value('patient_birthdate'));

                    woocommerce_form_field('patient_height', [
                        'type'              => 'number',
                        'label'             => __('Größe (cm)', 'doctorcura-core'),
                        'required'          => true,
                        'class'             => ['form-row-first', 'wa-height-field'],
                        'custom_attributes' => ['step' => '1', 'min' => '50', 'max' => '250'],
                    ], $get_field_value('patient_height'));

                    woocommerce_form_field('patient_weight', [
                        'type'              => 'number',
                        'label'             => __('Gewicht (kg)', 'doctorcura-core'),
                        'required'          => true,
                        'class'             => ['form-row-last', 'wa-weight-field'],
                        'custom_attributes' => ['step' => '0.1', 'min' => '30', 'max' => '300'],
                    ], $get_field_value('patient_weight'));
                    ?>

                    <div id="wa-pregnancy-wrapper" style="display:none; width:100%; clear:both;">
                        <?php
                        woocommerce_form_field('pregnancy_breastfeeding', [
                            'type'     => 'select',
                            'label'    => __('Schwangerschaft / Stillzeit', 'doctorcura-core'),
                            'required' => true,
                            'class'    => ['form-row-first', 'wa-padding-left-1rem'],
                            'options'  => [
                                ''    => __('Bitte Option auswählen...', 'doctorcura-core'),
                                'no'  => __('Nein', 'doctorcura-core'),
                                'yes' => __('Ja', 'doctorcura-core'),
                            ],
                        ], $get_field_value('pregnancy_breastfeeding'));
                        ?>
                    </div>

                    <?php
                    woocommerce_form_field('medication_use', [
                        'type'        => 'textarea',
                        'label'       => __('Nehmen Sie derzeit Medikamente ein?', 'doctorcura-core'),
                        'required'    => true,
                        'placeholder' => __('Bitte geben Sie alle Medikamente an oder schreiben Sie „Keine“.', 'doctorcura-core'),
                        'class'       => ['form-row-wide', 'wa-padding-both-1rem'],
                    ], $get_field_value('medication_use'));

                    woocommerce_form_field('allergies', [
                        'type'        => 'textarea',
                        'label'       => __('Haben Sie Allergien oder Unverträglichkeiten?', 'doctorcura-core'),
                        'required'    => true,
                        'placeholder' => __('Bitte geben Sie alle Allergien oder Unverträglichkeiten an oder schreiben Sie „Keine“.', 'doctorcura-core'),
                        'class'       => ['form-row-wide', 'wa-padding-both-1rem'],
                    ], $get_field_value('allergies'));

                    woocommerce_form_field('health_conditions', [
                        'type'     => 'hidden',
                        'required' => true,
                    ], '');
                    ?>

                    <p class="form-row form-row-wide wa-grid-label wa-padding-left-1rem" id="health_conditions_field">
                        <label>
                            <?php esc_html_e('Leiden Sie an einer der folgenden Erkrankungen?', 'doctorcura-core'); ?>
                            <abbr class="required">*</abbr>
                        </label>
                    </p>

                    <div class="wa-conditions-grid wa-padding-left-1rem" id="wa-conditions-grid">
                        <?php foreach ($this->get_health_condition_options() as $key => $label) : ?>
                            <?php
                            $class = ($key === 'none') ? 'wa-checkbox-none' : 'wa-checkbox-condition';
                            if ($key === 'other') {
                                $class .= ' wa-checkbox-other';
                            }
                            ?>
                            <label class="wa-checkbox-item">
                                <input
                                    type="checkbox"
                                    name="health_conditions[]"
                                    value="<?php echo esc_attr($key); ?>"
                                    class="<?php echo esc_attr($class); ?>"
                                    <?php checked(in_array($key, $saved_conditions, true)); ?>
                                >
                                <?php echo esc_html($label); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div id="wa-other-condition-wrapper" style="display:none; clear:both;" class="wa-padding-both-1rem">
                        <?php
                        woocommerce_form_field('health_conditions_other', [
                            'type'        => 'textarea',
                            'label'       => __('Bitte geben Sie weitere Erkrankungen an', 'doctorcura-core'),
                            'placeholder' => __('Bitte beschreiben Sie Ihre weiteren Erkrankungen hier.', 'doctorcura-core'),
                            'class'       => ['form-row-wide'],
                        ], $get_field_value('health_conditions_other'));
                        ?>
                    </div>

                    <?php if ($this->has_genital_warts_in_cart()) : ?>
                        <div id="wa-medical-upload-container" class="wa-padding-both-1rem" style="border:1px dashed #d5d8dc; background:#fdfdfd; padding:15px 15px 0; margin:25px 0 0; clear:both;">
                            <label><?php esc_html_e('Bilder hochladen (optional)', 'doctorcura-core'); ?></label>
                            <input type="file" name="medical_attachments[]" multiple accept="image/jpeg,image/png,image/gif,application/pdf">
                            <p class="description"><?php esc_html_e('Max. 5 MB pro Datei. Zulässige Formate: JPG, PNG, GIF, PDF.', 'doctorcura-core'); ?></p>
                        </div>
                    <?php endif; ?>

                    <div id="wa-final-verification-wrapper" class="wa-final-verification-section">
                        <h3 class="wa-section-title confirmation-title wa-padding-left-1rem"><?php esc_html_e('Abschließende Bestätigung', 'doctorcura-core'); ?></h3>
                        <?php
                        woocommerce_form_field('questionnaire_truth', [
                            'type'     => 'checkbox',
                            'label'    => __('Ich bestätige, dass alle Angaben wahrheitsgemäß sind.', 'doctorcura-core'),
                            'required' => true,
                            'class'    => ['form-row-wide', 'wa-confirmation-row', 'wa-padding-both-1rem'],
                        ], $get_field_value('questionnaire_truth'));
                        ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_medical_data_in_admin_order(WC_Order $order): void
    {
        $gender      = $order->get_meta('_medical_gender');
        $birthdate   = $order->get_meta('_medical_patient_birthdate');
        $height      = $order->get_meta('_medical_patient_height');
        $weight      = $order->get_meta('_medical_patient_weight');
        $medication  = $order->get_meta('_medical_medication_use');
        $allergies   = $order->get_meta('_medical_allergies');
        $conditions  = $order->get_meta('_medical_health_conditions');
        $other_cond  = $order->get_meta('_medical_health_conditions_other');
        $pregnancy   = $order->get_meta('_medical_pregnancy_breastfeeding');
        $attachments = $order->get_meta('_medical_attachments_urls');

        if (
            empty($gender) &&
            empty($birthdate) &&
            empty($height) &&
            empty($weight) &&
            empty($medication) &&
            empty($allergies) &&
            empty($conditions)
        ) {
            return;
        }

        $options = $this->get_health_condition_options();

        echo '<div class="order_data_column" style="width:100%; clear:both; margin-top:20px;">';
        echo '<h3>' . esc_html__('Medizinische Informationen', 'doctorcura-core') . '</h3>';
        echo '<div style="padding:12px; background:#fff; border:1px solid #ddd; border-radius:4px;">';

        if (!empty($gender)) {
            echo '<p><strong>' . esc_html__('Geschlecht', 'doctorcura-core') . ':</strong> ' . esc_html(ucfirst((string) $gender)) . '</p>';
        }

        if (!empty($birthdate)) {
            echo '<p><strong>' . esc_html__('Geburtsdatum', 'doctorcura-core') . ':</strong> ' . esc_html((string) $birthdate) . '</p>';
        }

        if (!empty($height)) {
            echo '<p><strong>' . esc_html__('Größe', 'doctorcura-core') . ':</strong> ' . esc_html((string) $height) . ' cm</p>';
        }

        if (!empty($weight)) {
            echo '<p><strong>' . esc_html__('Gewicht', 'doctorcura-core') . ':</strong> ' . esc_html((string) $weight) . ' kg</p>';
        }

        if ($gender === 'female' && !empty($pregnancy)) {
            echo '<p><strong>' . esc_html__('Schwangerschaft / Stillzeit', 'doctorcura-core') . ':</strong> ' . esc_html(ucfirst((string) $pregnancy)) . '</p>';
        }

        if (!empty($medication)) {
            echo '<p><strong>' . esc_html__('Eingenommene Medikamente', 'doctorcura-core') . ':</strong><br>' . nl2br(esc_html((string) $medication)) . '</p>';
        }

        if (!empty($allergies)) {
            echo '<p><strong>' . esc_html__('Allergien', 'doctorcura-core') . ':</strong><br>' . nl2br(esc_html((string) $allergies)) . '</p>';
        }

        if (is_array($conditions) && !empty($conditions)) {
            $readable = array_map(
                static fn($condition) => $options[$condition] ?? $condition,
                $conditions
            );
            echo '<p><strong>' . esc_html__('Erkrankungen', 'doctorcura-core') . ':</strong> ' . esc_html(implode(', ', $readable)) . '</p>';
        }

        if (!empty($other_cond)) {
            echo '<p><strong>' . esc_html__('Zusätzliche Angaben', 'doctorcura-core') . ':</strong><br>' . nl2br(esc_html((string) $other_cond)) . '</p>';
        }

        if (is_array($attachments) && !empty($attachments)) {
            echo '<p><strong>' . esc_html__('Anhänge', 'doctorcura-core') . ':</strong><br>';
            foreach ($attachments as $url) {
                echo '<a href="' . esc_url((string) $url) . '" target="_blank" rel="noopener noreferrer">' . esc_html(basename((string) $url)) . '</a><br>';
            }
            echo '</p>';
        }

        echo '</div>';
        echo '</div>';
    }

    private function add_field_error(string $message, string $field_id): void
    {
        wc_add_notice($message, 'error');

        if (!isset($GLOBALS['wa_checkout_errors'])) {
            $GLOBALS['wa_checkout_errors'] = [];
        }

        $GLOBALS['wa_checkout_errors'][$field_id] = $message;
    }

    public function validate_checkout_fields(): void
    {
        if (!is_checkout()) {
            return;
        }

        $nonce = isset($_POST['wa_medical_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['wa_medical_nonce']))
            : '';

        if (empty($nonce) || !wp_verify_nonce($nonce, 'wa_medical_questionnaire_nonce')) {
            wc_add_notice(
                __('Sicherheitsprüfung fehlgeschlagen. Bitte aktualisieren Sie die Seite und versuchen Sie es erneut.', 'doctorcura-core'),
                'error'
            );
            return;
        }

        $medical_fields = [
            'billing_gender'    => __('Geschlecht', 'doctorcura-core'),
            'patient_height'    => __('Größe', 'doctorcura-core'),
            'patient_weight'    => __('Gewicht', 'doctorcura-core'),
            'patient_birthdate' => __('Geburtsdatum', 'doctorcura-core'),
            'medication_use'    => __('Eingenommene Medikamente', 'doctorcura-core'),
            'allergies'         => __('Allergien', 'doctorcura-core'),
        ];

        foreach ($medical_fields as $id => $label) {
            $value = isset($_POST[$id])
                ? trim(sanitize_text_field(wp_unslash($_POST[$id])))
                : '';

            if ($value === '') {
                $this->add_field_error(
                    sprintf(__('Das Feld „%s“ ist erforderlich.', 'doctorcura-core'), $label),
                    $id
                );
            }
        }

        $health_conditions = isset($_POST['health_conditions']) && is_array($_POST['health_conditions'])
            ? array_map('sanitize_text_field', wp_unslash($_POST['health_conditions']))
            : [];

        $allowed_conditions = array_keys($this->get_health_condition_options());
        $health_conditions  = array_intersect($health_conditions, $allowed_conditions);

        if (empty($health_conditions)) {
            $this->add_field_error(
                __('Bitte wählen Sie mindestens eine Erkrankung aus.', 'doctorcura-core'),
                'health_conditions'
            );
        }

        if (!isset($_POST['questionnaire_truth'])) {
            $this->add_field_error(
                __('Bitte bestätigen Sie die Richtigkeit Ihrer Angaben.', 'doctorcura-core'),
                'questionnaire_truth'
            );
        }

        if (
            !empty($health_conditions) &&
            in_array('none', $health_conditions, true) &&
            count($health_conditions) > 1
        ) {
            $this->add_field_error(
                __('„Keine der oben genannten“ kann nicht mit anderen Optionen kombiniert werden.', 'doctorcura-core'),
                'health_conditions'
            );
        }

        $gender = isset($_POST['billing_gender'])
            ? sanitize_text_field(wp_unslash($_POST['billing_gender']))
            : '';

        $pregnancy = isset($_POST['pregnancy_breastfeeding'])
            ? sanitize_text_field(wp_unslash($_POST['pregnancy_breastfeeding']))
            : '';

        if ($gender === 'female' && $pregnancy === '') {
            $this->add_field_error(
                __('Bitte geben Sie an, ob Sie schwanger sind oder stillen.', 'doctorcura-core'),
                'pregnancy_breastfeeding'
            );
        }

        if (!empty($health_conditions) && in_array('other', $health_conditions, true)) {
            $other_text = isset($_POST['health_conditions_other'])
                ? trim(sanitize_textarea_field(wp_unslash($_POST['health_conditions_other'])))
                : '';

            if ($other_text === '') {
                $this->add_field_error(
                    __('Bitte beschreiben Sie Ihre weiteren Erkrankungen.', 'doctorcura-core'),
                    'health_conditions_other'
                );
            }
        }
    }

    public function save_order_meta(WC_Order $order, array $data): void
    {
        if (!is_checkout()) {
            return;
        }

        $fields_to_save = [
            'billing_gender'          => '_medical_gender',
            'patient_height'          => '_medical_patient_height',
            'patient_weight'          => '_medical_patient_weight',
            'patient_birthdate'       => '_medical_patient_birthdate',
            'medication_use'          => '_medical_medication_use',
            'allergies'               => '_medical_allergies',
            'health_conditions'       => '_medical_health_conditions',
            'health_conditions_other' => '_medical_health_conditions_other',
            'pregnancy_breastfeeding' => '_medical_pregnancy_breastfeeding',
        ];

        foreach ($fields_to_save as $post_key => $meta_key) {
            if (!isset($_POST[$post_key])) {
                continue;
            }

            $value = wp_unslash($_POST[$post_key]);

            if (is_array($value)) {
                $order->update_meta_data($meta_key, array_map('sanitize_text_field', $value));
                continue;
            }

            $sanitized_value = in_array($post_key, ['medication_use', 'allergies', 'health_conditions_other'], true)
                ? sanitize_textarea_field((string) $value)
                : sanitize_text_field((string) $value);

            $order->update_meta_data($meta_key, $sanitized_value);
        }

        $this->handle_file_uploads($order);
    }

    private function handle_file_uploads(WC_Order $order): void
    {
        if (
            !$this->has_genital_warts_in_cart() ||
            empty($_FILES['medical_attachments']['name'][0])
        ) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $files = $_FILES['medical_attachments'];
        $urls  = [];

        foreach ($files['name'] as $key => $name) {
            if ($files['error'][$key] !== UPLOAD_ERR_OK) {
                continue;
            }

            $file_size = isset($files['size'][$key]) ? (int) $files['size'][$key] : 0;
            if ($file_size <= 0 || $file_size > 5 * MB_IN_BYTES) {
                continue;
            }

            $file_name = sanitize_file_name((string) $files['name'][$key]);
            $file_type = wp_check_filetype_and_ext(
                (string) $files['tmp_name'][$key],
                $file_name,
                [
                    'jpg|jpeg|jpe' => 'image/jpeg',
                    'png'          => 'image/png',
                    'gif'          => 'image/gif',
                    'pdf'          => 'application/pdf',
                ]
            );

            if (empty($file_type['ext']) || empty($file_type['type'])) {
                continue;
            }

            $file = [
                'name'     => $file_name,
                'type'     => $file_type['type'],
                'tmp_name' => $files['tmp_name'][$key],
                'error'    => $files['error'][$key],
                'size'     => $file_size,
            ];

            $attach_id = media_handle_sideload($file, $order->get_id());

            if (!is_wp_error($attach_id)) {
                $url = wp_get_attachment_url($attach_id);
                if ($url) {
                    $urls[] = $url;
                }
            }
        }

        if (!empty($urls)) {
            $order->update_meta_data('_medical_attachments_urls', $urls);
        }
    }

    public function output_js_logic(): void
    {
        if (!is_checkout() || is_wc_endpoint_url('order-received')) {
            return;
        }

        $error_map = $GLOBALS['wa_checkout_errors'] ?? [];
        ?>
        <script type="text/javascript">
            var waCheckoutErrors = <?php echo wp_json_encode($error_map); ?>;
            (function($){
                'use strict';

                const updateUI = function () {
                    const gender = $('#billing_gender').val();
                    const otherChecked = $('.wa-checkbox-other').is(':checked');

                    $('#wa-pregnancy-wrapper').toggle(gender === 'female');
                    $('#wa-other-condition-wrapper').toggle(otherChecked);
                };

                $(document).ready(function () {
                    const $medicalBox = $('#wa-medical-questionnaire-container');
                    const $billingWrapper = $('.woocommerce-billing-fields').parent();

                    if ($medicalBox.length && $billingWrapper.length) {
                        $medicalBox.insertAfter($billingWrapper);
                    }

                    updateUI();
                });

                $(document).on('change', '#billing_gender, .wa-checkbox-other', updateUI);

                $(document).on('checkout_error', function () {
                    $('.wa-inline-error').remove();

                    setTimeout(function () {
                        if (typeof waCheckoutErrors !== 'undefined') {
                            $.each(waCheckoutErrors, function(fieldId, message) {
                                let $target = $('#' + fieldId + '_field');

                                if (fieldId === 'health_conditions') {
                                    $target = $('#wa-conditions-grid');
                                }

                                $target.addClass('woocommerce-invalid');

                                if ($target.find('.wa-inline-error').length === 0) {
                                    $target.append('<span class="wa-inline-error" style="color:red; display:block; margin-top:5px;">' + message + '</span>');
                                }
                            });
                        }
                    }, 50);
                });
            })(jQuery);
        </script>
        <?php
    }
}

CheckoutHandler::init();

