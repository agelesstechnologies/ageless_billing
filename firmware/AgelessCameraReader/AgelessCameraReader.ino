/* Ageless camera QR reader 1.0.0. This supplies a payment/credit pulse only;
 * the ride's own control system retains all start and safety decisions. */
#include <Arduino.h>
#include <ESP32QRCodeReader.h>
#include <atomic>
#include "ReaderCore.h"

ESP32QRCodeReader camera(CAMERA_MODEL_AI_THINKER,FRAMESIZE_QVGA);
struct Candidate {char qr[36];uint32_t captured;};
QueueHandle_t candidates;
std::atomic<bool> accepting(false);
TaskHandle_t consumerHandle=nullptr;

void consumeCamera(void*) {
 ScanGate gate(QR_REMOVAL_MS);QRCodeData decoded;
 for(;;) {
  if(!camera.receiveQrCode(&decoded,100))continue;
  if((uint32_t)(millis()-decoded.capturedAt)>500)continue;
  if(!decoded.detected){gate.blank(decoded.capturedAt);continue;}
  if(!decoded.valid || decoded.payloadLen!=35){gate.undecodableSymbol();continue;}
  Candidate candidate;memcpy(candidate.qr,decoded.payload,35);candidate.qr[35]=0;candidate.captured=decoded.capturedAt;
  // Observe even while network/output is busy: holding a QR cannot re-arm
  // merely because an ACK finished, the camera paused, or Wi-Fi came back.
  if(!gate.observe(candidate.qr,decoded.capturedAt))continue;
  bool ready=true;
  if(accepting.compare_exchange_strong(ready,false))xQueueSend(candidates,&candidate,0);
 }
}
void setup() {
 Serial.begin(115200);CreditReader::begin();if(CreditReader::fatal)return;
 candidates=xQueueCreate(1,sizeof(Candidate));
 if(!candidates || !camera.qrCodeQueue){CreditReader::fault("Cannot allocate camera queues.");return;}
 camera.setDebug(false);camera.cameraConfig.fb_location=CAMERA_FB_IN_PSRAM;camera.cameraConfig.grab_mode=CAMERA_GRAB_LATEST;
 QRCodeReaderSetupErr error=camera.setup();
 if(error!=SETUP_OK){CreditReader::fault(error==SETUP_NO_PSRAM_ERROR?"PSRAM missing. Use AI-Thinker ESP32-CAM with PSRAM enabled.":"Camera initialization failed. Check OV2640 and board profile.");return;}
 camera.beginOnCore(0);
 if(!camera.begun || xTaskCreatePinnedToCore(consumeCamera,"agelessQrConsumer",4096,nullptr,3,&consumerHandle,1)!=pdPASS){CreditReader::fault("Camera task initialization failed.");return;}
 Serial.println("Camera ready. Present the bill QR, then remove it for 2 seconds before using the same QR again.");
 accepting.store(CreditReader::canScan());
}
void loop() {
 if(CreditReader::fatal){accepting.store(false);delay(250);return;}
 uint32_t now=millis();
 bool networkDue=(CreditReader::phase!="" && now-CreditReader::lastAttempt>2500)
   || (CreditReader::configured && WiFi.status()==WL_CONNECTED && now-CreditReader::lastHeartbeat>30000);
 if(networkDue || !CreditReader::canScan())accepting.store(false);
 CreditReader::tick();
 Candidate candidate;
 if(xQueueReceive(candidates,&candidate,0)==pdTRUE) {
  if((uint32_t)(millis()-candidate.captured)<=MAX_SCAN_AGE_MS)CreditReader::scan(candidate.qr);
  else Serial.println("Old camera scan discarded. Remove QR and present it again.");
 }
 accepting.store(CreditReader::canScan());delay(10);
}
