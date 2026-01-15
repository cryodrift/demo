# Demo App 

- -sessionuser is used to simmulate a authenticated user 

## installation with (composer does this for you)

php index.php -echo /sys install

php index.php -echo -sessionuser="test@localhost.lan" /sys modules -dir=src\demo
 
## import testdata

php index.php -echo -sessionuser="test@localhost.lan" /demo/cli googlebooks -q="subject:\"Fantasy\"" -page=1 -write=true

## import images

php index.php -echo -sessionuser="test@localhost.lan" /demo/cli importimages -pattern="*.jpg|*.jpeg"  -path="full path to your media files" -skip


## run with

php index.php -echo -sessionuser="test@localhost.lan" /demo

php index.php -echo -sessionuser="test@localhost.lan" /demo/cli

php index.php -echo -sessionuser="test@localhost.lan" /demo/api


