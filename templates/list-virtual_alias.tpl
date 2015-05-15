{assign var="table" value='alias'}
{assign var="struct" value=$alias_data.struct}
{assign var="msg" value=$alias_data.msg}
{assign var="id_field" value=$msg.id_field}
{assign var="formconf" value=$alias_data.formconf}
{assign var="items" value=$tAlias}
{assign var="RAW_items" value=$RAW_tAlias}

{include 'list.tpl'}

