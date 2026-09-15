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
 * Diagnostic detail for a single evaluation target.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class detail {
    /**
     * Create a detail row.
     *
     * @param string $targettype Target type (course, section, cm, gradebook)
     * @param int $targetid Target id
     * @param string $targetname Human-readable target name
     * @param string $status One of pass, fail, error, na
     * @param array $fields Language string identifier plus placeholders
     */
    public function __construct(
        /** @var string Target type (course, section, cm, gradebook) */
        public string $targettype,
        /** @var int Target id */
        public int $targetid,
        /** @var string Human-readable target name */
        public string $targetname,
        /** @var string Status (pass, fail, error, na) */
        public string $status,
        /** @var array Additional fields for the language string and placeholders */
        public array $fields = []
    ) {
        if (!result::is_valid_status($status)) {
            throw new \coding_exception('Invalid detail status: ' . $status);
        }
    }

    /**
     * Encode the fields for the details database column.
     *
     * @return string JSON payload
     */
    public function to_storage(): string {
        $encoded = json_encode($this->fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $encoded === false ? '{}' : $encoded;
    }

    /**
     * Rebuild a detail from a stored JSON payload.
     *
     * @param string $targettype Target type
     * @param int $targetid Target id
     * @param string $targetname Human-readable target name
     * @param string $status One of pass, fail, error, na
     * @param string $json Stored JSON
     * @return self
     */
    public static function from_storage(
        string $targettype,
        int $targetid,
        string $targetname,
        string $status,
        string $json
    ): self {
        $fields = json_decode($json, true);
        if (!is_array($fields)) {
            $fields = [];
        }
        return new self($targettype, $targetid, $targetname, $status, $fields);
    }
}
