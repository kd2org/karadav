<?php

namespace KaraDAV;

require_once __DIR__ . '/_inc.php';

$users = new Users;

if (empty($_GET['nc']) && $users->current()) {
	header('Location: ' . WWW_URL);
	exit;
}

$error = 0;

if (!empty($_POST['login']) && !empty($_POST['password']) && csrf_check()) {
	if ($users->login($_POST['login'], $_POST['password'])) {
		$url = null;

		if (!empty($_POST['nc']) && $_POST['nc'] == 'redirect') {
			$url = $users->appSessionCreateAndGetRedirectURL();
		}
		elseif (!empty($_POST['nc'])) {
			$users->appSessionCreate($_POST['nc']);
			$error = -1;
		}
		else {
			$url = './';
		}

		if ($url) {
			header('Location: ' . $url);
			exit;
		}
	}
	else {
		$error = 1;
	}
}

html_head('Login');

if ($error == -1) {
	echo '<p class="info">You are logged in, you can close this window or tab and go back to the app.</p>';
	html_foot();
	exit;
}

if ($error) {
	echo '<p class="error">Invalid login or password</p>';
}

echo '
<form method="post" action="">';

if (isset($_GET['nc'])) {
	printf('<input type="hidden" name="nc" value="%s" />', htmlspecialchars($_GET['nc']));
	echo '<p class="info">An external application is trying to access your data. Please login to continue and allow access.</p>';
}

echo html_csrf();

echo '
<fieldset>
	<legend>Login</legend>
	<dl>
		<dt><label for="f_login">Login</label></dt>
		<dd><input type="text" name="login" id="f_login" required autocapitalize="none" /></dd>
		<dt><label for="f_password">Password</label></dt>
		<dd><input type="password" name="password" id="f_password" required /></dd>
		<dd><input type="submit" value="Connect me" /></dd>
	</dl>
</fieldset>
</form>
';

html_foot();
