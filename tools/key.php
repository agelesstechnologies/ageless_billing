<?php
chdir(dirname(__DIR__));
if(!is_file('.env')){fwrite(STDERR,"Run php tools/prepare.php first.\n");exit(1);}
$env=file_get_contents('.env');
if(preg_match('/^APP_KEY\s*=\s*["\']?(?:base64:)?[A-Za-z0-9+\/=]{20,}/m',$env)){echo "Existing APP_KEY preserved.\n";exit(0);}
passthru(escapeshellarg(PHP_BINARY).' artisan key:generate --force',$code);exit($code);
