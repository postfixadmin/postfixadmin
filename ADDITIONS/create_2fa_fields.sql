--
-- ADD 2fa fields to admin table
--
ALTER TABLE `admin` ADD  `x_2fa_secret` varchar(255) DEFAULT NULL AFTER `token_validity`;
ALTER TABLE `admin`  ADD `x_2fa_active` tinyint(1) NOT NULL DEFAULT '0' AFTER `x_2fa_secret`;
ALTER TABLE `admin`  ADD `x_2fa_qrcode` varchar(255) DEFAULT NULL COMMENT 'generated on the fly' AFTER `x_2fa_active`;
