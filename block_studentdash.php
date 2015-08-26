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
		$UNGRAD_FULL_TIME = 15;		// Use "Full time" hours as defined in PowerCampus as the default number
		$GRAD_FULL_TIME = 9;				// of credit hours used to calculate the expected graduation Year/Term.

        if ($this->content !== null) {
            return $this->content;
        }

        $username = $USER->username;    // Get the logged on user ID, it will be used to cross reference Campus6..PersonUser table to find the user's PowerCampus PEOPLE_ID

        if ($USER->STUDENT_DASH == null) {            	 // Get currently logged on user's student dashboard info if the STUDENT_DASH object is not already loaded;
            $USER->STUDENT_DASH = new stdClass();     //   this prevents multiple hits on the database server during the same session when chances are nothing has changed
            
            $USER->STUDENT_DASH->dbinfo = get_dashboard_info( get_config('studentdash', 'dbtype')
                                                            , get_config('studentdash', 'servername')
                                                            , get_config('studentdash', 'dbname')
                                                            , get_config('studentdash', 'uid')
                                                            , get_config('studentdash', 'pwd')
                                                            , $username
                                                            );

            $USER->STUDENT_DASH->peopleid = $USER->STUDENT_DASH->dbinfo->fields['PEOPLE_ID'];
            $USER->STUDENT_DASH->program = $USER->STUDENT_DASH->dbinfo->fields['PROGRAM'];
            $USER->STUDENT_DASH->degree = $USER->STUDENT_DASH->dbinfo->fields['DEGREE'];
            $USER->STUDENT_DASH->curriculum = $USER->STUDENT_DASH->dbinfo->fields['CURRICULUM'];
            $USER->STUDENT_DASH->degreetitle = $USER->STUDENT_DASH->dbinfo->fields['DegreeTitle'];
            $USER->STUDENT_DASH->curriculumtitle = $USER->STUDENT_DASH->dbinfo->fields['CurriculumTitle'];
            $USER->STUDENT_DASH->currentyear = $USER->STUDENT_DASH->dbinfo->fields['CurrentYear'];
            $USER->STUDENT_DASH->currentterm = $USER->STUDENT_DASH->dbinfo->fields['CurrentTerm'];
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
        }
		
		if ($USER->STUDENT_DASH->degree != 'PHD' and $USER->STUDENT_DASH->curriculumtitle != 'DLSA')  {    // Exclude block from non-PHD and DLSA students
			$this->title = 'My Academic Progress';
			$this->content = new stdClass;

			$content = '';      			//Holds the actual block's HTML

			if ($USER->STUDENT_DASH->acaplansetup == 'Y') {
				$content = 
				'<body class="noShow" onload="loadInitial(); recalculate(); ">
					<script type="text/javascript">
					    
                        // load up default value for the minimum number of credit hours considered as a "full time" student
					    var initialValue = '. (($USER->STUDENT_DASH->program == 'UNGRAD') ? $UNGRAD_FULL_TIME : $GRAD_FULL_TIME ).';
					    function loadInitial() {
						    document.getElementById("creditsPerTerm").innerHTML = initialValue;
					    }
					
						function recalculate() {
							var creditsRemaining =  '.$USER->STUDENT_DASH->creditsremaining.';
							var creditsPerTerm = parseInt(document.getElementById("creditsPerTerm").innerHTML);

							// Assign a numerical value to the current Year/Term to make calculation easier.
							var activeYearTermValue = parseFloat('.$USER->STUDENT_DASH->currentyear.');
							var activeTerm = "'.$USER->STUDENT_DASH->currentterm.'";
							switch (activeTerm) {
								case "JANUARY":
									activeYearTermValue = activeYearTermValue + 0.00;
									break;
								case "SPRING":
									activeYearTermValue = activeYearTermValue + 0.25;
									break;
								case "SUMMER":
									activeYearTermValue = activeYearTermValue + 0.50;
									break;
								case "FALL":
									activeYearTermValue = activeYearTermValue + 0.75;
									break;
							}
							
							// Calculate the number of terms required to complete the remaining hours if done at the rate specified by creditsPerTerm
							//  Math.ceil used because students cannot graduate in a middle of a term so any partial term needs to be rounded up to the next term.
							var termsLeft =  Math.ceil(creditsRemaining / creditsPerTerm);
							
							// Only consider Fall & Spring terms in this calculation--hence dividing the number of termLeft by 2 (terms) will give us the number of years
							//  it will take at the specified rate until the student graduates.
							var yearsLeft = termsLeft / 2;
							
							// Adding the number of years left to the current Year/Term value should get the graduating Year/Term
							// However, since credits are awarded after the term is completed, 0.25 or 0.50 must subtract (depending on the current term)
							//  to account for the current or the immediately prior FALL/SPRING term, that is, to credit that term with the specified credit hours.
							var gradYearTerm = 0;
							if (activeTerm == "JANUARY" || activeTerm == "SUMMER")
								gradYearTerm = (activeYearTermValue + yearsLeft) - 0.25;
							else
								gradYearTerm = (activeYearTermValue + yearsLeft) - 0.50;
							
							// Calculate the graduation Year/Term by parsing the gradYearTerm value: example 2015.75
							//  The part to the left of the decimal point indicates the year
							//  The part to the right of the decimal indicates the term: 0.00=JANUARY; 0.25=SPRING; 0.50=SUMMER; 0.75=FALL.
							//  Get the expectedYear first by rounding down the gradYearTerm value, and using it to find the decimal portion.
							var expectedYear = Math.floor(gradYearTerm);
							var tempTerm = gradYearTerm - expectedYear;
							
							switch (tempTerm) {
								case 0.00:
									expectedTerm = "JANUARY";
									break;
								case 0.25:
									expectedTerm = "SPRING";
									break;
								case 0.50:
									expectedTerm = "SUMMER";
									break;
								case 0.75:
									expectedTerm = "FALL";
									break;
							}
							
							// Display the expected graduating Year/Term 
							document.getElementById("expectedgraduation").innerHTML = expectedTerm + " " + expectedYear;
						}
					</script>
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
					<script>
						$(document).ready(function(){
							$("#showHide").click(function(){
								$("#dash").toggle();
							});
						});
					</script>
					<br />
					<button id="showHide">Show/Hide Stats</button>
					<div id="dash">
						<h3 class="tableHeader">'.$USER->STUDENT_DASH->degreetitle.' in '.$USER->STUDENT_DASH->curriculumtitle.'</h3>
						<div id="statBox" class="stats">
							<div class="statChartHolder">
								<!--Pie Chart -->
								<div class="progress-pie-chart" data-percent="30">
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
						<div id="statBox"> 
						<table class="stats">
							<tr>
								<td>
									<span class="blueLarge">'.(($USER->STUDENT_DASH->primarygpa == '.0000') ? 'N/A' : $USER->STUDENT_DASH->primarygpa).'</span><br>overall GPA <br><br>
									<span class="blueLarge">'.(($USER->STUDENT_DASH->percentcompletion < 100) ? ($USER->STUDENT_DASH->coursesremaining.'</span> course'.(($USER->STUDENT_DASH->coursesremaining == 1) ? '' : 's').' left') : ('Congratulations on completing your degree</span>')).'<br>
								</td>
								<td>
									<span class="blueLarge">'.(($USER->STUDENT_DASH->percentcompletion < 100) ? '<span id="expectedgraduation"></span>&nbsp;*</span><br>projected graduation<br><br>
									<span class="blueLarge">'.$USER->STUDENT_DASH->creditsremaining.'</span> credit'.(($USER->STUDENT_DASH->creditsremaining == 1) ? '' : 's').' left<br>'
															: ('    </span><br>             <br><br>               <br>'))
								.'</td>
							</tr>
							<tr>
								<td colspan="3" align="left">'.(($USER->STUDENT_DASH->percentcompletion < 100) ?
									'<!-------------------------------------------------------- BEGIN SLIDER -->
									<div id ="slider-tooltip"></div>
									<script src="'.$CFG->wwwroot.'/blocks/studentdash/nouislider.min.js"></script>
									<script>
										// create slider
										var tooltipSlider = document.getElementById("slider-tooltip");
										noUiSlider.create(tooltipSlider, {
											start: initialValue,
											range: {
													"min": 3,
													"14.29%": 6,
													"28.58%": 9,
													"42.87%": 12,
													"57.16%": 15,
													"71.45%": 18,
													"85.74%": 21,
													"max": 24
												},	
											snap: true,
											//pips: {
											//	mode: "values",
											//	values: [],
											//	density: 15
											//}
										});
									</script>
									<script>
										var tipHandles = tooltipSlider.getElementsByClassName("noUi-handle"),
										tooltips = [];
										// Add divs to the slider handles.
										for ( var i = 0; i < tipHandles.length; i++ ){
											tooltips[i] = document.createElement("div");
											tipHandles[i].appendChild(tooltips[i]);
										}
										// Add a class for styling
										tooltips[0].className += "tooltip";
										// Add additional markup
										tooltips[0].innerHTML = "<spanTip></spanTip>";
										// Replace the tooltip reference with the span we just added
										tooltips[0] = tooltips[0].getElementsByTagName("spanTip")[0];
									
										// When the slider changes, write the value to the tooltips.
										tooltipSlider.noUiSlider.on("update", function( values, handle ){
											//split apart at decimal since default .js is for 2 decimal places
											var noDecimal = values[handle].split(".")[0]; 
											//tooltips[handle].innerHTML = noDecimal; 
											// set text of span named "creditsPerTerm"
											document.getElementById("creditsPerTerm").innerHTML = noDecimal;
											recalculate();
										});
									</script>
									<br />
									<!-------------------------------------------------------- END SLIDER -->
									* Based on <span id="creditsPerTerm"></span> credits/semester.<br /> <br />' : '')
									.'If you have any questions about this information, <br />
									please contact the registrar <a href="mailto:registrar@sebts.edu?Subject=Question%20regarding%20Moodle%20Academic%20progress%20(Student%20ID:%20'.$USER->STUDENT_DASH->peopleid.')">here</a>.
									<br />
								</td>
							</tr>
							<tr>
								<td colspan="3" align="left">'.(($USER->STUDENT_DASH->advisorfirst == '') ? '' : 'Your Academic Advisor is '.$USER->STUDENT_DASH->advisorfirst.' '.$USER->STUDENT_DASH->advisorlast).'</td>
							</tr>
						</table>
						</div>
					</div>
				</body>';
			}
			if ($USER->STUDENT_DASH->acaplansetup == 'N') {
				$content = '<div id="plan_not_set">YOUR ACADEMIC PLAN CANNOT BE FOUND! Please see the Registrar\'s Office to set your academic plan or <a href="mailto:registrar@sebts.edu?Subject=Please%20set%20academic%20plan%20for%20studentID:%20'.$USER->STUDENT_DASH->peopleid.'">click here</a> to request to set your academic plan.</div>';			}
		$this->content->text = $content;

		}
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
        return false;
    }
}
