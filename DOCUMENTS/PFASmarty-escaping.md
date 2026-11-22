# Template output escaping

`PFASmarty` enables Smarty's auto-escaping (`setEscapeHtml(true)`, see
https://smarty-php.github.io/smarty/5.x/api/configuring/#enabling-auto-escaping).
Every `{$var}` is HTML-escaped (`htmlspecialchars(..., ENT_QUOTES)`) when the template is
**rendered**, not when the value is assigned.

`$smarty->assign($key, $value)` stores the value unchanged. Arrays and nested values are
not modified, so PHP code always sees the original data. Array keys are escaped too
when printed from a `{foreach}`.

## Summary

Example output for the value `<b>R&D</b> "x" 'y'`:

| Template | Output | Use for |
|---|---|---|
| `{$v}` | `&lt;b&gt;R&amp;D&lt;/b&gt; &quot;x&quot; &#039;y&#039;` | Normal HTML text and attributes (the default) |
| `{$v\|escape}` / `{$v\|escape:"html"}` | same as `{$v}` (no double escaping) | Nothing. It's redundant, so leave it out |
| `{$v\|raw}` | `<b>R&D</b> "x" 'y'` | Trusted HTML that is already safe |
| `{$v\|escape:url}` | `%3Cb%3ER%26D%3C%2Fb%3E%20%22x%22%20%27y%27` aka `rawurlencode($v)` | Query-string values in URLs |
| `{$v\|escape:"javascript"}` | `<b>R&D<\/b> \"x\" \'y\'` | Inside a `<script>` block |
| `{$v\|escape:"javascript"\|escape:"force"}` | `&lt;b&gt;R&amp;D&lt;\/b&gt; \&quot;x\&quot; \&#039;y\&#039;` | JavaScript inside an HTML attribute (e.g. `onclick="..."`) |

These cases are covered by `tests/PFASmartyEscapingTest.php`.

## `|raw`

`{$var|raw}` turns off escaping for that one variable. It does not validate or sanitise
the value, so use it only for HTML that PostfixAdmin generated itself or that is already
escaped. Never use it for user-supplied text.

Values that legitimately need `|raw` include:

- config variables holding HTML, e.g. `{#x#}` from `configs/menu.conf` and `$CONF.show_status_text`
- the output of HTML-producing modifiers such as `{$text|highlight:$search|raw}`, which
  escapes `$text` itself and then adds the `<span class='searchresult'>` markup

## Escape modifiers and auto-escaping

Smarty skips auto-escaping when a variable already ends in an escape modifier that
produces a non-HTML encoding (`escape:url`, `escape:javascript`, `escape:quotes`,
`escape:htmlall`). Its output is printed exactly as the modifier returns it.

- `escape:"javascript"` only adds backslashes before quotes and backslashes, escapes
  newlines and turns `</` into `<\/`. It leaves `<`, `>` and `&` unchanged. That's correct
  inside a `<script>` block but **not** inside an HTML attribute, so add `|escape:"force"`
  there.
- `escape:"html"` without `force` does nothing, because the value is HTML-escaped anyway.
- Don't use `escape:quotes`. It does not escape `"`.

## Quoted strings

Inside a double-quoted template string, a plain `{$v}` is **not** escaped, but `{$v|modifier}`
**is**. So this escapes the value twice when `$x` is later printed:

```smarty
{assign "x" "{$v|replace:'%s':$id}"}   {* wrong: escaped here and again when printed *}
```

Use the unquoted form instead:

```smarty
{assign "x" $v|replace:'%s':($id|escape:url)}
```

## Upgrading custom themes / templates

- The `$RAW_*` template variables have been removed. Replace `{$RAW_foo}` with `{$foo|raw}`
  (only if the value really is safe HTML), or with plain `{$foo}`.
- `|escape` / `|escape:"html"` are now redundant and can be removed.
- Values that used to be escaped in PHP before they were assigned will now be escaped
  twice (e.g. `&amp;amp;`). Assign the raw value and let the template escape it.
- Check HTML that comes from config files (`{#...#}`, `$CONF`) and add `|raw` where needed.
