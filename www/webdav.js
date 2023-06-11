var css_url = document.currentScript.src.replace(/\/[^\/]+$/, '') + '/webdav.css';

const WebDAVNavigator = (url, options) => {
	// Microdown
	// https://github.com/commit-intl/micro-down
	const microdown=function(){function l(n,e,r){return"<"+n+(r?" "+Object.keys(r).map(function(n){return r[n]?n+'="'+(a(r[n])||"")+'"':""}).join(" "):"")+">"+e+"</"+n+">"}function c(n,e){return e=n.match(/^[+-]/m)?"ul":"ol",n?"<"+e+">"+n.replace(/(?:[+-]|\d+\.) +(.*)\n?(([ \t].*\n?)*)/g,function(n,e,r){return"<li>"+g(e+"\n"+(t=r||"").replace(new RegExp("^"+(t.match(/^\s+/)||"")[0],"gm"),"").replace(o,c))+"</li>";var t})+"</"+e+">":""}function e(r,t,u,c){return function(n,e){return n=n.replace(t,u),l(r,c?c(n):n)}}function t(n,u){return f(n,[/<!--((.|\n)*?)-->/g,"\x3c!--$1--\x3e",/^("""|```)(.*)\n((.*\n)*?)\1/gm,function(n,e,r,t){return'"""'===e?l("div",p(t,u),{class:r}):u&&u.preCode?l("pre",l("code",a(t),{class:r})):l("pre",a(t),{class:r})},/(^>.*\n?)+/gm,e("blockquote",/^> ?(.*)$/gm,"$1",r),/((^|\n)\|.+)+/g,e("table",/^.*(\n\|---.*?)?$/gm,function(n,t){return e("tr",/\|(-?)([^|]*)\1(\|$)?/gm,function(n,e,r){return l(e||t?"th":"td",g(r))})(n.slice(0,n.length-(t||"").length))}),o,c,/#\[([^\]]+?)]/g,'<a name="$1"></a>',/^(#+) +(.*)(?:$)/gm,function(n,e,r){return l("h"+e.length,g(r))},/^(===+|---+)(?=\s*$)/gm,"<hr>"],p,u)}var i=this,a=function(n){return n?n.replace(/"/g,"&quot;").replace(/</g,"&lt;").replace(/>/g,"&gt;"):""},o=/(?:(^|\n)([+-]|\d+\.) +(.*(\n[ \t]+.*)*))+/g,g=function c(n,i){var o=[];return n=(n||"").trim().replace(/`([^`]*)`/g,function(n,e){return"\\"+o.push(l("code",a(e)))}).replace(/[!&]?\[([!&]?\[.*?\)|[^\]]*?)]\((.*?)( .*?)?\)|(\w+:\/\/[$\-.+!*'()/,\w]+)/g,function(n,e,r,t,u){return u?i?n:"\\"+o.push(l("a",u,{href:u})):"&"==n[0]?(e=e.match(/^(.+),(.+),([^ \]]+)( ?.+?)?$/),"\\"+o.push(l("iframe","",{width:e[1],height:e[2],frameborder:e[3],class:e[4],src:r,title:t}))):"\\"+o.push("!"==n[0]?l("img","",{src:r,alt:e,title:t}):l("a",c(e,1),{href:r,title:t}))}),n=function r(n){return n.replace(/\\(\d+)/g,function(n,e){return r(o[Number.parseInt(e)-1])})}(i?n:r(n))},r=function t(n){return f(n,[/([*_]{1,3})((.|\n)+?)\1/g,function(n,e,r){return e=e.length,r=t(r),1<e&&(r=l("strong",r)),e%2&&(r=l("em",r)),r},/(~{1,3})((.|\n)+?)\1/g,function(n,e,r){return l([,"u","s","del"][e.length],t(r))},/  \n|\n  /g,"<br>"],t)},f=function(n,e,r,t){for(var u,c=0;c<e.length;){if(u=e[c++].exec(n))return r(n.slice(0,u.index),t)+("string"==typeof e[c]?e[c].replace(/\$(\d)/g,function(n,e){return u[e]}):e[c].apply(i,u))+r(n.slice(u.index+u[0].length),t);c++}return n},p=function(n,e){n=n.replace(/[\r\v\b\f]/g,"").replace(/\\./g,function(n){return"&#"+n.charCodeAt(1)+";"});var r=t(n,e);return r!==n||r.match(/^[\s\n]*$/i)||(r=g(r).replace(/((.|\n)+?)(\n\n+|$)/g,function(n,e){return l("p",e)})),r.replace(/&#(\d+);/g,function(n,e){return String.fromCharCode(parseInt(e))})};return{parse:p,block:t,inline:r,inlineBlock:g}}();

	const PREVIEW_TYPES = /^image\/(png|webp|svg|jpeg|jpg|gif|png)|^application\/pdf|^text\/|^audio\/|^video\/|application\/x-empty/;

	const _ = key => typeof lang_strings != 'undefined' && key in lang_strings ? lang_strings[key] : key;

	const rename_button = `<input class="rename" type="button" value="${_('Rename')}" />`;
	const delete_button = `<input class="delete" type="button" value="${_('Delete')}" />`;

	const edit_button = `<input class="edit" type="button" value="${_('Edit')}" />`;

	const mkdir_dialog = `<input type="text" name="mkdir" placeholder="${_('Directory name')}" />`;
	const mkfile_dialog = `<input type="text" name="mkfile" placeholder="${_('File name')}" />`;
	const rename_dialog = `<input type="text" name="rename" placeholder="${_('New file name')}" />`;
	const paste_upload_dialog = `<h3>Upload this file?</h3><input type="text" name="paste_name" placeholder="${_('New file name')}" />`;
	const edit_dialog = `<textarea name="edit" cols="70" rows="30"></textarea>`;
	const markdown_dialog = `<div id="mdp"><textarea name="edit" cols="70" rows="30"></textarea><div id="md"></div></div>`;
	const delete_dialog = `<h3>${_('Confirm delete?')}</h3>`;
	const wopi_dialog = `<iframe id="wopi_frame" name="wopi_frame" allowfullscreen="true" allow="autoplay camera microphone display-capture"
			sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-top-navigation allow-popups-to-escape-sandbox allow-downloads allow-modals">
		</iframe>`;

	const dialog_tpl = `<dialog open><p class="close"><input type="button" value="&#x2716; ${_('Close')}" class="close" /></p><form><div>%s</div>%b</form></dialog>`;

	const html_tpl = `<!DOCTYPE html><html>
		<head><title>Files</title><link rel="stylesheet" type="text/css" href="${css_url}" /></head>
		<body><main></main><div class="bg"></div></body></html>`;

	const body_tpl = `<h1>%title%</h1>
		<div class="upload">
			<select class="sortorder btn">
				<option value="name">${_('Sort by name')}</option>
				<option value="date">${_('Sort by date')}</option>
				<option value="size">${_('Sort by size')}</option>
			</select>
			<input type="button" class="download_all" value="${_('Download all files')}" />
		</div>
		<table>%table%</table>`;

	const create_buttons = `<input class="mkdir" type="button" value="${_('New directory')}" />
			<input type="file" style="display: none;" />
			<input class="mkfile" type="button" value="${_('New text file')}" />
			<input class="uploadfile" type="button" value="${_('Upload file')}" />`;

	const dir_row_tpl = `<tr data-permissions="%permissions%"><td class="thumb"><span class="icon dir"><b>%icon%</b></span></td><th colspan="2"><a href="%uri%">%name%</a></th><td>%modified%</td><td class="buttons"><div></div></td></tr>`;
	const file_row_tpl = `<tr data-permissions="%permissions%" data-mime="%mime%" data-size="%size%"><td class="thumb"><span class="icon %icon%"><b>%icon%</b></span></td><th><a href="%uri%">%name%</a></th><td class="size">%size_bytes%</td><td>%modified%</td><td class="buttons"><div><a href="%uri%" download class="btn">${_('Download')}</a></div></td></tr>`;

	const propfind_tpl = '<'+ `?xml version="1.0" encoding="UTF-8"?>
		<D:propfind xmlns:D="DAV:" xmlns:oc="http://owncloud.org/ns">
			<D:prop>
				<D:getlastmodified/><D:getcontenttype/><D:getcontentlength/><D:resourcetype/><D:displayname/><oc:permissions/>
			</D:prop>
		</D:propfind>`;

	const wopi_propfind_tpl = '<' + `?xml version="1.0" encoding="UTF-8"?>
		<D:propfind xmlns:D="DAV:" xmlns:W="https://interoperability.blob.core.windows.net/files/MS-WOPI/">
			<D:prop>
				<W:file-url/><W:token/><W:token-ttl/>
			</D:prop>
		</D:propfind>`;

	const html = (unsafe) => {
		return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
	};

	const reqXML = (method, url, body, headers) => {
		return req(method, url, body, headers).then((r) => {
				if (!r.ok) {
					throw new Error(r.status + ' ' + r.statusText);
				}
				return r.text();
			}).then(str => new window.DOMParser().parseFromString(str, "text/xml"));
	};

	const reqAndReload = (method, url, body, headers) => {
		animateLoading();
		req(method, url, body, headers).then(r => {
			stopLoading();
			if (!r.ok) {
				return r.text().then(t => {
					var message;
					if (a = t.match(/<((?:\w+:)?message)>(.*)<\/\1>/)) {
						message = "\n" + a[2];
					}

					throw new Error(r.status + ' ' + r.statusText + message); });
			}
			reloadListing();
		}).catch(e => {
			console.error(e);
			alert(e);
		});
		return false;
	};

	const req = (method, url, body, headers) => {
		if (!headers) {
			headers = {};
		}

		if (auth_header) {
			headers.Authorization = auth_header;
		}

		return fetch(url, {method, body, headers});
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

	const wopi_init = async () => {
		try {
			var d = await reqXML('GET', wopi_discovery_url);
		}
		catch (e) {
			reloadListing();
			return;
		}

		d.querySelectorAll('app').forEach(app => {
			var mime = (a = app.getAttribute('name').match(/^.*\/.*$/)) ? a[0] : null;
			wopi_mimes[mime] = {};

			app.querySelectorAll('action').forEach(action => {
				var ext = action.getAttribute('ext').toUpperCase();
				var url = action.getAttribute('urlsrc').replace(/<[^>]*&>/g, '');
				var name = action.getAttribute('name');

				if (mime) {
					wopi_mimes[mime][name] = url;
				}
				else {
					if (!wopi_extensions.hasOwnProperty(ext)) {
						wopi_extensions[ext] = {};
					}

					wopi_extensions[ext][name] = url;
				}
			});
		});

		reloadListing();
	};

	const wopi_getEditURL = (name, mime) => {
		var file_ext = name.replace(/^.*\.(\w+)$/, '$1').toUpperCase();

		if (wopi_mimes.hasOwnProperty(mime) && wopi_mimes[mime].hasOwnProperty('edit')) {
			return wopi_mimes[mime].edit;
		}
		else if (wopi_extensions.hasOwnProperty(file_ext) && wopi_extensions[file_ext].hasOwnProperty('edit')) {
			return wopi_extensions[file_ext].edit;
		}

		return null;
	};

	const wopi_getViewURL = (name, mime) => {
		var file_ext = name.replace(/^.*\.(\w+)$/, '$1').toUpperCase();

		if (wopi_mimes.hasOwnProperty(mime) && wopi_mimes[mime].hasOwnProperty('view')) {
			return wopi_mimes[mime].view;
		}
		else if (wopi_extensions.hasOwnProperty(file_ext) && wopi_extensions[file_ext].hasOwnProperty('view')) {
			return wopi_extensions[file_ext].view;
		}

		return wopi_getEditURL(name, mime);
	};

	const wopi_open = async (document_url, wopi_url) => {
		var properties = await reqXML('PROPFIND', document_url, wopi_propfind_tpl, {'Depth': '0'});
		var src = (a = properties.querySelector('file-url')) ? a.textContent : null;
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

	const template = (tpl, params) => {
		return tpl.replace(/%(\w+)%/g, (a, b) => {
			return params[b];
		});
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

	const download_all = async () => {
		for (var i = 0; i < items.length; i++) {
			var item = items[i];
			if (item.is_dir) {
				continue;
			}

			await download(item.name, item.size, item.uri)
		}
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
		else {
			openDialog(`<iframe src="${url}" />`, false);
		}

		$('dialog').className = 'preview';
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

		return date.toLocaleString();
	};

	const openListing = (uri, push) => {
		closeDialog();

		reqXML('PROPFIND', uri, propfind_tpl, {'Depth': 1}).then((xml) => {
			buildListing(uri, xml)
			current_url = uri;
			changeURL(uri, push);
		}).catch((e) => {
			console.error(e);
			alert(e);
		});
	};

	const reloadListing = () => {
		stopLoading();
		openListing(current_url, false);
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
				openListing(url, false);
			});
		}
		catch (e) {
			// If using a HTML page on another origin
			location.hash = uri;
		}
	};

	const animateLoading = () => {
		document.body.classList.add('loading');
	};

	const stopLoading = () => {
		document.body.classList.remove('loading');
	};

	const buildListing = (uri, xml) => {
		uri = normalizeURL(uri);

		items = [[], []];
		var title = null;
		var root_permissions = null;

		xml.querySelectorAll('response').forEach((node) => {
			var item_uri = normalizeURL(node.querySelector('href').textContent);
			var props = null;

			node.querySelectorAll('propstat').forEach((propstat) => {
				if (propstat.querySelector('status').textContent.match(/200/)) {
					props = propstat;
				}
			});

			// This item didn't return any properties, everything is 404?
			if (!props) {
				console.error('Cannot find properties for: ' + item_uri);
				return;
			}

			var name = item_uri.replace(/\/$/, '').split('/').pop();
			name = decodeURIComponent(name);

			var permissions = (prop = node.querySelector('permissions')) ? prop.textContent : null;

			if (item_uri == uri) {
				title = name;
				root_permissions = permissions;
				return;
			}

			var is_dir = node.querySelector('resourcetype collection') ? true : false;
			var index = sort_order == 'name' && is_dir ? 0 : 1;

			items[index].push({
				'uri': item_uri,
				'name': name,
				'size': !is_dir && (prop = node.querySelector('getcontentlength')) ? parseInt(prop.textContent, 10) : null,
				'mime': !is_dir && (prop = node.querySelector('getcontenttype')) ? prop.textContent : null,
				'modified': (prop = node.querySelector('getlastmodified')) ? new Date(prop.textContent) : null,
				'is_dir': is_dir,
				'permissions': permissions,
			});
		});

		if (sort_order == 'name') {
			items[0].sort((a, b) => a.name.localeCompare(b.name));
		}

		items[1].sort((a, b) => {
			if (sort_order == 'date') {
				return b.modified - a.modified;
			}
			else if (sort_order == 'size') {
				return b.size - a.size;
			}
			else {
				return a.name.localeCompare(b.name);
			}
		});

		if (sort_order == 'name') {
			// Sort with directories first
			items = items[0].concat(items[1]);
		}
		else {
			items = items[1];
		}


		var table = '';
		var parent = uri.replace(/\/+$/, '').split('/').slice(0, -1).join('/') + '/';

		if (parent.length >= base_url.length) {
			table += template(dir_row_tpl, {'name': _('Back'), 'uri': parent, 'icon': '&#x21B2;'});
		}
		else {
			title = 'My files';
		}

		items.forEach(item => {
			// Don't include files we cannot read
			if (item.permissions !== null && item.permissions.indexOf('G') == -1) {
				console.error('OC permissions deny read access to this file: ' + item.name, 'Permissions: ', item.permissions);
				return;
			}

			var row = item.is_dir ? dir_row_tpl : file_row_tpl;
			item.size_bytes = item.size !== null ? formatBytes(item.size).replace(/ /g, '&nbsp;') : null;
			item.icon = item.is_dir ? '&#x1F4C1;' : (item.uri.indexOf('.') > 0 ? item.uri.replace(/^.*\.(\w+)$/, '$1').toUpperCase() : '');
			item.modified = item.modified !== null ? formatDate(item.modified) : null;
			item.name = html(item.name);
			table += template(row, item);
		});

		document.title = title;
		document.querySelector('main').innerHTML = template(body_tpl, {'title': html(document.title), 'base_url': base_url, 'table': table});

		var select = $('.sortorder');
		select.value = sort_order;
		select.onchange = () => {
			sort_order = select.value;
			window.localStorage.setItem('sort_order', sort_order);
			reloadListing();
		};

		if (!items.length) {
			$('.download_all').disabled = true;
		}
		else {
			$('.download_all').onclick = download_all;
		}

		if (!root_permissions || root_permissions.indexOf('C') != -1 || root_permissions.indexOf('K') != -1) {
			$('.upload').insertAdjacentHTML('afterbegin', create_buttons);

			$('.mkdir').onclick = () => {
				openDialog(mkdir_dialog);
				document.forms[0].onsubmit = () => {
					var name = $('input[name=mkdir]').value;

					if (!name) return false;

					name = encodeURIComponent(name);

					req('MKCOL', current_url + name).then(() => openListing(current_url + name + '/'));
					return false;
				};
			};

			$('.mkfile').onclick = () => {
				openDialog(mkfile_dialog);
				var t = $('input[name=mkfile]');
				t.value = '.md';
				t.focus();
				t.selectionStart = t.selectionEnd = 0;
				document.forms[0].onsubmit = () => {
					var name = t.value;

					if (!name) return false;

					name = encodeURIComponent(name);

					return reqAndReload('PUT', current_url + name, '');
				};
			};

			var fi = $('input[type=file]');

			$('.uploadfile').onclick = () => fi.click();

			fi.onchange = () => {
				if (!fi.files.length) return;

				var body = new Blob(fi.files);
				var name = fi.files[0].name;

				name = encodeURIComponent(name);

				return reqAndReload('PUT', current_url + name, body);
			};
		}

		Array.from($('table').rows).forEach((tr) => {
			var $$ = (a) => tr.querySelector(a);
			var file_url = $$('a').href;
			var file_name = $$('a').innerText;
			var dir = $$('[colspan]');
			var mime = !dir ? tr.getAttribute('data-mime') : 'dir';
			var buttons = $$('td.buttons div');
			var permissions = tr.getAttribute('data-permissions');
			var size = tr.getAttribute('data-size');

			if (permissions == 'null') {
				permissions = null;
			}

			if (dir) {
				$$('a').onclick = () => {
					openListing(file_url, true);
					return false;
				};
			}

			// For back link
			if (dir && $$('a').getAttribute('href').length < uri.length) {
				dir.setAttribute('colspan', 4);
				tr.querySelector('td:last-child').remove();
				tr.querySelector('td:last-child').remove();
				return;
			}

			// This is to get around CORS when not on the same domain
			if (user && password && (a = tr.querySelector('a[download]'))) {
				a.onclick = () => {
					download(file_name, size, url);
					return false;
				};
			}

			// Add rename/delete buttons
			if (!permissions || permissions.indexOf('NV') != -1) {
				buttons.insertAdjacentHTML('afterbegin', rename_button);

				$$('.rename').onclick = () => {
					openDialog(rename_dialog);
					let t = $('input[name=rename]');
					t.value = file_name;
					t.focus();
					t.selectionStart = 0;
					t.selectionEnd = file_name.lastIndexOf('.');
					document.forms[0].onsubmit = () => {
						var name = t.value;

						if (!name) return false;

						name = encodeURIComponent(name);
						name = name.replace(/%2F/, '/');

						var dest = current_url + name;
						dest = normalizeURL(dest);

						return reqAndReload('MOVE', file_url, '', {'Destination': dest});
					};
				};

			}

			if (!permissions || permissions.indexOf('D') != -1) {
				buttons.insertAdjacentHTML('afterbegin', delete_button);

				$$('.delete').onclick = (e) => {
					openDialog(delete_dialog);
					document.forms[0].onsubmit = () => {
						return reqAndReload('DELETE', file_url);
					};
				};
			}

			var view_url, edit_url;

			// Don't preview PDF in mobile
			if (mime.match(PREVIEW_TYPES)
				&& !(mime == 'application/pdf' && window.navigator.userAgent.match(/Mobi|Tablet|Android|iPad|iPhone/))) {
				$$('a').onclick = () => {
					if (file_url.match(/\.md$/)) {
						openDialog('<div class="md_preview"></div>', false);
						$('dialog').className = 'preview';
						req('GET', file_url).then(r => r.text()).then(t => {
							$('.md_preview').innerHTML = microdown.parse(html(t));
						});
						return false;
					}

					if (user && password) {
						(async () => { preview(mime, await get_url(file_url)); })();
					}
					else {
						preview(mime, file_url);
					}

					return false;
				};
			}
			else if (view_url = wopi_getViewURL(file_url, mime)) {
				$$('.icon').classList.add('document');
				$$('a').onclick = () => { wopi_open(file_url, view_url); return false; };
			}
			else if (user && password && !dir) {
				$$('a').onclick = () => { download(file_name, size, file_url); return false; };
			}
			else {
				$$('a').download = file_name;
			}

			if (!permissions || permissions.indexOf('W') != -1) {
				if (mime.match(/^text\/|application\/x-empty/)) {
					buttons.insertAdjacentHTML('beforeend', edit_button);

					$$('.edit').onclick = (e) => {
						req('GET', file_url).then((r) => r.text().then((t) => {
							let md = file_url.match(/\.md$/);
							openDialog(md ? markdown_dialog : edit_dialog);
							var txt = $('textarea[name=edit]');
							txt.value = t;

							// Markdown editor
							if (md) {
								let pre = $('#md');

								txt.oninput = () => {
									pre.innerHTML = microdown.parse(html(txt.value));
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

								return reqAndReload('PUT', file_url, content);
							};
						}));
					};
				}
				else if (edit_url = wopi_getEditURL(file_url, mime)) {
					buttons.insertAdjacentHTML('beforeend', edit_button);

					$$('.icon').classList.add('document');
					$$('.edit').onclick = () => { wopi_open(file_url, edit_url); return false; };
				}
			}
		});
	};

	var items = [[], []];
	var current_xhr = null;
	var current_url = url;
	var base_url = url;
	const user = options.user || null;
	const password = options.password || null;
	var auth_header = (user && password) ? 'Basic ' + btoa(user + ':' + password) : null;

	if (location.pathname.indexOf(base_url) === 0) {
		current_url = location.pathname;
	}

	if (!base_url.match(/^https?:/)) {
		base_url = location.href.replace(/^(https?:\/\/[^\/]+\/).*$/, '$1') + base_url.replace(/^\/+/, '');
	}

	var evt, paste_upload, popstate_evt, temp_object_url;
	var sort_order = window.localStorage.getItem('sort_order') || 'name';
	var wopi_mimes = {}, wopi_extensions = {};

	const wopi_discovery_url = options.wopi_discovery_url || null;

	document.querySelector('html').innerHTML = html_tpl;

	// Wait for WOPI discovery before creating the list
	if (wopi_discovery_url) {
		wopi_init();
	} else {
		reloadListing();
	}

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

		const files = [...e.dataTransfer.items].map(item => item.getAsFile());

		if (!files.length) return;

		animateLoading();

		(async () => {
			for (var i = 0; i < files.length; i++) {
				var f = files[i]
				await req('PUT', current_url + encodeURIComponent(f.name), f);
			}

			window.setTimeout(() => {
				stopLoading();
				reloadListing();
			}, 500);
		})();
	});
};

if (url = document.querySelector('html').getAttribute('data-webdav-url')) {
	WebDAVNavigator(url, {
		'wopi_discovery_url': document.querySelector('html').getAttribute('data-wopi-discovery-url'),
	});
}
