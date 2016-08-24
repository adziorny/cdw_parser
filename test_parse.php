<?php

// Parameters go here
$infile = 'Noname.sql';

$mysql_info = array('localhost', '<user>', '<pwd>', '<db>');

// Start running the script

$data = readScript($infile, $tcount);

// print_r($data[2]);

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
  for ($i = 0; $i < length($data[0]); $i++)
  {
    


  }
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