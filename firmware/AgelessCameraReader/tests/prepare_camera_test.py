#!/usr/bin/env python3
"""Prepare a NEW synthetic test ERP. Refuses to reuse any existing output directory."""
import argparse, shutil, subprocess
from pathlib import Path

p=argparse.ArgumentParser()
p.add_argument('--source',required=True,help='Updated Laravel project with vendor dependencies')
p.add_argument('--output',required=True,help='New, disposable directory; must not exist')
p.add_argument('--php',default='php')
a=p.parse_args();source=Path(a.source).resolve();dest=Path(a.output).resolve()
if dest.exists():raise SystemExit('Output already exists. Choose a new empty path; no database was changed.')
if not (source/'vendor/autoload.php').is_file():raise SystemExit('Install the source project Composer dependencies first.')
def ignore(directory,names):
 parts=Path(directory).parts;leaf=Path(directory).name
 exclude={'.pio','.env','.git','.phpunit.result.cache','.ageless-disposable-test'}
 if leaf=='database':exclude.update({'database.sqlite','database.sqlite-wal','database.sqlite-shm'})
 if 'storage' in parts and leaf in ('sessions','views','cache','logs'):exclude.update(n for n in names if not n.startswith('.'))
 if 'bootstrap' in parts and leaf=='cache':exclude.update(n for n in names if n!='.gitignore')
 return exclude
shutil.copytree(source,dest,ignore=ignore)
(dest/'database/database.sqlite').touch()
(dest/'.env').write_text('APP_NAME=Ageless\nAPP_ENV=testing\nAPP_KEY=base64:eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHg=\nAPP_DEBUG=false\nAPP_URL=http://127.0.0.1:8894\nDB_CONNECTION=SQLite\nDB_DATABASE="'+str(dest/'database/database.sqlite')+'"\nSESSION_DRIVER=file\nCACHE_STORE=file\nBCRYPT_ROUNDS=4\n')
(dest/'.ageless-disposable-test').write_text('Only synthetic test data. Never deploy this directory.\n')
(dest/'server-router.php').write_text("<?php $path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);if($path!=='/' && is_file(__DIR__.'/public'.$path))return false;require __DIR__.'/public/index.php';\n")
(dest/'seed-test.php').write_text(r'''<?php
require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
App\Models\Setting::firstOrCreate(['id'=>1],['data'=>App\Models\Setting::defaults()]);
$site=App\Models\WorkSite::create(['name'=>'Camera QR Test Site','timezone'=>'Asia/Kolkata','latitude'=>10.0,'longitude'=>76.3,'radius_m'=>200,'max_accuracy_m'=>100,'active'=>true,'enforce_geofence'=>true]);
App\Models\User::create(['name'=>'Camera Test Admin','email'=>'  ADMIN@Example.test  ','password'=>'TestPassword123!','role'=>'admin','active'=>true,'work_site_id'=>$site->id,'attendance_enabled'=>true]);
App\Models\Package::create(['name'=>'Camera test points','kind'=>'package','price_paise'=>50000,'points'=>10000,'validity_hours'=>24,'active'=>true,'color'=>'#7a31b7','icon'=>'ticket']);
echo "Disposable fixture seeded. No real accounts used.\n";
''')
subprocess.run([a.php,'artisan','migrate','--force'],cwd=dest,check=True)
subprocess.run([a.php,'seed-test.php'],cwd=dest,check=True)
print('Disposable ERP:',dest)
