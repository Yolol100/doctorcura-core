<?php

declare(strict_types=1);

namespace DCAF;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function settings(): array {
    return [
        'mail_from_address'        => 'info@doctorcura.com',
        'mail_from_name'           => 'DoctorCura',
        'verification_page_slug'   => 'email-verification',
        'verification_fallback'    => '/e-mail-bestatigung/',
        'reset_success_url'        => 'https://doctorcura.com/e-mail-bestatigung/',
        'logo_path'                => '/wp-content/uploads/2025/12/logo-2-scaled-1.png',
        'verification_expiration'  => DAY_IN_SECONDS * 2,
        'locale_meta_key'          => '_doctorcura_preferred_locale',
        'verified_meta_key'        => '_doctorcura_email_verified',
        'verify_token_meta_key'    => '_doctorcura_verify_token',
        'verify_time_meta_key'     => '_doctorcura_verify_time',
        'verify_used_meta_key'     => '_doctorcura_verify_used',
    ];
}

function setting( string $key, mixed $default = null ): mixed {
    $settings = settings();
    return $settings[ $key ] ?? $default;
}

function plugin_basename_path( string $path = '' ): string {
    $base = plugin_basename( DCAF_FILE );
    $dir  = trailingslashit( dirname( $base ) );
    return ltrim( $dir . ltrim( $path, '/' ), '/' );
}
