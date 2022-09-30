# KaraDAV - A lightweight WebDAV server, with NextCloud compatibility

This is WebDAV server, allowing to easily set up a WebDAV file sharing server compatible with NextCloud clients with no depencies and high performance.

The only dependency is SQLite3 for the database.

Although this is a demo, this can be used as a simple but powerful file sharing server.

This server features:

* WebDAV class 1, 2, 3 support, support for Etags
* No database is required
* Multiple user accounts
* Share files for users using WebDAV: delete, create, update, mkdir, get, list
* Compatible with WebDAV clients
* Support for HTTP ranges (partial download)
* Support for [RFC 3230](https://greenbytes.de/tech/webdav/rfc3230.xhtml) to get the MD5 digest hash of a file (to check integrity) on `HEAD` requests (only MD5 is supported so far)
* Support for `Content-MD5` with `PUT` requests, see [dCache documentation for details](https://dcache.org/old/manuals/UserGuide-6.0/webdav.shtml#checksums)
* Support for some of the [Microsoft proprietary properties](https://greenbytes.de/tech/webdav/webdavfaq.html)

* User-friendly directory listings for file browsing with a web browser:
	* Upload directly from browser
	* Rename
	* Delete
	* Create and edit text file
	* MarkDown live preview
	* Preview of images, text, MarkDown and PDF
* User-management through web UI

## NextCloud compatibility

* Android app
* Desktop app (tested on Debian)
* [NextCloud CLI client](https://docs.nextcloud.com/desktop/3.5/advancedusage.html)
* Support for [Direct download API](https://docs.nextcloud.com/server/latest/developer_manual/client_apis/OCS/ocs-api-overview.html#direct-download)

## WebDAV clients compatibility

* [FUSE webdavfs](https://github.com/miquels/webdavfs) is recommended for Linux
* davfs2 is NOT recommended: it is very slow, and it is using a local cache, meaning changing a file locally may not be synced to the server for a few minutes, leading to things getting out of sync. If you have to use it, at least disable locks, by setting `use_locks=0` in the config.

## Future development

This might get supported in future (maybe):

* [Partial upload via PATCH](https://github.com/miquels/webdav-handler-rs/blob/master/doc/SABREDAV-partialupdate.md)
* [Chunk upload](https://docs.nextcloud.com/server/latest/developer_manual/client_apis/WebDAV/chunking.html)
* [NextCloud Trashbin](https://docs.nextcloud.com/server/latest/developer_manual/client_apis/WebDAV/trashbin.html)
* [NextCloud sharing](https://docs.nextcloud.com/server/latest/developer_manual/client_apis/OCS/ocs-share-api.html) (maybe?)
* [WebDAV sharing](https://evertpot.com/webdav-caldav-carddav-sharing/)
* [Extended MKCOL](https://www.rfc-editor.org/rfc/rfc5689) if CalDAV support is implemented
* CalDAV/CardDAV support: maybe, [why not](https://evertpot.com/227/), we'll see, in the mean time see [Sabre/DAV](https://sabre.io/dav/) for that.

## Dependencies

This depends on the KD2\WebDAV and KD2\WebDAV_NextCloud classes from the [KD2FW package](https://fossil.kd2.org/kd2fw/), which are packaged in this repository.

They are lightweight and easy to use in your own software to add support for WebDAV and NextCloud clients to your software.

## Author

BohwaZ. Contact me on: IRC = bohwaz@irc.libera.chat / Mastodon = https://mamot.fr/@bohwaz / Twitter = @bohwaz

## License

This software and its dependencies are available in open source with the AGPL v3 license. This requires you to share all your source code if you include this in your software. This is voluntary.

For entities wishing to use this software or libraries in a project where you don't want to have to publish all your source code, we can also sell this software with a commercial license, contact me at bohwaz /at/ kd2 /dot/ org. We can do that as we have wrote and own 100% of the source code, dependencies included, there is no third-party code here.
