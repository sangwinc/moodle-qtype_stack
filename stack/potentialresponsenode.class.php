<?php
// This file is part of Stack - http://stack.bham.ac.uk/
//
// Stack is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Stack is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Stack.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Node in a potential response tree.
 *
 * @copyright  2012 University of Birmingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/answertest/controller.class.php');

/**
 * A node in a potential response tree.
 *
 * @copyright  2012 University of Birmingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stack_potentialresponse_node {

    /*
     * @var stack_cas_casstring Hold's nominal "student's answer".
     */
    public $sans;

    /*
     * @var stack_cas_casstring Hold's nominal "teacher's answer".
     */
    public $tans;

    /*
     * @var string Name of answer test to be used here.
     */
    private $answertest;

    /*
     * @var string Any options for the answer test
     */
    public $atoptions;

    /*
    * @var boolean Suppress any feedback from the answer test itself?
     */
    private $quiet;

    /*
    * @var boolean Used during editing to indicate this node should be removed.
     */
    private $delete;

    /*
     * @var string Private notes/memos about this potential response.
     */
    private $notes;

    /*
     * @var array Holds the information for each branch.
     */
    private $branches;

    public function __construct($sans, $tans, $answertest, $atoptions = null, $quiet=false, $notes='') {
        if (is_a($sans, 'stack_cas_casstring')) {
            $this->sans        = $sans;
        } else {
            throw new Exception('stack_potentialresponse_node: sans must be a stack_cas_casstring');
        }
        if (is_a($tans, 'stack_cas_casstring')) {
            $this->tans        = $tans;
        } else {
            throw new Exception('stack_potentialresponse_node: tans must be a stack_cas_casstring');
        }
        $this->answertest  = $answertest;
        if (!is_bool($quiet)) {
            throw new Exception('stack_potentialresponse_node: quiet must be a boolean.');
        } else {
            $this->quiet        = $quiet;
        }

        $this->atoptions = $atoptions;// This is not a stack_options class, but a string.
        $this->notes = $notes;

        $this->branches     = array();
    }

    /**
     * Add information into each branch
     *
     * @param int $trueorfalse 0 or 1, which branch to set.
     * @param string $mod score modification method. One of the values recognised by {@link update_score()}
     * @param float $score score value used by update_score.
     * @param float $penalty penalty for this branch.
     * @param int $nextnode index of the node to process next on this branch.
     * @param string $feedback feedback for this branch.
     * @param string $answernote answer note for this branch.
     */
    public function add_branch($trueorfalse, $mod, $score, $penalty, $nextnode, $feedback, $answernote) {
        if ($trueorfalse !== 0 && $trueorfalse !== 1) {
            throw new Exception('stack_potentialresponse_node: branches can only be 0 or 1.');
        }

        $this->branches[$trueorfalse] = array(
            'scoremodification' => $mod,
            'score'             => $score,
            'penalty'           => $penalty,
            'nextnode'          => $nextnode,
            'feedback'          => trim($feedback),
            'answernote'        => trim($answernote),
        );
    }

    /**
     * Actually execute the test for this node.
     */
    public function do_test($nsans, $ntans, $ncasopts, $options, $currentscore) {

        if (false === $ncasopts) {
            $ncasopts = $this->atoptions;
        }
        $at = new stack_ans_test_controller($this->answertest, $nsans, $ntans, $options, $ncasopts);
        $at->do_test();

        $result = $at->get_at_mark();
        if ($result) {
            $resultbranch = $this->branches[1];
        } else {
            $resultbranch = $this->branches[0];
        }

        $answernotes = array();
        if ($at->get_at_answernote()) {
            $answernotes[] = $at->get_at_answernote();
        }
        if ($resultbranch['answernote']) {
            $answernotes[] = $resultbranch['answernote'];
        }

        // If the answer test is running in quiet mode we suppress any
        // automatically generated feedback from the answertest itself.
        $feedback = array();
        if (!$this->quiet && $at->get_at_feedback()) {
            $feedback[] = $at->get_at_feedback();
        }
        if ($resultbranch['feedback']) {
            $feedback[] = $resultbranch['feedback'];
        }

        return array(
            'result' => $result,
            'valid' => $at->get_at_valid(),
            'errors' => $at->get_at_errors(),
            'newscore' => $this->update_score($currentscore, $resultbranch),
            'penalty' => $resultbranch['penalty'],
            'nextnode' => $resultbranch['nextnode'],
            'answernote' => implode(' | ', $answernotes),
            'feedback' => implode(' ', $feedback),
        );
    }

    /**
     * Traverse this node, updating the results array that is used by
     * {@link stack_potentialresponse_tree::evaluate_response()}.
     *
     * @param stack_potentialresponse_tree_state $results to be updated.
     * @param int $key the index of this node.
     * @param stack_cas_session $cascontext the CAS context that holds all the relevant variables.
     * @param stack_options $options
     * @return array with two elements, the updated $results and the index of the next node.
     */
    public function traverse($results, $key, $cascontext, $options) {

        $sans   = $cascontext->get_value_key('PRSANS' . $key);
        $tans   = $cascontext->get_value_key('PRTANS' . $key);
        $atopts = $cascontext->get_value_key('PRATOPT' . $key);

        // If we can't find atopts then they were not processed by the CAS.
        // They might still be some in the potential response which do not
        // need to be processed.
        if (false === $atopts) {
            $atopts = null;
        }

        list($result, $valid, $errors, $newscore, $penalty, $nextnode, $answernote, $feedback) =
                array_values($this->do_test($sans, $tans, $atopts, $options, $results->_score));

        $results->_valid = $results->_valid && $valid;
        $results->_score = $newscore;

        if ($answernote) {
            $results->add_answernote($answernote);
        }

        if ($feedback) {
            $results->add_feedback($feedback);
        }

        if ($penalty !== '') {
            $results->_penalty = $penalty;
        }

        $results->_errors .= $errors;

        return $nextnode;
    }

    /*
     * Does this answer test actually require options,?
     */
    public function process_atoptions() {
        $at = new stack_ans_test_controller($this->answertest, '', '', null, '');
        return $at->process_atoptions();
    }

    protected function update_score($oldscore, $resultbranch) {
        switch($resultbranch['scoremodification']) {
            case '=':
                return $resultbranch['score'];

            case '+':
                return $oldscore + $resultbranch['score'];

            case '-':
                return $oldscore - $resultbranch['score'];

            case '=AT':
                return $resultbranch['score'];

            default:
                throw new Exception('stack_potentialresponse_node: update_score called ' .
                        'with invalid score modificiation method: ' . $resultbranch['scoremodification']);
        }
    }

    /**
     * @return array of CAS strings. These cas strings include the names of all
     * the input variables that are required by this node.
     */
    public function get_required_cas_strings() {

        $ct = new stack_cas_text($this->branches[0]['feedback'] . $this->branches[1]['feedback']);
        $requiredcasstrings = $ct->get_all_raw_casstrings();

        $requiredcasstrings[] = $this->sans->get_raw_casstring();
        $requiredcasstrings[] = $this->tans->get_raw_casstring();

        if ($this->process_atoptions() && trim($this->atoptions) != '') {
            $requiredcasstrings[] = $this->atoptions;
        }

        return $requiredcasstrings;
    }

    /**
     * Get the context variables that this node uses, so that they can be
     * pre-evaluated prior to transversing the tree.
     * @param string $key used to make the variable names unique to this node.
     * @return array of stack_cas_casstring
     */
    public function get_context_variables($key) {
        $variables = array();

        $this->sans->set_key('PRSANS' . $key);
        $variables[] = $this->sans;

        $this->tans->set_key('PRTANS' . $key);
        $variables[] = $this->tans;

        if ($this->process_atoptions()) {
            $atopts = new stack_cas_casstring($this->atoptions);
            $atopts->validate('t', false, false);
            $atopts->set_key('PRATOPT' . $key);
            $variables[] = $atopts;
        }

        return $variables;
    }

    /**
     * Returns answer notes, used for question testing.
     * @return array string Of all the answer notes this tree might produce.
     */
    public function get_answer_notes() {
        return array($this->branches[true]['answernote'], $this->branches[false]['answernote']);
    }
}
