<nav class="navbar navbar-default navbar-fixed-top">
    <div class="container">
        <div class="navbar-header">
            <a class="navbar-brand" href='main.php'><img id="login_header_logo" src="{$CONF.theme_logo}"
                                                         alt="Logo"/></a>
            {if $CONF.show_header_text==='YES' && $CONF.header_text}
                <h2>{$CONF.header_text}</h2>
            {/if}
        </div>
    </div>
</nav>
 
<div id="edit_form">
    <form name="frmPassword" method="post" action="">
        <table>
            <tr>
                <th colspan="3">{$PALANG.pPassword_recovery_title}</th>
            </tr>
            <tr>
                <td class="label"><label>{$PALANG.pLogin_username}:</label></td>
                <td><input class="flat" type="text" name="fUsername"/></td>
            </tr>
            <tr>
                <td class="label">&nbsp;</td>
                <td colspan="2">
                    <input class="button" type="submit" name="submit" value="{$PALANG.pPassword_recovery_button}"/>
                </td>
            </tr>
        </table>
    </form>
    {literal}
        <script type="text/javascript">
            <!--
            document.frmPassword.fUsername.focus();
            // -->
        </script>
    {/literal}
</div>
