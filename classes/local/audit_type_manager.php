<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_bbcotodobien\local;

/**
 * CRUD for audit types and configured rules.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class audit_type_manager {
    /**
     * Create an audit type.
     *
     * @param string $name Display name
     * @param array|string $categories Category IDs or CSV; empty means site-wide
     * @param bool $active Whether the type is active
     * @return int New record id
     */
    public static function create_type(string $name, array|string $categories = '', bool $active = true): int {
        global $DB;

        $now = time();
        $record = (object) [
            'name' => $name,
            'active' => $active ? 1 : 0,
            'categories' => scope::format_categories($categories),
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        return (int) $DB->insert_record('local_bbcotodobien_audit_type', $record);
    }

    /**
     * Update an audit type.
     *
     * @param int $id Audit type id
     * @param array $fields Fields to update (name, categories, active)
     */
    public static function update_type(int $id, array $fields): void {
        global $DB;

        $record = self::require_type($id);
        if (array_key_exists('name', $fields)) {
            $record->name = (string) $fields['name'];
        }
        if (array_key_exists('categories', $fields)) {
            $record->categories = scope::format_categories($fields['categories']);
        }
        if (array_key_exists('active', $fields)) {
            $record->active = !empty($fields['active']) ? 1 : 0;
        }
        $record->timemodified = time();
        $DB->update_record('local_bbcotodobien_audit_type', $record);
    }

    /**
     * Delete an audit type and related configuration and history.
     *
     * @param int $id Audit type id
     */
    public static function delete_type(int $id): void {
        global $DB;

        self::require_type($id);
        type_matrix_file::delete_files_for_type($id);
        $audits = $DB->get_records('local_bbcotodobien_course_audit', ['audittypeid' => $id], '', 'id');
        foreach ($audits as $audit) {
            writer::delete_course_audit((int) $audit->id);
        }
        $configs = $DB->get_records('local_bbcotodobien_rule_config', ['audittypeid' => $id], '', 'id');
        foreach ($configs as $config) {
            self::delete_rule_config((int) $config->id);
        }
        $DB->delete_records('local_bbcotodobien_audit_type', ['id' => $id]);
    }

    /**
     * Get an audit type.
     *
     * @param int $id Audit type id
     * @return \stdClass
     */
    public static function get_type(int $id): \stdClass {
        return self::require_type($id);
    }

    /**
     * List audit types.
     *
     * @param bool $activeonly Only active types
     * @return \stdClass[]
     */
    public static function get_types(bool $activeonly = false): array {
        global $DB;

        $conditions = $activeonly ? ['active' => 1] : [];
        return $DB->get_records('local_bbcotodobien_audit_type', $conditions, 'id ASC');
    }

    /**
     * Create a rule configuration for an audit type.
     *
     * @param int $audittypeid Audit type id
     * @param array $data Fields: ruleclass, name, mandatory, active, params, guidance, guidanceformat
     * @return int New record id
     */
    public static function create_rule_config(int $audittypeid, array $data): int {
        global $DB;

        self::require_type($audittypeid);
        $now = time();
        $record = (object) [
            'audittypeid' => $audittypeid,
            'ruleclass' => (string) ($data['ruleclass'] ?? ''),
            'name' => (string) ($data['name'] ?? ''),
            'mandatory' => !empty($data['mandatory']) ? 1 : 0,
            'active' => array_key_exists('active', $data) ? (!empty($data['active']) ? 1 : 0) : 1,
            'params' => self::encode_params($data['params'] ?? []),
            'guidance' => (string) ($data['guidance'] ?? ''),
            'guidanceformat' => (int) ($data['guidanceformat'] ?? FORMAT_HTML),
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        return (int) $DB->insert_record('local_bbcotodobien_rule_config', $record);
    }

    /**
     * Update a rule configuration.
     *
     * @param int $id Rule config id
     * @param array $data Fields to update
     */
    public static function update_rule_config(int $id, array $data): void {
        global $DB;

        $record = self::require_rule_config($id);
        foreach (['ruleclass', 'name', 'guidance'] as $field) {
            if (array_key_exists($field, $data)) {
                $record->$field = (string) $data[$field];
            }
        }
        if (array_key_exists('mandatory', $data)) {
            $record->mandatory = !empty($data['mandatory']) ? 1 : 0;
        }
        if (array_key_exists('active', $data)) {
            $record->active = !empty($data['active']) ? 1 : 0;
        }
        if (array_key_exists('params', $data)) {
            $record->params = self::encode_params($data['params']);
        }
        if (array_key_exists('guidanceformat', $data)) {
            $record->guidanceformat = (int) $data['guidanceformat'];
        }
        $record->timemodified = time();
        $DB->update_record('local_bbcotodobien_rule_config', $record);
    }

    /**
     * Delete a rule configuration and its historical results.
     *
     * @param int $id Rule config id
     */
    public static function delete_rule_config(int $id): void {
        global $DB;

        self::require_rule_config($id);
        $results = $DB->get_records('local_bbcotodobien_rule_result', ['ruleconfigid' => $id], '', 'id');
        foreach ($results as $result) {
            $DB->delete_records('local_bbcotodobien_rule_detail', ['ruleresultid' => $result->id]);
        }
        $DB->delete_records('local_bbcotodobien_rule_result', ['ruleconfigid' => $id]);
        $DB->delete_records('local_bbcotodobien_rule_config', ['id' => $id]);
    }

    /**
     * Get a rule configuration.
     *
     * @param int $id Rule config id
     * @return \stdClass
     */
    public static function get_rule_config(int $id): \stdClass {
        $record = self::require_rule_config($id);
        $record->paramsdecoded = self::decode_params($record->params);
        return $record;
    }

    /**
     * List rule configurations for an audit type.
     *
     * @param int $audittypeid Audit type id
     * @param bool $activeonly Only active rules
     * @return \stdClass[]
     */
    public static function get_rule_configs(int $audittypeid, bool $activeonly = false): array {
        global $DB;

        $conditions = ['audittypeid' => $audittypeid];
        if ($activeonly) {
            $conditions['active'] = 1;
        }
        $records = $DB->get_records('local_bbcotodobien_rule_config', $conditions, 'id ASC');
        foreach ($records as $record) {
            $record->paramsdecoded = self::decode_params($record->params);
        }
        return $records;
    }

    /**
     * Encode rule parameters as JSON.
     *
     * @param array|string $params Parameters
     * @return string
     */
    public static function encode_params(array|string $params): string {
        if (is_string($params)) {
            return $params;
        }
        $encoded = json_encode($params);
        return $encoded === false ? '{}' : $encoded;
    }

    /**
     * Decode stored JSON parameters.
     *
     * @param string|null $json Stored JSON
     * @return array
     */
    public static function decode_params(?string $json): array {
        if ($json === null || $json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Fetch an audit type or throw a user-facing exception.
     *
     * @param int $id Audit type id
     * @return \stdClass
     */
    public static function require_type(int $id): \stdClass {
        global $DB;

        $record = $DB->get_record('local_bbcotodobien_audit_type', ['id' => $id]);
        if (!$record) {
            throw new \moodle_exception('errorinvalidaudittype', 'local_bbcotodobien');
        }
        return $record;
    }

    /**
     * Fetch a rule configuration or throw a user-facing exception.
     *
     * @param int $id Rule config id
     * @return \stdClass
     */
    public static function require_rule_config(int $id): \stdClass {
        global $DB;

        $record = $DB->get_record('local_bbcotodobien_rule_config', ['id' => $id]);
        if (!$record) {
            throw new \moodle_exception('errorinvalidruleconfig', 'local_bbcotodobien');
        }
        return $record;
    }
}
