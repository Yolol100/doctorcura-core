<?php

declare(strict_types=1);

namespace DCAF;

use WP_Error;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Auth_Flow {
    public static function hooks(): void {
        add_action( 'template_redirect', [ __CLASS__, 'redirect_reset_link_sent_from_account' ], 20 );
        add_action( 'init', [ __CLASS__, 'redirect_reset_link_sent_global' ], 20 );
        remove_action( 'template_redirect', 'wc_lost_password_redirect', 10 );
        add_filter( 'woocommerce_lost_password_redirect', [ __CLASS__, 'filter_lost_password_redirect' ], 999 );
        add_action( 'wp', [ __CLASS__, 'clear_notices_on_verification_page' ], 1 );

        add_filter( 'woocommerce_email_enabled_customer_new_account', '__return_false' );
        add_filter( 'woocommerce_registration_auth_new_customer', '__return_false' );
        add_filter( 'woocommerce_registration_generate_password', '__return_false' );

        add_action( 'woocommerce_created_customer', [ __CLASS__, 'handle_customer_created' ], 10 );
        add_action( 'wp_loaded', [ __CLASS__, 'redirect_after_register_submit' ], 20 );
        add_action( 'template_redirect', [ __CLASS__, 'handle_verification_link' ], 5 );
        add_filter( 'wp_authenticate_user', [ __CLASS__, 'block_login_if_not_verified' ], 30 );
        add_action( 'wp', [ __CLASS__, 'render_verification_notices' ] );

        add_filter( 'woocommerce_email_enabled_customer_reset_password', '__return_false' );
        add_action( 'woocommerce_reset_password_notification', [ __CLASS__, 'handle_reset_password_notification' ], 20, 2 );
        add_action( 'woocommerce_customer_reset_password', [ __CLASS__, 'handle_customer_reset_password' ], 10 );

        add_filter( 'wp_password_change_notification_email', '__return_false' );
        add_filter( 'send_email_change_email', '__return_false' );
        add_filter( 'wp_new_user_notification_email_admin', '__return_false' );
    }

    public static function is_reset_password_submit(): bool {
        return isset( $_POST['wc_reset_password'] );
    }

    public static function redirect_reset_link_sent_from_account(): void {
        if ( is_account_page() && isset( $_GET['reset-link-sent'] ) ) {
            wc_clear_notices();
            wp_safe_redirect( Mailer::reset_success_url() );
            exit;
        }
    }

    public static function redirect_reset_link_sent_global(): void {
        if ( isset( $_GET['reset-link-sent'] ) ) {
            wc_clear_notices();
            wp_safe_redirect( Mailer::reset_success_url() );
            exit;
        }
    }

    public static function filter_lost_password_redirect( string $redirect ): string {
        if ( self::is_reset_password_submit() || isset( $_GET['key'], $_GET['login'] ) ) {
            return $redirect;
        }

        return $redirect;
    }

    public static function clear_notices_on_verification_page(): void {
        if ( is_page( (string) setting( 'verification_page_slug' ) ) ) {
            remove_action( 'woocommerce_before_main_content', 'woocommerce_output_all_notices', 10 );
            wc_clear_notices();
        }
    }

    public static function handle_customer_created( int $customer_id ): void {
        Locale::capture_for_user( $customer_id );
        Mailer::send_verification_email( $customer_id );
        Mailer::send_account_notice_email( $customer_id, wc_get_page_permalink( 'myaccount' ) );
    }

    public static function redirect_after_register_submit(): void {
        if ( isset( $_POST['register'] ) ) {
            wp_safe_redirect( Mailer::verification_url() );
            exit;
        }
    }

    public static function handle_verification_link(): void {
        if ( ! isset( $_GET['verify_email'], $_GET['uid'], $_GET['token'] ) ) {
            return;
        }

        $user_id = absint( $_GET['uid'] );
        $token   = sanitize_text_field( wp_unslash( $_GET['token'] ) );
        $hash    = get_user_meta( $user_id, (string) setting( 'verify_token_meta_key' ), true );
        $time    = (int) get_user_meta( $user_id, (string) setting( 'verify_time_meta_key' ), true );
        $used    = get_user_meta( $user_id, (string) setting( 'verify_used_meta_key' ), true );

        if (
            is_string( $hash ) &&
            '' !== $hash &&
            'yes' !== $used &&
            wp_check_password( $token, $hash ) &&
            ( time() - $time < (int) setting( 'verification_expiration' ) )
        ) {
            update_user_meta( $user_id, (string) setting( 'verified_meta_key' ), 'yes' );
            update_user_meta( $user_id, (string) setting( 'verify_used_meta_key' ), 'yes' );
            delete_user_meta( $user_id, (string) setting( 'verify_token_meta_key' ) );

            Mailer::send_set_password_email( $user_id );

            wp_safe_redirect( add_query_arg( 'verified', '1', Mailer::verification_url() ) );
            exit;
        }

        wp_safe_redirect( add_query_arg( 'expired', '1', Mailer::verification_url() ) );
        exit;
    }

    public static function block_login_if_not_verified( WP_User|WP_Error $user ): WP_User|WP_Error {
        if ( is_wp_error( $user ) ) {
            return $user;
        }

        if ( $user instanceof WP_User && user_can( $user, 'manage_options' ) ) {
            return $user;
        }

        if (
            $user instanceof WP_User &&
            isset( $_POST['username'], $_POST['password'] ) &&
            'yes' !== get_user_meta( $user->ID, (string) setting( 'verified_meta_key' ), true )
        ) {
            return new WP_Error(
                'not_verified',
                Locale::t( 'not_verified_error', $user->ID )
            );
        }

        return $user;
    }

    public static function render_verification_notices(): void {
        if ( ! is_page( (string) setting( 'verification_page_slug' ) ) ) {
            return;
        }

        if ( isset( $_GET['verified'] ) ) {
            wc_add_notice( Locale::t( 'verified_notice' ), 'success' );
        }

        if ( isset( $_GET['expired'] ) ) {
            wc_add_notice( Locale::t( 'expired_notice' ), 'error' );
        }
    }

    public static function handle_reset_password_notification( string $user_login, string $reset_key ): void {
        Mailer::send_custom_reset_password_email( $user_login, $reset_key );
    }

    public static function handle_customer_reset_password( mixed $user ): void {
        if ( ! $user instanceof WP_User ) {
            return;
        }

        update_user_meta( $user->ID, (string) setting( 'verified_meta_key' ), 'yes' );

        $existing_time = (int) get_user_meta( $user->ID, (string) setting( 'verify_time_meta_key' ), true );
        if ( $existing_time > 0 ) {
            update_user_meta( $user->ID, (string) setting( 'verify_used_meta_key' ), 'yes' );
        }

        delete_user_meta( $user->ID, (string) setting( 'verify_token_meta_key' ) );
    }
}
