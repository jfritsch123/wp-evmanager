<?php

// includes/Setup/Activator.php
namespace WP_EvManager\Setup;

defined('ABSPATH') || exit;

final class Activator
{
    public static function activate(): void
    {
        Roles::add_roles();
        Roles::add_caps();
    }
}

