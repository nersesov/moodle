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

namespace core\output;

use moodle_page;
use stdClass;

/**
 * Tests for the activity navigation rendered by the core renderer.
 *
 * @package    core
 * @category   test
 * @copyright  2026 Vadym Nersesov <nersesov.vadim@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(core_renderer::class)]
final class core_renderer_activity_navigation_test extends \advanced_testcase {
    /**
     * Render the activity navigation for a page module.
     *
     * The 'frametop' layout does not support the course index, so the navigation is rendered
     * regardless of the theme in use.
     *
     * @param stdClass $course
     * @param int $cmid
     * @return string
     */
    private function render_activity_navigation(stdClass $course, int $cmid): string {
        $page = new moodle_page();
        $page->set_cm(get_coursemodule_from_id('page', $cmid, 0, false, MUST_EXIST), $course);
        $page->set_url('/mod/page/view.php', ['id' => $cmid]);
        $page->set_pagelayout('frametop');

        $renderer = new core_renderer($page, RENDERER_TARGET_GENERAL);
        return $renderer->activity_navigation();
    }

    /**
     * Get the text of the previous or next activity link.
     *
     * @param string $html
     * @param string $id link id (prev-activity-link or next-activity-link)
     * @return string|null null if the link is not rendered
     */
    private function get_link_text(string $html, string $id): ?string {
        if (!preg_match('/<a[^>]*id="' . $id . '"[^>]*>(.*?)<\/a>/s', $html, $matches)) {
            return null;
        }
        return trim(strip_tags($matches[1]));
    }

    /**
     * Activities inside a subsection must be navigated in display order.
     */
    public function test_activity_navigation_with_subsections(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'topics', 'numsections' => 1]);

        // Display order: Page A -> [Subsection 1: Page B -> Page C] -> Page D.
        // Modules inside the subsection are created last, so insertion order is A, D, B, C.
        $pagea = $generator->create_module('page', ['course' => $course->id, 'section' => 1, 'name' => 'Page A']);
        $subsection = $generator->create_module('subsection', ['course' => $course->id, 'section' => 1, 'name' => 'Subsection 1']);
        $paged = $generator->create_module('page', ['course' => $course->id, 'section' => 1, 'name' => 'Page D']);
        $subsectionnum = get_fast_modinfo($course)->get_cm($subsection->cmid)->get_delegated_section_info()->sectionnum;
        $pageb = $generator->create_module('page', ['course' => $course->id, 'section' => $subsectionnum, 'name' => 'Page B']);
        $pagec = $generator->create_module('page', ['course' => $course->id, 'section' => $subsectionnum, 'name' => 'Page C']);

        $html = $this->render_activity_navigation($course, $pagea->cmid);
        $this->assertNull($this->get_link_text($html, 'prev-activity-link'));
        $this->assertStringContainsString('Page B', $this->get_link_text($html, 'next-activity-link'));
        // The jump to menu lists the other activities in display order and skips the current one
        // and the subsection container.
        $this->assertStringNotContainsString('Page A', $html);
        $this->assertStringNotContainsString('Subsection 1', $html);
        $this->assertLessThan(strpos($html, 'Page C'), strpos($html, 'Page B'));
        $this->assertLessThan(strpos($html, 'Page D'), strpos($html, 'Page C'));

        $html = $this->render_activity_navigation($course, $pageb->cmid);
        $this->assertStringContainsString('Page A', $this->get_link_text($html, 'prev-activity-link'));
        $this->assertStringContainsString('Page C', $this->get_link_text($html, 'next-activity-link'));

        $html = $this->render_activity_navigation($course, $pagec->cmid);
        $this->assertStringContainsString('Page B', $this->get_link_text($html, 'prev-activity-link'));
        $this->assertStringContainsString('Page D', $this->get_link_text($html, 'next-activity-link'));

        $html = $this->render_activity_navigation($course, $paged->cmid);
        $this->assertStringContainsString('Page C', $this->get_link_text($html, 'prev-activity-link'));
        $this->assertNull($this->get_link_text($html, 'next-activity-link'));
    }
}
