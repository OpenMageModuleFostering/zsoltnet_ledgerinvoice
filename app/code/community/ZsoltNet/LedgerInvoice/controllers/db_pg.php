<?
// PostgreSQL database module

function db_connect($dbhost,$dbport,$dbuser,$dbpass,$dbdb)
{
  if (!$result=pg_connect("host=$dbhost port=$dbport dbname=$dbdb user=$dbuser password=$dbpass")) $result=false;
  return $result;
}

function db_disconnect($db_conn_id)
{
  $result=pg_close($db_conn_id);
  return $result;
}

function db_query($db_qtext,$db_conn_id)
{
  $result=pg_query($db_conn_id,$db_qtext);
  return $result;
}

function db_db_query($db_qdb,$db_qtext,$db_conn_id)
{
  $result=mysql_db_query($db_qdb,$db_qtext,$db_conn_id);
  return $result;
}

function db_fetch_row($db_qresult,&$q_row)
{
  $result=true;
  if (!$q_row=pg_fetch_row($db_qresult)) $result=false;
  return $result;
}

function db_fetch_assoc($db_qresult,&$q_row)
{
  $result=true;
  if (!$q_row=pg_fetch_assoc($db_qresult)) $result=false;
  return $result;
}

function db_data_seek($db_qresult,$row_number)
{
  $result=true;
  if (!mysql_data_seek($db_qresult, $row_number)) $result=false;
  return $result;
}

function db_num_rows($db_qresult)
{
  $result=mysql_num_rows($db_qresult);
  return $result;
}

function db_insert_id($db_conn_id)
{
  $result=mysql_insert_id($db_conn_id);
  return $result;
}

function db_error($db_conn_id)
{
  $result=mysql_error($db_conn_id);
  return $result;
}

?>
