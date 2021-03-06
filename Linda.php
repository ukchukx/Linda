<?php

namespace solutionstack\Linda;

require_once \realpath(\dirname(__FILE__)) . "/" . 'Linda.inc';

/**
 *
 * @brief      Linda is an Object Oriented ORM for PHP Built on top PDO, it currently supports MySql.
 * @author     Olubodun Agbalaya (s.stackng@gmail.com)
 * @version    GIT: 1.1.0
 * @copyright  2018 Olubodun Agbalaya
 * @license    MIT License
 * @link       https://github.com/solutionstack/Linda
 */
class Linda
{

    /** Holds last error raised */
    protected $lindaError = null;
    protected $dbLink;
    protected $tableModel;

    /** Holds last run query */
    protected $currentQuery;

    /** Holds column names for current schema @var array */
    protected $modelSchema = array();
    protected $defaultLimit = 1000;
    protected $defaultStartIndex = 0;

    /** Apply MySQL DSTINCT to query @var boolean */
    protected $distinctResult = false;
    protected $resultObject = null;

    /** Number of rows returned or affected by last ops */
    protected $lastAffectedRowCount = 0;
    protected $queryConfig = array();
    protected $defaultPrimaryKeyColumn = null;
    protected $isFectchOps = false;

    /** configured in Linda.inc */
    const LINDA_DB_HOST = LINDA_DB_HOSTC;

    /** configured in Linda.inc */
    const LINDA_DB_NAME = LINDA_DB_NAMEC;

    /** configured in Linda.inc */
    const LINDA_DB_TYPE = LINDA_DB_TYPEC;

    /** configured in Linda.inc */
    const LINDA_DB_USER = LINDA_DB_USERC;

    /** configured in Linda.inc */
    const LINDA_DB_PASSW = LINDA_DB_PASSWC;

    public function __construct()
    {

        $this->initConnnection();
    }

    /**
     * @ignore
     * @internal
     */
    protected function setTable($tableName)
    {

        if (\is_string($tableName)) {
            $this->tableModel = $tableName;
        }
    }

    /**
     * Fetches the table schema (columns) and finds the primary key if available
     *
     * @ignore
     * @internal
     */
    //+========================================================================================
    protected function parseModel()
    {

        $this->modelSchema = []; //always reset;

        $sql
            = "SELECT COLUMN_NAME,COLUMN_KEY FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = :table AND TABLE_SCHEMA = :schema";
        try {
            $core = $this->dbLink;
            $stmt = $core->prepare($sql);
            $stmt->bindValue(':table', $this->tableModel, \PDO::PARAM_STR);
            $stmt->bindValue(':schema', self::LINDA_DB_NAME, \PDO::PARAM_STR);
            $stmt->execute();

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $this->modelSchema[] = $row['COLUMN_NAME'];

                //detect primary key
                if (isset($row['COLUMN_KEY']) && $row['COLUMN_KEY'] === "PRI") {

                    $this->defaultPrimaryKeyColumn = $row['COLUMN_NAME'];
                }
            }

            if ( ! count($this->modelSchema)) {

                throw new Exception("Schemea *" . $this->tableModel . "* not valid or doesn't exist");
            }
            return $this->modelSchema;
        } catch (PDOException $pe) {
            trigger_error('Could not connect to MySQL database. ' . $pe->getMessage(), E_USER_ERROR);
        }
    }

//+=========================================================================================================

    /**
     * @ignore
     * @internal
     */
    private function initConnnection()
    {
        try {
            $this->dbLink = new \PDO(
                self::LINDA_DB_TYPE . ':host=' . self::LINDA_DB_HOST . ';dbname=' . self::LINDA_DB_NAME,
                self::LINDA_DB_USER, self::LINDA_DB_PASSW, array(\PDO::ATTR_PERSISTENT => true)
            );
            $this->dbLink->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            return true;
        } catch (\PDOException $pe) {
            $this->lindaError = "DB_CONNECT_ERROR";
            throw $pe;
        }
    }

//+=========================================================================================================

    /**
     * The get method fetches data from the table
     *
     * @param array() | string $feilds An associative array containing fields to get from the table, or the string *
     * @param array() $queryConfig A configuration array, that contains option for the get operation
     *
     * <pre>
     *  $queryConfig = array(
     *
     *                     "whereGroup" => array(
     *                       [
     *                           "actor_id"=>array("value"=>5, "operator"=>"="),
     *                          "last_name"=>array("value"=>"'%ER", "operator"=>"LIKE"),
     *                          "comparisonOp"=>"AND",
     *                          "nextOp"=>"OR"
     *                        ],
     *
     *                     [
     *           "actor_id"=>array("value"=>"last_name", "operator"=>"LIKE")
     *
     *                     ] ),
     *
     *
     *
     *
     *                      "where_in"=>array(
     *                             fieldName => "id",
     *                             options => " 10, 15, 22,
     *                               query => "",
     *                              operator = > "AND"
     *
     *                          ),
     *
     *                     "innerJoinGroup" => array(
     *                       [
     *                       table => "table_name",
     *                       conditional_column_a => "column name"
     *                       conditional_column_b => "column name"
     *                       ],
     *                       [
     *                     table => "table_name",
     *                       conditional_column => "column name"
     *                       ]
     *
     *
     *                     ) ,
     *
     *                      "limit" =>[
     *                               index => 2,
     *                               count =>18
     *
     *                             ],
     *
     *                )
     * </pre>
     * @return Linda
     */
    protected function fetch($fields, $queryConfig = array())
    {

        $this->isFectchOps = true;
        $this->resultObject = null;
        $this->queryBuilder("select", $fields, $queryConfig);

        $this->runQuery();
        return $this;
    }

    //+=========================================================================================================

    /**
     * Update fields in the DB
     *
     * @param array $fields      is an associative array containing values to set
     *                           <pre>
     *                           $fields = array(
     *                           id= "1",
     *                           email = "example.example.org
     *                           )
     *                           using NOW() or TIME() as values, inserts the date/time
     *                           </pre>
     * @param array  $queryConfig see #insert for structure of the queryConfig parameter
     *
     *
     * @return Linda
     */
    protected function update($fields, $queryConfig)
    {
        $this->isFectchOps = false;
        $this->resultObject = null;

        $this->queryBuilder("update", $fields, $queryConfig);
        $this->runQuery();

        return $this;
    }

    //+=========================================================================================================

    /**
     *  Inserts data into the table
     *
     * @param array $fields An associative array, containing column names that matches the actual table
     * @param array $values An associative array, values to be inserted per column, using NOW() or TIME(), inserts the date/time
     *                      in either YMD format or YMD H:m:s format
     *
     * @return Linda
     */
    protected function put($fields, $values)
    {
        $this->isFectchOps = false;

        $this->resultObject = null;
        $this->createInsert($fields, $values);

        $this->runQuery();
        return $this;
    }

    //+=========================================================================================================

    /**
     *  Deletes data from a table
     *
     * @param array $queryConfig See #insert for structure of the queryConfig parameter
     *
     * @return Linda
     */
    protected function delete($queryConfig)
    {
        $this->isFectchOps = false;
        $this->resultObject = null;
        $this->queryBuilder("delete", null, $queryConfig);

        $this->runQuery();

        return $this;
    }

    //+======================================================+

    /**
     *
     * @internal
     * @ignore
     */
    protected function sanitize($inp)
    {
//escape values for database query
        if (\is_array($inp)) {
            return \array_map(__METHOD__, $inp);
        }

        if ( ! empty($inp) && \is_string($inp)) {
            return \str_replace(
                array('\\', "\0", "\n", "\r", '"', "\x1a"), array('\\\\', '\\0', '\\n', '\\r', '\\"', '\\Z'), $inp
            );
        }

        return \trim(\strip_tags(\stripslashes(\htmlentities($inp, ENT_QUOTES, 'UTF-8'))));
    }

    //+======================================================+
    //*argument $inserts is an associative array of column names and value-data
    protected function createInsert($fields, $inserts)
    {

        //call our custom escape function on the data-values

        $values = \array_map(array($this, "sanitize"),
            \array_map(array($this, "stringOrInt"), \array_values($inserts)));
        // $keys   = \array_keys($inserts);

        $this->currentQuery = "INSERT INTO `" . $this->tableModel . "` (" . \implode(',', $fields) . ") VALUES ("
            . \implode(", ", $values) . ")";
    }

    //+==================================================================+
    //this method checks if a value is a string and quotes it if it is
    protected function stringOrInt($val, $key = '')
    {

        if ( ! \is_numeric($val) || "+" === \substr($val, 0, 1)) {
            //if it not numeric

            if ( ! empty($val)) { //if its not null
                return "'" . $val . "'";
            }
            //add quotes

            if (empty($val) && ! \is_string($key)) {
                //null colums
                return "" . null . "";
            }
        }

        return $val;
    }

    //+=========================================================================================================

    /**
     *
     * @internal
     * @ignore
     */
    protected function queryBuilder($mode, $fields, $queryConfig)
    {
        $this->currentQuery = "";

        switch ($mode) {

        case "select":


            $this->currentQuery .= " SELECT " . ($this->distinctResult ? "distinctResult " : "") . (\is_string($fields)
                    ? "* " : \implode(",", $fields)) . " FROM `" . $this->tableModel . "` ";

            //handle joins
            $join_count = 0; //
            foreach ($queryConfig as $index => $item) {
//loop through each inner join array argument
                if (false !== \strpos($index, "innerJoinGroup")) {

                    $this->currentQuery .= " AS T" . (++$join_count);

                    foreach ($item as $key => $val) {
                        $this->currentQuery .= " INNER JOIN `" . $val['table'] . "` AS T" . (++$join_count) . " ON T1."
                            . $val['conditional_column_a'] . " = T" . ($join_count) . "." . $val['conditional_column_b']
                            . " ";
                    }
                }
            }

            //handle where clause
            $where_clause_counter = 1;
            $in_where_clause = 1;

            foreach ($queryConfig as $index => $item) {
//loop through each where Groups
                if (false !== \strpos($index, "whereGroup")) {
                    //HANDLE WHERE CLUASE
                    if ($where_clause_counter++ === 1) {
                        $this->currentQuery .= " WHERE (";
                    }

                    foreach ($item as $key => $whereGroupIndex) {
//loop through each where Groups
                        $nextComparisonOp = isset($whereGroupIndex["nextOp"]) ? $whereGroupIndex["nextOp"] : "AND";
                        if ($in_where_clause > 1) {
                            $in_where_clause = 1;
                        }

                        foreach ($whereGroupIndex as $key2 => $val2) {
//loop through each whereGroups
                            if ($key2 !== "comparisonOp" && $key2 !== "nextOp" && $key2 !== "join_index") {
                                if ($in_where_clause++ > 1) {
                                    $this->currentQuery .= isset($whereGroupIndex['comparisonOp']) ? " "
                                        . $whereGroupIndex['comparisonOp'] . " " : " AND ";
                                }

                                if ($key2 !== "operator") { //this key shouldnt be added as a value
                                    $this->currentQuery .= " "

                                        //add the column name and comparison operator
                                        . "`" . $key2 . "`" . " " . (isset($val2['operator']) ? $val2['operator'] : "=")
                                        . " "

                                        //add the column value we are comparing with
                                        . $this->sanitize($this->stringOrInt($val2['value']));
                                }
                            }
                        }

                        $this->currentQuery .= " )";
                        if (\next($item)) {
                            $this->currentQuery .= " " . $nextComparisonOp . " (";
                        }
                    }
                }
            }

            //handle where_in_*
            if (isset($queryConfig['where_in'])) {

                $where_operator = isset($queryConfig['where_in']['operator']) ? " "
                    . $queryConfig['where_in']['operator'] . " " : " AND ";

                if ($where_clause_counter++ > 1) {

                    $this->currentQuery .= $where_operator . " `" . $queryConfig['where_in']['fieldName'] . "` IN (" .
                        (isset($queryConfig['where_in']['query']) ? $queryConfig['where_in']['query']
                            : $queryConfig['where_in']['options']) . ")";
                }
                else {

                    $this->currentQuery .= " WHERE `" . $queryConfig['where_in']['fieldName'] . "` IN (" .
                        (isset($queryConfig['where_in']['query']) ? $queryConfig['where_in']['query']
                            : $queryConfig['where_in']['options']) . ")";
                }
            }
            //handle where_not_in_*
            if (isset($queryConfig['where_not_in'])) {

                $where_operator = isset($queryConfig['where_not_in']['operator']) ? " "
                    . $queryConfig['where_not_in']['operator'] . " " : " AND ";

                if ($where_clause_counter++ > 1) {

                    $this->currentQuery .= $where_operator . " `" . $queryConfig['where_not_in']['fieldName']
                        . "` NOT IN (" .
                        (isset($queryConfig['where_not_in']['query']) ? $queryConfig['where_not_in']['query']
                            : $queryConfig['where_not_in']['options']) . ")";
                }
                else {

                    $this->currentQuery .= " WHERE `" . $queryConfig['where_not_in']['fieldName'] . "` NOT IN (" .
                        (isset($queryConfig['where_not_in']['query']) ? $queryConfig['where_not_in']['query']
                            : $queryConfig['where_not_in']['options']) . ")";
                }
            }

            if (isset($queryConfig['limit'])) {
                $this->currentQuery .= " LIMIT " . $queryConfig['limit']['index'];
                $this->currentQuery .= ",  " . $queryConfig['limit']['count'];
            }
            else {
                $this->currentQuery .= " LIMIT " . $this->defaultStartIndex;
                $this->currentQuery .= ", " . $this->defaultLimit;
            }

            $this->currentQuery .= ";";

            break;

        case "update":

            $update_column_count = 1;
            $this->currentQuery = "UPDATE `" . $this->tableModel . "` SET `";

            foreach ($fields as $key => $val) {

                if ($update_column_count++ > 1) {
                    $this->currentQuery .= ",`";
                }
                //seperate the next row feild/value

                if (\is_array($val)) {
                    $this->currentQuery .= $key . "` = (" . $val[0] . " )";
                }
                else {
                    $this->currentQuery .= $key . "` = " . $this->sanitize($this->stringOrInt($val, $key, true)) . " ";
                }
            }

            //handle where clause
            $where_clause_counter = 1;
            $in_where_clause = 1;
            foreach ($queryConfig as $index => $item) {
//loop through each where Groups
                if (false !== \strpos($index, "whereGroup")) {
                    //HANDLE WHERE CLUASE
                    if ($where_clause_counter++ === 1) {
                        $this->currentQuery .= " WHERE (";
                    }

                    foreach ($item as $key => $whereGroupIndex) {
//loop through each where Groups
                        $nextComparisonOp = isset($whereGroupIndex["nextOp"]) ? $whereGroupIndex["nextOp"] : "AND";
                        if ($in_where_clause > 1) {
                            $in_where_clause = 1;
                        }

                        foreach ($whereGroupIndex as $key2 => $val2) {
//loop through each whereGroups
                            if ($key2 !== "comparisonOp" && $key2 !== "nextOp") {

//add comparison (defaults to AND)
                                if ($in_where_clause++ > 1) {
                                    $this->currentQuery .= isset($whereGroupIndex['comparisonOp']) ? " "
                                        . $whereGroupIndex['comparisonOp'] . " " : " AND ";
                                }

                                if ($key2 !== "operator") { //this key shouldnt be added as a value
                                    $this->currentQuery .= " `" . $key2 . "` " . (isset($val2['operator'])
                                            ? $val2['operator'] : "=") . " " . $this->sanitize(
                                            $this->stringOrInt($val2['value'])
                                        );
                                }
                            }
                        }

                        $this->currentQuery .= " )";
                        if (\next($item)) {
                            $this->currentQuery .= " " . $nextComparisonOp . " (";
                        }
                    }
                }
            }

            //handle where_in_*
            if (isset($queryConfig['where_in'])) {
                $where_operator = isset($queryConfig['where_in']['operator']) ? " "
                    . $queryConfig['where_in']['operator'] . " " : " AND ";

                if ($where_clause_counter++ > 1) {

                    $this->currentQuery .= $where_operator . " `" . $queryConfig['where_in']['fieldName'] . "` IN (" .
                        ($queryConfig['where_in']['query'] ? $queryConfig['where_in']['query']
                            : $queryConfig['where_in']['options']) . ")";
                }
                else {

                    $this->currentQuery .= " WHERE `" . $queryConfig['where_in']['fieldName'] . "` IN (" .
                        ($queryConfig['where_in']['query'] ? $queryConfig['where_in']['query']
                            : $queryConfig['where_in']['options']) . ")";
                }
            }
            //handle where_not_in_*
            if (isset($queryConfig['where_not_in'])) {

                $where_operator = isset($queryConfig['where_not_in']['operator']) ? " "
                    . $queryConfig['where_not_in']['operator'] . " " : " AND ";

                if ($where_clause_counter++ > 1) {

                    $this->currentQuery .= $where_operator . " `" . $queryConfig['where_not_in']['fieldName']
                        . "` NOT IN (" .
                        (isset($queryConfig['where_not_in']['query']) ? $queryConfig['where_not_in']['query']
                            : $queryConfig['where_not_in']['options']) . ")";
                }
                else {

                    $this->currentQuery .= " WHERE `" . $queryConfig['where_not_in']['fieldName'] . "` NOT IN (" .
                        (isset($queryConfig['where_not_in']['query']) ? $queryConfig['where_not_in']['query']
                            : $queryConfig['where_not_in']['options']) . ")";
                }
            }

            $this->currentQuery .= " ;";

            break;
        case "delete":

            $this->currentQuery = "DELETE FROM `" . $this->tableModel . "` ";
            $this->currentQuery .= "";

            //handle where clause
            $where_clause_counter = 1;
            $in_where_clause = 1;
            foreach ($queryConfig as $index => $item) {
//loop through each where Groups
                if (false !== \strpos($index, "whereGroup")) {
                    //HANDLE WHERE CLUASE
                    if ($where_clause_counter++ === 1) {
                        $this->currentQuery .= " WHERE(";
                    }

                    foreach ($item as $key => $whereGroupIndex) {
//loop through each where Groups
                        $nextComparisonOp = isset($whereGroupIndex["nextOp"]) ? $whereGroupIndex["nextOp"] : "AND";
                        if ($in_where_clause > 1) {
                            $in_where_clause = 1;
                        }

                        foreach ($whereGroupIndex as $key2 => $val2) {
//loop through each whereGroups
                            if ($key2 !== "comparisonOp" && $key2 !== "nextOp") {
                                if ($in_where_clause++ > 1) {
                                    $this->currentQuery .= isset($whereGroupIndex['comparisonOp']) ? " "
                                        . $whereGroupIndex['comparisonOp'] . " " : " AND ";
                                }

                                if ($key2 !== "operator") {
                                    //this key shouldnt be added as a value
                                    $this->currentQuery .= " `" . $key2 . "` " . (isset($val2['operator'])
                                            ? $val2['operator'] : "=") . " " . $this->sanitize(
                                            $this->stringOrInt($val2['value'])
                                        );
                                }
                            }
                        }

                        $this->currentQuery .= " )";
                        if (\next($item)) {
                            $this->currentQuery .= " " . $nextComparisonOp . " (";
                        }
                    }
                }
            }

            //handle where_in_*
            if (isset($queryConfig['where_in'])) {

                $where_operator = isset($queryConfig['where_in']['operator']) ? $queryConfig['where_in']['operator']
                    : " AND ";

                if ($where_clause_counter++ > 1) {

                    $this->currentQuery .= $where_operator . " `" . $queryConfig['where_in']['fieldName'] . "` IN (" .
                        ($queryConfig['where_in']['query'] ? $queryConfig['where_in']['query']
                            : $queryConfig['where_in']['options']) . ")";
                }
                else {

                    $this->currentQuery .= " WHERE `" . $queryConfig['where_in']['fieldName'] . "` IN (" .
                        ($queryConfig['where_in']['query'] ? $queryConfig['where_in']['query']
                            : $queryConfig['where_in']['options']) . ")";
                }
            }

            //handle where_not_in_*
            if (isset($queryConfig['where_not_in'])) {

                $where_operator = isset($queryConfig['where_not_in']['operator']) ? " "
                    . $queryConfig['where_not_in']['operator'] . " " : " AND ";

                if ($where_clause_counter++ > 1) {

                    $this->currentQuery .= $where_operator . " `" . $queryConfig['where_not_in']['fieldName']
                        . "` NOT IN (" .
                        (isset($queryConfig['where_not_in']['query']) ? $queryConfig['where_not_in']['query']
                            : $queryConfig['where_not_in']['options']) . ")";
                }
                else {

                    $this->currentQuery .= " WHERE `" . $queryConfig['where_not_in']['fieldName'] . "` NOT IN (" .
                        (isset($queryConfig['where_not_in']['query']) ? $queryConfig['where_not_in']['query']
                            : $queryConfig['where_not_in']['options']) . ")";
                }
            }

            if (isset($queryConfig['LIMIT'])) {

                $this->currentQuery .= " LIMIT " . (int)$queryConfig['LIMIT'];
            }
            $this->currentQuery .= ";";

            break;
        }
        return $this;
    }

    //+===========================================================================================
    //run the query and set the return object
    /**
     * @ignore
     * @return $this
     */
    protected function runQuery()
    {
        $this->currentQuery = \str_replace("NOW()", \date("Y-m-d"), $this->currentQuery);
        $this->currentQuery = \str_replace("TIME()", \date("Y-m-d H:i:s"), $this->currentQuery);
        $this->lindaError = "";
        $this->lastAffectedRowCount = 0;

        $stmnt = null;

        try {
            if (($stmnt = $this->dbLink->prepare($this->currentQuery))) {

                if ( ! $stmnt->execute()) {
                    $this->lindaError = "ERROR_EXECUTING_QUERY";
                    $this->resultObject = null;
                }

                if ($stmnt->rowCount()) {
                    $this->lastAffectedRowCount = $stmnt->rowCount();

                }

                if ($this->isFectchOps) {
                    $this->resultObject = $stmnt->fetchAll(\PDO::FETCH_ASSOC);
                }

                if (false === $this->resultObject) {
                    $this->lindaError = "ERROR_EXECUTING_QUERY";
                    $this->resultObject = null;
                }

                //set the number of rows returned for select operation
                if ( ! $this->lastAffectedRowCount && \count($this->resultObject)) {
                    $this->lastAffectedRowCount = \count($this->resultObject);
                }

                if ($this->resultObject) {
                    if (\count($this->resultObject) || $this->lastAffectedRowCount) {

                    }
                    else {
                        $this->resultObject = null;
                    }
                };
            }
        } catch (PDOException $e) {

            $this->lindaError = $e->getMessage();
            $this->resultObject = null;

            echo $this->lindaError;
        }
        catch(\Error $e){
            $this->lindaError = $e->getMessage();
            $this->resultObject = null;

            echo $this->lindaError;
        }

        //after a query, reset these vars
        $this->defaultLimit = 1000;
        $this->defaultStartIndex = 0;
        $this->distinctResult = false;

        $this->queryConfig = array();
        return $this;
    }


    //+===================================================================================================

    /**
     * Fetches row(s) containing the maximum value of a particular column, this method executes a query directly on the table and doesnt
     *  work on the retrieved/stored data - so you must have set the table using the #setTable method, prior to calling
     *
     * DO NOT CALL DIRECTLY, CALLED BY LindaModel::maxRow
     *
     * @param string $fieldName field to get the maximum value from
     *
     * @return array()
     */
    protected function maxRow_($fieldName)
    {
        $this->isFectchOps = true;//set this unless a result obj wouldnt be geneated
        $this->currentQuery = "SELECT * FROM " . $this->tableModel . " WHERE " . $fieldName . " = (SELECT MAX("
            . $fieldName . ") FROM " . $this->tableModel . ")";
        $this->runQuery();

        $this->isFectchOps = false;
        return $this;
    }


    //+===================================================================================================

    /**
     *  Fetches row(s) containing the maximum value of a particular column,, this method executes a query directly on the table and doesnt
     *  work on the retrieved/stored data - so you must have set the table using the #setTable method, prior to calling
     *
     * @param string $columnName field to get the minimum value from
     *
     * DO NOT CALL DIRECTLY, CALLED BY LindaModel::minRow
     *
     * @return array()
     */
    protected function minRow_($columnName)
    {
        $this->isFectchOps = true;//set this unless a result obj wouldnt be geneated
        $this->currentQuery = "SELECT * FROM " . $this->tableModel . " WHERE " . $columnName . " = (SELECT MIN("
            . $columnName . ") FROM " . $this->tableModel . ")";
        $this->runQuery();

        $this->isFectchOps = false;
        return $this;
    }


    /**
     *  Returns the total number of rows in the result set, or the numbers of rows affected by the last update/delete operation
     *
     * @return integer
     */
    protected function numRows()
    {

        return $this->lastAffectedRowCount;
    }

    public function __destruct()
    {

    }

}
