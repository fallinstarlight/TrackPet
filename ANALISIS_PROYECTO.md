# Análisis de TrackPet

Fecha: 15 de octubre de 2026.

> Diagnóstico inicial, anterior a las correcciones solicitadas. Los errores y el estado descritos aquí son históricos. Consulta `CORRECCIONES.md`, [FUNCIONES_NUEVAS.md](FUNCIONES_NUEVAS.md) y el README actualizado para el estado vigente.

## Diagnóstico

TrackPet contiene una base de backend para administrar usuarios y mascotas, junto con una interfaz inicial de login. Todavía no implementa el flujo principal del producto: reportar una mascota perdida, recibir avistamientos con ubicación y mostrar su ubicación al dueño.

La separación entre controladores, servicios, repositorios, entidades y utilidades permite continuar sobre la estructura existente. Sin embargo, la afirmación del README de que el backend ya funciona requiere matices: hay errores reproducibles en componentes existentes y diferencias entre la documentación y los endpoints. Construir todas las pantallas antes de corregirlos obligaría a integrar contra una API inconsistente.

Se revisó primero el código y después el README. No se modificó la implementación ni se ejecutaron los scripts SQL.

## Qué existe

| Área | Implementación observada | Estado |
| --- | --- | --- |
| Backend | PHP nativo, autoload PSR-4 de Composer, PDO, controladores, servicios y repositorios | Base construida; requiere correcciones |
| Base de datos | Tablas `Users` y `Pets`, procedimientos almacenados, UUID binarios y bajas lógicas | Solo cubre usuarios y mascotas |
| Autenticación | Inicio y cierre de sesión, hash y verificación de contraseña, regeneración del ID de sesión y expiración | Lógica existente; faltan validaciones e integración completa |
| Usuarios | Alta, consulta, modificación y baja mediante API | Existen rutas; hay errores en consulta por ID y edición |
| Mascotas | Alta, consulta, modificación, baja, transferencia y cambio de estado de pérdida | Existen rutas; varias operaciones tienen bloqueos |
| Frontend | HTML/CSS del login y TypeScript para consumir la API | Solo login tiene un flujo conectado |
| Perfil | `public/profile.html` | Contenido provisional con arte ASCII |
| Archivos | Clase `Upload` con controles de tamaño, extensión y dimensiones | Sin endpoint ni integración; validación MIME defectuosa |
| Localización | Campo `isLost` y operaciones para consultarlo/cambiarlo | No hay reportes, coordenadas, historial ni mapa |

La presencia de una ruta o un método no acredita que el flujo completo funcione.

## Qué falta según el README, contrastado con el código

### 1. Reportes de desaparición

No existen tablas, entidades, repositorios, servicios, controladores ni endpoints para reportes. El booleano `Pets.isLost` solo indica un estado: no permite registrar cuándo ocurrió la desaparición, dónde se vio por última vez a la mascota, información del reporte o cuándo se cerró.

Pendiente: definir el modelo del reporte, abrirlo, consultarlo y cerrarlo al recuperar a la mascota; vincularlo con la mascota y su dueño; impedir reportes abiertos duplicados si esa es la regla elegida; mantener consistente el estado `isLost`.

### 2. Avistamientos y ubicaciones

No hay campos ni tablas para latitud, longitud, fecha de observación, precisión o usuario que reporta. Tampoco existe una API que reciba ubicaciones o consulte la última ubicación/historial de una mascota perdida.

Pendiente: modelo de avistamientos, publicación de coordenadas válidas, consulta autorizada de ubicación reciente e historial y reglas para observaciones antiguas o reportes ya cerrados. Conviene distinguir la hora del avistamiento de la hora de recepción en el servidor.

### 3. Vista de rastreo y actualización

No hay mapa, página de rastreo ni mecanismo que actualice los datos periódicamente o los envíe al navegador.

Pendiente: mapa con la ubicación reportada, fecha y precisión; estados sin avistamientos y sin reporte activo; actualización periódica o mecanismo de eventos. Para un primer prototipo puede evaluarse una consulta periódica al servidor; WebSockets no son una obligación del README.

### 4. Perfil y gestión de mascotas

La página de perfil no muestra datos ni ejecuta operaciones.

Pendiente: consultar la sesión actual; cargar el perfil y las mascotas del dueño; agregar, editar y dar de baja mascotas; editar la cuenta; marcar una mascota como perdida/recuperada; cerrar sesión y redirigir al login cuando corresponda. Es conveniente un endpoint de sesión/perfil actual para que el navegador pueda obtener su identidad sin depender de información retenida en memoria tras el login.

### 5. Registro, recuperación de acceso y logout

El formulario muestra botones `Sign up` y `Forgot password?`, pero el TypeScript solo obtiene sus elementos, sin asociarles acciones. Existe alta de usuario en la API, pero no una pantalla de registro. No existe flujo de recuperación de contraseña.

`src/ts/logout.ts` únicamente crea una petición con método `GET`; el backend espera `POST`. Además, no llama a `send()` ni está conectado a un botón.

Pendiente: pantalla y envío del registro, logout completo y una decisión explícita sobre recuperación de contraseña. Si se mantiene ese botón, debe tener un flujo funcional.

### 6. Fotos y subida de archivos

No existe una ruta de upload. El endpoint actual intenta interpretar el cuerpo como JSON, por lo que necesita adaptar el tratamiento del cuerpo para recibir archivos mediante `multipart/form-data`.

Usuarios tienen `profilePhoto`, pero mascotas no tienen un campo equivalente. Falta definir dónde se guarda la foto de una mascota y conectar la subida con el alta/edición. También deben verificarse los permisos para asociar o sustituir fotos y la limpieza de archivos que dejen de utilizarse.

### 7. Dashboard y páginas informativas

No hay dashboard para consultar usuarios/mascotas y su estado de pérdida, ni página informativa del proyecto. Faltan las vistas y las consultas necesarias para explorar datos, con límites y reglas de visibilidad.

Contactos y mensajería aparecen como ideas en el README. No tienen implementación; conviene tratarlos como una ampliación después del flujo de localización, salvo que el equipo los adopte como requisito de la entrega.

### 8. Aplicación móvil y BLE

El README deja explícitamente fuera de la prioridad actual la aplicación móvil y la recepción de señales BLE desde la web. Por ello, no corresponde contar un escáner Bluetooth web como pendiente de esta entrega.

Sí falta un contrato de API de ubicaciones que pueda reutilizar después la aplicación móvil. La compatibilidad con el backend aún necesita validarse, especialmente por las inconsistencias de UUID y el uso actual de sesiones con cookies.

## Errores en lo que ya existe

Las referencias siguientes indican archivos y líneas de la copia analizada.

| Prioridad | Hallazgo y evidencia | Consecuencia y corrección necesaria |
| --- | --- | --- |
| Alta | `src/config/connection.php:8` declara `Connection`, pero PSR-4 buscará `src/config/Connection.php` | En un sistema sensible a mayúsculas como Linux falla la carga normal de la clase. Alinear nombre del archivo y clase. |
| Alta | `public/api/endpoint.php:307` solo acepta 32 caracteres hexadecimales; `src/utils/Uuid.php:65` devuelve UUID con guiones | La API rechaza los identificadores que ella misma devuelve. Unificar el formato público y convertir a 16 bytes al acceder a la base. |
| Alta | `public/api/endpoint.php:51` exige un entero para consultar usuario por ID | Los usuarios tienen UUID, no IDs enteros. Validar y pasar un UUID. |
| Alta | `src/service/implementation/PetServiceImp.php:36` exige `ownerId` entero; `src/repository/PetRepository.php:47` usa `PDO::PARAM_INT` | El alta de mascota rechaza el UUID del dueño autenticado. Obtener el dueño de la sesión, convertir el UUID a bytes y usar el tipo adecuado en PDO. |
| Alta | `src/service/implementation/PetServiceImp.php:105` convierte `ownerId` aunque no se envíe en una edición | Editar solamente el nombre provoca `TypeError` antes de llegar al repositorio. Eliminar esa conversión innecesaria o resolver el dueño desde una fuente autorizada. |
| Alta | `src/service/implementation/UserServiceImp.php:43` convierte `petId`, pero deja `ownerId` binario en las mascotas incluidas en el login | Los bytes de UUID pueden producir JSON inválido por UTF-8 y romper la respuesta de login cuando hay mascotas. Convertir todos los identificadores expuestos. Lo mismo debe revisarse en respuestas de alta/edición y consulta de usuario por ID. |
| Alta | `src/model/entity/User.php:31` usa `OR` al intentar excluir `password` y `passwordHash` | La condición siempre resulta verdadera para esas claves. El cliente puede asignar `passwordHash` y saltarse la exigencia de una contraseña validada. Usar una lista explícita de campos editables y generar el hash únicamente en el servidor. |
| Alta | `public/api/endpoint.php:165` y otras rutas autorizan con la lista de mascotas guardada al iniciar sesión; `src/config/proceduresTrackPet.sql:223` transfiere sin comprobar que la mascota siga perteneciendo al dueño anterior | La lista queda desactualizada después de altas o transferencias. Una vez resueltos los bloqueos de UUID, persiste el riesgo de autorizar acciones del dueño anterior. Comprobar la propiedad vigente en la base y condicionar las escrituras al dueño actual. |
| Media | `src/model/entity/User.php:60` exige hash también durante edición | Cambiar solo nombre, correo o teléfono se rechaza por falta de contraseña. Validarla durante edición únicamente si se solicita cambiarla. |
| Media | `src/config/proceduresTrackPet.sql:60` busca el usuario recién creado con `LAST_INSERT_ID()` | La clave es UUID y no autoincremental. La respuesta de alta puede quedar sin datos del usuario. Consultar con `inUserId`. |
| Media | `src/service/implementation/UserServiceImp.php:230` envía el dueño textual al procedimiento que espera `BINARY(16)` | La consulta de mascotas por dueño no convierte el UUID de entrada a bytes. |
| Media | `src/service/implementation/UserServiceImp.php:59` actualiza `lastLogin` con el UUID ya convertido a texto | La actualización compara texto con una columna binaria. Mantener/construir el ID binario para la consulta. |
| Media | `src/utils/Upload.php:121` toma el índice 2 de `getimagesize()` como MIME | Ese índice es el código numérico del tipo de imagen. La comparación estricta contra cadenas MIME rechaza imágenes válidas. Leer la clave `mime`. |
| Media | `src/service/implementation/UserServiceImp.php:179` da de baja la cuenta sin destruir su sesión; `src/utils/Auth.php:79` solo comprueba si existe `userId` | La sesión puede continuar autenticada tras la baja. Invalidar sesiones y verificar el estado activo donde corresponda. |
| Media | Las consultas públicas de usuario no exigen login y sus procedimientos devuelven correo y teléfono (`src/config/proceduresTrackPet.sql:121`) | Falta separar la información del perfil público de los datos privados y definir quién puede consultarlos. |
| Baja | `public/styles/style.css:22` referencia `../Assets/`, pero la carpeta real se llama `assets` | El fondo falla en sistemas sensibles a mayúsculas. |
| Baja | `public/login.html:59` incluye `ApiRequest.js` como script clásico, aunque contiene `export` | Una vez compilado, esa inclusión provoca error de sintaxis en el navegador. `login.js` ya importa el módulo. |

Otros puntos que requieren ajuste: `$_BODY` en lugar de `$body` al validar el dueño del alta; validación uniforme de identificadores y tipos antes de construir entidades; manejo de excepciones de validación; medidas físicas positivas; coherencia de sanitización entre altas y cambios; y códigos HTTP consistentes para recursos inexistentes y errores del cliente.

La conexión no asigna el PDO creado a la propiedad estática que pretende reutilizar, por lo que ambos controladores pueden abrir conexiones separadas. También expone el mensaje de la excepción de conexión al cliente. Conviene resolver ambos puntos al corregir la configuración.

## Diferencias entre README y código

| Documentación | Código observado | Ajuste |
| --- | --- | --- |
| `GET pet&ownerId=...` | Se busca `userId` en el endpoint | Elegir un nombre y documentarlo de forma uniforme. |
| `GET isPetLost&petId=...` | La ruta se llama `getLostStatus` | Alinear contrato y ejemplos. |
| ID de usuario como UUID | La ruta de consulta lo valida/castea como entero | Corregir el endpoint. |
| Cambio de dueño con usuario original en el body | El endpoint toma el dueño original de la sesión | Documentar el comportamiento real y mantener la comprobación de propiedad en la base. |
| PHP 8+ | Composer exige PHP >= 8.1 y `mbstring` | Documentar requisitos exactos, incluido `pdo_mysql`. |
| `.env` con `SERVER: localhost` | Se lee mediante `parse_ini_file()` | Usar formato `SERVER=localhost`; el ejemplo documentado no carga esas variables. |
| Variable `PORT` | No se incorpora al DSN de conexión | Utilizarla o eliminarla de la configuración documentada. |
| Instrucciones de inicio | No describen importación ordenada de los SQL ni comando concreto para servir `public/` | Completar el procedimiento reproducible de instalación. |

`database.sql` comienza con `DROP SCHEMA IF EXISTS TrackPet`. Debe indicarse que reinicializa y elimina los datos de ese esquema; para evolución del proyecto hacen falta cambios de esquema que preserven datos.

La copia recibida no contiene `.env`, `vendor/`, `node_modules/` ni JavaScript compilado. Son elementos ignorados por Git, así que su ausencia es esperable en una descarga, pero requieren un proceso de preparación correcto. `package.json` tampoco define comandos de build, desarrollo o comprobación.

## Verificación realizada y límites

Se usó PHP 8.2.12 disponible en `/opt/lampp/bin/php`.

- Los 20 archivos PHP pasaron la comprobación de sintaxis. `public/api/endpoint.php` emitió una advertencia por `use Exception` sin efecto.
- Un cargador mínimo que reproduce la resolución PSR-4 no encontró `TrackPet\config\Connection` por la diferencia de mayúsculas.
- Editar una entidad usuario solo con `firstName` devolvió el error de contraseña.
- Crear una entidad usuario con los campos obligatorios y un `passwordHash` arbitrario, sin contraseña, devolvió cero errores de validación.
- Un UUID generado por la utilidad del proyecto fue rechazado por la condición del validador del endpoint.
- Serializar una mascota con el UUID binario del dueño produjo `Malformed UTF-8 characters` en el caso probado.
- El servicio de alta de mascota rechazó `ownerId` UUID por exigir entero.
- Editar una mascota sin `ownerId` produjo el `TypeError` previsto.
- La validación interna de imagen rechazó el JPEG existente del proyecto con `Image MIME type '2' is not allowed`.
- Interpretar el ejemplo de `.env` del README mediante el lector INI devolvió un arreglo vacío.

Las pruebas de servicios usaron un repositorio de sustitución sin conexión a base de datos. La validación de imagen se invocó aisladamente sobre un archivo existente; no se probó una subida HTTP completa. Las otras observaciones de SQL, sesiones y frontend proceden de revisión estática.

No se arrancó la aplicación, no se instaló software, no se importó SQL ni se ejecutaron pruebas HTTP contra una base de datos. Node/npm no estaban disponibles en el PATH consultado y no se compiló TypeScript. El proyecto no contiene una suite de pruebas automatizadas. Estos resultados verifican defectos concretos, no el funcionamiento integral del sistema.

## Orden propuesto de trabajo

1. **Estabilizar configuración y contrato de API.** Corregir PSR-4, ejemplo de entorno, tipos UUID, serialización y discrepancias de rutas. Preparar instrucciones de instalación verificables.
2. **Corregir usuarios, mascotas y permisos.** Resolver altas/ediciones, asignación de hashes, propiedad vigente en la base, transferencias y cierre de sesiones al dar de baja cuentas. Verificar con dos usuarios y mascotas de ambos.
3. **Completar la aplicación básica.** Registro, perfil, logout y gestión de mascotas con estados de carga y errores. Agregar el endpoint de sesión actual que necesita ese flujo.
4. **Integrar fotos.** Corregir `Upload`, implementar multipart, persistencia y visualización de fotos de usuario/mascota.
5. **Implementar el núcleo de localización.** Reportes, avistamientos, API de ubicaciones, mapa y actualización. Probar el recorrido: dueño reporta pérdida → otro usuario publica ubicación → dueño la ve → dueño cierra el reporte.
6. **Completar navegación y contenido.** Dashboard, perfiles públicos con datos limitados y página del proyecto.
7. **Ampliaciones.** Contactos, mensajes y posteriormente cliente móvil/BLE, según alcance acordado.

Para organizar el equipo conviene dividir responsabilidades por flujos completos y acordar primero nombres de rutas, cuerpos JSON y respuestas. El criterio mínimo de entrega debe ser poder completar los recorridos de registro, login/logout, gestión de mascotas y recuperación mediante avistamientos; el número de endpoints por sí solo no mide ese avance.
