<?php

/**
 * DB model example:
 * Base model class
 *
 * Base DB models class, inplements get/set/update static methods overloades
 *
 * @method array getBy__Field_Name__($value, int $limit=0, int $offset = 0) Gets row by field value, where {__Field_Name__} is the name of the field which exists in table and defined in table model, see Base_Model_Db_Base::__callStatic() for details
 * @method array getBy(array $rules) Gets row by array of fields and values, see Base_Model_Db_Base::__callStatic() for details
 * @method mixed get__Field_Name1__By__Field_Name2__($field_2_value) Gets row field {__Field_Name1__} value by {__Field_Name2__} value, where {__Field_Name1__} and {__Field_Name2__} are names of the fields which exist in table and defined in table model, see Base_Model_Db_Base::__callStatic() for details
 * @method int setBy__Field_Name__($value, field_value) Sets row field value by another field value, where {__Field_Name__} is the name of the field which exists in table and defined in table model, see Base_Model_Db_Base::__callStatic() for details
 * @method int update__Field_Name1__By__Field_Name2__($field_2_value, $field_1_value) Updates row {__Field_Name1__} with {field_1_value}, affects the row is it's {__Field_Name2__} value equals to {field_2_value}, where {__Field_Name1__} and {__Field_Name2__} are names of fields which exists in table and defined in table model, see Base_Model_Db_Base::__callStatic() for details
 * @method int updateBy(array $rules) Updates row by array of {field=>value}, see Base_Model_Db_Base::__callStatic() for details
 */
abstract class Base_Model_Db_Base
{
    const GET_PATTERN = "SELECT * FROM %s WHERE %s = ?";
    const GET_ALL_PATTERN = "SELECT * FROM %s";
    const GET_BY_ARRAY_PATTERN = "SELECT * FROM %s WHERE %s";
    const GET_BY_PATTERN = "SELECT %s FROM %s WHERE %s = ?";
    const SET_PATTERN = "UPDATE %s SET %s=? WHERE %s=?";
    const UPDATE_PATTERN = "UPDATE %s SET %s WHERE %s=?";
    const UPDATE_BY_ARRAY_PATTERN = "UPDATE %s SET %s WHERE %s";
    protected static $_delete_pattern = "DELETE FROM %s WHERE %s='%s'";
    protected static $_delete_by_array_pattern = "DELETE FROM %s WHERE %s";

    /**
     * The name of the table class manages
     * @var string
     */
    protected static $_table;

    /**
     * The table structure
     * @var array
     */
    protected static $_table_fields;

    // Table description example.
    // Define constants for enum field usage
    // const
    //     STATUS_NEW = 'new',
    //     STATUS_DOWNLOADING = 'downloading',
    //     STATUS_DOWNLOADED = 'downloaded',
    //     STATUS_CONVERTING = 'converting',
    //     STATUS_DONE = 'done';

    // Define array of avalaible enum values
    // protected static $statuses_enum = array(
    //     self::STATUS_NEW,
    //     self::STATUS_DOWNLOADING,
    //     self::STATUS_DOWNLOADED,
    //     self::STATUS_CONVERTING,
    //     self::STATUS_DONE,
    // );

    // protected static $_table_fields = array(
    //     'id'=>array('changeable' => false),
    //     'url' =>array(),
    //     'title' =>array(),
    //     'categories' =>array(),
    //     'status' =>array(
    //         'type' => 'enum',
    //         'enum_array' => 'statuses_enum'
    //         'inverted' => false  //this could be used when you want to use $statuses_enum array keys for setting values, see Base_Model_Db_ConverterTask example
    //     ),
    // );

    /**
     * Mysql functions constants
     */
    const
        MYSQL_FUNCTION_NOW = ' NOW()';

    /**
     * List of available mysql functions.
     * Format: usage alias -> mysql function
     */
    private static $_functions_list = array(
        self::MYSQL_FUNCTION_NOW
    );

    /**
     * LIMIT
     */
    const
        LIMIT_ONLY_PATTERN = ' LIMIT %d',
        LIMIT_WITH_OFFSET_PATTERN = ' LIMIT %d, %d';

    const
        RESULT_TYPE_ARRAY = 1,
        RESULT_TYPE_ASSOC = 2;

    /**
     * Connections names
     * Should overload fin child classes to use other connections names
     */
    protected static $_select_connection = 'local';
    protected static $_insert_connection = 'master';

    /**
     * Getters/setters overloading
     *
     * Overloads getters, setters, updaters for inherited classes
     *
     * @param string $name
     * @param array $args
     */
    public static function __callStatic($name, $args)
    {
        /**
         * {GetBy*} overloading
         *
         * Example:
         *     getById($id) - getting rows by one field
         *     getById($id, $limit = 0, $offset = 0) - getting rows by one field and set limit
         *     getById($id, $limit = 0, $offset = 0, $cached = false, $cache_time = [config]) - getting rows by one field and set limit
         *     getBy(array $fields, $limit = 0, $offset = 0) - getting rows by a bunch of conditions linked with AND,
         *                            if one of the fields values is an array then the arguments will be united with OR
         */
        if (preg_match("/getBy(.*)/i", $name, $matches)) {
            $conn = VDb::getConnection(static::$_select_connection);
            // Get by input array
            if ("getBy" == $matches[0] || "GetBy" == $matches[0]) {
                $data = $args[0];
                if (!is_array($data)) {
                    throw new InvalidArgumentException("Parameter 'data' should be an array");
                }
                $formatted_data = "";
                $input_array = array();
                foreach ($data as $name => $value) {
                    $name = self::_getCorrectField($name);

                    // if get an array of field arguments then use OR condition
                    $local_args = array();
                    if (is_array($value) && count($value)) {
                        foreach ($value as $val) {
                            $local_args[] = $name . "=?";
                            $input_array[] = $val;
                        }
                        $formatted_data[] = "(" . implode(" OR ", $local_args) . ")";
                    } else {
                        $formatted_data[] = $name . "=?";
                        $input_array[] = $value;
                    }
                }
                $formatted_data = implode(" AND ", $formatted_data);

                $sql = sprintf(self::GET_BY_ARRAY_PATTERN, static::$_table, $formatted_data);
            } else {
                // get by one field
                $by_name  = self::_getCorrectField($matches[1]);
                $sql = sprintf(self::GET_PATTERN, static::$_table, $by_name);
                $input_array = array($args[0]);
            }

            // limit and offset
            if (isset($args[1]) && !is_int($args[1])) {
                throw new InvalidArgumentException("Parameter 'offset' should be an integer value");
            }
            if (isset($args[2]) && !is_int($args[2])) {
                throw new InvalidArgumentException("Parameter 'limit' should be an integer value");
            }
            if (isset($args[1]) && $args[1] > 0 && isset($args[2]) && $args[2] > 0) {
                $sql .= sprintf(self::LIMIT_WITH_OFFSET_PATTERN, (int)$args[2], (int)$args[1]);
            } else if (isset($args[1]) && $args[1] > 0) {
                $sql .= sprintf(self::LIMIT_ONLY_PATTERN, (int)$args[1]);
            }
            //cache
            if (isset($args[3]) && !is_bool($args[3])) {
                throw new InvalidArgumentException("Parameter 'cache' should be a boolean value");
            }
            if (isset($args[4]) && !is_int($args[4])) {
                throw new InvalidArgumentException("Parameter 'cache_lifetime' should be an integer value");
            }
            if (isset($args[3]) && $args[3] === true) {
                $config = VRegistry::get('config');
                $secs2cache = isset($args[4]) ? $args[4] : $config['video_mcache_lifetime'] ;
                $res = $conn->CacheExecute($secs2cache, $sql, $input_array);
            } else {
                $res = $conn->Execute($sql, $input_array);
            }
            if ($res === false) {
                return array();
            }
            return $res->getrows();
        }

        /**
         * {get*By*} overloading
         * Example: getStatusById($id)
         */
        if (preg_match("/get(.*)By(.*)/i", $name, $matches)) {
            $conn = VDb::getConnection(static::$_select_connection);
            $get_name = self::_getCorrectField(strtolower($matches[1]));
            $by_name = self::_getCorrectField(strtolower($matches[2]));

            $sql = sprintf(self::GET_BY_PATTERN, $get_name, static::$_table, $by_name);
            $input_array = array($args[0]);

            //cache
            if (isset($args[1]) && !is_bool($args[1])) {
                throw new InvalidArgumentException("Parameter 'cache' should be a boolean value");
            }
            if (isset($args[2]) && !is_int($args[2])) {
                throw new InvalidArgumentException("Parameter 'cache_lifetime' should be an integer value");
            }
            if (isset($args[1]) && $args[1] === true) {
                $config = VRegistry::get('config');
                $secs2cache = isset($args[2]) ? $args[2] : $config['video_mcache_lifetime'] ;
                return $conn->CacheExecute($secs2cache, $sql, $input_array);
            } else {
                return $conn->execute($sql, $input_array);
            }
        }

        /**
         * {set*By*} overloading
         * Example: setStatusById($status, $id)
         *
         * @todo add setBy(array $fields, $id) handler
         */
        if (preg_match("/set(.*)By(.*)/i", $name, $matches)) {

            $conn = VDb::getConnection(static::$_insert_connection);

            $set_name = strtolower($matches[1]);
            $by_name = strtolower($matches[2]);

            self::_assertFieldChangeable($set_name);

            $input_arr = array($args[0], $args[1]);
            $sql = sprintf(self::SET_PATTERN, static::$_table, $set_name, $by_name);

            $conn->execute($sql, $input_arr);

            return $conn->Affected_Rows();

        }

        /**
         * {update*By*} overloading
         * Example:
         *     updateById($id, $data)
         *     updateBy(array $rules, array $data) - updating rows by a bunch of conditions linked with AND, if one of the fields values is an array then the arguments will be united with OR
         */
        if (preg_match("/updateBy(.*)/i", $name, $matches)) {
            $conn = VDb::getConnection(static::$_insert_connection);
             // Update by input array
            if ("updateBy" == $matches[0]) {

                $rules = $args[0];
                if (!is_array($rules)) {
                    throw new InvalidArgumentException("Parameter <rules> should be an array");
                }
                $data = $args[1];
                if (!is_array($data)) {
                    throw new InvalidArgumentException("Parameter <data> should be an array");
                }

                $input_array = array();
                // data
                $formatted_data = "";
                foreach ($data as $name => $value) {
                    $name = self::_getCorrectField($name);
                    self::_assertFieldChangeable($name);
                    $value = self::_checkFieldRules($name, $value);

                    // if get an array of field arguments then use OR condition
                    $local_args = array();
                    if (is_array($value) && count($value)) {
                        foreach ($value as $val) {
                            $local_args[] = $name . "=?";
                            $input_array[] = $val;
                        }
                        $formatted_data[] = "(" . implode(" OR ", $local_args) . ")";
                    } else {
                        //Mysql function check
                        if (in_array($value, self::$_functions_list)) {
                            $formatted_data[] = $name . "=" . $value;
                        } else {
                            $formatted_data[] = $name . "=?";
                            $input_array[] = $value;
                        }
                    }
                }
                $formatted_data = implode(", ", $formatted_data);

                // rules
                $formatted_rules = "";
                foreach ($rules as $name => $value) {
                    $name = self::_getCorrectField($name);

                    // if get an array of field arguments then use OR condition
                    $local_args = array();
                    if (is_array($value) && count($value)) {
                        foreach ($value as $val) {
                            $local_args[] = $name . "=?";
                            $input_array[] = $val;
                        }
                        $formatted_rules[] = "(" . implode(" OR ", $local_args) . ")";
                    } else {
                        $formatted_rules[] = $name . "=?";
                        $input_array[] = $value;
                    }
                }
                $formatted_rules = implode(" AND ", $formatted_rules);

                $sql = sprintf(self::UPDATE_BY_ARRAY_PATTERN, static::$_table, $formatted_data, $formatted_rules);
            }
            else {
                // Update by param
                $by_name  = self::_getCorrectField($matches[1]);

                $data = $args[1];

                $input_array = array();
                $formatted_data = "";
                foreach ($data as $name => $value) {
                    $name = self::_getCorrectField($name);
                    self::_assertFieldChangeable($name);
                    $value = self::_checkFieldRules($name, $value);

                    $formatted_data[] = $name . "=?";
                    $input_array[] = $value;
                }
                $formatted_data = implode(", ", $formatted_data);
                $input_array[] = $args[0];
                $sql = sprintf(self::UPDATE_PATTERN, static::$_table, $formatted_data, $by_name, $input_array);

            }

            $conn->execute($sql, $input_array);

            if (0 != $conn->ErrorNo()) {
                throw new Exception_Db_Model($conn->ErrorMsg(), $conn->ErrorNo());
            }

            return $conn->Affected_Rows();
        }

        /**
         * {deleteBy*} overloading
         */
        if (preg_match("/deleteBy(.*)/i", $name, $matches)) {
            $conn = VDb::getConnection(static::$_insert_connection);
            // Delete by input array
            if ("deleteBy" == $matches[0]) {
                $data = $args[0];
                if (!is_array($data)) {
                    throw new InvalidArgumentException("Parameter <data> should be an array");
                }
                $formatted_data = "";
                foreach ($data as $name => $value) {
                    $name = self::_getCorrectField($name);

                    // if get an array of field arguments then use OR condition
                    $local_args = array();
                    if (is_array($value) && count($value)) {
                        foreach ($value as $val) {
                            $local_args[] = $name . "='" . $val . "'";
                        }
                        $formatted_data[] = "(" . implode(" OR ", $local_args) . ")";
                    } else {
                        $formatted_data[] = $name . "='" . $value . "'";
                    }
                }
                $formatted_data = implode(" AND ", $formatted_data);

                $sql = sprintf(static::$_delete_by_array_pattern, static::$_table, $formatted_data);
            } else {
                // delete by one field
                $by_name  = self::_getCorrectField($matches[1]);
                $sql = sprintf(static::$_delete_pattern, static::$_table, $by_name, VDb::escape($args[0]));

                // limit and offset
                if (isset($args[1]) && $args[1] > 0 && isset($args[2]) && $args[2] > 0) {
                    $sql .= ' LIMIT ' . $args[2] . ', ' . $args[1];
                } else if (isset($args[1]) && $args[1] > 0) {
                    $sql .= ' LIMIT ' . $args[1];  // limit only
                }
            }

            $conn->execute($sql);

            return $conn->Affected_Rows();
        }

        return self::$name($args);
    }



    /**
     * Return existing field from table
     * @uses static::$_table_fields
     *
     * @assert ('VID')    === 'VID'
     * @assert ('Vid')    === 'VID'
     * @assert ('Status') === 'status'
     * @assert ('status') === 'status'
     * @assert ('fake_field') throws BadMethodCallException
     *
     * @param  string $name Field name
     * @return string correct Field name
     * @throws BadMethodCallException
     */
    protected static function _getCorrectField($name)
    {
        // check fields while case not changed yet
        if (array_key_exists($name, static::$_table_fields)) {
            return $name;
        }

        // check fields if case was changed
        $fields = array_keys(static::$_table_fields);
        if (false !== ($num = array_search(strtolower($name), array_map('strtolower', $fields)))) {
            return $fields[$num];
        } else {
            // field not found in static::$_table_fields
            throw new BadMethodCallException(sprintf("Field '%s' does not exist in table '%s'", $name, static::$_table));
        }
    }

    /**
     * Checks field specific rules
     *
     * @param  string $name             Field name
     * @param  mixed $value             Field value
     * @param   boolean $inverted   Explains which value was passed: key or value of array, by default looks up for key values
     *
     * @return  mixed               Modified or not value
     */
    protected static function _checkFieldRules($name, $value)
    {
        $classname = get_called_class();

         if (isset(static::$_table_fields[$name]['type'])) {
            switch (static::$_table_fields[$name]['type']) {
                case 'enum':
                    if (!isset(static::$_table_fields[$name]['enum_array'])) {
                        throw new BadMethodCallException(sprintf("You should provide 'enum_array' value for '%s' field in table '%s'", $name, static::$_table));
                    }
                    $enum_array_name = static::$_table_fields[$name]['enum_array'];
                    $enum_array_values = $classname::$$enum_array_name;
                    if (isset(static::$_table_fields[$name]['inverted']) && static::$_table_fields[$name]['inverted']) {
                        if (!isset($enum_array_values[$value])) {
                            throw new InvalidArgumentException(sprintf("Value '%s' for '%s' field in table '%s' should be one of that declared in 'enum_array' rules", $value, $name, static::$_table));
                        }
                        return $enum_array_values[$value];
                    }
                    if (!in_array($value, $enum_array_values)) {
                        throw new InvalidArgumentException(sprintf("Value '%s' for '%s' field in table '%s' should be one of that declared in 'enum_array' rules", $value, $name, static::$_table));
                    }
                    return $value;
                    break;

                default:
                    return $value;
                    break;
            }
         }

         return $value;
    }


    /**
     * Assures that the field is changeable
     *
     * @param  string $name Field name
     * @return void
     * @throws BadMethodCallException
     */
    protected static function _assertFieldChangeable($name)
    {
        if (isset(static::$_table_fields[$name]['changeable']) && !static::$_table_fields[$name]['changeable']) {
            throw new BadMethodCallException(sprintf("Field '%s' in table '%s' is not changeable", $name, static::$_table));
        }
    }

    /**
     * Insert new row in database from associated array
     *
     * @param array $row
     * @return int last insert id
     */
    public static function addRow($row, $replace = false, $ignore = false)
    {
        // prepare fields
        $fields = array_map(
            function ($field) {
                return "`{$field}`";
            },
            array_keys($row)
        );

        // prepare values
        $values = array();
        $input_arr = array();
        foreach (array_values($row) as $value) {
            $values[] = '?';
            if (is_null($value)) {
                $input_arr[] = null;
            } else {
                $input_arr[] = $value;
            }
        }

        if ($replace) {
            $sql = "REPLACE INTO " . static::$_table . " (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
        }
        elseif ($ignore) {
           $sql = "INSERT IGNORE INTO " . static::$_table . " (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
        }
        else {
            $sql = "INSERT INTO " . static::$_table . " (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
        }

        $conn = VDb::getConnection(static::$_insert_connection);
        $res = $conn->execute($sql, $input_arr);

        if (0 != $conn->ErrorNo()) {
            throw new Exception_Db_Model($conn->ErrorMsg(), $conn->ErrorNo());
        }

        if (!$res) {
            return false;
        }

        return $conn->insert_id();
    }

    /**
     * Return all records from table
     *
     * @param  boolean $offset            Select data start offset, false - no offset
     * @param  boolean $limit               Select data limit, false - no limit
     * @param  INT          $result_type  Type of results: array or associative array, one of [Model_Db_Base::RESULT_TYPE_ARRAY|Model_Db_Base::RESULT_TYPE_ASSOC]
     *
     * @return array|false                      Obtained results or false if no results recieved
     * @throws InvalidArgumentException
     */
    public static function getAll($offset = false, $limit = false, $result_type = self::RESULT_TYPE_ARRAY)
    {
        $sql = sprintf(self::GET_ALL_PATTERN, static::$_table);
        if (false !== $offset) {
            $sql .= " LIMIT " . (int)$offset;
        }
        if (false !== $limit) {
            $sql .= ", " . (int)$limit;
        }
        $conn = VDb::getConnection(static::$_select_connection);
        $res = $conn->execute($sql);
        if ($res) {
            switch ($result_type) {
                case self::RESULT_TYPE_ARRAY:
                    return $res->GetRows();
                case self::RESULT_TYPE_ASSOC:
                    return $res->GetAssoc();
                case 'default':
                    throw new InvalidArgumentException("Parameter <result_type> should be one of [default|assoc]");
            }

        }

        return false;
    }

}


class Exception_Db_Model extends Exception {

}
