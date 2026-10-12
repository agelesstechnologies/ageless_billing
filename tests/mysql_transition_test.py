#!/usr/bin/env python3
"""Use an initialized, marked, disposable MySQL datadir. Never use a live server."""
import argparse, decimal, hashlib, json, os, shutil, sqlite3, subprocess, time, uuid
from pathlib import Path

p=argparse.ArgumentParser()
for name in ['template','sqlite_source','camera_source','mysql_runtime','server_dir','php','output']:
 p.add_argument('--'+name.replace('_','-'),required=True)
a=p.parse_args();template=Path(a.template).resolve();source=Path(a.sqlite_source).resolve();camera_source=Path(a.camera_source).resolve();runtime=Path(a.mysql_runtime).resolve();server_dir=Path(a.server_dir).resolve();out=Path(a.output).resolve()
if out.exists() or not (server_dir/'.ageless-disposable-mysql').is_file():raise SystemExit('Use a NEW output directory and a marked disposable MySQL datadir.')
out.mkdir();checks=[];prefix='ageless_test_'+uuid.uuid4().hex[:10]
tables=['work_sites','users','settings','packages','rides','machines','park_passes','sales','sale_items','redemptions','point_entries','audit_logs','attendance_sessions','attendance_punches','attendance_corrections']
def literal(value):return "'"+str(value).replace('\\','\\\\').replace("'","\\'")+"'"
def query(code,database=None):
 dsn='mysql:host=127.0.0.1;port=3307;charset=utf8mb4'+(';dbname='+database if database else '')
 r=subprocess.run([a.php,'-r',"$p=new PDO("+literal(dsn)+",'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);"+code],capture_output=True,text=True)
 if r.returncode:raise RuntimeError('Disposable MySQL query failed: '+r.stderr[-500:])
 return json.loads(r.stdout) if r.stdout else None
def checked(label):checks.append(label);print('PASS',label,flush=True)
def ignore(directory,names):
 parts=Path(directory).parts;leaf=Path(directory).name
 excluded={'.env','.pio','.git','.phpunit.result.cache','__pycache__'}
 if leaf=='database':excluded.update(n for n in names if n.startswith('database.sqlite'))
 if 'storage' in parts and leaf in ['sessions','views','cache','logs']:excluded.update(n for n in names if not n.startswith('.'))
 if 'bootstrap' in parts and leaf=='cache':excluded.update(n for n in names if n!='.gitignore')
 return excluded
def fixture(name):
 db=prefix+'_'+name;query('$p->exec('+literal('CREATE DATABASE `'+db+'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')+');')
 dest=out/name;shutil.copytree(template,dest,ignore=ignore)
 (dest/'.env').write_text('APP_NAME=Ageless\nAPP_ENV=testing\nAPP_KEY=base64:eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHg=\nAPP_DEBUG=false\nAPP_URL=http://127.0.0.1:8894\nDB_CONNECTION=mysql\nDB_HOST=127.0.0.1\nDB_PORT=3307\nDB_DATABASE='+db+'\nDB_USERNAME=root\nDB_PASSWORD=""\nSESSION_DRIVER=file\nCACHE_STORE=file\nBCRYPT_ROUNDS=4\n')
 (dest/'.ageless-disposable-test').write_text('Private MySQL test fixture. Never deploy.\n')
 (dest/'server-router.php').write_text("<?php $path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);if($path!=='/' && is_file(__DIR__.'/public'.$path))return false;require __DIR__.'/public/index.php';\n")
 run(dest,'migrate','--force');return dest,db
def run(dest,*args,ok=True):
 r=subprocess.run([a.php,'artisan',*map(str,args)],cwd=dest,capture_output=True,text=True)
 if (r.returncode==0)!=ok:raise RuntimeError('Unexpected command result: '+str(args)+'\n'+r.stdout[-1000:]+r.stderr[-500:])
 return r.stdout
def rows(db,table):return query('echo json_encode($p->query('+literal('SELECT * FROM `'+table+'` ORDER BY id')+')->fetchAll(PDO::FETCH_ASSOC));',db)
def counts(db):return {t:len(rows(db,t)) for t in tables}
env=os.environ.copy();env['LD_LIBRARY_PATH']=str(runtime/'usr/lib/x86_64-linux-gnu')
args=[str(runtime/'usr/sbin/mysqld'),'--no-defaults','--user=root','--basedir='+str(runtime/'usr'),'--datadir='+str(server_dir/'data'),'--socket=','--pid-file='+str(server_dir/'mysql.pid'),'--bind-address=127.0.0.1','--port=3307','--mysqlx=0','--secure-file-priv=NULL','--performance-schema=OFF','--innodb-buffer-pool-size=16M','--max-connections=12','--table-open-cache=64','--log-error='+str(server_dir/'server.log')]
server=subprocess.Popen(args,env=env,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
try:
 for i in range(60):
  try:
   info=query('echo json_encode($p->query("SELECT VERSION() AS version, @@datadir AS datadir")->fetch(PDO::FETCH_ASSOC));');break
  except RuntimeError:
   if server.poll() is not None:raise RuntimeError('Disposable MySQL server stopped.')
   time.sleep(.1)
 else:raise RuntimeError('Disposable MySQL server did not start.')
 assert Path(info['datadir']).resolve()==(server_dir/'data').resolve()
 checked('Native MySQL 8 server uses the marked disposable datadir')
 dest,db=fixture('import');before=counts(db);digest=hashlib.sha256(source.read_bytes()).hexdigest()
 preview=run(dest,'park:import-sqlite',source);assert 'Preview only' in preview and counts(db)==before
 checked('Import preview reads supplied SQLite data without writing MySQL or SQLite')
 message=run(dest,'park:import-sqlite',source,'--execute',ok=False);assert 'artisan down' in message and counts(db)==before
 checked('Copy requires maintenance mode before changing the destination')
 run(dest,'down');run(dest,'park:import-sqlite',source,'--execute');run(dest,'up')
 with sqlite3.connect('file:'+str(source)+'?mode=ro',uri=True) as c:
  c.row_factory=sqlite3.Row
  json_fields={'data','receipt_settings','details','site_snapshot','before_values','after_values'}
  for table in tables:
   expected=[dict(x) for x in c.execute('SELECT * FROM "'+table+'" ORDER BY id')];actual=rows(db,table);assert len(expected)==len(actual),table
   for old,new in zip(expected,actual):
    for key,value in old.items():
     other=new[key]
     if key in json_fields and value is not None:assert json.loads(value)==json.loads(other),(table,key)
     elif isinstance(value,(int,float)) and other is not None:assert decimal.Decimal(str(value))==decimal.Decimal(str(other)),(table,key)
     else:assert value==other,(table,key)
 assert hashlib.sha256(source.read_bytes()).hexdigest()==digest
 checked('All 15 supplied tables retain IDs, hashes, balances, bills, history and values on MySQL')
 snapshot={t:rows(db,t) for t in tables};run(dest,'down');message=run(dest,'park:import-sqlite',source,'--execute',ok=False);assert 'already contains ERP data' in message;assert snapshot=={t:rows(db,t) for t in tables};run(dest,'up')
 checked('Repeat import refuses to overwrite or duplicate existing ERP records')
 missing=out/'missing.sqlite';run(dest,'park:import-sqlite',missing,ok=False);assert not missing.exists()
 checked('Missing source is rejected without creating a replacement SQLite file')
 bad=out/'invalid-foreign-key.sqlite';shutil.copy2(source,bad)
 with sqlite3.connect(bad) as c:c.execute('UPDATE point_entries SET user_id=999999 WHERE id=(SELECT MIN(id) FROM point_entries)')
 fail,faildb=fixture('rollback');snap={t:rows(faildb,t) for t in tables};run(fail,'down');message=run(fail,'park:import-sqlite',bad,'--execute',ok=False);assert 'rolled back' in message;assert snap=={t:rows(faildb,t) for t in tables}
 checked('MySQL foreign-key failure rolls back every copied table and restores the migration placeholder')
 other,otherdb=fixture('site');query('$p->exec("UPDATE work_sites SET name = \'Existing configured site\'");',otherdb);run(other,'down');message=run(other,'park:import-sqlite',source,'--execute',ok=False);assert 'configured work sites' in message;assert rows(otherdb,'work_sites')[0]['name']=='Existing configured site'
 checked('A configured destination work site is retained and import stops')
 camera,cameradb=fixture('camera');run(camera,'down');run(camera,'park:import-sqlite',camera_source,'--execute');run(camera,'up')
 checked('Synthetic administrator password hash imports unchanged and camera fixture is ready')
 childenv=os.environ.copy();childenv.update({'AGELESS_TEST_ERP':str(camera),'AGELESS_TEST_PHP':a.php,'AGELESS_TEST_OUTPUT':str(out/'http'),'AGELESS_TEST_DECODER':os.environ['AGELESS_TEST_DECODER']})
 script=template/'firmware/AgelessCameraReader/tests/camera_erp_integration.cjs'
 subprocess.run(['node',str(script)],env=childenv,check=True)
 http=json.loads((out/'http/camera-erp-checks.json').read_text());assert http['database_driver']=='mysql'
 summary={'mysql_version':info['version'],'import_checks_passed':len(checks),'import_checks':checks,'supplied_counts':counts(db),'http_scenarios_passed':http['passed'],'http':http,'scope':'Private MySQL 8 datadir; supplied SQLite data read-only; separate synthetic HTTP fixture. GPS input and reader ACKs simulated; no live deployment.'}
 (out/'mysql-transition-checks.json').write_text(json.dumps(summary,indent=2)+'\n');print('PASS',len(checks),'MySQL import checks and',http['passed'],'real HTTP scenarios',flush=True)
finally:
 server.terminate()
 try:server.wait(timeout=5)
 except subprocess.TimeoutExpired:server.kill()
