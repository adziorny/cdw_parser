<?php
$database = 'CHOPDW';
$user = 'dziornya';
$password = 'password123';

//$conn = db2_connect($database, $user, $password);

/**
$db = ne("ibm:DRIVER={IBM DB2 ODBC DRIVER};DATABASE=CDWPRD;" .
  "HOSTNAME=CHOPDW;PORT=5480;PROTOCOL=TCPIP;", $user, $password);

if ($db) {
    echo "Connection succeeded.";
//    db2_close($conn);
}
else {
    echo "Connection failed.";
}
**/

//connect to database 
$connectionstring = odbc_connect("CHOPDW", $user, $password); 
$Query = "SELECT count(*) from CDWPRD.CDW.Employees"; 
$queryexe = odbc_do($connectionstring, $Query); 

//disconnect from database 

odbc_close($connectionstring); 
?>