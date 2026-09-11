<?php

declare(strict_types=1);

namespace DCAF;

use WC_Order;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Security/privacy behavior for lost-password requests and the final medical
 * questionnaire acknowledgement required before checkout can complete.
 */
final class Security_Consent {
    private const PRESCRIPTION_ACK_FIELD = 'questionnaire_prescription_ack';

    /**
     * German remains the canonical/source language. English and French are
     * fallbacks for GTranslate's on-the-fly mode when the appended field is not
     * picked up by the first translation pass.
     */
    private const PRESCRIPTION_ACK_LABELS = [
        'de' => 'Ich bestätige, dass ich verstehe, dass die Entscheidung über die Ausstellung eines Rezepts auf den Angaben beruht, die ich im validierten medizinischen Fragebogen mache. Mir ist bewusst, dass falsche, irreführende oder unvollständige Angaben meine Gesundheit gefährden und den Arzt daran hindern können, eine genaue und angemessene medizinische Beurteilung vorzunehmen.',
        'en' => 'I confirm that I understand that, if the doctor issues a prescription, the decision will be based on the information I provide through the validated medical questionnaire. I understand that providing false, misleading, or incomplete information may be dangerous to my health and may prevent the physician from conducting an accurate and appropriate medical assessment.',
        'fr' => 'Je confirme que je comprends que la décision de délivrer une ordonnance repose sur les informations que je fournis dans le questionnaire médical validé. Je suis conscient que des informations fausses, trompeuses ou incomplètes peuvent mettre ma santé en danger et empêcher le médecin d’effectuer une évaluation médicale précise et appropriée.',
    ];

    private const PRESCRIPTION_ACK_ERRORS = [
        'de' => 'Bitte bestätigen Sie, dass Sie verstehen, wie der medizinische Fragebogen für die Entscheidung über eine mögliche Verschreibung verwendet wird und welche Risiken falsche, irreführende oder unvollständige Angaben mit sich bringen.',
        'en' => 'Please confirm that you understand how the medical questionnaire is used for a prescribing decision and the risks of providing false, misleading, or incomplete information.',
        'fr' => 'Veuillez confirmer que vous comprenez comment le questionnaire médical est utilisé pour une décision de prescription et les risques liés à des informations fausses, trompeuses ou incomplètes.',
    ];

    public static function hooks(): void {
        // Auth_Flow registers these redirects at plugins_loaded priority 20.
        // Remove them here so reset-link-sent remains on WooCommerce's own
        // lost-password page instead of being sent to a separate success page.
        remove_action(
            'template_redirect',
            [ Auth_Flow::class, 'redirect_reset_link_sent_from_account' ],
            20
        );
        remove_action(
            'init',
            [ Auth_Flow::class, 'redirect_reset_link_sent_global' ],
            20
        );

        // Existing and unknown accounts already converge on reset-link-sent via
        // Auth_Flow::normalize_lost_password_response(). Always show the same
        // public message so account existence cannot be inferred from the UI.
        add_filter(
            'woocommerce_lost_password_confirmation_message',
            [ __CLASS__, 'lost_password_confirmation_message' ],
            999
        );

        // Append the additional acknowledgement directly after the existing
        // questionnaire_truth field without duplicating the questionnaire code.
        add_filter(
            'woocommerce_form_field',
            [ __CLASS__, 'append_prescription_acknowledgement' ],
            20,
            4
        );
        add_action(
            'woocommerce_checkout_process',
            [ __CLASS__, 'validate_prescription_acknowledgement' ],
            20
        );
        add_action(
            'woocommerce_checkout_create_order',
            [ __CLASS__, 'save_confirmations' ],
            20,
            2
        );

        // GTranslate can miss content appended by WooCommerce filters during its
        // first on-the-fly translation pass. Keep German as source text and only
        // apply a small fallback when the selected GTranslate language is known.
        add_action(
            'wp_footer',
            [ __CLASS__, 'output_gtranslate_fallback' ],
            120
        );
    }

    public static function lost_password_confirmation_message( string $message ): string {
        return __(
            'Aus Sicherheitsgründen bestätigen wir nicht, ob eine E-Mail-Adresse oder ein Konto registriert ist. Falls ein passendes Konto existiert, wurde eine E-Mail mit einem Link zum Zurücksetzen des Passworts gesendet. Bitte prüfen Sie auch Ihren Spam-Ordner.',
            'doctorcura-core'
        );
    }

    private static function frontend_language(): string {
        if ( class_exists( Locale::class ) ) {
            $request_language = Locale::from_request();

            if ( in_array( $request_language, [ 'de', 'en', 'fr' ], true ) ) {
                return $request_language;
            }
        }

        return 'de';
    }

    private static function prescription_acknowledgement_label(): string {
        $language = self::frontend_language();

        return self::PRESCRIPTION_ACK_LABELS[ $language ] ?? self::PRESCRIPTION_ACK_LABELS['de'];
    }

    private static function prescription_acknowledgement_error(): string {
        $language = self::frontend_language();

        return self::PRESCRIPTION_ACK_ERRORS[ $language ] ?? self::PRESCRIPTION_ACK_ERRORS['de'];
    }

    public static function append_prescription_acknowledgement(
        string $field,
        string $key,
        array $args,
        mixed $value
    ): string {
        if ( 'questionnaire_truth' !== $key || ! function_exists( 'woocommerce_form_field' ) ) {
            return $field;
        }

        $checked = isset( $_POST[ self::PRESCRIPTION_ACK_FIELD ] ) ? '1' : '';

        $acknowledgement = woocommerce_form_field(
            self::PRESCRIPTION_ACK_FIELD,
            [
                'type'     => 'checkbox',
                'label'    => self::prescription_acknowledgement_label(),
                'required' => true,
                'return'   => true,
                'class'    => [ 'form-row-wide', 'wa-confirmation-row', 'wa-padding-both-1rem' ],
            ],
            $checked
        );

        // WooCommerce echoes fields by default and may therefore return null.
        // `return => true` requests the HTML string; keep this guard as a safe
        // fallback so a WooCommerce change can never cause a TypeError here.
        if ( ! is_string( $acknowledgement ) ) {
            $acknowledgement = '';
        }

        // Explicitly keep the field translatable. The data attribute documents
        // that German is the canonical source language without blocking GTranslate.
        $field_id = self::PRESCRIPTION_ACK_FIELD . '_field';
        $pattern  = '/\bid="' . preg_quote( $field_id, '/' ) . '"/';
        $replace  = 'id="' . $field_id . '" translate="yes" data-dc-source-language="de"';
        $updated  = preg_replace( $pattern, $replace, $acknowledgement, 1 );

        if ( is_string( $updated ) ) {
            $acknowledgement = $updated;
        }

        return $field . $acknowledgement;
    }

    public static function validate_prescription_acknowledgement(): void {
        if ( isset( $_POST[ self::PRESCRIPTION_ACK_FIELD ] ) ) {
            return;
        }

        $message = self::prescription_acknowledgement_error();

        wc_add_notice( $message, 'error' );

        if ( ! isset( $GLOBALS['wa_checkout_errors'] ) || ! is_array( $GLOBALS['wa_checkout_errors'] ) ) {
            $GLOBALS['wa_checkout_errors'] = [];
        }

        $GLOBALS['wa_checkout_errors'][ self::PRESCRIPTION_ACK_FIELD ] = $message;
    }

    public static function output_gtranslate_fallback(): void {
        if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
            return;
        }

        if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
            return;
        }

        $field_id = self::PRESCRIPTION_ACK_FIELD . '_field';
        $labels   = self::PRESCRIPTION_ACK_LABELS;
        ?>
        <script id="dc-prescription-ack-gtranslate-fallback">
        (function () {
            'use strict';

            var fieldId = <?php echo wp_json_encode( $field_id ); ?>;
            var labels = <?php echo wp_json_encode( $labels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?>;
            var supported = Object.keys(labels);

            function normalizeLanguage(value) {
                if (!value) {
                    return '';
                }

                return String(value).toLowerCase().replace('_', '-').split('-')[0];
            }

            function readCookie(name) {
                var prefix = name + '=';
                var parts = document.cookie ? document.cookie.split(';') : [];

                for (var i = 0; i < parts.length; i++) {
                    var item = parts[i].trim();

                    if (item.indexOf(prefix) === 0) {
                        return item.substring(prefix.length);
                    }
                }

                return '';
            }

            function selectedLanguage() {
                var pathParts = window.location.pathname.split('/').filter(Boolean);
                var pathLanguage = normalizeLanguage(pathParts.length ? pathParts[0] : '');

                if (supported.indexOf(pathLanguage) !== -1) {
                    return pathLanguage;
                }

                var googtrans = readCookie('googtrans');

                if (googtrans) {
                    var cookieParts = decodeURIComponent(googtrans).split('/').filter(Boolean);
                    var cookieLanguage = normalizeLanguage(cookieParts.length ? cookieParts[cookieParts.length - 1] : '');

                    if (supported.indexOf(cookieLanguage) !== -1) {
                        return cookieLanguage;
                    }
                }

                var gtranslateLanguage = normalizeLanguage(decodeURIComponent(readCookie('gtranslate_lang') || ''));

                if (supported.indexOf(gtranslateLanguage) !== -1) {
                    return gtranslateLanguage;
                }

                var combo = document.querySelector('select.goog-te-combo');
                var comboLanguage = normalizeLanguage(combo && combo.value ? combo.value : '');

                if (supported.indexOf(comboLanguage) !== -1) {
                    return comboLanguage;
                }

                return 'de';
            }

            function acknowledgementTextNode() {
                var field = document.getElementById(fieldId);

                if (!field) {
                    return null;
                }

                return field.querySelector('label span');
            }

            function applyFallback() {
                var node = acknowledgementTextNode();

                if (!node) {
                    return;
                }

                var language = selectedLanguage();
                var desired = labels[language];

                if (!desired) {
                    return;
                }

                var current = node.textContent.trim();
                var known = Object.keys(labels).map(function (key) {
                    return labels[key];
                });

                // Never overwrite a translation produced by GTranslate itself.
                // Only replace the canonical German text or one of our own fallbacks.
                if (current !== desired && known.indexOf(current) !== -1) {
                    node.textContent = desired;
                }
            }

            function scheduleFallback() {
                [0, 150, 500, 1200].forEach(function (delay) {
                    window.setTimeout(applyFallback, delay);
                });
            }

            function isTranslationControl(target) {
                if (!target || !target.closest) {
                    return false;
                }

                return Boolean(target.closest(
                    '.gtranslate_wrapper, .gt_switcher, .gt_selected, .gt_option, .gflag, .nturl, .gt_selector, select.goog-te-combo'
                ));
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', scheduleFallback, { once: true });
            } else {
                scheduleFallback();
            }

            document.addEventListener('click', function (event) {
                if (isTranslationControl(event.target)) {
                    scheduleFallback();
                }
            }, true);

            document.addEventListener('change', function (event) {
                if (isTranslationControl(event.target)) {
                    scheduleFallback();
                }
            }, true);

            if (window.jQuery) {
                window.jQuery(document.body).on('updated_checkout', scheduleFallback);
            }
        }());
        </script>
        <?php
    }

    public static function save_confirmations( WC_Order $order, array $data ): void {
        if ( isset( $_POST['questionnaire_truth'] ) ) {
            $order->update_meta_data( '_medical_questionnaire_truth', 'yes' );
        }

        if ( isset( $_POST[ self::PRESCRIPTION_ACK_FIELD ] ) ) {
            $order->update_meta_data( '_medical_prescription_acknowledgement', 'yes' );
        }
    }
}

add_action( 'plugins_loaded', [ Security_Consent::class, 'hooks' ], 30 );
