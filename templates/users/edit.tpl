{{Edit user} assign="title"}
{include file="_header.tpl" current="users"}

<form method="post" action="">
{form_csrf}
	<?php $quota = $user->quota > 0 ? round($user->quota / 1024 / 1024) : $user->quota; ?>

	{if $form_error}
		<p class="error">{$form_error}</p>
	{/if}

	<fieldset>
		<legend>{{Edit user}}</legend>
		<dl>

	{if !$ldap}
			<dt><label for="f_login">{{Login}}</label></dt>
			<dd><input type="text" pattern="[a-z0-9_]+" name="login" id="f_login" value="{$user.login}" required /></dd>
			<dt><label for="f_password">{{Password}}</label></dt>
			<dd><input type="password" name="password" id="f_password" /></dd>
			<dd>{{(Leave empty if you don't want to change the password.)}}</dd>
			<dt><label for="f_is_admin">{{Status}}</label></dt>
			<dd><label><input type="checkbox" name="is_admin" id="f_is_admin" {if $user.is_admin}checked="checked"{/if} /> {{Admin}}</label></dd>
	{/if}

			<dt><label for="f_quota">{{Quota}}</label></dt>
			<dd><input type="number" name="quota" step="1" min="-1" value="{$quota}" required="required" size="6" /> {{(in MB)}}</dd>
			<dd>{{Set to 0 to disable upload.}}</dd>
			<dd>{{Use -1 to allow using all the available space on disk.}}</dd>
			<dd><input type="submit" name="save" value="{{Save}}" /></dd>
		</dl>
	</fieldset>
</form>

{include file="_footer.tpl"}
