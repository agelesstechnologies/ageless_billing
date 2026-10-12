# Ageless camera QR reader 1.0.0

Target: **AI-Thinker ESP32-CAM + OV2640 + PSRAM**, no microSD. Use the supplied patched ESP32QRCodeReader library. Other ESP32/S3 camera boards need a separately verified camera pin profile.

## Connect to the ERP

1. Update the included ERP, retaining its live `.env`, APP_KEY and database.
2. Admin → Rides & attractions → Set up 25 camera rides. Enter actual names and points; save to download unique Config.h files and reader sketches.
3. Add Wi-Fi details to the matching reader Config.h. Each device has its own token/serial/ID. Ride prices/points remain in the ERP database.
4. Build with PlatformIO (`pio run`) or Arduino IDE, Espressif core **2.0.17**, board AI Thinker ESP32-CAM, ArduinoJson **7.4.2**. With Arduino IDE, copy the bundled ESP32QRCodeReader into the sketchbook libraries folder.
5. First use the default **ENABLE_PULSE_OUTPUT=false** bench mode. It decodes without debiting points or pulsing. Test receipt focus, power and the isolated credit-input driver.
6. After hardware checks, enable the ride and reader in ERP, set ENABLE_PULSE_OUTPUT=true and flash that individual device. GPIO13 is a credit-driver signal only. The ride controller retains its emergency stops, interlocks and start logic.

Default Config.h contains placeholders. The separate generic source pack has 25 folders; use the ERP-generated private pack for actual device identities. Keep tokens private. Never copy one token to all devices.

## Behaviour

The camera reads the bill's 35-character QR wallet credential. The server atomically debits the assigned ride's current points. A held QR stays latched; remove it for 2 seconds before presenting the same QR again. Multiple QR symbols in one frame are rejected. The server also enforces cooldown and pending-play rules.

A new request ID and QR are persisted before charging. Retries reuse that ID. The armed marker is stored before a pulse; reboot at that boundary requires review rather than a second pulse. ACK retries only acknowledge. Firmware/server/token/polarity changes while pending require restoring the original configuration and operator review. Never erase NVS to hide an unresolved debit.

HTTPS requires ROOT_CA and NTP time, with certificate verification. Explicit local HTTP is restricted to private IPv4 / .local hosts. Public deployments require HTTPS. No offline new-scan queue is provided.

## Verification

Both bench and enabled-output configurations were cross-compiled for ESP32-CAM. Native latch/credit validation checks passed, and the bundled decoder read a real ERP-issued QR rendered into QVGA pixels. A disposable ERP exercised 25 readers, shared-wallet deductions, replay protection and concurrent final-balance spending. HTTP ACKs were simulated; physical camera, flash, GPIO, power-loss behaviour and ride input were not exercised.

See `docs/ESP32-CAMERA-MALAYALAM.md` and `docs/ESP32-CAMERA-TEST-REPORT.md` in the ERP, or START-HERE-MALAYALAM.md and TEST-REPORT.md in the standalone pack. Tests are under `tests/`; fixture preparation refuses existing output directories and never uses live data.

Camera source: https://github.com/alvarowolfx/ESP32QRCodeReader (commit dd07abcf062c4c1b120e0584df57c0c92ca77daf, MIT). Local changes and additional quirc licensing are recorded in the bundled library.
