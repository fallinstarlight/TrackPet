#include <BLEDevice.h>
#include <ctype.h>

// Copy the registered pet's canonical UUID here before flashing its collar.
constexpr char PET_ID[] = "01a1b1d1-45bc-767e-9c4d-e5bb950fc061";

bool configuredPetId() {
  if (strlen(PET_ID) != 36) return false;
  for (size_t i = 0; i < 36; ++i) {
    const bool separator = i == 8 || i == 13 || i == 18 || i == 23;
    if (separator ? PET_ID[i] != '-' : !isxdigit(static_cast<unsigned char>(PET_ID[i]))) return false;
  }
  return true;
}

void setup() {
  Serial.begin(115200);
  if (!configuredPetId()) {
    Serial.println("Set PET_ID to this pet's registered UUID and flash again.");
    return;
  }

  BLEDevice::init("TrackPet");
  BLEAdvertisementData packet;
  packet.setFlags(0x06);
  packet.setName("TrackPet");
  packet.setCompleteServices(BLEUUID(String(PET_ID)));

  BLEAdvertising *radio = BLEDevice::getAdvertising();
  radio->setAdvertisementType(ADV_TYPE_NONCONN_IND);
  radio->setScanResponse(false);
  radio->setMinInterval(800);  // Units of 0.625 ms: 500 to 600 ms.
  radio->setMaxInterval(960);
  if (!radio->setAdvertisementData(packet) || !radio->start()) {
    Serial.println("BLE advertising failed. Reset the board to retry.");
    return;
  }
  Serial.printf("TrackPet beacon started for %s\n", PET_ID);
}

void loop() {
  delay(1000);  // The BLE controller repeats the advertisement independently.
}
