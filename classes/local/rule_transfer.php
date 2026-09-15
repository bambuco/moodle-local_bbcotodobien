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

use local_bbcotodobien\local\rules\base;
use local_bbcotodobien\local\rules\factory;

/**
 * Export and import rule configurations as JSON.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule_transfer {
    /** @var int JSON schema version written by this plugin */
    public const SCHEMA_VERSION = 1;

    /** @var string File area used for rule guidance */
    public const GUIDANCE_FILEAREA = 'guidance';

    /**
     * Export selected rule configurations of an audit type as JSON.
     *
     * @param int $audittypeid Audit type id
     * @param array $ruleconfigids Rule configuration ids to include
     * @return string JSON document
     */
    public static function export_rules(int $audittypeid, array $ruleconfigids): string {
        $type = audit_type_manager::require_type($audittypeid);
        $wanted = array_flip(array_map('intval', $ruleconfigids));
        unset($wanted[0]);

        $rules = [];
        foreach (audit_type_manager::get_rule_configs($audittypeid) as $config) {
            if (!isset($wanted[(int) $config->id])) {
                continue;
            }
            $rules[] = self::export_rule($config);
        }

        if (!$rules) {
            throw new \moodle_exception('errornorulesselected', 'local_bbcotodobien');
        }

        $payload = [
            'component' => 'local_bbcotodobien',
            'schemaversion' => self::SCHEMA_VERSION,
            'pluginversion' => (int) get_config('local_bbcotodobien', 'version'),
            'sourcetype' => [
                'name' => $type->name,
            ],
            'rules' => $rules,
        ];

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new \moodle_exception('errorinvalidruleexport', 'local_bbcotodobien');
        }

        return $encoded;
    }

    /**
     * Import rules from JSON into an existing audit type.
     *
     * Rules whose name already exists on the destination type are skipped.
     * Unknown rule identifiers are skipped.
     *
     * @param int $audittypeid Destination audit type id
     * @param string $json Exported JSON document
     * @return \stdClass Counters: imported, skippedname, skippedclass
     */
    public static function import_rules(int $audittypeid, string $json): \stdClass {
        global $DB;

        audit_type_manager::require_type($audittypeid);
        $payload = self::decode_payload($json);

        $existingnames = [];
        foreach (audit_type_manager::get_rule_configs($audittypeid) as $config) {
            $existingnames[(string) $config->name] = true;
        }

        $result = (object) [
            'imported' => 0,
            'skippedname' => 0,
            'skippedclass' => 0,
        ];

        $classes = factory::get_rule_classes();
        $transaction = $DB->start_delegated_transaction();
        foreach ($payload['rules'] as $ruledata) {
            if (!is_array($ruledata)) {
                $result->skippedclass++;
                continue;
            }

            $identifier = clean_param((string) ($ruledata['identifier'] ?? ''), PARAM_ALPHANUMEXT);
            $name = trim(clean_param((string) ($ruledata['name'] ?? ''), PARAM_TEXT));
            if ($identifier === '' || $name === '') {
                $result->skippedclass++;
                continue;
            }

            if (!isset($classes[$identifier])) {
                $result->skippedclass++;
                continue;
            }

            if (isset($existingnames[$name])) {
                $result->skippedname++;
                continue;
            }

            $rule = factory::create($classes[$identifier]);
            $newid = audit_type_manager::create_rule_config($audittypeid, [
                'ruleclass' => $classes[$identifier],
                'name' => $name,
                'mandatory' => !empty($ruledata['mandatory']),
                'active' => array_key_exists('active', $ruledata) ? !empty($ruledata['active']) : true,
                'params' => self::filter_params($rule, $ruledata['params'] ?? []),
                'guidance' => (string) ($ruledata['guidance'] ?? ''),
                'guidanceformat' => (int) ($ruledata['guidanceformat'] ?? FORMAT_HTML),
            ]);
            self::import_guidance_files($newid, $ruledata['files'] ?? []);
            $existingnames[$name] = true;
            $result->imported++;
        }
        $transaction->allow_commit();

        return $result;
    }

    /**
     * Serialise one rule configuration.
     *
     * @param \stdClass $config Rule configuration with paramsdecoded
     * @return array
     */
    protected static function export_rule(\stdClass $config): array {
        $identifier = self::identifier_for_config($config);
        $rule = null;
        if (factory::is_instantiatable_rule($config->ruleclass)) {
            $rule = factory::create($config->ruleclass);
        }

        $params = is_array($config->paramsdecoded ?? null) ? $config->paramsdecoded : [];
        if ($rule) {
            $params = self::filter_params($rule, $params);
        }

        $exported = [
            'identifier' => $identifier,
            'name' => (string) $config->name,
            'mandatory' => !empty($config->mandatory) ? 1 : 0,
            'active' => !empty($config->active) ? 1 : 0,
            'params' => $params,
            'guidance' => (string) ($config->guidance ?? ''),
            'guidanceformat' => (int) ($config->guidanceformat ?? FORMAT_HTML),
        ];

        $files = self::export_guidance_files((int) $config->id);
        if ($files) {
            $exported['files'] = $files;
        }

        return $exported;
    }

    /**
     * Decode and validate an export document.
     *
     * @param string $json Exported JSON
     * @return array
     */
    protected static function decode_payload(string $json): array {
        $payload = json_decode($json, true);
        $validcomponent = is_array($payload) && ($payload['component'] ?? '') === 'local_bbcotodobien';
        $validschema = $validcomponent && (int) ($payload['schemaversion'] ?? 0) === self::SCHEMA_VERSION;
        $validrules = $validschema && isset($payload['rules']) && is_array($payload['rules']);
        if (!$validrules) {
            throw new \moodle_exception('errorinvalidruleexport', 'local_bbcotodobien');
        }

        return $payload;
    }

    /**
     * Keep only parameter keys declared by the rule class.
     *
     * @param base $rule Rule instance
     * @param mixed $params Incoming parameters
     * @return array
     */
    protected static function filter_params(base $rule, mixed $params): array {
        if (!is_array($params) || array_is_list($params)) {
            $params = [];
        }

        $filtered = [];
        foreach ($rule->get_config_param_names() as $name) {
            if (array_key_exists($name, $params)) {
                $filtered[$name] = $params[$name];
            }
        }

        return $filtered;
    }

    /**
     * Machine identifier stored for a configured rule.
     *
     * @param \stdClass $config Rule configuration
     * @return string
     */
    protected static function identifier_for_config(\stdClass $config): string {
        $classname = (string) $config->ruleclass;
        if (class_exists($classname) && is_callable([$classname, 'get_identifier'])) {
            return $classname::get_identifier();
        }
        $parts = explode('\\', $classname);
        return (string) end($parts);
    }

    /**
     * Export guidance files for a rule configuration.
     *
     * @param int $ruleconfigid Rule configuration id
     * @return array
     */
    protected static function export_guidance_files(int $ruleconfigid): array {
        $fs = get_file_storage();
        $context = \context_system::instance();
        $stored = $fs->get_area_files(
            $context->id,
            'local_bbcotodobien',
            self::GUIDANCE_FILEAREA,
            $ruleconfigid,
            'filepath, filename',
            false
        );

        $files = [];
        foreach ($stored as $file) {
            $files[] = [
                'filepath' => $file->get_filepath(),
                'filename' => $file->get_filename(),
                'mimetype' => $file->get_mimetype(),
                'content' => base64_encode($file->get_content()),
            ];
        }

        return $files;
    }

    /**
     * Restore guidance files onto a newly created rule configuration.
     *
     * @param int $ruleconfigid New rule configuration id
     * @param mixed $files Exported file records
     */
    protected static function import_guidance_files(int $ruleconfigid, mixed $files): void {
        if (!is_array($files)) {
            return;
        }

        $fs = get_file_storage();
        $context = \context_system::instance();
        foreach ($files as $fileinfo) {
            if (!is_array($fileinfo)) {
                continue;
            }
            $filename = clean_param((string) ($fileinfo['filename'] ?? ''), PARAM_FILE);
            if ($filename === '' || $filename === '.') {
                continue;
            }
            $content = base64_decode((string) ($fileinfo['content'] ?? ''), true);
            if ($content === false) {
                continue;
            }

            $filepath = (string) ($fileinfo['filepath'] ?? '/');
            $filepath = '/' . trim(str_replace('\\', '/', $filepath), '/') . '/';
            if ($filepath === '//') {
                $filepath = '/';
            }

            if (strpos($filepath, '..') !== false) {
                continue;
            }

            $exists = $fs->file_exists(
                $context->id,
                'local_bbcotodobien',
                self::GUIDANCE_FILEAREA,
                $ruleconfigid,
                $filepath,
                $filename
            );
            if ($exists) {
                continue;
            }

            $fs->create_file_from_string([
                'contextid' => $context->id,
                'component' => 'local_bbcotodobien',
                'filearea' => self::GUIDANCE_FILEAREA,
                'itemid' => $ruleconfigid,
                'filepath' => $filepath,
                'filename' => $filename,
            ], $content);
        }
    }
}
