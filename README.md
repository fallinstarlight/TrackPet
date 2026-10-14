# TrackPet proyecto
TrackPet es un proyecto centrado en localizar mascotas perdidas a través de un sistema similar a AirTag en el cual un dispositivo bluetooth BLE beacon emite una señal conteniendo una uuid única de la mascota que llevará dicho dipositivo en su collar o en otra parte. Dispositivos móviles cercanos leerán esta señal y revisarán en el servidor si la mascota está perdida, en el caso de que sí, enviará la ubicación aproximada del dispositivo al servidor y el dueño de la mascota podrá ver esta ubicación en tiempo semi real.

Lo presente en este repositorio es la implementación web de este proyecto, misma que no tiene la capacidad de interceptar al dispositivo bluetooth, por lo que no puede publicar ubicación de mascotas perdidas en tiempo real, pero sí tiene acceso a las demás funcionalidades que la aplicación móvil. La aplicación móvil en teoría debería de ser compatible con el backend actual.

Esta sección les ayudará a entender un poco hasta dónde va el proyecto, qué herramientas hemos de usar y qué hace falta construir.

# Lo que hay
## Estructura del proyecto
El proyecto parte del directorio raíz root/
De root/ se desprenden las carpetas src/ y public/
Src contiene el código de la API en PHP y los archivos TypeScript
La estructura general es
root/
_____public/
__________api/
__________scripts/
_______________api/
__________styles/
__________assets/
_______________img/
__________________uploads/
_____src/
________config/
________controller/
________model/
_____________entity/
_____________response/
________repository/
________service/
______________implementation/
________utils/
________ts/

## Frontend
public almacena la parte pública de la página, desde este folder es donde se debe de iniciar el servidor PHP. La raíz de la carpeta incluye todas las vistas de la página (login, profile, etc). La carpeta api/ contiene *endpoint.php* que es el punto de acceso principal de las solicitudes HTTP (la REST API del proyecto), assets/ incluye todas las imágenes, audios y otro tipo de archivos que se usarán en el proyecto, dentro de ella habrá uploads/ la cual almacenará imágenes y archivos subidos por los usuarios. scripts/ contiene los archivos javascript generados por el compilador de typescript, no creen archivos en esta carpeta de manera manual. styles/ contiene los archivos CSS que se vayan a usar en el proyecto.
		
## Backend

Actualmente tengo el backend del proyecto funcionando. Es un backend hecho con PHP nativo, es decir, sin frameworks ni librerías externas. 
- config/: contiene el archivo de conexión a la base de datos, Connection.php. También contiene los dos scripts SQL para generar el esquema local y usarlo.
- controller/: contiene los controladores, la capa de la API encargada de realizar las solicitudes al servicio, recibir las respuestas y devolverlas al endpoint en formato JSON. Actualmente contiene PetController y UserController.php
- Model/ contiene entity/ carpeta donde se colocan las clases asociadas a cada tabla de la base de datos y con las cuales se construyen objetos cuyos atributos representan cada columna de la base de datos, actualmente contiene User.php y Pet.php. Response/ contiene una clase para crear respuestas que se enviarán al usuario.
- Repository/ Es la capa encargada de hablar directamente con la base de datos, aquí se crean queries y se recibe información de la base.
- Service/ contiene la capa encargada de la lógica del sistema, se hacen validaciones, se da forma a los datos que se envían y reciben, se capturan errores, entre otras.
- Utils/ contiene clases con funciones usadas a lo del sistema, como creación de UUIDs, validación y limpieza de datos, manejo de errores, etc.
- ts/ contiene todos los archivos de typescript del proyecto. Typescript es como javascript, pero más seguro y con una estructura de código más limpia. La carpeta api/ dentro de ts/ contiene una clase para realizar peticiones al endpoint de la aplicación, solo es cosa de especificar el método y los parámetros de la URL, entonces no hace falta andar creando peticiones inidivduales todo el tiempo. Es tan fácil como coloca new ApiRequest<>(), y posteriormente ApiRequest.send()
### Endpoints que existen actualmente
Todos los endpoints empiezan por api/endpoint.php?resource=
#### GET
user&userId={uuid} Obtener usuario por su ID
user&username={username} Obtener usuario por su nombre de usuario
pet&petId={uuid} Obtener mascota por su id
pet&ownerId={uuid} Obtener todas las mascotas de un usuario en específico
isPetLost&petId={uuid{ Obtener el estado de una mascota (perdido o no)
#### POST
login: Iniciar sesión, requiere username: y password: en body
logout: Cerrar sesión
user: Crear usuario
pet: crear mascota
#### PATCH
user&userId= {uuid} Editar usuario
pet&petId= {uuid} Editar mascota
toggleLostStatus: {uuid} Cambiar el estado de una mascota de perdido an encontrado y viceversa
changeOwnership: cambiar la posesión de una mascota de un usuario a otro. Requiere la uuid de la mascota, la uuid del usuario original y la del usuario destino en el body
#### DELETE
user: Elimina el usuario actual (soft delete)
pet&petId= {uuid} elimina la mascota con su id (soft delete)
# Lo que falta
Actualmente el proyecto está poco funcional, falta muchísimo para terminarlo. En lugar de ir en orden, voy a enumerar todo lo que falta.
- Endpoints, con sus respectivos modelos, controladores, servicios e implementación dentro de la base de datos para creación de reportes de desaparición de mascotas, envío de ubicación en tiempo real por parte de usuarios que hayan avistado a la mascota y que el usuario que perdio la mascota pueda ver dicha ubicación en tiempo real.
- Vista pública para el perfil del usuario, misma vista a la que será redirigido el usuario tras iniciar sesión, esta vista tendrá información de su perfil así como de todas sus mascotas, podrá editar, agregar o eliminar mascotas, así como su propio perfil.
- Dashboard en el que podrá ver a otros usuarios, sus mascotas, su perfil, y si alguna de sus mascotas está perdida. Para esto había pensado en una función para agregar contactos. También había pensado en que se pudiera implementar un sistema de mensajes, ya sea con websockets o on promise, lo que sea más fácil y rápido de implementar. Por su puesto todo esto requiere sus propios endpoints y líogica dentro del backend.
- Otras vistas públicas como un "about us" con información del proyecto, otra vista de rastreo a la que se acceda cuando se pierde a una mascota y esta vista mostraría la ubicación en tiempo real publicada por otros usuarios.
- Actualmente ningún endpoint está en uso, obviamente, a excepción del endpoint login, el cual ya funciona, fuera de eso no hay nada. El siguiente endpoint a implementar debería de ser el de logout (ya hay un poco de eso en los archivos de typescript, pero aún no están en uso por ningún botón o función en las vistas públicas.
- La lógica para un endpoint de subida de archivos ya existe, pero no está implementado en ninguna parte, ni siquiera existe dentro de los métodos POST en endpoint.php. Implementen este endpoint y combínenlo con los endpoint de agregar/editar mascotas/usuarios, pues se usa para subir una foto de perfil.

En general existe una parte substancial del backend, pero no existe prácticamente ninguna implementación dentro del backend.
La aplicación móvil no es de prioridad aún, sólo la página web. Una vez hecha la página web, debería de ser relativamente fácil trasladar todo a una aplicación móvil la cual ya contaría con la funcionalidad bluetooth.

## Para empezar a trabajar con el proyecto

Clona el repositorio.
Necesitas PHP 8+, MySQL, Node.js y Composer instalados. La extensión PDO en PHP debe de estar habilitada.
En el directorio raíz del proyecto, ejecuta composer dump-autoload. 
Posteriormente ejecuta npm install --save-dev typescript, seguido de esto, ejecuta npx tsc --watch.
Crea un archivo llamado .env en la carpeta src/config/
El archivo debe de tener lo siguiente:
>SERVER: localhost
>PORT: 3306
>DATABASE: TrackPet
>USERNAME: tu_nombre_de_usuario_mysql
>PASSWORD: tu_contraseña_de_mysql

Con esto ya deberías de tener todo listo para continuar.

## Consideraciones para trabajar
Estamos usando PHP nativo
Estamos usando TypeScript
Los archivos de Typescript deben de existir en la carpeta src/ts, no creen javascript manual en public/scripts/
Cualquier framework frontend como React es bienvenido, pero si quieren hacerlo todo con HTML y CSS nativo, está bien
Cualquier modificación a archivos ya existentes se hará sólo si es necesario, la lógica de la API funciona bien así como está actualmente, por supuesto si se trata de añadir nuevas funcionalidades, corregir bugs o mejorar el aspecto del código sin alterar el funcionamiento principal, entonces no hay problema. El mismo caso aplica para eliminar archivos. 

