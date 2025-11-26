<?php

namespace WP_EvManager\Security;

defined('ABSPATH') || exit;

final class Permissions
{
    private static function admin_fallback(): bool
    {
        return current_user_can('manage_options') || (function_exists('is_super_admin') && is_super_admin());
    }

    public static function can_read(): bool
    {
        return self::admin_fallback() || current_user_can('evm_read_events');
    }

    public static function can_create(): bool
    {
        return self::admin_fallback() || current_user_can('evm_create_events');
    }

    public static function can_edit_all(): bool
    {
        return self::admin_fallback() || current_user_can('evm_edit_all_events');
    }

    public static function can_delete_all(): bool
    {
        return self::admin_fallback() || current_user_can('evm_delete_all_events');
    }

    public static function can_edit_own(string $owner_login): bool
    {
        $u = wp_get_current_user();
        return $u && $u->exists() && $u->user_login === $owner_login && current_user_can('evm_edit_own_events');
    }

    public static function can_delete_own(string $owner_login): bool
    {
        $u = wp_get_current_user();
        return $u && $u->exists() && $u->user_login === $owner_login && current_user_can('evm_delete_own_events');
    }

    public static function current_login(): string
    {
        $u = wp_get_current_user();
        return ($u && $u->exists()) ? (string)$u->user_login : '';
    }
}
