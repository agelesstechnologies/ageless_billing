#pragma once
// Target: AI-Thinker ESP32-CAM + OV2640 + PSRAM. microSD is not used.
// Every reader has a different ERP token. Ride points always come from the server.
static const char* WIFI_SSID = "YOUR_WIFI_NAME";
static const char* WIFI_PASSWORD = "YOUR_WIFI_PASSWORD";
static const char* SERVER_URL = "YOUR_ERP_SERVER_URL";
static const char* MACHINE_TOKEN = "PASTE_UNIQUE_READER_TOKEN";
static const char* EXPECTED_MACHINE_SERIAL = "YOUR_READER_SERIAL";
static constexpr int EXPECTED_MACHINE_ID = 0; // ERP download fills this automatically.
static constexpr int PULSE_PIN = 13; // Free only with no microSD; camera pins must not be reused.
static constexpr bool PULSE_ACTIVE_LOW = false;
// Bench mode: decode QR and read config, but NEVER debit or pulse.
static constexpr bool ENABLE_PULSE_OUTPUT = false;
static constexpr bool ALLOW_PLAIN_HTTP_LAN = true; // Private IPv4 / .local only.
// HTTPS must verify the issuing root certificate and have correct NTP time.
static const char* ROOT_CA = "";
static constexpr uint32_t QR_REMOVAL_MS = 2000;
static constexpr uint32_t MAX_SCAN_AGE_MS = 3000;
