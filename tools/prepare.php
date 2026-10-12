<?php
// Safe to rerun. This never deletes a database or replaces an existing .env.
$root=dirname(__DIR__);chdir($root);
if(version_compare(PHP_VERSION,'8.2.0','<')){fwrite(STDERR,"PHP 8.2 or newer is required.\n");exit(1);}
if(!is_file('.env')){if(!copy('.env.example','.env')){fwrite(STDERR,"Cannot create .env. Check folder permissions.\n");exit(1);}echo "Created .env from the supplied example.\n";}
$env=file_get_contents('.env');$mysql=preg_match('/^\s*DB_CONNECTION\s*=\s*["\']?mysql["\']?\s*(?:#.*)?$/im',$env);
$required=['ctype','curl','dom','fileinfo','filter','hash','mbstring','openssl','pcre','PDO','session','tokenizer','xml',$mysql?'pdo_mysql':'pdo_sqlite'];$missing=[];
foreach($required as $ext)if(!extension_loaded($ext))$missing[]=$ext;
if($missing){fwrite(STDERR,"Enable these extensions in your CLI php.ini: ".implode(', ',$missing)."\nLoaded php.ini: ".(php_ini_loaded_file()?:'(none)')."\n");exit(1);}
foreach(['bootstrap/cache','storage/app/public','storage/framework/cache/data','storage/framework/sessions','storage/framework/views','storage/logs','database'] as $dir){if(!is_dir($dir)&&!mkdir($dir,0775,true)){fwrite(STDERR,"Cannot create $dir.\n");exit(1);}}
if(!$mysql&&!is_file('database/database.sqlite')&&!touch('database/database.sqlite')){fwrite(STDERR,"Cannot create SQLite file.\n");exit(1);}
echo "Environment and writable folders prepared. Existing data was preserved.\n";
