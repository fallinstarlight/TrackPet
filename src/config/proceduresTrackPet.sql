USE TrackPet;
DELIMITER $$

-- ============================================================
-- USER PROCEDURES
-- ============================================================

DROP PROCEDURE IF EXISTS getHash $$
CREATE PROCEDURE getHash(IN inUsername VARCHAR(50), IN isEmail BOOLEAN)
BEGIN
  IF isEmail THEN
    IF NOT EXISTS (SELECT 1 FROM Users WHERE email = inUsername AND status = 'active') THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User does not exist';
    ELSE
      SELECT userId, firstName, surname, username, passwordHash
      FROM Users WHERE email = inUsername AND status = 'active';
    END IF;
  ELSE
    IF NOT EXISTS (SELECT 1 FROM Users WHERE username = inUsername AND status = 'active') THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User does not exist';
    ELSE
      SELECT userId, firstName, surname, username, passwordHash
      FROM Users WHERE username = inUsername AND status = 'active';
    END IF;
  END IF;
END $$

DROP PROCEDURE IF EXISTS createUser $$
CREATE PROCEDURE createUser(
  IN inUserId BINARY (16),
  IN inName VARCHAR(50),
  IN inSurname VARCHAR(50),
  IN inUsername VARCHAR(25),
  IN inEmail VARCHAR(50),
  IN inPasswordHash VARCHAR(255),
  IN inPhone VARCHAR(15),
  IN inProfilePhoto VARCHAR(350)
)
BEGIN
  IF EXISTS (SELECT 1 FROM Users WHERE username = inUsername) THEN
    SIGNAL SQLSTATE '45001' SET MESSAGE_TEXT = 'Username already exists';
  ELSEIF EXISTS (
    SELECT 1 FROM Users
    WHERE email = inEmail
       OR (inPhone IS NOT NULL AND inPhone <> '' AND phone = inPhone)
  ) THEN
    SIGNAL SQLSTATE '45002' SET MESSAGE_TEXT = 'Email or phone already in use';
  ELSE
    INSERT INTO Users (userId, firstName, surname, username, email, passwordHash, phone, profilePhoto)
    VALUES (
      inUserId,
      inName,
      inSurname,
      inUsername,
      inEmail,
      inPasswordHash,
      NULLIF(COALESCE(inPhone, ''), ''),
      NULLIF(COALESCE(inProfilePhoto, ''), '')
    );
    SELECT userId, firstName, surname, username FROM Users WHERE userId = LAST_INSERT_ID();
  END IF;
END $$

DROP PROCEDURE IF EXISTS editUser $$
CREATE PROCEDURE editUser(
  IN inId BINARY(16),
  IN inName VARCHAR(50),
  IN inSurname VARCHAR(50),
  IN inUsername VARCHAR(25),
  IN inEmail VARCHAR(50),
  IN inPasswordHash VARCHAR(255),
  IN inPhone VARCHAR(15),
  IN inProfilePhoto VARCHAR(350)
)
BEGIN
  IF NOT EXISTS (SELECT 1 FROM Users WHERE userId = inId) THEN
    SIGNAL SQLSTATE '45003' SET MESSAGE_TEXT = 'User does not exist';
  ELSEIF EXISTS (SELECT 1 FROM Users WHERE userId = inId AND status = 'inactive') THEN
    SIGNAL SQLSTATE '45004' SET MESSAGE_TEXT = 'User is not available';
  ELSEIF EXISTS (
    SELECT 1 FROM Users
    WHERE userId != inId
      AND ((inEmail <> '' AND email = inEmail)
        OR (inPhone <> '' AND phone = inPhone)
        OR (inUsername <> '' AND username = inUsername))
  ) THEN
    SIGNAL SQLSTATE '45005' SET MESSAGE_TEXT = 'Email, phone or username already in use';
  ELSE
    UPDATE Users SET
      firstName    = COALESCE(NULLIF(inName, ''), firstName),
      surname      = COALESCE(NULLIF(inSurname, ''), surname),
      username     = COALESCE(NULLIF(inUsername, ''), username),
      email        = COALESCE(NULLIF(inEmail, ''), email),
      passwordHash = COALESCE(NULLIF(inPasswordHash, ''), passwordHash),
      phone        = COALESCE(NULLIF(inPhone, ''), phone),
      profilePhoto = COALESCE(NULLIF(inProfilePhoto, ''), profilePhoto)
    WHERE userId = inId;

    SELECT userId, firstName, surname, username FROM Users WHERE userId = inId;
  END IF;
END $$

DROP PROCEDURE IF EXISTS deleteUser $$
CREATE PROCEDURE deleteUser(IN inId BINARY(16))
BEGIN
  IF NOT EXISTS (SELECT 1 FROM Users WHERE userId = inId) THEN
    SIGNAL SQLSTATE '45003' SET MESSAGE_TEXT = 'User does not exist';
  ELSEIF EXISTS (SELECT 1 FROM Users WHERE userId = inId AND status = 'inactive') THEN
    SIGNAL SQLSTATE '45004' SET MESSAGE_TEXT = 'User is not available';
  ELSE
    UPDATE Users SET status = 'inactive' WHERE userId = inId;
  END IF;
END $$

DROP PROCEDURE IF EXISTS getUserByUsername $$
CREATE PROCEDURE getUserByUsername(IN inUsername VARCHAR(25))
BEGIN
  IF NOT EXISTS (SELECT 1 FROM Users WHERE username = inUsername AND status = 'active') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User does not exist';
  ELSE
    SELECT userId, firstName, surname, username, email, phone, profilePhoto,
           lastLogin, creationDate, modificationDate
    FROM Users WHERE username = inUsername AND status = 'active';
  END IF;
END $$

DROP PROCEDURE IF EXISTS getUserById $$
CREATE PROCEDURE getUserById(IN inId BINARY(16))
BEGIN
  IF NOT EXISTS (SELECT 1 FROM Users WHERE userId = inId AND status = 'active') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User does not exist';
  ELSE
    SELECT userId, firstName, surname, username, email, phone, profilePhoto,
           lastLogin, creationDate, modificationDate
    FROM Users WHERE userId = inId AND status = 'active';
  END IF;
END $$

-- ============================================================
-- PET PROCEDURES
-- ============================================================

DROP PROCEDURE IF EXISTS createPet $$
CREATE PROCEDURE createPet(
  IN inPetId BINARY(16),
  IN inPetName VARCHAR(100),
  IN inPetSpecies VARCHAR(100),
  IN inPetColour VARCHAR(100),
  IN inPetWeight DOUBLE(10, 2),
  IN inPetHeight DOUBLE(10, 2),
  IN inPetLenght DOUBLE(10, 2),
  IN inPetDescription VARCHAR(500),
  IN inOwnerId BINARY(16)
)
BEGIN
  IF NOT EXISTS (SELECT 1 FROM Users WHERE userId = inOwnerId AND status = 'active') THEN
    SIGNAL SQLSTATE '45006' SET MESSAGE_TEXT = 'Owner does not exist';
  ELSEIF EXISTS (SELECT 1 FROM Pets WHERE petId = inPetId) THEN
    SIGNAL SQLSTATE '45007' SET MESSAGE_TEXT = 'Pet ID collision, please retry';
  ELSE
    INSERT INTO Pets (
      petId, petName, petSpecies, petColour, petWeight,
      petHeight, petLenght, petDescription, ownerId
    ) VALUES (
      inPetId, inPetName, inPetSpecies, inPetColour,
      inPetWeight, inPetHeight, inPetLenght,
      NULLIF(COALESCE(inPetDescription, ''), ''),
      inOwnerId
    );
    SELECT petId, petName, petSpecies, petColour, petWeight,
           petHeight, petLenght, isLost, petDescription, ownerId,
           isActive, creationDate, modificationDate
    FROM Pets WHERE petId = inPetId;
  END IF;
END $$

DROP PROCEDURE IF EXISTS editPet $$
CREATE PROCEDURE editPet(
  IN inPetId BINARY(16),
  IN inPetName VARCHAR(100),
  IN inPetColour VARCHAR(100),
  IN inPetWeight DOUBLE(10, 2),
  IN inPetHeight DOUBLE(10, 2),
  IN inPetLenght DOUBLE(10, 2),
  IN inPetDescription VARCHAR(500)
)
BEGIN
  IF NOT EXISTS (SELECT 1 FROM Pets WHERE petId = inPetId AND isActive = 1) THEN
    SIGNAL SQLSTATE '45008' SET MESSAGE_TEXT = 'Pet does not exist or is not active';
  ELSE
    UPDATE Pets SET
      petName        = COALESCE(NULLIF(inPetName, ''), petName),
      petColour      = COALESCE(NULLIF(inPetColour, ''), petColour),
      petWeight      = COALESCE(inPetWeight, petWeight),
      petHeight      = COALESCE(inPetHeight, petHeight),
      petLenght      = COALESCE(inPetLenght, petLenght),
      petDescription = COALESCE(NULLIF(inPetDescription, ''), petDescription)
    WHERE petId = inPetId;

    SELECT petId, petName, petSpecies, petColour, petWeight,
           petHeight, petLenght, isLost, petDescription, ownerId,
           isActive, creationDate, modificationDate
    FROM Pets WHERE petId = inPetId;
  END IF;
END $$

DROP PROCEDURE IF EXISTS changePetOwnership $$
CREATE PROCEDURE changePetOwnership(
  IN inPetId BINARY(16),
  IN oldOwnerId BINARY(16),
  IN newOwnerId BINARY(16)
)
BEGIN
  IF NOT EXISTS (SELECT 1 FROM Users WHERE userId = oldOwnerId) THEN
    SIGNAL SQLSTATE '45006' SET MESSAGE_TEXT = 'Owner does not exist';
  ELSEIF NOT EXISTS (SELECT 1 FROM Users WHERE userId = newOwnerId) THEN
    SIGNAL SQLSTATE '45009' SET MESSAGE_TEXT = 'New owner does not exist';
  ELSEIF NOT EXISTS (SELECT 1 FROM Pets WHERE petId = inPetId AND isActive = 1) THEN
    SIGNAL SQLSTATE '45008' SET MESSAGE_TEXT = 'Pet does not exist or is not active';
  ELSEIF oldOwnerId = newOwnerId THEN
    SIGNAL SQLSTATE '45010' SET MESSAGE_TEXT = 'Both owner IDs are the same';
  ELSE
    UPDATE Pets SET ownerId = newOwnerId WHERE petId = inPetId;
  END IF;
END $$

DROP PROCEDURE IF EXISTS deletePet $$
CREATE PROCEDURE deletePet(IN inPetId BINARY(16))
BEGIN
  IF NOT EXISTS (SELECT 1 FROM Pets WHERE petId = inPetId AND isActive = 1) THEN
    SIGNAL SQLSTATE '45008' SET MESSAGE_TEXT = 'Pet does not exist or is already inactive';
  ELSE
    UPDATE Pets SET isActive = 0 WHERE petId = inPetId;
  END IF;
END $$

DROP PROCEDURE IF EXISTS toggleLostStatus $$
CREATE PROCEDURE toggleLostStatus(
  IN inPetId BINARY(16),
  IN inOwnerId BINARY(16)
)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM Pets pt
    JOIN Users us ON pt.ownerId = us.userId
    WHERE pt.ownerId = inOwnerId AND pt.petId = inPetId AND pt.isActive = 1
  ) THEN
    SIGNAL SQLSTATE '45011' SET MESSAGE_TEXT = 'User, pet or ownership does not exist';
  ELSE
    UPDATE Pets SET isLost = NOT isLost WHERE petId = inPetId;
    SELECT isLost FROM Pets WHERE petId = inPetId;
  END IF;
END $$

DROP PROCEDURE IF EXISTS getLostStatus $$
CREATE PROCEDURE getLostStatus(IN inPetId BINARY(16))
BEGIN
  IF NOT EXISTS (SELECT 1 FROM Pets WHERE petId = inPetId AND isActive = 1) THEN
    SIGNAL SQLSTATE '45008' SET MESSAGE_TEXT = 'Pet does not exist';
  ELSE
    SELECT isLost FROM Pets WHERE petId = inPetId;
  END IF;
END $$

DROP PROCEDURE IF EXISTS getPetById $$
CREATE PROCEDURE getPetById(IN inPetId BINARY(16))
BEGIN
  IF NOT EXISTS (SELECT 1 FROM Pets WHERE petId = inPetId AND isActive = 1) THEN
    SIGNAL SQLSTATE '45008' SET MESSAGE_TEXT = 'Pet does not exist';
  ELSE
    SELECT petId, petName, petSpecies, petColour, petWeight,
           petHeight, petLenght, isLost, petDescription, ownerId,
           isActive, creationDate, modificationDate
    FROM Pets WHERE petId = inPetId AND isActive = 1;
  END IF;
END $$

DROP PROCEDURE IF EXISTS getPetsByOwner $$
CREATE PROCEDURE getPetsByOwner(IN inOwnerId BINARY(16))
BEGIN
  IF NOT EXISTS (SELECT 1 FROM Users WHERE userId = inOwnerId AND status = 'active') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User does not exist';
  ELSE
    SELECT petId, petName, petSpecies, petColour, petWeight,
           petHeight, petLenght, isLost, petDescription, ownerId,
           isActive, creationDate, modificationDate
    FROM Pets WHERE ownerId = inOwnerId AND isActive = 1;
  END IF;
END $$

DELIMITER ;