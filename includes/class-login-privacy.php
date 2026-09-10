<?php

declare(strict_types=1);

namespace DCAF;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Prevent public WooCommerce login errors from revealing whether an account exists.
 */
final class Login_Privacy {
    public static function hooks(): void {
        add_filter( 'login_errors', [ __CLASS__, 'normalize_frontend_login_error' ], 999 );
    }

    public static function normalize_frontend_login_error( string $message ): string {
        if ( is_admin() || empty( $_POST['login'] ) ) {
            return $message;
        }

        $nonce = '';
        if ( isset( $_REQUEST['woocommerce-login-nonce'] ) && is_scalar( $_REQUEST['woocommerce-login-nonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( (string) $_REQUEST['woocommerce-login-nonce'] ) );
        } elseif ( isset( $_REQUEST['_wpnonce'] ) && is_scalar( $_REQUEST['_wpnonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( (string) $_REQUEST['_wpnonce'] ) );
        }

        if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'woocommerce-login' ) ) {
            return $message;
        }

        $username = isset( $_POST['username'] ) && is_scalar( $_POST['username'] )
            ? trim( (string) wp_unslash( $_POST['username'] ) )
            : '';
        $password = isset( $_POST['password'] ) && is_scalar( $_POST['password'] )
            ? (string) wp_unslash( $_POST['password'] )
            : '';

        if ( '' === $username || '' === $password ) {
            return $message;
        }

        $generic = Locale::t( 'login_failed_generic' );

        if ( 'login_failed_generic' === $generic ) {
            $generic = match ( Locale::get() ) {
                'de_DE' => 'Anmeldung fehlgeschlagen. Bitte prüfe deine Anmeldedaten und versuche es erneut.',
                'fr_FR' => 'Échec de la connexion. Vérifiez vos informations de connexion et réessayez.',
                default => 'Login failed. Please check your login details and try again.',
            };
        }

        return esc_html( $generic );
    }
}
