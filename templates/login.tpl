{{Login} assign="title"}
{include file="_header.tpl"}

{if $error === -1}
	<p class="info">{{You are logged in, you can close this window or tab and go back to the app.}}</p>
{else}
	{if $error}
		<p class="error">{{Invalid login or password}}</p>
	{elseif $_GET.logout}
		<p class="info">{{You have logged out}}</p>
	{/if}

	<form method="post" action="">

	{if $app_login !== null}
		<input type="hidden" name="nc" value="{$app_login}" />
		<p class="info">
			<strong>{{An external application is trying to access your data.}}</strong><br />
			{{Please login to continue and allow access.}}
		</p>
	{/if}

	<fieldset>
		<legend>{{Sign in}}</legend>
		<dl>
			<dt><label for="f_login">{{Login}}</label></dt>
			<dd><input type="text" name="login" id="f_login" required autocapitalize="none" /></dd>
			<dt><label for="f_password">{{Password}}</label></dt>
			<dd><input type="password" name="password" id="f_password" required /></dd>
			<dd><input type="submit" value="{{Connect me}}" /></dd>
		</dl>
	</fieldset>
	{form_csrf}
	</form>
{/if}

{include file="_footer.tpl"}
