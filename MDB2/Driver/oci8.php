<?php
// vim: set et ts=4 sw=4 fdm=marker:
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1998-2004 Manuel Lemos, Tomas V.V.Cox,                 |
// | Stig. S. Bakken, Lukas Smith                                         |
// | All rights reserved.                                                 |
// +----------------------------------------------------------------------+
// | MDB2 is a merge of PEAR DB and Metabases that provides a unified DB  |
// | API as well as database abstraction for PHP applications.            |
// | This LICENSE is in the BSD license style.                            |
// |                                                                      |
// | Redistribution and use in source and binary forms, with or without   |
// | modification, are permitted provided that the following conditions   |
// | are met:                                                             |
// |                                                                      |
// | Redistributions of source code must retain the above copyright       |
// | notice, this list of conditions and the following disclaimer.        |
// |                                                                      |
// | Redistributions in binary form must reproduce the above copyright    |
// | notice, this list of conditions and the following disclaimer in the  |
// | documentation and/or other materials provided with the distribution. |
// |                                                                      |
// | Neither the name of Manuel Lemos, Tomas V.V.Cox, Stig. S. Bakken,    |
// | Lukas Smith nor the names of his contributors may be used to endorse |
// | or promote products derived from this software without specific prior|
// | written permission.                                                  |
// |                                                                      |
// | THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS  |
// | "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT    |
// | LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS    |
// | FOR A PARTICULAR PURPOSE ARE DISCLAIMED.  IN NO EVENT SHALL THE      |
// | REGENTS OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,          |
// | INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, |
// | BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS|
// |  OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED  |
// | AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT          |
// | LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY|
// | WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE          |
// | POSSIBILITY OF SUCH DAMAGE.                                          |
// +----------------------------------------------------------------------+
// | Author: Lukas Smith <smith@backendmedia.com>                         |
// +----------------------------------------------------------------------+

// $Id$

/**
 * MDB2 OCI8 driver
 *
 * @package MDB2
 * @category Database
 * @author Lukas Smith <smith@backendmedia.com>
 */
class MDB2_Driver_oci8 extends MDB2_Driver_Common
{
    // {{{ properties
    var $escape_quotes = "'";

    var $uncommitedqueries = 0;

    // }}}
    // {{{ constructor

    /**
     * Constructor
     */
    function __construct()
    {
        parent::__construct();

        $this->phptype = 'oci8';
        $this->dbsyntax = 'oci8';

        $this->supported['sequences'] = true;
        $this->supported['indexes'] = true;
        $this->supported['summary_functions'] = true;
        $this->supported['order_by_text'] = true;
        $this->supported['current_id'] = true;
        $this->supported['affected_rows'] = true;
        $this->supported['transactions'] = true;
        $this->supported['limit_queries'] = true;
        $this->supported['LOBs'] = true;
        $this->supported['replace'] = true;
        $this->supported['sub_selects'] = true;
        $this->supported['auto_increment'] = false;

        $this->options['DBA_username'] = false;
        $this->options['DBA_password'] = false;
        $this->options['database_name_prefix'] = false;
        $this->options['emulate_database'] = true;
        $this->options['default_tablespace'] = false;
        $this->options['home'] = false;
        $this->options['default_text_field_length'] = 4000;
    }

    function MDB2_Driver_oci8()
    {
        $this->__construct();
    }

    // }}}
    // {{{ errorInfo()

    /**
     * This method is used to collect information about an error
     *
     * @param integer $error
     * @return array
     * @access public
     */
    function errorInfo($error = null)
    {
        if (is_resource($error)) {
            $error_data = @OCIError($error);
            $error = null;
        } elseif ($this->connection) {
            $error_data = @OCIError($this->connection);
        } else {
            $error_data = @OCIError();
        }
        $native_code = $error_data['code'];
        $native_msg  = $error_data['message'];
        if (is_null($error)) {
            static $ecode_map;
            if (empty($ecode_map)) {
                $ecode_map = array(
                    900 => MDB2_ERROR_SYNTAX,
                    904 => MDB2_ERROR_NOSUCHFIELD,
                    921 => MDB2_ERROR_SYNTAX,
                    923 => MDB2_ERROR_SYNTAX,
                    942 => MDB2_ERROR_NOSUCHTABLE,
                    955 => MDB2_ERROR_ALREADY_EXISTS,
                    1476 => MDB2_ERROR_DIVZERO,
                    1722 => MDB2_ERROR_INVALID_NUMBER,
                    2289 => MDB2_ERROR_NOSUCHTABLE,
                    2291 => MDB2_ERROR_CONSTRAINT,
                    2292 => MDB2_ERROR_CONSTRAINT,
                    2449 => MDB2_ERROR_CONSTRAINT,
                );
            }
            if (isset($ecode_map[$native_code])) {
                $error = $ecode_map[$native_code];
            }
        }
        return array($error, $native_code, $native_msg);
    }

    // }}}
    // {{{ autoCommit()

    /**
     * Define whether database changes done on the database be automatically
     * committed. This function may also implicitly start or end a transaction.
     *
     * @param boolean $auto_commit flag that indicates whether the database
     *                                 changes should be committed right after
     *                                 executing every query statement. If this
     *                                 argument is 0 a transaction implicitly
     *                                 started. Otherwise, if a transaction is
     *                                 in progress it is ended by committing any
     *                                 database changes that were pending.
     * @access public
     * @return mixed MDB2_OK on success, a MDB2 error on failure
     */
    function autoCommit($auto_commit)
    {
        $this->debug(($auto_commit ? 'On' : 'Off'), 'autoCommit');
        if ($this->auto_commit == $auto_commit) {
            return MDB2_OK;
        }
        if ($auto_commit) {
            if ($this->connection && MDB2::isError($commit = $this->commit())) {
                return $commit;
            }
        } elseif (!$this->destructor_registered) {
            $this->destructor_registered = true;
            $this->PEAR();
        }
        $this->auto_commit = $auto_commit;
        $this->in_transaction = !$auto_commit;
        return MDB2_OK;
    }

    // }}}
    // {{{ commit()

    /**
     * Commit the database changes done during a transaction that is in
     * progress. This function may only be called when auto-committing is
     * disabled, otherwise it will fail. Therefore, a new transaction is
     * implicitly started after committing the pending changes.
     *
     * @access public
     * @return mixed MDB2_OK on success, a MDB2 error on failure
     */
    function commit()
    {
        $this->debug('commit transaction', 'commit');
        if (!$this->supports('transactions')) {
            return $this->raiseError(MDB2_ERROR_UNSUPPORTED, null, null,
                'commit: transactions are not in use');
        }
        if ($this->auto_commit) {
            return $this->raiseError(MDB2_ERROR, null, null,
            'commit: transaction changes are being auto commited');
        }
        if ($this->uncommitedqueries) {
            if (!@OCICommit($this->connection)) {
                return $this->raiseError();
            }
            $this->uncommitedqueries = 0;
        }
        return MDB2_OK;
    }

    // }}}
    // {{{ rollback()

    /**
     * Cancel any database changes done during a transaction that is in
     * progress. This function may only be called when auto-committing is
     * disabled, otherwise it will fail. Therefore, a new transaction is
     * implicitly started after canceling the pending changes.
     *
     * @access public
     * @return mixed MDB2_OK on success, a MDB2 error on failure
     */
    function rollback()
    {
        $this->debug('rolling back transaction', 'rollback');
        if ($this->auto_commit) {
            return $this->raiseError(MDB2_ERROR, null, null,
                'rollback: transactions can not be rolled back when changes are auto commited');
        }
        if ($this->uncommitedqueries) {
            if (!@OCIRollback($this->connection)) {
                return $this->raiseError();
            }
            $this->uncommitedqueries = 0;
        }
        return MDB2_OK;
    }

    // }}}
    // {{{ _doConnect()

    /**
     * do the grunt work of the connect
     *
     * @return connection on success or MDB2 Error Object on failure
     * @access private
     */
    function _doConnect($username, $password, $persistent = false)
    {
        if (!PEAR::loadExtension($this->phptype)) {
            return $this->raiseError(MDB2_ERROR_NOT_FOUND, null, null,
                'extension '.$this->phptype.' is not compiled into PHP');
        }

        if (isset($this->dsn['hostspec'])) {
            $sid = $this->dsn['hostspec'];
        } else {
            $sid = getenv('ORACLE_SID');
        }
        if (empty($sid)) {
            return $this->raiseError(MDB2_ERROR, null, null,
                'it was not specified a valid Oracle Service Identifier (SID)');
        }

        if ($this->options['home']) {
            putenv('ORACLE_HOME='.$this->options['home']);
        }
        putenv('ORACLE_SID='.$sid);
        $function = ($persistent ? 'OCIPLogon' : 'OCINLogon');
        $connection = @$function($username, $password, $sid);
        if (!$connection) {
            $connection =  $this->raiseError(MDB2_ERROR_CONNECT_FAILED);
        }
        return $connection;
    }

    // }}}
    // {{{ connect()

    /**
     * Connect to the database
     *
     * @return MDB2_OK on success, MDB2 Error Object on failure
     * @access public
     */
    function connect()
    {
        if ($this->database_name && $this->options['emulate_database']) {
             $this->dsn['username'] = $this->options['database_name_prefix'].$this->database_name;
        }
        if ($this->connection != 0) {
            if (count(array_diff($this->connected_dsn, $this->dsn)) == 0
                && $this->opened_persistent == $this->options['persistent']
            ) {
                return MDB2_OK;
            }
            $this->_close();
        }

        $connection = $this->_doConnect($this->dsn['username'], $this->dsn['password'], $this->options['persistent']);
        if (MDB2::isError($connection)) {
            return $connection;
        }
        $this->connection = $connection;
        $this->connected_dsn = $this->dsn;
        $this->opened_persistent = $this->options['persistent'];
        $query = "ALTER SESSION SET NLS_DATE_FORMAT='YYYY-MM-DD HH24:MI:SS'";
        $doquery = $this->_doQuery($query);
        if (MDB2::isError($doquery)) {
            $this->_close();
            return $doquery;
        }
        $query = "ALTER SESSION SET NLS_NUMERIC_CHARACTERS='. '";
        $error = $this->_doQuery($query);
        if (MDB2::isError($error)) {
            $this->_close();
            return $error;
        }
        return MDB2_OK;
    }

    // }}}
    // {{{ _close()
    /**
     * Close the database connection
     *
     * @return boolean
     * @access private
     **/
    function _close()
    {
        if ($this->connection != 0) {
            if ($this->supports('transactions') && !$this->auto_commit) {
                $result = $this->rollback();
                if (MDB2::isError($result)) {
                    return $result;
                }
            }
            @OCILogOff($this->connection);
            $this->connection = 0;
            $this->uncommitedqueries = 0;
            unset($GLOBALS['_MDB2_databases'][$this->db_index]);
        }
        return MDB2_OK;
    }

    // }}}
    // {{{ standaloneQuery()

   /**
     * execute a query as DBA
     *
     * @param string $query the SQL query
     * @return mixed MDB2_OK on success, a MDB2 error on failure
     * @access public
     */
    function &standaloneQuery($query)
    {
        $connection = $this->_doConnect($this->options['DBA_username'], $this->options['DBA_password'], $this->options['persistent']);
        if (MDB2::isError($connection)) {
            return $connection;
        }

        $isManip = MDB2::isManip($query);
        $offset = $this->row_offset;
        $limit = $this->row_limit;
        $this->row_offset = $this->row_limit = 0;
        $query = $this->_modifyQuery($query, $isManip, $limit, $offset);

        $result = $this->_doQuery($query, $isManip, $connection, false);
        @OCILogOff($connection);
        if (MDB2::isError($result)) {
            return $result;
        }

        if ($isManip) {
            return $result;
        }

        return $this->_wrapResult($result, $types, true, false, $limit, $offset);
    }

    // }}}
    // {{{ _modifyQuery()

    /**
     * This method is used by backends to alter queries for various
     * reasons.
     *
     * @param string $query  query to modify
     * @return the new (modified) query
     * @access private
     */
    function _modifyQuery($query)
    {
        // "SELECT 2+2" must be "SELECT 2+2 FROM dual" in Oracle
        if (preg_match('/^\s*SELECT/i', $query)
            && !preg_match('/\sFROM\s/i', $query)
        ) {
            $query .= " FROM dual";
        }
        return $query;
    }

    // }}}
    // {{{ _doQuery()

    /**
     * Execute a query
     * @param string $query  query
     * @param boolean $isManip  if the query is a manipulation query
     * @param resource $connection
     * @param string $database_name
     * @return result or error object
     * @access private
     */
    function _doQuery($query, $isManip = false, $connection = null, $database_name = null)
    {
        $this->last_query = $query;
        $this->debug($query, 'query');
        if ($this->getOption('disable_query')) {
            if ($isManip) {
                return MDB2_OK;
            }
            return null;
        }

        if (is_null($connection)) {
            $connection = $this->connection;
        }

        $statement = @OCIParse($connection, $stmt);
        if (!$statement) {
            return $this->raiseError(MDB2_ERROR, null, null,
                'Could not create statement');
        }

        $mode = $this->auto_commit ? OCI_COMMIT_ON_SUCCESS : OCI_DEFAULT;
        $result = @OCIExecute($statement, $mode);
        if (!$result) {
            return $this->raiseError();
        }

        if ($isManip) {
            return @OCIRowCount($result);
        }
        return $result;
    }

    // }}}
    // {{{ prepare()

    /**
     * Prepares a query for multiple execution with execute().
     * With some database backends, this is emulated.
     * prepare() requires a generic query as string like
     * 'INSERT INTO numbers VALUES(?,?,?)'. The ? are wildcards.
     * Types of wildcards:
     *    ? - a quoted scalar value, i.e. strings, integers
     *
     * @param string $query the query to prepare
     * @param array $fields specifies the names of the fields (required for LOBs only)
     * @return mixed resource handle for the prepared query on success, a DB
     *        error on failure
     * @access public
     * @see execute
     */
    function &prepare($query, $types = null, $result_types = null)
    {
        $this->debug($query, 'prepare');
        if (is_array($types)) {
            $columns = '';
            $variables = '';
            foreach ($types as $parameter => $type) {
                if ($type == 'clob' || $type == 'blob') {
                    $columns.= ($columns ? ' RETURNING ' : ',').$parameter;
                    $variables.= ($columns ? ' INTO ' : ',').':'.$parameter;
                }
            }
            $query.= $columns.$variables;
        }
        $statement = @OCIParse($this->connection, $query);
        if (!$statement) {
            return $this->raiseError(MDB2_ERROR, null, null,
                'Could not create statement');
        }

        $class_name = 'MDB2_Statement_'.$this->phptype;
        return new $class_name($this, $query, $positions, $types, $result_types, $statement);
    }

    // }}}
    // {{{ nextID()

    /**
     * returns the next free id of a sequence
     *
     * @param string $seq_name name of the sequence
     * @param boolean $ondemand when true the seqence is
     *                           automatic created, if it
     *                           not exists
     * @return mixed MDB2 Error Object or id
     * @access public
     */
    function nextID($seq_name, $ondemand = true)
    {
        $sequence_name = $this->getSequenceName($seq_name);
        $this->expectError(MDB2_ERROR_NOSUCHTABLE);
        $result = $this->queryOne("SELECT $sequence_name.nextval FROM DUAL");
        $this->popExpect();
        if (MDB2::isError($result)) {
            if ($ondemand && $result->getCode() == MDB2_ERROR_NOSUCHTABLE) {
                $this->loadModule('manager');
                $result = $this->manager->createSequence($seq_name, 1);
                if (MDB2::isError($result)) {
                    return $result;
                }
                return $this->nextId($seq_name, false);
            }
        }
        return $result;
    }

    // }}}
    // {{{ currId()

    /**
     * returns the current id of a sequence
     *
     * @param string $seq_name name of the sequence
     * @return mixed MDB2_Error or id
     * @access public
     */
    function currId($seq_name)
    {
        $sequence_name = $this->getSequenceName($seq_name);
        return $this->queryOne("SELECT $sequence_name.currval FROM DUAL");
    }
}

class MDB2_Result_oci8 extends MDB2_Result_Common
{
    // {{{ _skipLimitOffset()

    /**
     * Skip the first row of a result set.
     *
     * @param resource $result
     * @return mixed a result handle or MDB2_OK on success, a MDB2 error on failure
     * @access private
     */
    function _skipLimitOffset()
    {
        if ($this->limit) {
            if ($this->rownum > $this->limit) {
                return false;
            }
        }
        if ($this->offset) {
            while ($this->offset_count < $this->offset) {
                ++$this->offset_count;
                if (!@OCIFetchInto($this->result, $row, OCI_RETURN_NULLS)) {
                    $this->offset_count = $this->offset;
                    return false;
                }
            }
        }
        return true;
    }

    // }}}
    // {{{ fetchRow()

    /**
     * Fetch a row and insert the data into an existing array.
     *
     * @param int       $fetchmode  how the array data should be indexed
     * @param int    $rownum    number of the row where the data can be found
     * @return int data array on success, a MDB2 error on failure
     * @access public
     */
    function &fetchRow($fetchmode = MDB2_FETCHMODE_DEFAULT, $rownum = null)
    {
        if (!$this->_skipLimitOffset()) {
            return null;
        }
        if (!is_null($rownum)) {
            $seek = $this->seek($rownum);
            if (MDB2::isError($seek)) {
                return $seek;
            }
        }
        if ($fetchmode == MDB2_FETCHMODE_DEFAULT) {
            $fetchmode = $this->db->fetchmode;
        }
        if ($fetchmode & MDB2_FETCHMODE_ASSOC) {
            @OCIFetchInto($this->result, $row, OCI_ASSOC+OCI_RETURN_NULLS);
            if (is_array($row)
                && $this->db->options['portability'] & MDB2_PORTABILITY_LOWERCASE
            ) {
                $row = array_change_key_case($row, CASE_LOWER);
            }
        } else {
            @OCIFetchInto($this->result, $row, OCI_RETURN_NULLS);
        }
        if (!$row) {
            if (is_null($this->result)) {
                return $this->db->raiseError(MDB2_ERROR_NEED_MORE_DATA, null, null,
                    'fetchRow: resultset has already been freed');
            }
            return null;
        }
        if (isset($this->types)) {
            $row = $this->db->datatype->convertResultRow($this->types, $row);
        }
        if ($this->db->options['portability'] & MDB2_PORTABILITY_RTRIM) {
            $this->db->_rtrimArrayValues($row);
        }
        if ($fetchmode === MDB2_FETCHMODE_OBJECT) {
            $object_class = $this->db->options['fetch_class'];
            if ($object_class == 'stdClass') {
                $row = (object) $row;
            } else {
                $row = &new $object_class($row);
            }
        }
        ++$this->rownum;
        return $row;
    }

    // }}}
    // {{{ getColumnNames()

    /**
     * Retrieve the names of columns returned by the DBMS in a query result.
     *
     * @return mixed associative array variable
     *      that holds the names of columns. The indexes of the array are
     *      the column names mapped to lower case and the values are the
     *      respective numbers of the columns starting from 0. Some DBMS may
     *      not return any columns when the result set does not contain any
     *      rows.
     * @access public
     */
    function getColumnNames()
    {
        $columns = array();
        $numcols = $this->numCols();
        if (MDB2::isError($numcols)) {
            return $numcols;
        }
        for ($column = 0; $column < $numcols; $column++) {
            $column_name = @OCIColumnName($this->result, $column + 1);
            $columns[$column_name] = $column;
        }
        if ($this->db->options['portability'] & MDB2_PORTABILITY_LOWERCASE) {
            $columns = array_change_key_case($columns, CASE_LOWER);
        }
        return $columns;
    }

    // }}}
    // {{{ numCols()

    /**
     * Count the number of columns returned by the DBMS in a query result.
     *
     * @return mixed integer value with the number of columns, a MDB2 error
     *      on failure
     * @access public
     */
    function numCols()
    {
        $cols = @OCINumCols($this->result);
        if (is_null($cols)) {
            if (is_null($this->result)) {
                return $this->db->raiseError(MDB2_ERROR_NEED_MORE_DATA, null, null,
                    'numCols: resultset has already been freed');
            }
            return $this->db->raiseError();
        }
        return $cols;
    }

    // }}}
    // {{{ free()

    /**
     * Free the internal resources associated with $result.
     *
     * @return boolean true on success, false if $result is invalid
     * @access public
     */
    function free()
    {
        $free = @OCIFreeCursor($this->result);
        if (!$free) {
            if (is_null($this->result)) {
                return MDB2_OK;
            }
            return $this->db->raiseError();
        }
        $this->result = null;
        return MDB2_OK;
    }
}

class MDB2_BufferedResult_oci8 extends MDB2_Result_oci8
{
    var $buffer;
    var $buffer_rownum = - 1;

    // {{{ _fillBuffer()

    /**
     * Fill the row buffer
     *
     * @param int $rownum   row number upto which the buffer should be filled
                            if the row number is null all rows are ready into the buffer
     * @return boolean true on success, false on failure
     * @access private
     */
    function _fillBuffer($rownum = null)
    {
        if (isset($this->buffer) && is_array($this->buffer)) {
            if (is_null($rownum)) {
                if (!end($this->buffer)) {
                    return false;
                }
            } elseif (isset($this->buffer[$rownum])) {
                return (bool)$this->buffer[$rownum];
            }
        }

        if (!$this->_skipLimitOffset()) {
            return false;
        }

        $row = true;
        while ((is_null($rownum) || $this->buffer_rownum < $rownum)
            && (!$this->limit || $this->buffer_rownum < $this->limit)
            && ($row = @OCIFetchInto($this->result, $buffer, OCI_RETURN_NULLS))
        ) {
            ++$this->buffer_rownum;
            $this->buffer[$this->buffer_rownum] = $buffer;
        }

        if (!$row) {
            ++$this->buffer_rownum;
            $this->buffer[$this->buffer_rownum] = false;
            return false;
        } elseif ($this->limit && $this->buffer_rownum >= $this->limit) {
            ++$this->buffer_rownum;
            $this->buffer[$this->buffer_rownum] = false;
        }
        return true;
    }

    // }}}
    // {{{ fetchRow()

    /**
     * Fetch a row and insert the data into an existing array.
     *
     * @param int       $fetchmode  how the array data should be indexed
     * @param int    $rownum    number of the row where the data can be found
     * @return int data array on success, a MDB2 error on failure
     * @access public
     */
    function &fetchRow($fetchmode = MDB2_FETCHMODE_DEFAULT, $rownum = null)
    {
        if (is_null($this->result)) {
            return $this->db->raiseError(MDB2_ERROR_NEED_MORE_DATA, null, null,
                'fetchRow: resultset has already been freed');
        }
        if (!is_null($rownum)) {
            $seek = $this->seek($rownum);
            if (MDB2::isError($seek)) {
                return $seek;
            }
        }
        $target_rownum = $this->rownum + 1;
        if ($fetchmode == MDB2_FETCHMODE_DEFAULT) {
            $fetchmode = $this->db->fetchmode;
        }
        if (!$this->_fillBuffer($target_rownum)) {
            return null;
        }
        $row = $this->buffer[$target_rownum];
        if ($fetchmode & MDB2_FETCHMODE_ASSOC) {
            $column_names = $this->getColumnNames();
            foreach ($column_names as $name => $i) {
                $column_names[$name] = $row[$i];
            }
            $row = $column_names;
        }
        if (isset($this->types)) {
            $row = $this->db->datatype->convertResultRow($this->types, $row);
        }
        if ($this->db->options['portability'] & MDB2_PORTABILITY_RTRIM) {
            $this->db->_rtrimArrayValues($row);
        }
        if ($fetchmode === MDB2_FETCHMODE_OBJECT) {
            $object_class = $this->db->options['fetch_class'];
            if ($object_class == 'stdClass') {
                $row = (object) $row;
            } else {
                $row = &new $object_class($row);
            }
        }
        ++$this->rownum;
        return $row;
    }

    // }}}
    // {{{ seek()

    /**
    * seek to a specific row in a result set
    *
    * @param int    $rownum    number of the row where the data can be found
    * @return mixed MDB2_OK on success, a MDB2 error on failure
    * @access public
    */
    function seek($rownum = 0)
    {
        if (is_null($this->result)) {
            return $this->db->raiseError(MDB2_ERROR_NEED_MORE_DATA, null, null,
                'seek: resultset has already been freed');
        }
        $this->rownum = $rownum - 1;
        return MDB2_OK;
    }

    // }}}
    // {{{ valid()

    /**
     * check if the end of the result set has been reached
     *
     * @return mixed true or false on sucess, a MDB2 error on failure
     * @access public
     */
    function valid()
    {
        if (is_null($this->result)) {
            return $this->db->raiseError(MDB2_ERROR_NEED_MORE_DATA, null, null,
                'valid: resultset has already been freed');
        }
        if ($this->_fillBuffer($this->rownum + 1)) {
            return true;
        }
        return false;
    }

    // }}}
    // {{{ numRows()

    /**
     * returns the number of rows in a result object
     *
     * @return mixed MDB2 Error Object or the number of rows
     * @access public
     */
    function numRows()
    {
        if (is_null($this->result)) {
            return $this->db->raiseError(MDB2_ERROR_NEED_MORE_DATA, null, null,
                'seek: resultset has already been freed');
        }
        $this->_fillBuffer();
        return $this->buffer_rownum;
    }

    // }}}
    // {{{ free()

    /**
     * Free the internal resources associated with $result.
     *
     * @return boolean true on success, false if $result is invalid
     * @access public
     */
    function free()
    {
        $this->buffer = null;
        $this->buffer_rownum = null;
        $free = parent::free();
    }
}

class MDB2_Statement_oci8 extends MDB2_Statement
{
    // }}}
    // {{{ _executePrepared()

    /**
     * Execute a prepared query statement.
     *
     * @param mixed $result_class string which specifies which result class to use
     * @param mixed $result_wrap_class string which specifies which class to wrap results in
     * @return mixed a result handle or MDB2_OK on success, a MDB2 error on failure
     *
     * @access private
     */
    function &_executePrepared($result_class = true, $result_wrap_class = false)
    {
        $isManip = MDB2::isManip($this->query);
        $query = $this->db->_modifyQuery($this->query);
        $this->db->last_query = $query;
        $this->db->debug($query, 'query');
        if ($this->db->getOption('disable_query')) {
            if ($isManip) {
                return MDB2_OK;
            }
            return null;
        }

        $connected = $this->db->connect();
        if (MDB2::isError($connected)) {
            return $connected;
        }

        $lobs = $descriptors = array();
        foreach ($this->values as $parameter => $value) {
            if (!isset($value)) {
                $value_quoted = 'NULL';
            } else {
                $type = isset($this->types[$parameter]) ? $this->types[$parameter] : null;
                if ($type == 'clob' || $type == 'blob') {
                    $lobs[$parameter]['close'] = true;
                    if (is_resource($value)) {
                        $lobs[$parameter]['close'] = false;
                    } elseif (preg_match('/^(\w+:\/\/)(.*)$/', $value, $match)) {
                        if ($match[1] == 'file://') {
                            $value = $match[2];
                        }
                        $value = @fopen($value, 'r');
                    } else {
                        $fp = @tmpfile();
                        @fwrite($fp, $value);
                        @rewind($fp);
                        $value = $fp;
                    }
                    $lobs[$parameter]['value'] = $value;
                    $descriptors[$parameter] = @OCINewDescriptor($this->db->connection, OCI_D_LOB);
                    if (!is_object($descriptors[$parameter])) {
                        $success = $this->db->raiseError();
                        break;
                    }
                }
                $value_quoted = $this->quote($value, $type);
                if (MDB2::isError($value_quoted)) {
                    return $value_quoted;
                }
            }
            if (is_resource($value)) {
                if (!OCIBindByName($statement, ':'.$parameter, $descriptors[$parameter], -1, ($type == 'blob' ? OCI_B_BLOB : OCI_B_CLOB))) {
                    $success =  $this->db->raiseError();
                    break;
                }
            } else {
                if (!OCIBindByName($statement, ':'.$parameter, $descriptors[$parameter], -1)) {
                    $success = $this->db->raiseError();
                    break;
                }
            }
        }

        $mode = (empty($lobs) && $this->auto_commit) ? OCI_COMMIT_ON_SUCCESS : OCI_DEFAULT;
        $result = @OCIExecute($statement, $mode);
        if (!$result) {
            return $this->db->raiseError();
        }

        if (!empty($lobs)) {
            foreach ($lobs as $parameter => $stream) {
                while (!@feof($stream['value'])) {
                    $data = @fread($stream['value'], $this->getOption('lob_buffer_length'));
                    if (!$descriptors[$parameter]->write($data, $this->getOption('lob_buffer_length'))) {
                        $success = $this->db->raiseError();
                        break(2);
                    }
                }
                if ($stream['close']) {
                    @fclose($stream);
                }
            }

            if (!MDB2::isError($success)) {
                if ($this->auto_commit) {
                    if (MDB2::isError($success)) {
                        if (!OCIRollback($this->db->connection)) {
                            $success = $this->db->raiseError();
                        }
                    } else {
                        if (!OCICommit($this->db->connection)) {
                            $success = $this->db->raiseError();
                        }
                    }
                } else {
                    ++$this->db->uncommitedqueries;
                }
            }
        }

        reset($descriptors);
        for ($j = count($descriptors); $descriptor < $j; next($descriptors)) {
            @$descriptors[key($descriptors)]->free();
        }

        if (MDB2::isError($success)) {
            return $success;;
        }

        if ($isManip) {
            return @OCIRowCount($statement);
        }

        return $this->db->_wrapResult($result, $isManip, $this->types,
            $result_class, $result_wrap_class, $this->row_offset, $this->row_limit);
    }

    // }}}
    // {{{ free()

    /**
     * Release resources allocated for the specified prepared query.
     *
     * @return mixed MDB2_OK on success, a MDB2 error on failure
     * @access public
     */
    function free()
    {
        @OCIFreeStatement($this->statement);
        return MDB2_OK;
    }
}
?>