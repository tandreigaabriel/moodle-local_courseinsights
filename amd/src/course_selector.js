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
 * AMD transport module for the course autocomplete filter.
 *
 * @module     local_courseinsights/course_selector
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {call as ajaxCall} from 'core/ajax';

/**
 * Moodle autocomplete transport function.
 *
 * @param {HTMLElement} selector
 * @param {string} query
 * @param {Function} callback
 * @param {Function} failure
 */
export const transport = (selector, query, callback, failure) => {
    ajaxCall([{
        methodname: 'local_courseinsights_search_courses',
        args: {query},
        done: callback,
        fail: failure,
    }]);
};

/**
 * Transform raw WS response into autocomplete option list.
 * Our WS already returns [{value, label}] so this is a pass-through.
 *
 * @param {HTMLElement} selector
 * @param {Array} results
 * @returns {Array}
 */
export const processResults = (selector, results) => results;
