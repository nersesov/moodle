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
 * Contains the default section navigation output class.
 *
 * @package   core_courseformat
 * @copyright 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_courseformat\output\local\content;

use context_course;
use core\output\named_templatable;
use core_courseformat\base as course_format;
use core_courseformat\output\local\courseformat_named_templatable;
use renderable;
use section_info;
use stdClass;

/**
 * Base class to render a course add section navigation.
 *
 * @package   core_courseformat
 * @copyright 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sectionnavigation implements named_templatable, renderable {

    use courseformat_named_templatable;

    /** @var course_format the course format class */
    protected $format;

    /** @var int the course displayed section */
    protected $sectionno;

    /** @var stdClass the calculated data to prevent calculations when rendered several times */
    protected $data = null;

    /**
     * Constructor.
     *
     * @param course_format $format the course format
     * @param int $sectionno the section number
     */
    public function __construct(course_format $format, int $sectionno) {
        $this->format = $format;
        $this->sectionno = $sectionno;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output typically, the renderer that's calling this function
     * @return stdClass data context for a mustache template
     */
    public function export_for_template(\renderer_base $output): stdClass {
        global $USER;

        if ($this->data !== null) {
            return $this->data;
        }

        $format = $this->format;
        $course = $format->get_course();
        $context = context_course::instance($course->id);

        $modinfo = $this->format->get_modinfo();

        // FIXME: This is really evil and should by using the navigation API.
        $canviewhidden = has_capability('moodle/course:viewhiddensections', $context, $USER);

        $data = (object)[
            'previousurl' => '',
            'nexturl' => '',
            'larrow' => $output->larrow(),
            'rarrow' => $output->rarrow(),
            'currentsection' => $this->sectionno,
        ];

        // Build the list of sections in course display order: top-level sections by section number,
        // with each delegated (sub)section inserted at the position of its delegating course module
        // in the parent sequence. This matches the course index order and, unlike raw section
        // numbers, reflects drag and drop reordering of subsections.
        $delegatedbycm = $modinfo->get_sections_delegated_by_cm();
        $ordered = [];
        $appendsection = function (section_info $section) use (&$appendsection, &$ordered, $modinfo, $delegatedbycm): void {
            $ordered[] = $section;
            foreach ($modinfo->sections[$section->section] ?? [] as $cmid) {
                if (isset($delegatedbycm[$cmid])) {
                    $appendsection($delegatedbycm[$cmid]);
                }
            }
        };
        foreach ($modinfo->get_section_info_all() as $section) {
            if ($section->is_delegated()) {
                continue;
            }
            $appendsection($section);
        }

        // Find the position of the current section in the display order.
        $currentindex = null;
        foreach ($ordered as $index => $section) {
            if ($section->section == $this->sectionno) {
                $currentindex = $index;
                break;
            }
        }

        if ($currentindex !== null) {
            // Previous: the first visible section before the current one in display order.
            for ($i = $currentindex - 1; $i >= 0 && empty($data->previousurl); $i--) {
                $section = $ordered[$i];
                if ($canviewhidden || $section->uservisible) {
                    if (!$section->visible) {
                        $data->previoushidden = true;
                    }
                    $data->previousname = get_section_name($course, $section);
                    $data->previousurl = course_get_url($course, (object) $section, ['navigation' => true]);
                    // If there is no url for the section the link should not be displayed.
                    $data->hasprevious = !empty($data->previousurl);
                }
            }

            // Next: the first visible section after the current one in display order.
            $count = count($ordered);
            for ($i = $currentindex + 1; $i < $count && empty($data->nexturl); $i++) {
                $section = $ordered[$i];
                if ($canviewhidden || $section->uservisible) {
                    if (!$section->visible) {
                        $data->nexthidden = true;
                    }
                    $data->nextname = get_section_name($course, $section);
                    $data->nexturl = course_get_url($course, (object) $section, ['navigation' => true]);
                    // If there is no url for the section the link should not be displayed.
                    $data->hasnext = !empty($data->nexturl);
                }
            }
        }

        $this->data = $data;
        return $data;
    }
}
