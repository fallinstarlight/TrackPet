# Funciones nuevas de TrackPet

Fecha: 16 de octubre de 2026.

Esta entrega implementa el flujo web de búsqueda de mascotas sobre las correcciones anteriores. El estado vigente y las instrucciones completas de instalación están en [README.md](README.md).

## Funciones entregadas

| Área | Comportamiento |
| --- | --- |
| Reportes | Abrir una búsqueda de una mascota propia, editar su información mientras esté abierta y cerrarla como recuperada o cancelada. |
| Información pública | Fecha de desaparición, zona, señas particulares, mascota, dueño y enlace para compartir. |
| Avistamientos | Personas autenticadas seleccionan un punto manualmente o solicitan el GPS, indican fecha, precisión y notas, y confirman el envío. |
| Mapa privado | El dueño autorizado ve el último avistamiento, el punto inicial y las observaciones de la página del historial, con círculos de precisión y ventanas informativas. |
| Historial | Páginas de 50 observaciones, ordenadas por la hora observada. El avistamiento más reciente se obtiene independientemente de la página. |
| Actualización | Consulta cada 10 segundos con la búsqueda abierta, en la primera página y con la pestaña visible. Pausa al consultar páginas antiguas y aumenta el intervalo ante errores. |
| Retiro | El colaborador consulta y retira sus propios avistamientos. El dueño puede retirar datos incorrectos de su búsqueda. Se conserva el registro para auditoría. |
| Comunidad | Dashboard con búsquedas abiertas, filtros, directorios de mascotas y personas, conteos reales y búsquedas propias abiertas o cerradas. |
| Navegación | Perfil público, página del proyecto, accesos desde «Mi cuenta» y regreso al destino solicitado después del login. |

Las coordenadas representan observaciones aproximadas; no constituyen una posición actual garantizada ni una ruta. El GPS solo se solicita mediante una acción explícita y obtenerlo no envía el formulario.

## Decisiones de datos y permisos

- `LostReports` y `Sightings` conservan el historial, con UUID binarios, fechas UTC y sus índices de consulta.
- Una restricción única y las transacciones impiden dos búsquedas abiertas de la misma mascota. `Pets.isLost` cambia en la misma operación.
- Aperturas y avistamientos aceptan claves de idempotencia para reconocer reintentos y evitar duplicados. Una clave reutilizada con otros datos produce un conflicto.
- Las conexiones concurrentes coordinan aperturas, cierres, avistamientos y bajas. Las transacciones reintentan interbloqueos de forma acotada.
- El historial privado requiere ser el dueño que abrió la búsqueda y conservar la propiedad actual. Una transferencia no entrega las coordenadas anteriores al nuevo dueño ni conserva acceso para el anterior.
- Los reportes públicos no incluyen coordenadas precisas ni notas privadas de cierre. Las respuestas tampoco incluyen hashes de contraseñas ni huellas internas de comandos.
- Las búsquedas cerradas no admiten nuevos avistamientos. Repetir un envío reconocido devuelve su registro original; no reabre búsquedas ni revive observaciones retiradas.
- No se transfiere una mascota con búsqueda abierta. Dar de baja la mascota o su dueño cancela sus búsquedas abiertas.
- Las ubicaciones, precisión, fechas y textos se validan antes de persistir. Hay un límite de 20 avistamientos nuevos por minuto por cuenta y de 64 KiB para cuerpos JSON.
- La API devuelve fechas ISO con zona UTC y milisegundos; la interfaz las presenta en la zona horaria del navegador.

## Archivos principales

- SQL: `src/config/migrations/20261016_tracking.sql`, `database.sql` y `proceduresTrackPet.sql`.
- Backend: entidades `LostReport` y `Sighting`; repositorios `ReportRepository` y `DiscoveryRepository`; sus servicios y controladores; validación `TrackingInput`, transacciones y rutas en `public/api/endpoint.php`.
- Interfaz: `new-report.html`, `report.html`, `tracking.html`, `sightings.html`, `user.html`, `about.html`, dashboard y sus módulos en `src/ts/`.
- Compartidos: modelos, mapa Leaflet e interfaz en `src/ts/shared/`. Las confirmaciones de cierre y retiro usan diálogos dentro de la página con opciones explícitas para volver o confirmar.
- Recursos: `scripts/copy-assets.mjs` distribuye Leaflet y su licencia con el build. El mapa base utiliza teselas HTTPS de OpenStreetMap con atribución.
- Pruebas: `tests/run.php`, `tests/integration.php`, `tests/tracking_scenarios.php` y `tests/tracking_concurrency.php`.

## Validación realizada

**323 comprobaciones automatizadas: 51 de regresión y 272 de integración HTTP/SQL.**

La integración cubre permisos de varios usuarios, validación de coordenadas y fechas, duplicados, reintentos después del cierre, orden del historial, precisión, retiro lógico, límites de envío, bajas, transferencias y revocación de sesiones. Incluye procesos PDO simultáneos para aperturas duplicadas, reintentos idénticos, avistamientos repetidos y cierre frente a envío.

Se comprobaron la migración repetida, la conversión de mascotas previamente marcadas como perdidas y la reparación de columnas/índices faltantes en una instalación parcial del módulo. Los scripts no borran los registros del equipo.

Las pruebas se ejecutaron con PHP 8.2.12 y MariaDB 10.4.32 en una instancia temporal sin acceso de red, con esquemas aleatorios y datos ficticios. No se modificaron la base de datos ni las credenciales del equipo. La compatibilidad con otra versión de MySQL/MariaDB debe verificarse al instalar allí.

TypeScript pasó la revisión de tipos y el build. Los archivos PHP propios pasaron la revisión de sintaxis.

En el navegador se verificaron apertura y edición de reportes, envío manual de un avistamiento desde otra cuenta, listado propio, denegación del mapa a un tercero y lectura del mapa por el dueño. Un historial ficticio de 62 observaciones permitió comprobar la paginación, su pausa automática y la llegada de una nueva pista sin recargar la página. También se inspeccionó visualmente el mapa con teselas reales, fecha y precisión.

**Límite de la revisión visual:** el navegador de automatización quedó bloqueado al probar la antigua confirmación nativa de cierre. Se sustituyeron las confirmaciones por diálogos de la página y se recompiló correctamente. La confirmación final de cierre/cancelación con esos diálogos queda pendiente de una comprobación manual en un navegador normal; ambos estados y sus reglas sí pasaron las pruebas HTTP/SQL.

## Instalación en la copia del equipo

1. Configurar `src/config/.env` con las credenciales reales, siguiendo el ejemplo del README.
2. Aplicar los SQL en el orden indicado en el README: esquema, migración de fotos, migración de rastreo y procedimientos actualizados. En una instalación en uso, detener las escrituras mientras se actualiza.
3. Instalar dependencias y ejecutar `npm run build`. Servir únicamente `public/` con PHP y habilitar permisos de escritura para sesiones, temporales y fotos.
4. Probar con dos cuentas: el dueño abre una búsqueda y el colaborador envía una observación. Verificar que solo el dueño ve el mapa; cerrar como recuperada y confirmar que no admite nuevas observaciones. Repetir con una búsqueda cancelada.

No se importan migraciones automáticamente al iniciar la aplicación.

## Ampliaciones que siguen pendientes

La aplicación móvil, el escaneo y la vinculación con collares BLE reales requieren trabajo adicional. La API admite `source: "ble"` como dato declarado por un cliente, sin certificar una detección física. También quedan pendientes contactos/mensajería, recuperación de contraseña con un canal de correo y notificaciones fuera de la página. Estas funciones no forman parte del flujo web implementado en esta entrega.
