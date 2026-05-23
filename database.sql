-- ============================================================
-- SERVI-JOB  —  Esquema de base de datos (con módulo de chat)
-- Base: service_libre  (ya definida en db.php)
-- Ejecutar en phpMyAdmin o consola MySQL
-- ============================================================

CREATE DATABASE IF NOT EXISTS `service_libre`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `service_libre`;

-- ── Tabla de usuarios ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `usuarios` (
    `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `nombre`     VARCHAR(80)     NOT NULL,
    `apellido`   VARCHAR(80)     NOT NULL DEFAULT '',
    `email`      VARCHAR(120)    NOT NULL UNIQUE,
    `telefono`   VARCHAR(30)     NOT NULL DEFAULT '',
    `password`   VARCHAR(255)    NOT NULL,
    `role`       ENUM('cliente','proveedor','admin') NOT NULL DEFAULT 'cliente',
    `last_login` DATETIME            NULL,
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME            NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME            NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_email` (`email`),
    KEY `idx_role`  (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Tabla de servicios ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `servicios` (
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `titulo`       VARCHAR(200)    NOT NULL,
    `descripcion`  TEXT                NULL,
    `imagen`       VARCHAR(255)        NULL,
    `categoria`    VARCHAR(80)     NOT NULL DEFAULT '',
    `municipio`    VARCHAR(60)     NOT NULL DEFAULT '',
    `precio`       DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    `usuario_id`   INT UNSIGNED        NULL,
    `es_destacado` TINYINT(1)      NOT NULL DEFAULT 0,
    `verificado`   TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME            NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`   DATETIME            NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_municipio`    (`municipio`),
    KEY `idx_es_destacado` (`es_destacado`),
    CONSTRAINT `fk_servicio_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Tabla de solicitudes de verificación ────────────────────
CREATE TABLE IF NOT EXISTS `verificaciones` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `nombre`      VARCHAR(120)  NOT NULL,
    `municipio`   VARCHAR(60)   NOT NULL DEFAULT '',
    `doc_path`    VARCHAR(255)  NOT NULL,
    `estado`      ENUM('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
    `usuario_id`  INT UNSIGNED      NULL,
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Tabla de municipios ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `municipios` (
    `id`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(60)  NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Tabla de categorías ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `categorias` (
    `id`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(80)  NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Tabla de mensajes de chat ────────────────────────────────
-- Un hilo se identifica por (servicio_id + cliente_id + proveedor_id).
-- emisor_id indica quién envió cada mensaje (puede ser cliente o proveedor).
CREATE TABLE IF NOT EXISTS `chat_mensajes` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `servicio_id`  INT UNSIGNED  NOT NULL,
    `cliente_id`   INT UNSIGNED  NOT NULL,
    `proveedor_id` INT UNSIGNED  NOT NULL,
    `emisor_id`    INT UNSIGNED  NOT NULL,
    `mensaje`      TEXT          NOT NULL,
    `leido`        TINYINT(1)    NOT NULL DEFAULT 0,
    `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_conv`   (`servicio_id`, `cliente_id`, `proveedor_id`),
    KEY `idx_emisor` (`emisor_id`),
    KEY `idx_leido`  (`leido`),
    CONSTRAINT `fk_chat_servicio`
        FOREIGN KEY (`servicio_id`)  REFERENCES `servicios`(`id`)  ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_chat_cliente`
        FOREIGN KEY (`cliente_id`)   REFERENCES `usuarios`(`id`)   ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_chat_proveedor`
        FOREIGN KEY (`proveedor_id`) REFERENCES `usuarios`(`id`)   ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_chat_emisor`
        FOREIGN KEY (`emisor_id`)    REFERENCES `usuarios`(`id`)   ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════
--  DATOS DE EJEMPLO
-- ════════════════════════════════════════════════════════════

-- ── Usuario admin por defecto ────────────────────────────────
-- Password: admin1234
-- Hash generado con password_hash('admin1234', PASSWORD_BCRYPT)
INSERT IGNORE INTO `usuarios` (nombre, apellido, email, password, role) VALUES
('Admin', 'ServiJob', 'admin@servijob.com',
 '$2y$10$TKh8H1.PfKAbnpvk9ne7d.MQPiDG7aFi1E8AHqDvXjMV2kSZ6V7.2',
 'admin');

-- ── Municipios ───────────────────────────────────────────────
INSERT IGNORE INTO `municipios` (`id`, `nombre`) VALUES
(1, 'Chacao'),
(2, 'Baruta'),
(3, 'Sucre'),
(4, 'Libertador');

-- ── Categorías ───────────────────────────────────────────────
INSERT IGNORE INTO `categorias` (`id`, `nombre`) VALUES
(1, 'Plomería'),
(2, 'Electricidad'),
(3, 'Comida'),
(4, 'Belleza'),
(5, 'Remodelación'),
(6, 'Tecnología'),
(7, 'Delivery'),
(8, 'Otro');

-- ── Servicios de ejemplo ─────────────────────────────────────
INSERT IGNORE INTO `servicios` (titulo, categoria, municipio, precio, es_destacado) VALUES
('Plomería Express — Reparaciones urgentes a domicilio',      'Plomería',    'Chacao',      45.00, 1),
('Electricista Certificado — Instalaciones y mantenimiento',  'Electricidad','Baruta',       80.00, 0),
('Cocina Doña Carmen — Almuerzos caseros con delivery',       'Comida',      'Sucre',        12.00, 1),
('Barbería Estilo Libre — Cortes modernos y afeitado',        'Belleza',     'Libertador',   15.00, 0),
('Soporte Técnico PC — Reparación y mantenimiento',           'Tecnología',  'Chacao',       30.00, 0),
('Remodelaciones LM — Pintura, cerámica y carpintería',       'Remodelación','Baruta',      200.00, 0);

-- ── Tabla de contrataciones ────────────────────────────────
CREATE TABLE IF NOT EXISTS `contrataciones` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `servicio_id` INT UNSIGNED NOT NULL,
    `cliente_id` INT UNSIGNED NOT NULL,
    `proveedor_id` INT UNSIGNED NOT NULL,
    `estado` ENUM('pendiente', 'aceptado', 'rechazado', 'cancelado', 'completado') NOT NULL DEFAULT 'pendiente',
    `motivo` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_contrato_servicio` FOREIGN KEY (`servicio_id`) REFERENCES `servicios`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_contrato_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_contrato_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Tabla de valoraciones ──────────────────────────────────
CREATE TABLE IF NOT EXISTS `valoraciones` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `contratacion_id` INT UNSIGNED NOT NULL,
    `cliente_id` INT UNSIGNED NOT NULL,
    `proveedor_id` INT UNSIGNED NOT NULL,
    `puntuacion` INT NOT NULL,
    `comentario` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_valoracion_contrato` FOREIGN KEY (`contratacion_id`) REFERENCES `contrataciones`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_valoracion_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_valoracion_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
