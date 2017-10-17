#!/bin/bash

if [ "`id -u`" != "0" ]; then
  echo "Script must be run as root, you may experience errors"
fi

pushd "`dirname $0`/.." > /dev/null
SCRIPTPATH=`pwd -P`
popd > /dev/null

ROOT_PATH="${SCRIPTPATH}"
WWW_USER=http
DEV_GROUP=pounard

# 1 is write mode for group (www-user will be the user)
# 2 is write mode for user (www-user will be the group)
MODE=1

# When set to 1 this will add extreme write rights access
# for Drush being able to reinstall the site
DRUSH=0

# Webroot folder name relative to script run
WWW_DIR="web"

# Force rights to be set without index.php file
FORCE=0

SUB_PATH="."

while getopts "fis:g:w:u:d" opt
do
  if [ $opt = "i" ]; then
    echo "Full access will be given to user"
    MODE=2
  fi
  if [ $opt = "g" ]; then
    DEV_GROUP=$OPTARG
  fi
  if [ $opt = "u" ]; then
    WWW_USER=$OPTARG
  fi
  if [ $opt = "d" ]; then
    DRUSH=1
  fi
  if [ $opt = "f" ]; then
    FORCE=1
  fi
  if [ $opt = "s" ]; then
    echo "Working in $OPTARG"
    SUB_PATH=$OPTARG
  fi
  if [ $opt = "w" ]; then
    echo "Using $OPTARG as public webroot"
    WWW_DIR=$OPTARG
  fi
done

# Security
if [ ! $FORCE ]; then
  if [ ! -f "${ROOT_PATH}/${WWW_DIR}/index.php" ]; then
    echo "You are not in a project webroot!"
    exit 1
  fi
fi

WORK_DIR="$ROOT_PATH/$SUB_PATH"

# Ignore
IGNORE="bin"

# The last two are for ZF2 projects
WWW_WRITABLE="log
tmp
var/cache
var/log
var/logs
var/tmp
app/cache
app/logs
$WWW_DIR/sites/*/files
$WWW_DIR/sites/*/translations"

DRUSH_WRITABLE="
$WWW_DIR/sites/all/libraries
$WWW_DIR/sites/*/settings.php
$WWW_DIR/sites/"

BIN="bin/*"

if [ "$MODE" == "1" ]; then
  r_normal="u+r,u-w,g+rw,o-rwx"
  r_write="u+w"
else	
  r_normal="g+r,g-w,u+rw,o-rwx"
  r_write="g+w"
fi

echo "Giving rights to $WWW_USER:$DEV_GROUP on $SUB_PATH"

find $ROOT_PATH -type d -exec chmod g+x,u+x '{}' \;
#find $ROOT_PATH -type f -exec chmod g-x,u-x '{}' \;
chown -R $WWW_USER:$DEV_GROUP $ROOT_PATH
chmod -R $r_normal $ROOT_PATH

for dir in $WWW_WRITABLE; do
  if [ -d $dir ]; then
    echo " * giving write access for www to $dir"
    chmod -R $r_write $ROOT_PATH/$dir
  else
    echo " * skipping non existing dir $dir"
  fi
done

for f in $BIN; do
  if [ -e $f ]; then
    echo " * giving execute flag to $f"
    chmod -R +x $f
  fi
done

if [ $DRUSH ]; then
  echo "Restoring excessive write access for Drush"
  chmod u+w,g+w $DRUSH_WRITABLE
fi

if [ -d "$ROOT_PATH/bin" ]; then
  echo "Restoring executable flag to scripts"
  chmod u+x $ROOT_PATH/bin/*.sh
fi

#EOF

