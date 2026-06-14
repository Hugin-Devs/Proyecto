-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost
-- Tiempo de generación: 30-05-2026 a las 08:07:53
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `service_libre`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `usuario_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'NULL si es acción del sistema',
  `tipo` varchar(60) NOT NULL,
  `entidad` varchar(40) DEFAULT NULL,
  `entidad_id` int(10) UNSIGNED DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `audit_log`
--

INSERT INTO `audit_log` (`id`, `usuario_id`, `tipo`, `entidad`, `entidad_id`, `descripcion`, `ip`, `created_at`) VALUES
(1, 3, 'logout', 'usuarios', 3, 'Logout', '127.0.0.1', '2026-05-30 01:15:01'),
(2, 1, 'login', 'usuarios', 1, 'Login unificado exitoso', '127.0.0.1', '2026-05-30 01:15:10'),
(3, 1, 'logout', 'usuarios', 1, 'Logout', '127.0.0.1', '2026-05-30 01:25:58'),
(4, 1, 'login', 'usuarios', 1, 'Login unificado exitoso', '127.0.0.1', '2026-05-30 01:26:01'),
(5, 1, 'login', 'usuarios', 1, 'Login unificado exitoso', '127.0.0.1', '2026-05-30 01:38:17'),
(6, 1, 'logout', 'usuarios', 1, 'Logout', '127.0.0.1', '2026-05-30 01:38:24'),
(7, 1, 'admin_toggle_verificado', 'servicios', 7, 'Admin cambió estado verificado del servicio #7', '127.0.0.1', '2026-05-30 01:43:22'),
(8, 1, 'admin_toggle_verificado', 'servicios', 7, 'Admin cambió estado verificado del servicio #7', '127.0.0.1', '2026-05-30 01:43:49');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

CREATE TABLE `categorias` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `categorias`
--

INSERT INTO `categorias` (`id`, `nombre`) VALUES
(1, 'Plomería'),
(2, 'Electricidad'),
(3, 'Comida'),
(4, 'Belleza'),
(5, 'Remodelación'),
(6, 'Tecnología'),
(7, 'Delivery'),
(8, 'Otro');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `chat_mensajes`
--

CREATE TABLE `chat_mensajes` (
  `id` int(10) UNSIGNED NOT NULL,
  `servicio_id` int(10) UNSIGNED NOT NULL,
  `cliente_id` int(10) UNSIGNED NOT NULL,
  `proveedor_id` int(10) UNSIGNED NOT NULL,
  `emisor_id` int(10) UNSIGNED NOT NULL,
  `mensaje` text NOT NULL,
  `leido` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `archivado_cliente` tinyint(1) NOT NULL DEFAULT 0,
  `archivado_proveedor` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `chat_mensajes`
--

INSERT INTO `chat_mensajes` (`id`, `servicio_id`, `cliente_id`, `proveedor_id`, `emisor_id`, `mensaje`, `leido`, `created_at`, `archivado_cliente`, `archivado_proveedor`) VALUES
(1, 7, 2, 3, 2, 'Hola que tal', 1, '2026-05-22 19:06:02', 0, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `contrataciones`
--

CREATE TABLE `contrataciones` (
  `id` int(10) UNSIGNED NOT NULL,
  `servicio_id` int(10) UNSIGNED NOT NULL,
  `cliente_id` int(10) UNSIGNED NOT NULL,
  `proveedor_id` int(10) UNSIGNED NOT NULL,
  `estado` enum('pendiente','aceptado','rechazado','cancelado','completado') NOT NULL DEFAULT 'pendiente',
  `motivo` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `contrataciones`
--

INSERT INTO `contrataciones` (`id`, `servicio_id`, `cliente_id`, `proveedor_id`, `estado`, `motivo`, `created_at`, `updated_at`) VALUES
(1, 7, 2, 3, 'completado', NULL, '2026-05-22 23:29:22', '2026-05-22 23:43:54'),
(2, 7, 2, 3, 'completado', NULL, '2026-05-23 00:01:44', '2026-05-23 00:02:06');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `municipios`
--

CREATE TABLE `municipios` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(60) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `municipios`
--

INSERT INTO `municipios` (`id`, `nombre`) VALUES
(1, 'Chacao'),
(2, 'Baruta'),
(3, 'Sucre'),
(4, 'Libertador');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `servicios`
--

CREATE TABLE `servicios` (
  `id` int(10) UNSIGNED NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `categoria` varchar(80) NOT NULL DEFAULT '',
  `municipio` varchar(60) NOT NULL DEFAULT '',
  `precio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `usuario_id` int(10) UNSIGNED DEFAULT NULL,
  `es_destacado` tinyint(1) NOT NULL DEFAULT 0,
  `verificado` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `servicios`
--

INSERT INTO `servicios` (`id`, `titulo`, `descripcion`, `imagen`, `categoria`, `municipio`, `precio`, `usuario_id`, `es_destacado`, `verificado`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Plomería Express — Reparaciones urgentes a domicilio', NULL, NULL, 'Plomería', 'Chacao', 45.00, NULL, 1, 0, '2026-05-22 17:07:44', NULL, NULL),
(2, 'Electricista Certificado — Instalaciones y mantenimiento', NULL, NULL, 'Electricidad', 'Baruta', 80.00, NULL, 0, 0, '2026-05-22 17:07:44', NULL, NULL),
(3, 'Cocina Doña Carmen — Almuerzos caseros con delivery', NULL, NULL, 'Comida', 'Sucre', 12.00, NULL, 1, 0, '2026-05-22 17:07:44', NULL, NULL),
(4, 'Barbería Estilo Libre — Cortes modernos y afeitado', NULL, NULL, 'Belleza', 'Libertador', 15.00, NULL, 0, 0, '2026-05-22 17:07:44', NULL, NULL),
(5, 'Soporte Técnico PC — Reparación y mantenimiento', NULL, NULL, 'Tecnología', 'Chacao', 30.00, NULL, 0, 0, '2026-05-22 17:07:44', NULL, NULL),
(6, 'Remodelaciones LM — Pintura, cerámica y carpintería', NULL, NULL, 'Remodelación', 'Baruta', 200.00, NULL, 0, 0, '2026-05-22 17:07:44', NULL, NULL),
(7, 'Servicio de Pruebas', NULL, NULL, 'Tecnología', 'libertador', 2500.00, 3, 0, 0, '2026-05-22 19:05:28', '2026-05-30 01:43:49', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `apellido` varchar(80) NOT NULL DEFAULT '',
  `email` varchar(120) NOT NULL,
  `telefono` varchar(30) NOT NULL DEFAULT '',
  `password` varchar(255) NOT NULL,
  `role` enum('cliente','proveedor','admin') NOT NULL DEFAULT 'cliente',
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `suspendido_at` datetime DEFAULT NULL COMMENT 'NULL = activo, fecha = suspendido desde esa fecha'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `apellido`, `email`, `telefono`, `password`, `role`, `last_login`, `created_at`, `updated_at`, `deleted_at`, `suspendido_at`) VALUES
(1, 'Admin', 'ServiJob', 'admin@servijob.com', '', '$2y$10$Sb2NT0Hjz9bmnmpnVGUk0.RTJtb4cTWPVuphJvFCiNvoV2Cp3livm', 'admin', '2026-05-30 01:38:17', '2026-05-22 17:07:44', '2026-05-30 01:38:17', NULL, NULL),
(2, 'user', 'test', 'usertest1@servijob.com', '', '$2y$10$wlIOO6STTlS2OLUvbG0d0uNFnG14oT9DcDfymE3wKjl6OJo7Sx2WO', 'cliente', '2026-05-23 01:21:02', '2026-05-22 18:20:00', '2026-05-23 01:21:02', NULL, NULL),
(3, 'proveedor', 'test', 'proveedortest1@servijob.com', '', '$2y$10$/SUSeiHnStAVC5x/hH0TputZh2scJDwKfZ0UxGxTQOYMI7A1/a.gK', 'proveedor', '2026-05-23 01:38:21', '2026-05-22 19:05:28', '2026-05-23 01:38:21', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `valoraciones`
--

CREATE TABLE `valoraciones` (
  `id` int(10) UNSIGNED NOT NULL,
  `contratacion_id` int(10) UNSIGNED NOT NULL,
  `cliente_id` int(10) UNSIGNED NOT NULL,
  `proveedor_id` int(10) UNSIGNED NOT NULL,
  `puntuacion` int(11) NOT NULL,
  `comentario` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `valoraciones`
--

INSERT INTO `valoraciones` (`id`, `contratacion_id`, `cliente_id`, `proveedor_id`, `puntuacion`, `comentario`, `created_at`) VALUES
(1, 1, 2, 1, 5, 'Excelente', '2026-05-22 23:59:59'),
(2, 2, 2, 3, 3, 'prueba', '2026-05-23 00:04:45');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `verificaciones`
--

CREATE TABLE `verificaciones` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `municipio` varchar(60) NOT NULL DEFAULT '',
  `doc_path` varchar(255) NOT NULL,
  `estado` enum('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
  `usuario_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tipo` (`tipo`),
  ADD KEY `idx_usuario` (`usuario_id`),
  ADD KEY `idx_creado` (`created_at`);

--
-- Indices de la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `chat_mensajes`
--
ALTER TABLE `chat_mensajes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conv` (`servicio_id`,`cliente_id`,`proveedor_id`),
  ADD KEY `idx_emisor` (`emisor_id`),
  ADD KEY `idx_leido` (`leido`),
  ADD KEY `fk_chat_cliente` (`cliente_id`),
  ADD KEY `fk_chat_proveedor` (`proveedor_id`);

--
-- Indices de la tabla `contrataciones`
--
ALTER TABLE `contrataciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_contrato_servicio` (`servicio_id`),
  ADD KEY `fk_contrato_cliente` (`cliente_id`),
  ADD KEY `fk_contrato_proveedor` (`proveedor_id`);

--
-- Indices de la tabla `municipios`
--
ALTER TABLE `municipios`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `servicios`
--
ALTER TABLE `servicios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_municipio` (`municipio`),
  ADD KEY `idx_es_destacado` (`es_destacado`),
  ADD KEY `fk_servicio_usuario` (`usuario_id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`);

--
-- Indices de la tabla `valoraciones`
--
ALTER TABLE `valoraciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_valoracion_contrato` (`contratacion_id`),
  ADD KEY `fk_valoracion_cliente` (`cliente_id`),
  ADD KEY `fk_valoracion_proveedor` (`proveedor_id`);

--
-- Indices de la tabla `verificaciones`
--
ALTER TABLE `verificaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_estado` (`estado`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `chat_mensajes`
--
ALTER TABLE `chat_mensajes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `contrataciones`
--
ALTER TABLE `contrataciones`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `municipios`
--
ALTER TABLE `municipios`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `servicios`
--
ALTER TABLE `servicios`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `valoraciones`
--
ALTER TABLE `valoraciones`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `verificaciones`
--
ALTER TABLE `verificaciones`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `fk_audit_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `chat_mensajes`
--
ALTER TABLE `chat_mensajes`
  ADD CONSTRAINT `fk_chat_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_chat_emisor` FOREIGN KEY (`emisor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_chat_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_chat_servicio` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `contrataciones`
--
ALTER TABLE `contrataciones`
  ADD CONSTRAINT `fk_contrato_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_contrato_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_contrato_servicio` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `servicios`
--
ALTER TABLE `servicios`
  ADD CONSTRAINT `fk_servicio_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `valoraciones`
--
ALTER TABLE `valoraciones`
  ADD CONSTRAINT `fk_valoracion_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_valoracion_contrato` FOREIGN KEY (`contratacion_id`) REFERENCES `contrataciones` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_valoracion_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
