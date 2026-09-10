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
    }

    public static function lost_password_confirmation_message( string $message ): string {
        return __(
            'Aus Sicherheitsgründen bestätigen wir nicht, ob eine E-Mail-Adresse oder ein Konto registriert ist. Falls ein passendes Konto existiert, wurde eine E-Mail mit einem Link zum Zurücksetzen des Passworts gesendet. Bitte prüfen Sie auch Ihren Spam-Ordner.',
            'doctorcura-core'
        );
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
                'label'    => __(
                    'I confirm that I understand that, if the doctor issues a prescription, the decision will be based on the information I provide through the validated medical questionnaire. I understand that providing false, misleading, or incomplete information may be dangerous to my health and may prevent the physician from conducting an accurate and appropriate medical assessment.',
                    'doctorcura-core'
                ),
                'required' => true,
                'class'    => [ 'form-row-wide', 'wa-confirmation-row', 'wa-padding-both-1rem' ],
            ],
            $checked
        );

        return $field . $acknowledgement;
    }

    public static function validate_prescription_acknowledgement(): void {
        if ( isset( $_POST[ self::PRESCRIPTION_ACK_FIELD ] ) ) {
            return;
        }

        $message = __(
            'Please confirm that you understand how the medical questionnaire is used for a prescribing decision and the risks of providing false, misleading, or incomplete information.',
            'doctorcura-core'
        );

        wc_add_notice( $message, 'error' );

        if ( ! isset( $GLOBALS['wa_checkout_errors'] ) || ! is_array( $GLOBALS['wa_checkout_errors'] ) ) {
            $GLOBALS['wa_checkout_errors'] = [];
        }

        $GLOBALS['wa_checkout_errors'][ self::PRESCRIPTION_ACK_FIELD ] = $message;
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
