// Microdown
// https://github.com/commit-intl/micro-down
const microdown=function(){function l(n,e,r){return"<"+n+(r?" "+Object.keys(r).map(function(n){return r[n]?n+'="'+(a(r[n])||"")+'"':""}).join(" "):"")+">"+e+"</"+n+">"}function c(n,e){return e=n.match(/^[+-]/m)?"ul":"ol",n?"<"+e+">"+n.replace(/(?:[+-]|\d+\.) +(.*)\n?(([ \t].*\n?)*)/g,function(n,e,r){return"<li>"+g(e+"\n"+(t=r||"").replace(new RegExp("^"+(t.match(/^\s+/)||"")[0],"gm"),"").replace(o,c))+"</li>";var t})+"</"+e+">":""}function e(r,t,u,c){return function(n,e){return n=n.replace(t,u),l(r,c?c(n):n)}}function t(n,u){return f(n,[/<!--((.|\n)*?)-->/g,"\x3c!--$1--\x3e",/^("""|```)(.*)\n((.*\n)*?)\1/gm,function(n,e,r,t){return'"""'===e?l("div",p(t,u),{class:r}):u&&u.preCode?l("pre",l("code",a(t),{class:r})):l("pre",a(t),{class:r})},/(^>.*\n?)+/gm,e("blockquote",/^> ?(.*)$/gm,"$1",r),/((^|\n)\|.+)+/g,e("table",/^.*(\n\|---.*?)?$/gm,function(n,t){return e("tr",/\|(-?)([^|]*)\1(\|$)?/gm,function(n,e,r){return l(e||t?"th":"td",g(r))})(n.slice(0,n.length-(t||"").length))}),o,c,/#\[([^\]]+?)]/g,'<a name="$1"></a>',/^(#+) +(.*)(?:$)/gm,function(n,e,r){return l("h"+e.length,g(r))},/^(===+|---+)(?=\s*$)/gm,"<hr>"],p,u)}var i=this,a=function(n){return n?n.replace(/"/g,"&quot;").replace(/</g,"&lt;").replace(/>/g,"&gt;"):""},o=/(?:(^|\n)([+-]|\d+\.) +(.*(\n[ \t]+.*)*))+/g,g=function c(n,i){var o=[];return n=(n||"").trim().replace(/`([^`]*)`/g,function(n,e){return"\\"+o.push(l("code",a(e)))}).replace(/[!&]?\[([!&]?\[.*?\)|[^\]]*?)]\((.*?)( .*?)?\)|(\w+:\/\/[$\-.+!*'()/,\w]+)/g,function(n,e,r,t,u){return u?i?n:"\\"+o.push(l("a",u,{href:u})):"&"==n[0]?(e=e.match(/^(.+),(.+),([^ \]]+)( ?.+?)?$/),"\\"+o.push(l("iframe","",{width:e[1],height:e[2],frameborder:e[3],class:e[4],src:r,title:t}))):"\\"+o.push("!"==n[0]?l("img","",{src:r,alt:e,title:t}):l("a",c(e,1),{href:r,title:t}))}),n=function r(n){return n.replace(/\\(\d+)/g,function(n,e){return r(o[Number.parseInt(e)-1])})}(i?n:r(n))},r=function t(n){return f(n,[/([*_]{1,3})((.|\n)+?)\1/g,function(n,e,r){return e=e.length,r=t(r),1<e&&(r=l("strong",r)),e%2&&(r=l("em",r)),r},/(~{1,3})((.|\n)+?)\1/g,function(n,e,r){return l([,"u","s","del"][e.length],t(r))},/  \n|\n  /g,"<br>"],t)},f=function(n,e,r,t){for(var u,c=0;c<e.length;){if(u=e[c++].exec(n))return r(n.slice(0,u.index),t)+("string"==typeof e[c]?e[c].replace(/\$(\d)/g,function(n,e){return u[e]}):e[c].apply(i,u))+r(n.slice(u.index+u[0].length),t);c++}return n},p=function(n,e){n=n.replace(/[\r\v\b\f]/g,"").replace(/\\./g,function(n){return"&#"+n.charCodeAt(1)+";"});var r=t(n,e);return r!==n||r.match(/^[\s\n]*$/i)||(r=g(r).replace(/((.|\n)+?)(\n\n+|$)/g,function(n,e){return l("p",e)})),r.replace(/&#(\d+);/g,function(n,e){return String.fromCharCode(parseInt(e))})};return{parse:p,block:t,inline:r,inlineBlock:g}}();

function html(unsafe)
{
    return unsafe
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}

const url = location.pathname;

const PREVIEW_TYPES = /^image\/(png|webp|svg|jpeg|jpg|gif|png)|^application\/pdf|^text\/|^audio\/|^video\//;

const upload_form = `<div class="a">
	<input class="mkdir" type="button" value="New directory" />
	<input type="file" style="display: none;" />
	<input class="mkfile" type="button" value="New text file" />
	<input class="upload" type="button" value="Upload file" />
	</div>`;

const file_buttons = `<td><input class="rename" type="button" value="Rename" />
	<input class="delete" type="button" value="Delete" /></td>`;

const edit_button = `<input class="edit" type="button" value="Edit" />`;

const mkdir_dialog = `<input type="text" name="mkdir" placeholder="Directory name" />`;
const mkfile_dialog = `<input type="text" name="mkfile" placeholder="File name" />`;
const rename_dialog = `<input type="text" name="rename" placeholder="New file name" />`;
const edit_dialog = `<textarea name="edit" cols="70" rows="30"></textarea>`;
const markdown_dialog = `<div id="mdp"><textarea name="edit" cols="70" rows="30"></textarea><div id="md"></div></div>`;
const delete_dialog = `<h3>Confirm delete ?</h3>`;

const dialog_tpl = `<dialog open><p class="close"><input type="button" value="&#x2716; Close" class="close" /></p><form><div>%s</div>%b</form></dialog>`;

const req = (method, url, body, headers) => {
	fetch(url, {method,	body, headers}).then((r) => location.reload());
	return false;
};

var evt, evt2;

const dialog = (hash, html, ok_btn = true) => {
	location.hash = '#' + hash;
	var tpl = dialog_tpl.replace(/%b/, ok_btn ? '<p><input type="submit" value="OK" /></p>' : '');
	$('body').insertAdjacentHTML('beforeend', tpl.replace(/%s/, html));
	$('.close input').onclick = close;
	evt = window.addEventListener('keyup', (e) => {
		if (e.key != 'Escape') return;
		e.preventDefault();
		close();
		return false;
	});
	evt2 = window.addEventListener('hashchange', (e) => {
		if (location.hash == '') {
			close();
		}
	});
	if (a = $('dialog form input, dialog form textarea')) a.focus();
};

const close = () => {
	if (!$('dialog')) return;
	$('dialog').remove();
	window.removeEventListener('keyup', evt);
	evt = null;
	window.removeEventListener('hashchange', evt2);
	evt2 = null;
	location.hash = '';
};

const $ = (a) => document.querySelector(a);

$('h1').insertAdjacentHTML('afterend', upload_form);

Array.from($('table').rows).forEach((tr) => {
	var $$ = (a) => tr.querySelector(a);
	var file_url = $$('a').href;
	var file_name = $$('a').innerText;
	var dir = $$('[colspan]');
	var type = !dir ? $$('td:nth-child(3)').innerText : null;

	if (dir && url.match('..')) {
		dir.setAttribute('colspan', 4);
		return;
	}

	tr.insertAdjacentHTML('beforeend', file_buttons);

	if (type.match(PREVIEW_TYPES)) {
		$$('a').onclick = () => {
			if (file_url.match(/\.md$/)) {
				dialog(file_name, '<div class="md_preview"></div>', false);
				fetch(file_url).then((r) => r.text().then((t) => {
					$('.md_preview').innerHTML = microdown.parse(html(t));
				}));
			}
			else if (type.match(/^image\//)) {
				dialog(file_name, `<img src="${file_url}" />`, false);
			}
			else {
				dialog(file_name, `<iframe src="${file_url}" />`, false);
			}
			$('dialog').className = 'preview';
			return false;
		};
	}

	if (type.match(/^text\/|application\/x-empty/)) {
		$$('td:nth-child(5)').insertAdjacentHTML('beforeend', edit_button);

		$$('.edit').onclick = (e) => {
			fetch(file_url).then((r) => r.text().then((t) => {
				let md = file_url.match(/\.md$/);
				dialog(file_name, md ? markdown_dialog : edit_dialog);
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

					return req('PUT', file_url, content);
				};
			}));
		};
	}

	$$('.delete').onclick = (e) => {
		dialog('delete', delete_dialog);
		document.forms[0].onsubmit = () => {
			return req('DELETE', file_url, '');
		};
	};

	$$('.rename').onclick = () => {
		dialog('rename', rename_dialog);
		let t = $('input[name=rename]');
		t.value = file_name;
		t.select();
		document.forms[0].onsubmit = () => {
			var name = t.value;

			if (!name) return false;

			return req('MOVE', file_url, '', {'Destination': location.href + name});
		};
	};

});

$('.mkdir').onclick = () => {
	dialog('mkdir', mkdir_dialog);
	document.forms[0].onsubmit = () => {
		var name = $('input[name=mkdir]').value;

		if (!name) return false;

		return req('MKCOL', url + name);
	};
};

$('.mkfile').onclick = () => {
	dialog('mkfile', mkfile_dialog);
	document.forms[0].onsubmit = () => {
		var name = $('input[name=mkfile]').value;

		if (!name) return false;

		return req('PUT', url + name, '');
	};
};

var fi = $('input[type=file]');

$('.upload').onclick = () => fi.click();

fi.onchange = () => {
	if (!fi.files.length) return;

	var body = new Blob(fi.files);
	var name = fi.files[0].name;

	return req('PUT', url + name, body);
};
