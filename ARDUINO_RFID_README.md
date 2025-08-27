# ESP32 RFID Scanner - Complete System Documentation

## 📋 Overview

This ESP32-based RFID scanner is designed for pet grooming salon management. It reads RFID cards, tracks tap counts, generates custom UIDs, and sends data to a MySQL database via HTTP API.

## 🔧 Hardware Requirements

### Components:
- **ESP32 Development Board**
- **MFRC522 RFID Reader Module**
- **Buzzer (for audio feedback)**
- **Built-in LED (for status indication)**
- **RFID Cards/Tags**

### Pin Connections:
```
ESP32 Pin 21 → MFRC522 SDA/SS
ESP32 Pin 22 → MFRC522 RST
ESP32 Pin 17 → Buzzer (+)
ESP32 Pin 2  → Built-in LED (status)
GND → MFRC522 GND, Buzzer (-)
3.3V → MFRC522 VCC
```

## 🚀 System Flow

### 1. **Initialization Phase (setup())**
```
┌─────────────────────────────────────┐
│ 1. Serial Communication (115200 baud)│
│ 2. Pin Configuration                 │
│ 3. Watchdog Timer Setup              │
│ 4. WiFi Connection                   │
│ 5. NTP Time Synchronization          │
│ 6. RFID Scanner Initialization       │
│ 7. Load Saved Data & Configuration   │
└─────────────────────────────────────┘
```

### 2. **Main Operation Loop (loop())**
```
┌─────────────────────────────────────┐
│ 1. Reset Watchdog Timer             │
│ 2. Check WiFi Health                │
│ 3. Check RFID Disabled Status       │
│ 4. Detect RFID Card                 │
│ 5. Process Card Validation          │
│ 6. Send Data to Database            │
└─────────────────────────────────────┘
```

## 🎯 Card Detection & Validation Flow

### **Step-by-Step Process:**

1. **Card Detection**
   - Device continuously scans for RFID cards
   - Uses debouncing (500ms) to prevent false readings
   - Detects card UID and converts to string format

2. **Validation Timer**
   - Card must be held for **3 seconds** for validation
   - Shows countdown timer in serial monitor
   - Provides audio feedback (single beep on detection)

3. **Success Processing**
   - After 3 seconds: Card is validated
   - Generates/updates custom UID
   - Increments tap count
   - Sends data to database
   - Provides success feedback (3 beeps + LED)

4. **Cooldown Period**
   - Device disabled for **5 seconds** after success
   - Prevents accidental double-taps
   - Shows countdown timer

## 📊 Tap Count System

### **Tap Count Progression:**
```
Tap 1 → "checked-in"    (Pet arrives)
Tap 2 → "bathing"       (Pet starts bathing)
Tap 3 → "grooming"      (Pet starts grooming)
Tap 4 → "ready"         (Pet ready for pickup)
Tap 5 → "completed"     (Owner picks up pet)
```

### **Card Reuse Logic:**
- After 5 taps, card is reset for reuse
- New custom UID is generated
- Tap count resets to 1
- Card can be assigned to new customer

## 🔄 Data Flow

### **Upload Retry Mechanism:**
```
┌─────────────────────────────────────┐
│ 1. Card Detected & Validated        │
│ 2. Calculate New Tap Count          │
│ 3. Attempt Database Upload          │
│ 4. If Success → Increment Tap Count │
│ 5. If Failed → Add to Retry Queue   │
│ 6. Retry Every 30 Seconds           │
└─────────────────────────────────────┘
```

**Key Features:**
- **Tap count is NOT incremented until upload succeeds**
- **Failed uploads are stored in memory queue**
- **Automatic retry every 30 seconds**
- **Queue limited to 10 failed uploads**
- **Old failed uploads (>1 hour) are discarded**

### **Local Storage (ESP32):**
```
Preferences Storage:
├── cardKeys: "card1,card2,card3,"
├── tap_card1: 3
├── uid_card1: "ABC12345"
├── tap_card2: 1
├── uid_card2: "XYZ67890"
└── ... (for each card)
```

### **Database Communication:**
```
ESP32 → HTTP POST → Server API → MySQL Database
```

**JSON Payload:**
```json
{
  "card_uid": "69:33:b2:01",
  "custom_uid": "ABC12345",
  "tap_count": 3,
  "max_taps": 5,
  "device_info": "ESP32-RFID-Scanner",
  "wifi_network": "HUAWEI-2.4G-x6Nj",
  "signal_strength": -45,
  "validation_status": "approved",
  "readable_time": "2025-08-27 10:30:15",
  "timestamp_value": "2025-08-27 10:30:15",
  "rfid_scanner_status": "OK",
  "validation_time_ms": 3000
}
```

## 🌐 WiFi & Network Management

### **WiFi Connection:**
- Supports multiple WiFi networks (fallback)
- Automatic reconnection on failure
- Health monitoring every 30 seconds
- Signal strength reporting

### **HTTP Communication:**
- **Endpoint**: `http://192.168.100.18/animates/api/rfid_endpoint.php`
- **Method**: POST
- **Content-Type**: application/json
- **Timeout**: 10 seconds
- **Retry**: 3 attempts with delays
- **Failed Upload Queue**: Stores failed uploads for retry
- **Automatic Retry**: Every 30 seconds for failed uploads
- **Queue Limit**: Maximum 10 failed uploads stored
- **Age Limit**: Failed uploads older than 1 hour are discarded

## 🔧 Configuration System

### **Configurable Parameters:**
```cpp
struct DeviceConfig {
    int maxTaps = 5;                    // Maximum taps per card
    unsigned long validationTime = 3000; // Card hold time (ms)
    unsigned long disableTime = 5000;    // Cooldown time (ms)
    unsigned long cardTimeout = 2000;    // Card removal timeout (ms)
    String serverUrl = "...";           // API endpoint
};
```

### **Loading Configuration:**
- Stored in ESP32 preferences
- Loaded on startup
- Can be modified via preferences API

## 🛡️ Error Handling & Recovery

### **WiFi Issues:**
- Automatic reconnection attempts
- Fallback to alternative networks
- Health monitoring with failure counting

### **RFID Scanner Issues:**
- Status checking via version register
- Automatic reset on communication failure
- Error indication via LED

### **HTTP Communication:**
- Retry mechanism (3 attempts per upload)
- Failed upload queue with automatic retry
- Timeout handling (10 seconds)
- Response validation
- Error logging
- Persistent retry for failed uploads

### **System Recovery:**
- Watchdog timer (30-second timeout)
- Automatic restart on system hang
- Error recovery functions

## 📱 User Interface

### **Serial Monitor Output:**
```
========================================
🚀 ESP32 RFID SCANNER STARTING UP
========================================
🌐 Connecting to WiFi networks...
📡 Attempting: HUAWEI-2.4G-x6Nj
✅ Connected to: HUAWEI-2.4G-x6Nj
🌐 IP: 192.168.100.25
📶 Signal: -45 dBm
⏰ Configuring time with NTP server (5 second max)...
✅ Time synchronized successfully!
📅 Current time: 2025-08-27 10:30:15
🔧 Initializing RFID scanner...
✅ RFID Scanner connected successfully!
========================================
🎯 RFID READER READY!
📱 Tap an RFID card/tag to read its ID
⏱️ Hold card for 3 seconds for validation
========================================
```

### **Card Detection Output:**
```
🆕 New card detected - Starting 3-second validation...
🔍 SCANNING - Card UID: 69:33:b2:01
🏷️ Card ID (String): 69:33:b2:01
⏳ Status: Hold for 3 more seconds for validation
----------------------------------------
```

### **Success Output:**
```
🎉 *** SUCCESS! CARD VALIDATED! ***
✅ Card UID: 69:33:b2:01 - APPROVED
🏁 Validation completed successfully!
========================================
🔄 PREPARING TO SEND DATA TO DATABASE
========================================
📝 Processing card data...
🕒 Time: 2025-08-27 10:30:18
🆔 Custom UID: ABC12345
🔢 Tap count: 3
📄 JSON Data: {"card_uid":"69:33:b2:01",...}
🌐 Sending to: http://192.168.100.18/animates/api/rfid_endpoint.php
📡 Sending HTTP request...
📥 Server Response Code: 200
🎉 SUCCESS! Data sent to MySQL database
✅ Server confirmed success: true
```

## 🎨 Visual & Audio Feedback

### **LED Status Indication:**
- **Scanning**: Slow blink (500ms on/off)
- **Success**: Solid on for 2 seconds
- **Error**: Fast blink (5x 100ms on/off)

### **Buzzer Feedback:**
- **Card Detected**: Single short beep (100ms)
- **Success**: Three short beeps (200ms each)
- **Error**: No beep (LED indicates error)

## 🔧 Customization Guide

### **Changing WiFi Networks:**
```cpp
WiFiCredentials wifiList[] = {
  {"YourWiFi1", "password1"},
  {"YourWiFi2", "password2"},
  // Add more networks as needed
};
```

### **Modifying Timeouts:**
```cpp
const unsigned long VALIDATION_TIME = 3000; // 3 seconds
const unsigned long DISABLE_TIME = 5000;     // 5 seconds
const unsigned long DEBOUNCE_TIME = 500;     // 500ms
```

### **Changing Server URL:**
```cpp
#define SERVER_URL "http://your-server.com/api/endpoint.php"
```

### **Adjusting Max Taps:**
```cpp
DeviceConfig config = {
    .maxTaps = 5,  // Change this value
    // ... other config
};
```

## 🚨 Troubleshooting

### **Common Issues:**

1. **WiFi Connection Fails**
   - Check WiFi credentials
   - Verify network availability
   - Check signal strength

2. **RFID Scanner Not Working**
   - Verify pin connections
   - Check power supply (3.3V)
   - Test with known working card

3. **Database Communication Fails**
   - Verify server URL
   - Check network connectivity
   - Verify API endpoint is working

4. **Card Not Detected**
   - Check card compatibility (MIFARE Classic)
   - Verify card is not damaged
   - Check RFID module connections

### **Debug Information:**
- All operations logged to Serial Monitor
- WiFi status and signal strength reported
- HTTP response codes and messages
- RFID scanner status checking

## 📈 Performance Characteristics

### **Response Times:**
- Card detection: < 100ms
- Validation period: 3000ms (configurable)
- HTTP request: < 10 seconds (with retry)
- Cooldown period: 5000ms (configurable)

### **Memory Usage:**
- JSON buffer: 2048 bytes
- Response buffer: 1024 bytes
- Card data storage: Variable (based on number of cards)

### **Reliability Features:**
- Watchdog timer protection
- Automatic error recovery
- Persistent data storage
- Retry mechanisms
- Health monitoring
- Failed upload queue with automatic retry
- Tap count only incremented after successful upload

## 🔄 Update & Maintenance

### **Firmware Updates:**
- Upload new code via Arduino IDE
- Configuration preserved in preferences
- Card data maintained across updates

### **Data Backup:**
- Card data stored in ESP32 preferences
- Survives power cycles
- Can be cleared via preferences.clear()

### **Monitoring:**
- Serial monitor for real-time status
- LED indicators for visual feedback
- WiFi signal strength monitoring
- Error logging and reporting

---

## 📞 Support

For technical support or questions about this RFID system:
- Check the troubleshooting section above
- Review the serial monitor output for error messages
- Verify all hardware connections
- Test with known working components

This system is designed for reliability and ease of use in a pet grooming salon environment, providing accurate tracking and seamless integration with your existing database system.
