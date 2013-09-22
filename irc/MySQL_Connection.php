<?

/**
 ** File Name:      includes/MySQL_Connection.php
 ** Last Modified:  1/25/03
 ** Author:         Dan Schaub
 **
 ** File Version:   v1.1-stable
 **
 ** Description:
 ** MySQL Class to control all database interactions.
 **
 ** Requires:
 ** - includes/common.php
 **
 ** Page type: Class file
 **
 ** Parameters:
 ** - query($sql) - MySQL query string
 ** - num_rows($result) - query result reference
 ** - fetch_array($result) - query result reference
 ** - first_row($sql) - MySQL query string
 ** - error($errnum, $errdesc, $sql?) - error number, error description, optional MySQL query string
 **
 **/

class MySQL_Connection {
    var $_connectid;
    var $_resultid;
    var $_resultarray;

    var $_errornum;
    var $_errordesc;

    var $_hostname;
    var $_username;
    var $_password;
    var $_database;

    // the info array should have only variable names specified above (without the underscore)
    function MySQL_Connection($info) {
        if (!is_array($info)) {
            die($this->_error(0, 'Class Error: MySQL information not passed to constructor as an array.'));
        }

        foreach ($info as $key => $value) {
            $this->{"_$key"} = $value;
        }

        $this->_connect();
    }

    function _connect() {
        $this->_connectid = @mysql_connect($this->_hostname, $this->_username, $this->_password) or die($this->_error(mysql_errno(), mysql_error()));
        mysql_select_db($this->_database, $this->_connectid) or die($this->_error(mysql_errno(), mysql_error()));
        return $this->_connectid;
    }

    function query($sql) {
        $this->_resultid = @mysql_query($sql, $this->_connectid) or die($this->_error(mysql_errno(), mysql_error(), $sql));
        return $this->_resultid;
    }

    function num_rows($result = '') {
        if ($result == '') $result = $this->_resultid;
        return mysql_num_rows($result);
    }

    function fetch_array($result = '') {
        if ($result == '') $result = $this->_resultid;
        return mysql_fetch_array($result);
    }

    function first_row($sql) {
        $this->_resultid = @mysql_query($sql, $this->_connectid) or die($this->_error(mysql_errno(), mysql_error(), $sql));
        return mysql_fetch_array($this->_resultid);
    }

    function insert_id() {
        return mysql_insert_id();
    }

    function _error($_errornum, $_errordesc, $sql = '') {
        $message = 'A fatal error occured during interaction with the backend database.  We are sorry for the inconvience.<br><br>';
        $message .= '<blockquote><font size="1">Technical Information:</font><hr><font face="courier new" size="2">';
        $message .= "MySQL Error Code:\t\t$_errornum<br>MySQL Error Description:\t$_errordesc";
        if ($sql != '') $message .= "<br>MySQL Query: $sql";
        $message .= "</font><hr></blockquote>";
        return $message;
    }
}

?>