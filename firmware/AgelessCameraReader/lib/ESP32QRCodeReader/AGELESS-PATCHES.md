# Ageless camera integration patches

Upstream: https://github.com/alvarowolfx/ESP32QRCodeReader
Pinned commit: dd07abcf062c4c1b120e0584df57c0c92ca77daf (library 1.1.0).
Upstream MIT license retained; quirc license retained in src/quirc/LICENSE; OpenMV source copyright notices and MIT terms included in src/openmv/LICENSE.

Changes: headers moved into src for Arduino IDE compatibility; initialized camera config and task handle; checked task creation; rejected frame length/format mismatches; bounded decoded payload copy; emitted timestamped empty/present observations; rejected multiple QR symbols in one frame; declared PSRAM allocation functions with correct C types; preserved old buffers when resize allocation fails; null-safe destruction; current camera SCCB field names. Host verification uses the same decoder with standard malloc in place of ESP PSRAM allocation. Required for AgelessCameraReader and its continuous-QR latch. Use this bundled library, not an unpatched Library Manager copy.
