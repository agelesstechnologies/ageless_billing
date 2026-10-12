# ESP32-CAM / 25-ride release — verification

9 October 2026. Camera firmware 1.0.0. Target: **AI-Thinker ESP32-CAM / OV2640 / PSRAM**. Original UART firmware retained.

| Verification | Result |
| --- | --- |
| Laravel suite, in-memory SQLite | **102 tests / 579 assertions passed** |
| New camera setup feature tests | 11 tests included: roles, whole-batch validation/rollback, positive rates, unique hashed tokens, disabled new readers, retained reader/pending data, config download and API identity/cache headers |
| Native camera latch / approval checks | **643 checks passed**, C++11, warnings treated as errors |
| Actual bill QR decoding | Bundled detector/decoder read the ERP-issued QR from rendered 320 × 240 grayscale pixels |
| Real disposable ERP / browser / machine HTTP integration | **10 scenarios passed**, including 25 distinct authenticated readers |
| Same wallet / 25 deductions | Synthetic 10,000 points → **6,750**; 25 debits totaling **3,250** points |
| Retry protection | Same request IDs preserved the original debit; simulated completed ACKs/replays allowed no further pulse |
| Concurrent final-balance requests | Two readers requested the final 10 points; one approved, one denied; balance 0 with one debit |
| ESP32-CAM bench build | Successful; flash 1,041,413 / 3,145,728 bytes; static RAM 51,716 / 327,680 bytes |
| ERP-generated reader pack, pulse output enabled | ZIP CRC verified and compiled; flash 1,044,917 / 3,145,728 bytes; static RAM 51,716 / 327,680 bytes |
| Supplied .env and SQLite file | Byte-identical to the original project; existing account hashes and records retained |
| Physical ESP32 flash / camera / GPIO / ride input | **Not tested in this environment** |
| Live server deployment / actual MySQL | **Not performed** |

Build: PlatformIO 6.1.18; espressif32 6.9.0; Arduino ESP32 2.0.17 (framework package 3.20017.241212+sha.dcc1105b); Xtensa 8.4.0+2021r2-patch5; ArduinoJson 7.4.2. RAM numbers are linker static RAM, not runtime PSRAM measurements. No preconfigured charging binary is shipped; compile each device's own configuration.

The browser integration launched a new synthetic SQLite ERP with four PHP workers. It exercised database admin login, the 25-row form, private ZIP download, all 25 reader tokens/identities/rates, explicit enablement, actual POS billing, the issued QR, 25 debits/retries, admin wallet/history and simultaneous final-balance spending. HTTP acknowledgements were simulated only in that disposable fixture. The native build uses the same bundled quirc detector/decoder, with normal malloc instead of ESP PSRAM allocation. It read ERP receipt SVG pixels rendered into a QVGA image; this is a QR encoding compatibility test, not a physical camera capture test.

Pure C++ checks cover a held QR for 60 seconds, short blanks, unreadable symbols, removal/re-presentation, different guests, camera stalls (unobserved time never releases the latch), timing wraparound, identity/request mismatches, response status, pulse limits and TTL margins. Source persists before debit and before GPIO output, and retains uncertain state on reboot; actual NVS/power-loss and electrical delivery require hardware commissioning.

All distributed configurations start with ENABLE_PULSE_OUTPUT=false. Newly created ERP readers are disabled. Synthetic databases, test reader tokens, toolchains and build caches are excluded from delivery. Generic 25-folder configurations have placeholders; the admin-generated private pack contains actual server/token/serial/ID values. Ride points are supplied by the database, never hardcoded in the reader.

Test sources: firmware/AgelessCameraReader/tests and tests/Feature/CameraRideSetupTest.php. Result summary: camera-erp-checks.json. Fixture preparation refuses existing output directories and never uses live data. Tests need development dependencies, Python/Pillow, C/C++ compilers and Playwright/Chromium.

## First hardware check

Follow ESP32-CAMERA-MALAYALAM.md. Verify board power/PSRAM, receipt focus/lighting, Wi-Fi/server, unique device assignment, idle polarity and the isolated GPIO13 credit-input driver. Test one low-balance wallet on one ride before enabling the remaining readers. The manufacturer's controller retains all emergency-stop/interlock/start decisions; ACK records a credit pulse, not proof of ride operation.

## Primary source references

- [ESP32QRCodeReader pinned upstream](https://github.com/alvarowolfx/ESP32QRCodeReader/tree/dd07abcf062c4c1b120e0584df57c0c92ca77daf): local patches, MIT/OpenMV notices and quirc license included.
- [Arduino ESP32 2.0.17](https://github.com/espressif/arduino-esp32/tree/2.0.17).
- [PlatformIO espressif32 6.9.0](https://github.com/platformio/platform-espressif32/tree/v6.9.0).
- [ArduinoJson 7.4.2](https://github.com/bblanchon/ArduinoJson/tree/v7.4.2).
