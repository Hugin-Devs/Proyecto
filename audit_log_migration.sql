-- ============================================================
--  SERVI-JOB — Migración v2.5: Tabla de Auditoría + Suspensión
--  Ejecutar UNA SOLA VEZ sobre la BD service_libre
-- ============================================================

-- Tabla de log de auditoría
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `usuario_id`  INT UNSIGNED      NULL COMMENT 'NULL si es acción del sistema',
  `tipo`        VARCHAR(60)   NOT NULL,
  `entidad`     VARCHAR(40)       NULL,
  `entidad_id`  INT UNSIGNED      NULL,
  `descripcion` TEXT              NULL,
  `ip`          VARCHAR(45)       NULL,
  `created_at`  DATETIME      NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tipo`    (`tipo`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_creado`  (`created_at`),
  CONSTRAINT `fk_audit_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campo suspensión reversible en usuarios
ALTER TABLE `usuarios`
  ADD COLUMN `suspendido_at` DATETIME NULL DEFAULT NULL
    COMMENT 'NULL = activo, fecha = suspendido desde esa fecha'
  AFTER `deleted_at`;
