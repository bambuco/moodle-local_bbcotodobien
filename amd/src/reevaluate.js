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
import Templates from 'core/templates';

/**
 * Pix icon names keyed by rule status, matching course_report::get_status_icon().
 *
 * @type {Object.<string, string>}
 */
const STATUS_ICONS = {
    pass: 'i/checkedcircle',
    fail: 'i/incorrect',
    error: 'i/warning',
    na: 'i/completion-auto-n',
};

/** @type {number} How long the result style stays on the re-evaluate button */
const HOLD_MS = 3000;

/** @type {string[]} Mutually exclusive Bootstrap variants used on the button */
const BUTTON_VARIANTS = ['btn-primary', 'btn-success', 'btn-danger'];

/** @type {WeakMap<HTMLElement, number>} Pending restore timeouts keyed by button */
const holdTimeouts = new WeakMap();

/**
 * Normalise a status key to one of the known values.
 *
 * @param {string} status Raw status from the web service
 * @return {string}
 */
const resolveStatusKey = (status) => STATUS_ICONS[status] ? status : 'na';

/**
 * Build the icon HTML and translated label for a status.
 *
 * @param {string} status Raw status from the web service
 * @return {Promise<{icon: string, label: string}>}
 */
const renderStatus = async (status) => {
    const key = resolveStatusKey(status);
    const label = await getString('status' + key, 'local_bbcotodobien');
    const icon = await Templates.renderPix(STATUS_ICONS[key], 'core', label);
    return {icon, label};
};

/**
 * Show the heading notice that aggregated values may have changed.
 *
 * @param {HTMLElement} row Rule table row
 */
const showStaleNotice = (row) => {
    const panel = row.closest('.local-bbcotodobien-typepanel');
    const notice = panel?.querySelector('[data-region="stalenotice"]');
    if (notice) {
        notice.removeAttribute('hidden');
    }
};

/**
 * Apply the web service result to the rule row.
 *
 * @param {HTMLElement} row Rule table row
 * @param {Object} response Web service payload
 * @return {Promise}
 */
const updateRuleRow = async (row, response) => {
    const previousStatus = row.dataset.status || '';
    const statusChanged = previousStatus !== response.status;

    const complianceCell = row.querySelector('[data-region="rulecompliance"]');
    if (complianceCell) {
        complianceCell.textContent = Number(response.compliance).toFixed(2) + '%';
    }

    const detailButton = row.querySelector('[data-action="viewdetail"]');
    if (detailButton) {
        detailButton.dataset.auditid = String(response.auditid);
        detailButton.disabled = false;
    }

    const {icon, label} = await renderStatus(response.status);
    const statusCell = row.querySelector('[data-region="rulestatus"]');
    if (statusCell) {
        statusCell.innerHTML = icon + ' ' + label;
    }
    row.dataset.status = response.status;

    if (statusChanged) {
        showStaleNotice(row);
    }
};

/**
 * Keep the re-evaluate button disabled with a result style, then restore primary.
 *
 * @param {HTMLElement} button Re-evaluate button
 * @param {string} variantClass Bootstrap button class (btn-success or btn-danger)
 */
const holdButton = (button, variantClass) => {
    const previous = holdTimeouts.get(button);
    if (previous) {
        clearTimeout(previous);
    }

    button.disabled = true;
    BUTTON_VARIANTS.forEach((cls) => button.classList.remove(cls));
    button.classList.add(variantClass);

    const timeoutId = setTimeout(() => {
        holdTimeouts.delete(button);
        BUTTON_VARIANTS.forEach((cls) => button.classList.remove(cls));
        button.classList.add('btn-primary');
        button.disabled = false;
    }, HOLD_MS);
    holdTimeouts.set(button, timeoutId);
};

/**
 * Re-evaluate one rule and update the report row.
 *
 * @param {Number} courseId
 * @param {HTMLElement} button Re-evaluate button
 * @return {Promise}
 */
const reevaluateRule = (courseId, button) => {
    const row = button.closest('tr');
    const ruleConfigId = Number(button.dataset.ruleconfigid);
    const pendingPromise = new Pending('local_bbcotodobien/reevaluate:rule');
    button.disabled = true;

    return Ajax.call([{
        methodname: 'local_bbcotodobien_reevaluate_rule',
        args: {
            courseid: courseId,
            ruleconfigid: ruleConfigId,
        },
    }])[0]
    .then((response) => updateRuleRow(row, response))
    .then(() => {
        pendingPromise.resolve();
        holdButton(button, 'btn-success');
        return null;
    })
    .catch((error) => {
        pendingPromise.resolve();
        holdButton(button, 'btn-danger');
        Notification.exception(error);
        return null;
    });
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
        const reloadButton = e.target.closest('[data-action="reloadreport"]');
        if (reloadButton) {
            e.preventDefault();
            window.location.reload();
            return;
        }

        const reevaluateButton = e.target.closest('[data-action="reevaluate"]');
        if (reevaluateButton) {
            e.preventDefault();
            reevaluateRule(courseId, reevaluateButton);
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
