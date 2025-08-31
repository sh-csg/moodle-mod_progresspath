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

namespace mod_progresspath;

/**
 * Class imagehelper
 *
 * @package    mod_progresspath
 * @copyright  2025 ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class imagehelper {
    /**
     * Get the dimensions of an image file.
     *
     * @param \stored_file $storedfile The stored file object.
     * @return array An array containing the width and height of the image.
     */
    public static function get_image_dimensions(\stored_file $storedfile): array {
        $content = $storedfile->get_content();
        $imageinfo = getimagesizefromstring($content);
        if (!$imageinfo) {
            if ($storedfile->get_mimetype() === 'image/svg+xml') {
                $xml = simplexml_load_string($content);
                $attr = $xml->attributes();
                if ($attr->width && $attr->height) {
                    return ['width' => (int)$attr->width, 'height' => (int)$attr->height];
                } else if ($attr->viewBox) {
                    $viewbox = explode(' ', (string)$attr->viewBox);
                    if (count($viewbox) === 4) {
                        return [
                            'width' => (int)$viewbox[2] - (int)$viewbox[0],
                            'height' => (int)$viewbox[3] - (int)$viewbox[1]
                        ];
                    }
                }
                return ['width' => 100, 'height' => 100];
            }
            throw new \moodle_exception('invalidimage', 'error');
        }
        return ['width' => $imageinfo[0], 'height' => $imageinfo[1]];
    }
}
