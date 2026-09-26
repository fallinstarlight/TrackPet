drop schema if exists TrackPet;

create schema if not exists TrackPet;

use TrackPet;

create table Users (
    id int not null auto_increment primary key,
    name varchar(50) not null,
    surname varchar(50) not null,
    username varchar(25) not null unique,
    email varchar(50) not null unique,
    passwordHash varchar(255) not null,
    phone varchar(15) unique,
    profilePhoto varchar(350) null,
    status enum('active', 'inactive') not null default 'active',
    lastLogin datetime null,
    creationDate datetime not null default current_timestamp,
    modificationDate datetime on update current_timestamp
);

delimiter $$

drop procedure if exists getHash $$

create procedure getHash(inUsername varchar(50), isEmail boolean)
begin
  if isEmail
  then
    if not exists (select 1 from Users where email = inUsername and status = 'active')
    then
      select 1 as flag;
    else
      select 0 as flag, id, name, surname, username, passwordHash from Users where email = inUsername;
    end if;
  else 
    if not exists (select 1 from Users where username = inUsername and status = 'active')
    then
      select 1 as flag;
    else
      select 0 as flag, id, name, surname, username, passwordHash from Users where username = inUsername;
    end if;
  end if;
end $$

drop procedure if exists createUser$$

create procedure createUser(
  inName varchar(50), 
  inSurname varchar(50), 
  inUsername varchar(25), 
  inEmail varchar(50), 
  inPasswordHash varchar(255), 
  inPhone varchar(15), 
  inProfilePhoto varchar(350)
)
begin
  if not exists (select 1 from Users where username = inUsername)
  then
    if not exists (select 1 from Users where email = inEmail or (phone is not null and phone = inPhone))
    then
      insert into Users (name, surname, username, email, passwordHash, phone, profilePhoto)
      values
      (
        inName,
        inSurname,
        inUsername,
        inEmail,
        inPasswordHash,
        nullif(coalesce(inPhone, ''), ''),
        nullif(coalesce(inProfilePhoto, ''), '')
      );
      select name, surname, username from Users where id = last_insert_id();
    else
      select 2 as flag, 'mail or phone already in use' as message;
    end if;
  else
    select 2 as flag, 'username already exists' as message;
  end if;
end $$