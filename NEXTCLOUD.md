# Implement a server compatible with NextCloud desktop and mobile apps

FileRun

https://docs.nextcloud.com/server/19/developer_manual/client_apis/OCS/ocs-api-overview.html?highlight=capabilities
https://docs.nextcloud.com/server/latest/developer_manual/client_apis/OCS/ocs-status-api.html
https://docs.nextcloud.com/server/latest/developer_manual/client_apis/WebDAV/index.html

The desktop client is the most annoying

## Two different login flows

* [v1 is for mobile](https://docs.nextcloud.com/server/latest/developer_manual/client_apis/LoginFlow/index.html) and is quite simple to implement
* [v2 is for desktop](https://docs.nextcloud.com/server/latest/developer_manual/client_apis/LoginFlow/index.html#login-flow-v2) and requires two endpoints

The first login API requires you to simply generate an app password and redirect to a special URL after login:

1. Client will call the `/index.php/login/flow` URL
2. You redirect or display a login form for the user (it is recommended that you ask the user to confirm that he wants to allow the app after login)
3. After login you redirect to this special URL: `nc://login/server:https://...&user:bohwaz&password:supersecret`.

Because why re-use standard URL query parameters when you can invent weird stuff, you have to use a colon instead of an equal sign. Also it seems that parameters cannot be URL-encoded, so not sure what happens if your server URL, username or password contain a special character. But aside from that it is quite straightforward.

The second API is different but is explained in the documentation, it involves extra steps. When the process finishes, the user is left to close the opened browser window, so you got to have a specific waiting page for that. The desktop app will try to request (poll) the username and password everytime it receives focus again, or every 30 seconds.

## JSON/XML API endpoints required

Those endpoints are requested by the clients and one or the other client will fail if they don't return something that looks valid.

## Mobile app

* `getcontentlength` must be present and be a number, even for collections

## Desktop client

### Requests performed

This is when you are adding a new server to the client:

1. `GET /status.php`
2. `GET /remote.php/webdav/` (without a `Authorization` header, to see if the server supports Basic auth)
3. `PROPFIND /remote.php/webdav/` (not sure why)
4. `GET /ocs/v2.php/cloud/capabilities?format=json` 
5. `POST /index.php/login/v2`
6. `POST /index.php/login/v2/poll` (at this point you are logged-in)
8. `PROPFIND /remote.php/webdav/` requesting only the `d:getlastmodified` property for the root directory (depth = 0).
8. `PROPFIND /remote.php/webdav/` only for `oc:size` on the root directory and depth = 0. This could have been in previous request to make things faster.
9. `PROPFIND /remote.php/webdav/` only for `oc:size` and `d:getlastmodified` on the root directory and depth = 1 when you are clicking the button to select the folders to sync.

After you have set up the sync you get a bunch of requests:

1. `GET /status.php`
2. `PROPFIND /remote.php/webdav/` requesting only the `d:getlastmodified` property for the root directory (depth = 0)
3. `GET /ocs/v1.php/cloud/capabilities?format=json` which is now returning more stuff as you are logged-in (also note the `v1`, before it was `v2`)
4. `GET /ocs/v1.php/config?format=json`
5. `GET /ocs/v1.php/cloud/user?format=json`
6. (other requests for internal Nextcloud features)
7. `PROPFIND /remote.php/dav/files/toto/` (where toto is the username)

### Etags are mandatory

### Don't use text/xml

> PROPFIND reply is not XML formatted

https://github.com/nextcloud/desktop/issues/4873

### Custom WebDAV properties

// from lib/private/Files/Storage/DAV.php in NextCloud
// and apps/dav/lib/Connector/Sabre/Node.php
// R = Shareable
// S = Shared
// M = Mounted
// D = Delete
// G = Readable
// NV = Renameable/moveable
// Files only:
// W = Write (Update)
// CK = Create/Update

<oc:id>%s</oc:id>
<oc:size>0</oc:size>
<oc:downloadURL></oc:downloadURL>
<oc:permissions>%s</oc:permissions>
<oc:share-types/>

## Use a proxy with desktop client

1. start mitmweb
2. run `export http_proxy=http://localhost:8080 && nextcloud -l --logdebug`