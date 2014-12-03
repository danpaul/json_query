<?php

/**
 * Cachew is a simple file system cache. At present, it caches JSON 
 *		encodable data using a simple key/value paring.
 *
 * Basic usage:
 *
 * $cache = new Cachew();
 * $cache->set('foo', array('baz', 'bat', 'bar'));
 * print_r($cache->get('foo')); // ~> array([0] => 'baz'...
 *
 * @author Dan Breczinski
 * @version 0.0.1
 * last updated: 3/7/2014
 */

class Cachew
{
	protected $cache_dir;
	protected $cache_method;
	protected $permissions;

    /**
     * Class constructor
     *
     * @param array $options, optional
     * @param array memeber $params['cache_dir'], optional, directory to store
     *			cache. If none give, data will b stored in the cache folder in
     *			the class directory
     * @param array memeber $params['permsission'], optional, permission to give
     *			cache files and directory. If none give 0764 will be used
     * @return `Cachew` object
     */
	public function __construct($options = NULL)
	{
		$this->cache_dir = isset($options['cache_dir']) ? $options['cache_dir'] : __DIR__ . '/cache';
		$this->permissions = isset($options['permissions']) ? $options['permissions'] : 0764;
		$this->cache_method = 'json';
	}

    /**
     * @param string $key, the key associated with the cached item
     * @return returns the item associated with the key or `NULL` if the item
     *		isn't in the cache
     */
	public function get($key)
	{
		$file_name = $this->cache_dir. '/'. $key;
		if( !file_exists($file_name) ){ return NULL; }
		if( $this->cache_method === 'json')
		{
			return json_decode(file_get_contents($file_name), TRUE);
		}
	}

    /**
     * @param string $key, the key to associate with the cache
     * @param mixed &$data, any valid PHP item which can be JSON encoded
     */
	public function set($key, &$data)
	{
		$file = $this->cache_dir. '/'. $key;
		if( !is_dir($this->cache_dir) )
		{
			mkdir($this->cache_dir, $this->permissions, TRUE);
		}
		$f = fopen($file, 'w');
		if( !$f )
		{ 
			throw new Exception('Unable to open cache file. Permissions may need to be set.', 1);
		}
		flock($f, LOCK_EX);
		if( $this->cache_method === 'json' )
		{
			if( !fwrite($f, json_encode($data)) ){throw new Exception('Error writing to cache.', 1);
			};
		}
		flock($f, LOCK_UN);
		fclose($f);
		chmod($file, $this->permissions);
	}

    /**
     * @param string $key, the key to associate with the cache
     * @return boolean, returns `TRUE` if cache exists else returns `FALSE`
     */
	public function has_key($key)
	{
		return file_exists($this->cache_dir. '/'. $key);
	}

    /**
     * Clears the cache.
     */
	public function clear_cache()
	{
		$dir = new DirectoryIterator($this->cache_dir);
		foreach ($dir as $fileinfo)
		{
			if( !$fileinfo->isDot() )
			{
				if( !unlink($fileinfo->getPathname()) )
				{
					throw new Exception('Error clearing cache.', 1);					
				}
			}
		}
	}
}