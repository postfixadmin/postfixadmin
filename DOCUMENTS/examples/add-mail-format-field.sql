-- Optional local extension for gradual per-user Maildir to mdbox migration.
--
-- This is not required for standard PostfixAdmin installations.
-- Use it only if your Dovecot 2.4 userdb query returns mail_driver/mail_path
-- from SQL and you want to migrate users gradually.

ALTER TABLE mailbox
  ADD COLUMN mail_format VARCHAR(16) NOT NULL DEFAULT 'maildir';

-- Optional visibility check:
SELECT username, mail_format FROM mailbox ORDER BY username LIMIT 20;
