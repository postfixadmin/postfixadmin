Random instructions for help:

This 'debian' directory is used by dpkg-buildpackage when creating a .deb.

You'll need to install :

   apt-get install dpkg-dev quilt debhelper

Then from within the trunk directory (or whatever forms the root of the 
postfixadmin project), do the following :

1. Update debian/changelog; include your email address in the last change 
                (this is used to determine a gpg key to use)

2. debian/rules prep

3. dpkg-buildpackage -rfakeroot 

4. Look in ../ at the shiny .deb / .tar.gz

5. Profit.
