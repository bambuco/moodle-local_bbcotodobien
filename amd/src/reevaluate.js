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
 * Re-evaluate a rule and open diagnostic detail.
 *
 * @module     local_bbcotodobien/reevaluate
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import CancelModal from 'core/modal_cancel';
import * as Fragment from 'core/fragment';
import Notification from 'core/notification';
import Pending from 'core/pending';
import {getString} from 'core/str';

/**
 * Re-evaluate one rule and reload the report.
 *
 * @param {Number} courseId
 * @param {Number} ruleConfigId
 * @return {Promise}
 */
const reevaluateRule = (courseId, ruleConfigId) => {
    const pendingPromise = new Pending('local_bbcotodobien/reevaluate:rule');
    return Ajax.call([{
        methodname: 'local_bbcotodobien_reevaluate_rule',
        args: {
            courseid: courseId,
            ruleconfigid: ruleConfigId,
        },
    }])[0]
    .then(() => {
        pendingPromise.resolve();
        window.location.reload();
        return null;
    })
    .catch(Notification.exception);
};

/**
 * Open the diagnostic detail modal.
 *
 * @param {Number} courseId
 * @param {Number} contextId
 * @param {Number} ruleConfigId
 * @param {Number} auditId
 * @return {Promise}
 */
const showDetail = (courseId, contextId, ruleConfigId, auditId) => {
    return CancelModal.create({
        title: getString('viewdetail', 'local_bbcotodobien'),
        body: Fragment.loadFragment('local_bbcotodobien', 'rule_detail', contextId, {
            courseid: String(courseId),
            ruleconfigid: String(ruleConfigId),
            auditid: String(auditId || 0),
        }),
        large: true,
        show: true,
        removeOnClose: true,
    }).catch(Notification.exception);
};

/**
 * Bind report actions.
 *
 * @param {Number} courseId
 * @param {Number} contextId
 * @param {Number} auditId
 */
export const init = (courseId, contextId, auditId) => {
    document.addEventListener('click', (e) => {
        const reevaluateButton = e.target.closest('[data-action="reevaluate"]');
        if (reevaluateButton) {
            e.preventDefault();
            reevaluateRule(courseId, Number(reevaluateButton.dataset.ruleconfigid));
            return;
        }

        const detailButton = e.target.closest('[data-action="viewdetail"]');
        if (detailButton && !detailButton.disabled) {
            e.preventDefault();
            const rowAuditId = Number(detailButton.dataset.auditid || 0);
            showDetail(courseId, contextId, Number(detailButton.dataset.ruleconfigid), auditId || rowAuditId);
        }
    });
};
