#pragma once
// Configure these values before uploading. Do not commit real Wi-Fi passwords or tokens.
static const char* WIFI_SSID = "YOUR_WIFI_NAME";
static const char* WIFI_PASSWORD = "YOUR_WIFI_PASSWORD";
static const char* SERVER_URL = "http://192.168.0.100:8000"; // SERVER PC address; no trailing slash.
static const char* MACHINE_TOKEN = "PASTE_TOKEN_FROM_ESP_READERS";

// Generic ESP32-WROOM + a TTL UART QR scanner. These are NOT universal CYD pins.
static constexpr int SCANNER_RX = 16; // Scanner TX -> ESP RX
static constexpr int SCANNER_TX = 17; // Scanner RX -> ESP TX, only when needed
static constexpr uint32_t SCANNER_BAUD = 9600;
static constexpr int PULSE_PIN = 25;
static constexpr bool PULSE_ACTIVE_LOW = false;

// Start in non-charging bench mode. False means: no redemption request, no point debit, no pulse.
// Change to true only after isolated-input testing and matching the machine's electrical requirements.
static constexpr bool ENABLE_PULSE_OUTPUT = false;

// Plain HTTP is permitted only for an isolated, trusted test LAN. Never expose it to public Wi-Fi.
static constexpr bool ALLOW_PLAIN_HTTP_LAN = true;
// For HTTPS: set SERVER_URL to https://..., ALLOW_PLAIN_HTTP_LAN=false, and paste the issuing root CA.
// Certificate verification is never disabled. Accurate device time is needed for HTTPS.
static const char* ROOT_CA = "";
