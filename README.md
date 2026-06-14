# ServiJob

**ServiJob** es una plataforma web de **marketplace de servicios locales**, enfocada en el contexto venezolano. Permite a los clientes explorar y contactar proveedores de servicios, y a los proveedores gestionar sus ofertas de manera sencilla.

## 🚀 Requisitos del Sistema
- PHP 8+
- MySQL o MariaDB
- Servidor web (Apache / Nginx)

## 🛠️ Instalación y Arranque
1. Clona este repositorio en tu servidor web (por ejemplo, en `htdocs` si usas XAMPP).
2. Crea una base de datos llamada `service_libre`.
3. Importa el archivo `database/service_libre.sql` en tu base de datos para cargar la estructura y datos iniciales.
4. Asegúrate de configurar correctamente los parámetros de conexión en `app/core/db.php` si tus credenciales de base de datos son diferentes a las por defecto (`root` sin contraseña).
5. Accede a la plataforma desde tu navegador: `http://localhost/servijob/` (o la ruta correspondiente).

## 🗂️ Estructura del Proyecto
- `app/`: Contiene el núcleo de la aplicación (backend PHP, controladores, middleware, y API).
- `public/`: Contiene todos los archivos públicos (CSS, fuentes, librerías).
- `database/`: Scripts SQL de estructura y migraciones.
- `docs/`: Documentación técnica del proyecto.
- `scripts/`: Herramientas de desarrollo y mantenimiento (no usar en producción).

Para más detalles, revisa el archivo de documentación en `docs/analisis_sistema_servijob.md`.
