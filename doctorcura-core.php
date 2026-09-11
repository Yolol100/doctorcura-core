<?php
/**
 * Plugin Name: DoctorCura Core
 * Description: Core DoctorCura account, auth, checkout, order workflow, cancellation, and medical request features.
 * Version: 1.0.9
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: OpenAI
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: doctorcura-core
 * Domain Path: /languages
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$dcaf_this_file = __FILE__;
$dcaf_this_path = plugin_dir_path( __FILE__ );
$dcaf_this_url  = plugin_dir_url( __FILE__ );
$dcaf_primary_instance = ! defined( 'DCAF_FILE' );

if ( ! defined( 'DCAF_VERSION' ) ) {
    define( 'DCAF_VERSION', '1.0.9' );
}

if ( ! defined( 'DCAF_FILE' ) ) {
    define( 'DCAF_FILE', $dcaf_this_file );
}

if ( ! defined( 'DCAF_PATH' ) ) {
    define( 'DCAF_PATH', $dcaf_this_path );
}

if ( ! defined( 'DCAF_URL' ) ) {
    define( 'DCAF_URL', $dcaf_this_url );
}

// Older DoctorCura copies and migrated Code Snippets may already declare
// these symbols. Skip duplicate modules to prevent activation-time fatals.
if ( ! function_exists( 'DCAF\\settings' ) ) {
    require_once $dcaf_this_path . 'includes/helpers.php';
}
if ( ! class_exists( 'DCAF\\Locale', false ) ) {
    require_once $dcaf_this_path . 'includes/class-locale.php';
}
if ( ! class_exists( 'DCAF\\Mailer', false ) ) {
    require_once $dcaf_this_path . 'includes/class-mailer.php';
}
if ( ! class_exists( 'DCAF\\Auth_Flow', false ) ) {
    require_once $dcaf_this_path . 'includes/class-auth-flow.php';
}
if ( ! class_exists( 'DCAF\\Account_UI', false ) ) {
    require_once $dcaf_this_path . 'includes/class-account-ui.php';
}

$dcaf_loaded_login_privacy = false;
if ( ! class_exists( 'DCAF\\Login_Privacy', false ) ) {
    require_once $dcaf_this_path . 'includes/class-login-privacy.php';
    $dcaf_loaded_login_privacy = true;
}

// Security_Consent owns the privacy-safe lost-password confirmation and the
// additional required prescribing acknowledgement in the medical questionnaire.
if ( ! class_exists( 'DCAF\\Security_Consent', false ) ) {
    require_once $dcaf_this_path . 'includes/class-security-consent.php';
}

$dcaf_loaded_plugin_class = false;
if ( ! class_exists( 'DCAF\\Plugin', false ) ) {
    require_once $dcaf_this_path . 'includes/class-plugin.php';
    $dcaf_loaded_plugin_class = true;
}

if ( ! class_exists( 'DoctorCura\\Checkout\\CheckoutHandler', false ) ) {
    require_once $dcaf_this_path . 'includes/extra-checkout.php';
}
if ( ! class_exists( 'MedicalOrderNotes', false ) ) {
    require_once $dcaf_this_path . 'includes/extra-medical-notes.php';
}
if ( ! class_exists( 'Doctorcura_Request_Form', false ) ) {
    require_once $dcaf_this_path . 'includes/extra-request-form.php';
}
if ( ! class_exists( 'DoctorCura\\OrderManagement\\OrderCancellation', false ) ) {
    require_once $dcaf_this_path . 'includes/extra-order-cancellation.php';
}

// Only the copy that loaded the Plugin class owns lifecycle hooks. If an older
// Core copy is already active, add only the privacy layer that this release owns.
if ( $dcaf_loaded_plugin_class && $dcaf_primary_instance ) {
    register_activation_hook( $dcaf_this_file, [ 'DCAF\\Plugin', 'activate' ] );
    register_deactivation_hook( $dcaf_this_file, [ 'DCAF\\Plugin', 'deactivate' ] );
    DCAF\Plugin::init();
} elseif ( $dcaf_loaded_login_privacy ) {
    DCAF\Login_Privacy::hooks();
}
