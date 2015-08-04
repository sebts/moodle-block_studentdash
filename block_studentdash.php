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
 * Newblock block caps.
 *
 * @package    block_studentdash
 * @copyright  N/a <>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/adodb/adodb.inc.php');
require_once 'services_powercampus.php';

class block_studentdash extends block_base {

    function init() {
        $this->title = get_string('pluginname', 'block_studentdash');
    }

    function get_content() {
        global $CFG, $OUTPUT, $USER;
        $UNGRAD_FULL_TIME = 15; //These "full time" hours are based on estimates of total hours taken over the course of a full year, counting what may be taken during Summer or J terms
        $GRAD_FULL_TIME = 12;   //They will be used to calculate expected graduation, which will only take place in Dec or May, so the assumed full time hours are made slightly larger and then the remaining semesters calculated from them is doubled.

        if ($this->content !== null) {
            return $this->content;
        }

        $username = $USER->username;
        if ($USER->STUDENT_DASH == null) {
            $USER->STUDENT_DASH = new stdClass();
            $USER->STUDENT_DASH->dbinfo = get_dashboard_info(get_config('studentdash', 'dbtype'), 
                                        get_config('studentdash', 'servername'), 
                                        get_config('studentdash','dbname'), 
                                        get_config('studentdash', 'uid'), 
                                        get_config('studentdash', 'pwd'),
                                        $username);
            $USER->STUDENT_DASH->peopleid = $USER->STUDENT_DASH->dbinfo->fields['PEOPLE_ID'];
            $USER->STUDENT_DASH->program = $USER->STUDENT_DASH->dbinfo->fields['PROGRAM'];
            $USER->STUDENT_DASH->degree = $USER->STUDENT_DASH->dbinfo->fields['DEGREE'];
            $USER->STUDENT_DASH->curriculum = $USER->STUDENT_DASH->dbinfo->fields['CURRICULUM'];
            $USER->STUDENT_DASH->degreetitle = $USER->STUDENT_DASH->dbinfo->fields['DegreeTitle'];
            $USER->STUDENT_DASH->curriculumtitle = $USER->STUDENT_DASH->dbinfo->fields['CurriculumTitle'];
            $USER->STUDENT_DASH->activeyti = $USER->STUDENT_DASH->dbinfo->fields['YearTermIndex'];
            $USER->STUDENT_DASH->activeyear = $USER->STUDENT_DASH->dbinfo->fields['LatestAcadYear'];
            $USER->STUDENT_DASH->activeterm = $USER->STUDENT_DASH->dbinfo->fields['LatestAcadTerm'];
            $USER->STUDENT_DASH->acaplansetup = $USER->STUDENT_DASH->dbinfo->fields['ACA_PLAN_SETUP'];
            $USER->STUDENT_DASH->advisorfirst = $USER->STUDENT_DASH->dbinfo->fields['ADVISOR_FNAME'];
            $USER->STUDENT_DASH->advisorlast = $USER->STUDENT_DASH->dbinfo->fields['ADVISOR_LNAME'];
            $USER->STUDENT_DASH->primarygpa = $USER->STUDENT_DASH->dbinfo->fields['OVERALL_GPA'];
            $USER->STUDENT_DASH->coursemin = $USER->STUDENT_DASH->dbinfo->fields['COURSE_MIN'];
            $USER->STUDENT_DASH->coursestaken = $USER->STUDENT_DASH->dbinfo->fields['COURSES_TAKEN'];
            $USER->STUDENT_DASH->coursesremaining = $USER->STUDENT_DASH->coursemin - $USER->STUDENT_DASH->coursestaken;
            $USER->STUDENT_DASH->creditmin = $USER->STUDENT_DASH->dbinfo->fields['CREDIT_MIN'];
            $USER->STUDENT_DASH->creditstaken = $USER->STUDENT_DASH->dbinfo->fields['CREDITS_TAKEN'];
            
            
            
            
            
            
            $USER->STUDENT_DASH->creditsremaining = $USER->STUDENT_DASH->creditmin - $USER->STUDENT_DASH->creditstaken;
            $USER->STUDENT_DASH->percentcompletion = 100 * $USER->STUDENT_DASH->creditstaken / $USER->STUDENT_DASH->creditmin;
            $graduationyti = (($USER->STUDENT_DASH->program == 'UNGRAD') ? 
                             ($USER->STUDENT_DASH->activeyti + ceil(2 * $USER->STUDENT_DASH->creditsremaining / $UNGRAD_FULL_TIME)) 
                           : ($USER->STUDENT_DASH->activeyti + ceil(2 * $USER->STUDENT_DASH->creditsremaining / $GRAD_FULL_TIME)));
            
            $USER->STUDENT_DASH->expectedgraduation = 0 + $USER->STUDENT_DASH->activeyear + (int)($graduationyti / 4);
            
            //We only want expected graduation to be a Fall or Spring semester, so we push it forward one term if it falls in Jan or Summer
		    switch ($USER->STUDENT_DASH->activeterm) {
			    case 'JANUARY':
				    switch ($graduationyti % 4) {
				        case 1:
				        case 0:
				            $USER->STUDENT_DASH->expectedgraduation = ''.'SPRING '."{$USER->STUDENT_DASH->expectedgraduation}";
				            break;
				        case 2:
				        case 3:
				            $USER->STUDENT_DASH->expectedgraduation = ''.'FALL '."{$USER->STUDENT_DASH->expectedgraduation}";
				            break;
				    }
			        break;
    			case 'SPRING':
	    			switch ($graduationyti % 4) {
				        case 1:
				        case 2:
				            $USER->STUDENT_DASH->expectedgraduation = ''.'FALL '."{$USER->STUDENT_DASH->expectedgraduation}";
				            break;
				        case 3:
                            $USER->STUDENT_DASH->expectedgraduation++;
				            $USER->STUDENT_DASH->expectedgraduation = ''.'SPRING '."{$USER->STUDENT_DASH->expectedgraduation}";
				            break;
				        case 0:
				            $USER->STUDENT_DASH->expectedgraduation = ''.'SPRING '."{$USER->STUDENT_DASH->expectedgraduation}";
				            break;
				    }
	    		    break;
	    		case 'SUMMER':
	    		    switch ($graduationyti % 4) {
				        case 1:
				        case 0:
				            $USER->STUDENT_DASH->expectedgraduation = ''.'FALL '."{$USER->STUDENT_DASH->expectedgraduation}";
				            break;
				        case 2:
				        case 3:
                            $USER->STUDENT_DASH->expectedgraduation++;
				            $USER->STUDENT_DASH->expectedgraduation = ''.'SPRING '."{$USER->STUDENT_DASH->expectedgraduation}";
				            break;
				    }
	    		    break;
	    		case 'FALL':
	    		    switch ($graduationyti % 4) {
				        case 1:
				        case 2:
                            $USER->STUDENT_DASH->expectedgraduation++;
				            $USER->STUDENT_DASH->expectedgraduation = ''.'SPRING '."{$USER->STUDENT_DASH->expectedgraduation}";
				            break;
				        case 3:
                            $USER->STUDENT_DASH->expectedgraduation++;
    				        $USER->STUDENT_DASH->expectedgraduation = ''.'FALL '."{$USER->STUDENT_DASH->expectedgraduation}";
				            break;
				        case 0:
				            $USER->STUDENT_DASH->expectedgraduation = ''.'FALL '."{$USER->STUDENT_DASH->expectedgraduation}";
				            break;
				    }
	    		    break;
		    }
        }
        
        $this->content          = new stdClass;

        //This is the actual block html
        $this->content->text    = ($USER->STUDENT_DASH->acaplansetup == 'Y') ?
                                    '<body>
									<script type="text/javascript">
									$(function() {
									  var $ppc = $(\'.progress-pie-chart\'),
										percent = parseInt('.$USER->STUDENT_DASH->percentcompletion.'),
										deg = 360 * percent / 100;
									  if (percent > 50) {
										$ppc.addClass(\'gt-50\');
									  }
									  $(\'.ppc-progress-fill\').css(\'transform\', \'rotate(\' + deg + \'deg)\');
									  $(\'.ppc-percents span\').html(percent + \'%<br><br><span class="gray">complete</span>\');
									});
								</script>

								<div id="dash">
									<h3 class="tableHeader">'.$USER->STUDENT_DASH->degreetitle.' in '.$USER->STUDENT_DASH->curriculumtitle.'</h3>
									<div id="statBox" class="stats">
										<div class="statChartHolder">
										<div class="progress-pie-chart" data-percent="30">
											<!--Pie Chart -->
												<div class="ppc-progress">
													<div class="ppc-progress-fill"></div>
												</div>
											<div class="ppc-percents">
												<div class="pcc-percents-wrapper">
												  <span>%</span>
												</div>
											</div>
										</div>
										<!--End Chart -->
										</div>
									</div>
									<div id="statBox" style="margin-left:25px"> 
										<table class="stats">
											<tr>
												<td>
													<span class="blueLarge">'.(($USER->STUDENT_DASH->primarygpa == '.0000') ? 'N/A' : $USER->STUDENT_DASH->primarygpa).'</span><br>overall GPA <br><br>
													<span class="blueLarge">'.(($USER->STUDENT_DASH->percentcompletion < 100) ? ($USER->STUDENT_DASH->coursesremaining.'</span> course'.(($USER->STUDENT_DASH->coursesremaining == 1) ? '' : 's').' left') : ('Congratulations on completing your degree</span>')).'<br>
												</td>
												<td>
													<span class="blueLarge">'.(($USER->STUDENT_DASH->percentcompletion < 100) ? ($USER->STUDENT_DASH->expectedgraduation.'</span><br>expected graduation<br><br>
													<span class="blueLarge">'.$USER->STUDENT_DASH->creditsremaining.'</span> credit'.(($USER->STUDENT_DASH->creditsremaining == 1) ? '' : 's').' left<br>')
													                        : ('    </span><br>             <br><br>               <br>'))
												.'</td>
											</tr>
											<tr>
											<td colspan="3" align="left">'.(($USER->STUDENT_DASH->advisorfirst == '') ? '' : 'Your Academic Advisor is '.$USER->STUDENT_DASH->advisorfirst.' '.$USER->STUDENT_DASH->advisorlast).'</td>
											</tr>
										</table>
									</div>
									</div>
									</body>'
                                  :'<div id="plan_not_set">YOUR ACADEMIC PLAN CANNOT BE FOUND! Please see the Registrar\'s Office to set your academic plan or <a href="mailto:registrar@sebts.edu?Subject=Please%20set%20academic%20plan%20for%20studentID:%20'.$USER->STUDENT_DASH->peopleid.'">click here</a> to request to set your academic plan.</div>';

        return $this->content;
    }

    public function applicable_formats() {
        return array('my-index' => true);
    }

    function has_config() {
        return true;
    }

    public function html_attributes() {
        $attributes = parent::html_attributes();
        $attributes['class'] .= ' block_'. $this->name();
        return $attributes;
    }

    function instance_allow_multiple() {
        return false;
    }
    
    public function hide_header() {
        return true;
    }
}
