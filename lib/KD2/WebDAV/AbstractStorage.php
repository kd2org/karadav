<?php

namespace KD2\WebDAV;

abstract class AbstractStorage
{
	/**
	 * These file names will be ignored when doing a PUT
	 * as they are garbage, coming from some OS
	 * @see https://raw.githubusercontent.com/owncloud/client/master/sync-exclude.lst
	 */
	const PUT_IGNORE_PATTERN = '!^~|~$|^~.*tmp$|^Thumbs\.db$|^desktop\.ini$|\.unison$|^My Saved Places'
		. '|^\.(lock\.|_|DS_Store|DocumentRevisions|directory|Trash|Temp|fseventsd|apdisk|synkron|sync|symform|fuse|nfs)!i';

	/**
	 * Return the requested resource
	 *
	 * @param  string $uri Path to resource
	 * @return null|array An array containing one of those keys:
	 * path => Full filesystem path to a local file, it will be streamed directly to the client
	 * resource => a PHP resource (eg. returned by fopen) that will be streamed directly to the client
	 * content => a string that will be returned
	 * or NULL if the resource cannot be returned (404)
	 *
	 * It is recommended to use X-SendFile inside this method to make things faster.
	 * @see https://tn123.org/mod_xsendfile/
	 */
	abstract public function get(string $uri): ?array;

	/**
	 * Return TRUE if the requested resource exists, or FALSE
	 *
	 * @param  string $uri
	 * @return bool
	 */
	abstract public function exists(string $uri): bool;

	/**
	 * Return the requested resource properties
	 *
	 * This method is used for HEAD requests, for PROPFIND, and other places
	 *
	 * @param string $uri Path to resource
	 * @param null|array $requested_properties Properties requested by the client, NULL if all available properties are requested,
	 * or if specific properties are requested, each item will be a key,
	 * like 'namespace_url:property_name', eg. 'DAV::getcontentlength' or 'http://owncloud.org/ns:size'
	 * See Server::BASIC_PROPERTIES for default properties.
	 * @param int $depth Depth, can be 0 or 1
	 * @return null|array An array containing the requested properties, each item must have a key
	 * of the same form as the requested properties.
	 *
	 * This method MUST return NULL if the resource does not exist.
	 * Or it MUST return an array, where the keys are 'namespace_url:property_name' tuples,
	 * and the value is the content of the property tag.
	 */
	abstract public function propfind(string $uri, ?array $requested_properties, int $depth): ?array;

	/**
	 * Store resource properties
	 * @param string $uri
	 * @param string $properties List of PROPPATCH request:
	 * key = property name (namespace:tag_name)
	 * value = array ['action' => 'set'|'remove', 'attributes' => array|null, 'content' => string|null]
	 *
	 * Should return an array:
	 * key = property name
	 * value = HTTP code (200 = OK, 403 = forbidden, 409 = conflict)
	 */
	public function proppatch(string $uri, array $properties): array
	{
		// By default, properties are not saved
		$out = [];

		foreach ($properties as $key => $value) {
			$out[$key] = 200;
		}

		return $out;
	}

	/**
	 * Create or replace a resource
	 * @param  string $uri     Path to resource
	 * @param  resource $pointer A PHP file resource containing the sent data (note that this might not always be seekable)
	 * @param  null|string $hash_algo The name of the hash algorithm, either `MD5` or `SHA1`.
	 * @param  null|string $hash A hash of the resource to store.
	 * If it is supplied and doesn't match the uploaded file, this method should fail with a
	 * 400 code WebDAV exception and not proceed to store the resource.
	 * @return bool Return TRUE if the resource has been created, or FALSE it has just been updated.
	 */
	abstract public function put(string $uri, $pointer, ?string $hash_algo, ?string $hash): bool;

	/**
	 * Delete a resource
	 * @param  string $uri
	 * @return void
	 */
	abstract public function delete(string $uri): void;

	/**
	 * Copy a resource from $uri to $destination
	 * @param  string $uri
	 * @param  string $destination
	 * @return bool TRUE if the destination has been overwritten
	 */
	abstract public function copy(string $uri, string $destination): bool;

	/**
	 * Move (rename) a resource from $uri to $destination
	 * @param  string $uri
	 * @param  string $destination
	 * @return bool TRUE if the destination has been overwritten
	 */
	abstract public function move(string $uri, string $destination): bool;

	/**
	 * Create collection of resources (eg. a directory)
	 * @param  string $uri
	 * @return void
	 */
	abstract public function mkcol(string $uri): void;

	/**
	 * Return a list of resources for target $uri
	 *
	 * @param  string $uri
	 * @param  array $properties List of properties requested by client (see ::properties)
	 * @return iterable An array or other iterable (eg. a generator)
	 * where each item has a key string containing the name of the resource (eg. file name),
	 * and the value being an array of properties, or NULL.
	 *
	 * If the array value IS NULL, then a subsequent call to properties() will be issued for each element.
	 */
	abstract public function list(string $uri, array $properties): iterable;

	/**
	 * Change the file modification time for target $uri
	 *
	 * @param  string             $uri
	 * @param  \DateTimeInterface $datetime
	 * @return bool TRUE if the file modification time has been set correctly
	 */
	abstract public function touch(string $uri, \DateTimeInterface $datetime): bool;

	/**
	 * Lock the requested resource
	 * @param  string $uri   Requested resource
	 * @param  string $token Unique token given to the client for this resource
	 * @param  string $scope Locking scope, either ::SHARED_LOCK or ::EXCLUSIVE_LOCK constant
	 * @return void
	 */
	public function lock(string $uri, string $token, string $scope): void
	{
		// By default locking is not implemented
	}

	/**
	 * Unlock the requested resource
	 * @param  string $uri   Requested resource
	 * @param  string $token Unique token sent by the client
	 * @return void
	 */
	public function unlock(string $uri, string $token): void
	{
		// By default locking is not implemented
	}

	/**
	 * If $token is supplied, this method MUST return ::SHARED_LOCK or ::EXCLUSIVE_LOCK
	 * if the resource is locked with this token. If the resource is unlocked, or if it is
	 * locked with another token, it MUST return NULL.
	 *
	 * If $token is left NULL, then this method must return ::EXCLUSIVE_LOCK if there is any
	 * exclusive lock on the resource. If there are no exclusive locks, but one or more
	 * shared locks, it MUST return ::SHARED_LOCK. If the resource has no lock, it MUST
	 * return NULL.
	 *
	 * @param  string      $uri
	 * @param  string|null $token
	 * @return string|null
	 */
	public function getLock(string $uri, ?string $token = null): ?string
	{
		// By default locking is not implemented, so NULL is always returned
		return null;
	}
}
