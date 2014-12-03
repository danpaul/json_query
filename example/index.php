<?php

/*******************************************************************************
						About
********************************************************************************

Json_query is a class to simplify the querying of json data using an SQL like
set of commands. It's modeled on Sparrow (https://github.com/mikecao/sparrow)
but doesn't implement everything that Sparrow does.

Under typical usage, Json_query will get passed a directory path. All files
in this directory should be valid json files. Each file in the directry will
be treated as a table with the name of the file (witout extension) as its name.
Each file should contain an array of JSON objects. Each top-level array member
gets treated as row.

Although any file in the directory path can be queried, a file is not read and 
parsed until it is queried. So, there is no overhead involved with adding a lot
of different files/tables to the directory.

If the cache option is enabled, queries will be cached.

When Json_query is queried, this is the order of its operations in psuedo code:

	if cacheing is enabled:
		if query is cached:
			return cached query

	if file has not been read and parsed:
		read and parse it

	perform query

	if cacheing is enabled:
		cache results

	return results 

Json_query will perform automatic casting of fields with that follow a naming
convention. Any field with a name ending in an underscore + a special character
will get cast. These characters are 'f' for float, 'i' for integer and 'd' for
date time. So, if the field was named 'number_f', it would be cast to a float,
it if was named 'published_d', it woud get cast as a date.

********************************************************************************
						EXAMPLE USAGE
*******************************************************************************/

//Use `print_r($r);` after any query to see results

require_once('../json_query.php');

//create a new Json_query object
// $q = new Json_query('../json');

//Create a new Json_query object with
$q = new Json_query('./data', array('cache' => TRUE));

//select all records
$r = $q
	->from('videos')
	->execute();

//select all records where year is 2014
$r = $q
	->from('videos')
	->where('year', '2014')
	->execute();

//select all records where year is 2013 and month is Oct
$r = $q
	->from('videos')
	->where('year', '2013')
	->where('month', 'Oct')
	->execute();

//alternate syntax
$r = $q
	->from('videos')
	->where(array ('year' => '2013', 'month' => 'Oct'))
	->execute();

//select only certain fields
$r = $q
	->from('videos')
	->where(array ('year' => '2013', 'month' => 'Oct'))
	->select(array('year', 'month'))
	->execute();

//add condition to where. valid conditions are: < <= > >=
$r = $q
	->from('videos')
	->where('coolness_rating_f >', 11)
	->execute();

//where in
$r = $q
	->from('videos')
	->where('coolness_rating_f @', array(22, 3, 45))
	->execute();

//where not in
$r = $q
	->from('videos')
	->where('coolness_rating_f !@', array(22, 3, 45))
	->execute();

//sort from least to most cool
$r = $q
	->from('videos')
	->where(array ('year' => '2013', 'month' => 'Oct'))
	->sortAsc('coolness_rating_f')
	->execute();

//sort from most to least cool
$r = $q
	->from('videos')
	->where(array ('year' => '2013', 'month' => 'Oct'))
	->sortDesc('coolness_rating_f')
	->execute();

//sort by multiple parameters
$r = $q
	->from('videos')
	->sortAsc('year')
	->sortAsc('month')
	->execute();

//sort by multiple parameters (alternate syntax)
$r = $q
	->from('videos')
	->sortAsc(array('year', 'month'))
	->execute();

//group results (grouped key becomes key for results)
//e.g. $r['2013'] will be all 2013 records
$r = $q
	->from('videos')
	->sortAsc(array('year', 'month'))
	->groupBy('year')
	->execute();

//limit results
$r = $q
	->from('videos')
	->sortAsc(array('year', 'month'))
	->limit(3)
	->execute();

//limit and offset results
$r = $q
	->from('videos')
	->sortAsc(array('year', 'month'))
	->limit(3)
	->offset(1)
	->execute();

//create a new in memory table from results
$q_2 = new Json_query();
$q_2->add_table_from_array($r, 'new_table');

$r = $q_2
	->from('new_table')
	->where('title', 'Episode 1')
	->execute();

//clear cache to keep repo clean
$q->clear_cache();

print_r($r);