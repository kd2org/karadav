{{Delete user} assign="title"}
{include file="_header.tpl" current="users"}

<form method="post" action="">
{form_csrf}
	{if $form_error}
		<p class="error">{$form_error}</p>
	{/if}

	<fieldset>
		<legend>{{Delete user}}</legend>
		<h2 class="alert">{{Do you want to delete the user "%name" and all their files?} name=$user.login}</h2>
		<dd><input type="submit" name="delete" value="{{Yes, delete}}" /></dd>
	</fieldset>
</form>

{include file="_footer.tpl"}
