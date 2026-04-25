<?php

declare(strict_types=1);

namespace DCAF;

use WP_Post;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Mailer {
    public static function hooks(): void {
        add_filter( 'wp_mail_from', [ __CLASS__, 'mail_from_address' ], 99 );
        add_filter( 'wp_mail_from_name', [ __CLASS__, 'mail_from_name' ], 99 );
    }

    public static function verification_url(): string {
        $page = get_page_by_path( (string) setting( 'verification_page_slug' ), OBJECT, 'page' );

        if ( $page instanceof WP_Post ) {
            return (string) get_permalink( $page );
        }

        return home_url( (string) setting( 'verification_fallback' ) );
    }

    public static function reset_success_url(): string {
        return (string) setting( 'reset_success_url' );
    }

    public static function mail_from_address( mixed $from ): string {
        return (string) setting( 'mail_from_address' );
    }

    public static function mail_from_name( mixed $name ): string {
        return (string) setting( 'mail_from_name' );
    }

    public static function html_mail_content_type(): string {
        return 'text/html';
    }

    public static function get_multilingual_blocks( string $type, string $username, string $button_url ): string {
        $username = esc_html( $username );
        $blocks   = [];

        $content = [
            'en' => [
                'label'   => 'English',
                'title'   => match ( $type ) {
                    'verify'         => 'Verify your email address',
                    'reset_password' => 'Reset your password',
                    'account_notice' => 'Important account information',
                    default          => 'Set your password',
                },
                'message' => match ( $type ) {
                    'verify'         => 'Please verify your email to activate your account.',
                    'reset_password' => "Someone requested a password reset for your account. If this was you, click the button below to choose a new password.\n\nIf you didn’t request this, you can safely ignore this email.\nThis reset link will expire automatically.",
                    'account_notice' => "You will receive further instructions by email.\n\nYour personal data will be used to improve your experience on this website, manage access to your account, and for other purposes described in our privacy policy.",
                    default          => 'Your email has been verified. Please set your password below.',
                },
                'button'  => match ( $type ) {
                    'verify'         => 'Verify email',
                    'reset_password' => 'Reset password',
                    'account_notice' => 'Open account',
                    default          => 'Set password',
                },
                'hello'   => sprintf( 'Hi %s,', $username ),
                'support' => 'Need help? Contact our support team.',
            ],
            'de' => [
                'label'   => 'Deutsch',
                'title'   => match ( $type ) {
                    'verify'         => 'Verifizieren Sie Ihre E-Mail-Adresse',
                    'reset_password' => 'Passwort zurücksetzen',
                    'account_notice' => 'Wichtige Informationen zu Ihrem Konto',
                    default          => 'Passwort festlegen',
                },
                'message' => match ( $type ) {
                    'verify'         => 'Bitte verifizieren Sie Ihre E-Mail-Adresse, um Ihr Konto zu aktivieren.',
                    'reset_password' => "Es wurde eine Zurücksetzung Ihres Passworts angefordert. Falls Sie das waren, klicken Sie auf die Schaltfläche unten, um ein neues Passwort festzulegen.\n\nFalls Sie diese Anfrage nicht gestellt haben, können Sie diese E-Mail sicher ignorieren.\nDieser Link verfällt automatisch.",
                    'account_notice' => "Sie erhalten weitere Anweisungen per E-Mail.\n\nIhre personenbezogenen Daten werden verwendet, um Ihr Erlebnis auf dieser Website zu verbessern, den Zugriff auf Ihr Konto zu verwalten sowie für andere Zwecke, die in unserer Datenschutzerklärung beschrieben sind.",
                    default          => 'Ihre E-Mail-Adresse wurde verifiziert. Bitte legen Sie unten Ihr Passwort fest.',
                },
                'button'  => match ( $type ) {
                    'verify'         => 'E-Mail verifizieren',
                    'reset_password' => 'Passwort zurücksetzen',
                    'account_notice' => 'Konto öffnen',
                    default          => 'Passwort festlegen',
                },
                'hello'   => sprintf( 'Hallo %s,', $username ),
                'support' => 'Benötigen Sie Hilfe? Kontaktieren Sie unser Support-Team.',
            ],
            'fr' => [
                'label'   => 'Français',
                'title'   => match ( $type ) {
                    'verify'         => 'Vérifiez votre adresse e-mail',
                    'reset_password' => 'Réinitialisez votre mot de passe',
                    'account_notice' => 'Informations importantes sur votre compte',
                    default          => 'Définir votre mot de passe',
                },
                'message' => match ( $type ) {
                    'verify'         => 'Veuillez vérifier votre adresse e-mail pour activer votre compte.',
                    'reset_password' => "Une demande de réinitialisation du mot de passe a été effectuée pour votre compte. Si c’était bien vous, cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe.\n\nSi vous n’êtes pas à l’origine de cette demande, vous pouvez ignorer cet e-mail en toute sécurité.\nCe lien de réinitialisation expirera automatiquement.",
                    'account_notice' => "Vous recevrez des instructions supplémentaires par e-mail.\n\nVos données personnelles seront utilisées pour améliorer votre expérience sur ce site, gérer l’accès à votre compte et à d’autres fins décrites dans notre politique de confidentialité.",
                    default          => 'Votre e-mail a été vérifié. Veuillez définir votre mot de passe ci-dessous.',
                },
                'button'  => match ( $type ) {
                    'verify'         => 'Vérifier l’e-mail',
                    'reset_password' => 'Réinitialiser le mot de passe',
                    'account_notice' => 'Ouvrir le compte',
                    default          => 'Définir le mot de passe',
                },
                'hello'   => sprintf( 'Bonjour %s,', $username ),
                'support' => 'Besoin d’aide ? Contactez notre support.',
            ],
        ];

        foreach ( $content as $lang ) {
            $message_html = nl2br( esc_html( $lang['message'] ) );

            $blocks[] = '
                <div style="border:1px solid #dbe7ff; border-radius:12px; padding:18px 16px; margin:0 0 16px 0; background:#ffffff;">
                    <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#0560FF; margin:0 0 8px 0;">' . esc_html( $lang['label'] ) . '</div>
                    <h3 style="margin:0 0 10px 0; font-size:18px; line-height:1.35; color:#111827;">' . esc_html( $lang['title'] ) . '</h3>
                    <p style="margin:0 0 10px 0; font-size:15px; line-height:1.7; color:#374151;">' . esc_html( $lang['hello'] ) . '</p>
                    <p style="margin:0 0 16px 0; font-size:15px; line-height:1.75; color:#374151;">' . $message_html . '</p>
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 14px 0;"><tr><td style="border-radius:8px; background:#0560FF; text-align:center;"><a href="' . esc_url( $button_url ) . '" style="display:inline-block; background:#0560FF; color:#ffffff; padding:12px 18px; text-decoration:none; border-radius:8px; font-size:14px; line-height:1.2; font-weight:700;">' . esc_html( $lang['button'] ) . '</a></td></tr></table>
                    <p style="margin:0; font-size:13px; line-height:1.7; color:#6b7280;">' . esc_html( $lang['support'] ) . '</p>
                </div>';
        }

        return implode( '', $blocks );
    }

    public static function email_template( string $subject_title, string $mail_type, string $username, string $button_url, ?int $user_id = null ): string {
        $site_name = get_bloginfo( 'name' );
        $site_url  = home_url( '/' );
        $logo_url  = home_url( (string) setting( 'logo_path' ) );

        return '
        <div style="margin:0; padding:0; background:#f3f6fb;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#f3f6fb; margin:0; padding:0;">
                <tr>
                    <td style="padding:24px 12px;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width:720px; margin:0 auto; background:#DBEAFE; border-radius:16px;">
                            <tr>
                                <td style="padding:24px 18px;">
                                    <div style="margin:0 0 24px 0;"><a href="' . esc_url( $site_url ) . '" style="text-decoration:none;"><img src="' . esc_url( $logo_url ) . '" width="170" alt="' . esc_attr( $site_name ) . '" style="display:block; max-width:100%; height:auto; border:0;"></a></div>
                                    <h2 style="margin:0 0 10px 0; color:#111827; font-size:22px; line-height:1.35; font-weight:700;">' . esc_html( $subject_title ) . '</h2>
                                    <p style="margin:0 0 20px 0; color:#4b5563; font-size:14px; line-height:1.75;">' . esc_html( Locale::t( 'email_intro', $user_id ) ) . '</p>
                                    ' . self::get_multilingual_blocks( $mail_type, $username, $button_url ) . '
                                    <div style="margin-top:20px; padding-top:18px; border-top:1px solid #bfd3ff;">
                                        <p style="margin:0 0 8px 0; font-size:12px; line-height:1.6; color:#4b5563;">' . esc_html( Locale::t( 'direct_link', $user_id ) ) . '</p>
                                        <p style="margin:0 0 16px 0; font-size:12px; line-height:1.7; word-break:break-word;"><a href="' . esc_url( $button_url ) . '" style="color:#0560FF; text-decoration:underline;">' . esc_html( $button_url ) . '</a></p>
                                        <p style="margin:0; font-size:13px; line-height:1.7; color:#374151;">' . esc_html( Locale::t( 'email_footer', $user_id ) ) . '<br><strong>' . esc_html( $site_name ) . ' Team</strong></p>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>';
    }

    public static function send( string $to, string $subject, string $mail_type, string $username, string $button_url, ?int $user_id = null ): bool {
        add_filter( 'wp_mail_content_type', [ __CLASS__, 'html_mail_content_type' ] );

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
        ];

        $sent = wp_mail(
            $to,
            $subject,
            self::email_template( $subject, $mail_type, $username, $button_url, $user_id ),
            $headers
        );

        remove_filter( 'wp_mail_content_type', [ __CLASS__, 'html_mail_content_type' ] );

        return (bool) $sent;
    }

    public static function send_verification_email( int $user_id ): bool {
        $user = get_userdata( $user_id );
        if ( ! $user instanceof WP_User ) {
            return false;
        }

        update_user_meta( $user_id, (string) setting( 'verified_meta_key' ), 'no' );

        $token = wp_generate_password( 32, false );
        update_user_meta( $user_id, (string) setting( 'verify_token_meta_key' ), wp_hash_password( $token ) );
        update_user_meta( $user_id, (string) setting( 'verify_time_meta_key' ), time() );
        update_user_meta( $user_id, (string) setting( 'verify_used_meta_key' ), 'no' );

        $verify_url = add_query_arg(
            [
                'verify_email' => 1,
                'uid'          => $user_id,
                'token'        => $token,
            ],
            self::verification_url()
        );

        return self::send(
            $user->user_email,
            Locale::t( 'verify_subject', $user_id ),
            'verify',
            $user->user_login,
            $verify_url,
            $user_id
        );
    }

    public static function send_set_password_email( int $user_id ): bool {
        $user = get_userdata( $user_id );
        if ( ! $user instanceof WP_User ) {
            return false;
        }

        $reset_key = get_password_reset_key( $user );
        if ( is_wp_error( $reset_key ) ) {
            return false;
        }

        $reset_url = add_query_arg(
            [
                'key'    => $reset_key,
                'login'  => rawurlencode( $user->user_login ),
                'action' => 'rp',
            ],
            wc_get_endpoint_url( 'lost-password', '', wc_get_page_permalink( 'myaccount' ) )
        );

        return self::send(
            $user->user_email,
            Locale::t( 'setpw_subject', $user_id ),
            'set_password',
            $user->user_login,
            $reset_url,
            $user_id
        );
    }

    public static function send_account_notice_email( int $user_id, ?string $button_url = null ): bool {
        $user = get_userdata( $user_id );
        if ( ! $user instanceof WP_User ) {
            return false;
        }

        $url = $button_url ?: wc_get_page_permalink( 'myaccount' );

        return self::send(
            $user->user_email,
            Locale::t( 'account_notice_subject', $user_id ),
            'account_notice',
            $user->user_login,
            $url,
            $user_id
        );
    }

    public static function send_custom_reset_password_email( string $user_login, string $reset_key ): void {
        $user = get_user_by( 'login', $user_login );
        if ( ! $user instanceof WP_User ) {
            return;
        }

        $reset_url = add_query_arg(
            [
                'key'    => $reset_key,
                'login'  => rawurlencode( $user_login ),
                'action' => 'rp',
            ],
            wc_get_endpoint_url( 'lost-password', '', wc_get_page_permalink( 'myaccount' ) )
        );

        self::send(
            $user->user_email,
            Locale::t( 'resetpw_subject', (int) $user->ID ),
            'reset_password',
            $user_login,
            $reset_url,
            (int) $user->ID
        );
    }
}
