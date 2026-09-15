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

use local_bbcotodobien\local\rules\cm_content_contains;
use local_bbcotodobien\local\rules\grade_category_moditems;
use local_bbcotodobien\local\rules\grade_category_weights;

/**
 * Tests for rule configuration export and import.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(rule_transfer::class)]
final class rule_transfer_test extends \advanced_testcase {
    /**
     * Export then import copies the selected rules into another type.
     */
    public function test_round_trip_copies_rules_into_another_type(): void {
        $this->resetAfterTest();

        $sourceid = audit_type_manager::create_type('Source');
        $firstid = audit_type_manager::create_rule_config($sourceid, [
            'ruleclass' => grade_category_moditems::class,
            'name' => 'Has items',
            'mandatory' => true,
            'active' => true,
            'params' => ['idnumber' => 'CAT1', 'ignored' => 'drop-me'],
            'guidance' => '<p>Fix the gradebook</p>',
            'guidanceformat' => FORMAT_HTML,
        ]);
        $secondid = audit_type_manager::create_rule_config($sourceid, [
            'ruleclass' => grade_category_weights::class,
            'name' => 'Weights',
            'mandatory' => false,
            'active' => false,
            'params' => ['idnumber' => 'CAT1'],
        ]);

        $json = rule_transfer::export_rules($sourceid, [$firstid, $secondid]);
        $payload = json_decode($json, true);
        $this->assertSame('local_bbcotodobien', $payload['component']);
        $this->assertSame(rule_transfer::SCHEMA_VERSION, $payload['schemaversion']);
        $this->assertSame('Source', $payload['sourcetype']['name']);
        $this->assertCount(2, $payload['rules']);
        $this->assertSame('grade_category_moditems', $payload['rules'][0]['identifier']);
        $this->assertSame(['idnumber' => 'CAT1'], $payload['rules'][0]['params']);
        $this->assertArrayNotHasKey('ignored', $payload['rules'][0]['params']);

        $destid = audit_type_manager::create_type('Destination');
        $result = rule_transfer::import_rules($destid, $json);

        $this->assertSame(2, $result->imported);
        $this->assertSame(0, $result->skippedname);
        $this->assertSame(0, $result->skippedclass);
        $this->assertSame('Destination', audit_type_manager::get_type($destid)->name);

        $imported = array_values(audit_type_manager::get_rule_configs($destid));
        $this->assertCount(2, $imported);
        $this->assertSame('Has items', $imported[0]->name);
        $this->assertSame(grade_category_moditems::class, $imported[0]->ruleclass);
        $this->assertSame(['idnumber' => 'CAT1'], $imported[0]->paramsdecoded);
        $this->assertSame(1, (int) $imported[0]->mandatory);
        $this->assertSame(1, (int) $imported[0]->active);
        $this->assertSame('<p>Fix the gradebook</p>', $imported[0]->guidance);
        $this->assertSame('Weights', $imported[1]->name);
        $this->assertSame(grade_category_weights::class, $imported[1]->ruleclass);
        $this->assertSame(0, (int) $imported[1]->mandatory);
        $this->assertSame(0, (int) $imported[1]->active);
        $this->assertNotEquals($firstid, (int) $imported[0]->id);
    }

    /**
     * Only the selected rule ids are written to the export file.
     */
    public function test_export_omits_unselected_rules(): void {
        $this->resetAfterTest();

        $typeid = audit_type_manager::create_type('Quality');
        $keepid = audit_type_manager::create_rule_config($typeid, [
            'ruleclass' => grade_category_moditems::class,
            'name' => 'Keep me',
            'params' => ['idnumber' => 'KEEP'],
        ]);
        audit_type_manager::create_rule_config($typeid, [
            'ruleclass' => grade_category_weights::class,
            'name' => 'Skip me',
            'params' => ['idnumber' => 'SKIP'],
        ]);

        $payload = json_decode(rule_transfer::export_rules($typeid, [$keepid]), true);
        $this->assertCount(1, $payload['rules']);
        $this->assertSame('Keep me', $payload['rules'][0]['name']);
        $this->assertSame('grade_category_moditems', $payload['rules'][0]['identifier']);
    }

    /**
     * Importing into a type that already has the same rule name skips that rule.
     */
    public function test_import_skips_duplicate_names(): void {
        $this->resetAfterTest();

        $sourceid = audit_type_manager::create_type('Source');
        $ruleid = audit_type_manager::create_rule_config($sourceid, [
            'ruleclass' => grade_category_moditems::class,
            'name' => 'Has items',
            'params' => ['idnumber' => 'CAT1'],
        ]);
        $json = rule_transfer::export_rules($sourceid, [$ruleid]);

        $destid = audit_type_manager::create_type('Destination');
        audit_type_manager::create_rule_config($destid, [
            'ruleclass' => cm_content_contains::class,
            'name' => 'Has items',
            'params' => ['modname' => 'page', 'idnumber' => 'X', 'field' => 'intro', 'pattern' => 'x', 'matchmode' => 'literal'],
        ]);

        $result = rule_transfer::import_rules($destid, $json);
        $this->assertSame(0, $result->imported);
        $this->assertSame(1, $result->skippedname);
        $this->assertCount(1, audit_type_manager::get_rule_configs($destid));
    }

    /**
     * Duplicate names inside the same file import only the first occurrence.
     */
    public function test_import_skips_duplicate_names_within_file(): void {
        $this->resetAfterTest();

        $destid = audit_type_manager::create_type('Destination');
        $json = json_encode([
            'component' => 'local_bbcotodobien',
            'schemaversion' => rule_transfer::SCHEMA_VERSION,
            'rules' => [
                [
                    'identifier' => 'grade_category_moditems',
                    'name' => 'Same name',
                    'mandatory' => 1,
                    'active' => 1,
                    'params' => ['idnumber' => 'FIRST'],
                ],
                [
                    'identifier' => 'grade_category_weights',
                    'name' => 'Same name',
                    'mandatory' => 1,
                    'active' => 1,
                    'params' => ['idnumber' => 'SECOND'],
                ],
            ],
        ]);

        $result = rule_transfer::import_rules($destid, $json);
        $this->assertSame(1, $result->imported);
        $this->assertSame(1, $result->skippedname);
        $imported = array_values(audit_type_manager::get_rule_configs($destid));
        $this->assertCount(1, $imported);
        $this->assertSame(grade_category_moditems::class, $imported[0]->ruleclass);
        $this->assertSame(['idnumber' => 'FIRST'], $imported[0]->paramsdecoded);
    }

    /**
     * Unknown identifiers are skipped and do not abort the rest of the file.
     */
    public function test_import_skips_unknown_identifiers(): void {
        $this->resetAfterTest();

        $destid = audit_type_manager::create_type('Destination');
        $json = json_encode([
            'component' => 'local_bbcotodobien',
            'schemaversion' => rule_transfer::SCHEMA_VERSION,
            'rules' => [
                [
                    'identifier' => 'not_a_real_rule',
                    'name' => 'Ghost',
                    'params' => [],
                ],
                [
                    'identifier' => 'grade_category_moditems',
                    'name' => 'Real',
                    'mandatory' => 1,
                    'active' => 1,
                    'params' => ['idnumber' => 'CAT1'],
                ],
            ],
        ]);

        $result = rule_transfer::import_rules($destid, $json);
        $this->assertSame(1, $result->imported);
        $this->assertSame(1, $result->skippedclass);
        $imported = array_values(audit_type_manager::get_rule_configs($destid));
        $this->assertCount(1, $imported);
        $this->assertSame('Real', $imported[0]->name);
    }

    /**
     * Guidance files are copied with the HTML so pluginfile references keep working.
     */
    public function test_round_trip_copies_guidance_files(): void {
        $this->resetAfterTest();

        $sourceid = audit_type_manager::create_type('Source');
        $ruleid = audit_type_manager::create_rule_config($sourceid, [
            'ruleclass' => grade_category_moditems::class,
            'name' => 'Has items',
            'params' => ['idnumber' => 'CAT1'],
            'guidance' => '<p><img src="@@PLUGINFILE@@/hint.png" alt="hint" /></p>',
        ]);

        $fs = get_file_storage();
        $context = \context_system::instance();
        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'local_bbcotodobien',
            'filearea' => rule_transfer::GUIDANCE_FILEAREA,
            'itemid' => $ruleid,
            'filepath' => '/',
            'filename' => 'hint.png',
        ], 'png-bytes');

        $json = rule_transfer::export_rules($sourceid, [$ruleid]);
        $payload = json_decode($json, true);
        $this->assertSame('hint.png', $payload['rules'][0]['files'][0]['filename']);

        $destid = audit_type_manager::create_type('Destination');
        rule_transfer::import_rules($destid, $json);
        $imported = array_values(audit_type_manager::get_rule_configs($destid))[0];
        $this->assertStringContainsString('@@PLUGINFILE@@/hint.png', $imported->guidance);

        $file = $fs->get_file(
            $context->id,
            'local_bbcotodobien',
            rule_transfer::GUIDANCE_FILEAREA,
            (int) $imported->id,
            '/',
            'hint.png'
        );
        $this->assertNotFalse($file);
        $this->assertSame('png-bytes', $file->get_content());
    }

    /**
     * Invalid JSON or the wrong component is rejected.
     */
    public function test_import_rejects_invalid_documents(): void {
        $this->resetAfterTest();
        $destid = audit_type_manager::create_type('Destination');

        try {
            rule_transfer::import_rules($destid, '{not json');
            $this->fail('Invalid JSON should throw.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('errorinvalidruleexport', $exception->errorcode);
        }

        try {
            rule_transfer::import_rules($destid, json_encode([
                'component' => 'tool_usertours',
                'schemaversion' => rule_transfer::SCHEMA_VERSION,
                'rules' => [],
            ]));
            $this->fail('Wrong component should throw.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('errorinvalidruleexport', $exception->errorcode);
        }

        try {
            rule_transfer::export_rules($destid, []);
            $this->fail('Export without rules should throw.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('errornorulesselected', $exception->errorcode);
        }
    }
}
