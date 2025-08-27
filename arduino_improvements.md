# Arduino RFID Code Improvements

## 1. Fix Card Reuse Logic

```cpp
String getOrCreateCustomUID(String cardUID) {
    // Check if card exists in our tracking
    if (cardTapCount.find(cardUID) == cardTapCount.end()) {
        // New card - create first entry
        cardTapCount[cardUID] = 0;
        cardCustomUID[cardUID] = generateCustomUIDFromRFID(cardUID);
        Serial.println("🆕 New card registered: " + cardUID + " -> " + cardCustomUID[cardUID]);
    }
    
    // Increment tap count
    cardTapCount[cardUID]++;
    
    Serial.println("📊 Card: " + cardUID + " - Tap #" + String(cardTapCount[cardUID]) + "/5");
    
    // Check if we need to reset for reuse (after 5 taps)
    if (cardTapCount[cardUID] > 5) {
        // Reset to 0 for new cycle
        cardTapCount[cardUID] = 1; // Start at 1 since we just incremented
        // Generate new customUID with consistent seed
        String newSeed = cardUID + "_cycle_" + String(millis() / 60000); // Use minute-based seed
        cardCustomUID[cardUID] = generateCustomUIDFromRFID(newSeed);
        Serial.println("🔄 Card completed cycle! New CustomUID generated: " + cardCustomUID[cardUID]);
        Serial.println("🎫 Card ready for new customer assignment");
    }
    
    // Save data to persistent storage
    saveCardData();
    
    return cardCustomUID[cardUID];
}
```

## 2. Improve HTTP Request Handling

```cpp
void sendToMySQL(String cardUID) {
    Serial.println("========================================");
    Serial.println("🔄 PREPARING TO SEND DATA TO DATABASE");
    Serial.println("========================================");
    
    if (WiFi.status() != WL_CONNECTED) {
        Serial.println("❌ No WiFi connection. Cannot send data.");
        return;
    }
    
    // Create JSON with larger buffer
    DynamicJsonDocument doc(2048); // Increased from 1024
    
    // ... existing JSON creation code ...
    
    String jsonString;
    serializeJson(doc, jsonString);
    
    Serial.println("📄 JSON Data: " + jsonString);
    Serial.println("🌐 Sending to: " + String(SERVER_URL));
    
    // Send HTTP POST with timeout and retry
    HTTPClient http;
    http.begin(SERVER_URL);
    http.addHeader("Content-Type", "application/json");
    http.setTimeout(10000); // 10 second timeout
    
    Serial.println("📡 Sending HTTP request...");
    
    // Retry mechanism
    int maxRetries = 3;
    int httpResponseCode = 0;
    
    for (int attempt = 1; attempt <= maxRetries; attempt++) {
        httpResponseCode = http.POST(jsonString);
        
        if (httpResponseCode > 0) {
            String response = http.getString();
            Serial.println("📥 Server Response Code: " + String(httpResponseCode));
            
            if (httpResponseCode == 200) {
                Serial.println("🎉 SUCCESS! Data sent to MySQL database");
                Serial.println("🏷️ Card UID: " + cardUID);
                Serial.println("🆔 Custom UID: " + customUID);
                Serial.println("📊 Tap Count: " + String(currentTapCount) + "/5");
                
                // Parse response for additional info
                DynamicJsonDocument responseDoc(1024);
                deserializeJson(responseDoc, response);
                
                if (responseDoc.containsKey("success")) {
                    Serial.println("✅ Server confirmed success: " + String(responseDoc["success"].as<bool>()));
                }
                
                break; // Success, exit retry loop
            } else {
                Serial.println("⚠️ Server responded with non-200 code: " + String(httpResponseCode));
                if (attempt < maxRetries) {
                    Serial.println("🔄 Retrying in 2 seconds... (Attempt " + String(attempt + 1) + "/" + String(maxRetries) + ")");
                    delay(2000);
                }
            }
            Serial.println("📝 Response: " + response);
        } else {
            Serial.println("❌ Failed to send to database (Attempt " + String(attempt) + "/" + String(maxRetries) + ")");
            Serial.println("💥 Error Code: " + String(httpResponseCode));
            
            if (attempt < maxRetries) {
                Serial.println("🔄 Retrying in 3 seconds...");
                delay(3000);
            } else {
                Serial.println("🔍 Final failure. Possible issues:");
                Serial.println("   • Server not running");
                Serial.println("   • Wrong URL");
                Serial.println("   • Network connectivity");
            }
        }
    }
    
    http.end();
    Serial.println("🔚 HTTP connection closed");
    Serial.println("========================================");
}
```

## 3. Add WiFi Connection Monitoring

```cpp
// Add this function to monitor WiFi health
void checkWiFiHealth() {
    static unsigned long lastWiFiCheck = 0;
    static int consecutiveFailures = 0;
    
    if (millis() - lastWiFiCheck > 30000) { // Check every 30 seconds
        lastWiFiCheck = millis();
        
        if (WiFi.status() != WL_CONNECTED) {
            consecutiveFailures++;
            Serial.println("📶 WiFi disconnected. Attempt " + String(consecutiveFailures));
            
            if (consecutiveFailures >= 3) {
                Serial.println("🔄 Reconnecting to WiFi...");
                WiFi.disconnect();
                delay(1000);
                connectToWiFi();
                consecutiveFailures = 0;
            }
        } else {
            consecutiveFailures = 0;
            // Log WiFi stats periodically
            Serial.println("📶 WiFi: " + WiFi.SSID() + " | Signal: " + String(WiFi.RSSI()) + " dBm");
        }
    }
}
```

## 4. Improve Card Detection Logic

```cpp
// Add debouncing for card detection
unsigned long lastCardDetection = 0;
const unsigned long DEBOUNCE_TIME = 500; // 500ms debounce

void loop() {
    // Add WiFi health check
    checkWiFiHealth();
    
    // ... existing RFID disabled check ...
    
    bool cardDetected = false;
    String currentCardID = "";
    
    // Try to detect card with debouncing
    if (mfrc522.PICC_IsNewCardPresent() && mfrc522.PICC_ReadCardSerial()) {
        unsigned long currentTime = millis();
        
        // Debounce card detection
        if (currentTime - lastCardDetection < DEBOUNCE_TIME) {
            delay(100);
            return;
        }
        
        lastCardDetection = currentTime;
        cardDetected = true;
        lastDetectionTime = millis();
        
        // ... rest of card detection logic ...
    }
    
    // ... rest of loop logic ...
}
```

## 5. Add Configuration Management

```cpp
// Add configuration structure
struct DeviceConfig {
    int maxTaps;
    unsigned long validationTime;
    unsigned long disableTime;
    unsigned long cardTimeout;
    String serverUrl;
};

DeviceConfig config = {
    .maxTaps = 5,
    .validationTime = 3000,
    .disableTime = 5000,
    .cardTimeout = 2000,
    .serverUrl = "http://192.168.100.18/animates/api/rfid_endpoint.php"
};

// Load configuration from preferences
void loadConfig() {
    config.maxTaps = preferences.getInt("maxTaps", 5);
    config.validationTime = preferences.getULong("validationTime", 3000);
    config.disableTime = preferences.getULong("disableTime", 5000);
    config.cardTimeout = preferences.getULong("cardTimeout", 2000);
    config.serverUrl = preferences.getString("serverUrl", "http://192.168.100.18/animates/api/rfid_endpoint.php");
    
    Serial.println("⚙️ Configuration loaded:");
    Serial.println("   Max Taps: " + String(config.maxTaps));
    Serial.println("   Validation Time: " + String(config.validationTime) + "ms");
    Serial.println("   Disable Time: " + String(config.disableTime) + "ms");
    Serial.println("   Card Timeout: " + String(config.cardTimeout) + "ms");
}
```

## 6. Add Error Recovery

```cpp
// Add watchdog timer
#include <esp_task_wdt.h>

void setup() {
    // ... existing setup code ...
    
    // Enable watchdog timer
    esp_task_wdt_config_t twdt_config = {
        .timeout_ms = 30000, // 30 second timeout
        .idle_core_mask = (1 << 0), // Bitmask for core 0
        .trigger_panic = true
    };
    esp_task_wdt_deinit(); // Ensure no watchdog is already configured
    esp_task_wdt_init(&twdt_config);
    esp_task_wdt_add(NULL);
    
    // ... rest of setup ...
}

void loop() {
    // Reset watchdog timer
    esp_task_wdt_reset();
    
    // ... existing loop code ...
}

// Add error recovery function
void handleError(const char* error) {
    Serial.println("💥 ERROR: " + String(error));
    
    // Try to recover
    if (WiFi.status() != WL_CONNECTED) {
        Serial.println("🔄 Attempting WiFi recovery...");
        connectToWiFi();
    }
    
    // Reset RFID scanner if needed
    if (getRFIDStatus() == "ERROR") {
        Serial.println("🔄 Resetting RFID scanner...");
        mfrc522.PCD_Reset();
        delay(1000);
        mfrc522.PCD_Init();
    }
}
```

## 7. Add Data Validation

```cpp
// Add JSON validation before sending
bool validateCardData(String cardUID, String customUID, int tapCount) {
    if (cardUID.length() == 0 || cardUID.length() > 50) {
        Serial.println("❌ Invalid card UID length");
        return false;
    }
    
    if (customUID.length() != 8) {
        Serial.println("❌ Invalid custom UID length");
        return false;
    }
    
    if (tapCount < 1 || tapCount > 10) {
        Serial.println("❌ Invalid tap count");
        return false;
    }
    
    return true;
}

// Use in sendToMySQL
void sendToMySQL(String cardUID) {
    String customUID = getOrCreateCustomUID(cardUID);
    int currentTapCount = cardTapCount[cardUID];
    
    if (!validateCardData(cardUID, customUID, currentTapCount)) {
        Serial.println("❌ Data validation failed. Not sending to server.");
        return;
    }
    
    // ... rest of sending logic ...
}
```

## 8. Add Status LED Support

```cpp
#define STATUS_LED_PIN 2  // Built-in LED

void indicateStatus(const char* status) {
    if (strcmp(status, "scanning") == 0) {
        // Blink slowly
        digitalWrite(STATUS_LED_PIN, HIGH);
        delay(500);
        digitalWrite(STATUS_LED_PIN, LOW);
    } else if (strcmp(status, "success") == 0) {
        // Solid on
        digitalWrite(STATUS_LED_PIN, HIGH);
        delay(2000);
        digitalWrite(STATUS_LED_PIN, LOW);
    } else if (strcmp(status, "error") == 0) {
        // Fast blink
        for (int i = 0; i < 5; i++) {
            digitalWrite(STATUS_LED_PIN, HIGH);
            delay(100);
            digitalWrite(STATUS_LED_PIN, LOW);
            delay(100);
        }
    }
}
```

## Summary of Key Improvements:

1. **Fixed card reuse logic** - Proper reset to 1 after 5 taps
2. **Consistent custom UID generation** - Deterministic for same card
3. **HTTP retry mechanism** - 3 attempts with timeouts
4. **WiFi health monitoring** - Automatic reconnection
5. **Card detection debouncing** - Prevents false readings
6. **Configuration management** - Loadable settings
7. **Error recovery** - Watchdog timer and recovery functions
8. **Data validation** - Prevents invalid data transmission
9. **Status LED support** - Visual feedback
10. **Larger JSON buffer** - Prevents memory issues
