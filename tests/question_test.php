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
 * Unit tests for the OU multiple response question class.
 *
 * @package    qtype
 * @subpackage oumultiresponse
 * @copyright 2008 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(dirname(__FILE__) . '/test_base.php');


/**
 * Unit tests for (some of) question/type/oumultiresponse/questiontype.php.
 *
 * @copyright  2008 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group qtype_stack
 */
class qtype_stack_question_test extends qtype_stack_testcase {

    /**
     * @return qtype_stack_question the requested question object.
     */
    protected function get_test_stack_question($which = null) {
        return test_question_maker::make_question('stack', $which);
    }

    public function test_get_expected_data() {
        $q = $this->get_test_stack_question();
        $this->assertEquals(array('ans1' => PARAM_RAW, 'ans1_val' => PARAM_RAW), $q->get_expected_data());
    }

    public function test_get_expected_data_test3() {
        $q = $this->get_test_stack_question('test3');
        $this->assertEquals(array('ans1' => PARAM_RAW, 'ans1_val' => PARAM_RAW,
                'ans2' => PARAM_RAW, 'ans2_val' => PARAM_RAW, 'ans3' => PARAM_RAW, 'ans3_val' => PARAM_RAW,
                'ans4' => PARAM_RAW), $q->get_expected_data());
    }

    public function test_get_correct_response_test0() {
        $q = $this->get_test_stack_question('test0');
        $q->start_attempt(new question_attempt_step(), 1);

        $this->assertEquals(array('ans1' => '2', 'ans1_val' => '2'), $q->get_correct_response());
    }

    public function test_get_correct_response_test3() {
        $q = $this->get_test_stack_question('test3');
        $q->start_attempt(new question_attempt_step(), 1);

        $this->assertEquals(array('ans1' => 'x^3', 'ans2' => 'x^4', 'ans3' => '0', 'ans4' => 'true',
            'ans1_val' => 'x^3', 'ans2_val' => 'x^4', 'ans3_val' => '0'),
                $q->get_correct_response());
    }

    public function test_get_is_same_response_test0() {
        $q = $this->get_test_stack_question('test0');

        $this->assertFalse($q->is_same_response(array(), array('ans1' => '2')));
        $this->assertTrue($q->is_same_response(array('ans1' => '2'), array('ans1' => '2')));
        $this->assertFalse($q->is_same_response(array('_seed' => '123'), array('ans1' => '2')));
        $this->assertFalse($q->is_same_response(array('ans1' => '2'), array('ans1' => '3')));
    }

    public function test_is_complete_response_test0() {
        $q = $this->get_test_stack_question('test0');
        $q->start_attempt(new question_attempt_step(), 1);

        $this->assertFalse($q->is_complete_response(array()));
        $this->assertFalse($q->is_complete_response(array('ans1' => '2')));
        $this->assertTrue($q->is_complete_response(array('ans1' => '2', 'ans1_val' => '2')));
    }

    public function test_is_gradable_response_test0() {
        $q = $this->get_test_stack_question('test0');
        $q->start_attempt(new question_attempt_step(), 1);

        $this->assertFalse($q->is_gradable_response(array()));
        $this->assertTrue($q->is_gradable_response(array('ans1' => '2')));
        $this->assertTrue($q->is_gradable_response(array('ans1' => '2', 'ans1_val' => '2')));
    }

    public function test_grade_parts_that_can_be_graded() {
        $q = $this->get_test_stack_question('test3');
        $q->start_attempt(new question_attempt_step(), 4);

        $response = array('ans1' => '(x', 'ans2' => '(x', 'ans3' => 'x+1', 'ans4' => 'false',
                'ans1_val' => 'x^3', 'ans3_val' => 'x');
        $lastgradedresponses = array(
            'odd'     => array('ans1' => 'x^3', 'ans2' => '', 'ans3' => 'x', 'ans4' => '', 'ans1_val' => 'x^3', 'ans3_val' => 'x'),
            'oddeven' => array('ans1' => 'x^3', 'ans2' => '', 'ans3' => 'x', 'ans4' => '', 'ans1_val' => 'x^3', 'ans3_val' => 'x'),
        );
        $partscores = $q->grade_parts_that_can_be_graded($response, $lastgradedresponses, false);

        $expected = array(
            'unique' => new qbehaviour_adaptivemultipart_part_result('unique', 0, 1),
        );
        $this->assertEquals($expected, $partscores);
    }
}
