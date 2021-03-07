# Migrating to Postfixadmin from other products

## From Postfix

Where a database structure like this exists :

See also: https://github.com/postfixadmin/postfixadmin/issues/468


```SQL

CREATE TABLE `virtual_domains` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) CHARACTER SET latin1 NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=latin1 COLLATE=latin1_german1_ci;

CREATE TABLE `virtual_aliases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `domain_id` int(11) NOT NULL,
  `source` varchar(40) CHARACTER SET latin1 NOT NULL,
  `destination` varchar(80) CHARACTER SET latin1 NOT NULL,
  PRIMARY KEY (`id`),
  KEY `domain_id` (`domain_id`),
  CONSTRAINT `virtual_aliases_ibfk_1` FOREIGN KEY (`domain_id`) REFERENCES `virtual_domains` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=465 DEFAULT CHARSET=latin1 COLLATE=latin1_german1_ci;

CREATE TABLE `virtual_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `domain_id` int(11) NOT NULL,
  `user` varchar(40) CHARACTER SET latin1 NOT NULL,
  `password` varchar(32) CHARACTER SET latin1 NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQUE_EMAIL` (`domain_id`,`user`),
  CONSTRAINT `virtual_users_ibfk_1` FOREIGN KEY (`domain_id`) REFERENCES `virtual_domains` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=114 DEFAULT CHARSET=latin1 COLLATE=latin1_german1_ci;

```

## Possible route

You'll need to modify the below to match how your mailboxes are stored on disk (the maildir).

It assumes the password hash format is compatible. If not some sort of blanket reset is probably required.

### Migrate domains

`insert into domain (domain, description, transport) select name, name, 'virtual' from postfix_legacy.virtual_domains;`

### Migrate users 

` insert into mailbox (username, password, name, maildir, local_part, domain) select concat(user, '@', d.name), password, user, concat(d.name, '/', user), user, d.name FROM postfix_legacy.virtual_users INNER JOIN postfix_legacy.virtual_domains d ON postfix_legacy.virtual_users.domain_id = d.id;`

### Migrate Aliases

`insert into alias (address, goto, domain) select concat(a.source, '@', d.name), a.destination, d.name FROM postfix_legacy.virtual_aliases a INNER JOIN postfix_legacy.virtual_domains d ON d.id = a.domain_id;`
