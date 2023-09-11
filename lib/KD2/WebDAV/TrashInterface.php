<?php

namespace KD2\WebDAV;

interface TrashInterface
{
	/**
	 * Restore an URI (relative to the root of the trashbin) to its original location
	 */
	public function restoreFromTrash(string $uri): void;

	/**
	 * Move an URI to the trashbin
	 */
	public function moveToTrash(string $uri): void;

	/**
	 * Delete everything from trashbin
	 */
	public function emptyTrash(): void;

	/**
	 * Delete an URI from the trashbin
	 * @param  string $uri Path, relative to the trashbin directory
	 */
	public function deleteFromTrash(string $uri): void;

	/**
	 * Delete old files from trashbin
	 * @param  int $delete_before_timestamp UNIX timestamp representing the date before which all trashed files must be deleted
	 * @return int number of deleted files
	 */
	public function pruneTrash(int $delete_before_timestamp): int;

	/**
	 * List all files in trash
	 *
	 * @return iterable Each item having the file name (URI) as the key
	 * and an array of these PROPFIND properties:
	 * - {http://nextcloud.org/ns}trashbin-deletion-time
	 * - {http://nextcloud.org/ns}trashbin-original-location
	 * - {http://nextcloud.org/ns}trashbin-filename
	 * - {dav:}getcontenttype
	 * - {dav:}getcontentlength
	 * - {dav:}resourcetype
	 * - {http://owncloud.org/ns}size
	 * - {http://owncloud.org/ns}id
	 */
	public function listTrashFiles(): iterable;
}
