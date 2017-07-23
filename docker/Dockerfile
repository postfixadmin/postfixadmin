FROM php:7.0-apache

# Install required PHP extensions
RUN docker-php-ext-install mysqli

VOLUME /var/www/html

ENV POSTFIXADMIN_VERSION 3.1
ENV POSTFIXADMIN_SHA512 3bda4e9d4a7308d22edca30d181af76b7153e57b19bda878e32f5eeeb49127f46581c966706bcca13cd31740cadacc584e15830aa157b4655e60d44d66f45ddd

RUN set -eux; \
	curl -o postfixadmin.tar.gz -SL "https://github.com/postfixadmin/postfixadmin/archive/postfixadmin-${POSTFIXADMIN_VERSION}.tar.gz"; \
	echo "$POSTFIXADMIN_SHA512 *postfixadmin.tar.gz" | sha512sum -c -; \
	# upstream tarball include ./postfixadmin-postfixadmin-${POSTFIXADMIN_VERSION}/
	tar -xzf postfixadmin.tar.gz -C /usr/src/; \
	mv /usr/src/postfixadmin-postfixadmin-${POSTFIXADMIN_VERSION} /usr/src/postfixadmin; \
	rm postfixadmin.tar.gz; \
	# Does not exist in tarball but is required
	mkdir -p /usr/src/postfixadmin/templates_c; \
	chown -R www-data:www-data /usr/src/postfixadmin

COPY docker-entrypoint.sh /usr/local/bin/

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
