<?php

namespace WP_EvManager\Setup;

defined('ABSPATH') || exit;

final class Roles
{
    // Granular, falls du später feiner steuern willst:
    public const CAPS = [
        'evm_read_events',
        'evm_create_events',
        'evm_edit_own_events',
        'evm_delete_own_events',
        'evm_edit_all_events',
        'evm_delete_all_events',
    ];

    /**
     * Rollen anlegen (bei Aktivierung oder init)
     */
    public static function add_roles(): void
    {
        // Volle Rechte für alle Events
        add_role(
            'event_manager_all',
            __('Ev Manager', 'wp-evmanager'),
            [
                'read' => true,
            ]
        );

        // Nur eigene Events
        add_role(
            'event_manager_own',
            __('Ev Manager Organization', 'wp-evmanager'),
            [
                'read' => true,
            ]
        );
    }

    /**
     * Capabilities zuweisen
     */
    public static function add_caps(): void
    {
        // Administrator: alles
        if ($role = get_role('administrator')) {
            foreach (self::CAPS as $cap) {
                $role->add_cap($cap);
            }
            $role->add_cap('upload_files'); // wichtig für Medien-Upload
        }

        // Event Manager ALL: alles
        if ($role = get_role('event_manager_all')) {
            foreach (self::CAPS as $cap) {
                $role->add_cap($cap);
            }
            $role->add_cap('upload_files'); // ebenfalls erlauben
            $role->add_cap('manage_options');
        }

        // Event Manager OWN: nur eigene
        if ($role = get_role('event_manager_own')) {
            $role->add_cap('evm_read_events');
            $role->add_cap('evm_create_events');
            $role->add_cap('evm_edit_own_events');
            $role->add_cap('evm_delete_own_events');

            $role->add_cap('upload_files'); // damit auch eigene Bilder hochgeladen werden können
        }
    }

    /**
     * Rollen entfernen (z. B. bei Deaktivierung)
     */
    public static function remove_roles(): void
    {
        remove_role('event_manager_all');
        remove_role('event_manager_own');
    }

    /**
     * Caps von allen Rollen entfernen (Admin bleibt unangetastet)
     */
    public static function remove_caps(): void
    {
        foreach (['event_manager_all', 'event_manager_own', 'administrator'] as $role_name) {
            if ($role = get_role($role_name)) {
                foreach (self::CAPS as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }
    }
}

