{{My account} assign="title"}
{include file="_header.tpl" current="index"}

<h3>
	Hello, {$logged_user.login} ! <img src="{$logged_user.avatar_url}" alt="" />
</h3>

<dl>
	<dd><h3>{$percent} used, {$quota.free|format_bytes} free</h3></dd>
	<dd><progress max="{$quota.total}" value="{$quota.used}"></progress>
	<dd>Used {$quota.used|format_bytes} out of a total of {$quota.total|format_bytes}.</dd>
	<dd>
		Trash: {$quota.trash|format_bytes}.
		{if $quota.trash}
			<br /><a href="?empty_trash" class="btn sm">{{Empty trash now}}</a>
		{/if}
	</dd>
	<dt>WebDAV URL</dt>
	<dd><h3><a href="{$logged_user.dav_url}"><tt>{$logged_user.dav_url}</tt></a></h3>
	<dt>NextCloud URL</dt>
	<dd><tt>{$www_url}</tt></dd>
	<dd class="help">Use this URL to setup a NextCloud or ownCloud client to access your files.</dd>
</dl>

{include file="_footer.tpl"}
