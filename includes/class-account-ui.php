<?php

declare(strict_types=1);

namespace DCAF;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Account_UI {
    public static function hooks(): void {
        add_filter( 'gettext', [ __CLASS__, 'translate_cancelled_label' ], 20, 3 );
        add_filter( 'woocommerce_account_orders_columns', [ __CLASS__, 'filter_orders_columns' ], 20 );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ], 99 );
    }

    public static function is_account_context(): bool {
        return function_exists( 'is_account_page' ) && is_account_page();
    }

    public static function translate_cancelled_label( string $translated, string $text, string $domain ): string {
        if ( ! self::is_account_context() ) {
            return $translated;
        }

        if ( 'woocommerce' === $domain && 'Cancelled' === $text ) {
            return Locale::t( 'cancelled_label' );
        }

        return $translated;
    }

    /**
     * Remove the actions column from the account orders table.
     *
     * @param array<string,string> $columns WooCommerce orders columns.
     * @return array<string,string>
     */
    public static function filter_orders_columns( array $columns ): array {
        unset( $columns['order-actions'] );

        return $columns;
    }

    public static function enqueue_assets(): void {
        if ( ! self::is_account_context() ) {
            return;
        }

        wp_enqueue_style(
            'dcaf-account-ui',
            DCAF_URL . 'assets/css/account-orders.css',
            [],
            DCAF_VERSION
        );

        wp_enqueue_script(
            'dcaf-account-ui',
            DCAF_URL . 'assets/js/account-orders.js',
            [],
            DCAF_VERSION,
            true
        );

        wp_localize_script(
            'dcaf-account-ui',
            'dcafAccountUi',
            [
                'cancelledLabel' => Locale::t( 'cancelled_label' ),
            ]
        );
    }
}
