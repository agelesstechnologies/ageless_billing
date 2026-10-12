#include <assert.h>
#include <stdio.h>
#include <stdint.h>
#include "../ScanGate.h"
static int checks=0;
static void check(bool value){assert(value);checks++;}
int main() {
 const char* a="QR-AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA";
 const char* b="QR-BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB";
 ScanGate gate;
 check(gate.observe(a,100));
 for(uint32_t t=200;t<61000;t+=100)check(!gate.observe(a,t));
 gate.blank(62000);gate.blank(63999);check(!gate.observe(a,64000));
 gate.blank(65000);gate.undecodableSymbol();gate.blank(68000);check(!gate.observe(a,68500));
 for(uint32_t t=70000;t<=72000;t+=250){gate.blank(t);}
 check(gate.observe(a,72001));
 check(gate.observe(b,73000));check(!gate.observe(b,73100));
 // Observing while the application is busy still latches it; availability
 // returning cannot turn a held QR into a second new scan.
 check(gate.observe(a,74000));check(!gate.observe(a,100000));
 check(!gate.observe("https://example.com",100001));
 check(!gate.observe("QR-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",100002));
 check(!gate.observe("QR-AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAZ",100003));
 ScanGate paused;check(paused.observe(a,100));paused.blank(200);paused.blank(5200);check(!paused.observe(a,5201));
 for(uint32_t t=6000;t<=7500;t+=250){paused.blank(t);}
 check(!paused.observe(a,7501));
 for(uint32_t t=8000;t<=10000;t+=250){paused.blank(t);}
 check(paused.observe(a,10001));
 ScanGate wrap;check(wrap.observe(a,UINT32_MAX-5000));
 for(uint32_t offset=0;offset<=2000;offset+=250)wrap.blank(UINT32_MAX-1000+offset);
 check(wrap.observe(a,1002));
 CreditReply reply={true,true,true,true,200,250,30,0,"approved"};
 check(validCredit(reply));reply.elapsedMs=29749;check(validCredit(reply));reply.elapsedMs=29750;check(!validCredit(reply));
 reply.elapsedMs=UINT32_MAX;check(!validCredit(reply));reply.elapsedMs=0;
 reply.identityMatches=false;check(!validCredit(reply));reply.identityMatches=true;
 reply.requestMatches=false;check(!validCredit(reply));reply.requestMatches=true;
 reply.allowPulse=false;check(!validCredit(reply));reply.allowPulse=true;
 reply.approved=false;check(!validCredit(reply));reply.approved=true;
 reply.httpStatus=409;check(!validCredit(reply));reply.httpStatus=200;
 reply.status="completed";check(!validCredit(reply));reply.status="uncertain";check(!validCredit(reply));reply.status="denied";check(!validCredit(reply));reply.status="approved";
 reply.pulseMs=49;check(!validCredit(reply));reply.pulseMs=2001;check(!validCredit(reply));reply.pulseMs=250;
 reply.ttlSeconds=0;check(!validCredit(reply));reply.ttlSeconds=31;check(!validCredit(reply));reply.ttlSeconds=1;reply.elapsedMs=749;check(validCredit(reply));reply.elapsedMs=750;check(!validCredit(reply));
 printf("PASS: %d camera latch / credit approval checks\n",checks);
}
