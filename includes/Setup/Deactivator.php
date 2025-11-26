<?php

// includes/Setup/Deactivator.php
namespace WP_EvManager\Setup;

defined('ABSPATH') || exit;

final class Deactivator
{
    public static function deactivate(): void
    {
        Roles::remove_roles();
        Roles::remove_caps();
        // ggf. Cronjobs entfernen, Flush Rewrites
        // flush_rewrite_rules();
    }
}
