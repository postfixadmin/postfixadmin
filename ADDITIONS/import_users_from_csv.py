#!/usr/bin/env python
# -*- coding: utf-8 -*-
# Script takes a CSV list of users and does a 'bulk' insertion into mysql.
#
# Copyright (C) 2009 Simone Piccardi
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or (at
# your option) any later version.
#
# This program is distributed in the hope that it will be useful, but
# WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
# General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
#

import csv
import getopt
import sys
import re
import time
import random, string
from datetime import datetime
from crypt import crypt
try:
    import MySQLdb 
except ImportError ,e:
    print 'Cannot import the needed MySQLdb module, you must install it'
    print 'on Debian systems just use the command'
    print '   apt-get install python-mysqldb'

def usage():
    print "Usage: inspostadmusers.py [options] users.csv"
    print "       -h        print this help"
    print "       -t        test run, do not insert, just print"
    print "       -u        DB user"
    print "       -p        DB password"
    print "       -D        DB name"
    print "       -H        DB host"
    print "       -q        Quota in Mb (0 => no limit)"
    print "       -n        char in seed"
    print "       -d        debug info on"
    print "       -A        create default alias for each domain"
    print
    print "the users.csv file must contains the user list with a line"
    print "for each user, first line should be a title line with at least"
    print "the following column names: "
    print " * user     - user part of the email (like user in user@domain.com)"
    print " * password - cleartext password"
    print " * domain   - domain name (like 'domain.com')"
    print " * name     - full user name ('Name Surname')"
    print
    print "the 'name' column is optional, other columns will be ignored"
    print
    print "Known restrictions:"
    print "* this script only works with MySQL"
    print "* mailbox paths are hardcoded to domain/username/"


# option parsing
try:
    opts, args = getopt.getopt(sys.argv[1:], 'u:p:d:D:H:htdA')
    optval={}
    for opt, val in opts:
        if opt == "-h":
            usage()
            sys.exit(0)
        else:
            optval[opt]=val
except getopt.GetoptError:
    usage()
    sys.exit(2)

#
# Setup DB connection
#
MYSQLDB="postfixadmin"
MYSQLUSER="postfixadmin"
MYSQLPASSWORD=""
MYSQLHOST="localhost"

# settings by command line options
if optval.has_key('-u'):
    MYSQLUSER = optval['-u']
if optval.has_key('-p'):
    MYSQLPASSWORD = optval['-p']
if optval.has_key('-D'):
    MYSQLDB = optval['-D']
if optval.has_key('-H'):
    MYSQLHOST = optval['-H']

if optval.has_key('-q'):
    quota = optval['-q']
else:
    quota = 0

if optval.has_key('-n'):
    seed_len = optval['-n']
else:
    seed_len = 8

# check arguments, only the user list file must be present
if len(args) !=1:
    print 'Need just one argument'
    usage()
    sys.exit(1)

# MySQL connection (skipped in test run)
if optval.has_key('-t'):
    print "Test Run"
else:
    try:
        connection = MySQLdb.connect(host=MYSQLHOST, user=MYSQLUSER, 
                                     db=MYSQLDB, passwd=MYSQLPASSWORD)
    except MySQLdb.MySQLError, e:
        print "Database connection error"
        print e
        sys.exit(1)
 
    cursor = connection.cursor()

#
# Main body
#
NOW = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

# read and convert CSV data
lista = csv.DictReader(open(args[0]))

def gen_seed(seed_len, chars):
    return '$1$'+''.join([random.choice(chars) for _ in xrange(seed_len)])+'$'

def insert_record(cursor,table,record):
    columns = record.keys()
    query = "INSERT INTO " + table + "(" + ','.join(columns) + ") VALUES (" + ','.join(len(columns)*['%s']) + ")"
    try:
        cursor.execute(query, record.values())
        return 0
    except MySQLdb.MySQLError, e:
        print "Database insertion error"
        print e
        print "Record was:"
        print record.values()
        print "Query was:"
        print query

# defining default values for tables (mailbox, alias and domain)
mailbox = {
    'created': NOW, 
    'modified': NOW,
    'active': 1,
    'quota': quota
    }
aliases = {
    'created':  NOW,
    'modified': NOW,
    'active':   1
    }
domain = {
    'description': "",
    'aliases': 0,
    'mailboxes': 0,
    'quota': 0,
    'transport': 'virtual',
    'backupmx': 0,
    'created': NOW,
    'modified': NOW,
    'active': 1
}

# list of default alias
def_alias = ['abuse','hostmaster','postmaster','webmaster'] 

domain_list = {}
chars = string.letters + string.digits

# loop over the CSV 
for row in lista:
    # create domain if it does not exists
    if domain_list.has_key(row["domain"]):
        if optval.has_key('-d'):
            print "Domain " + row["domain"] + "already exixts"
    else:
        domain_list[row["domain"]] = 1
        domain['domain'] = row["domain"]
        if optval.has_key('-t'):
            print "Inserting domain"
            print domain
        else:
            insert_record(cursor,'domain',domain)
            if optval.has_key('-A'):
                for i in def_alias:
                    aliases['address']= i+'@'+row["domain"]
                    aliases['goto']= aliases['address']
                    aliases['domain'] = row["domain"]
                    if optval.has_key('-t'):
                        print "Inserting alias"
                        print aliases
                    else:
                        insert_record(cursor,'alias',aliases)

    # build query data for mailbox table
    mailbox['username']=row["user"]+'@'+row["domain"]
    encpass=crypt(row["password"], gen_seed(seed_len,chars))
    mailbox['password'] = encpass
    mailbox['name'] = row["name"]
    mailbox['maildir'] = row["domain"]+'/'+row["user"]+'/'
    mailbox['local_part'] =row["user"]
    mailbox['domain'] = row["domain"]

    # build query data for alias table
    aliases['address']= mailbox['username']
    aliases['goto']= mailbox['username']
    aliases['domain'] = row["domain"]

    # inserting data for mailbox (and relate alias)
    if optval.has_key('-t'):
        print "Inserting mailbox"
        print mailbox
        print aliases
    else:
        insert_record(cursor,'mailbox',mailbox)
        insert_record(cursor,'alias',aliases)


sys.exit(0)
