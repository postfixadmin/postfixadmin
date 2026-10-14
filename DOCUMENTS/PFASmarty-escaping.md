# Template output escaping

`$smarty->assign($key, $value)` escapes strings recursively for HTML output by default. Keep this default unless you have very good reasons to disable escaping. Never disable escaping for user-supplied text, including nested list values.

When a value intentionally contains HTML that is already safe for its output context, use `$smarty->assign($key, $value, PFASmarty::NO_ESCAPING)`. The constant makes the decision visible during review; it does not validate or sanitise HTML.

The existing third argument name (`sanitise`), default `true`, legacy `false` opt-out and `RAW_` template variables remain supported for compatibility with existing integrations and themes. Prefer the named constant over `false` in new code. This change introduces no runtime deprecation warning.
