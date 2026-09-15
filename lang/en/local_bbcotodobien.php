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

/**
 * English language pack for ToDo good
 *
 * @package    local_bbcotodobien
 * @category   string
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['active'] = 'Active';
$string['addaudittype'] = 'Add audit type';
$string['addrule'] = 'Add rule';
$string['allcategories'] = 'All categories';
$string['auditrerun'] = 'The audit was executed again.';
$string['audittypecreated'] = 'Audit type created';
$string['audittypedeleted'] = 'Audit type deleted';
$string['audittypename'] = 'Name';
$string['audittypename_help'] = 'A short name that identifies this audit type to teachers and administrators.';
$string['audittypeupdated'] = 'Audit type updated';
$string['bbcotodobien:execute'] = 'Execute ToDo good audits';
$string['bbcotodobien:viewreport'] = 'View ToDo good reports';
$string['categories'] = 'Categories';
$string['categories_help'] = 'Limit this audit type to courses in the selected categories and their descendants. Leave empty to apply it to every course on the site.';
$string['cliauditcompleted'] = 'Audit completed: course {$a->courseid}, type {$a->audittypeid}, audit {$a->auditid}, compliance {$a->compliance}% ({$a->status}).';
$string['clirulereevaluated'] = 'Rule re-evaluated: course {$a->courseid}, rule {$a->ruleconfigid}, audit {$a->auditid}, compliance {$a->compliance}% ({$a->status}).';
$string['compliance'] = 'Compliance';
$string['currentreport'] = 'Current report';
$string['dashboard'] = 'Audit dashboard';
$string['deleteaudittype'] = 'Delete audit type';
$string['deleteaudittypeconfirm'] = 'Are you sure you want to delete the audit type "{$a}" and all of its rules and history?';
$string['deleterule'] = 'Delete rule';
$string['deleteruleconfirm'] = 'Are you sure you want to delete the rule "{$a}" and all of its historical results?';
$string['editaudittype'] = 'Edit audit type';
$string['editrule'] = 'Edit rule';
$string['entitycourseaudit'] = 'Course audit';
$string['erroraudittypenotapplicable'] = 'The audit type does not apply to this course.';
$string['errorcannotviewdashboard'] = 'You do not have permission to view the ToDo good dashboard.';
$string['errorcliconflictingoptions'] = 'Specify either --audittypeid or --ruleconfigid, not both.';
$string['errorclismissingoptions'] = 'You must specify --courseid and either --audittypeid or --ruleconfigid.';
$string['errorinvalidaudittype'] = 'The audit type does not exist.';
$string['errorinvalidcourseaudit'] = 'The course audit does not exist.';
$string['errorinvaliddataformat'] = 'The selected download format is not available.';
$string['errorinvalidruleclass'] = 'The selected rule class is not valid.';
$string['errorinvalidruleconfig'] = 'The rule configuration does not exist.';
$string['errorinvalidruleexport'] = 'The rule export file is not valid.';
$string['errornorulesselected'] = 'Select at least one rule to export.';
$string['eventauditcompleted'] = 'Course audit completed';
$string['eventrulereevaluated'] = 'Audit rule re-evaluated';
$string['exportrules'] = 'Export rules';
$string['exportrules_help'] = 'Choose which rules of this audit type to include in the downloadable JSON file.';
$string['generatesnapshot'] = 'Generate current audit snapshot';
$string['generatesnapshot_help'] = 'Creates a downloadable file with the latest stored audit for each course of this type. Choose one of the data formats installed on this site.';
$string['guidance'] = 'Guidance';
$string['guidance_help'] = 'Optional formative text shown to teachers when this rule does not pass. You can include files and links.';
$string['headeralert'] = 'ToDo good alert';
$string['headeralertmessage'] = 'The audit compliance for this course is {$a}%.';
$string['history'] = 'History';
$string['importrules'] = 'Import rules';
$string['importrules_help'] = 'Import rules from a JSON file into this audit type. Rules whose name already exists in this type are skipped.';
$string['importrulessummary'] = 'Imported {$a->imported} rule(s). Skipped {$a->skippedname} with a duplicate name and {$a->skippedclass} with an unknown rule class.';
$string['includehidden'] = 'Include hidden courses';
$string['includehidden_desc'] = 'If enabled, scheduled audits also process courses that are hidden (visible = 0).';
$string['lastrun'] = 'Last run';
$string['loadparameters'] = 'Load parameters';
$string['manageaudittypes'] = 'Manage audit types';
$string['managesnapshotfiles'] = 'Manage files';
$string['mandatory'] = 'Mandatory';
$string['maxhistorypercourse'] = 'Maximum historical reports per course';
$string['maxhistorypercourse_desc'] = 'Maximum number of stored audit reports per course and audit type. Use 0 for no volume limit.';
$string['neverrun'] = 'Never run';
$string['noapplicabletypes'] = 'No audit types apply to this course.';
$string['noaudittypes'] = 'No audit types have been created yet.';
$string['nodetails'] = 'There are no diagnostic details for this rule yet.';
$string['noruleclasses'] = 'No rule classes are available yet. Concrete rules will appear here when they are installed.';
$string['norules'] = 'No rules have been configured for this audit type yet.';
$string['nosnapshots'] = 'No snapshots have been generated for this audit type yet.';
$string['notavailableyet'] = 'This page is not available yet. It will be enabled in a later implementation phase.';
$string['optional'] = 'Optional';
$string['pluginname'] = 'ToDo good';
$string['privacy:metadata:course_audit'] = 'Stores each audit execution against a course, including who ran it.';
$string['privacy:metadata:course_audit:audittypeid'] = 'The audit type that was executed.';
$string['privacy:metadata:course_audit:compliance'] = 'The overall compliance percentage for the execution.';
$string['privacy:metadata:course_audit:courseid'] = 'The course that was audited.';
$string['privacy:metadata:course_audit:status'] = 'The overall status of the execution.';
$string['privacy:metadata:course_audit:timecreated'] = 'The time the execution was created.';
$string['privacy:metadata:course_audit:timemodified'] = 'The time the execution was last updated.';
$string['privacy:metadata:course_audit:userid'] = 'The user who ran the audit (0 for scheduled tasks).';
$string['privacy:metadata:rule_result'] = 'Stores consolidated results for each rule in an audit execution.';
$string['privacy:metadata:rule_result:compliance'] = 'The compliance percentage for the rule.';
$string['privacy:metadata:rule_result:courseauditid'] = 'The parent course audit execution.';
$string['privacy:metadata:rule_result:ruleconfigid'] = 'The configured rule that was evaluated.';
$string['privacy:metadata:rule_result:status'] = 'The status of the rule result.';
$string['privacy:metadata:rule_result:timecreated'] = 'The time the rule result was recorded.';
$string['privacy:metadata:rule_result:userid'] = 'The user who ran the rule evaluation (0 for scheduled tasks).';
$string['privacy:metadata:snapshots'] = 'Institutional snapshot files of audit results, including course contact identity fields. Files are stored at site level per audit type, not per user.';
$string['reevaluate'] = 'Re-evaluate';
$string['reevaluateall'] = 'Re-evaluate all';
$string['retentiondays'] = 'History retention (days)';
$string['retentiondays_desc'] = 'Delete audit history older than this many days. Use 0 to keep history until the volume limit applies.';
$string['rule_cm_content_contains'] = 'Activity content contains text';
$string['rule_cm_content_contains_desc'] = 'Checks that activities of a given module with a given ID number contain a literal string or regular expression in a module table field, after Moodle format filters.';
$string['rule_cm_content_excludes'] = 'Activity content does not contain text';
$string['rule_cm_content_excludes_desc'] = 'Checks that activities of a given module with a given ID number do not contain a literal string or regular expression in a module table field, after Moodle format filters.';
$string['rule_cm_html_selector'] = 'Activity HTML contains a CSS class';
$string['rule_cm_html_selector_desc'] = 'Parses the formatted HTML of a module table field and checks that an element with the given CSS class contains a literal string or regular expression.';
$string['rule_forum_coursecontact'] = 'Forum started by a course contact';
$string['rule_forum_coursecontact_desc'] = 'Checks that a general forum with the given ID number has at least one discussion started by a user with a course contact role in that course.';
$string['rule_grade_category_moditems'] = 'Grade category contains activity items';
$string['rule_grade_category_moditems_desc'] = 'Checks that a grade category identified by ID number contains at least one grade item that belongs to an activity.';
$string['rule_grade_category_weights'] = 'Grade category weights sum to 100%';
$string['rule_grade_category_weights_desc'] = 'If the grade category uses weighted mean aggregation, the weights of its non extra-credit children must sum to 100% within the configured tolerance.';
$string['rule_section_activity_dates'] = 'Section activities have start and end dates';
$string['rule_section_activity_dates_desc'] = 'Checks that each activity in numbered sections that has a native start and end date pair has both dates set. Activities with only one date are ignored.';
$string['rule_section_date_label'] = 'Section summary contains a dated label';
$string['rule_section_date_label_desc'] = 'Checks that each numbered section summary contains a literal label (case-insensitive) followed by a date that PHP strtotime() can parse.';
$string['ruleactivitydates_fail'] = 'The activity does not have both a start and an end date.';
$string['ruleactivitydates_fail_list'] = 'The following activities are missing a start or end date:';
$string['ruleactivitydates_na'] = 'Only one native date is set, so this activity is not included in the score.';
$string['ruleactivitydates_pass'] = 'The activity has both a start and an end date.';
$string['ruleclass'] = 'Rule class';
$string['ruleclass_help'] = 'The validation rule implementation. After choosing a class, load its parameters to configure them.';
$string['rulecontentcontains_fail'] = 'The field does not contain the expected text.';
$string['rulecontentcontains_fail_list'] = 'The following activities do not contain the expected text:';
$string['rulecontentcontains_pass'] = 'The field contains the expected text.';
$string['rulecontentexcludes_fail'] = 'The field contains the excluded text.';
$string['rulecontentexcludes_fail_list'] = 'The following activities contain the excluded text:';
$string['rulecontentexcludes_pass'] = 'The field does not contain the excluded text.';
$string['rulecreated'] = 'Rule created';
$string['rulecssclass'] = 'CSS class';
$string['rulecssclass_help'] = 'Class name to look for (with or without a leading dot), for example quality-note.';
$string['ruledatelabel'] = 'Date label';
$string['ruledatelabel_help'] = 'Literal text that must appear in the section summary immediately before a parseable date, for example Start:';
$string['ruledeleted'] = 'Rule deleted';
$string['ruleevaluationerror'] = 'The rule could not be evaluated.';
$string['ruleexcludesections'] = 'Excluded sections';
$string['ruleexcludesections_help'] = 'Comma-separated section numbers to skip. Section 0 is always skipped.';
$string['ruleexportfile'] = 'Rule export file';
$string['rulefailingactivities_list'] = 'The following activities do not meet this check:';
$string['rulefailingsections_list'] = 'The following sections do not meet this check:';
$string['rulefield'] = 'Database field';
$string['rulefield_help'] = 'Column of the module instance table to inspect, for example intro or content.';
$string['ruleforumdiscussion_fail'] = 'No discussion was started by a course contact.';
$string['ruleforumdiscussion_fail_list'] = 'The following forums have no discussion started by a course contact:';
$string['ruleforumdiscussion_pass'] = 'A course contact started at least one discussion.';
$string['ruleforumtypefail'] = 'The forum exists but is not a general forum.';
$string['rulegradecategorymissing'] = 'No grade category with ID number "{$a}" was found.';
$string['rulegradecategorymoditems_fail'] = 'The grade category "{$a}" does not contain any activity grade items.';
$string['rulegradecategorymoditems_pass'] = 'The grade category "{$a}" contains at least one activity grade item.';
$string['rulegradeidnumber'] = 'Grade category ID number';
$string['rulegradeidnumber_help'] = 'ID number of the grade category (stored on the category grade item).';
$string['rulegradeweights_fail'] = 'The weights of category "{$a->idnumber}" sum to {$a->sum}%, which is outside the accepted tolerance.';
$string['rulegradeweights_na'] = 'The grade category "{$a}" does not use weighted mean aggregation.';
$string['rulegradeweights_pass'] = 'The weights of category "{$a}" sum to 100% within the accepted tolerance.';
$string['rulehtmlinvalid'] = 'The HTML in the field is not valid.';
$string['rulehtmlselector_fail_noclass'] = 'No element with class "{$a}" was found.';
$string['rulehtmlselector_fail_noclass_list'] = 'The following activities have no element with class "{$a}":';
$string['rulehtmlselector_fail_notext'] = 'Elements with class "{$a}" do not contain the expected text.';
$string['rulehtmlselector_fail_notext_list'] = 'The following activities do not contain the expected text in an element with class "{$a}":';
$string['rulehtmlselector_pass'] = 'An element with class "{$a}" contains the expected text.';
$string['ruleidnumber'] = 'ID number';
$string['ruleidnumber_help'] = 'Course module ID number used to find the activities to evaluate.';
$string['ruleinvalidmodule'] = 'The module "{$a}" is not available.';
$string['ruleinvalidregex'] = 'The regular expression is not valid.';
$string['rulematchmode'] = 'Match mode';
$string['rulematchmode_help'] = 'Literal mode looks for the text in the filtered plain-text version of the field, ignoring case. Regular expression mode uses a PHP regex without delimiters.';
$string['rulematchmodeliteral'] = 'Literal text';
$string['rulematchmoderegex'] = 'Regular expression';
$string['rulemissingfield'] = 'The field "{$a->field}" does not exist on module {$a->modname}.';
$string['rulemissinginstance'] = 'The activity instance is missing.';
$string['rulemissingparams'] = 'The rule is missing required parameters.';
$string['rulemodname'] = 'Module';
$string['rulemodname_help'] = 'The activity type to search for, for example Page or Label.';
$string['rulename'] = 'Name';
$string['rulenomatches'] = 'No {$a->modname} activity with ID number "{$a->idnumber}" was found.';
$string['ruleparameters'] = 'Rule parameters';
$string['rulepattern'] = 'Text or pattern';
$string['rulepattern_help'] = 'Literal text or regular expression (without delimiters) to look for in the filtered field.';
$string['rules'] = 'Rules';
$string['rulesectiondatelabel_fail_nodate'] = 'The label "{$a}" is not followed by a valid date.';
$string['rulesectiondatelabel_fail_nodate_list'] = 'The following sections do not have a valid date after the label "{$a}".';
$string['rulesectiondatelabel_fail_nolabel'] = 'The section summary does not contain the label "{$a}".';
$string['rulesectiondatelabel_fail_nolabel_list'] = 'The following sections do not contain the label "{$a}".';
$string['rulesectiondatelabel_pass'] = 'The section summary contains the label "{$a}" followed by a valid date.';
$string['rulesfor'] = 'Rules for {$a}';
$string['ruleupdated'] = 'Rule updated';
$string['ruleweighting'] = 'Weighting';
$string['ruleweighting_help'] = 'Mandatory rules are included in the compliance percentage. Optional rules are evaluated and reported but do not affect the overall score.';
$string['scheduledtaskuser'] = 'Scheduled task';
$string['selectcategories'] = 'Leave empty for all categories';
$string['selectrules'] = 'Rules to export';
$string['skipcomplete'] = 'Skip fully compliant courses';
$string['skipcomplete_desc'] = 'If enabled, scheduled audits skip a course audit type when the latest execution already has 100% compliance.';
$string['skipunchangeddays'] = 'Skip unchanged courses (days)';
$string['skipunchangeddays_desc'] = 'Skip courses that have not been modified and were last audited at least this many days ago. Use 0 to disable this skip.';
$string['snapshotcolauditdate'] = 'Audit date';
$string['snapshotcolcategorypath'] = 'Category path';
$string['snapshotcolcontactemail'] = 'Contact email';
$string['snapshotcolcontactfirstname'] = 'Contact first name';
$string['snapshotcolcontactid'] = 'Contact ID';
$string['snapshotcolcontactidnumber'] = 'Contact ID number';
$string['snapshotcolcontactlastname'] = 'Contact last name';
$string['snapshotcolcontactusername'] = 'Contact username';
$string['snapshotcolcoursecategory'] = 'Course category';
$string['snapshotcolcoursefullname'] = 'Course full name';
$string['snapshotcolcourseid'] = 'Course ID';
$string['snapshotcolcourseidnumber'] = 'Course ID number';
$string['snapshotcolcourseshortname'] = 'Course short name';
$string['snapshotcreated'] = 'The snapshot was generated.';
$string['snapshotdataformat'] = 'File format';
$string['snapshotnotice'] = 'You are viewing a historical snapshot. Re-evaluation is disabled.';
$string['snapshots'] = 'Snapshots';
$string['snapshotsfor'] = 'Snapshots for {$a}';
$string['statuserror'] = 'Error';
$string['statusfail'] = 'Fail';
$string['statusna'] = 'Not applicable';
$string['statuspass'] = 'Pass';
$string['stubrule'] = 'Stub rule';
$string['stubruleexpected'] = 'Expected';
$string['stubrulefailed'] = 'Failed';
$string['stubrulepassed'] = 'Passed';
$string['taskauditcourses'] = 'Run scheduled course audits';
$string['taskauditcoursessummary'] = 'Scheduled audits: {$a->ran} run, {$a->skipped} skipped, {$a->errors} errors.';
$string['taskcleanuphistory'] = 'Clean up ToDo good audit history';
$string['taskcleanuphistorysummary'] = 'Deleted {$a} historical audit report(s).';
$string['unknowncategory'] = 'Unknown category ({$a})';
$string['viewcoursereport'] = 'View course report';
$string['viewdetail'] = 'View detail';
$string['viewsnapshot'] = 'View snapshot';
$string['weighttolerance'] = 'Grade weight tolerance';
$string['weighttolerance_desc'] = 'Accepted decimal margin when checking that weighted grade items sum to 100%.';
