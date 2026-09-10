<?php

declare(strict_types=1);

namespace DCAF;

use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Locale {
    public static function supported_languages(): array {
        return [ 'en', 'de', 'fr' ];
    }

    public static function from_request(): string {
        $supported = self::supported_languages();

        if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
            $path  = (string) parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
            $path  = trim( $path, '/' );
            $first = strtolower( (string) strtok( $path, '/' ) );

            if ( in_array( $first, $supported, true ) ) {
                return $first;
            }
        }

        if ( isset( $_COOKIE['googtrans'] ) && is_string( $_COOKIE['googtrans'] ) ) {
            $parts     = explode( '/', trim( sanitize_text_field( wp_unslash( $_COOKIE['googtrans'] ) ), '/' ) );
            $candidate = strtolower( (string) end( $parts ) );

            if ( in_array( $candidate, $supported, true ) ) {
                return $candidate;
            }
        }

        if ( isset( $_COOKIE['gtranslate_lang'] ) && is_string( $_COOKIE['gtranslate_lang'] ) ) {
            $candidate = strtolower( trim( sanitize_text_field( wp_unslash( $_COOKIE['gtranslate_lang'] ) ) ) );

            if ( in_array( $candidate, $supported, true ) ) {
                return $candidate;
            }
        }

        if ( isset( $_GET['lang'] ) ) {
            $candidate = strtolower( sanitize_text_field( wp_unslash( $_GET['lang'] ) ) );

            if ( in_array( $candidate, $supported, true ) ) {
                return $candidate;
            }
        }

        return '';
    }

    public static function normalize( string $value ): string {
        $value = strtolower( trim( $value ) );

        return match ( true ) {
            str_starts_with( $value, 'en' ) => 'en_US',
            str_starts_with( $value, 'de' ) => 'de_DE',
            str_starts_with( $value, 'fr' ) => 'fr_FR',
            default                         => 'en_US',
        };
    }

    public static function get( ?int $user_id = null ): string {
        $request_language = self::from_request();

        if ( '' !== $request_language ) {
            return self::normalize( $request_language );
        }

        if ( $user_id ) {
            $saved = get_user_meta( $user_id, (string) setting( 'locale_meta_key' ), true );
            if ( is_string( $saved ) && '' !== $saved ) {
                return self::normalize( $saved );
            }

            $user_locale = get_user_locale( $user_id );
            if ( is_string( $user_locale ) && '' !== $user_locale ) {
                return self::normalize( $user_locale );
            }
        }

        $locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();

        return self::normalize( (string) $locale );
    }

    public static function capture_for_user( int $user_id ): void {
        update_user_meta( $user_id, (string) setting( 'locale_meta_key' ), self::get( $user_id ) );
    }

    public static function translations(): array {
        return [
            'en_US' => [
                'verify_subject'         => 'Verify email',
                'setpw_subject'          => 'Set password',
                'resetpw_subject'        => 'Reset password',
                'account_notice_subject' => 'Account information',
                'verified_notice'        => 'Your email has been verified. Please check your inbox to set your password.',
                'expired_notice'         => 'Verification link expired. Please register again.',
                'not_verified_error'     => 'Please verify your email address first.',
                'login_failed_generic'   => 'Login failed. Please check your login details and try again.',
                'direct_link'            => 'Direct link:',
                'email_footer'           => 'Kind regards,',
                'email_intro'            => 'This email contains English, German and French instructions.',
                'cancelled_label'        => 'Cancelled',
                'view_order_label'       => 'View order %s',
                'view_order_fallback'    => 'View order',
            ],
            'de_DE' => [
                'verify_subject'         => 'E-Mail bestätigen',
                'setpw_subject'          => 'Passwort festlegen',
                'resetpw_subject'        => 'Passwort zurücksetzen',
                'account_notice_subject' => 'Kontoinformationen',
                'verified_notice'        => 'Ihre E-Mail-Adresse wurde bestätigt. Bitte prüfen Sie Ihren Posteingang, um Ihr Passwort festzulegen.',
                'expired_notice'         => 'Der Bestätigungslink ist abgelaufen. Bitte registrieren Sie sich erneut.',
                'not_verified_error'     => 'Bitte bestätigen Sie zuerst Ihre E-Mail-Adresse.',
                'login_failed_generic'   => 'Anmeldung fehlgeschlagen. Bitte prüfe deine Anmeldedaten und versuche es erneut.',
                'direct_link'            => 'Direkter Link:',
                'email_footer'           => 'Mit freundlichen Grüßen,',
                'email_intro'            => 'Diese E-Mail enthält Anweisungen auf Englisch, Deutsch und Französisch.',
                'cancelled_label'        => 'Storniert',
                'view_order_label'       => 'Bestellung %s ansehen',
                'view_order_fallback'    => 'Bestellung ansehen',
            ],
            'fr_FR' => [
                'verify_subject'         => 'Vérifier l’e-mail',
                'setpw_subject'          => 'Définir le mot de passe',
                'resetpw_subject'        => 'Réinitialiser le mot de passe',
                'account_notice_subject' => 'Informations du compte',
                'verified_notice'        => 'Votre adresse e-mail a été vérifiée. Veuillez consulter votre boîte de réception pour définir votre mot de passe.',
                'expired_notice'         => 'Le lien de vérification a expiré. Veuillez vous inscrire à nouveau.',
                'not_verified_error'     => 'Veuillez d’abord vérifier votre adresse e-mail.',
                'login_failed_generic'   => 'Échec de la connexion. Vérifiez vos informations de connexion et réessayez.',
                'direct_link'            => 'Lien direct :',
                'email_footer'           => 'Cordialement,',
                'email_intro'            => 'Cet e-mail contient des instructions en anglais, allemand et français.',
                'cancelled_label'        => 'Annulée',
                'view_order_label'       => 'Voir la commande %s',
                'view_order_fallback'    => 'Voir la commande',
            ],
        ];
    }

    public static function t( string $key, ?int $user_id = null ): string {
        $locale       = self::get( $user_id );
        $translations = self::translations();

        return $translations[ $locale ][ $key ] ?? $translations['en_US'][ $key ] ?? $key;
    }
}
