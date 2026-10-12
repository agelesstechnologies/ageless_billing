"""Optional static-frontend checks. No Laravel server or real billing is exercised.
Requires: pip install playwright; python -m playwright install chromium
Set CHROMIUM_PATH to use an existing Chromium installation.
"""
from pathlib import Path
from playwright.sync_api import sync_playwright
import os, json, shutil
P=Path(__file__).resolve().parents[1]
checks=[]
def check(cond,name):
 if not cond:raise AssertionError(name)
 checks.append(name)
with sync_playwright() as pw:
 executable=os.environ.get('CHROMIUM_PATH') or shutil.which('chromium') or shutil.which('chromium-browser')
 browser=pw.chromium.launch(headless=True, **({'executable_path':executable} if executable else {}))
 page=browser.new_page(viewport={'width':1460,'height':1050},device_scale_factor=1)
 errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
 markup=(P/'PREVIEW.html').read_text().replace('<link rel="stylesheet" href="public/assets/app.css">','<style>'+(P/'public/assets/app.css').read_text()+'</style>').replace('<script src="public/assets/app.js" defer></script>','<script>'+(P/'public/assets/app.js').read_text()+'</script>')
 markup=markup.replace('<link rel="stylesheet" href="public/assets/premium.css">','<style>'+(P/'public/assets/premium.css').read_text()+'</style>')
 page.set_content(markup);page.wait_for_timeout(300)
 check(page.locator('#checkout-button').is_disabled(),'empty cart cannot submit')
 page.locator('[data-add="2"]').click();page.locator('[data-add="1"]').click();page.locator('[data-add="1"]').click()
 check(page.locator('#total').inner_text()=='₹1,000.00','multi-item total')
 check(page.locator('#points-total').inner_text()=='800 pts','multi-item points')
 check(page.locator('#item-count').inner_text()=='3','quantity counter')
 check(page.locator('#cart-fields input').count()==4,'server form fields generated')
 page.locator('#discount').fill('100');check(page.locator('#total').inner_text()=='₹900.00','discount total')
 page.locator('#received').fill('1000');check(page.locator('#change-note').inner_text()=='Change: ₹100.00','cash change')
 page.locator('#received').fill('100');check(page.locator('#checkout-button').is_disabled(),'insufficient cash blocks checkout')
 page.locator('[name=payment_method][value=upi]').check();check(page.locator('#cash-field').is_hidden() and page.locator('#reference-field').is_visible(),'payment method fields')
 check(page.locator('#checkout-button').is_enabled(),'cash amount ignored for UPI')
 page.locator('[data-filter="package"]').click();check(page.locator('[data-card-id]:visible').count()==5,'category filter')
 page.locator('#catalog-search').fill('dome');check(page.locator('[data-card-id]:visible').count()==1,'search filter')
 page.locator('#catalog-search').fill('');page.locator('[data-filter="all"]').click()
 page.locator('#discount').fill('0');page.locator('[name=payment_method][value=cash]').check();page.locator('#received').fill('1000')
 check(not errors,'no JavaScript runtime errors')
 page.set_viewport_size({'width':1460,'height':1470});page.evaluate('document.activeElement.blur();window.scrollTo(0,0)');page.screenshot(path=str(P/'docs/UI-preview.png'),full_page=True)
 page.set_viewport_size({'width':390,'height':844});page.wait_for_timeout(150)
 check(page.evaluate('document.documentElement.scrollWidth <= window.innerWidth'),'mobile has no horizontal overflow')
 page.locator('#mobile-toggle').click();check(page.locator('#sidebar').evaluate("e=>e.classList.contains('open')"),'mobile navigation')
 page.keyboard.press('Escape');page.screenshot(path=str(P/'docs/UI-mobile.png'),full_page=True)
 page.locator('#clear-cart').click();check(page.locator('#checkout-button').is_disabled(),'clear cart disables checkout')
 browser.close()
(P/'docs/browser-checks.json').write_text(json.dumps({'checks_passed':checks,'count':len(checks),'runtime':'Chromium, static interface preview using actual app.js and CSS','not_tested':'Laravel HTTP server, database, QR generation and hardware'},indent=2))
print('PASS:',len(checks),'browser/UI checks')
