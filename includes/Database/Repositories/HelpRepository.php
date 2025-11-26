<?php
namespace WP_EvManager\Database\Repositories;

use wpdb;

defined('ABSPATH') || exit;

final class HelpRepository
{
    private wpdb $db;
    private string $table;

    public function __construct(wpdb $db = null)
    {
        global $wpdb;
        $this->db = $db ?? $wpdb;
        $this->table = $this->db->prefix . 'evmanager_help';
    }

    /**
     * Hole einen Hilfetext anhand des context_key
     */
    public function find_by_context(string $context_key): ?array
    {
        $row = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->table} WHERE context_key = %s LIMIT 1",
                $context_key
            ),
            ARRAY_A
        );
        return $row ?: null;
    }

    /**
     * Hole alle Hilfetexte
     */
    public function all(): array
    {
        $results = $this->db->get_results("SELECT * FROM {$this->table} ORDER BY context_key ASC", \ARRAY_A);
        return $results ?: [];
    }

    /**
     * Füge einen Hilfetext hinzu oder aktualisiere ihn
     */
    public function insert_or_update(string $context_key, string $title, string $content): int
    {
        $existing = $this->find_by_context($context_key);

        if ($existing) {
            $this->db->update(
                $this->table,
                [
                    'title'   => $title,
                    'content' => wp_kses_post($content),
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => $existing['id']],
                ['%s', '%s', '%s'],
                ['%d']
            );
            return (int)$existing['id'];
        } else {
            $this->db->insert(
                $this->table,
                [
                    'context_key' => $context_key,
                    'title'       => $title,
                    'content'     => wp_kses_post($content),
                    'created_at'  => current_time('mysql'),
                    'updated_at'  => current_time('mysql'),
                ],
                ['%s', '%s', '%s', '%s', '%s']
            );
            return (int)$this->db->insert_id;
        }
    }

    /**
     * Lösche einen Hilfetext
     */
    public function delete(string $context_key): bool
    {
        return (bool)$this->db->delete(
            $this->table,
            ['context_key' => $context_key],
            ['%s']
        );
    }


}
