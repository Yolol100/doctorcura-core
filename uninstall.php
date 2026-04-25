<?php

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove lightweight plugin options only.
// Keep user-related verification meta to avoid destructive uninstall behavior.
delete_option( 'dcaf_version' );
