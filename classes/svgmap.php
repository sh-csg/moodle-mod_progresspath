<?php
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

namespace mod_progresspath;

use DOMDocument;
use DOMXPath;

/**
 * Class for handling the content of the progresspath
 *
 * @package     mod_progresspath
 * @copyright   2025 ISB Bayern
 * @author      Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class svgmap {
    /**
     * DOMDocument for parsing the SVG
     * @var DOMDocument
     */
    protected DOMDocument $dom;
    /**
     * DOMXPath for querying the SVG
     * @var DOMXPath
     */
    protected DOMXPath $xpath;
    /**
     * String containing the SVG code (synchronized with $dom)
     * @var string
     */
    protected string $svgcode;
    /**
     * String to prepend to the SVG code (for parsing by DOMDocument)
     * @var string
     */
    protected string $prepend;
    /**
     * Creates map from SVG code
     *
     * @param string $svgcode The SVG code to build the map from
     */
    public function __construct(string $svgcode) {
        global $CFG;
        $this->svgcode = $svgcode;
        // This fixes a problem for loading SVG DTD on Windows locally.
        if (strcasecmp(substr(PHP_OS, 0, 3), 'WIN') == 0) {
            $dtd = '' . new \moodle_url('/mod/progresspath/pix/svg11.dtd');
        } else {
            $dtd = $CFG->dirroot . '/mod/progresspath/pix/svg11.dtd';
        }
        $this->prepend = '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "' . $dtd . '">';

        $this->dom = new \DOMDocument('1.0', 'UTF-8');
        $this->dom->validateOnParse = true;
        $this->dom->preserveWhiteSpace = false;
        $this->dom->formatOutput = true;
        $this->xpath = new \DOMXPath($this->dom);

        $this->load_dom();
    }

    /**
     * Loads the code from svgcode attribute for DOM processing
     *
     * @return void
     */
    public function load_dom(): void {
        $this->remove_tags_before_svg();
        $this->dom->loadXML($this->prepend . $this->svgcode);
    }

    /**
     * Reitems the stylesheet with a new one generated from itemstore
     *
     * @param array $itemstoreoverride array of overrides for itemstore
     * @return void
     */
    public function replace_stylesheet(array $itemstoreoverride = []): void {
        global $OUTPUT;
        $itemstorelocal = array_merge($this->itemstore, $itemstoreoverride);
        $this->svgcode = preg_replace(
            '/<style[\s\S]*style>/i',
            $OUTPUT->render_from_template('mod_progresspath/cssskeleton', $itemstorelocal),
            $this->svgcode
        );
        $this->load_dom();
    }

    /**
     * Removes tags before the SVG tag to avoid parsing problems
     *
     * @return void
     */
    public function remove_tags_before_svg(): void {
        $remove = ['<?xml version="1.0"?>', $this->prepend];
        $this->svgcode = str_replace($remove, '', $this->svgcode);
    }

    /**
     * Returns the current svg code
     *
     * @return string
     */
    public function get_svgcode(): string {
        return $this->svgcode;
    }

    /**
     * Save processed SVG data to svgcode
     *
     * @return void
     */
    public function save_svg_data(): void {
        $this->svgcode = $this->dom->saveXML();
    }

    /**
     * Get attribute value (for unit testing)
     *
     * @param string $id The id of the DOM element
     * @param string $attribute The name of the attribute
     * @return ?string null, if element doesn't exist
     */
    public function get_attribute(string $id, string $attribute): ?string {
        $element = $this->dom->getElementById($id);
        return $element === null ? null : $element->getAttribute($attribute);
    }

    /**
     * Wraps an item in a link.
     *
     * @param string $id Id of a place or path
     * @param string $url URL to link to
     * @return void
     */
    public function wrap_in_link(string $id, string $url): void {
        $element = $this->dom->getElementById($id);
        if ($element) {
            $link = $this->dom->createElement('a');
            $link->setAttribute('xlink:href', $url);
            $element->parentNode->insertBefore($link, $element);
            $link->appendChild($element);
        }
    }

    /**
     * Inserts an image into the SVG.
     *
     * @param string $parentid Id of the parent element
     * @param string $url URL of the image
     * @param int $width Width of the image
     * @param int $height Height of the image
     * @return void
     */
    public function insert_image(string $parentid, string $url, int $width, int $height): void {
        $parent = $this->dom->getElementById($parentid);
        if ($parent) {
            $image = $this->dom->createElement('image');
            $image->setAttribute('xlink:href', $url);
            $image->setAttribute('id', 'progresspath-image');
            $image->setAttribute('width', (string)$width);
            $image->setAttribute('height', (string)$height);
            $parent->insertBefore($image, $parent->firstChild);
        }
    }

    /**
     * Adds the progresspath-hidden class to an element.
     *
     * @param string $id Id of a place or path
     * @return void
     */
    public function set_hidden(string $id): void {
        $element = $this->dom->getElementById($id);
        if ($element) {
            $element->setAttribute('class', $element->getAttribute('class') . ' progresspath-hidden');
        }
    }

    /**
     * Emulates getElementsByClassname via XPath
     *
     * @param string $classname The class name to search for
     * @return array An array of matching elements
     */
    public function get_elements_by_classname(string $classname): array {
        $elements = $this->xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");
        return iterator_to_array($elements);
    }
}
