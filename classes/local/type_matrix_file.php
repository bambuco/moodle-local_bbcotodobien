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
 * Stores and lists audit type snapshot files.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class type_matrix_file {
    /** @var string File area for generated snapshots */
    public const FILEAREA = 'snapshots';

    /**
     * Enabled dataformat plugins keyed by name.
     *
     * @return array<string, string>
     */
    public static function get_dataformat_options(): array {
        $formats = \core\plugin_manager::instance()->get_plugins_of_type('dataformat');
        $options = [];
        foreach ($formats as $format) {
            if ($format->is_enabled()) {
                $options[$format->name] = get_string('dataformat', $format->component);
            }
        }
        return $options;
    }

    /**
     * Generate a snapshot file for an audit type.
     *
     * @param int $audittypeid Audit type id
     * @param string $dataformat Dataformat plugin name
     * @return \stored_file
     */
    public static function generate(int $audittypeid, string $dataformat): \stored_file {
        $type = audit_type_manager::require_type($audittypeid);
        if (!array_key_exists($dataformat, self::get_dataformat_options())) {
            throw new \moodle_exception('errorinvaliddataformat', 'local_bbcotodobien');
        }

        $columns = type_matrix::get_columns($audittypeid);
        $filerecord = [
            'contextid' => \context_system::instance()->id,
            'component' => 'local_bbcotodobien',
            'filearea' => self::FILEAREA,
            'itemid' => $audittypeid,
            'filepath' => '/',
            'filename' => self::make_filename($type),
            'userid' => 0,
        ];

        return \core\dataformat::write_data_to_filearea(
            $filerecord,
            $dataformat,
            array_values($columns),
            type_matrix::iterate_rows($audittypeid)
        );
    }

    /**
     * Snapshot files for a type, newest first.
     *
     * @param int $audittypeid Audit type id
     * @return \stored_file[]
     */
    public static function list_files(int $audittypeid): array {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            \context_system::instance()->id,
            'local_bbcotodobien',
            self::FILEAREA,
            $audittypeid,
            'timemodified DESC, id DESC',
            false
        );
        return array_values($files);
    }

    /**
     * Delete every snapshot stored for a type.
     *
     * @param int $audittypeid Audit type id
     */
    public static function delete_files_for_type(int $audittypeid): void {
        get_file_storage()->delete_area_files(
            \context_system::instance()->id,
            'local_bbcotodobien',
            self::FILEAREA,
            $audittypeid
        );
    }

    /**
     * File manager options for the type file area.
     *
     * @return array
     */
    public static function filemanager_options(): array {
        return [
            'subdirs' => 0,
            'maxfiles' => -1,
            'accepted_types' => '*',
            'return_types' => FILE_INTERNAL | FILE_REFERENCE | FILE_CONTROLLED_LINK,
        ];
    }

    /**
     * Build a filesystem-safe basename without extension.
     *
     * @param \stdClass $type Audit type
     * @return string
     */
    protected static function make_filename(\stdClass $type): string {
        $name = clean_filename((string) $type->name);
        $name = trim(preg_replace('/\s+/', '-', $name) ?? '', '.-');
        if ($name === '') {
            $name = 'audittype-' . $type->id;
        }
        $stamp = userdate(time(), '%Y%m%d-%H%M%S', 99, false, false);
        return $name . '-' . $stamp . '-' . substr(uniqid(), -4);
    }
}
