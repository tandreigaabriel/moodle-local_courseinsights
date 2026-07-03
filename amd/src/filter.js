// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * AJAX filter and pagination behaviour for the Course Insights dashboard.
 *
 * @module     local_courseinsights/filter
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {loadFragment} from 'core/fragment';
import {replaceNodeContents} from 'core/templates';
import {exception as notifyException} from 'core/notification';

const SELECTORS = {
    AUTO_SUBMIT: 'select[name="cohortid"], select[name="categoryid"],' +
        ' select[name="courseid"], select[name="activitytype"], select[name="studentstatus"]',
    DASHBOARD: '[data-region="local-courseinsights-dashboard"]',
    PAGE_BTN: '[data-ci-page]',
    DATE_PRESET: '.local-courseinsights-date-preset',
    SORT_HEADER: '[data-ci-sortby]',
};

const FILTER_FIELDS = [
    'cohortid', 'categoryid', 'courseid', 'startdate', 'enddate',
    'compare_startdate', 'compare_enddate',
    'activitytype', 'studentstatus', 'usecache',
    'sortby', 'sortdir',
];

const getFilterParams = (form) => {
    const params = {};
    FILTER_FIELDS.forEach(name => {
        const checkbox = form.querySelector(`input[type="checkbox"][name="${name}"]`);
        if (checkbox) {
            params[name] = checkbox.checked ? '1' : '0';
            return;
        }
        const el = form.querySelector(`[name="${name}"]`);
        if (el) {
            params[name] = el.value;
        }
    });
    return params;
};


const initServerSort = (contextid, container) => {
    container.querySelectorAll(SELECTORS.SORT_HEADER).forEach(th => {
        th.addEventListener('click', () => {
            const form = document.querySelector('form');
            if (!form) {
                return;
            }
            const sortkey = th.dataset.ciSortby;
            const sortbyInput = form.querySelector('input[name="sortby"]');
            const sortdirInput = form.querySelector('input[name="sortdir"]');
            if (!sortbyInput || !sortdirInput) {
                return;
            }
            const currentSortby = sortbyInput.value;
            const currentDir = sortdirInput.value;
            const newDir = currentSortby === sortkey && currentDir === 'asc' ? 'desc' : 'asc';
            sortbyInput.value = sortkey;
            sortdirInput.value = newDir;
            const dash = document.querySelector(SELECTORS.DASHBOARD);
            if (dash) {
                loadDashboard(dash, contextid, {...getFilterParams(form), page: '0'});
            } else {
                form.submit();
            }
        });
        th.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                th.click();
            }
        });
    });
};

const fmtDate = (d) => {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
};

const initDatePresets = (contextid) => {
    document.querySelectorAll(SELECTORS.DATE_PRESET).forEach(btn => {
        btn.addEventListener('click', () => {
            const form = btn.closest('form');
            if (!form) {
                return;
            }
            const startInput = form.querySelector('input[name="startdate"]');
            const endInput = form.querySelector('input[name="enddate"]');
            if (!startInput || !endInput) {
                return;
            }

            const today = new Date();
            const preset = btn.dataset.ciPreset;

            if (preset === '7days') {
                const start = new Date(today);
                start.setDate(start.getDate() - 6);
                startInput.value = fmtDate(start);
                endInput.value = fmtDate(today);
            } else if (preset === '30days') {
                const start = new Date(today);
                start.setDate(start.getDate() - 29);
                startInput.value = fmtDate(start);
                endInput.value = fmtDate(today);
            } else if (preset === 'thismonth') {
                const start = new Date(today.getFullYear(), today.getMonth(), 1);
                const end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                startInput.value = fmtDate(start);
                endInput.value = fmtDate(end);
            } else if (preset === 'clear') {
                startInput.value = '';
                endInput.value = '';
            }

            const dash = document.querySelector(SELECTORS.DASHBOARD);
            if (dash) {
                loadDashboard(dash, contextid, {...getFilterParams(form), page: '0'});
            } else {
                form.submit();
            }
        });
    });
};

const loadDashboard = (dash, contextid, params) => {
    loadFragment('local_courseinsights', 'dashboard', contextid, params)
        .then((html, js) => {
            replaceNodeContents(dash, html, js);
            initServerSort(contextid, dash);
            initPagination(dash, contextid);
            return;
        })
        .catch(notifyException);
};

const initPagination = (container, contextid) => {
    container.querySelectorAll(SELECTORS.PAGE_BTN).forEach(btn => {
        btn.addEventListener('click', () => {
            const page = btn.dataset.ciPage;
            const dash = document.querySelector(SELECTORS.DASHBOARD);
            const form = document.querySelector('form');
            if (!dash) {
                return;
            }
            const params = form ? {...getFilterParams(form), page} : {page};
            loadDashboard(dash, contextid, params);
        });
    });
};

const SIDEBAR_KEY = 'ci_sidebar_collapsed';

const initSidebarToggle = () => {
    const toggleBtn = document.getElementById('ci-sidebar-toggle');
    const closeBtn  = document.getElementById('ci-sidebar-close');
    const sidebar   = document.getElementById('ci-filter-sidebar');
    if (!toggleBtn || !sidebar) {
        return;
    }

    const setSidebar = (collapsed) => {
        sidebar.classList.toggle('ci-sidebar--closed', collapsed);
        toggleBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        try { localStorage.setItem(SIDEBAR_KEY, collapsed ? '1' : '0'); } catch (_e) {}
    };

    try {
        if (localStorage.getItem(SIDEBAR_KEY) === '1') {
            setSidebar(true);
        }
    } catch (_e) {}

    toggleBtn.addEventListener('click', () => setSidebar(!sidebar.classList.contains('ci-sidebar--closed')));
    if (closeBtn) {
        closeBtn.addEventListener('click', () => setSidebar(true));
    }
};

export const init = (contextid) => {
    const dashboard = document.querySelector(SELECTORS.DASHBOARD);
    if (dashboard) {
        initServerSort(contextid, dashboard);
        initPagination(dashboard, contextid);
    }

    initDatePresets(contextid);
    initSidebarToggle();

    document.querySelectorAll(SELECTORS.AUTO_SUBMIT).forEach(select => {
        select.addEventListener('change', () => {
            const form = select.closest('form');
            const dash = document.querySelector(SELECTORS.DASHBOARD);
            if (!dash) {
                form.submit();
                return;
            }
            loadDashboard(dash, contextid, {...getFilterParams(form), page: '0'});
        });
    });
};
