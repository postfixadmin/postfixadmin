ALTER TABLE mailbox ADD COLUMN password_expiry TIMESTAMP DEFAULT now() not null;
UPDATE mailbox set password_expiry = now() + interval 90 day;
ALTER TABLE domain ADD COLUMN password_expiry int DEFAULT 0;
