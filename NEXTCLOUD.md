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

## Desktop client

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