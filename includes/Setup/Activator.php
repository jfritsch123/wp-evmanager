<?php

// includes/Setup/Activator.php
namespace WP_EvManager\Setup;

use WP_EvManager\Database\Schema;

defined('ABSPATH') || exit;

final class Activator
{
    public static function activate(): void
    {
        try {
            Roles::add_roles();
            Roles::add_caps();

            Schema::install();

        } catch (\Throwable $e) {
            error_log('[WP_EvManager][Activation] ' . $e->getMessage());
        }
    }
}

