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
 * Tests for the section navigation output class.
 *
 * @package    core_courseformat
 * @category   test
 * @copyright  2026 Vadym Nersesov <nersesov.vadim@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(sectionnavigation::class)]
final class sectionnavigation_test extends \advanced_testcase {
    #[\Override]
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/course/lib.php');
    }

    /**
     * Create a course with two named sections and two subsections inside section 1.
     *
     * Display order: General -> Section 1 -> Subsection A -> Subsection B -> Section 2.
     * Delegated sections are numbered after the regular ones (3 and 4), so ordering by
     * section number would place both subsections after Section 2.
     *
     * @return array [course, subsection A cm id, subsection B cm id]
     */
    private function create_course_with_subsections(): array {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'topics', 'numsections' => 2]);
        $DB->set_field('course_sections', 'name', 'Section 1', ['course' => $course->id, 'section' => 1]);
        $DB->set_field('course_sections', 'name', 'Section 2', ['course' => $course->id, 'section' => 2]);
        rebuild_course_cache($course->id, true);

        $subseca = $generator->create_module('subsection', ['course' => $course->id, 'section' => 1, 'name' => 'Subsection A']);
        $subsecb = $generator->create_module('subsection', ['course' => $course->id, 'section' => 1, 'name' => 'Subsection B']);

        return [$course, $subseca->cmid, $subsecb->cmid];
    }

    /**
     * Move a course module the same way the course editor (drag and drop) does.
     *
     * @param stdClass $course
     * @param int $cmid the course module to move
     * @param int $targetsectionid the target section id
     * @param int|null $targetcmid move before this course module, or to the end of the section if null
     */
    private function move_cm(stdClass $course, int $cmid, int $targetsectionid, ?int $targetcmid = null): void {
        course_format::reset_course_cache($course->id);
        $updates = new stateupdates(course_get_format($course->id));
        (new stateactions())->cm_move($updates, $course, [$cmid], $targetsectionid, $targetcmid);
    }

    /**
     * Export the section navigation data for the given section number.
     *
     * A fresh format instance is used every time so the modinfo reflects the latest course state.
     *
     * @param stdClass $course
     * @param int $sectionno
     * @return stdClass
     */
    private function export_navigation(stdClass $course, int $sectionno): stdClass {
        global $PAGE;

        course_format::reset_course_cache($course->id);
        $format = course_get_format($course->id);
        $navigation = new sectionnavigation($format, $sectionno);
        return $navigation->export_for_template($PAGE->get_renderer('format_topics'));
    }

    /**
     * Previous and next links must follow the course display order, including subsections.
     */
    public function test_export_for_template_with_subsections(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $cmida, $cmidb] = $this->create_course_with_subsections();
        $modinfo = get_fast_modinfo($course);
        $sectiona = $modinfo->get_cm($cmida)->get_delegated_section_info();
        $sectionb = $modinfo->get_cm($cmidb)->get_delegated_section_info();
        $this->assertEquals(3, $sectiona->sectionnum);
        $this->assertEquals(4, $sectionb->sectionnum);

        // Section 1 -> Subsection A (not Section 2).
        $data = $this->export_navigation($course, 1);
        $this->assertTrue($data->hasnext);
        $this->assertSame('Subsection A', $data->nextname);

        // Subsection A: between Section 1 and Subsection B.
        $data = $this->export_navigation($course, $sectiona->sectionnum);
        $this->assertSame('Section 1', $data->previousname);
        $this->assertSame('Subsection B', $data->nextname);

        // Subsection B: between Subsection A and Section 2.
        $data = $this->export_navigation($course, $sectionb->sectionnum);
        $this->assertSame('Subsection A', $data->previousname);
        $this->assertSame('Section 2', $data->nextname);

        // Section 2 <- Subsection B (not Section 1), and nothing after it.
        $data = $this->export_navigation($course, 2);
        $this->assertSame('Subsection B', $data->previousname);
        $this->assertEmpty($data->nexturl);
    }

    /**
     * Moving a subsection only changes the parent sequence, not the section number.
     * The navigation must follow the new display order.
     */
    public function test_export_for_template_after_moving_subsection(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $cmida] = $this->create_course_with_subsections();
        $sectionnuma = get_fast_modinfo($course)->get_cm($cmida)->get_delegated_section_info()->sectionnum;

        // Move Subsection A to the General section.
        // Display order: General -> Subsection A -> Section 1 -> Subsection B -> Section 2.
        $general = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 0], '*', MUST_EXIST);
        $this->move_cm($course, $cmida, $general->id);

        // The section number of the moved subsection is unchanged.
        $this->assertEquals(
            $sectionnuma,
            get_fast_modinfo($course->id)->get_cm($cmida)->get_delegated_section_info()->sectionnum
        );

        $data = $this->export_navigation($course, 0);
        $this->assertSame('Subsection A', $data->nextname);

        $data = $this->export_navigation($course, $sectionnuma);
        $this->assertSame(get_section_name($course, 0), $data->previousname);
        $this->assertSame('Section 1', $data->nextname);

        $data = $this->export_navigation($course, 1);
        $this->assertSame('Subsection A', $data->previousname);
        $this->assertSame('Subsection B', $data->nextname);

        $data = $this->export_navigation($course, 2);
        $this->assertSame('Subsection B', $data->previousname);
        $this->assertEmpty($data->nexturl);
    }
}
