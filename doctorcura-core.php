<?php
/**
 * Plugin Name: DoctorCura Core
 * Description: Core DoctorCura account, auth, checkout, order workflow, cancellation, and medical request features.
 * Version: 1.0.5
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

if ( ! defined( 'DCAF_VERSION' ) ) {
    define( 'DCAF_VERSION', '1.0.5' );
}

if ( ! defined( 'DCAF_FILE' ) ) {
    define( 'DCAF_FILE', __FILE__ );
}

if ( ! defined( 'DCAF_PATH' ) ) {
    define( 'DCAF_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'DCAF_URL' ) ) {
    define( 'DCAF_URL', plugin_dir_url( __FILE__ ) );
}

require_once DCAF_PATH . 'includes/helpers.php';
require_once DCAF_PATH . 'includes/class-locale.php';
require_once DCAF_PATH . 'includes/class-mailer.php';
require_once DCAF_PATH . 'includes/class-auth-flow.php';
require_once DCAF_PATH . 'includes/class-account-ui.php';
require_once DCAF_PATH . 'includes/class-plugin.php';
// AI-PATCH: Skip loading legacy modules when the same snippet/class is already active.
if ( ! class_exists( 'DoctorCura\\Checkout\\CheckoutHandler', false ) ) {
    require_once DCAF_PATH . 'includes/extra-checkout.php';
}
if ( ! class_exists( 'MedicalOrderNotes', false ) ) {
    require_once DCAF_PATH . 'includes/extra-medical-notes.php';
}
if ( ! class_exists( 'Doctorcura_Request_Form', false ) ) {
    require_once DCAF_PATH . 'includes/extra-request-form.php';
}
if ( ! class_exists( 'DoctorCura\\OrderManagement\\OrderCancellation', false ) ) {
    require_once DCAF_PATH . 'includes/extra-order-cancellation.php';
}

register_activation_hook( __FILE__, [ 'DCAF\\Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'DCAF\\Plugin', 'deactivate' ] );

DCAF\Plugin::init();
