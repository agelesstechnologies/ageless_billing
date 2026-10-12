'use strict';
(()=>{
 const $=(s)=>document.querySelector(s);
 $('#mobile-toggle')?.addEventListener('click',()=>$('#sidebar')?.classList.toggle('open'));
 document.addEventListener('keydown',e=>{if(e.key==='Escape')$('#sidebar')?.classList.remove('open');});
 document.addEventListener('pointerdown',e=>{const sidebar=$('#sidebar'),toggle=$('#mobile-toggle');if(sidebar?.classList.contains('open')&&!sidebar.contains(e.target)&&!toggle?.contains(e.target))sidebar.classList.remove('open');});
 document.querySelectorAll('form[data-confirm]').forEach(f=>f.addEventListener('submit',e=>{if(!confirm(f.dataset.confirm))e.preventDefault();}));
 document.querySelectorAll('[data-print]').forEach(b=>b.addEventListener('click',()=>window.print()));
 document.querySelectorAll('[data-copy]').forEach(b=>b.addEventListener('click',async()=>{const el=document.getElementById(b.dataset.copy);try{await navigator.clipboard.writeText(el.textContent.trim());b.textContent='Copied';}catch{alert('Select and copy the code shown on screen.');}}));
 const form=$('#pos-form'); if(!form)return;
 const catalog=JSON.parse(form.dataset.catalog);const cart=new Map();let filter='all';
 const money=v=>'₹'+(v/100).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});
 const decimal=s=>{if(!s)return 0;if(!/^\d+(\.\d{1,2})?$/.test(s))return NaN;const [whole,fraction='']=s.split('.');return Number(whole)*100+Number(fraction.padEnd(2,'0'));};
 let old=[];try{old=JSON.parse(form.dataset.oldItems)}catch{}
 for(const line of old){const p=catalog.find(p=>Number(p.id)===Number(line.package_id));if(p)cart.set(p.id,Math.max(1,Math.min(99,Number(line.quantity)||1)));}
 const el=(tag,cls,text)=>{const n=document.createElement(tag);if(cls)n.className=cls;if(text!==undefined)n.textContent=text;return n;};
 const hidden=(name,value)=>{const i=el('input');i.type='hidden';i.name=name;i.value=value;return i;};
 function render(){
  const area=$('#cart');const fields=$('#cart-fields');area.replaceChildren();fields.replaceChildren();let index=0;
  for(const [id,qty]of cart){
   const p=catalog.find(p=>p.id===id);if(!p)continue;
   const row=el('div','cart-item'),head=el('div','cart-item-head'),bottom=el('div','cart-item-bottom');
   head.append(el('strong','',p.name),el('span','',money(p.price*qty)));
   bottom.append(el('small','',`${p.points*qty} points · ${p.hours}h valid`));
   const step=el('div','stepper');const minus=el('button','','−'),plus=el('button','','+');minus.type=plus.type='button';minus.setAttribute('aria-label','Remove one '+p.name);plus.setAttribute('aria-label','Add one '+p.name);
   minus.addEventListener('click',()=>{qty<=1?cart.delete(id):cart.set(id,qty-1);render();});plus.addEventListener('click',()=>{cart.set(id,Math.min(99,qty+1));render();});step.append(minus,el('span','',String(qty)),plus);bottom.append(step);row.append(head,bottom);area.append(row);
   fields.append(hidden(`items[${index}][package_id]`,id),hidden(`items[${index}][quantity]`,qty));index++;
  }
  if(!cart.size)area.append(el('div','cart-empty','Choose an adventure to begin.'));
  calculate();
 }
 function calculate(){
  let subtotal=0,points=0,count=0,hours=Infinity;
  for(const[id,qty]of cart){const p=catalog.find(p=>p.id===id);subtotal+=p.price*qty;points+=p.points*qty;count+=qty;hours=Math.min(hours,p.hours);}
  const discount=decimal($('#discount').value),rate=Number(form.dataset.taxBps),inclusive=form.dataset.taxMode==='inclusive';const net=Math.max(0,subtotal-(Number.isFinite(discount)?discount:0));
  const denominator=inclusive?10000+rate:10000;const tax=Math.floor((net*rate+Math.floor(denominator/2))/denominator);const total=inclusive?net:net+tax;
  $('#subtotal').textContent=money(subtotal);$('#tax').textContent=money(tax);$('#total').textContent=money(total);$('#points-total').textContent=points.toLocaleString()+' pts';$('#item-count').textContent=String(count);
  $('#validity-note').textContent=Number.isFinite(hours)?`New pass validity: ${hours} hours from billing. Top-ups never shorten existing validity.`:'';
  const cash=form.querySelector('[name=payment_method]:checked').value==='cash';$('#cash-field').hidden=!cash;$('#reference-field').hidden=cash;
  const received=$('#received').value.trim()===''?total:decimal($('#received').value);$('#change-note').textContent='Change: '+money(Math.max(0,received-total));
  let error='';if(subtotal>9999999900||total>9999999900)error='Split this amount into separate bills.';else if(!Number.isFinite(discount)||discount>subtotal)error='Discount must be between zero and the subtotal.';else if(cash&&(!Number.isFinite(received)||received<total))error='Cash received is less than the total.';
  $('#checkout-error').textContent=error;$('#checkout-button').disabled=!cart.size||Boolean(error);
 }
 document.querySelectorAll('[data-add]').forEach(b=>b.addEventListener('click',()=>{const id=Number(b.dataset.add);cart.set(id,Math.min(99,(cart.get(id)||0)+1));render();}));
 $('#clear-cart').addEventListener('click',()=>{cart.clear();render();});
 function search(){const term=$('#catalog-search').value.toLowerCase();let count=0;document.querySelectorAll('[data-card-id]').forEach(card=>{const match=(filter==='all'||card.dataset.kind===filter)&&card.dataset.search.includes(term);card.hidden=!match;if(match)count++;});$('#catalog-count').textContent=count+' options';}
 document.querySelectorAll('[data-filter]').forEach(b=>b.addEventListener('click',()=>{filter=b.dataset.filter;document.querySelectorAll('[data-filter]').forEach(t=>t.classList.toggle('active',t===b));search();}));$('#catalog-search').addEventListener('input',search);
 ['#discount','#received'].forEach(s=>$(s).addEventListener('input',calculate));form.querySelectorAll('[name=payment_method]').forEach(r=>r.addEventListener('change',calculate));
 form.addEventListener('submit',e=>{calculate();if($('#checkout-button').disabled){e.preventDefault();return;}if(form.dataset.preview==='true'){e.preventDefault();alert('Visual preview only. Install the Laravel app to save a bill and issue a real QR.');return;}$('#checkout-button').disabled=true;$('#checkout-button').textContent='Saving bill…';});
 render();
})();
