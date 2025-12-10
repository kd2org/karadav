{{Manage users} assign="title"}
{include file="_header.tpl" current="users"}

{if !$ldap}
	<p class="actions">
		<a href="new.php" class="btn sm">{{Create new user}}</a>
	</p>
{/if}

<table>
	<thead>
		<tr>
			<td></td>
			<th>{{User}}</th>
			<td>{{Quota}}</td>
			<td>{{Group}}</td>
			<td></td>
		</tr>
	</thead>
	<tbody>

	{foreach from=$list item="user"}
	<?php $quota = $users->quota($user); ?>

		<tr>
			<td><img src="{$user.avatar_url}" alt="" /></td>
			<th>{$user.login}</th>
			<td>
				{{%used used out of %total} used=$quota.used|format_bytes total=$quota.total|format_bytes}<br />
				<progress max="{$quota.total}" value="{$quota.used}"></progress>
			</td>
			<td>{if $user.is_admin}{{Admin}}{/if}</td>
			<td>
				<a href="edit.php?id={$user.id}" class="btn sm">{{Edit}}</a>
				<a href="delete.php?id={$user.id}" class="btn sm">{{Delete}}</a>
		</td>
		</tr>

	{/foreach}


</tbody>
</table>

{include file="_footer.tpl"}