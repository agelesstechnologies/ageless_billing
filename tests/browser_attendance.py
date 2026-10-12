"""Static browser UI tests with mocked geolocation/fetch and intercepted saved events.
Does not run Laravel, SQL, physical GPS, native browser permission prompts or ESP.
No network navigation is needed. Python + Playwright + Chromium required.
"""
from pathlib import Path
from playwright.sync_api import sync_playwright
import re, json, os, shutil
P=Path(__file__).resolve().parents[1]
checks=[]
def check(condition,name):
 if not condition:raise AssertionError(name)
 checks.append(name)
def markup(file,live=False):
 html=(P/file).read_text()
 for name in ('app','premium','staff'):
  html=html.replace(f'<link rel="stylesheet" href="public/assets/{name}.css">','<style>'+(P/f'public/assets/{name}.css').read_text()+'</style>')
 for name in ('app','attendance'):
  html=html.replace(f'<script src="public/assets/{name}.js" defer></script>','<script>'+(P/f'public/assets/{name}.js').read_text()+'</script>')
 if live:html=html.replace('data-preview="true"','')
 return html
setup=r"""
window.__geoCalls=0;window.__geoError=0;window.__accuracy=12;window.__requests=[];window.__responses=[];window.__saved=[];window.__secure=true;
Object.defineProperty(window,'isSecureContext',{configurable:true,get(){return window.__secure;}});
Object.defineProperty(navigator,'geolocation',{configurable:true,value:{getCurrentPosition(success,error,options){window.__geoCalls++;window.__geoOptions=options;if(window.__geoError){error({code:window.__geoError});return;}success({coords:{latitude:10.0001,longitude:76.3001,accuracy:window.__accuracy}});}}});
Object.defineProperty(navigator,'onLine',{configurable:true,value:true});
window.fetch=async(url,opts)=>{window.__requests.push({url,headers:opts.headers,body:JSON.parse(opts.body)});const next=window.__responses.shift()||{status:200,data:{ok:true,message:'Punch recorded.',redirect:'/staff'}};if(next==='networkfail')throw new TypeError('Mocked lost response');return {ok:next.status>=200&&next.status<300,status:next.status,json:async()=>next.data};};
window.confirm=()=>true;
document.addEventListener('parkdesk:attendance-saved',e=>{e.preventDefault();window.__saved.push(e.detail);},true);
"""
with sync_playwright() as pw:
 browser=pw.chromium.launch(headless=True,executable_path=os.environ.get('CHROMIUM_PATH') or shutil.which('chromium') or shutil.which('chromium-browser'))
 errors=[]
 for filename,slug,views in [('PREVIEW-CRM.html','CRM-admin',[(1460,1480),(390,844)]),('PREVIEW-STAFF.html','Staff-mobile',[(390,844),(360,800),(1024,1000)]),('PREVIEW-LOGIN.html','Login',[(1440,1020),(390,844)])]:
  for width,height in views:
   context=browser.new_context(viewport={'width':width,'height':height},device_scale_factor=1)
   page=context.new_page();page.on('pageerror',lambda e:errors.append(str(e)));page.set_content(markup(filename));page.wait_for_timeout(80)
   check(page.evaluate('document.documentElement.scrollWidth<=innerWidth'),f'{slug} no horizontal page overflow at {width}px')
   if filename=='PREVIEW-CRM.html' and width==390:
    page.locator('#mobile-toggle').click();check(page.locator('#sidebar').evaluate("e=>e.classList.contains('open')"),'CRM mobile menu opens');page.keyboard.press('Escape')
   if filename=='PREVIEW-STAFF.html':check(page.locator('.staff-tabs a').count()==4,f'four staff tabs at {width}px')
   if (filename=='PREVIEW-STAFF.html' and width==390) or (filename!='PREVIEW-STAFF.html' and width>=1400):page.screenshot(path=str(P/f'docs/{slug}.png'),full_page=False)
   if filename=='PREVIEW-LOGIN.html' and width==390:page.screenshot(path=str(P/'docs/Login-mobile.png'),full_page=False)
   context.close()
 check(not errors,'no JavaScript runtime errors in visual previews')
 def new_case(live=True,custom=None):
  context=browser.new_context(viewport={'width':390,'height':844});page=context.new_page();body=custom or markup('PREVIEW-STAFF.html',live)
  page.set_content(body.replace('</head>','<script>'+setup+'</script></head>'));return context,page
 def n(page):return page.evaluate('__requests.length')
 def feedback(page):return page.locator('[data-punch-feedback]').inner_text()
 context,page=new_case(live=False);page.locator('[data-check-location]').click();page.locator('[data-punch]').click()
 check(page.evaluate('__geoCalls')==0 and n(page)==0,'static preview never requests location or POSTs, even on clicks')
 check('UI preview only' in feedback(page),'static preview reports no real writes');context.close()
 context,page=new_case()
 check(page.evaluate('__geoCalls')==0,'no location request on real UI page load')
 page.locator('[data-check-location]').click();check(page.evaluate('__geoCalls')==1 and n(page)==0,'Check location displays fix but does not send a punch')
 check(page.locator('[data-location-title]').inner_text()=='Location captured','captured location feedback')
 check(page.evaluate('__geoOptions.maximumAge')==0 and page.evaluate('__geoOptions.enableHighAccuracy'),'fresh high-accuracy position requested')
 page.locator('[data-punch]').click();page.wait_for_function('__saved.length===1');payload=page.evaluate('__requests[0].body')
 check(n(page)==1 and payload['action']=='in','punch-in sends correct action')
 check(payload['latitude']==10.0001 and payload['accuracy_m']==12,'punch sends reported fix and accuracy')
 check(bool(re.fullmatch(r'[0-9a-f-]{36}',payload['request_id'])),'punch has UUID request ID')
 check(not any(k in payload for k in ['user_id','work_site_id','punched_at','started_at']),'client does not choose employee, site or authoritative timestamp')
 check(page.evaluate('__requests[0].headers["X-CSRF-TOKEN"]')=='preview-token','CSRF token is sent')
 check(page.locator('[data-punch]').is_disabled(),'successful saved event prevents a second click until state refresh');context.close()
 for code,label in [(1,'permission denied'),(2,'location unavailable'),(3,'location timeout')]:
  context,page=new_case();page.evaluate(f'__geoError={code}');page.locator('[data-punch]').click();check(n(page)==0 and page.locator('[data-punch-feedback]').is_visible(),f'{label} does not send punch');context.close()
 context,page=new_case();page.evaluate('__accuracy=101');page.locator('[data-punch]').click();check(n(page)==0 and 'No punch was sent' in feedback(page),'poor-accuracy in-punch blocked before HTTP');context.close()
 context,page=new_case();page.evaluate('__secure=false');page.locator('[data-punch]').click();check(page.evaluate('__geoCalls')==0 and 'HTTPS' in feedback(page),'insecure-context branch gives HTTPS instruction');context.close()
 context,page=new_case();page.evaluate("__responses=[{status:422,data:{errors:{location:['You are outside the permitted site radius.']}}},{status:422,data:{errors:{location:['You are outside the permitted site radius.']}}}]")
 page.locator('[data-punch]').click();check('outside the permitted site radius' in feedback(page),'server geofence error displayed as text')
 page.locator('[data-punch]').click();sent=page.evaluate('__requests');check(len(sent)==2 and sent[0]['body']['request_id']!=sent[1]['body']['request_id'],'definitive validation rejection permits a fresh request');context.close()
 context,page=new_case();page.evaluate("__responses=['networkfail',{status:200,data:{ok:true,replay:true,redirect:'/staff'}}]")
 page.locator('[data-punch]').click();check('server may have saved' in feedback(page),'lost response warns that server may have saved the punch')
 check(page.evaluate('__geoCalls')==1,'one GPS request before uncertain response')
 page.locator('[data-punch]').click();sent=page.evaluate('__requests');check(len(sent)==2 and sent[0]['body']==sent[1]['body'],'retry preserves entire immutable payload and request ID')
 check(page.evaluate('__geoCalls')==1,'uncertain retry does not recapture location');context.close()
 html=markup('PREVIEW-STAFF.html',True).replace('data-action="in"','data-action="out"').replace('data-session-id=""','data-session-id="42"')
 context,page=new_case(custom=html);page.evaluate('__accuracy=250');page.locator('[data-punch]').click();sent=page.evaluate('__requests[0].body')
 check(sent['action']=='out' and sent['session_id']==42,'punch-out is bound to an explicit session')
 check(sent['accuracy_m']==250,'poor-accuracy out-punch is sent to the server for review');context.close()
 context,page=new_case();page.evaluate("__responses=[{status:419,data:{message:'Expired'}}]");page.locator('[data-punch]').click();check('sign in again' in feedback(page),'expired session gives re-login guidance');context.close()
 context,page=new_case();page.evaluate("Object.defineProperty(navigator,'onLine',{value:false});");page.locator('[data-punch]').click();check(n(page)==0 and 'offline' in feedback(page),'offline UI does not queue or POST attendance');context.close()
 browser.close()
(P/'docs/browser-attendance-checks.json').write_text(json.dumps({'checks_passed':checks,'count':len(checks),'scope':'Static Chromium previews with real shared CSS/JS; browser geolocation/fetch/security state mocked; saved-event default navigation intercepted. NOT Laravel, database, real permission prompts or physical GPS.'},indent=2))
print('PASS:',len(checks),'browser/UI assertions (mocked GPS/HTTP; no Laravel).')
