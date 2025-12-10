{{Create a new user} assign="title"}
{include file="_header.tpl" current="users"}

<form method="post" action="">
	{form_csrf}

	{if $form_error}
		<p class="error">{$form_error}</p>
	{/if}

	<fieldset>
		<legend>{{Create a new user}}</legend>
		<dl>
			<dt><label for="f_login">{{Login}}</label></dt>
			<dd><input type="text" pattern="[a-z0-9_]+" name="login" id="f_login" required /></dd>
			<dt><label for="f_password">{{Password}}</label></dt>
			<dd><input type="password" name="password" id="f_password" required /></dd>
			<dd><input type="submit" name="create" value="{{Create}}" /></dd>
		</dl>
	</fieldset>
</form>

{include file="_footer.tpl"}
