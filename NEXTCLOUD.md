# Implement a server compatible with NextCloud desktop and mobile apps

FileRun

https://docs.nextcloud.com/server/19/developer_manual/client_apis/OCS/ocs-api-overview.html?highlight=capabilities
https://docs.nextcloud.com/server/latest/developer_manual/client_apis/OCS/ocs-status-api.html
https://docs.nextcloud.com/server/latest/developer_manual/client_apis/WebDAV/index.html

The desktop client is the most annoying

## Two different login flows

* [v1 is for mobile](https://docs.nextcloud.com/server/latest/developer_manual/client_apis/LoginFlow/index.html) and is quite simple to implement
* [v2 is for desktop](https://docs.nextcloud.com/server/latest/developer_manual/client_apis/LoginFlow/index.html#login-flow-v2) and requires two endpoints

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