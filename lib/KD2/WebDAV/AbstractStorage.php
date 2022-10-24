<?php

namespace KD2\WebDAV;

abstract class AbstractStorage
{
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
	abstract public function properties(string $uri, ?array $requested_properties, int $depth): ?array;

	/**
	 * Store resource properties
	 * @param string $uri
	 * @param string $body XML PROPPATCH request, parsing it is up to you
	 */
	public function setProperties(string $uri, string $body): void
	{
		// By default, properties are not saved
	}

	/**
	 * Create or replace a resource
	 * @param  string $uri     Path to resource
	 * @param  resource $pointer A PHP file resource containing the sent data (note that this might not always be seekable)
	 * @param  null|string $hash A MD5 hash of the resource to store, if it is supplied,
	 * this method should fail with a 400 code WebDAV exception and not proceed to store the resource.
	 * @param  null|int $mtime The modification timestamp to set on the file
	 * @return bool Return TRUE if the resource has been created, or FALSE it has just been updated.
	 */
	abstract public function put(string $uri, $pointer, ?string $hash, ?int $mtime): bool;

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
	 * and the value being an array of properties, or NULL
	 */
	abstract public function list(string $uri, array $properties): iterable;

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
