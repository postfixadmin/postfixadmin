ALTER TABLE mailbox ADD COLUMN pw_expires_on TIMESTAMP DEFAULT now() not null;
ALTER TABLE mailbox ADD COLUMN thirty boolean not null DEFAULT false;
ALTER TABLE mailbox ADD COLUMN fourteen boolean not null DEFAULT false;
ALTER TABLE mailbox ADD COLUMN seven boolean not null DEFAULT false;
UPDATE mailbox set pw_expires_on = now() + interval 90 day;
