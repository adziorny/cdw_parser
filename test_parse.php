<?php

include_once('defs.php');

// Load data element from file
$data = readScript($infile, $tcount);

// Upload elements to database
uploadData ($data, $mysql_info);

/**
 * Uploads the data definitions array into a relational
 * database.  Assumes that $data array comes from readScript() 
 * function and has the same format as output by that function.
 */
function uploadData ($data, $mysql_info)
{
  // Connect to MySQL dB
  $mysqli = mysqli_connect($mysql_info[0], $mysql_info[1], $mysql_info[2], $mysql_info[3]);
  if (mysqli_connect_errno($mysqli)) {
    echo "Failed to connect to MySQL: " . mysqli_connect_error();
    exit;
  }

  // Upload here ...
  // For each table, insert table, select / insert variables, link
  for ($i = 0; $i < count($data[0]); $i++)
  {
    $d_id = getDatabaseID($mysqli,$data[0][$i]);

    $query = "INSERT INTO CDWDEF.TABLES (tname, did, vcount) 
              VALUES (\'" . $data[1][$i] . "\', " . $d_id .
              "," . $data[3][$i] . ")";
    $mysqli->query($query);

    $t_id = $mysqli->insert_id;

    // Now iterate through variables, select/insert, and link
    for ($j = 0; $j < count($data[2][$i]); $j++)
    {
      $v_id = getVariableID($mysqli,$data[2][$i][$j]);

      $query = "INSERT INTO CDWDEF.TV (tid, vid) VALUES (" .
               $t_id . "," . $v_id . ")";
      $mysqli->query($query);

    } // End variable iteration

  } // End table iteration

  $mysqli->close();
}

/**
 * For a given variable array of structure:
 *   [0] -> Variable Name
 *   [1] -> Variable Type [(Size)]
 * Determines if this variable exists in the database,
 * and either returns current vid or inserts and returns vid.
 */
function getVariableID ($mysqli, $var_array)
{
  $var = $var_array[0];
  $type = $var_array[1];
  $size = "NULL";  // Must be a string NULL for uploading to dB
  if ( ($pos_s = strpos($type,'(')) !== FALSE &&
       ($pos_e = strpos($type,')')) !== FALSE ) {
    $size = "\'" . substr($type,$pos_s+1,$pos_e - $pos_s - 1) . "\'";
    $type = substr($type,0,$pos_s);
  }

  $query = "SELECT v.id FROM CDWDEF.VARS 
            WHERE v.vname = \'" . $var . "\' AND
                  v.vtype = \'" . $type . "\' AND
                  v.vsize = " . $size;
  $result = $mysqli->query($query);

  if ($result->num_rows == 0) {
    $query = "INSERT INTO CDWDEF.VARS (vname, vtype, vsize) VALUES (\'" . 
             $var . "\', \'" . $type . "\', ". $size . ")";
    $mysqli->query($query);

    return $mysqli->insert_id;
  }

  $row = $result->fetch_row();

  $result->close();

  return $row[0];
}

/**
 * Returns the database ID associated with that
 * database name, either with a valid SELECT from
 * the DBASES table, or with an INSERT.
 */
function getDatabaseID ($mysqli, $database)
{
  $query = "SELECT d.id FROM CDWDEF.DBASES 
            WHERE d.dname = \'" . $database . "\'";
  $result = $mysqli->query($query);

  if ($result->num_rows == 0) {
    $query = "INSERT INTO CDWDEF.DBASES (dname) VALUES (\'" . $database . "\')";
    $mysqli->query($query);

    return $mysqli->insert_id;
  }

  $row = $result->fetch_row();

  $result->close();

  return $row[0];
}

/**
 * Opens the input file, and reads the data definitions into
 * an array of the following structure:
 *
 *   [0]   DBs array
 *   [1]   Table Names array
 *   [2]   Variables array 
 *       Format: {var_name, type} pairs
 *   [3]   Variable Count array
 */
function readScript ($infile, &$tcount)
{
  // Open file to read
  $fp = @fopen($infile,'r');

  if (!$fp) {
    echo 'Error: could not open file ($infile) for reading';
    exit();
  }

  // Read line-by-line and parse as we go
  $tcount = 0;
  while (($buffer = fgets($fp)) != FALSE) {

    // We only care aboute 'CREATE TABLE' commands now ...
    if (strpos($buffer, 'CREATE TABLE') !== FALSE) {
      $table = substr($buffer, 13);

      $db = '';
      if (strpos($table, 'CDWPRD.') !== FALSE)
        $table = substr($table, 7);
      if (strpos($table, 'CDW.') !== FALSE) {
        $db = 'CDW';
        $table = substr($table, 4);
      }
      if (strpos($table, 'CDW_ANALYTICS.') !== FALSE) {
        $db = 'CDW_ANALYTICS';
        $table = substr($table, 14);
      }

      $table = trim($table);
      $tcount++;

      $dbs[] = $db;
      $tables[] = $table;
      $vars[] = getVariables($fp, $table, $varcount);
      $varcounts[] = $varcount;
    }
  }

  if (!feof($fp)) {
    echo 'Error: unexpected fail of fgets() operation';
  }

  // Close the file
  fclose($fp);

  // Return all of the info we've collected
  return array($dbs, $tables, $vars, $varcounts);
}

/**
 * Gathers the variables for a given table.  Returns an array of
 * variable - type pairs.
 */
function getVariables ($fp, $table, &$varcount)
{
  $buffer = fgets($fp); // this should be '('
  if ($buffer == FALSE) {
    echo 'Error: table ' . $table . ' incorrectly formatted; fgets() resulted false';
    return;
  }

  if (strcmp(trim($buffer),'(') != 0) {
    echo 'Error: table ' . $table . ' incorrectly formatted; no \'(\' found';
    return;
  }

  // Now loop to collect variables
  $varcount = 0;
  while (($buffer = fgets($fp)) != FALSE) {

    // Break on the closing parentheses
    if (strcmp(substr(trim($buffer),0,1),')') == 0)
      break;

    // Get the variable name
    $varline = trim($buffer);
    $pos = strpos($varline,' ');
    $var = substr($varline,0,$pos);

    // Get the type; remove 'NOT NULL' and trailing ','
    $typeline = trim(substr($varline,$pos+1));
    $pos = strpos($typeline,'NOT NULL');
    if ($pos !== FALSE)
      $typeline = trim(substr($typeline,0,$pos-1));

    $pos = strrpos($typeline,',');
    if ($pos === FALSE)
      $type = $typeline;
    elseif ($pos = strlen($typeline) - 1)
      $type = substr($typeline,0,$pos);
    else
      $type = $typeline;

    $varcount++;
    $vars[] = array($var, $type);
  }

  return $vars;
}

?>