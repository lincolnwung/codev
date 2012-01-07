<?php if (!isset($_SESSION)) { session_start(); header('P3P: CP="NOI ADM DEV PSAi COM NAV OUR OTRo STP IND DEM"'); } ?>
<?php /*
    This file is part of CoDev-Timetracking.

    CoDev-Timetracking is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    CoDev-Timetracking is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with CoDev-Timetracking.  If not, see <http://www.gnu.org/licenses/>.
*/ ?>

<?php include_once '../path.inc.php'; ?>

<?php
include_once 'i18n.inc.php';
if (!isset($_SESSION['userid'])) {
  echo T_("Sorry, you need to <a href='../'>login</a> to access this page.");
  exit;
}
?>

<?php
   $_POST['page_name'] = T_("Forecasting");
   include 'header.inc.php';

   $logger = Logger::getLogger("forecasting");
?>

<?php include 'login.inc.php'; ?>
<?php include 'menu.inc.php'; ?>

<script language="JavaScript">

// ------ JQUERY --------
$(function() {

		$( "#dialog_CurrentDriftStats" ).dialog({	autoOpen: false, hide: "fade"	});
		$( "#dialog_CurrentDriftStats_link" ).click(function() { $( "#dialog_CurrentDriftStats" ).dialog( "open" ); return false;	});

	});

</script>

<?php
// TODO mettre dans un fichier separe pour inclure aussi dans stats

// ------ JQUERY --------
echo "<div id='dialog_CurrentDriftStats' title='".T_("Effort Deviation")."' style='display: none'>";
echo "<p><strong>".T_("Effort Deviation").":</strong><br>";
echo T_("Overflow day quantity")."<br/>".
        "- ".T_("Computed on task NOT Resolved/Closed on ").date("Y-m-d").".<br/>";
echo "<span style='color:blue'>".T_("Formula").": elapsed - EffortEstim</span></p>";
echo "<p><strong>".T_("Tasks in drift").":</strong><br>";
echo T_("Tasks for which the elapsed time is greater than the estimated effort")."<br>";
echo "<span style='color:blue'>".T_("Formula").": ".T_("drift")." > 1</span></p>";
echo "<p><strong>".T_("Tasks in time").":</strong><br>";
echo T_("Tasks resolved in time")."<br>";
echo "<span style='color:blue'>".T_("Formula").": -1 <= ".T_("drift")." <= 1</span></p>";
echo "<p><strong>".T_("Tasks ahead").":</strong><br>";
echo T_("Tasks resolved in less time than the estimated effort")."<br>";
echo "<span style='color:blue'>".T_("Formula").": ".T_("drift")." < -1</span></p>";
echo "</div>";

// ---


?>

<div id="content">

<?php

include_once "period_stats_report.class.php";
include_once "issue.class.php";
include_once "team.class.php";
include_once "time_tracking.class.php";



// -----------------------------------------------
/**
 *
 * display Drifts for Issues that are CURRENTLY OPENED
 *
 * @param $timeTracking
 */
function displayCurrentDriftStats ($timeTracking) {

   // ---- get Issues that are not Resolved/Closed
   $formatedProdProjectList = implode( ', ', $timeTracking->prodProjectList);
   $issueList = array();

   $query = "SELECT DISTINCT id ".
               "FROM `mantis_bug_table` ".
               "WHERE status < get_project_resolved_status_threshold(project_id) ".
               "AND project_id IN ($formatedProdProjectList) ".
               "ORDER BY id DESC";
   $result = mysql_query($query) or die("Query failed: $query");

   while($row = mysql_fetch_object($result)) {
      $issue = IssueCache::getInstance()->getIssue($row->id);
      $issueList[] = $issue;
   }

   if (0 != count($issueList)) {
      $driftStats_new = $timeTracking->getIssuesDriftStats($issueList);
   } else {
      $driftStats_new = array();
   }

   echo "<table>\n";
   echo "<caption>".T_("EffortDeviation - Today opened Tasks")."&nbsp;&nbsp; <a id='dialog_CurrentDriftStats_link' href='#'><img title='help' src='../images/help_icon.gif'/></a></caption>\n";
   echo "<tr>\n";
   echo "<th></th>\n";
   echo "<th width='100' title='".T_("BEFORE analysis")."'>".T_("PrelEffortEstim")."</th>\n";
   echo "<th width='100' title='".T_("AFTER analysis")."'>".T_("EffortEstim <br/>(BI + BS)")."</th>\n";
   echo "<th>".T_("Tasks")."</th>\n";
   echo "</tr>\n";

   echo "<tr>\n";
   echo "<td title='".T_("If < 0 then ahead on planning.")."'>".T_("EffortDeviation")."</td>\n";
   echo "<td title='elapsed - PrelEffortEstim'>".number_format($driftStats_new["totalDriftETA"], 2)."</td>\n";
   echo "<td title='elapsed - EffortEstim'>".number_format($driftStats_new["totalDrift"], 2)."</td>\n";
   echo "<td></td>\n";
   echo "</tr>\n";

   echo "<tr>\n";
   echo "<td>".T_("Tasks in drift")."</td>\n";
   echo "<td title='".T_("nb tasks")."'>".($driftStats_new["nbDriftsPosETA"])."<span title='".T_("nb days")."' class='floatr'>(".($driftStats_new["driftPosETA"]).")</span></td>\n";
   echo "<td title='".T_("nb tasks")."'>".($driftStats_new["nbDriftsPos"])."<span title='".T_("nb days")."' class='floatr'>(".($driftStats_new["driftPos"]).")</span></td>\n";
   echo "<td title='".T_("Task list for EffortEstim")."'>".$driftStats_new["formatedBugidPosList"]."</td>\n";
   echo "</tr>\n";

   echo "<tr>\n";
   echo "<td>".T_("Tasks in time")."</td>\n";
   echo "<td title='".T_("nb tasks")."'>".($driftStats_new["nbDriftsEqualETA"])."<span title='".T_("nb days")."' class='floatr'>(".($driftStats_new["driftEqualETA"] + $driftStatsClosed["driftEqualETA"]).")</span></td>\n";
   echo "<td title='".T_("nb tasks")."'>".($driftStats_new["nbDriftsEqual"])."<span title='".T_("nb days")."' class='floatr'>(".($driftStats_new["driftEqual"] + $driftStatsClosed["driftEqual"]).")</span></td>\n";
   if (isset($_GET['debug'])) {
      echo "<td title='".T_("Task list for EffortEstim")."'>".$driftStats_new["formatedBugidEqualList"]."</td>\n";
   } else {
      echo "<td title='".$driftStats_new["bugidEqualList"]."'>".T_("Tasks resolved in time")."</td>\n";
   }
   echo "</tr>\n";

   echo "<tr>\n";
   echo "<td>".T_("Tasks ahead")."</td>\n";
   echo "<td title='".T_("nb tasks")."'>".($driftStats_new["nbDriftsNegETA"])."<span title='".T_("nb days")."' class='floatr'>(".($driftStats_new["driftNegETA"]).")</span></td>\n";
   echo "<td title='".T_("nb tasks")."'>".($driftStats_new["nbDriftsNeg"])."<span title='".T_("nb days")."' class='floatr'>(".($driftStats_new["driftNeg"]).")</span></td>\n";
   echo "<td title='".T_("Task list for EffortEstim")."'>".$driftStats_new["formatedBugidNegList"]."</td>\n";
   echo "</tr>\n";
   echo "</table>\n";
}







// =========== MAIN ==========
$year = date('Y');

$defaultTeam = isset($_SESSION['teamid']) ? $_SESSION['teamid'] : 0;
$teamid = isset($_POST['teamid']) ? $_POST['teamid'] : $defaultTeam;
$_SESSION['teamid'] = $teamid;

$session_user = UserCache::getInstance()->getUser($_SESSION['userid']);

$action = isset($_POST['action']) ? $_POST['action'] : '';

$mTeamList = $session_user->getTeamList();
$lTeamList = $session_user->getLeadedTeamList();
$oTeamList = $session_user->getObservedTeamList();
$managedTeamList = $session_user->getManagedTeamList();
$teamList = $mTeamList + $lTeamList + $oTeamList + $managedTeamList;

if (0 == count($teamList)) {
   echo "<div id='content'' class='center'>";
   echo T_("Sorry, you need to be member of a Team to access this page.");
   echo "</div>";

} else {

   $weekDates = week_dates(date('W'),$year);
   $date1  = isset($_REQUEST["date1"]) ? $_REQUEST["date1"] : date("Y-m-d", $weekDates[1]);
   $date2  = isset($_REQUEST["date2"]) ? $_REQUEST["date2"] : date("Y-m-d", $weekDates[5]);
   $startTimestamp = date2timestamp($date1);
   $endTimestamp = date2timestamp($date2);
   $endTimestamp += 24 * 60 * 60 -1; // + 1 day -1 sec.

   echo "DEBUG startTimestamp ".date("Y-m-d H:i:s", $startTimestamp)."<br/>";
   echo "DEBUG endTimestamp   ".date("Y-m-d H:i:s", $endTimestamp)."<br/>";

   $timeTracking = new TimeTracking($startTimestamp, $endTimestamp, $teamid);

   #setInfoForm($teamid, $teamList, $date1, $date2, $defaultProjectid);
   echo "<br/><br/>\n";


   if (0 != $teamid) {

      echo "<br/><br/>\n";
      displayCurrentDriftStats($timeTracking);


   } // if teamid

} // else teamList
?>

</div>

<?php include 'footer.inc.php'; ?>
