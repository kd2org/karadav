# Roadmap

This might get supported in future (maybe):

* Likely: OpenIDConnect support for login
* Probably: [NextCloud sharing](https://docs.nextcloud.com/server/latest/developer_manual/client_apis/OCS/ocs-share-api.html)
* Maybe: NextCloud files versioning
	* [NextCloud API](https://docs.nextcloud.com/server/latest/developer_manual/client_apis/WebDAV/versions.html)
	* [NextCloud versioning pattern](https://docs.nextcloud.com/server/latest/user_manual/en/files/version_control.html)
	* [NextCloud implementation](https://github.com/nextcloud/server/blob/master/apps/files_versions/lib/Storage.php)
	* [Mercurial revlog](https://www.mercurial-scm.org/wiki/Revlog)
	* [Eric Sink on SCM versioning](https://ericsink.com/scm/scm_repositories.html)
* Maybe: document thumbnails

This probably won't get supported anytime soon:

* CalDAV/CardDAV support:
  * this would require a [bunch of new stuff implemented](https://evertpot.com/227/)
  * the only CalDAV test suite ([CavDAVtester](https://github.com/apple/ccs-caldavtester)) does not work anymore as it's written for Python 2
  * for now the best option is to use [Baikal from Sabre/DAV](https://sabre.io/baikal/) for that
  * Nice web clients to add to Baikal are [AgenDAV](https://github.com/agendav/agendav) and [InfCloud](https://inf-it.com/open-source/clients/infcloud/)
* [Extended MKCOL](https://www.rfc-editor.org/rfc/rfc5689) required only if CalDAV support is implemented
* [Partial upload via PATCH](https://github.com/miquels/webdav-handler-rs/blob/master/doc/SABREDAV-partialupdate.md)
* [Resumable upload via TUS](https://tus.io/protocols/resumable-upload.html)
* [WebDAV sharing if it ever becomes a spec?](https://evertpot.com/webdav-caldav-carddav-sharing/)

## Calendar and contacts

If you are looking for calendar (CalDAV) and contacts (CardDAV), KaraDAV doesn't have these, so have a look at these servers:

* [Davis](https://github.com/tchapi/davis) (PHP, Baikal fork)
* [Baikal](https://sabre.io/baikal/) (PHP)
* [Radicale](https://radicale.org/) (Python)
* [DAViCal](https://www.davical.org/) (PHP, calendar only)

And these clients:

* [AgenDAV](https://github.com/agendav/agendav) (web, PHP, calendar only)
* [InfCloud](https://inf-it.com/open-source/clients/infcloud/) (web, javascript, calendar + contacts + todo lists)

If you are looking at implementing a CalDAV/CardDAV client or server, look at these amazing resources from Sabre:

* [Building a CalDAV client](https://sabre.io/dav/building-a-caldav-client/)
* [Building a CardDAV client](https://sabre.io/dav/building-a-carddav-client/)
* See also [SimpleCalDAV](https://github.com/wvrzel/simpleCalDAV) for a simple implementation.
