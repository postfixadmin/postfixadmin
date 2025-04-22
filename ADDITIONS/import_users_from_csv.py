#!/usr/bin/env python3
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
# 2024/12/02 - see https://github.com/postfixadmin/postfixadmin/issues/875 - naive porting to python3 by DavidGoodwin

import csv
import argparse
import sys
import re
import time
import random, string
from datetime import datetime
from crypt import crypt

try:
    import MySQLdb 
except ImportError:
    print("""
Cannot import the needed MySQLdb module, you must install it.
On Debian systems just use the command: apt-get install python-mysqldb
""")



# option parsing
try:
    epilog = """

The users.csv file must contains the user list with a line
for each user, first line should be a title line with at least
the following column names: 
 * user     - user part of the email (like user in user@domain.com)
 * password - cleartext password
 * domain   - domain name (like 'domain.com')
 * name     - full user name ('Name Surname')
    
the 'name' column is optional, other columns will be ignored

Known restrictions:
 * this script only works with MySQL
 * mailbox paths are hardcoded to domain/username/ 
    """

    parser = argparse.ArgumentParser(prog="import_users_from_csv.py", description="import .csv file for PostfixAdmin into a MySQL DB", epilog=epilog)
    parser.add_argument('filename')
    parser.add_argument('-t', "--test",   help="test run, do not insert, just print", action="store_true")
    parser.add_argument('-u', "--dbuser", help="MySQL DB Username", default="postfixadmin")
    parser.add_argument('-p', "--dbpass", help="MySQL DB Password", default="")
    parser.add_argument('-D', "--dbname", help="MySQL DB Name", default="postfixadmin")
    parser.add_argument('-H', "--dbhost", help="MySQL DB Host", default="localhost")
    parser.add_argument('-q', "--quota",  help="Quota in Mb (0 => no limit)", default=0)
    parser.add_argument('-n', "--seedchars",   help="number of chars in seed", default=8)
    parser.add_argument('-d', "--debug",  help="debug info on", action="store_true")
    parser.add_argument('-A', "--defaultaliases", help="create default alias for each domain", action="store_true")

    args = parser.parse_args()

    filename = args.filename

    print("Args: %r" % args)

    MYSQLUSER = args.dbuser
    MYSQLPASSWORD = args.dbpass
    MYSQLDB = args.dbname
    MYSQLHOST = args.dbhost

    quota = args.quota
   
    seed_len = args.seedchars

    test_run = args.test
    debug = args.debug
    defaultalias = args.defaultaliases

except Exception as e:
    print("Failed to parse args?: %s" % e)
    sys.exit(1)

try:
    connection = MySQLdb.connect(host=MYSQLHOST, user=MYSQLUSER, 
                                 db=MYSQLDB, passwd=MYSQLPASSWORD)
except MySQLdb.MySQLError as e:
    print("Database connection error, %r" % e)
    sys.exit(1)

cursor = connection.cursor()

#
# Main body
#
NOW = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

# read and convert CSV data
lista = csv.reader(open(filename))

def gen_seed(seed_len, chars):
    return '$1$'+''.join([random.choice(chars) for _ in range(seed_len)])+'$'

def insert_record(cursor,table,record):

    columns = record.keys()
    query = "INSERT INTO " + table + "(" + ','.join(columns) + ") VALUES (" + ','.join(len(columns)*['%s']) + ")"

    if test_run:
        print("Would run SQL : %s ... with args: %r" % ( query, record.values() ))
        return 0

    try:
        cursor.execute(query, record.values())
        return 0
    except MySQLdb.MySQLError as e:
        print("Database insertion error: %s. Record was: %r, Query was: %s" % (e, record.values, query))

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

chars = string.ascii_letters + string.digits

# loop over the CSV 
for row in lista:

    if debug:
        print("Handling row: %r" % row)
    # create domain if it does not exists

    csv_user = row[0]
    csv_pass = row[1]
    csv_domain = row[2]
    csv_name =row[3]

    if csv_domain in domain_list:
        if debug:
            print("Domain " + csv_domain + " already exixts")
    else:
        # Surely the domain could exist in our db, so this would just fail?
        domain_list[csv_domain] = 1
        domain['domain'] = csv_domain
        if debug:
            print("Inserting domain: %s" % domain)
        else:
            insert_record(cursor,'domain',domain)
            if defaultalias:
                for i in def_alias:
                    aliases['address']= i+'@' + csv_domain
                    aliases['goto']= aliases['address']
                    aliases['domain'] = csv_domain
                    if debug:
                        print("Inserting alias: %s" % aliases)
                    insert_record(cursor,'alias',aliases)

    # build query data for mailbox table
    mailbox['username']= csv_user+'@'+csv_domain
    encpass=crypt(csv_pass, gen_seed(seed_len,chars))
    mailbox['password'] = encpass
    mailbox['name'] = csv_name
    mailbox['maildir'] = csv_domain + '/' + csv_user +'/'
    mailbox['local_part'] = csv_user
    mailbox['domain'] = csv_domain

    # build query data for alias table
    aliases['address']= mailbox['username']
    aliases['goto']= mailbox['username']
    aliases['domain'] = csv_domain

    # inserting data for mailbox (and relate alias)
    if debug:
        print("Inserting mailbox: %s, %s" % ( mailbox, aliases ))

    insert_record(cursor,'mailbox',mailbox)
    insert_record(cursor,'alias',aliases)


sys.exit(0)
