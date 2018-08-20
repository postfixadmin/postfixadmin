ALTER TABLE mailbox ADD COLUMN pw_expires_on TIMESTAMP DEFAULT now() not null;
UPDATE mailbox set pw_expires_on = now() + interval 90 day;
ALTER TABLE domain ADD COLUMN password_expiration_value int DEFAULT 0;
