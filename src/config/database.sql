-- MySQL 8.0.16+ | Step 1 of 2: reset the TrackPet development database.
-- Running this file deletes all existing TrackPet data and routines.
-- After it completes, run 02_procedures.sql on the same server.
SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci;
DROP DATABASE IF EXISTS TrackPet;
CREATE DATABASE TrackPet CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE TrackPet;

CREATE TABLE Users (
    userId BINARY(16) NOT NULL PRIMARY KEY,
    firstName VARCHAR(50) NOT NULL,
    surname VARCHAR(50) NOT NULL,
    username VARCHAR(25) NOT NULL UNIQUE,
    email VARCHAR(50) NOT NULL UNIQUE,
    passwordHash VARCHAR(255) NOT NULL,
    phone VARCHAR(15) UNIQUE,
    profilePhoto VARCHAR(350) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    lastLogin DATETIME NULL,
    creationDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modificationDate DATETIME ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE Pets(
  petId BINARY(16) NOT NULL PRIMARY KEY,
  petName VARCHAR(100) NOT NULL,
  petSpecies VARCHAR(100) NOT NULL,
  petColour VARCHAR(100) NOT NULL,
  petWeight DOUBLE(10, 2),
  petHeight DOUBLE(10, 2),
  petLenght DOUBLE(10, 2),
  isLost BOOLEAN NOT NULL DEFAULT 0,
  petDescription VARCHAR(500),
  petPhoto VARCHAR(350) NULL,
  ownerId BINARY(16) NOT NULL,
  isActive BOOLEAN NOT NULL DEFAULT 1,
    CONSTRAINT ownerOwnsPet FOREIGN KEY (ownerId) REFERENCES Users(userId),
  creationDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modificationDate DATETIME ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE LostReports (
    reportId BINARY(16) NOT NULL PRIMARY KEY,
    petId BINARY(16) NOT NULL,
    ownerId BINARY(16) NOT NULL,
    activePetId BINARY(16) NULL,
    clientRequestId BINARY(16) NULL,
    payloadHash CHAR(64) NULL,
    status ENUM('open', 'recovered', 'cancelled') NOT NULL DEFAULT 'open',
    lostAt DATETIME(6) NOT NULL,
    lastSeenArea VARCHAR(160) NOT NULL,
    details VARCHAR(2000) NOT NULL DEFAULT '',
    lastSeenLatitude DECIMAL(9,6) NULL,
    lastSeenLongitude DECIMAL(10,6) NULL,
    closedAt DATETIME(6) NULL,
    closureReason VARCHAR(1000) NULL,
    createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    UNIQUE KEY oneOpenReportPerPet (activePetId),
    UNIQUE KEY reportRequest (ownerId, clientRequestId),
    KEY reportFeed (status, createdAt, reportId),
    KEY ownerReportHistory (ownerId, createdAt),
    CONSTRAINT reportPet FOREIGN KEY (petId) REFERENCES Pets(petId),
    CONSTRAINT reportOwner FOREIGN KEY (ownerId) REFERENCES Users(userId),
    CONSTRAINT reportActivePet FOREIGN KEY (activePetId) REFERENCES Pets(petId),
    CONSTRAINT reportState CHECK (
        (status = 'open' AND activePetId IS NOT NULL AND activePetId = petId AND closedAt IS NULL)
        OR (status <> 'open' AND activePetId IS NULL AND closedAt IS NOT NULL)
    ),
    CONSTRAINT reportLatitude CHECK (lastSeenLatitude IS NULL OR lastSeenLatitude BETWEEN -90 AND 90),
    CONSTRAINT reportLongitude CHECK (lastSeenLongitude IS NULL OR lastSeenLongitude BETWEEN -180 AND 180),
    CONSTRAINT reportCoordinatePair CHECK ((lastSeenLatitude IS NULL) = (lastSeenLongitude IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE Sightings (
    sightingId BINARY(16) NOT NULL PRIMARY KEY,
    reportId BINARY(16) NOT NULL,
    reporterId BINARY(16) NULL,
    clientRequestId BINARY(16) NOT NULL,
    payloadHash CHAR(64) NOT NULL,
    latitude DECIMAL(9,6) NOT NULL,
    longitude DECIMAL(10,6) NOT NULL,
    accuracyMeters DECIMAL(10,2) NULL,
    observedAt DATETIME(6) NOT NULL,
    source ENUM('manual', 'gps', 'ble') NOT NULL DEFAULT 'manual',
    notes VARCHAR(1000) NOT NULL DEFAULT '',
    isRetracted BOOLEAN NOT NULL DEFAULT 0,
    retractedAt DATETIME(6) NULL,
    createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY sightingRequest (reporterId, clientRequestId),
    KEY reportTimeline (reportId, isRetracted, observedAt, createdAt, sightingId),
    KEY reporterRecent (reporterId, createdAt),
    CONSTRAINT sightingReport FOREIGN KEY (reportId) REFERENCES LostReports(reportId),
    CONSTRAINT sightingReporter FOREIGN KEY (reporterId) REFERENCES Users(userId),
    CONSTRAINT sightingLatitude CHECK (latitude BETWEEN -90 AND 90),
    CONSTRAINT sightingLongitude CHECK (longitude BETWEEN -180 AND 180),
    CONSTRAINT sightingAccuracy CHECK (accuracyMeters IS NULL OR accuracyMeters BETWEEN 0 AND 100000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

