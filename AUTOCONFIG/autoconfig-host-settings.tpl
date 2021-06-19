<tr class="{if $server.type == 'imap' || $server.type == 'pop3'}autoconfig-incoming{else}autoconfig-outgoing{/if}">
    <td colspan="3">
        <table class="server" width="100%">
            <tr>
                <td class="label" width="20%"><label>{$PALANG.pAutoconfig_type}:</label></td>
                {if $server.type == "imap" || $server.type == "pop3"}
                    <td width="60%"><select name="type[]" class="host_type">
                            <option value="imap" {if !empty($server.type) && $server.type == "imap"}selected{/if}>imap
                            </option>
                            <option value="pop3" {if !empty($server.type) && $server.type == "pop3"}selected{/if}>pop3
                            </option>
                        </select></td>
                {else}
                    <td width="20%"><em>smtp</em><input type="hidden" name="type[]" value="smtp"/></td>
                {/if}
                <td rowspan="2">
                    <button class="autoconfig-command ripple autoconfig-server-add {if $server.type == 'imap' || $server.type == 'pop3'} autoconfig-incoming-server{else}autoconfig-outgoing-server{/if}"
                            title="{$PALANG.pAutoconfig_add_new_host}"><i class="fas fa-plus fa-2x"></i></button>
                    <button class="autoconfig-command ripple autoconfig-server-remove {if $server.type == 'imap' || $server.type == 'pop3'} autoconfig-incoming-server{else}autoconfig-outgoing-server{/if}"
                            title="{$PALANG.pAutoconfig_remove_host}"><i class="fas fa-minus fa-2x"></i></button>
                    <button class="autoconfig-command ripple autoconfig-move-up"
                            title="{$PALANG.pAutoconfig_move_up_host}"><i class="fas fa-arrow-up fa-2x"></i></button>
                    <button class="autoconfig-command ripple autoconfig-move-down"
                            title="{$PALANG.pAutoconfig_move_down_host}"><i class="fas fa-arrow-down fa-2x"></i>
                    </button>
                </td>
            </tr>
            <tr>
                <td class="label"><label>{$PALANG.pAutoconfig_hostname}:</label></td>
                <td><input type="hidden" name="host_id[]" value="{$server.host_id}"/>
                    <input type="text" size="40" class="flat" name="hostname[]" maxlength="255"
                           value="{$server.hostname}"/></td>
                <!-- 							<td>&nbsp;</td> -->
            </tr>
            <tr>
                <td class="label"><label>{$PALANG.pAutoconfig_port}:</label></td>
                <td><input type="number" class="flat" name="port[]" maxlength="255" value="{$server.port}"
                           list="defaultPorts"/></td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td class="label"><label>{$PALANG.pAutoconfig_socket_type}:</label></td>
                <td><select name="socket_type[]">
                        <option value=""
                                {if empty($server.socket_type) || $server.socket_type == ""}selected{/if}>{$PALANG.pAutoconfig_no_selection}</option>
                        <option value="SSL"
                                {if !empty($server.socket_type) && $server.socket_type == "SSL"}selected{/if}>SSL
                        </option>
                        <option value="STARTTLS"
                                {if !empty($server.socket_type) && $server.socket_type == "STARTTLS"}selected{/if}>
                            STARTTLS
                        </option>
                        <option value="TLS"
                                {if !empty($server.socket_type) && $server.socket_type == "TLS"}selected{/if}>TLS
                        </option>
                    </select></td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td class="label"><label>{$PALANG.pAutoconfig_auth}:</label></td>
                <td><select name="auth[]">
                        <option value="password-cleartext"
                                {if !empty($server.auth) && $server.auth == "password-cleartext"}selected{/if}>{$PALANG.pAutoconfig_password_cleartext}</option>
                        <option value="password-encrypted"
                                {if !empty($server.auth) && $server.auth == "password-encrypted"}selected{/if}>{$PALANG.pAutoconfig_password_encrypted}</option>
                        <option value="NTLM" {if !empty($server.auth) && $server.auth == "NTLM"}selected{/if}>NTLM
                        </option>
                        <option value="GSSAPI" {if !empty($server.auth) && $server.auth == "GSSAPI"}selected{/if}>
                            GSSAPI
                        </option>
                        <option value="client-IP-address"
                                {if !empty($server.auth) && $server.auth == "client-IP-address"}selected{/if}>{$PALANG.pAutoconfig_client_ip_address}</option>
                        <option value="TLS-client-cert"
                                {if !empty($server.auth) && $server.auth == "TLS-client-cert"}selected{/if}>{$PALANG.pAutoconfig_tls_client_cert}</option>
                        <option value="smtp-after-pop"
                                {if !empty($server.auth) && $server.auth == "smtp-after-pop"}selected{/if}>{$PALANG.pAutoconfig_smtp_after_pop}</option>
                        <option value="ouath2" {if !empty($server.auth) && $server.auth == "ouath2"}selected{/if}>
                            OAuth2
                        </option>
                    </select></td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td class="label"><label>{$PALANG.pAutoconfig_username}:</label></td>
                <td><input type="text" class="flat" name="username[]" maxlength="255" value="{$server.username}"
                           placeholder="%EMAILADDRESS%"/> {$PALANG.pAutoconfig_username_template}<select
                            name="username_template" class="username_template">
                        <option value="">{$PALANG.pAutoconfig_no_selection}</option>
                        <option value="%EMAILADDRESS%">%EMAILADDRESS%</option>
                        <option value="%EMAILLOCALPART%">%EMAILLOCALPART%</option>
                        <option value="%EMAILDOMAIN%">%EMAILDOMAIN%</option>
                    </select></td>
                <td>&nbsp;</td>
            </tr>
            {if $server.type == "pop3" || $server.type == "imap"}
                <!-- if incoming server is a pop3 -->
                {if isset( $server.host_id )}
                    {assign var=host_unique_id value=$server.host_id}
                {else}
                    {assign var=host_unique_id value=10|mt_rand:20}
                {/if}
                <tr class="host_pop3">
                    <td class="label"><label
                                for="autoconfig_leave_messages_on_server_{$host_unique_id}">{$PALANG.pAutoconfig_leave_messages_on_server}
                            :</label></td>
                    <td><input type="checkbox" class="flat" name="leave_messages_on_server[]"
                               id="autoconfig_leave_messages_on_server_{$host_unique_id}" value="1"
                               {if !empty($server.leave_messages_on_server) && $server.leave_messages_on_server == 1}checked="checked"{/if} />
                    </td>
                    <td>&nbsp;</td>
                </tr>
                <tr class="host_pop3">
                    <td class="label"><label
                                for="autoconfig_download_on_biff_{$host_unique_id}">{$PALANG.pAutoconfig_download_on_biff}
                            :</label></td>
                    <td><input type="checkbox" class="flat" name="download_on_biff[]"
                               id="autoconfig_download_on_biff_{$host_unique_id}" value="1"
                               {if !empty($server.download_on_biff) && $server.download_on_biff == 1}checked="checked"{/if} />
                    </td>
                    <td>&nbsp;</td>
                </tr>
                <tr class="host_pop3">
                    <td class="label"><label
                                for="autoconfig_days_to_leave_messages_on_server_{$host_unique_id}">{$PALANG.pAutoconfig_days_to_leave_messages_on_server}
                            :</label></td>
                    <td><input type="number" class="flat" name="days_to_leave_messages_on_server[]"
                               id="autoconfig_days_to_leave_messages_on_server_{$host_unique_id}" min="0" max="365"
                               value="{$server.days_to_leave_messages_on_server}"/></td>
                    <td>&nbsp;</td>
                </tr>
                <tr class="host_pop3">
                    <td class="label"><label
                                for="autoconfig_check_interval_{$host_unique_id}">{$PALANG.pAutoconfig_check_interval}
                            :</label></td>
                    <td><input type="number" class="flat" name="check_interval[]"
                               id="autoconfig_check_interval_{$host_unique_id}" value="{$server.check_interval}"/></td>
                    <td>&nbsp;</td>
                </tr>
            {/if}
        </table>
    </td>
</tr>
<!-- end if incoming server is a pop3 -->
