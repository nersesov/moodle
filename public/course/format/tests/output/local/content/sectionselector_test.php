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

namespace core_courseformat\output\local\content;

use core_courseformat\base as course_format;
use core_courseformat\stateactions;
use core_courseformat\stateupdates;
use stdClass;

/**
 * Tests for the section selector output class.
 *
 * @package    core_courseformat
 * @category   test
 * @copyright  2026 Vadym Nersesov <nersesov.vadim@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(sectionselector::class)]
final class sectionselector_test extends \advanced_testcase {
    #[\Override]
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/course/lib.php');
    }

    /**
     * Render the section selector for the given section and return the option labels in order.
     *
     * @param stdClass $course
     * @param int $sectionno
     * @return string[]
     */
    private function get_selector_options(stdClass $course, int $sectionno): array {
        global $PAGE;

        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        course_format::reset_course_cache($course->id);
        $format = course_get_format($course->id);
        $selector = new sectionselector($format, new sectionnavigation($format, $sectionno));
        $data = $selector->export_for_template($PAGE->get_renderer('format_topics'));

        $this->assertMatchesRegularExpression('/<form[^>]*id="sectionmenu"/', $data->selector);
        preg_match_all('/<option[^>]*>(.*?)<\/option>/s', $data->selector, $matches);
        return array_values(array_filter(array_map(
            fn(string $label): string => trim(html_entity_decode(strip_tags($label)), " \n\r\t\u{A0}"),
            $matches[1],
        )));
    }

    /**
     * Subsections must be listed in display order, inside their parent section.
     */
    public function test_export_for_template_with_subsections(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'topics', 'numsections' => 2]);
        $DB->set_field('course_sections', 'name', 'Section 1', ['course' => $course->id, 'section' => 1]);
        $DB->set_field('course_sections', 'name', 'Section 2', ['course' => $course->id, 'section' => 2]);
        rebuild_course_cache($course->id, true);
        $subseca = $generator->create_module('subsection', ['course' => $course->id, 'section' => 1, 'name' => 'Subsection A']);
        $subsecb = $generator->create_module('subsection', ['course' => $course->id, 'section' => 1, 'name' => 'Subsection B']);

        $options = $this->get_selector_options($course, 1);
        $expected = [
            get_string('jumpto'),
            get_string('maincoursepage'),
            get_section_name($course, 0),
            'Section 1',
            'Subsection A',
            'Subsection B',
            'Section 2',
        ];
        $this->assertSame($expected, $options);

        // Move Subsection B before Subsection A inside Section 1 the same way the course editor
        // (drag and drop) does: only the parent sequence changes, the section numbers do not.
        $section1 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1], '*', MUST_EXIST);
        course_format::reset_course_cache($course->id);
        $updates = new stateupdates(course_get_format($course->id));
        (new stateactions())->cm_move($updates, $course, [$subsecb->cmid], $section1->id, $subseca->cmid);

        $options = $this->get_selector_options($course, 1);
        $expected = [
            get_string('jumpto'),
            get_string('maincoursepage'),
            get_section_name($course, 0),
            'Section 1',
            'Subsection B',
            'Subsection A',
            'Section 2',
        ];
        $this->assertSame($expected, $options);
    }
}
