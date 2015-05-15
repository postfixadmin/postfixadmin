{*** Domain Aliases ***}

{assign var="table" value='aliasdomain'}
{assign var="struct" value=$aliasdomain_data.struct}
{assign var="msg" value=$aliasdomain_data.msg}
{assign var="id_field" value=$msg.id_field}
{assign var="formconf" value=$aliasdomain_data.formconf}
{assign var="items" value=$tAliasDomains}
{include 'list.tpl'}
