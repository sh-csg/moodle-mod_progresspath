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
 * Renderer module for the progresspath.
 *
 * @module     mod_progresspath/renderer
 * @copyright   2025 ISB Bayern
 * @author      Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Ajax from 'core/ajax';
import Log from 'core/log';
import Pending from 'core/pending';

export const selectors = {
    PROGRESSPATH_RENDER_CONTAINER_PREFIX: 'progresspath-render-container-'
};

/**
 * Renders the progresspath into the correct div.
 *
 * @param {number} cmId the course module id of the progresspath
 */
export const init = (cmId) => {
    const rendererPendingPromise = new Pending('mod_progresspath/renderer-' + cmId);
    renderLearningmap(cmId);
    rendererPendingPromise.resolve();
};

/**
 * Render the progresspath with the given cmId into the corresponding div in the DOM.
 *
 * @param {number} cmId the course module id of the progresspath
 */
export const renderLearningmap = (cmId) => {
    const promises = Ajax.call(
        [
            {
                methodname: 'mod_progresspath_get_progresspath',
                args: {
                    'cmId': cmId
                }
            }
        ]);

    promises[0].then(data => {
        const targetDiv = document.getElementById(selectors.PROGRESSPATH_RENDER_CONTAINER_PREFIX + cmId);
        targetDiv.innerHTML = data.content;
        return true;
    }).catch((error) => {
        Log.error(error);
        return false;
    });
};
