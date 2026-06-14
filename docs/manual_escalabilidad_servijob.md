# 📈 Manual de Requisitos y Escalabilidad — ServiJob

Este documento establece los requisitos mínimos y recomendados del sistema, así como las estrategias y consideraciones arquitectónicas para escalar la plataforma ServiJob a medida que crezca el número de usuarios (proveedores y clientes) y el volumen de datos.

---

## 1. 🖥️ Requisitos del Sistema

### Entorno de Desarrollo Local
*   **SO**: Windows 10+, macOS 11+, o Linux (Ubuntu 20.04+).
*   **Servidor Web**: Apache 2.4 o Nginx.
*   **Lenguaje**: PHP 8.0 o superior (Extensiones requeridas: `mysqli`, `json`, `mbstring`).
*   **Base de Datos**: MySQL 5.7+ o MariaDB 10.3+.
*   **Hardware Mínimo**: 2 GB de RAM, procesador Dual-Core, 1 GB de espacio libre en disco.

### Entorno de Producción (Mínimo - MVP)
Adecuado para los primeros ~1,000 usuarios activos.
*   **Servidor**: VPS básico (ej. DigitalOcean, Linode, AWS EC2).
*   **CPU**: 1 vCore.
*   **RAM**: 1 GB - 2 GB.
*   **Almacenamiento**: 25 GB SSD (atención al tamaño de la carpeta `uploads/`).
*   **Sistema Operativo**: Ubuntu 22.04 LTS o Debian 11.
*   **Base de Datos**: MySQL/MariaDB corriendo en el mismo servidor (localhost).

---

## 2. 🚀 Estrategias de Escalabilidad

Cuando ServiJob alcance hitos significativos de tráfico (ej. 10,000+ usuarios, o miles de peticiones simultáneas), la arquitectura actual (monolítica sin framework) debe evolucionar. A continuación se presentan las estrategias por capa.

### 2.1. Capa de Base de Datos (Escalabilidad Vertical y Horizontal)
El cuello de botella inicial suele ser la base de datos debido a las consultas complejas de servicios y métricas, además del polling del chat.

*   **Índices**: Asegurar que campos críticos como `usuario_id`, `servicio_id`, `estado` en contrataciones, y los FK de chats tengan índices (`INDEX`).
*   **Separación de Lectura/Escritura (Read Replicas)**: Implementar una base de datos maestra para escrituras (`INSERT`, `UPDATE`) y una o más réplicas de lectura para las consultas (`SELECT` en el Home y Explorador).
*   **Caché en Memoria**: Integrar **Redis** o **Memcached** para almacenar en caché:
    *   Listas dinámicas (categorías, municipios).
    *   Servicios destacados del Home.
    *   Métricas agregadas (promedio de valoraciones, total de pendientes), que actualmente se calculan al vuelo en `proveedor_panel.php`.

### 2.2. Capa de Almacenamiento (Archivos y Multimedia)
Los usuarios (proveedores) suben imágenes de servicios y documentos de identidad.

*   **Almacenamiento de Bloques (Object Storage)**: Migrar el directorio `uploads/` a un servicio en la nube como **AWS S3**, **DigitalOcean Spaces** o **Google Cloud Storage**.
    *   *Beneficio*: Evita que el disco del servidor web se llene y permite que múltiples servidores web sirvan las mismas imágenes.
*   **CDN (Content Delivery Network)**: Utilizar un CDN como **Cloudflare** para cachear y distribuir las imágenes globalmente, reduciendo el ancho de banda del servidor.

### 2.3. Capa de Aplicación (Servidores Web)
Para soportar alto tráfico, la aplicación debe poder ejecutarse en múltiples servidores.

*   **Balanceador de Carga (Load Balancer)**: Colocar un balanceador (ej. AWS ALB, Nginx Load Balancer) frente a la aplicación para distribuir el tráfico HTTP entre múltiples nodos de Apache/Nginx.
*   **Manejo de Sesiones**: Actualmente las sesiones de PHP (`$_SESSION`) se guardan en el disco local (`/tmp`). En un entorno multi-servidor, esto causará deslogueos aleatorios.
    *   *Solución*: Configurar PHP para guardar las sesiones en una base de datos centralizada o preferiblemente en **Redis** (`session.save_handler = redis`).

### 2.4. Capa de Tiempo Real (Mensajería y Notificaciones)
Actualmente, el chat de ServiJob utiliza **Long Polling** (peticiones AJAX cada 3 segundos en `chat_get.php`). Esto no escala bien.

*   **Evolución a WebSockets**: Reemplazar el polling por una solución de WebSockets.
    *   *Opciones*: Implementar un servidor **Node.js (Socket.io)** externo, usar **Swoole** (PHP), o servicios gestionados como **Pusher** o **Firebase Cloud Messaging**.
    *   *Beneficio*: Reduce dramáticamente las consultas a la base de datos y la sobrecarga de conexiones HTTP, ofreciendo una experiencia en tiempo real real.

---

## 3. 🛡️ Consideraciones de Seguridad en el Escalado

*   **Protección Anti-DDoS y WAF**: Utilizar Cloudflare para mitigar ataques de denegación de servicio, especialmente contra el endpoint de inicio de sesión (`auth_login.php`) y la búsqueda de servicios.
*   **Limitar Peticiones (Rate Limiting)**: Implementar reglas en Nginx/Apache para limitar el número de peticiones por segundo por IP, previniendo abusos en formularios y APIs (`chat_send.php`).
*   **Monitoreo Constante**: Instalar agentes como New Relic, Datadog o la pila Prometheus/Grafana para vigilar el consumo de CPU, memoria y tiempos de respuesta de MySQL.

---

## 4. 📝 Resumen del Roadmap de Crecimiento

1.  **Fase 1 (Actual - MVP)**: Monolito, base de datos y servidor web en la misma máquina. Archivos locales. Polling de chat de 3s.
2.  **Fase 2 (1k-5k usuarios)**: Base de datos en un servidor administrado independiente (ej. AWS RDS). Archivos estáticos en S3 + Cloudflare.
3.  **Fase 3 (10k+ usuarios)**: Balanceador de carga con al menos 2 servidores web. Sesiones en Redis. WebSockets para el chat en lugar de polling.
4.  **Fase 4 (Escala Regional)**: Réplicas de lectura de base de datos, optimización agresiva de queries, microservicios (separar chat y notificaciones del core de PHP).
