# 25 rides — ESP32 camera QR setup

ഇത് **AI-Thinker ESP32-CAM + OV2640 camera + PSRAM** board-ന് തയ്യാറാക്കിയ code ആണ്. ESP32-S3 / CYD / മറ്റൊരു camera board ആണെങ്കിൽ അതേ pin mapping ഉപയോഗിക്കരുത്; ആ model-നുള്ള mapping വേണം. ഓരോ ride-നും ഒരു moduleയും unique device tokenഉം വേണം. എല്ലാ modulesഉം ERPയുടെ ഒരേ database ഉപയോഗിക്കുന്നു.

## 1. ERP update ചെയ്യുക

Updated project code server-ൽ നൽകുക. നിലവിലെ `.env`, `APP_KEY`, live database, uploaded files നിലനിർത്തുക. Project folder-ൽ:

```bash
php artisan config:clear
php artisan route:clear
php artisan migrate --force
php artisan park:database-check
```

Admin application email/password ഉപയോഗിച്ച് login ചെയ്യുക. പഴയ users, attendance, bills, points reset ചെയ്യേണ്ടതില്ല.

## 2. 25 rides-ന്റെ points നൽകുക

**Rides & attractions → Set up 25 camera rides** തുറക്കുക.

1. ഓരോ ride-ന്റെ actual name, unique code, **Points / play**, credit pulse duration, same-pass cooldown നൽകുക. പുതിയ rows-ൽ points blank ആണ്; നിങ്ങളുടെ rate നൽകണം.
2. ESP32കൾക്ക് എത്താവുന്ന ERP address നൽകുക. Local PC: ഉദാ. `http://192.168.1.10:8000`; PCയും ESP32കളും same trusted LAN/Wi-Fiയിൽ. `localhost` ഉപയോഗിക്കരുത്. Hosted ERPക്ക് valid HTTPS certificate വേണം.
3. **Create a camera reader for each ride without one** tick ചെയ്യുക.
4. **Save rides & download camera pack** അമർത്തുക. ഓരോ പുതിയ reader-നും unique token/config ഉള്ള private ZIP ലഭിക്കും. New readers disabled ആണ്. Existing readers/tokens മാറ്റില്ല.
5. ZIP ഉടൻ സൂക്ഷിക്കുക. Databaseൽ token hash മാത്രമാണ്; actual token വീണ്ടും കാണാൻ കഴിയില്ല. Download നഷ്ടപ്പെട്ടാൽ ആവശ്യമായ reader-ന്റെ pending plays ആദ്യം resolve ചെയ്ത് **ESP readers → Configure → Rotate device token** ഉപയോഗിക്കുക; തുടർന്ന് **Download camera Config.h**. അത് പഴയ token മാറ്റും.

Existing rides ആദ്യം കാണിക്കും. 25നു മുകളിൽ existing rides ഉണ്ടെങ്കിൽ ബാക്കിയുള്ളവ **All rides**ൽ നിലനിൽക്കും. ഒരു ride മാത്രം പിന്നീട് ചേർക്കാൻ **Add ride**, തുടർന്ന് **Add ESP reader** ഉപയോഗിക്കാം.

**Points ESP32 code-ൽ hardcode ചെയ്യുന്നില്ല.** Admin rate മാറ്റിയാൽ അടുത്ത പുതിയ scan serverയിലെ പുതിയ rate ഉപയോഗിക്കും. Completed/history recordsൽ പഴയ charged rate നിലനിൽക്കും.

## 3. ഓരോ module-ലേക്ക് code upload ചെയ്യുക

Arduino IDE:

1. Boards Managerൽ **esp32 by Espressif Systems 2.0.17** install ചെയ്യുക. Delivered camera build ഈ core ഉപയോഗിച്ചാണ് പരിശോധിക്കുന്നത്.
2. **AI Thinker ESP32-CAM** board തിരഞ്ഞെടുക്കുക; PSRAM enable ചെയ്ത profile ഉപയോഗിക്കുക.
3. Library Managerൽ **ArduinoJson 7.4.2** install ചെയ്യുക.
4. ZIPയിലെ `libraries/ESP32QRCodeReader` folder Arduino sketchbookയിലെ `libraries` folder-ിലേക്ക് copy ചെയ്യുക. Included patched library തന്നെയാണ് വേേണ്ടത്; unpatched Library Manager copy നീക്കി conflict ഒഴിവാക്കുക. ഇത് QR length bounds, continuous scan handling എന്നിവ ഉൾക്കൊള്ളുന്നു.
5. അതത് reader-ന്റെ folderയിലെ `.ino` തുറക്കുക. ഉദാ. `Ride_01_ESP_CAM/Ride_01_ESP_CAM.ino`. Folderയിലെ `ReaderCore.h`, `ScanGate.h`, `Config.h` കൂടെ വേണം.
6. `Config.h`ൽ Wi-Fi SSID/password നൽകുക. ERP-generated ZIPയിൽ server URL, unique token, expected reader serial/id ഇതിനകം ഉണ്ടാകും. Generic 25-folder source pack ആണെങ്കിൽ ഇവ ERPയിൽ നിന്നെടുത്ത് നൽകണം. എല്ലാ modulesക്കും ഒരേ token copy ചെയ്യരുത്.
7. `ENABLE_PULSE_OUTPUT=false` ആദ്യം നിലനിർത്തുക. ഇത് camera QR decode ചെയ്യുന്ന bench mode ആണ്; point debit/pulse ഉണ്ടാകില്ല.
8. USB programmer / ESP32-CAM-MB ഉപയോഗിച്ച് Upload ചെയ്യുക. Separate USB-UART ആണെങ്കിൽ 3.3V logic, boardന്റെ 5V supply, common GND, TX→U0R, RX→U0T. Flash modeക്ക് GPIO0→GND ചെയ്ത് reset; upload കഴിഞ്ഞ് GPIO0-GND ബന്ധം മാറ്റി വീണ്ടും reset ചെയ്യുക.
9. Serial Monitor **115200** തുറക്കുക. PSRAM/camera error ഇല്ലെന്നും bill QR കാണിക്കുമ്പോൾ `BENCH: bill QR decoded` വരുന്നുണ്ടെന്നും പരിശോധിക്കുക.

PlatformIOയ്ക്ക് ഓരോ projectൽ `platformio.ini` ഉൾപ്പെടുത്തിയിട്ടുണ്ട്: `pio run`, തുടർന്ന് correct USB port നൽകി `pio run --target upload`. Platform **espressif32 6.9.0**, board **esp32cam**, Arduino core **2.0.17**, ArduinoJson **7.4.2** pinned ആണ്. The patched decoder is included locally.

## 4. Credit input connection & enable

| ESP32-CAM pin | ഉപയോഗം |
| --- | --- |
| 5V / GND | Board manufacturer നിർദേശിക്കുന്ന regulated supply; UART signal level 3.3V മാത്രം |
| GPIO13 | 3.3V isolated credit-input driver signal; microSD ഉപയോഗിക്കുന്നില്ല |
| GPIO0 | Upload mode മാത്രം; normal runningൽ camera XCLK ഉപയോഗിക്കുന്നതിനാൽ GNDയിൽ ബന്ധിപ്പിക്കരുത് |
| U0R / U0T | Programming / serial monitor |

GPIO13 നേരിട്ട് relay coil, mains, motor, ride-start power line എന്നിവയിലേക്ക് ബന്ധിപ്പിക്കരുത്. Appropriate 3.3V opto/relay driver വഴി manufacturer-approved isolated **credit input** മാത്രം ഉപയോഗിക്കുക. Active-high configurationക്ക് GPIO13ൽ hardware pull-down (ഉദാ. 10 kΩ) വേണം; reboot/unpowered output credit ആകരുത്. Active-low input ആണെങ്കിൽ idle pull-up/polarity അതിന് അനുസരിച്ച് installer verify ചെയ്യണം. GPIO13/14 microSD pins ആണ്; ഈ firmware microSD initialize ചെയ്യുന്നില്ല. Camera/PSRAM pins reuse ചെയ്യരുത്.

ഈ module payment credit നൽകുന്നു. Rideയുടെ emergency stop, restraint, interlock, operator start logic എന്നിവ അതിന്റെ control systemൽ തന്നെ തുടരും. Software ACK ഒരു electrical pulse അയച്ചെന്ന രേഖയാണ്; ride യഥാർത്ഥത്തിൽ run ചെയ്തെന്ന sensor proof അല്ല.

Isolated input test പൂർത്തിയായ ശേഷം `ENABLE_PULSE_OUTPUT=true` ആയി compile/upload ചെയ്യുക. ERPയിൽ ആ **ride Active** ആക്കുകയും അതത് **ESP reader Enabled** ആക്കുകയും ചെയ്യുക. Serialൽ `READY: ride name / N points per play` വരണം. Test QR ചെറിയ balance ഉപയോഗിച്ച് points, single pulse, ledger match ചെയ്യുക.

## 5. Bill QR ഉപയോഗിക്കുക

1. Counterൽ package / points bill issue ചെയ്യുക. അതിലെ **QR-…** wallet QR cameraയ്ക്ക് കാണിക്കുക.
2. ESP32 unique token വഴി സ്വന്തം assigned ride-ന്റെ server rate ചോദിക്കും; server guest walletൽ നിന്ന് points atomic ആയി കുറയ്ക്കും.
3. മതിയായ points ഉണ്ടെങ്കിൽ ഒരു credit pulse; തുടർന്ന് ACK. Serialൽ deducted points / remaining balance കാണാം.
4. അതേ QR വീണ്ടും ഉപയോഗിക്കാൻ cameraയ്ക്ക് മുന്നിൽ നിന്ന് **2 seconds നീക്കി**, ride cooldown കഴിഞ്ഞ് വീണ്ടും കാണിക്കുക. QR തുടർച്ചയായി പിടിച്ചുനിർത്തുമ്പോൾ repeated debit ഉണ്ടാകാതിരിക്കാൻ latch ഉണ്ട്. Frameൽ ഒരേസമയം രണ്ട് QR കാണിക്കരുത്.
5. **QR wallets → guest → Point history**, **Ride transactions** എന്നിവയിൽ ride-wise debitയും current balanceഉം കാണാം. Balance തീർന്നാൽ counterൽ **Recharge existing QR pass** ഉപയോഗിക്കുക; അതേ QR തുടരും.

Printed QRയിൽ blur/glare ഒഴിവാക്കുക; code മുഴുവനും quiet borderഉം frameൽ വേണം. Camera focus/distance/lighting actual receiptയിൽ test ചെയ്യണം. ESP32 offline ആണെങ്കിൽ scan later debit ചെയ്യാനുള്ള queue ഇല്ല.

## 6. Missing / uncertain transaction

Network response നഷ്ടപ്പെട്ടാൽ firmware അതേ request ID retry ചെയ്യും; പുതിയ debit request സൃഷ്ടിക്കില്ല. Pulseിനു മുമ്പ് NVSൽ `armed`, ശേഷം `ack` save ചെയ്യും. `armed` stateൽ reboot ആണെങ്കിൽ വീണ്ടും pulse അയക്കാതെ reviewക്ക് പോകും. ACK retry മാത്രം രണ്ടാം pulse നൽകില്ല. Reader/server/token മാറ്റിയ pending record സ്വയം replay ചെയ്യില്ല.

`REVIEW REQUIRED` / orphan pending play ഉണ്ടെങ്കിൽ **Ride transactions** തുറക്കുക. Reader disabled ചെയ്ത് power off ആക്കുക; approval window കഴിയുമ്പോൾ verified outcome അനുസരിച്ച് **Complete** / **Refund** ചെയ്യുക. NVS erase ചെയ്ത് pending state മറയ്ക്കരുത്. Physical operation verify ചെയ്താണ് accounting decision എടുക്കേണ്ടത്.

HTTPS ഉപയോഗിക്കുന്നുവെങ്കിൽ `ROOT_CA`യിൽ issuing root certificate ചേർക്കുക; correct NTP time വേണം. TLS verification bypass ചെയ്തിട്ടില്ല. Local HTTP trusted private LANക്ക് മാത്രം; token/login/QR traffic encrypted അല്ല.

Source/build/database tests ഈ releaseന്റെ `docs/ESP32-CAMERA-TEST-REPORT.md`ൽ കാണാം. Actual camera focus, power, Wi-Fi coverage, GPIO driver and ride input validation hardwareൽ ചെയ്യണം; ഈ environmentൽ physical ESP32 flash/test നടത്തിയിട്ടില്ല.
