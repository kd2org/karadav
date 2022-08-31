# KaraDAV - A lightweight WebDAV server, with NextCloud compatibility

This is WebDAV server serving a demonstration of the KD2\WebDAV and KD2\WebDAV_NextCloud components, allowing to easily set up a WebDAV file sharing server compatible with NextCloud clients with no depencies and high performance.

The only dependency is SQLite3.

Although this is a demo, this can be used as a simple but powerful file sharing server.

This server features:

* No database is required
* Multiple user accounts
* Share files for users using WebDAV: delete, create, update, mkdir, get, list
* Compatible with WebDAV clients
* Supports NextCloud Android app
* Supports NextCloud desktop app
* User-friendly directory listings for file browsing with a web browser:
	* Upload directly from browser
	* Rename
	* Delete
	* Create and edit text file
	* MarkDown live preview
	* Preview of images, text, MarkDown and PDF
* User-management through web UI

## Future development

It is not planned to implement CalDAV and CardDAV currently, but it might come in the future, in the mean time see [Sabre/DAV](https://sabre.io/dav/) for that.

## Dependencies

This depends on the KD2\WebDAV and KD2\WebDAV_NextCloud classes from the [KD2FW package](https://fossil.kd2.org/kd2fw/), which are packaged in this repository.

These are lightweight and easy to use in your own software to add this feature to your product.

## Author

BohwaZ/KD2

## License

This software and its dependencies are available in open source with the AGPL v3 license. This requires you to share all your source code if you include this in your software. This is voluntary.

For entities wishing to use this software or libraries in a project where you don't want to have to publish all your source code, we can also sell this software with a commercial license, contact me at bohwaz /at/ kd2 /dot/ org. We can do that as we have wrote and own 100% of the source code, dependencies included, there is no third-party code here.
