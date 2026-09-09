<?php

declare(strict_types=1);

namespace DCAF;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Login_Privacy {
    public static function hooks(): void {
        add_filter( 'login_errors', [ __CLASS__, 'normalize_frontend_login_error' ], 999 );
    }

    public static function normalize_frontend_login_error( string $message ): string {
        if ( is_admin() ) {
            return $message;
        }

        if ( empty( $_POST['login'] ) || empty( $_POST['woocommerce-login-nonce'] ) ) {
            return $message;
        }

        $username = isset( $_POST['username'] ) ? trim( (string) wp_unslash( $_POST['username'] ) ) : '';
        $password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';

        if ( '' === $username || '' === $password ) {
            return $message;
        }

        return esc_html( Locale::t( 'login_failed_generic' ) );
    }
}
