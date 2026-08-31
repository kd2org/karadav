var css_url = document.currentScript.src.replace(/\/[^\/]+$/, '') + '/webdav.css?2025';

const WebDAVNavigator = async function (url, options) {
	const PREVIEW_TYPES = /^image\/(png|webp|svg|jpeg|jpg|gif|png)|^application\/pdf|^text\/|^audio\/|^video\/|application\/x-empty/;
	const PREVIEW_EXTENSIONS = /\.(?:png|webp|svg|jpeg|jpg|gif|png|pdf|txt|css|js|html?|md|mp4|mkv|webm|ogg|flac|mp3|aac|m4a|avi)$/i;

	const OPENDOCUMENT_TEMPLATES = {
		'ods': 'UEsDBBQAAAAAAOw6wVCFbDmKLgAAAC4AAAAIAAAAbWltZXR5cGVhcHBsaWNhdGlvbi92bmQub2FzaXMub3BlbmRvY3VtZW50LnNwcmVhZHNoZWV0UEsDBBQAAAAIABxZFFFL43PrmgAAAEABAAAVAAAATUVUQS1JTkYvbWFuaWZlc3QueG1slVDRDoMgDHz3KwjvwvZK1H9poEYSKETqon8vLpluWfawPrXXy921XQTyIxY2r0asMVA5x14uM5kExRdDELEYtiZlJJfsEpHYfPLNXd2kGBpRqzvB0QdsK3nexIUtIbQZeOqllhcc0XloecvYS8g5eAvsE+kHOfWMod7dVckzgisTIkv9p61NxIdGveBHAMaV9bGu0p3++tXQ7FBLAwQUAAAACAAAWRRRA4GGVIkAAAD/AAAACwAAAGNvbnRlbnQueG1sXY/RCsIwDEWf9SvG3uv0Ncz9S01TLLTNWFJwf29xbljzEu49N1wysvcBCRxjSZTVIGetu3ulmAU2eu/LkoGtBIFsEwkoAs+U9yv4TcPtcu2nc1dn/DqCS5hVuqG1fe0y3iIZRxg/+LQzW5ST1YBGdI3Uwge7tcpDy7yQdfIk0i03NMFD/n85vQFQSwECFAMUAAAAAADsOsFQhWw5ii4AAAAuAAAACAAAAAAAAAAAAAAAtIEAAAAAbWltZXR5cGVQSwECFAMUAAAACAAcWRRRS+Nz65oAAABAAQAAFQAAAAAAAAAAAAAAtIFUAAAATUVUQS1JTkYvbWFuaWZlc3QueG1sUEsBAhQDFAAAAAgAAFkUUQOBhlSJAAAA/wAAAAsAAAAAAAAAAAAAALSBIQEAAGNvbnRlbnQueG1sUEsFBgAAAAADAAMAsgAAANMBAAAAAA==',
		'odp': 'UEsDBBQAAAAAAC6dVEszJqyoLwAAAC8AAAAIAAAAbWltZXR5cGVhcHBsaWNhdGlvbi92bmQub2FzaXMub3BlbmRvY3VtZW50LnByZXNlbnRhdGlvblBLAwQUAAAACAAsYRRRP7fJFJoAAABBAQAAFQAAAE1FVEEtSU5GL21hbmlmZXN0LnhtbJVQwQqDMAy97ytK77bbNaj/EmpkhTYtNg79+1VhujF2WC5JXh7vJWkjsh+pCLwKtcTA5Wg7PU8MCYsvwBipgDhImXhIbo7EAp98uJmrVv1F1WgPcPSBmkqeVnVicwhNRrl32uoTjjR4bGTN1GnMOXiH4hPbBw9mX8O8u5s8Ual552j7p69LLJtIPeHHBkKL2G1cpVv79az+8gRQSwMEFAAAAAgAMl4UUXz4vRWJAAAA/gAAAAsAAABjb250ZW50LnhtbF2P0QqDMAxFn+dXiO+d22tw/ksXUyjYpJgI8+8tOGVdXsK994Qkg4QQkWASXBOxORS20ttPmlnhSF/dujCI16jAPpGCIUgmPqfgl4bn/dGNTVtq+DqKS8ymbT82t9MLZZELHslNhHOd+dUkeYvo1LaZ6vAt01bkpfNCWm4ouPAB9hV5yf8fx2YHUEsBAhQDFAAAAAAALp1USzMmrKgvAAAALwAAAAgAAAAAAAAAAAAAALSBAAAAAG1pbWV0eXBlUEsBAhQDFAAAAAgALGEUUT+3yRSaAAAAQQEAABUAAAAAAAAAAAAAALSBVQAAAE1FVEEtSU5GL21hbmlmZXN0LnhtbFBLAQIUAxQAAAAIADJeFFF8+L0ViQAAAP4AAAALAAAAAAAAAAAAAAC0gSIBAABjb250ZW50LnhtbFBLBQYAAAAAAwADALIAAADUAQAAAAA=',
		'odg': 'UEsDBBQAAAAAAE8+S1PfJa3pNAAAADQAAAAIAAAAbWltZXR5cGVhcHBsaWNhdGlvbi92bmQub2FzaXMub3BlbmRvY3VtZW50LmdyYXBoaWNzLXRlbXBsYXRlUEsDBBQAAAAIALZDh1ScUI71nQAAAEEBAAAVAAAATUVUQS1JTkYvbWFuaWZlc3QueG1slVDNDoIwDD7LU5Ddt+l1Ad+lGUWWbF3DioG3F0kEjfHgrf365ftpk4BCj0Xca6jnFKnsa6umkVyGEoojSFiceJcZqct+SkjiPvnuYs7qWp2aHehDRL0Sx6U+sClGzSBDq6w64IRdAC0LY6uAOQYPEjLZO3Vmi2Denc1tBB6CL1owcQRBVdt/rH0meeqsDX6EEJzFbudVuLFfz7pWD1BLAwQUAAAACADDQ4dUUZP77oMAAAD2AAAACwAAAGNvbnRlbnQueG1sXY9BCoNADEXX9RTifmq7Dda7TDOZMjCTiIlUb1/BVrSr8PJ+CL+TGBMSBMGpEJtDYVtnPZfMCpt9NNPIIF6TAvtCCoYgA/HvCo5puF9vTV9dui8qjmkwrdvDLq5fXPRILhDms/OTSfGW0Kktmc7yKWFZcecw+nfi15ZpT6Ed/7v11QdQSwECFAMUAAAAAABPPktT3yWt6TQAAAA0AAAACAAAAAAAAAAAAAAAtIEAAAAAbWltZXR5cGVQSwECFAMUAAAACAC2Q4dUnFCO9Z0AAABBAQAAFQAAAAAAAAAAAAAAtIFaAAAATUVUQS1JTkYvbWFuaWZlc3QueG1sUEsBAhQDFAAAAAgAw0OHVFGT++6DAAAA9gAAAAsAAAAAAAAAAAAAALSBKgEAAGNvbnRlbnQueG1sUEsFBgAAAAADAAMAsgAAANYBAAAAAA==',
		'odt': 'UEsDBBQAAAAAAPMbH0texjIMJwAAACcAAAAIAAAAbWltZXR5cGVhcHBsaWNhdGlvbi92bmQub2FzaXMub3BlbmRvY3VtZW50LnRleHRQSwMEFAAAAAgA3U0SUeqX5meSAAAAMQEAABUAAABNRVRBLUlORi9tYW5pZmVzdC54bWyVUEEOgzAMu+8VqHfa7Rq1/CUqQavUphUNE/wemDTYNO2wW2I7thWbkMNAVeA1NHOKXI/VqWlkyFhDBcZEFcRDLsR99lMiFvjUw01fVXdp7AEMIVK7CcelObEpxrag3J0y6oQT9QFbWQo5haXE4FFCZvPgXj8r6PdkLTSLMv+E+cyyX26df8TunmanN19rvr7TrVBLAwQUAAAACACQThJRWmJBaH8AAADjAAAACwAAAGNvbnRlbnQueG1sXY/RCsMgDEXf+xWj767ba+j8FxcjCGpKE6H9+wlbRfYUbs69uWTlECISeMaaqahBLtrm7cipCHzpa657AXYSBYrLJKAIvFG5UjC64Xl/zHZaf0pwj5vKYq9FaA0mOCTjCdMAXFXOTiMa0TNRI/3Im/3ZfUqHttQysqnL/0/sB1BLAQIUAxQAAAAAAPMbH0texjIMJwAAACcAAAAIAAAAAAAAAAAAAACkgQAAAABtaW1ldHlwZVBLAQIUAxQAAAAIAN1NElHql+ZnkgAAADEBAAAVAAAAAAAAAAAAAACkgU0AAABNRVRBLUlORi9tYW5pZmVzdC54bWxQSwECFAMUAAAACACQThJRWmJBaH8AAADjAAAACwAAAAAAAAAAAAAApIESAQAAY29udGVudC54bWxQSwUGAAAAAAMAAwCyAAAAugEAAAAA'
	};

	// https://docs.nextcloud.com/server/latest/developer_manual//client_apis/WebDAV/basic.html
	// https://web.archive.org/web/20250829204116/https://doc.owncloud.com/desktop/next/appendices/architecture.html#server-side-permissions
	const PERM_SHARED = 'S'; // file or folder is shared
	const PERM_SHARE = 'R'; // can be shared (includes re-share)
	const PERM_MOUNTED = 'M'; // is mounted (like on Dropbox, Samba, etc.)
	const PERM_WRITE = 'W'; // can write to file
	const PERM_CREATE = 'C'; // can create file in folder
	const PERM_MKDIR = 'K'; // can create folder (mkdir)
	const PERM_DELETE = 'D';
	const PERM_RENAME = 'N';
	const PERM_MOVE = 'V';
	const PERM_READ = 'G';

	const _ = key => typeof lang_strings != 'undefined' && key in lang_strings ? lang_strings[key] : key;

	const download_button = `<a download title="${_('Download')}" class="btn">${_('Download')}</a>`;
	const rename_button = `<input class="icon rename" type="button" value="${_('Rename')}" title="${_('Rename')}" />`;
	const delete_button = `<input class="icon delete" type="button" value="${_('Delete')}" title="${_('Delete')}" />`;
	const edit_button = `<input class="icon edit" type="button" value="${_('Edit')}" title="${_('Edit')}" />`;

	const mkdir_dialog = `<input type="text" name="mkdir" placeholder="${_('Directory name')}" />`;
	const mkfile_dialog = `<input type="text" name="mkfile" placeholder="${_('File name')}" />`;
	const rename_dialog = `<input type="text" name="rename" placeholder="${_('New file name')}" />`;
	const paste_upload_dialog = `<h3>Upload this file?</h3><input type="text" name="paste_name" placeholder="${_('New file name')}" />`;
	const edit_dialog = `<textarea name="edit" cols="70" rows="30"></textarea>`;
	const markdown_dialog = `<div id="mdp"><textarea name="edit" cols="70" rows="30"></textarea><div class="md_preview"></div></div>`;
	const delete_dialog = `<h3>${_('Confirm delete?')}</h3>`;
	const wopi_dialog = `<iframe id="wopi_frame" name="wopi_frame" allow="clipboard-read *; clipboard-write *;" allowfullscreen="true">
		</iframe>`;

	const dialog_tpl = `<dialog open><p class="close"><input type="button" value="&#x2716; ${_('Close')}" class="close" /></p><form><div>%s</div>%b</form></dialog>`;

	const html_tpl = `<!DOCTYPE html><html>
		<head><title></title><link rel="stylesheet" type="text/css" href="${css_url}" /></head>
		<body><main>
		<div class="toolbar">
			<div class="selection" style="display: none">
				<div class="buttons">
					<input type="button" class="icon download" value="${_('Download')}" />
					<input type="button" class="icon delete" value="${_('Delete')}" />
					<input type="button" class="icon cut" value="${_('Cut')}" />
					<input type="button" class="icon copy" value="${_('Copy')}" />
				</div>
				<div class="paste">
					<input type="button" value="${_('Copy here')}" class="icon copy" />
					<input type="button" value="${_('Move here')}" class="icon move" />
				</div>
				<span class="count"></span>
				<input type="button" value="${_('Cancel')}" class="icon cancel" />
			</div>
			<div class="create">
				<input type="file" style="display: none;" multiple />
				<input class="icon upload" type="button" value="${_('Upload files')}" />
				<input class="icon mk" type="button" value="${_('New')}" />
				<div class="menu">
					<input class="icon mkdir" type="button" value="${_('Directory')}" />
					<input class="icon mktext" type="button" value="${_('Text file')}" />
					<div class="wopi">
						<h5>${_('Office document')}</h5>
						<input class="icon ODT" type="button" value="${_('Text')}" />
						<input class="icon ODS" type="button" value="${_('Spreadsheet')}" />
						<input class="icon ODP" type="button" value="${_('Presentation')}" />
						<input class="icon ODG" type="button" value="${_('Drawing')}" />
					</div>
				</div>
			</div>
		</div>
		<table style="display: none">
			<thead>
				<tr>
					<td scope="col" class="check"><input type="checkbox" /><label><span></span></label></td>
					<td scope="col" class="name" data-sort="name"><button>${_('Name')}</button></td>
					<td scope="col" class="size" data-sort="size"><button>${_('Size')}</button></td>
					<td scope="col" class="date" data-sort="date"><button>${_('Date')}</button></td>
					<td></td>
				</tr>
			</thead>
			<tbody></tbody>
		</table>
		</main><div class="bg"></div></body></html>`;

	const parent_row_tpl = `<tr class="parent">
		<td class="check"></td>
		<th colspan="2"><a href="../"><span class="icon parent"><b></b></span> ${_('Back')}</a></th>
		<td class="date"></td>
		<td class="buttons"></td>
	</tr>`;

	const dir_row_tpl = `<tr data-permissions="%permissions%" class="%class%" data-name="%name%">
		<td class="check"><input type="checkbox" name="delete" value="%url%" /><label><span></span></label></td>
		<th colspan="2"><a href="%url%">%thumb% %name%</a></th>
		<td class="date">%modified%</td>
		<td class="buttons"><div>${rename_button} ${delete_button}</div></td>
	</tr>`;

	const file_row_tpl = `<tr data-permissions="%permissions%" data-mime="%mime%" data-size="%size%" data-name="%name%">
		<td class="check"><input type="checkbox" name="delete" value="%url%" /><label><span></span></label></td>
		<th><a href="%url%">%thumb% %name%</a></th>
		<td class="size">%size_bytes%</td>
		<td class="date">%modified%</td>
		<td class="buttons"><div>${edit_button} ${download_button} ${rename_button} ${delete_button}</div></td>
	</tr>`;

	const icon_tpl = `<span class="icon %icon%"><b>%icon%</b></span>`;
	const root_url = url.replace(/(?<!\/)\/.*$/, '/');
	const image_thumb_tpl = `<img src="${root_url}index.php/apps/files/api/v1/thumbnail/150/150/%path%" alt="" />`;

	const wopi_propfind_tpl = '<' + `?xml version="1.0" encoding="UTF-8"?>
		<D:propfind xmlns:D="DAV:" xmlns:W="https://interoperability.blob.core.windows.net/files/MS-WOPI/">
			<D:prop>
				<W:wopi-url/><W:token/><W:token-ttl/>
			</D:prop>
		</D:propfind>`;

	// Util functions ///////

	const template = (tpl, params) => {
		return tpl.replace(/%(\w+)%/g, (a, b) => {
			return params[b];
		});
	};

	const html = (unsafe) => {
		return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
	};

	const basename = path => path.split('/').pop();
	const dirname = path => {
		var parts = path.replace(/\/$/, '').split('/');
		parts.pop();
		return parts.join('/') + '/';
	};

	const $ = (a) => document.querySelector(a);

	const formatBytes = (bytes) => {
		const unit = _('B');

		if (bytes >= 1024*1024*1024) {
			return Math.round(bytes / (1024*1024*1024)) + ' G' + unit;
		}
		else if (bytes >= 1024*1024) {
			return Math.round(bytes / (1024*1024)) + ' M' + unit;
		}
		else if (bytes >= 1024) {
			return Math.round(bytes / 1024) + ' K' + unit;
		}
		else {
			return bytes + '  ' + unit;
		}
	};

	const formatDate = (date) => {
		if (isNaN(date)) {
			return '';
		}

		var now = new Date;
		var nb_hours = (+(now) - +(date)) / 3600 / 1000;

		if (date.getFullYear() == now.getFullYear() && date.getMonth() == now.getMonth() && date.getDate() == now.getDate()) {
			if (nb_hours <= 1) {
				return _('%d minutes ago').replace(/%d/, Math.round(nb_hours * 60));
			}
			else {
				return _('%d hours ago').replace(/%d/, Math.round(nb_hours));
			}
		}
		else if (nb_hours <= 24) {
			return _('Yesterday, %s').replace(/%s/, date.toLocaleTimeString());
		}

		return date.toLocaleString([], {year: 'numeric', month: 'numeric', day: 'numeric'});
	};

	const normalizeURL = (url) => {
		if (!url.match(/^https?:\/\//)) {
			url = base_url.replace(/^(https?:\/\/[^\/]+\/).*$/, '$1') + url.replace(/^\/+/, '');
		}

		return url;
	};

	const changeURL = (uri, push) => {
		try {
			if (push) {
				history.pushState(1, null, uri);
			}
			else {
				history.replaceState(1, null, uri);
			}

			if (popstate_evt) return;

			popstate_evt = window.addEventListener('popstate', (e) => {
				var url = location.pathname;
				browser.open(url, false);
			});
		}
		catch (e) {
			// If using a HTML page on another origin
			location.hash = uri;
		}
	};

	// Classes ///////

	var dav = {'headers': {}},
		wopi = {'discovery_url': null, 'mimes': {}, 'extensions': {}},
		browser = {
			files: {},
			selection: {},
			paste_selection: [],
			paste_action: null,
			sort_order: 'name',
			sort_order_desc: false
		};

	dav.setAuth = function (username, password) {
		dav.headers = {};

		if (username && password) {
			dav.headers['Authorization'] = 'Basic ' + btoa(user + ':' + password);
		}
	};

	dav.send = function (method, url, body, headers) {
		headers = Object.assign(headers || {}, dav.headers);
		return fetch(url, {method, body, headers});
	};

	dav.propfind = async function (url, body, depth) {
		var r = await dav.send('PROPFIND', url, body, {'Depth': depth, 'Content-Type': 'text/xml; charset=utf-8'});
		r = await r.text();
		return new window.DOMParser().parseFromString(r, "text/xml");
	};

	dav.list = async function (parent_url) {
		const body = '<'+ `?xml version="1.0" encoding="UTF-8"?>
			<D:propfind xmlns:D="DAV:" xmlns:oc="http://owncloud.org/ns">
				<D:prop>
					<D:getlastmodified/><D:getcontenttype/><D:getcontentlength/><D:resourcetype/><D:displayname/><oc:permissions/>
				</D:prop>
			</D:propfind>`;

		parent_url = normalizeURL(parent_url);
		var xml = await dav.propfind(parent_url, body, 1);
		var files = {};

		var list = xml.querySelectorAll('response');

		for (var i = 0; i < list.length; i++) {
			var node = list[i];
			var path = node.querySelector('href').textContent;
			var url = normalizeURL(path);
			var props = null;

			node.querySelectorAll('propstat').forEach(propstat => {
				if (propstat.querySelector('status').textContent.match(/200/)) {
					props = propstat;
				}
			});

			// This item didn't return any properties, everything is 404?
			if (!props) {
				console.error('Cannot find properties for: ' + url);
				return;
			}

			var name = url.replace(/\/$/, '').split('/').pop();
			name = decodeURIComponent(name);
			var is_dir = node.querySelector('resourcetype collection') ? true : false;

			// Assume we can do anything if no permissions are supplied
			var permissions = 'WCKDNVG';

			if (prop = node.querySelector('permissions')) {
				permissions = prop.textContent;
			}

			permissions = permissions.split('');

			var modified = null;

			if (prop = node.querySelector('getlastmodified')) {
				modified = new Date(prop.textContent);
			}

			var mime = null;

			if (!is_dir && (prop = node.querySelector('getcontenttype'))) {
				mime = prop.textContent;
			}

			var size = null;

			if ((prop = node.querySelector('getcontentlength')) && prop.textContent !== '') {
				size = parseInt(prop.textContent, 10);
			}

			var path = url.substring(base_url.length);

			var extension = null;

			if (!is_dir && (m = url.match(/\.([^./]{1,4})$/))) {
				extension = m[1].toLowerCase();
			}

			files[url === parent_url ? '.' : name] = {url, path, name, size, mime, modified, is_dir, permissions, extension};
		}

		return files;
	};

	dav.copymove = function(method, src, dst, overwrite) {
		dst = normalizeURL(dst);
		overwrite = overwrite === true ? 'T' : 'F';
		return dav.send(method, src, '', {'Destination': dst, 'Overwrite': overwrite});
	};

	dav.exists = async function (url) {
		var r = await dav.send('HEAD', url);
		return r.status === 200;
	};

	browser.init = () => {
		document.title = _('My files');
		document.querySelector('html').innerHTML = html_tpl;
		browser.createToolbar();

		// Create actions for sorting buttons
		document.querySelectorAll('thead td[data-sort] button').forEach(elm => elm.onclick = (e) => {
			$('thead td[data-sort="' + browser.sort_order + '"]').classList.remove('selected', 'desc', 'asc');
			var new_sort_order = e.target.parentNode.dataset.sort;

			if (browser.sort_order == new_sort_order) {
				browser.sort_order_desc = !browser.sort_order_desc;
			}

			browser.sort_order = new_sort_order;

			window.localStorage.setItem('sort_order', new_sort_order);
			window.localStorage.setItem('sort_order_desc', browser.sort_order_desc ? '1' : '0');
			browser.reload();
		});

		// Check all by checking box in table header
		document.querySelector('thead td.check input').onchange = (e) => {
			document.querySelectorAll('tbody td.check input').forEach(i => {
				if (e.target.checked !== i.checked) {
					i.click();
				}
			});
		};
	};

	browser.open = function (url, push_history, focus_file) {
		closeDialog();
		browser.url = normalizeURL(url);

		// Show order in correct column
		$('thead td[data-sort="' + browser.sort_order + '"]').className += ' selected ' + (browser.sort_order_desc ? 'desc' : 'asc');

		dav.list(url).then(files => {
			browser.root = files['.'];
			delete files['.'];
			browser.files = files;

			if (Object.keys(browser.selection).length) {
				browser.cancelSelection();
			}

			var title = browser.root.name;

			if (browser.root.url === base_url) {
				title = _('My files');
			}

			document.title = title;

			browser.setRootPermissions(browser.root.permissions);
			browser.createFilesList();

			changeURL(browser.url, push_history);

			if (focus_file) {
				browser.focusFile(focus_file);
			}

			css.show('table');
		});
	};

	browser.createFilesList = () => {
		var items = Object.values(browser.files);

		// Sort files using specified order
		items.sort((a, b) => {
			if (browser.sort_order === 'date') {
				return a.modified - b.modified;
			}
			else if (browser.sort_order === 'size') {
				return a.size - b.size;
			}
			else {
				return a.name.localeCompare(b.name);
			}
		});

		// Sort with directories first
		if (browser.sort_order !== 'date') {
			items.sort((a, b) => b.is_dir - a.is_dir);
		}

		if (browser.sort_order_desc) {
			items = items.reverse();
		}

		var rows = '';

		// Add link to parent directory
		if (browser.root.url !== base_url) {
			rows += parent_row_tpl;
		}

		items.forEach(item => {
			// Don't include files we cannot read
			if (item.permissions !== null
				&& !item.permissions.includes(PERM_READ)) {
				console.error('OC permissions deny read access to this file: ' + item.name, 'Permissions: ', item.permissions);
				return;
			}

			var row = item.is_dir ? dir_row_tpl : file_row_tpl;
			item.size_bytes = item.size !== null ? formatBytes(item.size).replace(/ /g, '&nbsp;') : null;

			item.icon = (item.extension || '').toUpperCase();
			item.class = item.is_dir ? 'dir' : 'file';
			item.modified = item.modified !== null ? formatDate(item.modified) : null;
			item.name = html(item.name);

			if (item.mime && item.mime.match(/^image\//) && options.nc_thumbnails) {
				item.thumb = template(image_thumb_tpl, item);
			}
			else {
				item.thumb = template(icon_tpl, item);
			}

			rows += template(row, item);
		});

		document.querySelector('main > table > tbody').innerHTML = rows;

		document.querySelectorAll('table tbody tr').forEach(browser.createRowActions);
	};

	browser.cancelSelection = () => {
		css.hide('.toolbar .selection');
		browser.selection = {};

		if (browser.paste_selection.length) {
			browser.paste_selection = [];
			browser.paste_action = null;
			css.hide('.toolbar .paste');
		}

		browser.unselectAll();
	};

	browser.unselectAll = () => {
		Object.values(browser.files).forEach(f => f.selected = false);
		css.all('table input[type=checkbox]:checked').forEach(e => e.checked = false);
	};

	browser.updateSelection = () => {
		css.show('.selection .buttons, .selection .buttons .delete, .selection .buttons .copy, .selection .buttons .cut');
		var file_count = 0;
		var dir_count = 0;

		for (var key in browser.selection) {
			if (!browser.selection.hasOwnProperty(key)) {
				continue;
			}

			let file = browser.selection[key];

			dir_count += file.is_dir ? 1 : 0;
			file_count += !file.is_dir ? 1 : 0;

			if (!file.permissions.includes(PERM_DELETE)) {
				css.hide('.selection .buttons .delete');
			}

			if (!file.permissions.includes(PERM_MOVE)) {
				css.hide('.selection .buttons  .cut');
			}

			if (!browser.root.permissions.includes(PERM_CREATE)) {
				css.hide('.selection .buttons  .copy');
			}
		}

		// Hide selected files menu
		if (!file_count && !dir_count) {
			css.hide('.toolbar .selection');
			return;
		}

		css.show('.toolbar .selection');

		var count = $('.toolbar .selection .count');
		count.innerHTML = '<span class="prefix">' + _('Selected:') + '</span>';

		if (dir_count) {
			let msg = _('%d directories').replace('%d', dir_count);
			count.innerHTML += '<span class="directories">' + msg + '</span>';
		}

		if (file_count) {
			let msg = _('%d files').replace('%d', file_count);
			count.innerHTML += '<span class="files">' + msg + '</span>';
		}
	};

	browser.setRowPermissions = (tr, file) => {
		var p = file.permissions;
		var hideButton = a => document.querySelector('.buttons .' + a).style.display = 'none';

		if (!p.includes(PERM_RENAME)) {
			hideButton('rename');
		}

		if (!p.includes(PERM_DELETE)) {
			hideButton('delete');
		}

		if (file.is_dir || !p.includes(PERM_WRITE)) {
			hideButton('edit');
		}

		if (!p.includes(PERM_SHARE)) {
			//hideButton('share');
		}

		if (!p.includes(PERM_SHARED)) {
			//hideButton('shared');
		}

		// if (mime.match(/^text\/|application\/x-empty/))
	};

	browser.createRowActions = (tr) => {
		// Ignore parent row
		if (tr.classList.contains('parent')) {
			tr.querySelector('a').onclick = () => {
				browser.open(dirname(browser.root.url));
				return false;
			};
			return;
		}

		var $$ = (a) => tr.querySelector(a);
		var url = $$('a').href;
		var url_name = decodeURIComponent(basename(url.replace(/\/$/, '')));
		var file = browser.files[url_name];

		browser.setRowPermissions(tr, file);

		var dir = $$('[colspan]');
		var mime = !dir ? tr.getAttribute('data-mime') : 'dir';
		var buttons = $$('td.buttons div');
		var permissions = tr.getAttribute('data-permissions');
		var size = tr.getAttribute('data-size');

		var checkbox = $$('input[type=checkbox]');
		checkbox.onchange = () => {
			var file = browser.files[url_name];

			if (checkbox.checked) {
				browser.selection[file.name] = file;
			}
			else {
				delete browser.selection[file.name];
			}

			browser.updateSelection();
		};

		if (file.is_dir) {
			$$('a').onclick = () => {
				browser.open(file.url, true);
				return false;
			};

			return;
		}

		$$('.buttons .rename').onclick = () => {
			openDialog(rename_dialog);
			let t = $('input[name=rename]');
			t.value = file.name;
			t.focus();
			t.selectionStart = 0;
			t.selectionEnd = file.name.lastIndexOf('.');
			document.forms[0].onsubmit = () => {
				var name = t.value.trim();

				if (!name) return false;

				var new_url = browser.root.url + encodeURIComponent(name);

				dav.copymove('MOVE', file.url, new_url, false);
				browser.reload(name);
				return false;
			};
		};

		$$('.buttons .delete').onclick = (e) => {
			openDialog(delete_dialog);
			document.forms[0].onsubmit = () => {
				dav.send('DELETE', file.url);
				browser.reload(name);
				return false;
			};
		};

		if (!file.is_dir) {
			$$('.buttons [download]').href = file.url;
			$$('.buttons [download]').download = file.name;
		}

		var edit_url, view_url;
		$$('.buttons .edit').style.display = 'none';
		var allow_preview = false;

		// Don't preview PDF in mobile, it doesn't work
		if ((mime == 'application/pdf' || file.name.match(/\.pdf$/i))
			&& window.navigator.userAgent.match(/Mobi|Tablet|Android|iPad|iPhone/)) {
			allow_preview = false;
		}
		else if (mime.match(PREVIEW_TYPES)
			|| file.name.match(PREVIEW_EXTENSIONS)) {
			allow_preview = true;
		}

		if (permissions.includes(PERM_WRITE)
			&& (file.mime.match(/^text\/|application\/x-empty/)
				|| file.name.match(/\.(md|txt)$/i)
				|| (edit_url = wopi.getEditURL(file.url, file.mime)))) {
			if (edit_url)  {
				var action = () => { wopi.open(file.url, edit_url); return false; };
				$$('.icon').classList.add('document');
				allow_preview = false;
			}
			else {
				allow_preview = !file.name.match(/\.md$/);
				var action = () => { browser.editTextFile(file); return false; };
			}

			$$('.buttons .edit').style.display = null;
			$$('.buttons .edit').onclick = action;

			if (!allow_preview) {
				$$('th a').onclick = action;
			}
		}
		// Open WOPI viewser
		else if (view_url = wopi.getViewURL(file.url, mime)) {
			$$('.icon').classList.add('document');
			$$('th a').onclick = () => { wopi.open(file.url, view_url); return false; };
		}
		else if (!file.is_dir) {
			$$('th a').download = file.name;
			$$('th a').href = file.url;
		}


		if (allow_preview) {
			$$('th a').onclick = () => { browser.openPreview(file); return false; };
		}
	};

	browser.openPreview = (file) => {
		if (file.name.match(/\.md$/i)) {
			openDialog('<div class="md_preview"></div>', false);
			$('dialog').className = 'preview';
			req('GET', file.url).then(r => r.text()).then(t => {
				$('.md_preview').innerHTML = editor.markdownToHTML(t);
			});
			return false;
		}

		if (user && password) {
			(async () => { preview(file.mime, await get_url(file.url)); })();
		}
		else {
			preview(file.mime, file.url);
		}

		return false;
	};

	browser.editTextFile = (file) => {
		req('GET', file.url).then((r) => r.text().then((t) => {
			let md = file.url.match(/\.md$/i);
			var tpl = dialog_tpl.replace(/%b/, '');
			$('body').classList.add('dialog');
			$('body').insertAdjacentHTML('beforeend', tpl.replace(/%s/, md ? markdown_dialog : edit_dialog));

			var tb = $('.close');
			tb.className = 'toolbar';
			tb.innerHTML = `<input type="button" value="&#x2716; ${_('Cancel')}" class="close" />
				<label><input type="checkbox" class="autosave" /> ${_('Autosave')}</label>
				<span class="status"></span>
				<input class="save" type="button" value="${_('Save and close')}" />`;

			var txt = $('textarea[name=edit]');
			txt.value = t;

			var saved_status = $('.toolbar .status');
			var close_btn = $('.toolbar .close');
			var save_btn = $('.toolbar .save');
			var autosave = $('.toolbar .autosave');

			var c = localStorage.getItem('autosave') ?? options.autosave;
			autosave.checked = c == 1 || c ===  true;
			autosave.onchange = () => {
				localStorage.setItem('autosave', autosave.checked ? 1 : 0);
			};

			var preventClose = (e) => {
				if (txt.value == t) {
					return;
				}

				e.preventDefault();
				e.returnValue = '';
				return true;
			};

			var close = () => {
				if (txt.value !== t) {
					if (!confirm(_('Your changes have not been saved. Do you want to cancel WITHOUT saving?'))) {
						return;
					}
				}

				window.removeEventListener('beforeunload', preventClose, {capture: true});
				closeDialog();
			};

			var save = () => {
				reqOrError('PUT', file.url, txt.value);
				t = txt.value;
				updateSaveStatus();
			};

			var updateSaveStatus = () => {
				saved_status.innerHTML = txt.value !== t ? '⚠️ ' + _('Modified') : '✔️ ' + _('Saved');
			};

			save_btn.onclick = () => { save(); close(); };
			close_btn.onclick = close;

			// Prevent close of tab if content has changed and is not saved
			window.addEventListener('beforeunload', preventClose, { capture: true });

			txt.onkeydown = (e) => {
				if (e.ctrlKey && e.key == 's') {
					save();
					e.preventDefault();
					return false;
				}
				else if (e.key === 'Escape') {
					close();
					e.preventDefault();
					return false;
				}
			};

			txt.onkeyup = (e) => {
				updateSaveStatus();
			};

			window.setInterval(() => {
				if (autosave.checked && t != txt.value) {
					save();
				}
			}, 10000);

			// Markdown editor
			if (md) {
				let pre = $('.md_preview');

				txt.oninput = () => {
					pre.innerHTML = editor.markdownToHTML(txt.value);
				};

				txt.oninput();

				// Sync scroll, not perfect but better than nothing
				txt.onscroll = (e) => {
					var p = e.target.scrollTop / (e.target.scrollHeight - e.target.offsetHeight);
					var target = e.target == pre ? txt : pre;
					target.scrollTop = p * (target.scrollHeight - target.offsetHeight);
					e.preventDefault();
					return false;
				};
			}

			document.forms[0].onsubmit = () => {
				var content = txt.value;

				return reqAndReload('PUT', file.url, content);
			};
		}));
	};

	browser.reload = function (focus_file) {
		closeDialog();
		stopLoading();
		browser.open(browser.url, false, focus_file);
	};

	browser.focusFile = (name) => {
		css.all('tr[data-name]').forEach(tr => {
			tr.classList.remove('focus');

			if (tr.dataset.name == name) {
				tr.classList.add('focus');

				if (!css.isVisible(tr)) {
					tr.scrollIntoView({block: 'center', behavior: 'smooth'});
				}
			}
		});
	};

	browser.getFreeFilename = function (filename) {
		var increment_filename = (filename) => filename.replace(/(?:\s+\((\d+)\))?(\.[^.]+)?$/, (_, i, ext) => {
			var i = parseInt(i || 0, 10) + 1;
			return ' (' + i + ')' + (ext || '');
		});

		var j = 0;

		while (browser.files.hasOwnProperty(filename)) {
			filename = increment_filename(filename);

			if (j++ > 100) {
				break;
			}
		}

		return filename;
	};

	browser.pasteTo = function (file, action) {
		// Don't do anything if cutting and pasting in the same directory
		if (action === 'move' && browser.root.url === dirname(file.url)) {
			alert(_('Cannot move to the same directory'));
			return;
		}

		var filename = browser.getFreeFilename(file.name);
		return dav.copymove(action === 'copy' ? 'COPY' : 'MOVE', file.url, browser.root.url + encodeURIComponent(filename), false);
	};

	browser.applyPasteSelection = async function () {
		animateLoading();

		for (var i = 0; i < browser.paste_selection.length; i++) {
			await browser.pasteTo(browser.paste_selection[i], browser.paste_action);
		}

		browser.cancelSelection();
		browser.reload();
	};

	browser.createPasteSelection = (action) => {
		if (!Object.keys(browser.selection).length) {
			alert(_('No file is selected'));
			return;
		}

		browser.paste_selection = Object.values(browser.selection);
		browser.paste_action = action;
		browser.selection = {};
		browser.unselectAll();

		css.show('.toolbar .selection, .toolbar .paste, .toolbar .paste .copy, .toolbar .paste .move'); // re-enable selection, as it was hidden by cancelSelection
		css.hide('.toolbar .buttons');

		if (action === 'move') {
			css.hide('.toolbar .paste .copy');
		}
		else {
			css.hide('.toolbar .paste .move');
		}
	};

	browser.downloadSelectedFiles = async () => {
		var items = document.querySelectorAll('tbody input[type=checkbox]:checked');
		for (var i = 0; i < items.length; i++) {
			var input = items[i];
			var row = input.parentNode.parentNode;

			// Skip directories
			if (!row.dataset.mime) {
				return;
			}

			await download(row.dataset.name, row.dataset.size, row.querySelector('th a').href);
		}
	};

	browser.deleteSelectedFiles = () => {
		var l = document.querySelectorAll('input[name=delete]:checked');

		if (!l.length) {
			alert(_('No file is selected'));
			return;
		}

		openDialog(delete_dialog);
		document.forms[0].onsubmit = () => {
			animateLoading();

			for (var i = 0; i < l.length; i++) {
				dav.send('DELETE', )
				reqOrError('DELETE', l[i].value);
			}

			// Don't reload too fast
			window.setTimeout(() => {
				stopLoading();
				browser.reload();
			}, 500);
		};
	};

	var editor = {};
	editor.create = (input_name, file_name, text) => {
		if ('prismEditor' in window) {
			var e = document.createElement('div');
			const ed = prismEditor(e, {
				language: file_name.match(/\.md$/i) ? 'markdown' : 'text',
				value: text,
				wordwrap: true
			});
			ed.textarea.name = input_name;

			return ed.textarea;
		}

		var t = document.createElement('textarea');
		t.name = input_name;
		t.value = text;
		return t;
	};

	editor.markdownToHTML = (text) => {
		text = text.replace(/\r\n|\r/g, "\n");
		text = html(text);

		var lines = text.split("\n");
		var out = '';
		var in_code = false;
		var in_quote = false;
		var in_table = false;
		var ul_length = null;
		var ul_level = 0;
		var ol = false;

		for (var i = 0; i < lines.length; i++) {
			var line = lines[i].trimEnd();

			if (line.match(/^\s*```\w*\s*/)) {
				if (in_code) {
					out += '</code></pre>';
					in_code = false;
				}
				else {
					out += '<pre><code>';
					in_code = true;
				}
				continue;
			}
			else if (in_code) {
				out += line + "\n";
				continue;
			}
			else if (line.match(/^\|[\|\s-:]+\|$/)) {
				// Ignore table separator
				continue;
			}
			else if (line.match(/^\|/)) {
				line = line.replace(/^\||\|$/g, '');
				line = line.replace(/\|/g, '</td><td>');

				if (!in_table) {
					out += '<table>';
					in_table = true;
				}

				out += '<tr><td>' + line + '</td></tr>';
				continue;
			}
			else if (in_table) {
				out += '</table>';
				in_table = false;
				continue;
			}
			else if (match = line.match(/^#+/)) {
				var l = match[0].length;
				out += '<h' + l + '>' + line.substr(l).trim() + '</h' + l + '>';
				continue;
			}
			else if (line.match(/^(?:---+|\*{3,}|___+)$/)
				&& (i === 0 || !lines[i-1].trim().length)) {
				out += '<hr />';
				continue;
			}
			else if (match = line.match(/^(&gt;\s*)+/)) {
				line = line.substr(match[0].length).trim();

				if (!in_quote) {
					out += '<blockquote>';
					in_quote = true;
				}
			}
			else if (in_quote) {
				out += '</blockquote>';
				in_quote = false;
			}

			if (match = line.match(/^(\s*)[*-]\s+/)) {
				var length = match[1].length;

				if (ul_level === 0 || length > ul_length) {
					out += '<ul><li>';
					ul_length = length;
					ul_level++;
				}
				else if (length < ul_length) {
					out += '</li></ul><li>';
					ul_length = length;
					ul_level--;
				}
				else {
					out += '</li>';
					out += '<li>';
				}

				line = line.substr(match[0].length).trim();
			}
			else if (ul_level) {
				while (ul_level) {
					out += '</ul>';
					ul_level--;
				}

				ul_length = null;

				if (line === '') {
					continue;
				}
			}
			else if (match = line.match(/^\d+\.\s+/)) {
				if (!ol) {
					out += '<ol>';
					ol = true;
				}

				line = line.substr(match[0].length).trim();
				out += '<li>';
			}
			else if (ol) {
				out += '</ol>';
				ol = false;

				if (line === '') {
					continue;
				}
			}

			if (line === '') {
				out += '<p>';
			}
			else {
				line = line.replace(/!\[(.*?)\]\((.+?)\)/g, (_, alt, url) => '<img src="' + url + '" alt="' + alt + '" />');
				line = line.replace(/\[(.*?)\]\((.+?)\)/g, (_, l, h) => '<a href="' + h + '">' + (l || h) + '</a>');
				line = line.replace(/&lt;(https?:\/\/.+?)&gt;/g, (_, url) => '<a href="' + url + '">' + url + '</a>');
				line = line.replace(/(?<=^|\s)(https?:\/\/.+?)(?=$|\s)/gm, (_, url) => '<a href="' + url + '">' + url + '</a>');
				line = line.replace(/\*{3}(.+?)\*{3}/g, (_, text) => '<strong><em>' + text + '</em></strong>');
				line = line.replace(/\*{2}(.+?)\*{2}/g, (_, text) => '<strong>' + text + '</strong>');
				line = line.replace(/\*{1}(.+?)\*{1}/g, (_, text) => '<em>' + text + '</em>');
				line = line.replace(/==(\b.+?\b)==/g, (_, text) => '<mark>' + text + '</mark>');
				line = line.replace(/~~(\b.+?\b)~~/g, (_, text) => '<s>' + text + '</s>');
				line = line.replace(/`(\b.+?\b)`/g, (_, text) => '<code>' + text + '</code>');
				line = line.replace(/\[ +\](?=\s|$)/gm, '<span class="unchecked">☐</span>');
				line = line.replace(/\[x\](?=\s|$)/gmi, '<span class="checked">☑</span>');
				out += line;
			}

			if (!ol && !ul_level && line !== '') {
				out += "<br />";
			}
		}

		return out;
	};

	var js = {};
	js.loaded = {};
	js.load = (url, css) => {
		return new Promise((resolve) => {
			if (url in js.loaded) {
				resolve(url);
				return;
			}

			var script = document.createElement('script');
			script.type = 'text/javascript';
			script.src = url;
			script.onload = () => resolve(url)
			document.head.appendChild(script);

			if (css) {
				var l = document.createElement('link');
				l.type = 'text/css';
				l.rel = 'stylesheet';
				l.href = css;
				document.head.appendChild(l);
			}
		});
	};

	js.prism = (resolve) => js.load('./prism_editor.js', './prism_editor.css').then(url => {
		if (!(url in js.loaded)) {

		}

		resolve();
	});

	var css = {};
	css.all = (selector) => document.querySelectorAll(selector);
	css.hide = (selector) => css.all(selector).forEach(e => e.style.display = 'none');
	css.show = (selector) => css.all(selector).forEach(e => e.style.display = null);
	css.toggle = (selector, show) => show ? css.show(selector) : css.hide(selector);
	css.onclick = (selector, callback) => css.all(selector).forEach(el => el.onclick = (ev) => callback(ev, el));
	css.isVisible = (elm) => {
		var rect = elm.getBoundingClientRect();
		var viewHeight = Math.max(document.documentElement.clientHeight, window.innerHeight);
		return !(rect.bottom < 0 || rect.top - viewHeight >= 0);
	};

	browser.createToolbar = () => {
		$('.selection .buttons .download').onclick = browser.downloadSelectedFiles;
		$('.selection .buttons .copy').onclick = () => browser.createPasteSelection('copy');
		$('.selection .buttons .cut').onclick = () => browser.createPasteSelection('move');
		$('.selection .buttons .delete').onclick = browser.deleteSelectedFiles;
		$('.selection .cancel').onclick = browser.cancelSelection;
		$('.selection .paste .copy').onclick = browser.applyPasteSelection;
		$('.selection .paste .move').onclick = browser.applyPasteSelection;
		$('.toolbar .paste .move, .toolbar .paste .copy').onclick = browser.applyPasteSelection;

		// Hide stuff that can only be used if permissions allow
		css.hide('.toolbar .create, .selection .buttons .copy, .selection .buttons  .cut, .selection .buttons .delete, .toolbar .menu, .toolbar .menu .wopi, .toolbar .paste');

		var menu = $('.toolbar .menu');
		menu.dataset.visible = '0';

		var toggle_menu = () => {
			menu.dataset.visible = menu.dataset.visible == 0 ? 1 : 0;
			menu.style.display = menu.dataset.visible == 1 ? 'flex' : 'none';
		};

		$('.toolbar .mk').onclick = toggle_menu;

		if (wopi.extensions) {
			css.show('.toolbar .menu .wopi');

			css.onclick('.toolbar .menu .wopi input', (ev, btn) => {
				toggle_menu();
				openDialog(mkfile_dialog);
				var t = $('input[name=mkfile]');
				var ext = btn.className.substr(-3).toLowerCase();
				t.focus();
				document.forms[0].onsubmit = () => {
					var name = t.value;
					closeDialog();

					if (!name) return false;

					name = encodeURIComponent(name + '.' + ext);
					var file_url = current_url + name;

					// Cannot use atob here, or JS will send blob as unicode text
					fetch('data:application/octet-stream;base64,' + OPENDOCUMENT_TEMPLATES[ext]).then(r => r.blob()).then(r => {
						req('PUT', file_url, r, {'Content-Type': 'application/octet-stream'}).then(() => {
							wopi.open(file_url, wopi.getEditURL(file_url, ext));
						});
					});

					return false;
				};
			});
		}

		$('.mkdir').onclick = () => {
			toggle_menu();
			openDialog(mkdir_dialog);
			document.forms[0].onsubmit = () => {
				var name = $('input[name=mkdir]').value;

				if (!name) return false;

				var new_url = current_url + encodeURIComponent(name);

				dav.send('MKCOL', new_url).then(() => browser.open(new_url + '/', true));
				return false;
			};
		};

		$('.mktext').onclick = () => {
			toggle_menu();
			openDialog(mkfile_dialog);
			var t = $('input[name=mkfile]');
			t.value = '.md';
			t.focus();
			t.selectionStart = t.selectionEnd = 0;
			document.forms[0].onsubmit = () => {
				var name = t.value;

				if (!name) return false;

				dav.send('PUT', current_url + encodeURIComponent(name), '');
				browser.reload(name);
				return false;
			};
		};

		var fi = $('input[type=file]');

		$('.upload').onclick = () => fi.click();

		fi.onchange = () => {
			if (!fi.files.length) return;
			uploadFiles(fi.files);
		};
	};

	browser.setRootPermissions = (perms) => {
		css.toggle('.toolbar .create', perms.includes(PERM_CREATE) || perms.includes(PERM_MKDIR));
	};

	const reqXML = (method, url, body, headers) => {
		return req(method, url, body, headers).then((r) => {
				if (!r.ok) {
					throw new Error(r.status + ' ' + r.statusText);
				}
				return r.text();
			}).then(str => new window.DOMParser().parseFromString(str, "text/xml"));
	};

	const reqHandler = (r, c) => {
		if (!r.ok) {
			return r.text().then(t => {
				var message;
				if (a = t.match(/<((?:\w+:)?message)>(.*)<\/\1>/)) {
					message = "\n" + a[2];
				}

				throw new Error(r.status + ' ' + r.statusText + message);
			});
		}
		window.setTimeout(c, 200);
		return r;
	};

	const reqAndReload = (method, url, body, headers) => {
		animateLoading();
		req(method, url, body, headers).then(r => reqHandler(r, () => {
			stopLoading();
			browser.reload();
		})).catch(e => {
			console.error(e);
			alert(e);
		});
		return false;
	};

	const req = (method, url, body, headers) => {
		return dav.send(method, url, body, headers);
	};

	const xhr = (method, url, progress_callback) => {
		var xhr = new XMLHttpRequest();
		current_xhr = xhr;
		xhr.responseType = 'blob';
		var p = new Promise((resolve, reject) => {
			xhr.open(method, url);
			xhr.onload = function () {
				if (this.status >= 200 && this.status < 300) {
					resolve(xhr.response);
				} else {
					reject({
						status: this.status,
						statusText: xhr.statusText
					});
				}
			};
			xhr.onerror = function () {
				reject({
					status: this.status,
					statusText: xhr.statusText
				});
			};
			xhr.onprogress = progress_callback;
			xhr.send();
		});
		return p;
	};

	const uploadFiles = (files) => {
		animateLoading();

		(async () => {
			for (var i = 0; i < files.length; i++) {
				var f = files[i];
				await reqOrError('PUT', current_url + encodeURIComponent(f.name), f);
			}

			window.setTimeout(() => {
				browser.reload();
			}, 500);
		})();
	};

	const reqOrError = (method, url, body) => {
		return req(method, url, body).then(reqHandler).catch(e => {
			console.error(e);
			alert(e);
			browser.reload();
			throw e;
		});
	}

	const get_url = async (url) => {
		var progress = (e) => {
			var p = $('progress');
			if (!p || e.loaded <= 0) return;
			p.value = e.loaded;
			$('.progress_bytes').innerHTML = formatBytes(e.loaded);
		};

		if (temp_object_url) {
			window.URL.revokeObjectURL(temp_object_url);
		}

		return await xhr('GET', url, progress).then(blob => {
			temp_object_url = window.URL.createObjectURL(blob);
			return temp_object_url;
		});
	};

	wopi.init = async function (discovery_url) {
		try {
			var d = await reqXML('GET', discovery_url);
		}
		catch (e) {
			// FIXME: notify
			return;
		}

		d.querySelectorAll('app').forEach(app => {
			var mime = (a = app.getAttribute('name').match(/^.*\/.*$/)) ? a[0] : null;
			wopi.mimes[mime] = {};

			app.querySelectorAll('action').forEach(action => {
				var ext = action.getAttribute('ext').toUpperCase();
				var url = action.getAttribute('urlsrc').replace(/<[^>]*&>/g, '');
				var name = action.getAttribute('name');

				if (mime) {
					wopi.mimes[mime][name] = url;
				}
				else {
					if (!wopi.extensions.hasOwnProperty(ext)) {
						wopi.extensions[ext] = {};
					}

					wopi.extensions[ext][name] = url;
				}
			});
		});
	};

	wopi.getEditURL = (name, mime) => {
		var file_ext = name.replace(/^.*\.(\w+)$/, '$1').toUpperCase();

		if (wopi.mimes.hasOwnProperty(mime) && wopi.mimes[mime].hasOwnProperty('edit')) {
			return wopi.mimes[mime].edit;
		}
		else if (wopi.extensions.hasOwnProperty(file_ext) && wopi.extensions[file_ext].hasOwnProperty('edit')) {
			return wopi.extensions[file_ext].edit;
		}

		return null;
	};

	wopi.getViewURL = (name, mime) => {
		var file_ext = name.replace(/^.*\.(\w+)$/, '$1').toUpperCase();

		if (wopi.mimes.hasOwnProperty(mime) && wopi.mimes[mime].hasOwnProperty('view')) {
			return wopi.mimes[mime].view;
		}
		else if (wopi.extensions.hasOwnProperty(file_ext) && wopi.extensions[file_ext].hasOwnProperty('view')) {
			return wopi.extensions[file_ext].view;
		}

		return wopi.getEditURL(name, mime);
	};

	wopi.open = async (document_url, wopi_url) => {
		var properties = await reqXML('PROPFIND', document_url, wopi_propfind_tpl, {'Depth': '0'});
		var src = (a = properties.querySelector('wopi-url')) ? a.textContent : null;
		var token = (a = properties.querySelector('token')) ? a.textContent : null;
		var token_ttl = (a = properties.querySelector('token-ttl')) ? a.textContent : +(new Date(Date.now() + 3600 * 1000));

		if (!src || !token) {
			alert('Cannot open document: WebDAV server did not return WOPI properties');
		}

		wopi_url += '&WOPISrc=' + encodeURIComponent(src);

		openDialog(wopi_dialog, false);
		$('dialog').className = 'preview';

		var f = $('dialog form');
		f.target = 'wopi_frame';
		f.action = wopi_url;
		f.method = 'post';
		f.insertAdjacentHTML('beforeend', `<input name="access_token" value="${token}" type="hidden" /><input name="access_token_ttl" value="${token_ttl}" type="hidden" />`);
		f.submit();
	};

	const openDialog = (html, ok_btn = true) => {
		var tpl = dialog_tpl.replace(/%b/, ok_btn ? `<p><input type="submit" value="${_('OK')}" /></p>` : '');
		$('body').classList.add('dialog');
		$('body').insertAdjacentHTML('beforeend', tpl.replace(/%s/, html));
		$('.close input').onclick = closeDialog;
		evt = window.addEventListener('keyup', (e) => {
			if (e.key != 'Escape') return;
			closeDialog();
			return false;
		});
		if (a = $('dialog form input, dialog form textarea')) a.focus();
	};

	const closeDialog = (e) => {
		if (!$('body').classList.contains('dialog')) {
			return;
		}

		if (current_xhr) {
			current_xhr.abort();
			current_xhr = null;
		}

		window.onbeforeunload = null;

		$('body').classList.remove('dialog');
		if (!$('dialog')) return;
		$('dialog').remove();
		window.removeEventListener('keyup', evt);
		evt = null;
	};

	const download = async (name, size, url) => {
		window.onbeforeunload = () => {
			if (current_xhr) {
				current_xhr.abort();
			}

			return true;
		};

		openDialog(`<p class="spinner"><span></span></p>
			<h3>${html(name)}</h3>
			<progress max="${size}"></progress>
			<p><span class="progress_bytes"></span> / ${formatBytes(size)}</p>`, false);

		await get_url(url);
		const a = document.createElement('a');
		a.style.display = 'none';
		a.href = temp_object_url;
		a.download = name;
		document.body.appendChild(a);
		a.click();
		window.URL.revokeObjectURL(temp_object_url);
		a.remove();

		closeDialog();
		window.onbeforeunload = null;
	};

	const preview = (type, url) => {
		if (type.match(/^image\//)) {
			openDialog(`<img src="${url}" />`, false);
		}
		else if (type.match(/^audio\//)) {
			openDialog(`<audio controls="true" autoplay="true" src="${url}" />`, false);
		}
		else if (type.match(/^video\//)) {
			openDialog(`<video controls="true" autoplay="true" src="${url}" />`, false);
		}
		else if (type.match(/pdf/)) {
			openDialog(`<iframe src="${url}" />`, false);
		}
		else {
			openDialog(`<iframe sandbox="" src="${url}" />`, false);
		}

		$('dialog').className = 'preview';
	};

	const animateLoading = () => {
		document.body.classList.add('loading');
	};

	const stopLoading = () => {
		document.body.classList.remove('loading');
	};

	var items = [[], []];
	var current_xhr = null;
	var current_url = url;
	var base_url = url;
	const user = options.user || null;
	const password = options.password || null;
	dav.setAuth(user, password);

	if (location.pathname.indexOf(base_url) === 0) {
		current_url = location.pathname;
	}

	if (!base_url.match(/^https?:/)) {
		base_url = location.href.replace(/^(https?:\/\/[^\/]+\/).*$/, '$1') + base_url.replace(/^\/+/, '');
	}

	var evt, paste_upload, popstate_evt, temp_object_url;
	var sort_order = window.localStorage.getItem('sort_order') || 'name';
	var sort_order_desc = !!parseInt(window.localStorage.getItem('sort_order_desc'), 10);

	wopi.discovery_url = options.wopi_discovery_url || null;
	options.autosave = options.autosave || false;

	// Wait for WOPI discovery before creating the list
	if (wopi.discovery_url) {
		await wopi.init(wopi.discovery_url);
	}

	browser.init();
	browser.open(current_url);

	window.addEventListener('paste', (e) => {
		let items = e.clipboardData.items;
		const IMAGE_MIME_REGEX = /^image\/(p?jpeg|gif|png)$/i;

		for (var i = 0; i < items.length; i++) {
			if (items[i].kind === 'file' || IMAGE_MIME_REGEX.test(items[i].type)) {
				e.preventDefault();
				let f = items[i].getAsFile();
				let name = f.name == 'image.png' ? f.name.replace(/\./, '-' + (+(new Date)) + '.') : f.name;

				paste_upload = f;

				openDialog(paste_upload_dialog);

				let t = $('input[name=paste_name]');
				t.value = name;
				t.focus();
				t.selectionStart = 0;
				t.selectionEnd = name.lastIndexOf('.');

				document.forms[0].onsubmit = () => {
					name = encodeURIComponent(t.value);
					return reqAndReload('PUT', current_url + name, paste_upload);
				};

				return;
			}
		}
	});

	var dragcounter = 0;

	window.addEventListener('dragover', (e) => {
		e.preventDefault();
		e.stopPropagation();
	});

	window.addEventListener('dragenter', (e) => {
		e.preventDefault();
		e.stopPropagation();

		if (!dragcounter) {
			document.body.classList.add('dragging');
		}

		dragcounter++;
	});

	window.addEventListener('dragleave', (e) => {
		e.preventDefault();
		e.stopPropagation();
		dragcounter--;

		if (!dragcounter) {
			document.body.classList.remove('dragging');
		}
	});

	window.addEventListener('drop', (e) => {
		e.preventDefault();
		e.stopPropagation();
		document.body.classList.remove('dragging');
		dragcounter = 0;

		var files = [...e.dataTransfer.items].map(item => item.getAsFile());

		files = files.filter(f => f !== null);

		if (!files.length) return;

		uploadFiles(files);
	});
};

if (url = document.querySelector('html').getAttribute('data-webdav-url')) {
	WebDAVNavigator(url, {
		'wopi_discovery_url': document.querySelector('html').getAttribute('data-wopi-discovery-url'),
		'nc_thumbnails': document.querySelector('html').getAttribute('data-nc-thumbnails') ? true : false
	});
}
