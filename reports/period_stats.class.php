<?php

// -- CALCULATE DURATIONS --
// Status & Issue classes

class PeriodStats {
  var $startTimestamp;
  var $endTimestamp;

  // The number of issue which current state is 'status' within the timestamp
  var $statusCountList;

  // The bugIds of issues which current state are 'status' within the timestamp
  // REM: $statusIssueList is an array containing lists of bugIds
  var $statusIssueList;
  
  // The projects NOT listed here will be excluded from statistics
  var $projectList;
  
  // The Projects which type is NOT listed here will be excluded from statistics
  var $projectTypeList;
  
  // -------------------------------------------------
  public function PeriodStats($startTimestamp, $endTimestamp) {
  	 
  	global $workingProjectType;
  	global $noCommonProjectType;
  	
    $this->startTimestamp = $startTimestamp;
    $this->endTimestamp = $endTimestamp;

    $this->statusCountList     = array();
    $this->statusIssueList     = array();
    $this->projectList = array();
    
    $this->projectTypeList = array();
    
    // default values
    $this->projectTypeList[] = $workingProjectType;
    $this->projectTypeList[] = $noCommonProjectType;
  }

  // -------------------------------------------------
  // Returns a list of bugId which status is $status
  public function getIssueList($status) {
    return $this->statusIssueList[$status];
  }

  // -------------------------------------------------
  // Returns the number of issues which status is $status
  public function getNbIssues($status) {
    return count($this->statusIssueList[$status]);
  }

  // -------------------------------------------------
  public function computeStats() {
    global $status_new;
    global $status_feedback;
    global $status_ack;
    global $status_analyzed;
    global $status_accepted;
    global $status_openned;
    global $status_resolved;
    global $status_delivered;
    global $status_closed;

    //$this->statusCountList[$status_feedback_ATOS] = 0;
    //$this->statusCountList[$status_feedback_FDJ] = 0;

    $this->statusCountList["submitted"]      = 0;
    $this->statusCountList[$status_new]      = 0;
    $this->statusCountList[$status_feedback] = 0;
    $this->statusCountList[$status_ack]      = 0;
    $this->statusCountList[$status_analyzed] = 0;
    $this->statusCountList[$status_accepted] = 0;
    $this->statusCountList[$status_openned]  = 0;
    $this->statusCountList[$status_resolved] = 0;
    $this->statusCountList[$status_delivered] = 0;
    $this->statusCountList[$status_closed]   = 0;

    $this->statusIssueList["submitted"]      = array();
    $this->statusIssueList[$status_new]      = array();
    $this->statusIssueList[$status_feedback] = array();
    $this->statusIssueList[$status_ack]      = array();
    $this->statusIssueList[$status_analyzed] = array();
    $this->statusIssueList[$status_accepted] = array();
    $this->statusIssueList[$status_openned]  = array();
    $this->statusIssueList[$status_resolved] = array();
    $this->statusIssueList[$status_delivered] = array();
    $this->statusIssueList[$status_closed]   = array();

    // Compute stats
    $this->countIssues_submitted();
    $this->statusCountList[$status_new] = $this->countIssues_new();
    $this->countIssues_other();
  }

  // -------------------------------------------------
  // Count the nb of issues submitted in [startTimestamp, endTimestamp]
  // REM: select only projectTypes in $projectTypeList
  // REM: select only projects in $projectList, if $projectList = 0 then ALL projects.  
  private function countIssues_submitted() {
  	
    $this->statusCountList["submitted"] = 0;

    $formatedProjectTypes = simpleListToSQLFormatedString($this->projectTypeList);

    // sideTaskprojects are excluded
    $query = "SELECT DISTINCT mantis_bug_table.id, mantis_bug_table.date_submitted, mantis_bug_table.project_id ".
      "FROM `mantis_bug_table`, `codev_team_project_table` ".
      "WHERE mantis_bug_table.date_submitted >= $this->startTimestamp AND mantis_bug_table.date_submitted < $this->endTimestamp ".
      "AND mantis_bug_table.project_id = codev_team_project_table.project_id ".
      "AND codev_team_project_table.type IN ($formatedProjectTypes) ";

    // Only for specified Projects   
    if ((isset($this->projectList)) && (0 != count($this->projectList))) {
        	$formatedProjects = simpleListToSQLFormatedString($this->projectList);
    	$query .= "AND mantis_bug_table.project_id IN ($formatedProjects)";
    }
    if (isset($_GET['debug_sql'])) { echo "countIssues_submitted(): query = $query<br/>"; }
    
    $result = mysql_query($query) or die("Query failed: $query");

    while($row = mysql_fetch_object($result))
    {
      $this->statusCountList["submitted"]++;
      $this->statusIssueList["submitted"][] = $row->id;
                        
      if (isset($_GET['debug'])) { 
      	echo "DEBUG submitted $row->id   date < ".date("m Y", $this->endTimestamp)." project $row->project_id <br/>";
      }
    }

    return $count_new;
  }

  // -------------------------------------------------
  // Count the nb of 'new' issues in [startTimestamp, endTimestamp]
  private function countIssues_new() {
    global $status_new;
      
    $count_new = -1;
      
    $this->statusCountList[$status_new] = 0;
      
    // TODO 
    return $count_new;
  }

  // -------------------------------------------------
  // REM: select only projectTypes in $projectTypeList
  // REM: select only projects in $projectList, if $projectList = 0 then ALL projects.  
  private function countIssues_other() {
    
    $formatedProjectTypes = simpleListToSQLFormatedString($this->projectTypeList);
    
    
  	 // select all but SideTasks & rem 'doublons'
    $query = "SELECT DISTINCT mantis_bug_table.id ".
      "FROM `mantis_bug_table`, `codev_team_project_table` ".
      "WHERE mantis_bug_table.project_id = codev_team_project_table.project_id ".
      "AND codev_team_project_table.type IN ($formatedProjectTypes) ";


    // Only for specified Projects   
        if ((isset($this->projectList)) && (0 != count($this->projectList))) {
         $formatedProjects = simpleListToSQLFormatedString($this->projectList);
      $query .= "AND mantis_bug_table.project_id IN ($formatedProjects)";
    }
        if (isset($_GET['debug_sql'])) { echo "countIssues_other(): query = $query<br/>"; }
    
    $result = mysql_query($query) or die("Query failed: $query");

    // For each bugId
    while($row = mysql_fetch_object($result))
    {
      $bugId1 = $row->id;
      // Find most recent transitions where date < $endTimestamp
      $query2 = "SELECT bug_id, new_value, old_value, date_modified ".
        "FROM `mantis_bug_history_table` ".
        "WHERE field_name='status' ".
        "AND bug_id =$bugId1 ".
        "AND date_modified < $this->endTimestamp ".
        "ORDER BY id DESC";
                        
      $result2 = mysql_query($query2) or die("Query failed: $query2");

      if (0 != mysql_num_rows($result2)) {
        $row2 = mysql_fetch_object($result2);

        $this->statusCountList[$row2->new_value]++;
        $this->statusIssueList[$row2->new_value][] = $bugId1;
      }
    }
    if (isset($_GET['debug'])) {
      echo "date < ".date("m Y", $this->endTimestamp)."<br/>";
      foreach ($this->statusIssueList as $state => $bugList) {
        foreach ($bugList as $bug) {
          echo "#$bug ($state)<br/>";
        }
      }
    }
  }
  
} // end class PeriodStats

?>
