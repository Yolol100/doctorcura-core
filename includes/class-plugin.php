<?php

declare(strict_types=1);

namespace DCAF;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Plugin {
    public static function init(): void {
        add_action( 'plugins_loaded', [ __CLASS__, 'load_textdomain' ] );
        add_action( 'plugins_loaded', [ __CLASS__, 'boot' ], 20 );
    }

    public static function boot(): void {
        Mailer::hooks();
        Auth_Flow::hooks();
        Account_UI::hooks();
    }

    public static function load_textdomain(): void {
        load_plugin_textdomain(
            'doctorcura-core',
            false,
            dirname( plugin_basename( DCAF_FILE ) ) . '/languages'
        );
    }

    public static function activate(): void {
        if ( ! wp_next_scheduled( 'dcaf_cleanup_expired_tokens' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'dcaf_cleanup_expired_tokens' );
        }

        add_rewrite_endpoint( 'cancelled-orders', EP_ROOT | EP_PAGES );
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        wp_clear_scheduled_hook( 'dcaf_cleanup_expired_tokens' );
        flush_rewrite_rules();
    }
}

add_action( 'dcaf_cleanup_expired_tokens', static function (): void {
    global $wpdb;

    $meta_key_time = (string) setting( 'verify_time_meta_key' );
    $meta_key_used = (string) setting( 'verify_used_meta_key' );
    $meta_key_hash = (string) setting( 'verify_token_meta_key' );
    $expiration    = time() - (int) setting( 'verification_expiration' );

    $user_ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND CAST(meta_value AS UNSIGNED) < %d",
            $meta_key_time,
            $expiration
        )
    );

    if ( empty( $user_ids ) ) {
        return;
    }

    foreach ( array_map( 'intval', $user_ids ) as $user_id ) {
        update_user_meta( $user_id, $meta_key_used, 'yes' );
        delete_user_meta( $user_id, $meta_key_hash );
    }
} );
