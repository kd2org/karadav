<?php

namespace KaraDAV;

require_once __DIR__ . '/_inc.php';

$users = new Users;
$install_password = DB::getInstallPassword();

$error = $install_message = '';

if (!empty($_POST['login']) && !empty($_POST['password'])) {
	if ($users->login($_POST['login'], $_POST['password'])) {
		header('Location: /');
		exit;
	}

	$error = '<p class="error">Invalid login or password</p>';
}

if ($install_password) {
	$install_message = sprintf('<p class="info">Your default user is:<br />
		demo / %1$s<br>
		<em>(this is only visible by you and will disappear when you close your browser)</em></p>', $install_password);
}

html('Login', <<<EOF
<form method="post" action="">
{$install_message}
{$error}
<fieldset>
	<legend>Login</legend>
	<dl>
		<dt><label for="f_login">Login</label></dt>
		<dd><input type="text" name="login" id="f_login" required /></dd>
		<dt><label for="f_password">Password</label></dt>
		<dd><input type="password" name="password" id="f_password" required /></dd>
	</dl>
	<p><input type="submit" value="Submit" /></p>
</fieldset>
</form>
EOF);
