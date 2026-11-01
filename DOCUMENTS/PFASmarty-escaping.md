# Template output escaping

`$smarty->assign($key, $value)` escapes strings recursively for HTML output by default, using `TemplateEscape::HTML`. Keep this default unless you have very good reasons to disable escaping. Never disable escaping for user-supplied text, including nested list values.

When a value intentionally contains HTML that is already safe for its output context, use `$smarty->assign($key, $value, TemplateEscape::RAW)`. RAW does not validate or sanitise HTML. JSON, JavaScript, URLs and commands need their own context-specific encoding before any intentional HTML-escaping opt-out; the enum does not implement those encoders.

The third argument is named `sanitise` and accepts only `TemplateEscape::HTML` or `TemplateEscape::RAW`. Boolean arguments, strings and other values throw `TypeError` before any template assignment. There is no intermediate deprecation stage. Replace legacy `true` with the default or `TemplateEscape::HTML`; review each legacy `false` call and remove its opt-out when HTML escaping is appropriate, otherwise use `TemplateEscape::RAW` only for values already safe in their output context. Apply the same migration to dynamically computed mode arguments in integrations.

The existing `RAW_` template variables remain available for compatibility. They contain the original assigned values and do not provide output-context encoding. The enum requires PHP 8.1 or newer and is supported by master's PHP >=8.2 requirement; this API change must not be copied to the PHP 7.4 branch.
