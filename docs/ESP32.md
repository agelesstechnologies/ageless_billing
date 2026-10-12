> **Camera QR modules:** use `firmware/AgelessCameraReader` and `docs/ESP32-CAMERA-MALAYALAM.md`. The guide below covers the retained UART scanner firmware; its scanner/GPIO pin mapping is not the ESP32-CAM mapping.

# ESP32 commissioning notes

The sketch is a generic integration example. It has not been compiled with the ESP32 toolchain or tested on a physical scanner/relay in this build environment.

## Intended starting point

Generic ESP32-WROOM/Dev Module with a TTL UART QR scanner, following the user's earlier UART scanner arrangement. Default scanner baud is 9600, 8N1. Default ESP RX is GPIO16, TX is GPIO17 and credit output is GPIO25. These assignments are not universal; some modules or CYD display boards reserve or reuse these pins. Verify the exact board and scanner datasheets before connecting.

For a compatible UART scanner, the scanner's TX goes to the configured ESP RX and grounds share the appropriate reference. Do not connect 5 V logic to an ESP pin; confirm scanner supply and signal levels independently. A device with USB, RS232, RS485, Ethernet or a proprietary terminal SDK requires the appropriate interface/driver, not direct UART wiring.

No TFT display, NFC, fingerprint or WebSocket terminal driver is included. Status and balances appear on Serial Monitor at 115200. The billing API is available for adding a known display driver later.

## Software configuration

Open `AgelessParkReader.ino` in Arduino IDE. Install the Espressif ESP32 board package (3.x API target) and ArduinoJson 7. Select the actual board and port. Configure `Config.h` with Wi-Fi, the billing-server address, the unique token generated under ESP readers, verified pins and output polarity.

The scanner should provide the plain QR payload, ideally with CR/LF termination; the example also processes a short UART idle gap. Printed codes from this application are 35 characters: `QR-` plus 32 uppercase hex characters.

The default `ENABLE_PULSE_OUTPUT=false` is non-charging bench mode. It reads QR data and heartbeats, but does not create redemption requests, debit points or generate pulses. Change to true only after isolated bench testing, against a test database. Test with an indicator/approved isolated input first, not a live ride.

## Electrical and ride-safety boundary

The output is a short credit/authorization pulse, not a direct motor command. An ESP GPIO cannot directly drive a ride motor, mains contactor or arbitrary relay coil. Use the manufacturer's approved isolated credit interface with the correct voltage, polarity, pull-state and driver hardware. Provide an inactive hardware default at power-up and when the controller fails.

Retain the ride's independent safety controller, emergency stops, interlocks, restraints and required operator supervision. The software does not assess guest height, age, capacity, restraint state or safe boarding. Have a qualified technician verify the interface. Do not bypass a safety circuit to make this example work.

## Network and TLS

A local PC server must be reachable from the ESP network. `localhost` refers to the ESP itself, not the PC. Reserve the server's LAN IP and permit only the needed private-network traffic. Guest Wi-Fi isolation can prevent readers reaching the server; do not solve that by opening the service to the public internet.

Plain HTTP in the example is limited by an explicit test-LAN flag. It is not safe on an untrusted/shared guest network because QR identifiers and device tokens are bearer credentials. For deployment, use verified HTTPS with the proper root CA, an accurate clock and `ALLOW_PLAIN_HTTP_LAN=false`. The sketch never calls `setInsecure`. For an offline HTTPS LAN, supply an appropriately trusted internal certificate and a reachable trusted time source instead of assuming public NTP will work.

## Recovery states

`send`: request ID and QR saved before an API attempt; retries use the same ID.
`armed`: approval received and pre-pulse marker saved. A reboot here becomes review, not a second pulse.
`ack`: pulse was sent and its post-pulse marker saved; only the acknowledgement is retried.
`review`: outcome is uncertain; new scans remain blocked while the server record is reconciled.

This is a conservative delivery strategy, not an exactly-once mechanical guarantee. A power failure after arming but before completing a pulse may require a staff point refund or manual confirmation. Lost/damaged NVS, interrupted pulses, firmware faults and a physically malfunctioning ride cannot be resolved from an HTTP response alone. Do not erase flash to bypass a pending transaction.

Flash writes happen around each play. Assess NVS endurance and the actual device's reliability against the park's intended transaction volume. The example's synchronous network calls can delay reading UART while a request is in flight; test scanner buffering and realistic traffic.

## Pending-play procedure

Stop customer use of that reader. In the app disable it, then physically power it off. Wait at least 30 seconds from its last approval. Verify whether the credit/play was delivered from the approved device/ride counters and staff observations. In Ride transactions either confirm delivery or return points with a reason. Reboot the reader so it reconciles its stored request without replaying a pulse, then re-enable it after checking status.

Never automatically refund just because the network timed out: the credit pulse may already have been delivered.
