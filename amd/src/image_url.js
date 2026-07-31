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
 * Shared pluginfile URL cache-busting helper.
 *
 * @module     local_dixeo/image_url
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    'use strict';

    /**
     * @param {string} url
     * @param {string} [contenthash]
     * @returns {string}
     */
    const appendImageRev = (url, contenthash = '') => {
        if (!contenthash) {
            return url;
        }
        let cleaned = url.replace(/([?&])rev=[^&]*/g, '');
        cleaned = cleaned.replace(/[?&]$/, '');
        const separator = cleaned.indexOf('?') >= 0 ? '&' : '?';
        return cleaned + separator + 'rev=' + encodeURIComponent(contenthash);
    };

    return {
        appendImageRev,
    };
});
