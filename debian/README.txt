Random instructions for help:


This 'debian' directory is used by dpkg-buildpackage when creating a .deb.

If you wish to do it yourself, please try the following from within the trunk directory.

1. Update debian/changelog; include your email address in the last change

2. dpkg-buildpackage -rfakeroot .

3. Look in ../ at the shiny .deb / .tar.gz

4. Profit.
