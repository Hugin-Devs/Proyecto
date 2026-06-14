# 🚀 Manual de Despliegue — ServiJob

Este documento describe paso a paso cómo instalar, configurar y poner en marcha la plataforma ServiJob en un entorno de desarrollo local (como XAMPP/WAMP) o en un servidor de producción.

---

## 1. 🛠 Requisitos Previos del Sistema

Asegúrate de que tu entorno cumpla con las siguientes características:
*   **Servidor Web**: Apache (recomendado) o Nginx.
*   **PHP**: Versión 8.0 o superior.
*   **Base de Datos**: MySQL (versión 5.7+) o MariaDB (versión 10.3+).
*   **Extensiones PHP**: `mysqli`.
*   **Permisos de Sistema**: Capacidad para asignar permisos de escritura a carpetas de almacenamiento local.

---

## 2. 📥 Instalación de los Archivos

### En un entorno local (Ej. XAMPP)
1. Extrae o clona el repositorio del proyecto dentro de la carpeta pública de tu servidor (en el caso de XAMPP, en `C:\xampp\htdocs\` o `/opt/lampp/htdocs/`).
2. Nombra la carpeta con un nombre adecuado para tu entorno (por ejemplo, `servijob` o `Proyecto`).
   * *La URL local será algo como: `http://localhost/Proyecto`*

### En un servidor de producción (Linux/Debian/Ubuntu)
1. Sube los archivos a la carpeta pública, generalmente `/var/www/html/servijob`.
2. Asigna la propiedad de los archivos al usuario del servidor web (típicamente `www-data`):
   ```bash
   sudo chown -R www-data:www-data /var/www/html/servijob
   ```

---

## 3. 🗄️ Configuración de la Base de Datos

El sistema depende de una base de datos preestructurada para funcionar. Sigue estos pasos para importarla:

1. Abre tu gestor de base de datos (por ejemplo, phpMyAdmin o terminal MySQL).
2. Crea una base de datos vacía llamada **`service_libre`** con el cotejamiento (charset) `utf8mb4_general_ci` o `utf8mb4_unicode_ci`.
3. Importa el archivo SQL que contiene la estructura y los datos iniciales, el cual se encuentra en el proyecto bajo la siguiente ruta:
   * `database/service_libre.sql`

---

## 4. ⚙️ Conexión de la Base de Datos

Una vez importada la base de datos, debes decirle a la aplicación cómo conectarse a ella:

1. Abre el archivo `app/core/db.php`.
2. Verifica o ajusta las credenciales de conexión según tu entorno. Por defecto, en instalaciones locales estándar, el código lucirá así:

   ```php
   $host = 'localhost';
   $user = 'root';        // Tu usuario de BD
   $pass = '';            // Tu contraseña de BD
   $db   = 'service_libre';
   ```
3. Si estás desplegando en producción, reemplaza `$user` y `$pass` con las credenciales de tu usuario restringido de base de datos.

---

## 5. 🔒 Permisos de Directorios (Imágenes y Subidas)

Para que el sistema permita a los proveedores subir imágenes de sus servicios o documentos de verificación, el servidor necesita permisos de escritura en la carpeta respectiva.

1. Localiza el directorio `uploads/` en la raíz del proyecto.
2. Si estás en **Windows** (XAMPP), normalmente no requieres configuración extra.
3. Si estás en **Linux/Mac**, debes asegurar permisos de escritura:
   ```bash
   chmod 775 uploads/
   ```
   *(Si tienes problemas en Linux, puedes asignar `777` temporalmente para pruebas: `chmod 777 uploads/`)*

---

## 6. 🛡️ Credenciales de Acceso Administrador

El archivo SQL que importaste (`service_libre.sql`) incluye un usuario Administrador por defecto para que puedas iniciar sesión, verificar proveedores y realizar labores de mantenimiento.

*   **URL del Sistema**: Accede a `http://localhost/Proyecto` (o tu dominio).
*   Haz clic en el botón de **Iniciar Sesión**.
*   **Correo**: `admin@servijob.com`
*   **Contraseña**: `admin1234`

> ⚠️ **Nota de Seguridad**: Se recomienda encarecidamente cambiar la contraseña del administrador desde el panel de perfil en un entorno de producción, así como eliminar cualquier script de limpieza que se encuentre en `scripts/php/`.

---

## 7. 🚀 Validaciones Finales

Una vez desplegado:
1. Accede a la raíz del sitio (Home/Landing page). Verifica que las estadísticas dinámicas se visualicen correctamente (esto confirmará que la conexión a BD sirve).
2. Haz clic en **Ingresar** y utiliza las credenciales de administrador para validar el panel de administración.
3. Crea un usuario Cliente y un Proveedor de pruebas desde el botón **Regístrate** para validar que el enrutamiento base del sistema funciona sin fallas.
