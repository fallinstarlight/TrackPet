# Correcciones aplicadas

Fecha: 15 de octubre de 2026.

Este documento conserva el registro histórico de las correcciones posteriores a `ANALISIS_PROYECTO.md`. El flujo de rastreo se implementó en una entrega posterior: consulta [FUNCIONES_NUEVAS.md](FUNCIONES_NUEVAS.md) y el [README actualizado](README.md) para el estado vigente y las instrucciones actuales de instalación.

## Backend

- Se renombró `src/config/connection.php` a `Connection.php` para que Composer pueda cargar la clase en Linux.
- La conexión ahora reutiliza el PDO, utiliza el puerto configurado, admite socket Unix y lee un `.env` INI válido o variables del entorno. Los errores inesperados se registran en el servidor y no se exponen al cliente.
- Se añadió el requisito `pdo_mysql` al manifiesto de Composer y se regeneró el lockfile/autoload.
- Se unificaron los UUID: entradas canónicas o hexadecimales compactas, salida canónica y conversiones a 16 bytes en las consultas SQL. Se corrigieron consulta de usuario por ID, filtro por dueño, alta de mascotas y actualización de `lastLogin`.
- Todas las respuestas convierten los identificadores binarios, incluidos los dueños de mascotas que aparecen en el login. Los hashes nunca se incluyen en las respuestas.
- Se corrigió el retorno de usuario recién creado: se consulta con `inUserId`, sin `LAST_INSERT_ID()`.
- Las ediciones de usuario ya no requieren contraseña cuando esta no cambia; una contraseña nueva sí debe pasar la validación. Se rechazan hashes enviados por el cliente y campos internos no admitidos.
- Las entidades solo asignan campos explícitamente admitidos. Los servicios validan tipos antes de construirlas, sanitizan altas y cambios, y rechazan identificadores inválidos. Las medidas se validan contra el rango y escala de almacenamiento.
- Se corrigió la edición de mascotas sin `ownerId` en el cuerpo. También puede actualizarse su especie.
- Las operaciones privadas comprueban que la cuenta continúe activa. Al darla de baja se destruye la sesión actual; sus otras sesiones dejan de tener acceso en la siguiente operación privada.
- Se eliminó la dependencia de la lista de mascotas guardada en la sesión. Las escrituras comprueban la propiedad vigente en SQL, y las condiciones del `UPDATE` incluyen al dueño para impedir acciones del propietario anterior tras una transferencia.
- Se exige que ambos usuarios de una transferencia estén activos. Se rechazan transferencias a uno mismo y operaciones ajenas.
- Los perfiles públicos excluyen correo y teléfono. `GET session` devuelve el perfil privado y las mascotas del usuario autenticado.
- Se uniformaron formatos de respuesta y códigos HTTP, validación de cuerpos JSON, multipart y errores de cliente. Se mantienen alias para rutas documentadas anteriormente.
- Las sesiones usan cookies HttpOnly, SameSite Lax y modo estricto; el timeout ahora coincide con los 30 minutos que describía el comentario original. Se corrigió la interpretación de `HTTPS=off`.

## Imágenes

- Se corrigió la lectura del MIME de `getimagesize()`.
- Se rechazan estructuras de archivo malformadas sin producir errores de tipos.
- Se añadieron endpoints multipart para fotos de usuario y mascota, con autenticación y comprobación de dueño.
- Se añadió `Pets.petPhoto`, incluida una migración repetible para instalaciones existentes.
- La ruta de upload guarda la foto y actualiza el registro. Si falla la persistencia, elimina el archivo nuevo. Al reemplazar una foto, elimina la anterior solo si ningún usuario o mascota la referencia.
- Se conservaron los límites de 5 MiB, 5000 píxeles por dimensión y formatos JPEG/PNG/GIF/WebP.
- Se ignoraron los archivos subidos en Git, conservando la carpeta mediante `.gitkeep`.

## Interfaz

- Se corrigieron la ruta del fondo y la inclusión de un módulo JavaScript como script clásico.
- Se conectó el botón de registro con una nueva pantalla y el endpoint existente.
- Se sustituyó el perfil provisional por una pantalla que consulta la sesión y permite editar la cuenta, agregar/editar/dar de baja mascotas, transferirlas, cambiar su estado y subir fotos.
- Se corrigió logout para usar POST, ejecutar la petición, cerrar la sesión y regresar al login.
- Se retiró el botón provisional de recuperación de contraseña hasta implementar ese flujo.
- Se añadieron estados de envío, mensajes de error y redirección cuando expira una sesión. Los datos recibidos se muestran como texto y las fotos solo admiten rutas del directorio de uploads.
- Se corrigió el desbordamiento de los campos de archivo en pantallas estrechas, observado durante la revisión visual.
- Se agregaron comandos `build`, `dev` y `typecheck`, y se verificó la instalación con el lockfile de npm.

## Base de datos y documentación

- `database.sql` ya no elimina el esquema: crea tablas que no existan.
- `src/config/migrations/20261015_pet_photo.sql` agrega la columna de foto sin borrar registros y admite ejecuciones repetidas.
- Se actualizaron las firmas y permisos de los procedimientos almacenados. Es necesario importar el archivo de procedimientos junto con la actualización de código.
- El README ahora incluye preparación reproducible, ejemplo correcto de entorno, comandos de importación y servidor, contrato de API, permisos, pruebas y pendientes actuales.
- El análisis inicial se marcó como histórico para evitar confundirlo con el estado vigente.

## Validación

- **36 comprobaciones de regresión** en `tests/run.php`: autoload, UUID, serialización, contraseñas, entradas inválidas, medidas, MIME y expiración de sesión.
- **85 comprobaciones HTTP/SQL** en `tests/integration.php`: registro y duplicados, login con/sin mascotas, privacidad, ediciones, códigos HTTP, propietarios distintos, transferencias, pérdida inmediata de permisos del dueño anterior, fotos multipart, limpieza de fotos reemplazadas, conservación de fotos referenciadas, bajas y revocación de otras sesiones.
- Los **22 archivos PHP propios**, incluidas las pruebas, pasaron la revisión de sintaxis sin errores ni advertencias.
- `npm ci --ignore-scripts` completó la instalación; `npm run typecheck` y `npm run build` pasaron con TypeScript **7.0.2**, la versión del lockfile.
- Composer regeneró el lockfile y el autoload, y validó el manifiesto. Persiste la advertencia informativa previa de que el proyecto no declara una licencia; no se asignó una licencia sin decisión del equipo.
- Se probó en el navegador con datos ficticios: registro, login, carga de perfil, edición de nombre sin contraseña, alta/edición de mascota, cambio de estado y logout, sin errores de consola en esos recorridos. La expiración también redirigió al login.
- La comprobación de subida desde el navegador quedó interrumpida por un timeout de aprobación automática. Las subidas, sus permisos y su persistencia sí pasaron las pruebas HTTP reales.

Las pruebas SQL se ejecutaron con PHP 8.2.12 y una instancia temporal de MariaDB 10.4.32, sin acceso de red y con datos propios en `/tmp`. La suite creó y eliminó únicamente esquemas aleatorios de prueba. No se aplicaron SQL ni credenciales a la base de datos del equipo. La compatibilidad con un servidor MySQL de otra versión deberá comprobarse en ese entorno.

## Para usar los cambios

1. Configurar `src/config/.env` a partir de `.env.example` con las credenciales del equipo.
2. Aplicar la migración de `petPhoto` e importar `proceduresTrackPet.sql`, siguiendo el README. Para una instalación nueva, importar primero `database.sql`.
3. Ejecutar `composer install`, `npm ci` y `npm run build` si se usa otra copia del proyecto.
4. Servir `public/` con PHP y comprobar permisos de sesiones y uploads.

Siguen pendientes reportes de desaparición, avistamientos con coordenadas, mapa/rastreo, dashboard, recuperación de contraseña y las ampliaciones de contactos, mensajería y aplicación móvil/BLE.
