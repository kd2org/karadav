<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0, target-densitydpi=device-dpi" />
	<title>{$title}</title>
	<link rel="stylesheet" type="text/css" href="{$www_url}ui.css?2025" />
	<link rel="icon" type="image/svg+xml" href="{$www_url}logo.svg" />
</head>
<body>
<nav id="skip">
	<a href="#content">{{Go to content}}</a>
	<a href="#nav">{{Go to navigation}}</a>
</nav>
<div id="all">
<header id="nav">
	<h1><img src="{$www_url}logo.svg" alt="KaraDAV" /></h1>
	<nav>
		<ul>
		{if $logged_user}
			<li class="index{if $current === 'index'} current{/if}"><a href="{$www_url}"><img src="{$logged_user.avatar_url}" alt="" /> <span>{{My account}}</span></a></li>
			<li class="files{if $current === $logged_user.dav_url} current{/if}"><a href="{$www_url}frame.php?url={$logged_user.dav_url|rawurlencode}"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M15 3v4a1 1 0 0 0 1 1h4"/><path d="M18 17h-7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4l5 5v7a2 2 0 0 1-2 2"/><path d="M16 17v2a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2h2"/></g></svg> <span>{{My files}}</span></a></li>
			{if $logged_user.is_admin}
			<li class="users{if $current === 'users'} current{/if}"><a href="{$www_url}users/"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 7a4 4 0 1 0 8 0a4 4 0 1 0-8 0M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2m1-17.87a4 4 0 0 1 0 7.75M21 21v-2a4 4 0 0 0-3-3.85"/></svg> <span>{{Manage users}}</span></a></li>
			{/if}
			<li class="logout"><a href="{$www_url}?logout"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M14 8V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2v-2"/><path d="M9 12h12l-3-3m0 6l3-3"/></g></svg> <span>{{Logout}}</span></a></li>
		{else}
			<li class="current login"><a href="{$www_url}login.php"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M15 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2v-2"/><path d="M21 12H8l3-3m0 6l-3-3"/></g></svg> <span>{{Login}}</span></a></li>
		{/if}
		</ul>
	</nav>
	<footer>
		Powered by <a href="https://fossil.kd2.org/karadav/" target="_blank">KaraDAV</a>
	</footer>
</header>
<main id="content">
