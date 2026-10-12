#pragma once
#include <Arduino.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>
#include <Preferences.h>
#include <ArduinoJson.h>
#include <esp_system.h>
#include <mbedtls/sha256.h>
#include <time.h>
#include "Config.h"
#include "ScanGate.h"

// Single owner (Arduino loop) for network, NVS and output. The camera task
// communicates only through a queue, never through these mutable Strings.
namespace CreditReader {
Preferences nvs;
String phase,requestId,pendingQr,binding;
bool fatal=false,readerEnabled=false,orphanPending=false,configured=false;
uint32_t lastAttempt=0,lastHeartbeat=0,lastWifi=0;
const uint8_t ACTIVE=PULSE_ACTIVE_LOW?LOW:HIGH,IDLE=PULSE_ACTIVE_LOW?HIGH:LOW;

void fault(const char* message){digitalWrite(PULSE_PIN,IDLE);fatal=true;Serial.print("STOP: ");Serial.println(message);}
bool qrValid(const String& qr){if(qr.length()!=35||!qr.startsWith("QR-"))return false;for(size_t i=3;i<35;i++)if(!((qr[i]>='0'&&qr[i]<='9')||(qr[i]>='A'&&qr[i]<='F')))return false;return true;}
bool privateHttp(String url) {
 if(!url.startsWith("http://"))return false;
 String host=url.substring(7);int slash=host.indexOf('/');if(slash>=0)host=host.substring(0,slash);int colon=host.indexOf(':');if(colon>=0)host=host.substring(0,colon);host.toLowerCase();
 if(host.endsWith(".local"))return true;
 IPAddress ip;if(!ip.fromString(host))return false;
 return ip[0]==10 || (ip[0]==172&&ip[1]>=16&&ip[1]<=31) || (ip[0]==192&&ip[1]==168);
}
String profileBinding() {
 String value=String(SERVER_URL)+"|"+MACHINE_TOKEN+"|"+EXPECTED_MACHINE_SERIAL+"|"+PULSE_PIN+"|"+(PULSE_ACTIVE_LOW?"low":"high");unsigned char digest[32];
 mbedtls_sha256_ret((const unsigned char*)value.c_str(),value.length(),digest,0);
 char hex[65];for(int i=0;i<32;i++)snprintf(hex+i*2,3,"%02x",digest[i]);return String(hex);
}
bool persist(const String& next) {
 JsonDocument d;d["phase"]=next;d["id"]=requestId;d["qr"]=pendingQr;d["binding"]=binding;
 String value;serializeJson(d,value);
 if(nvs.putString("pending",value)!=value.length()){fault("Cannot persist transaction. No output permitted.");return false;}
 phase=next;return true;
}
void clearPending() {
 if(nvs.isKey("pending")&&!nvs.remove("pending")){fault("Cannot clear resolved transaction.");return;}
 phase="";requestId="";pendingQr="";lastHeartbeat=millis()-30000;
}
class BodySink:public Stream {
 public:String value;bool tooLarge=false;
 size_t write(uint8_t byte) override {return write(&byte,1);}
 size_t write(const uint8_t* bytes,size_t count) override {
  if(value.length()+count>8192){tooLarge=true;return 0;}
  return value.concat((const char*)bytes,count)?count:0;
 }
 int available() override{return 0;}int read() override{return -1;}int peek() override{return -1;}void flush() override{}
};
int api(const String& path,const String& body,JsonDocument& result,bool post=true) {
 result.clear();if(!configured||WiFi.status()!=WL_CONNECTED)return -1;
 WiFiClient plain;WiFiClientSecure secure;HTTPClient http;String base(SERVER_URL);while(base.endsWith("/"))base.remove(base.length()-1);
 if(base.startsWith("https://")) {
  if(strlen(ROOT_CA)<100 || time(nullptr)<1700000000){Serial.println("HTTPS needs ROOT_CA and correct NTP time.");return -2;}
  secure.setCACert(ROOT_CA);if(!http.begin(secure,base+path))return -3;
 }else {
  if(!ALLOW_PLAIN_HTTP_LAN || !privateHttp(base))return -4;
  if(!http.begin(plain,base+path))return -3;
 }
 http.setReuse(false);http.setConnectTimeout(3500);http.setTimeout(5000);
 http.addHeader("Authorization",String("Bearer ")+MACHINE_TOKEN);http.addHeader("Accept","application/json");http.addHeader("Content-Type","application/json");http.addHeader("Cache-Control","no-cache");
 const int status=post?http.POST(body):http.GET();
 if(status<=0){http.end();return status;}
 if(http.getSize()>8192){http.end();return -5;}
 BodySink sink;int received=http.writeToStream(&sink);http.end();
 if(received<0 || sink.tooLarge || deserializeJson(result,sink.value))return -5;
 return status;
}
bool identity(JsonDocument& d){return String(d["serial"]|"")==EXPECTED_MACHINE_SERIAL && d["machine_id"].is<int>() && (EXPECTED_MACHINE_ID==0 || d["machine_id"].as<int>()==EXPECTED_MACHINE_ID);}
bool sameReply(JsonDocument& d){return identity(d)&&String(d["request_id"]|"")==requestId;}
void heartbeat() {
 JsonDocument d;int status=api("/api/machine/config","",d,false);
 readerEnabled=false;
 if(status!=200 || !identity(d) || !d["api_version"].is<int>() || d["api_version"].as<int>()<2){Serial.println("Cannot configure reader: check ERP update, URL, unique token and reader serial.");return;}
 readerEnabled=d["enabled"]|false;orphanPending=d["pending"]|false;
 Serial.print(readerEnabled?"READY: ":"DISABLED: ");Serial.print(d["ride"]["name"]|"Unknown ride");Serial.print(" / ");Serial.print(d["points_per_play"]|0);Serial.println(" points per play");
 if(orphanPending && phase=="")Serial.println("Server has a pending play without local state. Resolve Ride transactions; no new scans accepted.");
}
String newId(){char s[72];snprintf(s,sizeof(s),"cam-%012llX-%08lX%08lX",(unsigned long long)(ESP.getEfuseMac()&0xFFFFFFFFFFFFULL),(unsigned long)esp_random(),(unsigned long)esp_random());return String(s);}
void reconcile() {
 if(fatal || phase=="" || !configured || WiFi.status()!=WL_CONNECTED)return;
 JsonDocument d;String path="/api/machine/transactions/"+requestId;
 if(phase=="ack") {
  int status=api(path+"/ack","{\"outcome\":\"pulsed\"}",d);
  if(status==200 && sameReply(d) && String(d["status"]|"")=="completed"){Serial.println("Acknowledged once. Ready for the next guest.");clearPending();}
  else if(status>0 && sameReply(d) && String(d["status"]|"")=="reversed")fault("Locally pulsed play was refunded. Operator reconciliation required.");
  return;
 }
 if(phase=="review" || phase=="armed") {
  int status=api(path,"",d,false);
  if(status>0 && sameReply(d)) {
   String state=d["status"]|"";
   if(state=="completed" || state=="reversed" || state=="denied"){Serial.println("Resolved by server/operator. No pulse replayed.");clearPending();return;}
   if(state=="approved"){JsonDocument ack;api(path+"/ack","{\"outcome\":\"uncertain\"}",ack);}
  }
  Serial.println("REVIEW REQUIRED: use Ride transactions. New scans blocked.");return;
 }
 if(phase!="send"){fault("Unknown pending state.");return;}
 if(!ENABLE_PULSE_OUTPUT){Serial.println("Pending play held in bench mode. Keep NVS; restore original configuration to reconcile.");return;}
 JsonDocument payload;payload["credential"]=pendingQr;payload["request_id"]=requestId;payload["source"]="qr";String body;serializeJson(payload,body);
 const uint32_t started=millis();int status=api("/api/machine/redeem",body,d);
 if(status<=0 || status>=500 || status==429){Serial.println("Unconfirmed server response. Retry SAME request ID; no pulse.");return;}
 if(!sameReply(d)){Serial.println("Reply identity mismatch or auth error. Pending request retained; no pulse.");return;}
 String state=d["status"]|"";Serial.println(d["message"]|"ERP replied.");
 if(state=="denied" || state=="completed" || state=="reversed"){clearPending();return;}
 CreditReply reply={identity(d),String(d["request_id"]|"")==requestId,d["approved"].is<bool>() && d["approved"].as<bool>(),d["allow_pulse"].is<bool>() && d["allow_pulse"].as<bool>(),status,d["pulse_ms"].is<int>()?d["pulse_ms"].as<int>():0,d["approval_ttl_seconds"].is<int>()?d["approval_ttl_seconds"].as<int>():0,(uint32_t)(millis()-started),state.c_str()};
 if(!validCredit(reply)){if(persist("review")){JsonDocument ack;api(path+"/ack","{\"outcome\":\"uncertain\"}",ack);}return;}
 // Record the ambiguity boundary BEFORE any electrical output.
 if(!persist("armed"))return;
 reply.elapsedMs=(uint32_t)(millis()-started);
 if(!validCredit(reply)){if(persist("review")){JsonDocument ack;api(path+"/ack","{\"outcome\":\"uncertain\"}",ack);}return;}
 digitalWrite(PULSE_PIN,ACTIVE);delay((uint32_t)reply.pulseMs);digitalWrite(PULSE_PIN,IDLE);
 if(!persist("ack"))return;
 Serial.print("One credit pulse. Points deducted: ");Serial.print(d["points_deducted"]|0);Serial.print(" / remaining: ");Serial.println(d["balance_after"].as<long>());
 lastAttempt=millis()-2500;
}
bool canScan(){return !fatal && phase=="" && (!ENABLE_PULSE_OUTPUT || (configured && readerEnabled && !orphanPending && WiFi.status()==WL_CONNECTED));}
void scan(const char* value) {
 String code(value);if(!qrValid(code))return;
 if(!ENABLE_PULSE_OUTPUT){Serial.println("BENCH: bill QR decoded. No point debit and no pulse.");return;}
 if(!canScan())return;
 requestId=newId();pendingQr=code;if(persist("send")){lastAttempt=millis()-2500;Serial.println("Authorising selected ride in project database...");}
}
void begin() {
 digitalWrite(PULSE_PIN,IDLE);pinMode(PULSE_PIN,OUTPUT);digitalWrite(PULSE_PIN,IDLE);
 binding=profileBinding();if(!nvs.begin("agelesscam",false)){fault("Cannot open NVS.");return;}
 if(nvs.isKey("pending")) {
  String saved=nvs.getString("pending","");JsonDocument d;
  if(saved.length()>768 || deserializeJson(d,saved) || String(d["binding"]|"")!=binding){fault("Pending record damaged or firmware/token/server changed. Restore original config and review ERP; never erase NVS.");return;}
  phase=d["phase"]|"";requestId=d["id"]|"";pendingQr=d["qr"]|"";
  if(!qrValid(pendingQr) || requestId.length()<8 || requestId.length()>80 || !(phase=="send" || phase=="armed" || phase=="ack" || phase=="review")){fault("Invalid pending record. Operator review required.");return;}
  if(phase=="armed" && !persist("review"))return;
 }
 String token(MACHINE_TOKEN);configured=token.length()==64 && String(EXPECTED_MACHINE_SERIAL)!="YOUR_READER_SERIAL" && (String(SERVER_URL).startsWith("https://") || (ALLOW_PLAIN_HTTP_LAN && privateHttp(SERVER_URL))) && String(WIFI_SSID)!="YOUR_WIFI_NAME";
 for(size_t i=0;i<token.length();i++)if(!((token[i]>='a'&&token[i]<='f')||(token[i]>='0'&&token[i]<='9')))configured=false;
 if(configured){WiFi.mode(WIFI_STA);WiFi.setAutoReconnect(true);WiFi.begin(WIFI_SSID,WIFI_PASSWORD);configTime(0,0,"pool.ntp.org","time.cloudflare.com");}
 else Serial.println("Wi-Fi/URL/token/serial not configured. Camera bench mode only; no debit.");
 if(!ENABLE_PULSE_OUTPUT)Serial.println("BENCH MODE enabled. Verify decoding and isolated input before enabling charges.");
 lastHeartbeat=millis()-30000;lastAttempt=millis()-2500;
}
void tick() {
 if(fatal)return;uint32_t now=millis();
 if(configured && WiFi.status()!=WL_CONNECTED && now-lastWifi>10000){lastWifi=now;WiFi.reconnect();readerEnabled=false;}
 if(configured && WiFi.status()==WL_CONNECTED && now-lastHeartbeat>30000){lastHeartbeat=now;heartbeat();}
 if(phase!="" && now-lastAttempt>2500){lastAttempt=now;reconcile();}
}
}
