<form name="password" method="post" action="" class="form-horizontal">
    <div id="edit_form" class="panel panel-default">
        <div class="panel-heading"><h4>{$PALANG.pApp_passwords_welcome}</h4></div>
        <div class="panel-body enable-asterisk">
            <input class="flat" type="hidden" name="token" value="{$smarty.session.PFA_token|escape:"url"}"/>
            <div class="form-group {if $pPassword_text}has-error{/if}">
                <label class="col-md-4 col-sm-4 control-label"
                       for="fPassword_current">{$PALANG.pPassword_password_current}:</label>
                <div class="col-md-6 col-sm-8">
                    <input class="form-control" type="password" name="fPassword_current" id="fPassword_current"/>
                </div>
                <span class="help-block">{$pPassword_text}</span>
            </div>
            <div class="form-group">
                <label class="col-md-4 col-sm-4 control-label" for="fAppDesc">{$PALANG.pTotp_exceptions_description}:</label>
                <div class="col-md-6 col-sm-8"><input class="form-control" type="input" name="fAppDesc" id="fAppDesc"/></div>
            </div>
            <div class="form-group">
                <label class="col-md-4 col-sm-4 control-label" for="fAppPass">{$PALANG.password}:</label>
                <div class="col-md-6 col-sm-8">
                    <input class="form-control" type="input" name="fAppPass" id="fAppPass"/>
                    <div class="pull-right">
                        <a id="genbutton" class="btn btn-primary">{$PALANG.generate}</a>
                        <a id="copybutton" class="btn btn-primary">{$PALANG.copy}</a>
                    </div>
                 </div>
            </div>
        </div>
        <div class="panel-footer">
            <div class="btn-toolbar" role="toolbar">

                <div class="pull-right">
                    <a href="main.php" class="btn mr btn-secondary">{$PALANG.exit}</a>

                    <button class="btn ml btn-lg btn-primary" type="submit" name="submit" value="{$PALANG.pApp_passwords_add}">{$PALANG.pApp_passwords_add}</button>

                </div>
            </div>
        </div>
    </div>
</form>

<div id="edit_form" class="panel panel-default">
    <div class="panel-heading"><h4>{$PALANG.pApp_passwords_list}</h4></div>
    <table class="table table-hover" id="mailbox_table">
        <tr class="header">
            <th>{$PALANG.pOverview_mailbox_username}</th>
            <th>{$PALANG.pTotp_exceptions_description}</th>
            <th>{$PALANG.pTotp_exceptions_revoke}</th>
        </tr>
        {foreach $pPasswords as $p}
        <tr>
            <td>{$p.username}</td>
            <td>{$p.description}</td>
            <td>
                <form name="exception{$p.id}" method="post" action="" class="form-vertical">
                    <input type="hidden" name="fAppId" value="{$p.id}">
                    <input class="flat" type="hidden" name="token" value="{$smarty.session.PFA_token|escape:"url"}"/>
                    <button class="btn ml btn-primary" type="submit" {if !$p.edit}disabled="disabled"{/if} name="submit" value="{$PALANG.pTotp_exceptions_revoke}">{$PALANG.pTotp_exceptions_revoke}</button>
                </form>
            </td>
        </tr>
        {/foreach}
    </table>
</div>

<script>

const getRandomElement = arr => {
  const rand = Math.floor(Math.random() * arr.length);
  return arr[rand];
}

const generateRandomPasswordSelection = (length) => {
  const uppercase = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
  const lowercase = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z'];
  const special = ['~', '!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '_', '+', '-', '=', '{', '}', '[', ']', ':', ';', '?', ', ', '.', '|', '\\'];
  const numbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

  const nonSpecial = [...uppercase, ...lowercase, ...numbers];

  let password = '';

  for (let i = 0; i < length; i++) {
    // Previous character is a special character
    if (i !== 0 && special.includes(password[i - 1])) {
      password += getRandomElement(nonSpecial);
    } else password += getRandomElement([...nonSpecial, ...special]);
  }

  return password;
}

const passwordInput = document.querySelector("#fAppPass");

passwordInput.value = generateRandomPasswordSelection(32);

document.querySelector("#genbutton").addEventListener("click", () => {
    passwordInput.value = generateRandomPasswordSelection(32);
});

document.querySelector("#copybutton").addEventListener("click", () => {
    navigator.clipboard.writeText(passwordInput.value);
});

</script>
