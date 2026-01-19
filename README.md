# Demo App 

This creates a Single Page App featuring a simple Webshop


## Installation

composer require cryodrift/demo

## installation with (composer does this for you)

php index.php -echo /sys install

## run installers (runs packagedir Cli::install)

php index.php -echo -sessionuser="test@localhost.lan" /sys modules 
 
## import testdata

php index.php -echo -sessionuser="test@localhost.lan" /demo/cli googlebooks -q="subject:\"Fantasy\"" -page=1 -write=true

## import images

php index.php -echo -sessionuser="test@localhost.lan" /demo/cli importimages -pattern="*.jpg|*.jpeg"  -path="full path to your media files" -skip


## run with

php index.php -echo -sessionuser="test@localhost.lan" /demo

php index.php -echo -sessionuser="test@localhost.lan" /demo/cli

php index.php -echo -sessionuser="test@localhost.lan" /demo/api
                  
## ENV settings

USER_HTTPS="0"
USER_USE2FA="0"
USER_HIDELOGIN="0"

## start Server

php vendor\bin\cryodrift.php -sessionuser="test@localhost.lan" -echo /sys serv -index=vendor\bin\cryodrift.php

open in your browser localhost:port/demo



