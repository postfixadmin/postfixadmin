# Building

cd docker ; docker build --pull --rm -t postfixadmin-image .

# Running

## No config.local.php / no existing setup

If you do not have a config.local.php, then we fall back to look for environment variables to generate one.

POSTFIXADMIN\_DB\_TYPE can be one of :

 * mysqli
 * pgsql
 * sqlite


```bash
docker run -e POSTFIXADMIN_DB_TYPE=mysqli \
           -e POSTFIXADMIN_DB_HOST=whatever \
           -e POSTFIXADMIN_DB_USER=user \
           -e POSTFIXADMIN_DB_PASSWORD=topsecret \
           -e POSTFIXADMIN_DB_NAME=postfixadmin \
           --name postfixadmin \
           -p 8080:80 \
        postfixadmin-image
```

Note: An sqlite database is used as a fallback if you do not have a config.local.php and do not specify the above variables.



## Existing config.local.php 

```bash
docker run --name postfixadmin -p 8080:80 postfixadmin-image
```

## Linking to a MySQL or PostgreSQL container

If you link the container to a MySQL or PostgreSQL container, then we attempt to generate a valid config.local.php from it. 

