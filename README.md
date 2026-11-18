# TrackPet

TrackPet es un proyecto de investigación para apoyar la localización de mascotas perdidas. Esta versión web permite registrar mascotas, abrir búsquedas, recibir avistamientos y consultar un mapa privado con su historial. La aplicación Android detecta por Bluetooth Low Energy (BLE) la baliza ESP32 del collar. Si hay una búsqueda abierta y el usuario habilitó la detección, el teléfono envía su ubicación al servidor.

## Funciones disponibles

- Registro, login por usuario o correo y logout con sesiones PHP.
- Perfil privado, gestión de mascotas, fotos y perfiles públicos con información limitada.
- Reportes de desaparición: apertura, edición, recuperación y cancelación.
- Un reporte activo por mascota, con sincronización transaccional de `Pets.isLost`.
- Avistamientos manuales o con ubicación obtenida del navegador, con fecha, precisión y notas.
- Mapa privado para el dueño autorizado, ubicación observada más reciente e historial paginado.
- Actualización cada 10 segundos mientras la búsqueda esté abierta y la página esté visible.
- Revisión y retiro de avistamientos propios; el dueño también puede retirar datos incorrectos de su mapa.
- Dashboard con búsquedas abiertas, búsqueda por zona/nombre, directorio de mascotas y personas, estadísticas e historial propio.
- Páginas informativas y enlaces para compartir el reporte público.
- Validación de entrada, protección de coordenadas, reintentos idempotentes y pruebas con conexiones concurrentes.

Consulta `FUNCIONES_NUEVAS.md` para el diseño, las decisiones de permisos y la validación de esta entrega. `CORRECCIONES.md` conserva el registro de la etapa anterior.

## Estructura

```text
android/                  Aplicación Kotlin, proyecto Gradle y APK de demostración
firmware/TrackPetBeacon/  Sketch Arduino para el collar ESP32
public/
  api/endpoint.php        Entrada de la API
  index.html              Dashboard público
  login.html              Inicio de sesión
  register.html           Registro
  profile.html            Cuenta y mascotas
  new-report.html         Apertura de búsqueda
  report.html             Reporte público y envío de avistamientos
  tracking.html           Mapa e historial privados del dueño
  sightings.html          Avistamientos del colaborador
  user.html               Perfil público
  about.html              Información del proyecto
  scripts/                JavaScript generado; no editar manualmente
  vendor/leaflet/          Recursos de Leaflet copiados durante el build
  styles/
  assets/img/uploads/     Fotos recibidas; no se versionan
src/
  config/Connection.php
  config/database.sql
  config/proceduresTrackPet.sql
  config/migrations/
  controller/
  model/entity/
  model/response/
  repository/
  service/implementation/
  ts/shared/              Modelos, interfaz común y mapa
  utils/
scripts/copy-assets.mjs

```

## Preparación

Necesitas PHP **8.1 o superior**, Composer, Node.js, npm y MySQL o MariaDB con tablas **InnoDB**. PHP debe tener `pdo_mysql`, `mbstring`, `ctype`, `json` y sesiones habilitados. Las pruebas HTTP además usan la extensión `curl`.

Desde la carpeta que contiene este README:

```bash
composer install
npm ci
npm run build
cp src/config/.env.example src/config/.env
```

Configura tus credenciales en `src/config/.env`. El formato usa **`=`**, no `:`:

```ini
SERVER=127.0.0.1
PORT=3306
DATABASE=TrackPet
DB_USER=trackpet
PASSWORD="tu-contraseña"
```

`PORT` se incorpora a la conexión. Opcionalmente, `DB_SOCKET=/ruta/mysql.sock` sustituye al host y puerto. Las variables del entorno tienen prioridad sobre el archivo. `.env` no se versiona ni debe estar dentro de la raíz pública del servidor.

### Base nueva o actualización

Con una cuenta administrativa de MySQL/MariaDB, aplica en este orden:

```bash
mysql -u root -p < src/config/database.sql
mysql -u root -p < src/config/migrations/20261015_pet_photo.sql
mysql -u root -p < src/config/migrations/20261016_tracking.sql
mysql -u root -p < src/config/migrations/20261105_anonymous_sightings.sql
mysql -u root -p < src/config/proceduresTrackPet.sql
```

Los scripts conservan los registros existentes. La primera migración agrega `Pets.petPhoto`. La segunda crea las tablas de reportes/avistamientos, agrega columnas que falten en instalaciones parciales y convierte las mascotas activas previamente marcadas como perdidas en búsquedas iniciales. Después sincroniza `isLost` con los reportes abiertos. Esas búsquedas iniciales utilizan la fecha disponible del registro anterior y la zona «Zona por especificar»; su dueño puede corregir la información desde el mapa.

Los procedimientos deben actualizarse junto con el código. La firma del procedimiento legado `toggleLostStatus` ahora recibe un tercer parámetro con el UUID del nuevo reporte; la ruta HTTP conserva su contrato anterior y proporciona ese UUID internamente. Al actualizar una instalación en uso, detén temporalmente sus escrituras mientras se aplican estos scripts.

La cuenta de la aplicación necesita `SELECT`, `INSERT`, `UPDATE` y `EXECUTE` sobre `TrackPet`, incluidas `LostReports` y `Sightings`. La cuenta que importa los scripts necesita permisos para crear tablas, índices y procedimientos. Para otro nombre de base, ajusta `TrackPet` en los scripts y `DATABASE` en la configuración.

No se importa SQL automáticamente al arrancar la web. No se necesita una clave de pago para el mapa base predeterminado.

### Desarrollo

```bash
php -d upload_max_filesize=6M -d post_max_size=7M -S 127.0.0.1:8000 -t public
```

Abre `http://127.0.0.1:8000/index.html`. En otra terminal:

```bash
npm run dev
```

`npm run build` compila TypeScript y copia Leaflet, sus imágenes y su licencia a `public/vendor/leaflet/`. `npm run dev` prepara esos recursos antes de observar cambios de TypeScript. `public/scripts/` y `public/vendor/` son archivos generados e ignorados por Git.

PHP debe poder escribir en sus directorios de sesiones, archivos temporales y `public/assets/img/uploads/`. Ajusta `session.save_path` si tu instalación apunta a una carpeta sin permisos. En producción sirve únicamente `public/`, utiliza HTTPS para sesiones y GPS, y configura los límites de PHP por encima de los 5 MiB admitidos por imagen.

## Recorrido de uso

1. Registra una cuenta y una mascota desde «Mi cuenta».
2. Selecciona «Reportar desaparición», indica la fecha, la zona y las señas particulares. El último punto conocido es opcional y privado.
3. Comparte el enlace del reporte público. Cualquier persona puede seleccionar el lugar de un avistamiento, indicar cuándo ocurrió y confirmar el envío.
4. Abre el mapa privado. Muestra las observaciones disponibles con su precisión, fecha y colaborador. El punto más reciente se elige por la hora de observación, aunque haya llegado un reporte más antiguo después.
5. Retira observaciones incorrectas si hace falta y cierra la búsqueda como «Recuperada» o «Cancelada». No se aceptan nuevos avistamientos una vez cerrada.

El GPS se solicita únicamente al pulsar su botón. Obtener una ubicación no la envía: el usuario debe confirmar y enviar el formulario. Si no está en el lugar observado, puede elegirlo manualmente. Las pistas son aproximadas; no representan una posición actual garantizada ni una ruta confirmada.

## Reglas de datos y permisos

- UUID públicos en minúsculas y con guiones; también se aceptan entradas hexadecimales compactas de 32 caracteres. MySQL almacena los IDs en `BINARY(16)`.
- Sesiones con cookie HttpOnly, SameSite Lax, modo estricto y 30 minutos de inactividad. Las operaciones privadas comprueban que la cuenta siga activa.
- La propiedad de mascotas se verifica en la base. Los reportes y los avistamientos se escriben en transacciones que bloquean la mascota para coordinar aperturas, cierres y envíos simultáneos.
- Solo existe un reporte abierto por mascota, también mediante una restricción única en MySQL. `isLost` se mantiene en la misma transacción.
- No se puede transferir una mascota con una búsqueda abierta. Dar de baja una mascota o su dueño cancela sus búsquedas abiertas y detiene nuevos avistamientos.
- Las coordenadas del reporte y el historial completo requieren que la cuenta sea el dueño que abrió el reporte **y** conserve la propiedad actual de la mascota. Una transferencia no entrega el historial privado anterior al nuevo dueño ni mantiene acceso al dueño anterior.
- Un colaborador puede leer y retirar sus propios avistamientos, incluso si la búsqueda ya cerró. El dueño autorizado puede retirarlos del mapa. El retiro es lógico y conserva el registro para auditoría.
- El perfil público no expone correo ni teléfono; ningún endpoint devuelve hashes de contraseña o huellas internas de los envíos.
- Las fechas de rastreo se reciben en ISO 8601/RFC 3339 con zona horaria, se almacenan en UTC y se devuelven con `Z` y precisión de milisegundos. La interfaz las muestra en la zona del navegador.
- Se admite hasta 5 minutos de diferencia de reloj al comparar observaciones con la desaparición o con la hora actual. No se permite cambiar la fecha de desaparición dejando observaciones activas anteriores a esa tolerancia.
- Se aceptan hasta **20 avistamientos nuevos por minuto por cuenta**, incluidos los retirados. Los reintentos reconocidos no consumen cupo adicional. El exceso devuelve `429` y `Retry-After: 60`.

## API

Base: `api/endpoint.php?resource=...`. Los cuerpos normales deben ser objetos JSON con `Content-Type: application/json` y tamaño máximo de **64 KiB**. Los uploads usan multipart y sus propios límites.

### Usuarios y mascotas

| Método | Recurso / parámetros | Cuerpo | Acceso |
| --- | --- | --- | --- |
| GET | `viewer` | — | Identidad mínima de la sesión, o `authenticated: false` |
| GET | `session` | — | Perfil privado y mascotas del usuario autenticado |
| GET | `user&userId={uuid}` | — | Perfil público |
| GET | `user&username={username}` | — | Perfil público |
| GET | `pet&petId={uuid}` | — | Mascota activa |
| GET | `pet&ownerId={uuid}` | — | Mascotas activas del dueño; incluye el reporte activo si existe |
| GET | `getLostStatus&petId={uuid}` | — | Estado perdido/recuperado |
| POST | `login` | `username`, `password` | Público |
| POST | `logout` | — | Sesión activa |
| POST | `user` | Datos de registro | Público |
| POST | `pet` | Datos de mascota | Sesión activa; dueño tomado de la sesión |
| POST | `upload&target=user` | Multipart, `fileToUpload` | Foto propia |
| POST | `upload&target=pet&petId={uuid}` | Multipart, `fileToUpload` | Dueño vigente |
| PATCH | `user` | Campos que se modifican | Cuenta propia |
| PATCH | `pet&petId={uuid}` | Campos que se modifican | Dueño vigente |
| PATCH | `toggleLostStatus&petId={uuid}` | — | Compatibilidad: abre un reporte básico o cierra el activo como recuperado |
| PATCH | `changeOwnership` | `petId`, `newOwnerId` | Dueño vigente; destinatario activo; búsqueda cerrada |
| DELETE | `user` | — | Cuenta propia |
| DELETE | `pet&petId={uuid}` | — | Dueño vigente |

Se mantienen los alias `GET pet&userId=...` e `isPetLost`. Los IDs de modificaciones pueden estar en el cuerpo; si también aparecen en la URL, deben coincidir. `oldOwnerId` en una transferencia es opcional y debe coincidir con la sesión si se envía. Los clientes nuevos deben utilizar aperturas/cierres explícitos de reportes para obtener reintentos idempotentes, en lugar del toggle legado.

Registro: `firstName`, `surname`, `username`, `email`, `password`; `phone` opcional. La contraseña requiere 8 caracteres, mayúscula, minúscula, número y un símbolo de `@$!%*?&_`. En edición solo se valida si cambia. `phone: ""` elimina el teléfono.

Mascota: `petName`, `petSpecies`, `petColour`; opcionales `petWeight`, `petHeight`, `petLenght`, `petDescription`. Se conserva la grafía histórica `petLenght`. Las medidas deben estar entre 0.01 y 99999999.99; la interfaz usa kg y cm. `petDescription: ""` vacía la descripción.

Fotos: rutas relativas `profilePhoto` y `petPhoto`, JPEG/PNG/GIF/WebP, hasta 5 MiB y 5000 píxeles por dimensión. El upload verifica permisos, valida la imagen y persiste la ruta; si falla, elimina el archivo nuevo. Al reemplazar una foto, elimina la anterior únicamente si ningún registro la referencia. Los campos de foto solo admiten rutas de uploads.

### Reportes, ubicaciones y comunidad

| Método | Recurso / parámetros | Descripción |
| --- | --- | --- |
| GET | `reports&q=&limit=12&offset=0` | Reportes abiertos públicos, sin coordenadas exactas |
| GET | `reports&mine=true&q=&limit=12&offset=0` | Reportes e historial del dueño autenticado |
| GET | `report&reportId={uuid}` | Metadatos públicos; información privada adicional si el dueño está autorizado |
| GET | `report&petId={uuid}` | Reporte activo de una mascota, útil para la aplicación Android |
| POST | `report` | Abrir búsqueda de una mascota propia |
| PATCH | `report&reportId={uuid}` | Editar metadatos abiertos o cerrar el reporte si se incluye `status` |
| POST | `sighting` | Enviar un avistamiento manual o GPS a una búsqueda abierta, con sesión opcional. La fuente BLE requiere sesión. |
| POST | `beaconSighting&reportId={uuid}&clientRequestId={uuid}` | Registrar coordenadas actuales del teléfono que detectó el collar, con sesión |
| GET | `tracking&reportId={uuid}&limit=50&offset=0` | Reporte, último avistamiento e historial privado del dueño |
| GET | `sightings&limit=20&offset=0` | Avistamientos propios, incluidos los retirados |
| DELETE | `sighting&sightingId={uuid}` | Retirar avistamiento propio o del reporte que administra el dueño |
| GET | `directory&kind=users&q=&limit=12&offset=0` | Directorio público de personas |
| GET | `directory&kind=pets&q=&lost=true&limit=12&offset=0` | Directorio de mascotas; `lost` es opcional y aplica a mascotas |
| GET | `stats` | Conteos reales de cuentas, mascotas, búsquedas abiertas y búsquedas recuperadas |

Las listas responden con `{items, total, limit, offset}`. `reports`, `directory` y `sightings` admiten límites entre 1 y 50; `tracking` entre 1 y 100. El offset se limita a 10000. Las búsquedas usan el texto como literal: los caracteres `%` y `_` no amplían la consulta.

Abrir reporte:

```json
{
  "petId": "UUID de la mascota",
  "clientRequestId": "UUID generado por el cliente para esta apertura",
  "lostAt": "2026-10-16T09:30:00-06:00",
  "lastSeenArea": "Colonia Centro, junto al parque",
  "details": "Collar rojo y mancha gris en la oreja",
  "lastSeenLatitude": 19.4326,
  "lastSeenLongitude": -99.1332
}
```

`petId` y `lastSeenArea` son obligatorios. `lostAt` omitido usa la hora del servidor; detalles y coordenadas son opcionales. Las coordenadas deben proporcionarse juntas. `clientRequestId` es opcional por compatibilidad, pero los clientes nuevos deben enviarlo y conservarlo al reintentar la misma apertura. Una repetición del mismo comando devuelve el reporte original con `replayed: true`, incluso si ya cerró; no abre otra búsqueda. Reutilizar la clave con otros datos devuelve `409`. Una apertura distinta cuando ya existe un reporte activo también devuelve `409` e identifica ese reporte.

Editar reporte: envía solo `lostAt`, `lastSeenArea`, `details` y/o ambas coordenadas. Enviar ambas coordenadas como `null` retira el punto inicial. No se puede editar un reporte cerrado. Para cerrar, envía `{"status":"recovered","closureReason":"nota privada opcional"}` o usa `cancelled`. Repetir el mismo estado de cierre es seguro; cambiar un estado terminal devuelve `409`.

Enviar avistamiento:

```json
{
  "reportId": "UUID del reporte",
  "clientRequestId": "UUID generado por el cliente para este envío",
  "latitude": 19.4332,
  "longitude": -99.1327,
  "accuracyMeters": 25,
  "observedAt": "2026-10-16T10:15:00-06:00",
  "source": "manual",
  "notes": "La vi cerca de la entrada del parque"
}
```

Obligatorios: `reportId`, `clientRequestId`, `latitude`, `longitude`, `observedAt`. Latitud: −90 a 90; longitud: −180 a 180; precisión opcional: 0 a 100000 metros; notas: hasta 1000 caracteres. `source` admite `manual`, `gps` o `ble`; es una fuente declarada por el cliente y no certifica una detección física. La web utiliza las dos primeras.

El servidor asigna al colaborador desde la sesión. Un envío sin sesión guarda `reporterId: null` y aparece como «Anónimo» en el mapa privado del dueño. Solo el dueño autorizado puede retirar ese punto. Los reintentos se identifican por cuenta y `clientRequestId` o, para envíos anónimos, por reporte y `clientRequestId`. Cada reporte admite hasta 20 envíos anónimos nuevos por minuto entre todos los visitantes. Reintentar el mismo cuerpo devuelve la observación original con `replayed: true`, incluso después del cierre. Reutilizar la clave con otros datos devuelve `409`.

`tracking` devuelve `{report, latestSighting, sightings, fetchedAt}`. `latestSighting` es `null` cuando no quedan observaciones activas y se calcula independientemente de la página solicitada. El orden usa hora observada, hora recibida e ID como desempate. El mapa muestra la página actual, el último avistamiento y el punto inicial si existe; navegar a páginas antiguas pausa la actualización automática.

### Respuestas y errores

Las respuestas tienen `status`, `message`, `data`; las de controladores incluyen `flag`. Códigos: `200` éxito, `400` validación, `401` sesión/credenciales, `403` permisos, `404` inexistente/no público, `405` método no permitido, `409` conflicto, `413` cuerpo demasiado grande, `415` formato no admitido, `429` exceso de avistamientos y `500` fallo inesperado. Los detalles inesperados se registran solo en el servidor.

## Mapa y dependencias

Se utiliza [Leaflet 1.9.4](https://leafletjs.com/reference.html), distribuido localmente y con su licencia. El mapa base carga teselas de [OpenStreetMap](https://operations.osmfoundation.org/policies/tiles/) mediante HTTPS, conserva la atribución y utiliza la caché normal del navegador. No hay descargas masivas ni precarga de regiones.

El mapa base requiere conexión y disponibilidad del proveedor. Si falla, las coordenadas, fechas y notas siguen disponibles, y los formularios permiten escribir el punto manualmente. Para despliegues de mayor demanda revisa la política del proveedor y configura un servicio adecuado para esa carga.

## Pruebas

```bash
composer test
npm run typecheck
npm run build
```

Integración, contra una instancia **dedicada a pruebas**, con una cuenta que pueda crear/eliminar esquemas y procedimientos:

```bash
TRACKPET_TEST_DSN='mysql:host=127.0.0.1;port=3306;charset=utf8mb4' \
TRACKPET_TEST_USER='usuario_de_pruebas' \
TRACKPET_TEST_PASSWORD='contraseña_de_pruebas' \
composer test:integration
```

También se admite `mysql:unix_socket=/ruta/mysql.sock;charset=utf8mb4`. El DSN no debe contener `dbname`.

La suite crea un esquema `trackpet_test_` con sufijo aleatorio, importa los SQL, inicia un servidor PHP local y prueba endpoints con varias sesiones. Las pruebas concurrentes usan procesos PHP con conexiones PDO independientes y una barrera de inicio; no dependen del servidor HTTP de un solo hilo para simular concurrencia. Al terminar se eliminan el esquema, las fotos y los archivos temporales. No se usan la base ni las credenciales de `.env`. Una interrupción abrupta podría dejar recursos temporales de prueba.

## Aplicación Android

### Compilar el APK de depuración

Instala el JDK 17 y, desde el SDK Manager de Android Studio, instala Android SDK Platform 35 y Android SDK Build-Tools 35.0.0. Configura `ANDROID_HOME` con la ruta del SDK. La primera compilación requiere conexión a Internet para descargar Gradle y las dependencias.

Desde la raíz del repositorio, ejecuta en PowerShell:

```powershell
cd android
.\gradlew.bat assembleDebug
```

En macOS o Linux, usa `./gradlew assembleDebug` desde la misma carpeta. El APK se genera en `android/app/build/outputs/apk/debug/app-debug.apk`, ruta relativa a la raíz del repositorio.

Instala el APK en un dispositivo con Android 12 o posterior y configura la URL del servidor PHP. Consulta la [guía de Android](android/README.md) para conectar el teléfono al servidor local.

## Baliza de collar NodeMCU-ESP32

1. Registra la mascota y copia su `petId` de la respuesta de la API. Abre `firmware/TrackPetBeacon/TrackPetBeacon.ino` y pega ese UUID con guiones en `PET_ID`. Cada collar lleva el UUID de su propia mascota.
2. En Arduino IDE instala **esp32 by Espressif Systems 3.2.1** con el gestor de placas. Si hace falta, añade `https://espressif.github.io/arduino-esp32/package_esp32_index.json` a las URLs adicionales de placas. Selecciona **ESP32 Dev Module** para un NodeMCU con ESP32 clásico, elige el puerto USB y carga el sketch.
3. Abre el monitor serie a 115200 baudios y comprueba el anuncio con un escáner BLE. Debe mostrar el nombre local completo `TrackPet` y un único UUID de servicio de 128 bits que coincida con el `petId`. El paquete primario contiene las banderas, el nombre y la lista de servicios (tipo `0x07`, UUID en orden little-endian en los bytes crudos). Un escáner que ya presenta el UUID como texto hace esa conversión. El collar emite continuamente sin conexión a internet.

### Contrato para la aplicación móvil

1. Al detectar el anuncio, consulta `GET /api/endpoint.php?resource=report&petId={petId}` sin enviar coordenadas. Continúa únicamente con HTTP 200 y `data.status` igual a `open`. Ante 404, estado cerrado o error de consulta, omite la ubicación.
2. Con una sesión iniciada y permiso de ubicación, obtiene la latitud y longitud actuales **del teléfono**. Genera un UUID de solicitud por observación. Envía de inmediato la cookie de sesión y `POST /api/endpoint.php?resource=beaconSighting&reportId={data.reportId}&clientRequestId={uuid}` con `Content-Type: application/json` y este cuerpo:

   ```json
   {"latitude":19.4326,"longitude":-99.1332}
   ```

3. El servidor comprueba el estado perdido bajo bloqueo y guarda el punto en el historial privado del reporte con fuente `ble` y hora de recepción UTC. HTTP 200 confirma el envío. Si se pierde la respuesta, repite los mismos identificadores y coordenadas. HTTP 409 indica reporte inactivo o reutilización del identificador con datos distintos. Descarta las detecciones antiguas al cerrar la búsqueda. HTTP 401 requiere iniciar sesión y HTTP 429 incluye `Retry-After`. Agrupa los anuncios repetidos de un mismo encuentro antes de pedir la ubicación.

## Ampliaciones futuras

- Validación de la detección BLE, el GPS y el consumo de batería en teléfonos físicos con el collar.
- Contactos y mensajería, si el equipo los incorpora al alcance.
- Recuperación de contraseña y configuración de un canal de correo.
- Notificaciones fuera de la página, si se definen sus canales y preferencias.


