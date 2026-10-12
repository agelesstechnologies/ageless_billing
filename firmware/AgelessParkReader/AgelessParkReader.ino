/*
 ParkDesk ESP32 QR reader -- integration example, not a ride-safety controller.
 Required: Espressif Arduino ESP32 core 3.x; ArduinoJson 7.x.
 Pin assumptions: generic ESP32 WROOM, UART scanner. No TFT/NFC/SDK-terminal driver is included.

 Important delivery semantics:
   1. Persist request ID + QR BEFORE contacting the server.
   2. Retry the same ID. The server deducts once for that ID.
   3. Persist "armed" BEFORE pulsing. A reboot in this state NEVER pulses automatically.
   4. Persist "ack" AFTER the pulse, then retry acknowledgements without a second pulse.
   5. Uncertain outcomes stop this reader until staff resolve them.
 This deliberately avoids blindly giving a second pulse. It cannot prove the ride actually ran.
 Wear-levelled NVS still has finite endurance: evaluate flash lifetime for a high-volume park.
*/
#include <Arduino.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>
#include <Preferences.h>
#include <ArduinoJson.h>
#include <esp_system.h>
#include <time.h>
#include "Config.h"

HardwareSerial scanner(2);
Preferences nvs;
String phase, requestId, pendingQr, scanBuffer, lastQr;
bool fatalFault=false, readerEnabled=false;
uint32_t lastAttempt=0, lastHeartbeat=0, lastWifi=0, lastByteAt=0, lastQrSeen=0;
uint32_t cooldownMs=5000;
const uint8_t ACTIVE_LEVEL=PULSE_ACTIVE_LOW?LOW:HIGH;
const uint8_t IDLE_LEVEL=PULSE_ACTIVE_LOW?HIGH:LOW;

void safeFault(const char* message){
 digitalWrite(PULSE_PIN,IDLE_LEVEL);fatalFault=true;Serial.print("STOP: ");Serial.println(message);
}
bool persist(const String& next){
 JsonDocument d;d["phase"]=next;d["id"]=requestId;d["qr"]=pendingQr;String value;serializeJson(d,value);
 if(nvs.putString("pending",value)!=value.length()){safeFault("Cannot persist pending state. No further pulse is allowed.");return false;}
 phase=next;return true;
}
bool clearPending(){
 // Delete only after a terminal, verified server outcome. Never erase uncertain state manually.
 if(nvs.isKey("pending")&&!nvs.remove("pending")){safeFault("Cannot clear terminal pending state.");return false;}
 phase="";requestId="";pendingQr="";return true;
}
bool validQr(const String& qr){
 if(qr.length()!=35||!qr.startsWith("QR-"))return false;
 for(size_t i=3;i<qr.length();i++)if(!((qr[i]>='0'&&qr[i]<='9')||(qr[i]>='A'&&qr[i]<='F')))return false;
 return true;
}
int api(const String& path,const String& payload,JsonDocument& out,bool post=true){
 out.clear();if(WiFi.status()!=WL_CONNECTED)return -1;
 WiFiClient plain;WiFiClientSecure secure;HTTPClient http;String base(SERVER_URL);
 while(base.endsWith("/"))base.remove(base.length()-1);
 if(base.startsWith("https://")){
  if(strlen(ROOT_CA)<100||time(nullptr)<1700000000){Serial.println("HTTPS requires a root CA and valid NTP time.");return -2;}
  secure.setCACert(ROOT_CA);if(!http.begin(secure,base+path))return -3;
 }else{
  if(!ALLOW_PLAIN_HTTP_LAN||!base.startsWith("http://")){Serial.println("HTTP disabled. Configure verified HTTPS.");return -4;}
  if(!http.begin(plain,base+path))return -3;
 }
 http.setConnectTimeout(3500);http.setTimeout(5000);
 http.addHeader("Authorization",String("Bearer ")+MACHINE_TOKEN);
 http.addHeader("Accept","application/json");http.addHeader("Content-Type","application/json");
 const int code=post?http.POST(payload):http.GET();
 if(code>0){String body=http.getString();if(body.length()>8192||deserializeJson(out,body)){http.end();return -5;}}
 http.end();return code;
}
String makeRequestId(){
 char buffer[72];snprintf(buffer,sizeof(buffer),"esp-%012llX-%08lX%08lX",(unsigned long long)(ESP.getEfuseMac()&0xFFFFFFFFFFFFULL),(unsigned long)esp_random(),(unsigned long)esp_random());return String(buffer);
}
bool sameReply(JsonDocument& reply){return requestId==String(reply["request_id"]|"");}
void reconcile(){
 if(fatalFault||phase==""||WiFi.status()!=WL_CONNECTED)return;
 JsonDocument response;const String path="/api/machine/transactions/"+requestId;
 if(phase=="ack"){
  int code=api(path+"/ack","{\"outcome\":\"pulsed\"}",response);
  if(code>0&&sameReply(response)&&String(response["status"]|"")=="completed"){
   Serial.println("Pulse acknowledged. Ready for another guest.");clearPending();
  }else if(code>0&&sameReply(response)&&String(response["status"]|"")=="reversed"){
   // An operator refund after a known pulse is an accounting exception; never hide it.
   safeFault("Server reversed a locally pulsed play. Staff reconciliation required.");
  }
  return;
 }
 if(phase=="review"||phase=="armed"){
  // "armed" at boot means power may have failed either before or after the physical pulse.
  int code=api(path,"",response,false);
  if(code>0&&sameReply(response)){
   String status=response["status"]|"";
   if(status=="completed"||status=="reversed"||status=="denied"){
    Serial.println("Staff/server resolved pending play. No pulse replayed.");clearPending();return;
   }
   if(status=="approved"){
    JsonDocument ack;api(path+"/ack","{\"outcome\":\"uncertain\"}",ack);
   }
  }
  Serial.println("REVIEW REQUIRED: reader remains blocked. Check Ride transactions.");return;
 }
 if(phase!="send"){safeFault("Unrecognised pending phase.");return;}
 if(!ENABLE_PULSE_OUTPUT){Serial.println("Existing pending request held in bench mode. Do not erase NVS.");return;}
 JsonDocument payload;payload["credential"]=pendingQr;payload["request_id"]=requestId;payload["source"]="qr";String body;serializeJson(payload,body);
 const uint32_t started=millis();int code=api("/api/machine/redeem",body,response);
 if(code<=0||code>=500||code==429){Serial.println("Network/server delay. Retrying the SAME request ID; no pulse.");return;}
 if(!sameReply(response)){Serial.println("Unverified response or authentication error. Pending request retained; no pulse.");return;}
 String status=response["status"]|"";Serial.println(response["message"]|"Response received.");
 if(status=="denied"||status=="completed"||status=="reversed"){clearPending();return;}
 if(status=="uncertain"){persist("review");return;}
 bool approved=response["approved"]|false;bool allow=response["allow_pulse"]|false;
 int pulseMs=response["pulse_ms"]|0;int ttl=response["approval_ttl_seconds"]|0;
 if(status!="approved"||!approved||!allow){persist("review");return;}
 if(pulseMs<50||pulseMs>2000||ttl<=0||ttl>30||millis()-started+250>=(uint32_t)ttl*1000){
  if(persist("review")){JsonDocument ack;api(path+"/ack","{\"outcome\":\"uncertain\"}",ack);}return;
 }
 if(!persist("armed"))return; // NVS record MUST succeed before any output.
 digitalWrite(PULSE_PIN,ACTIVE_LEVEL);delay((uint32_t)pulseMs);digitalWrite(PULSE_PIN,IDLE_LEVEL);
 if(!persist("ack"))return; // Failure leaves an ambiguous armed record; stop, do not repeat.
 Serial.print("Credit pulse sent once. Balance: ");Serial.println(response["balance_after"].as<long>());
 // Next loop retries ACK until accepted. It never resends the pulse in phase "ack".
 lastAttempt=millis()-2500;
}
void barcode(String code){
 code.trim();if(!validQr(code)){Serial.println("Not a ParkDesk QR pass.");return;}
 const uint32_t now=millis();bool duplicate=code==lastQr&&now-lastQrSeen<cooldownMs;lastQr=code;lastQrSeen=now;
 if(duplicate)return; // Continuous scans need a pause, not another debit every few seconds.
 if(fatalFault||phase!=""){Serial.println("Reader busy / pending review. No new debit.");return;}
 if(!ENABLE_PULSE_OUTPUT){Serial.println("BENCH MODE: QR read; no debit and no pulse. Set ENABLE_PULSE_OUTPUT=true after testing.");return;}
 if(WiFi.status()!=WL_CONNECTED||!readerEnabled){Serial.println("Reader offline or disabled. No approval.");return;}
 requestId=makeRequestId();pendingQr=code;
 if(persist("send")){lastAttempt=millis()-2500;Serial.println("Authorising ride...");}
}
void heartbeat(){
 JsonDocument d;int code=api("/api/machine/config","",d,false);
 if(code==200){readerEnabled=d["enabled"]|false;uint32_t seconds=d["cooldown_seconds"]|5;cooldownMs=constrain(seconds,2UL,300UL)*1000;
  Serial.print(readerEnabled?"READY: ":"DISABLED: ");Serial.print(d["ride"]["name"]|"No ride");Serial.print(" / ");Serial.print(d["points_per_play"]|0);Serial.println(" points");
 }else{readerEnabled=false;Serial.println("Cannot authenticate/configure reader. Check server, network and token.");}
}
void setup(){
 digitalWrite(PULSE_PIN,IDLE_LEVEL);pinMode(PULSE_PIN,OUTPUT);digitalWrite(PULSE_PIN,IDLE_LEVEL);
 Serial.begin(115200);scanner.begin(SCANNER_BAUD,SERIAL_8N1,SCANNER_RX,SCANNER_TX);
 if(!nvs.begin("parkdesk",false)){safeFault("Cannot open NVS. Device locked.");return;}
 if(nvs.isKey("pending")){
  String saved=nvs.getString("pending","");JsonDocument d;
  if(saved.length()>512||deserializeJson(d,saved)){safeFault("Pending record damaged. Keep device powered off for staff review.");return;}
  requestId=d["id"]|"";pendingQr=d["qr"]|"";phase=d["phase"]|"";
  if(requestId.length()<8||requestId.length()>80||!validQr(pendingQr)||!(phase=="send"||phase=="armed"||phase=="ack"||phase=="review")){safeFault("Invalid pending record. Do not clear NVS blindly.");return;}
  if(phase=="armed"&&!persist("review"))return;
  Serial.println("Restored pending request. New scans blocked until reconciled.");
 }
 WiFi.mode(WIFI_STA);WiFi.setAutoReconnect(true);WiFi.begin(WIFI_SSID,WIFI_PASSWORD);
 configTime(0,0,"pool.ntp.org","time.cloudflare.com");lastHeartbeat=millis()-30000;lastAttempt=millis()-2500;
 Serial.println("ParkDesk reader starting. A credit pulse must never bypass ride-safety controls.");
}
void loop(){
 if(fatalFault){delay(250);return;}
 uint32_t now=millis();
 if(WiFi.status()!=WL_CONNECTED&&now-lastWifi>10000){lastWifi=now;WiFi.reconnect();}
 if(WiFi.status()==WL_CONNECTED&&now-lastHeartbeat>30000){lastHeartbeat=now;heartbeat();}
 while(scanner.available()){
  char c=(char)scanner.read();lastByteAt=millis();
  if(c=='\r'||c=='\n'){if(scanBuffer.length()){barcode(scanBuffer);scanBuffer="";}}
  else if(c>=32&&c<=126){scanBuffer+=c;if(scanBuffer.length()>120)scanBuffer="";}
 }
 if(scanBuffer.length()&&millis()-lastByteAt>100){barcode(scanBuffer);scanBuffer="";}
 if(phase!=""&&millis()-lastAttempt>2500){lastAttempt=millis();reconcile();}
 delay(10);
}
