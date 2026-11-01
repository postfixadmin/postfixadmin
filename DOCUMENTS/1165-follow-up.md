# Search and account-form follow-up

Searches containing only unknown or password fields are treated as an unfiltered
list after filtering, including administrator lists. Existing domain and user
restrictions still apply.

Password recovery updates only the password fields. Messages returned by the
handler are displayed on both success and failure.

The generic TOTP exception editor now requires the acting account's current
password in `value[password_current]`, for both creation and editing. Direct
`TotpexceptionHandler::set()` callers must include `password_current` too. This
field is not persisted. A successful confirmation is not reused by a later
`set()` call. The dedicated TOTP exception page keeps its existing replacement,
validation and script behavior; `TotpPf::addException()` shares the handler's
password check and retains its public signature. Revocation behavior is unchanged.

This follows the review of [PR 1165](https://github.com/postfixadmin/postfixadmin/pull/1165),
including Chris's suggestions on password confirmation and recovery updates.
The maintained 4.x branch is outside this change.
