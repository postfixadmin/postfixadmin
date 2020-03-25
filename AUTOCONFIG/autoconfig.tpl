{literal}
	<link rel="stylesheet" type="text/css" href="autoconfig.css" />
	<script language="JavaScript" src="https://code.jquery.com/jquery-3.3.1.min.js" type="text/javascript"></script>
	<script language="JavaScript" src="sprintf.js" type="text/javascript"></script>
	<script language="JavaScript" src="autoconfig.js" type="text/javascript"></script>
{/literal}
<script id="host-template" type="text/template">
{include file='autoconfig-host-settings.tpl'}
</script>
<div id="postfixadmin-progress" class="waiting">
	<dt></dt>
	<dd></dd>
</div>
<div id="message"></div>
<div id="edit_form">
	<form id="autoconfig_form" name="edit-autoconfig" method="post" action=''>
		<input class="flat" type="hidden" name="token" value="{$smarty.session.PFA_token|escape:"url"}" />
		<input class="flat" type="hidden" name="config_id" value="{$form.config_id}" />
		<input type="checkbox" class="switch-input" name="enable_status" id="enable_status" value="1" {if !empty($form.enable_status) && $form.enable_status == 1}checked="checked"{/if} />
		<input type="checkbox" class="switch-input" name="documentation_status" id="documentation_status" value="1" {if !empty($form.documentation_status) && $form.documentation_status == 1}checked="checked"{/if} />
		<datalist id="defaultPorts">
			<option value="25">
			<option value="143">
			<option value="465">
			<option value="587">
			<option value="993">
			<option value="995">
			<option value="2525">
		</datalist>
		<table>
			<tr>
				<th colspan="3">{$PALANG.pAutoconfig_page_title}</th>
			</tr>
			<tr class="autoconfig_jump">
				<td class="label"><label for="autoconfig_active">{$PALANG.pAutoconfig_jump_to}:</label></td>
				<td><select name="jump_to">
					<option value="">{$PALANG.pAutoconfig_new_configuration}</option>
					{html_options options=$form.config_options selected=$form.config_id}
				</select></td>
				<td>&nbsp;</td>
			</tr>
			<tr>
				<td class="label"><label for="autoconfig_active">{$PALANG.pAutoconfig_active}:</label></td>
				<td><input type="checkbox" class="flat" name="active" id="autoconfig_active" value="1" {if !empty($form.active) && $form.active == 1}checked="checked"{/if} /></td>
				<td>&nbsp;</td>
			</tr>
			<tr>
				<td class="label"><label>{$PALANG.pAutoconfig_encoding}:</label></td>
				<td><input type="hidden" class="flat" name="encoding" value="utf-8" /><em>utf-8</em></td>
				<td>&nbsp;</td>
			</tr>
			<tr>
				<td class="label"><label>{$PALANG.pAutoconfig_provider_id}:</label></td>
				<td><input type="text" size="40" class="flat" name="provider_id" maxlength="255" value="{$form.provider_id}" placeholder="{$form.placeholder.provider_id}" /></td>
				<td>&nbsp;</td>
			</tr>
			<tr>
				<td class="label"><label>{$PALANG.pAutoconfig_provider_domain}:</label></td>
				<td><select class="flat" name="provider_domain[]" id="autoconfig_provider_domain" size="10" multiple="multiple">
					{foreach from=$form.provider_domain_options item=domain}
					<option value="{$domain}"{if in_array($domain, $form.provider_domain)} selected="selected"{elseif in_array($domain, $form.provider_domain_disabled)} disabled="true"{/if}>{$domain}</option>
					{/foreach}
				</select><br /><input type="button" id="autoconfig_toggle_select_all_domains" value="{$PALANG.pAutoconfig_toggle_select_all}" /></td>
				<td>&nbsp;</td>
			</tr>
			<tr>
				<td class="label"><label>{$PALANG.pAutoconfig_provider_name}:</label></td>
				<td><input type="text" size="40" class="flat" name="provider_name" maxlength="255" value="{$form.provider_name}" placeholder="{$form.placeholder.provider_name}" /></td>
				<td>&nbsp;</td>
			</tr>
			<tr>
				<td class="label"><label>{$PALANG.pAutoconfig_provider_short}:</label></td>
				<td><input type="text" size="40" class="flat" name="provider_short" maxlength="120" value="{$form.provider_short}" /></td>
				<td>&nbsp;</td>
			</tr>
			<!-- looping for each incoming server -->
			<tr>
				<th colspan="3">{$PALANG.pAutoconfig_incoming_server}</th>
			</tr>
			{if count($form.incoming_server) == 0}
			{assign var="server" value=['type' => 'imap'] scope="global"}
			{include file='autoconfig-host-settings.tpl' server=$server}
			{else}
			{foreach name=outer item=server from=$form.incoming_server}
			{include file='autoconfig-host-settings.tpl' server=$server}
			{/foreach}
			{/if}
			<!-- end looping for each incoming server -->
			<!-- looping for each outgoing server -->
			<tr>
				<th colspan="3">{$PALANG.pAutoconfig_outgoing_server}</th>
			</tr>
			{if count($form.outgoing_server) == 0}
			{assign var="server" value=['type' => 'smtp'] scope="global"}
			{include file='autoconfig-host-settings.tpl' server=$server}
			{else}
			{foreach name=outer item=server from=$form.outgoing_server}
			{include file='autoconfig-host-settings.tpl' server=$server}
			{/foreach}
			{/if}
			<!-- end looping for each outgoing server -->
			<tr>
				<th colspan="3">{$PALANG.pAutoconfig_enable}
					<label class="switch" for="enable_status">
						<span class="switch-label" data-on="{$PALANG.pAutoconfig_on}" data-off="{$PALANG.pAutoconfig_off}"></span>
						<span class="switch-handle"></span>
					</label>
<br />{$PALANG.pAutoconfig_not_supported} (<a href="https://developer.mozilla.org/en-US/docs/Mozilla/Thunderbird/Autoconfiguration/FileFormat/HowTo" title="Autoconfig: How to create a configuration file" target="_new">?</a>)</th>
			</tr>
			<tr>
				<td class="label"><label>{$PALANG.pAutoconfig_enable_url}:</label></td>
				<td><input type="text" size="40" class="flat" name="enable_url" maxlength="2048" value="{$form.enable.url}" /></td>
				<td>&nbsp;</td>
			</tr>
			{foreach name=support item=text from=$form.enable.instruction}
			<tr class="autoconfig-instruction">
				<td class="label"><label>{$PALANG.pAutoconfig_enable_instruction}:</label></td>
				<td><input type="hidden" name="instruction_id[]" value="{$text.id}" />
				{html_options name="instruction_lang[]" options=$language_options selected=$text.lang}<br />
				<textarea class="flat" rows="5" cols="50" name="instruction_text[]" >{$text.phrase}</textarea></td>
				<td><button class="autoconfig-command autoconfig-instruction autoconfig-locale-text-add ripple" title="{$PALANG.pAutoconfig_add_new_text}"><i class="fas fa-plus fa-2x"></i></button><button class="autoconfig-command autoconfig-instruction autoconfig-locale-text-remove ripple" title="{$PALANG.pAutoconfig_remove_text}"><i class="fas fa-minus fa-2x"></i></button></td>
			</tr>
			{/foreach}
			<tr>
				<th colspan="3">{$PALANG.pAutoconfig_documentation}
					<label class="switch" for="documentation_status">
						<span class="switch-label" data-on="{$PALANG.pAutoconfig_on}" data-off="{$PALANG.pAutoconfig_off}"></span>
						<span class="switch-handle"></span>
					</label>
				</th>
			</tr>
			<tr>
				<td class="label"><label>{$PALANG.pAutoconfig_documentation_url}:</label></td>
				<td><input type="text" size="40" class="flat" name="documentation_url" maxlength="2048" value="{$form.documentation.url}" /></td>
				<td>&nbsp;</td>
			</tr>
			{foreach name=support item=text from=$form.documentation.description}
			<tr class="autoconfig-documentation">
				<td class="label"><label>{$PALANG.pAutoconfig_documentation_desc}:</label></td>
				<td><input type="hidden" name="documentation_id[]" value="{$text.id}" />
				{html_options name="documentation_lang[]" options=$language_options selected=$text.lang}<br />
				<textarea class="flat" rows="5" cols="50" name="documentation_text[]" >{$text.phrase}</textarea></td>
				<td><button class="autoconfig-command autoconfig-documentation autoconfig-locale-text-add ripple" title="{$PALANG.pAutoconfig_add_new_text}"><i class="fas fa-plus fa-2x"></i></button><button class="autoconfig-command autoconfig-documentation autoconfig-locale-text-remove ripple" title="{$PALANG.pAutoconfig_remove_text}"><i class="fas fa-minus fa-2x"></i></button></td>
			</tr>
			{/foreach}
			<tr>
				<th colspan="3">{$PALANG.pAutoconfig_webmail}</th>
			</tr>
			<tr>
				<td class="label"><label>{$PALANG.pAutoconfig_webmail_login_page}:</label></td>
				<td><input type="text" size="40" class="flat" name="webmail_login_page" maxlength="2048" value="{$form.webmail_login_page}" /></td>
				<td>&nbsp;</td>
			</tr>
			<tr>
				<th colspan="3">{$PALANG.pAutoconfig_webmail_login_page_info}</th>
			</tr>
			<tr>
				<td class="label"><label>{$PALANG.pAutoconfig_lp_info_url}:</label></td>
				<td><input type="text" size="40" class="flat" name="lp_info_url" maxlength="2048" value="{$form.lp_info_url}" /></td>
				<td>&nbsp;</td>
			</tr>
			<tr>
				<th colspan="3">{$PALANG.pAutoconfig_lp_info_username}</th>
			</tr>
			<tr>
				<td class="label"><label>{$PALANG.pAutoconfig_lp_info_username_field_id}:</label></td>
				<td><input type="text" class="flat" name="lp_info_username_field_id" maxlength="255" value="{$form.lp_info_username_field_id}" /></td>
				<td>&nbsp;</td>
			</tr>
			<tr>
				<td class="label"><label>{$PALANG.pAutoconfig_lp_info_username_field_name}:</label></td>
				<td><input type="text" class="flat" name="lp_info_username_field_name" maxlength="255" value="{$form.lp_info_username_field_name}" /></td>
				<td>&nbsp;</td>
			</tr>
			<tr>
				<th colspan="3">{$PALANG.pAutoconfig_lp_info_login_button}</th>
			</tr>
			<tr>
				<td class="label"><label>{$PALANG.pAutoconfig_lp_info_login_button_id}:</label></td>
				<td><input type="text" class="flat" name="lp_info_login_button_id" maxlength="255" value="{$form.lp_info_login_button_id}" /></td>
				<td>&nbsp;</td>
			</tr>
			<tr>
				<td class="label"><label>{$PALANG.pAutoconfig_lp_info_login_button_name}:</label></td>
				<td><input type="text" class="flat" name="lp_info_login_button_name" maxlength="255" value="{$form.lp_info_login_button_name}" /></td>
				<td>&nbsp;</td>
			</tr>
			<tr>
				<th colspan="3">{$PALANG.pAutoconfig_mac_specific_settings}</th>
			</tr>
			<tr>
				<td class="label"><label>{$PALANG.pAutoconfig_account_name}:</label></td>
				<td><input type="text" size="40" class="flat" name="account_name" maxlength="255" value="{$form.account_name}" placeholder="John Doe" /></td>
				<td>&nbsp;</td>
			</tr>
			<tr>
				<td class="label"><label>{$PALANG.pAutoconfig_account_type}:</label></td>
				<td><select name="account_type">
					<option value="imap" {if !empty($form.account_type) && $form.account_type == "imap"}selected{/if}>imap</option>
					<option value="pop3" {if !empty($form.account_type) && $form.account_type == "pop3"}selected{/if}>pop3</option>
				</select></td>
				<td>&nbsp;</td>
			</tr>
			<tr>
				<td class="label"><label>{$PALANG.pAutoconfig_email}:</label></td>
				<td><input type="text" size="40" class="flat" name="email" maxlength="255" value="{$form.email}" placeholder="john.doe@example.org" /></td>
				<td>&nbsp;</td>
			</tr>
			<!-- Not sure this field should stay there, because it is automatically computed based on the sock_type, ie SSL, TLS, STARTTLS -->
			<tr>
				<td class="label"><label for="autoconfig_ssl">{$PALANG.pAutoconfig_ssl}:</label></td>
				<td><input type="checkbox" class="flat" name="ssl_enabled" id="autoconfig_ssl" value="1" {if !empty($form.ssl_enabled) && $form.ssl_enabled == 1}checked="checked"{/if} /></td>
				<td>&nbsp;</td>
			</tr>
<!-- 
			<tr>
				<td class="label"><label>{$PALANG.Autoconfig_password}:</label></td>
				<td><input type="text" class="flat" name="password" maxlength="255" value="{$form.password}" /></td>
				<td>&nbsp;</td>
			</tr>
 -->
			<tr>
				<td class="label"><label>{$PALANG.pAutoconfig_description}:</label></td>
				<td><textarea class="flat" rows="5" cols="60" name="description" >{$form.description}</textarea></td>
				<td>&nbsp;</td>
			</tr>
			<tr>
				<td class="label"><label>{$PALANG.pAutoconfig_organisation}:</label></td>
				<td><input type="text" size="40" class="flat" name="organisation" maxlength="255" value="{$form.organisation}" /><input type="button" id="copy_provider_value" value="{$PALANG.pAutoconfig_copy_provider_name}" /></td>
				<td>&nbsp;</td>
			</tr>
			<tr>
				<td class="label"><label>{$PALANG.pAutoconfig_payload_type}:</label></td>
				<td><select name="payload_type">
					<option value="com.apple.mail.managed" {if !empty($form.payload_type) && $form.payload_type == "com.apple.mail.managed"}selected{/if}>Mail account</option>
					<option value="com.apple.eas.account" {if !empty($form.payload_type) && $form.payload_type == "com.apple.eas.account"}selected{/if}>Microsoft Exchange</option>
				</select></td>
				<td>&nbsp;</td>
			</tr>
			<tr>
				<td class="label"><label for="autoconfig_prevent_app_sheet">{$PALANG.pAutoconfig_prevent_app_sheet}:</label></td>
				<td><input type="checkbox" class="flat" name="prevent_app_sheet" id="autoconfig_prevent_app_sheet" value="1" {if !empty($form.prevent_app_sheet) && $form.prevent_app_sheet == 1}checked="checked"{/if} /></td>
				<td>&nbsp;</td>
			</tr>
			<tr>
				<td class="label"><label for="autoconfig_prevent_move">{$PALANG.pAutoconfig_prevent_move}:</label></td>
				<td><input type="checkbox" class="flat" name="prevent_move" id="autoconfig_prevent_move" value="1" {if !empty($form.prevent_move) && $form.prevent_move == 1}checked="checked"{/if} /></td>
				<td>&nbsp;</td>
			</tr>
			<tr>
				<td class="label"><label for="autoconfig_smime_enabled" >{$PALANG.pAutoconfig_smime_enabled}:</label></td>
				<td><input type="checkbox" class="flat" name="smime_enabled" id="autoconfig_smime_enabled" value="1" {if !empty($form.smime_enabled) && $form.smime_enabled == 1}checked="checked"{/if} /></td>
				<td>&nbsp;</td>
			</tr>
			<tr>
				<td class="label"><label for="autoconfig_payload_remove_ok">{$PALANG.pAutoconfig_payload_remove_ok}:</label></td>
				<td><input type="checkbox" class="flat" name="payload_remove_ok" id="autoconfig_payload_remove_ok" value="1" {if !empty($form.payload_remove_ok) && $form.payload_remove_ok == 1}checked="checked"{/if} /></td>
				<td>&nbsp;</td>
			</tr>
			<tr>
				<td class="label"><label for="autoconfig_spa">{$PALANG.pAutoconfig_spa}:</label></td>
				<td><input type="checkbox" class="flat" name="spa" id="autoconfig_spa" value="1" {if !empty($form.spa) && $form.spa == 1}checked="checked"{/if} /></td>
				<td>&nbsp;</td>
			</tr>
			<tr>
				<th colspan="3">{$PALANG.pAutoconfig_cert_sign}</th>
			</tr>
			<tr>
				<td class="label"><label>{$PALANG.pAutoconfig_cert_option}:</label></td>
				<td><select name="sign_option">
					<option value="none" {if !empty($form.sign_option) && $form.sign_option == "none"}selected{/if}>{$PALANG.pAutoconfig_cert_none}</option>
					<option value="local" {if !empty($form.sign_option) && $form.sign_option == "local"}selected{/if}>{$PALANG.pAutoconfig_cert_local}</option>
					<option value="global" {if !empty($form.sign_option) && $form.sign_option == "global"}selected{/if}>{$PALANG.pAutoconfig_cert_global}</option>
				</select></td>
				<td>&nbsp;</td>
			</tr>
			<tr class="cert_files">
				<td class="label"><label>{$PALANG.pAutoconfig_cert_filepath}:</label></td>
				<td><input type="text" size="70" class="flat" name="cert_filepath" maxlength="1024" value="{$form.cert_filepath}" placeholder="/etc/letsencrypt/live/example.com/cert.pm" /></td>
				<td>&nbsp;</td>
			</tr>
			<tr class="cert_files">
				<td class="label"><label>{$PALANG.pAutoconfig_privkey_filepath}:</label></td>
				<td><input type="text" size="70" class="flat" name="privkey_filepath" maxlength="1024" value="{$form.privkey_filepath}" placeholder="/etc/letsencrypt/live/example.com/privkey.pm" /></td>
				<td>&nbsp;</td>
			</tr>
			<tr class="cert_files">
				<td class="label"><label>{$PALANG.pAutoconfig_chain_filepath}:</label></td>
				<td><input type="text" size="70" class="flat" name="chain_filepath" maxlength="1024" value="{$form.chain_filepath}" placeholder="/etc/letsencrypt/live/example.com/chain.pm" /></td>
				<td>&nbsp;</td>
			</tr>
			<tr>
				<td>&nbsp;</td>
				<td colspan="2">
					<input class="button" id="autoconfig_save" type="submit" name="fChange" value="{$PALANG.pEdit_autoconfig_set}" />
					<input class="button" id="autoconfig_remove" type="submit" name="fBack" value="{$PALANG.pEdit_autoconfig_remove}" />
					<input class="button" id="autoconfig_cancel" type="submit" name="fCancel" value="{$PALANG.exit}" />
				</td>
			</tr>
		</table>
	</form>
</div>
