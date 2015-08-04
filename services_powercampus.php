<?php

function db_init($argdbtype, $argdbhost, $argdbname, $argdbuser, $argdbpass) {
    global $CFG;
    $extdb = ADONewConnection($argdbtype);

    // The dbtype may contain the new connection URL, so make sure we are not connected yet.
    if (!$extdb->IsConnected()) {
        $result = $extdb->Connect($argdbhost, $argdbuser, $argdbpass, $argdbname, true);
        if (!$result) {
            return null;
        }
    }

    $extdb->SetFetchMode(ADODB_FETCH_ASSOC);
    return $extdb;
}

/**
 * These parameters are used to connect to the database.
 * @param mixed $argdbtype 
 * @param mixed $argdbhost 
 * @param mixed $argdbname 
 * @param mixed $argdbuser 
 * @param mixed $argdbpass 
 * @param mixed $argusername 
 * @return array
 */
function get_dashboard_info($argdbtype, $argdbhost, $argdbname, $argdbuser, $argdbpass, $argusername) {
   $adodb = db_init($argdbtype, $argdbhost, $argdbname, $argdbuser, $argdbpass);
   return $adodb->Execute("select * from PC_StudentDashboardInfo where UserName = '".$argusername."' or PEOPLE_ID = '".$argusername."'");
}
