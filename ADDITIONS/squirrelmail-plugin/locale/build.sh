#!/bin/bash

for f in $(find . -name postfixadmin.po)
do
    msgfmt -o $(dirname $f)/postfixadmin.mo $f
done
