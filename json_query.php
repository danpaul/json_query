<?php

/**
 * Json_query
 *
 * @author Dan Breczinski
 * @version 0.0.1
 * last updated: 3/6/2014
 */

class Json_query
{
	public $data;
	public $table_rows;
	public $data_directory;

	protected $options;
	protected $query;

    /**
     * Class constructor
     *
     * @param string $data_dir, optional directory for the JSON files
     * @param array $params (optional). 
     * @param array member, boolean $params['query'], boolean. If `TRUE` will cache queries
     * @return `Json_query` object
     */

	public function __construct($data_dir = FALSE, $options = NULL)
	{
		$this->options = $options;
		$this->data = array();

		if( isset($options['cache']) && $options['cache'] === TRUE )
		{
			require_once(__DIR__. '/lib/cachew/cachew.php');
			$this->cachew = new Cachew();
		}else{
			$this->cachew = NULL;
		}

		if( $data_dir )
		{
			if( !is_dir($data_dir) )
			{
				throw new Exception($data. ' is not a directory.', 1);	
			}
			$this->data_directory = $data_dir;
		 }
	}

    /**
     * Used to construct internal tables from memory instead of file.
     *
     * @param array $data_array. A 2 dimensional array. Each top level element
     *			of array will be converted an a row in the table. The keys for
     *			the first top level element will become the column names 
     * @param string, $table_name, the name of the table created by the array.
     */
	public function add_table_from_array($data_array, $table_name)
	{
		$this->data[$table_name] = $data_array;
	}

    /**
     * Used to define which table will be queried
     *
     * @param string, from. The table that will be queried.
     * @return Json_query, returns `$this`
     */
	public function from($table)
	{
		$this->query['table'] = $table;
		return $this;
	}

    /**
     * Key or keys to be used to sort the table in ascending order.
     *
     * @param mixed, string or array. If a string is passed, the table will be
     *			sorted in ascending order on this column. If an array,
     *			it will be sorted by each of these columns.
     * @return Json_query, returns `$this`
     */
	public function sortAsc($key)
	{
		$this->sort($key, 'asc');
		return $this;
	}

    /**
     * Key or keys to be used to sort the table in ascending order.
     *
     * @param mixed, string or array. If a string is passed, the table will be
     *			sorted in descending order on this column. If an array,
     *			it will be sorted by each of these columns.
     * @return Json_query, returns `$this`
     */
	public function sortDesc($key)
	{
		$this->sort($key, 'desc');
		return $this;
	}

    /**
     * Primarily helper for sortAsc and sortDesc methods.
     *
     * @param mixed, string or array. If a string is passed, the table will be
     *			sorted on this column. If an array,it will be sorted by 
     *			each of these columns.
     * @param string. Either 'asc' or 'desc'. Specifed to sort the table in 
     *			ascending or descending order.
     */
	public function sort($key, $direction)
	{
		if( is_array($key) )
		{
			foreach( $key as $k )
			{
				$this->sort($k, $direction);
			}
		}else{
			$this->add_sort_param($key, $direction);
		}
	}

    /**
     * Limit the number or rows returned from the query.
     *
     * @param int, $limit. The maximum number of rows to return.
     * @return returns `$this`
     */
	public function limit($limit)
	{
		$this->query['limit'] = $limit;
		return $this;
	}

    /**
     * Offset the rows returned.
     *
     * @param int, $offset. Offsets the rows returned.
     */
	public function offset($offset)
	{
		$this->query['offset'] = $offset;
		return $this;
	}

    /**
     * Defines elements to match in the query.
     *
     * @param mixed (string or array), $key. If string, is passed the column
     * 		specified by `$key` must match `$value` for it to be included in the 
     *		query results. If an array is passed each key/value pair must match.
     *		Certain special params can be passed as well including comparsion 
     *		operators (>, <, >=, <=) and include/exclude operators (@, !@).
     * @param string, conditionally required. If an array isn't passed, this 
     * 		field is required. Only columns where the column matches `$value`
     *		will be included in the query results.
     * @return returns `$this`
     *
     */
	public function where($key, $value = NULL)
	{
		if( is_array($key) )
		{
			foreach( $key as $k => $v )
			{
				$this->where($k, $v);
			}
		}else{
			if( !isset($this->query['where']) )
			{ 
				$this->query['where'] = array();
			}
			array_push($this->query['where'], array($key, $value));
		}
		return $this;
	}

    /**
     * Defines element to group results by.
     *
     * @param string, $key. The results will be grouped by this column when 
     * 		returned.
     * @return returns `$this`
     */
	public function groupBy($key)
	{
		$this->query['group'] = $key;
		return $this;
	}

    /**
     * Defines which columns should be returned.
     *
     * @param array, $select_array. An array of strings specifying which columns
     *		to return.
     * @return retunrs `$this`
     */
	public function select($select_array)
	{
		$this->query['select'] = $select_array;
		return $this;
	}

    /**
     * Executes the query
	 *
     * @return returns an array of results defined by the query params
     */
	public function execute()
	{
		if( empty($this->query) )
		{ 
			trigger_error('No query parameters sent to Json_query');
			return NULL;
		}

		$results = array();

		if( $this->cachew )
		{
			$key = $this->get_query_key($this->query);
			if( $this->cachew->has_key($key) )
			{
				return($this->cachew->get($key));
			}
		}

		if( !isset($this->data[$this->query['table']]) )
		{
			$this->read_in_table($this->query['table']);
		}

		if(isset($this->query['sort']))
		{
			$this->sort_data();
		}

		$is_limited = isset($this->query['limit']) ? TRUE : FALSE;
		$has_offset = isset($this->query['offset']) ? TRUE : FALSE;

		if( $is_limited && $has_offset )
		{
			$this->query['limit'] = $this->query['limit'] + $this->query['offset'];
		}

		$count = 0;
		foreach( $this->data[$this->query['table']] as $row )
		{
			if( isset($this->query['where']) )
			{
				$match = TRUE;
				foreach( $this->query['where'] as $condition_params )
				{
					$params = $this->build_match_params($condition_params);
					if( !$this->row_match($row, $params) )
					{
						$match = FALSE;
					}
				}
				if( $match )
				{
					if( $has_offset )
					{
						if( $count >= $this->query['offset'] )
						{
							array_push($results, $row);
						}
					}else{
						array_push($results, $row);
					}
					$count += 1;
					if( $is_limited && $count === $this->query['limit'] )
					{
						return($this->parse_results($results));
					}
				}
			}else{
				if( $has_offset )
				{
					if( $count >= $this->query['offset'] )
					{
						array_push($results, $row);
					}
				}else{
					array_push($results, $row);
				}
				$count += 1;
				if( $is_limited && $count === $this->query['limit'] )
				{
					return($this->parse_results($results));
				}
			}
		}
		return($this->parse_results($results));
	}


    /**
     * Useful for debugging
	 *
     * @param string, $table_name, the name of the table to dump
     * @param optional, array, $columns. The columns to be include in the dump.
     */
	public function print_rows($table_name, $columns = FALSE)
	{
		foreach( $this->data[$table_name] as $row )
		{
			if( $columns )
			{

				foreach( $columns as $column )
				{
					var_dump($row[$column]);
					echo '   :   ';
				}
			}else{
				var_dump($row);
			}
			
			echo '<br>';
			echo '<br>';
		}
	}


	protected function cast_table_data($table_name)
	{
		$keys = $this->table_rows[$table_name];
		foreach( $keys as $column_name => $index )
		{
			$type = $this->get_type($column_name);
			if( $type !== 'string' )
			{
				foreach( $this->data[$table_name] as &$row )
				{
					if( $type === 'float' )
					{
						$row[$column_name] = (float)$row[$column_name];
					}else if( $type === 'int'){
						$row[$column_name] = (int)$row[$column_name];
					}else if( $type === 'date' ){
						$row[$column_name] = strtotime($row[$column_name]);
						if( $row[$column_name] === FALSE )
						{
							trigger_error('Invalid date in JSON.');
							$row[$column_name] = 0;
						}
					}
				}
			}
		}
	}

	protected function read_in_table($table_name)
	{
		$file = $this->data_directory. '/'. $table_name. '.json';
		if( !file_exists($file) )
		{
			throw new Exception('Can not find file: '. $file, 1);
		}
		$this->data[$table_name] = json_decode(file_get_contents($file), TRUE);
		$this->add_table_row_keys($table_name);
		if( $this->data[$table_name] === NULL )
		{
			trigger_error('Error parsing JSON in: '. $file, 1);
		}
		$this->cast_table_data($table_name);
	}

	protected function cast_data()
	{
		foreach( $this->data as $table_name => &$rows )
		{
			$keys = $this->table_rows[$table_name];
			foreach( $keys as $column_name => $index )
			{
				$type = $this->get_type($column_name);
				if( $type !== 'string' )
				{
					foreach( $rows as &$row )
					{
						if( $type === 'float' )
						{
							$row[$column_name] = (float)$row[$column_name];
						}else if( $type === 'int'){
							$row[$column_name] = (int)$row[$column_name];
						}else if( $type === 'date' ){
							$row[$column_name] = strtotime($row[$column_name]);
							if( $row[$column_name] === FALSE )
							{
								trigger_error('Invalid date in JSON.');
								$row[$column_name] = 0;
							}
						}
					}
				}
			}
		}
	}

	protected function get_type($name)
	{
		$type = strrchr($name, '_');

		if( $type )
		{
			switch( substr($type, 1, strlen($type)) )
			{
				case 'f':
					return 'float';
				case 'i':
					return 'int';
				case 'd':
					return 'date';
			}			
		}
		return 'string';
	}

	protected function add_table_row_keys($table_name)
	{
		$this->table_rows[$table_name] = array();
		$count = 0;
		foreach ($this->data[$table_name][0] as $name => $value)
		{
			$this->table_rows[$table_name][$name] = $count;
			$count += 1;
		}
	}

	protected function add_sort_param($key, $direction)
	{
		if( !isset($this->query['sort']) ){ $this->query['sort'] = array(); }
		array_push($this->query['sort'], array('direction' => $direction, 'key' => $key));
	}


	protected function sort_data()
	{
		$sort = array();

		foreach( $this->query['sort'] as $query_param )
		{
			$tmp_array = array();
			$key = $query_param['key'];
			foreach( $this->data[$this->query['table']] as $k => $v )
			{
				array_push($tmp_array, $v[$key]);
			}
			$sort[] = &$tmp_array;
			unset($tmp_array);
			if( $query_param['direction'] === 'asc' )
			{
				array_push($sort, SORT_ASC);
			}else{
				array_push($sort, SORT_DESC);
			}
		}

		$sort[] = &$this->data[$this->query['table']];
		call_user_func_array('array_multisort', $sort);
	}

	protected function build_match_params(&$condition_params)
	{
		$params = array();
		$split = explode(' ', $condition_params[0]);

		if( count($split) === 2 )
		{
			$params['operator'] = $split[1];
		}else{
			$params['operator'] = FALSE;
		}
		$params['column'] = $split[0];
		$params['condition'] = $condition_params[1];
		return $params;
	}

	protected function row_match(&$row, &$params)
	{
		if( $params['operator'] )
		{
			switch ($params['operator'])
			{
				case '<':
					return( $row[$params['column']] < $params['condition'] );
				case '<=':
					return( $row[$params['column']] <= $params['condition'] );
				case '>':
					return( $row[$params['column']] > $params['condition'] );
				case '>=':
					return( $row[$params['column']] >= $params['condition'] );
				case '@':
					return ( in_array($row[$params['column']], $params['condition']) );
				case '!@':
					return ( !in_array($row[$params['column']], $params['condition']) );

				default:
					trigger_error('Unknown operator: '. $params['operator']);
			}
		}else{
			return( $row[$params['column']] == $params['condition'] );
		}
		return FALSE;
	}

	protected function get_query_key(&$query_array)
	{
		$key = '';
		foreach( $query_array as $k => $v )
		{
			if( is_array($v) )
			{
				$key .= $this->get_query_key($v);
			}else{
				$key .= $k. $v;
			}
		}
		return $key;
	}

	protected function parse_results(&$results)
	{
		if( isset($this->query['select']) ){ $this->perform_selection($results); }
		if( isset($this->query['group']) ){ $results = $this->perform_grouping($results); }
		if( $this->cachew )
		{
			$key = $this->get_query_key($this->query);
			$this->cachew->set($key, $results);
		}
		unset($this->query);
		return $results;
	}

	protected function perform_grouping(&$results)
	{
		$grouped_results = array();
		$key = $this->query['group'];
		foreach( $results as $row )
		{
			if( !isset($grouped_results[$row[$key]]) )
			{
				$grouped_results[$row[$key]] = array();
			}
			array_push($grouped_results[$row[$key]], $row);
		}
		return $grouped_results;
	}

	protected function perform_selection(&$results)
	{
		foreach( $results as &$row )
		{
			foreach( $row as $key => &$value )
			{
				if( !in_array($key, $this->query['select']) )
				{
					unset($row[$key]);
				}
			}
		}
	}

	public function clear_cache()
	{
		if( $this->cachew )
		{
			$this->cachew->clear_cache();
		}
	}
}