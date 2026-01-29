-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 14-01-2026 a las 21:55:03
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
-- Base de datos: `mrsos`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `clId` int(5) NOT NULL,
  `clNombre` varchar(50) NOT NULL,
  `clDireccion` text NOT NULL,
  `clTelefono` bigint(20) NOT NULL,
  `clCorreo` varchar(50) NOT NULL,
  `clEstatus` enum('Activo','Inactivo','NewPass','Error') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`clId`, `clNombre`, `clDireccion`, `clTelefono`, `clCorreo`, `clEstatus`) VALUES
(1, 'MR Solutions', 'Alhambra 813 Bis, Portales Sur, Benito Juárez, 03300 Ciudad de México, CDMX', 5555232003, 'ventas@mrsolutions.com.mx', 'Activo'),
(2, 'Enel', 'NA', 12, 'NA', 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cliente_sede`
--

CREATE TABLE `cliente_sede` (
  `csId` int(9) NOT NULL,
  `clId` int(5) NOT NULL,
  `czId` int(9) DEFAULT NULL,
  `csNombre` varchar(80) NOT NULL,
  `csCodigo` varchar(40) DEFAULT NULL,
  `csDireccion` text DEFAULT NULL,
  `csEstatus` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `csEsPrincipal` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cliente_sede`
--

INSERT INTO `cliente_sede` (`csId`, `clId`, `czId`, `csNombre`, `csCodigo`, `csDireccion`, `csEstatus`, `csEsPrincipal`) VALUES
(1, 1, NULL, 'Principal', NULL, NULL, 'Activo', 1),
(4, 2, 1, 'Enel Monterrey', NULL, NULL, 'Activo', 0),
(5, 2, 1, 'Enel Puebla', NULL, NULL, 'Activo', 0),
(6, 2, 2, 'Enel Miyana', NULL, NULL, 'Activo', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cliente_zona`
--

CREATE TABLE `cliente_zona` (
  `czId` int(9) NOT NULL,
  `clId` int(5) NOT NULL,
  `czNombre` varchar(80) NOT NULL,
  `czCodigo` varchar(40) DEFAULT NULL,
  `czDescripcion` text DEFAULT NULL,
  `czEstatus` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cliente_zona`
--

INSERT INTO `cliente_zona` (`czId`, `clId`, `czNombre`, `czCodigo`, `czDescripcion`, `czEstatus`) VALUES
(1, 2, 'Zona Norte', 'NORTE', 'Zona norte de ejemplo', 'Activo'),
(2, 2, 'Zona Sur', 'SUR', 'Zona sur de ejemplo', 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cuentas`
--

CREATE TABLE `cuentas` (
  `cuId` int(5) NOT NULL,
  `clId` int(5) NOT NULL,
  `pcId` int(5) NOT NULL,
  `usId` int(5) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cuentas`
--

INSERT INTO `cuentas` (`cuId`, `clId`, `pcId`, `usId`) VALUES
(1, 2, 2, 1003),
(2, 2, 3, 1003),
(3, 2, 4, 1003),
(4, 1, 1, 1000);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `equipos`
--

CREATE TABLE `equipos` (
  `eqId` int(5) NOT NULL,
  `eqModelo` varchar(50) NOT NULL,
  `eqVersion` varchar(25) NOT NULL,
  `eqTipoEquipo` varchar(50) NOT NULL DEFAULT 'Server',
  `maId` int(5) NOT NULL,
  `eqTipo` varchar(50) NOT NULL,
  `eqCPU` varchar(50) NOT NULL,
  `eqSockets` varchar(50) NOT NULL,
  `eqMaxRAM` varchar(50) NOT NULL,
  `eqNIC` varchar(50) NOT NULL,
  `eqDescripcion` text NOT NULL,
  `eqEstaus` enum('Activo','Inactivo','Cambios','Error') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `equipos`
--

INSERT INTO `equipos` (`eqId`, `eqModelo`, `eqVersion`, `eqTipoEquipo`, `maId`, `eqTipo`, `eqCPU`, `eqSockets`, `eqMaxRAM`, `eqNIC`, `eqDescripcion`, `eqEstaus`) VALUES
(1, 'FusionServer 1288H V6', 'V6', 'Servidor', 3, 'Rack 1U', 'Intel Xeon Scalable 3? Gen', '2', 'Hasta 12 TB', '2 x 10 GbE', 'Servidor de alta densidad para virtualizaci?n y computaci?n en la nube.', 'Activo'),
(2, 'FusionServer 2288H V6', 'V6', 'Servidor', 3, 'Rack 2U', 'Intel Xeon Scalable 3? Gen', '2', 'Hasta 12 TB', '4 x 1 GbE', 'Servidor vers?til para cargas de trabajo empresariales y big data.', 'Activo'),
(3, 'FusionServer 2488H V6', 'V6', 'Servidor', 3, 'Rack 2U', 'Intel Xeon Scalable 3? Gen', '4', 'Hasta 18 TB', '4 x 10 GbE', 'Servidor de alto rendimiento para virtualizaci?n y bases de datos.', 'Activo'),
(4, 'FusionServer 5288 V6', 'V6', 'Servidor', 3, 'Rack 4U', 'Intel Xeon Scalable 3? Gen', '2', 'Hasta 12 TB', '2 x 10 GbE', 'Servidor con gran capacidad de almacenamiento para archivado de datos.', 'Activo'),
(5, 'FusionServer X6000 V6', 'V6', 'Servidor', 3, 'High-Density 2U', 'Intel Xeon Scalable 3? Gen', '2 por nodo', 'Hasta 2 TB por nodo', '2 x 10 GbE por nodo', 'Servidor de alta densidad con 4 nodos para aplicaciones de HPC y nube.', 'Activo'),
(6, 'FusionServer G5500 V7', 'V7', 'Servidor', 3, 'AI Server', 'Intel Xeon Scalable 4? Gen', '2', 'Hasta 4 TB', '2 x 10 GbE', 'Servidor optimizado para aplicaciones de inteligencia artificial y an?lisis de datos.', 'Activo'),
(7, 'FusionServer G8600 V7', 'V7', 'Servidor', 3, 'AI Server', 'Intel Xeon Scalable 4? Gen', '2', 'Hasta 4 TB', '2 x 10 GbE', 'Servidor de alto rendimiento para cargas de trabajo de IA y HPC.', 'Activo'),
(8, 'FusionServer XH321 V6', 'V6', 'Servidor', 3, 'Blade 1U', 'Intel Xeon Scalable 3? Gen', '2', 'Hasta 2 TB', '2 x 10 GbE', 'Nodo blade de alto rendimiento para centros de datos y computaci?n en la nube.', 'Activo'),
(9, 'FusionServer XH321C V6', 'V6', 'Servidor', 3, 'Blade 1U', 'Intel Xeon Scalable 3? Gen', '2', 'Hasta 2 TB', '2 x 10 GbE', 'Nodo blade con refrigeraci?n l?quida para aplicaciones de alta densidad.', 'Activo'),
(10, 'FusionServer 2298 V5', 'V5', 'Servidor', 3, 'Rack 2U', 'Intel Xeon Scalable 2? Gen', '2', 'Hasta 1.5 TB', '2 x 10 GbE', 'Servidor con almacenamiento h?brido para archivado de datos hist?ricos.', 'Activo'),
(11, 'ProLiant DL360 Gen10', 'Gen10', 'Servidor', 13, 'Rack 1U', 'Intel Xeon Scalable', '2', 'Hasta 3 TB', '4 x 1 GbE', 'Servidor de alta densidad para cargas de trabajo intensivas.', 'Activo'),
(12, 'ProLiant DL380 Gen10', 'Gen10', 'Servidor', 13, 'Rack 2U', 'Intel Xeon Scalable', '2', 'Hasta 3 TB', '4 x 1 GbE', 'Servidor vers?til y seguro, adecuado para diversas cargas de trabajo empresariales.', 'Activo'),
(13, 'ProLiant ML350 Gen10', 'Gen10', 'Servidor', 13, 'Torre 4U', 'Intel Xeon Scalable', '2', 'Hasta 3 TB', '4 x 1 GbE', 'Servidor torre con alta capacidad de expansi?n y rendimiento.', 'Activo'),
(14, 'ProLiant DL385 Gen10', 'Gen10', 'Servidor', 13, 'Rack 2U', 'AMD EPYC', '2', 'Hasta 4 TB', '4 x 1 GbE', 'Servidor optimizado para cargas de trabajo virtualizadas y de alto rendimiento.', 'Activo'),
(15, 'ProLiant DL560 Gen10', 'Gen10', 'Servidor', 13, 'Rack 2U', 'Intel Xeon Scalable', '4', 'Hasta 6 TB', '4 x 1 GbE', 'Servidor de alto rendimiento para aplicaciones empresariales cr?ticas.', 'Activo'),
(16, 'ProLiant DL580 Gen10', 'Gen10', 'Servidor', 13, 'Rack 4U', 'Intel Xeon Scalable', '4', 'Hasta 6 TB', '4 x 1 GbE', 'Servidor de misi?n cr?tica con alta escalabilidad y rendimiento.', 'Activo'),
(17, 'ProLiant DL180 Gen10', 'Gen10', 'Servidor', 13, 'Rack 2U', 'Intel Xeon Scalable', '2', 'Hasta 1 TB', '2 x 1 GbE', 'Servidor de nivel de entrada para peque?as y medianas empresas.', 'Activo'),
(18, 'ProLiant DL160 Gen10', 'Gen10', 'Servidor', 13, 'Rack 1U', 'Intel Xeon Scalable', '2', 'Hasta 1 TB', '2 x 1 GbE', 'Servidor compacto y eficiente para cargas de trabajo generales.', 'Activo'),
(19, 'ProLiant ML110 Gen10', 'Gen10', 'Servidor', 13, 'Torre 4U', 'Intel Xeon Scalable', '1', 'Hasta 192 GB', '2 x 1 GbE', 'Servidor torre asequible para peque?as empresas con necesidades de expansi?n limitadas.', 'Activo'),
(20, 'ProLiant ML30 Gen10', 'Gen10', 'Servidor', 13, 'Torre 4U', 'Intel Xeon E-Series', '1', 'Hasta 64 GB', '2 x 1 GbE', 'Servidor torre compacto para peque?as oficinas y entornos remotos.', 'Activo'),
(21, 'Power S1022', 'Power10', 'Servidor', 5, 'Rack 2U', 'IBM Power10', '2', 'Hasta 4 TB', '2 x 10 GbE', 'Servidor de alto rendimiento para cargas de trabajo cr?ticas en entornos empresariales.', 'Activo'),
(22, 'Power S922', 'Power9', 'Servidor', 5, 'Rack 2U', 'IBM Power9', '2', 'Hasta 4 TB', '2 x 10 GbE', 'Servidor vers?til y seguro, adecuado para diversas cargas de trabajo empresariales.', 'Activo'),
(23, 'Power S924', 'Power9', 'Servidor', 5, 'Rack 4U', 'IBM Power9', '2', 'Hasta 8 TB', '2 x 10 GbE', 'Servidor de alto rendimiento para aplicaciones empresariales cr?ticas.', 'Activo'),
(24, 'Power E950', 'Power9', 'Servidor', 5, 'Rack 4U', 'IBM Power9', '4', 'Hasta 16 TB', '4 x 10 GbE', 'Servidor de misi?n cr?tica con alta escalabilidad y rendimiento.', 'Activo'),
(25, 'Power E980', 'Power9', 'Servidor', 5, 'Rack 5U', 'IBM Power9', '4', 'Hasta 64 TB', '4 x 10 GbE', 'Servidor de alto rendimiento para cargas de trabajo intensivas y an?lisis de datos.', 'Activo'),
(26, 'ower S1012', 'Power10', 'Servidor', 5, 'Rack 1U', 'IBM Power10', '1', 'Hasta 1 TB', '2 x 10 GbE', 'Servidor compacto para cargas de trabajo en el edge y centros de datos.', 'Activo'),
(27, 'Power S1014', 'Power10', 'Servidor', 5, 'Rack 4U', 'IBM Power10', '1', 'Hasta 2 TB', '2 x 10 GbE', 'Servidor de 1 socket para cargas de trabajo empresariales cr?ticas.', 'Activo'),
(28, 'Power S1022', 'Power10', 'Servidor', 5, 'Rack 2U', 'IBM Power10', '2', 'Hasta 4 TB', '2 x 10 GbE', 'Servidor de 2 sockets para consolidaci?n de cargas de trabajo.', 'Activo'),
(29, 'Power S1024', 'Power10', 'Servidor', 5, 'Rack 4U', 'IBM Power10', '2', 'Hasta 8 TB', '2 x 10 GbE', 'Servidor de 2 sockets con mayor capacidad de memoria para cargas intensivas.', 'Activo'),
(30, 'Power E1050', 'Power10', 'Servidor', 5, 'Rack 4U', 'IBM Power10', '4', 'Hasta 16 TB', '4 x 10 GbE', 'Servidor de alto rendimiento para aplicaciones empresariales cr?ticas.', 'Activo'),
(31, 'Power E1080', 'Power10', 'Servidor', 5, 'Rack 5U', 'IBM Power10', '4', 'Hasta 64 TB', '4 x 10 GbE', 'Servidor de misi?n cr?tica con alta escalabilidad y rendimiento.', 'Activo'),
(32, 'Power S1022', 'Power10', 'Servidor', 5, 'Rack 2U', 'IBM Power10', '2', 'Hasta 4 TB', '2 x 10 GbE', 'Servidor de alto rendimiento para cargas de trabajo cr?ticas en entornos empresariales.', 'Activo'),
(33, 'Power S922', 'Power9', 'Servidor', 5, 'Rack 2U', 'IBM Power9', '2', 'Hasta 4 TB', '2 x 10 GbE', 'Servidor vers?til y seguro, adecuado para diversas cargas de trabajo empresariales.', 'Activo'),
(34, 'Power S924', 'Power9', 'Servidor', 5, 'Rack 4U', 'IBM Power9', '2', 'Hasta 8 TB', '2 x 10 GbE', 'Servidor de alto rendimiento para aplicaciones empresariales cr?ticas.', 'Activo'),
(35, 'Power E950', 'Power9', 'Servidor', 5, 'Rack 4U', 'IBM Power9', '4', 'Hasta 16 TB', '4 x 10 GbE', 'Servidor de misi?n cr?tica con alta escalabilidad y rendimiento.', 'Activo'),
(36, 'Power E980', 'Power9', 'Servidor', 5, 'Rack 5U', 'IBM Power9', '4', 'Hasta 64 TB', '4 x 10 GbE', 'Servidor de alto rendimiento para cargas de trabajo intensivas y an?lisis de datos.', 'Activo'),
(37, 'Power S1022', 'Power10', 'Servidor', 5, 'Rack 2U', 'IBM Power10', '2', 'Hasta 4 TB', '2 x 10 GbE', 'Servidor de alto rendimiento para cargas de trabajo cr?ticas en entornos empresariales.', 'Activo'),
(38, 'Power S922', 'Power9', 'Servidor', 5, 'Rack 2U', 'IBM Power9', '2', 'Hasta 4 TB', '2 x 10 GbE', 'Servidor vers?til y seguro, adecuado para diversas cargas de trabajo empresariales.', 'Activo'),
(39, 'Power S924', 'Power9', 'Servidor', 5, 'Rack 4U', 'IBM Power9', '2', 'Hasta 8 TB', '2 x 10 GbE', 'Servidor de alto rendimiento para aplicaciones empresariales cr?ticas.', 'Activo'),
(40, 'Power E950', 'Power9', 'Servidor', 5, 'Rack 4U', 'IBM Power9', '4', 'Hasta 16 TB', '4 x 10 GbE', 'Servidor de misi?n cr?tica con alta escalabilidad y rendimiento.', 'Activo'),
(41, 'Power E980', 'Power9', 'Servidor', 5, 'Rack 5U', 'IBM Power9', '4', 'Hasta 64 TB', '4 x 10 GbE', 'Servidor de alto rendimiento para cargas de trabajo intensivas y an?lisis de datos.', 'Activo'),
(42, 'Advanced Server DS225', 'G2', 'Servidor', 2, 'Rack 2U', 'Intel Xeon Scalable', '2', 'Hasta 3 TB', '2 x 10 GbE', 'Servidor de alto rendimiento para aplicaciones exigentes.', 'Activo'),
(43, 'Advanced Server HA810', 'G3', 'Servidor', 2, 'Rack 1U', 'Intel Xeon Scalable', '2', 'Hasta 3 TB', '2 x 10 GbE', 'Servidor compacto y eficiente para cargas generales.', 'Activo'),
(44, 'Advanced Server HA805 G3', 'G3', 'Servidor', 2, 'Rack 1U', 'AMD EPYC 4? Gen', '1', 'Hasta 3 TB', '2 x 10 GbE', 'Servidor de alto rendimiento para aplicaciones empresariales cr?ticas.', 'Activo'),
(45, 'Advanced Server HA815 G3', 'G3', 'Servidor', 2, 'Rack 1U', 'AMD EPYC 4? Gen', '2', 'Hasta 6 TB', '2 x 10 GbE', 'Servidor blade de alto rendimiento para centros de datos y computaci?n en la nube.', 'Activo'),
(46, 'Advanced Server DS220 G2', 'G2', 'Servidor', 2, 'Rack 2U', 'Intel Xeon Scalable', '2', 'Hasta 8 TB', '4 x 10 GbE', 'Servidor de misi?n cr?tica con alta escalabilidad y rendimiento.', 'Activo'),
(47, 'PowerEdge R750', '15G', 'Servidor', 4, 'Rack 2U', 'Intel Xeon Scalable 3ª Gen', '2', '8 TB', '2 x 10 GbE', 'Servidor versátil para virtualización y aplicaciones empresariales de alto rendimiento.', 'Activo'),
(48, 'PowerEdge R650', '15G', 'Servidor', 4, 'Rack 1U', 'Intel Xeon Scalable 3ª Gen', '2', '4 TB', '2 x 10 GbE', 'Servidor de alta densidad optimizado para eficiencia energética y rendimiento.', 'Activo'),
(49, 'PowerEdge R550', '15G', 'Servidor', 4, 'Rack 2U', 'Intel Xeon Scalable 3ª Gen', '2', '2 TB', '2 x 1 GbE', 'Servidor rentable y escalable para cargas de trabajo generales.', 'Activo'),
(50, 'PowerEdge T550', '15G', 'Servidor', 4, 'Torre', 'Intel Xeon Scalable 3ª Gen', '2', '2 TB', '2 x 1 GbE', 'Servidor torre robusto para oficinas remotas y centros de datos pequeños.', 'Activo'),
(51, 'PowerEdge R740', '14G', 'Servidor', 4, 'Rack 2U', 'Intel Xeon Scalable 1ª/2ª Gen', '2', '3 TB', '4 x 1 GbE', 'Servidor versátil y probado para cargas de trabajo empresariales.', 'Activo'),
(52, 'PowerEdge R760', '16G', 'Servidor', 4, 'Rack 2U', 'Intel Xeon Scalable 4ª Gen', '2', '8 TB', '2 x 25 GbE', 'Servidor optimizado para rendimiento extremo y tareas empresariales exigentes.', 'Activo'),
(53, 'PowerEdge T640', '14G', 'Servidor', 4, 'Torre/Rack 5U', 'Intel Xeon Scalable 1ª/2ª Gen', '2', '3 TB', '4 x 1 GbE', 'Servidor torre altamente expandible ideal para virtualización y backup.', 'Activo'),
(54, 'PowerEdge MX740c', 'Modular', 'Servidor', 4, 'Blade', 'Intel Xeon Scalable 2ª Gen', '2', '3 TB', 'Conectividad de chasis MX', 'Servidor blade modular para centros de datos hiperconvergentes.', 'Activo'),
(55, 'PowerEdge XR11', '15G', 'Servidor', 4, 'Rugged 1U', 'Intel Xeon Scalable 3ª Gen', '1', '1 TB', '2 x 10 GbE', 'Servidor reforzado diseñado para entornos hostiles y Edge.', 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `health_check`
--

CREATE TABLE `health_check` (
  `hcId` int(11) NOT NULL,
  `clId` int(11) NOT NULL,
  `csId` int(11) NOT NULL,
  `usId` int(11) NOT NULL,
  `hcFechaHora` datetime NOT NULL,
  `hcDuracionMins` int(11) NOT NULL DEFAULT 240,
  `hcNombreContacto` varchar(120) NOT NULL,
  `hcNumeroContacto` varchar(25) NOT NULL,
  `hcCorreoContacto` varchar(120) NOT NULL,
  `hcEstatus` enum('Programado','Completado','Cancelado') NOT NULL DEFAULT 'Programado',
  `hcCreadoEn` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `health_check`
--

INSERT INTO `health_check` (`hcId`, `clId`, `csId`, `usId`, `hcFechaHora`, `hcDuracionMins`, `hcNombreContacto`, `hcNumeroContacto`, `hcCorreoContacto`, `hcEstatus`, `hcCreadoEn`) VALUES
(1, 1, 1, 1001, '2026-01-15 18:00:00', 240, 'Andre Gonzalo Flores Cabrera', '5534846421', 'andre.flores@mrsolutions.com.mx', 'Programado', '2026-01-07 00:12:12'),
(2, 2, 5, 2001, '2026-01-30 00:00:00', 240, 'Nailea Fragoso Osorio', '5534846421', 'andre.flores@mrsolutions.com.mx', 'Programado', '2026-01-07 05:12:03');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `health_check_items`
--

CREATE TABLE `health_check_items` (
  `hciId` int(11) NOT NULL,
  `hcId` int(11) NOT NULL,
  `eqId` int(11) NOT NULL,
  `peId` int(11) DEFAULT NULL,
  `tiId` int(11) DEFAULT NULL,
  `hciCreadoEn` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `health_check_items`
--

INSERT INTO `health_check_items` (`hciId`, `hcId`, `eqId`, `peId`, `tiId`, `hciCreadoEn`) VALUES
(1, 1, 2, 1, 21, '2026-01-07 00:12:12'),
(2, 1, 47, 2, 22, '2026-01-07 00:12:12'),
(3, 2, 47, 5, 23, '2026-01-07 05:12:03');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial`
--

CREATE TABLE `historial` (
  `hId` int(50) NOT NULL,
  `hDescripcion` text NOT NULL,
  `usId` int(5) NOT NULL,
  `hFecha_hora` varchar(255) NOT NULL,
  `hTabla` varchar(255) NOT NULL,
  `hEstatus` enum('Activo','Inactivo','Cambios','Error') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `hojas_servicio`
--

CREATE TABLE `hojas_servicio` (
  `hsId` int(11) NOT NULL,
  `clId` int(11) NOT NULL,
  `csId` int(11) NOT NULL,
  `tiId` int(11) DEFAULT NULL,
  `peId` int(11) DEFAULT NULL,
  `hsTipo` enum('T','HC') NOT NULL,
  `hsNumero` int(11) NOT NULL,
  `hsPrefix` varchar(10) NOT NULL,
  `hsFolio` varchar(30) NOT NULL,
  `hsNombreEquipo` varchar(200) DEFAULT NULL,
  `hsPath` varchar(500) NOT NULL,
  `hsMime` varchar(80) DEFAULT 'application/pdf',
  `hsActivo` tinyint(1) DEFAULT 1,
  `hsCreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `hsUpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ingenieros`
--

CREATE TABLE `ingenieros` (
  `ingId` int(5) NOT NULL,
  `usId` int(5) NOT NULL,
  `ingTier` enum('Tier 1','Tier 2','Tier 3') NOT NULL DEFAULT 'Tier 1',
  `ingExperto` enum('OS','Cloud','Storage','Datacom','Servers','Virtualización','Backup','Otro') NOT NULL DEFAULT 'Otro',
  `ingDescripcion` text DEFAULT NULL,
  `ingEstatus` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ingenieros`
--

INSERT INTO `ingenieros` (`ingId`, `usId`, `ingTier`, `ingExperto`, `ingDescripcion`, `ingEstatus`) VALUES
(1, 1001, 'Tier 1', 'Cloud', 'Ingeniero Experto en cloud', 'Activo'),
(2, 1002, 'Tier 1', 'Storage', 'Ingeniero experto en Storage', 'Activo'),
(3, 1004, 'Tier 1', 'OS', 'Ingeniero experto en OS', 'Activo'),
(4, 1005, 'Tier 1', 'Datacom', 'Ingeniero experto en Datacom', 'Activo'),
(6, 1007, 'Tier 2', 'Backup', 'Ingeniero experto en Backup', 'Activo'),
(7, 1008, 'Tier 2', 'Storage', 'Ingeniero experto en Storage', 'Activo'),
(8, 1007, 'Tier 3', 'Backup', 'Ingeniero experto en Backup', 'Activo'),
(9, 1008, 'Tier 3', 'Storage', 'Ingeniero experto en Storage', 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventario`
--

CREATE TABLE `inventario` (
  `invId` int(5) NOT NULL,
  `invSerialNumber` bigint(30) NOT NULL,
  `refPartNumber` int(5) NOT NULL,
  `invUbicación` varchar(15) NOT NULL,
  `invEstatus` enum('Activo','Inactivo','Cambios','Error') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `marca`
--

CREATE TABLE `marca` (
  `maId` int(5) NOT NULL,
  `maNombre` varchar(50) NOT NULL,
  `maEstatus` enum('Activo','Inactivo','Cambios','Error') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `marca`
--

INSERT INTO `marca` (`maId`, `maNombre`, `maEstatus`) VALUES
(1, 'Huawei', 'Activo'),
(2, 'Hitachi', 'Activo'),
(3, 'xFusion', 'Activo'),
(4, 'Dell', 'Activo'),
(5, 'IBM', 'Activo'),
(6, 'Cisco', 'Activo'),
(7, 'Lenovo', 'Activo'),
(8, 'Veeam', 'Activo'),
(9, 'Proxmox', 'Activo'),
(10, 'Commvault', 'Activo'),
(11, 'Oracle', 'Activo'),
(12, 'VMware', 'Activo'),
(13, 'HPE', 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `polizascliente`
--

CREATE TABLE `polizascliente` (
  `pcId` int(5) NOT NULL,
  `pcIdentificador` varchar(64) DEFAULT NULL,
  `pcPdfPath` varchar(255) DEFAULT NULL,
  `pcTipoPoliza` varchar(15) NOT NULL,
  `clId` int(5) NOT NULL,
  `csId` int(9) DEFAULT NULL,
  `pcFechaInicio` date NOT NULL,
  `pcFechaFin` date NOT NULL,
  `usId` int(5) NOT NULL,
  `pcEstatus` enum('Activo','Inactivo','Cambios','Error','Vencida') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `polizascliente`
--

INSERT INTO `polizascliente` (`pcId`, `pcIdentificador`, `pcPdfPath`, `pcTipoPoliza`, `clId`, `csId`, `pcFechaInicio`, `pcFechaFin`, `usId`, `pcEstatus`) VALUES
(1, 'B 6162', NULL, 'Platinum', 1, 1, '2023-05-01', '2028-05-31', 1001, 'Activo'),
(2, 'B 6163', 'uploads/poliza/enel', 'Platinum', 2, 4, '2023-05-01', '2028-05-31', 2001, 'Activo'),
(3, 'B 6164', NULL, 'Platinum', 2, 5, '2023-05-01', '2028-12-01', 2001, 'Activo'),
(4, 'B 6165', NULL, 'Gold', 2, 6, '2023-05-01', '2028-12-31', 2001, 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `polizasequipo`
--

CREATE TABLE `polizasequipo` (
  `peId` int(5) NOT NULL,
  `peDescripcion` text NOT NULL,
  `peSN` text NOT NULL,
  `pcId` int(5) NOT NULL,
  `csId` int(9) DEFAULT NULL,
  `peSO` varchar(25) NOT NULL,
  `peEstatus` enum('Activo','Inactivo','Error') NOT NULL DEFAULT 'Activo',
  `eqId` int(5) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `polizasequipo`
--

INSERT INTO `polizasequipo` (`peId`, `peDescripcion`, `peSN`, `pcId`, `csId`, `peSO`, `peEstatus`, `eqId`) VALUES
(1, 'Equipo que almacena las VM de prueba', '2106195YSAXEP2000008', 1, 1, 'Windows Server 2019', 'Activo', 2),
(2, 'Equipo que almacena las VM de prueba 2', '2106195YSAXEP2000009', 1, 1, 'Ubuntu', 'Activo', 47),
(3, 'Equipo que almacena las VM de prueba 3', '2106195YSAXEP2000010', 2, 4, 'Ubuntu', 'Activo', 3),
(4, 'Equipo que almacena las VM de prueba 4', '2106195YSAXEP2000011', 4, 6, 'Ubuntu', 'Activo', 44),
(5, 'Equipo que almacena las VM de prueba 5', '2106195YSAXEP2000012', 3, 5, 'Ubuntu', 'Activo', 47),
(6, 'Equipo que almacena las VM de prueba 4', '2106195YSAXEP2000015', 4, 6, 'Ubuntu', 'Activo', 44),
(7, 'Equipo que almacena las VM de prueba 5', '2106195YSAXEP2000019', 4, 5, 'Ubuntu', 'Activo', 47);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `refaccion`
--

CREATE TABLE `refaccion` (
  `refId` int(5) NOT NULL,
  `refPartNumber` varchar(50) NOT NULL,
  `refDescripcion` text NOT NULL,
  `refTipoRefaccion` enum('Network Card','Video Card','RAID Card','PCIE Card','Motherboard','Hard Disk','DIMM','Processador','Fan Module','Gbics','Power Supply','Cinta LTO','Backplain','Nodo','Flash Card','Disipador de Calor','Manage Card','Diagnostic Card','Caddy','Sistema Operativo','Swicth Module') NOT NULL,
  `refInterfaz` varchar(25) NOT NULL,
  `refTipo` varchar(15) NOT NULL,
  `maId` int(5) NOT NULL,
  `refCapacidad` decimal(10,2) NOT NULL,
  `refTpCapacidad` varchar(15) NOT NULL,
  `refVelocidad` decimal(10,2) NOT NULL,
  `refTpVelocidad` varchar(15) NOT NULL,
  `refEstatus` enum('Activo','Inactivo','Cambios','Error') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ticket_acceso_ingeniero`
--

CREATE TABLE `ticket_acceso_ingeniero` (
  `taiId` int(11) NOT NULL,
  `tiId` int(11) NOT NULL,
  `usId` int(11) DEFAULT NULL,
  `nombre` varchar(120) NOT NULL,
  `email` varchar(150) NOT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `curp` varchar(20) DEFAULT NULL,
  `nss` varchar(20) DEFAULT NULL,
  `autoPlacas` varchar(15) DEFAULT NULL,
  `autoMarca` varchar(50) DEFAULT NULL,
  `autoModelo` varchar(50) DEFAULT NULL,
  `autoColor` varchar(40) DEFAULT NULL,
  `celMarca` varchar(50) DEFAULT NULL,
  `celModelo` varchar(50) DEFAULT NULL,
  `celSerie` varchar(80) DEFAULT NULL,
  `lapMarca` varchar(50) DEFAULT NULL,
  `lapModelo` varchar(50) DEFAULT NULL,
  `lapSerie` varchar(80) DEFAULT NULL,
  `extraInfo` varchar(500) DEFAULT NULL,
  `creadoEn` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ticket_acceso_ingeniero`
--

INSERT INTO `ticket_acceso_ingeniero` (`taiId`, `tiId`, `usId`, `nombre`, `email`, `telefono`, `curp`, `nss`, `autoPlacas`, `autoMarca`, `autoModelo`, `autoColor`, `celMarca`, `celModelo`, `celSerie`, `lapMarca`, `lapModelo`, `lapSerie`, `extraInfo`, `creadoEn`) VALUES
(4, 10, 1005, 'Nailea Fragoso', 'nailea.fragoso@cliente.com', '5551234567', 'NAFN990101MDFRRL09', '12345678901', 'ABC-1234', 'Toyota', 'Corolla 2018', 'Gris', 'Samsung', 'Galaxy S22', 'IMEI358240123456789', 'Dell', 'Latitude 5420', 'LAT5420-NAI-001', 'Ingeniera principal asignada a la visita.', '2025-12-01 01:08:54'),
(5, 10, 1005, 'Carlos Méndez', 'carlos.mendez@cliente.com', '5559876543', 'MEMC920315HDFNRL03', '98765432109', 'XYZ-9876', 'Nissan', 'Versa 2020', 'Blanco', 'Apple', 'iPhone 13', 'IMEI356789123456789', 'Lenovo', 'ThinkPad T14', 'T14-CAR-002', 'Ingeniero de apoyo para pruebas en sitio.', '2025-12-01 01:08:54'),
(6, 10, 1005, 'Andrea López', 'andrea.lopez@cliente.com', '5543219876', 'LOAA930722MDFPRN08', '55566677788', 'KLM-4569', 'Chevrolet', 'Aveo 2019', 'Rojo', 'Xiaomi', 'Mi 13', 'IMEI359001234567890', 'HP', 'EliteBook 840', 'HP840-AND-003', 'Requiere acceso a cuarto de cómputo.', '2025-12-01 01:08:54');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ticket_archivos`
--

CREATE TABLE `ticket_archivos` (
  `taId` int(11) NOT NULL,
  `tiId` int(11) NOT NULL,
  `taTipo` enum('log','evidencia','otro') DEFAULT 'log',
  `taNombreOriginal` varchar(255) NOT NULL,
  `taNombreAlmacenado` varchar(255) NOT NULL,
  `taMime` varchar(120) DEFAULT NULL,
  `taTamano` int(11) DEFAULT NULL,
  `taRuta` varchar(255) NOT NULL,
  `usId` int(11) DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ticket_archivos`
--

INSERT INTO `ticket_archivos` (`taId`, `tiId`, `taTipo`, `taNombreOriginal`, `taNombreAlmacenado`, `taMime`, `taTamano`, `taRuta`, `usId`, `fecha`) VALUES
(1, 9, 'log', 'Telmex Licenciado.txt', 'log_9_689e2ed9056664.23183947.txt', 'text/plain', 306, 'uploads/logs/9/log_9_689e2ed9056664.23183947.txt', 2001, '2025-08-14 18:45:45'),
(2, 9, 'log', 'Telmex Licenciado.txt', 'log_9_689e363760a9e9.69115002.txt', 'text/plain', 306, 'uploads/logs/9/log_9_689e363760a9e9.69115002.txt', 2001, '2025-08-14 19:17:11'),
(3, 9, 'log', 'Telmex Licenciado.txt', 'log_9_689e390a5dc087.21046012.txt', 'text/plain', 306, 'uploads/logs/9/log_9_689e390a5dc087.21046012.txt', 2001, '2025-08-14 19:29:14'),
(4, 9, 'log', 'Telmex Licenciado.txt', 'log_9_689e39c8b776f7.55100291.txt', 'text/plain', 306, 'uploads/logs/9/log_9_689e39c8b776f7.55100291.txt', 2001, '2025-08-14 19:32:24'),
(5, 9, 'log', 'Telmex Licenciado.txt', 'log_9_689e3b3a61fc81.81603436.txt', 'text/plain', 306, 'uploads/logs/9/log_9_689e3b3a61fc81.81603436.txt', 2001, '2025-08-14 19:38:34'),
(6, 9, 'log', 'Telmex Licenciado.txt', 'log_9_689e70ea0a8825.82359326.txt', 'text/plain', 306, 'uploads/logs/9/log_9_689e70ea0a8825.82359326.txt', 2001, '2025-08-14 23:27:38'),
(7, 9, 'log', 'Telmex Licenciado.txt', 'log_9_68f92c9569f2d1.90012156.txt', 'text/plain', 306, 'uploads/logs/9/log_9_68f92c9569f2d1.90012156.txt', 2001, '2025-10-22 19:12:21'),
(8, 9, 'log', 'Backup-codes-lizma0116.txt', 'log_9_692a5d3dac5d16.74180365.txt', 'text/plain', 434, 'uploads/logs/9/log_9_692a5d3dac5d16.74180365.txt', 2001, '2025-11-29 02:41:01'),
(9, 15, 'log', 'Backup-codes-lizma0116.txt', 'log_15_692df07d1cc380.77129215.txt', 'text/plain', 434, 'uploads/logs/15/log_15_692df07d1cc380.77129215.txt', 2001, '2025-12-01 19:46:05'),
(10, 17, 'log', 'Backup-codes-lizma0116.txt', 'log_17_692f1983049bd5.82020539.txt', 'text/plain', 434, 'uploads/logs/17/log_17_692f1983049bd5.82020539.txt', 2001, '2025-12-02 16:53:23');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ticket_folio_entrada`
--

CREATE TABLE `ticket_folio_entrada` (
  `tfeId` int(11) NOT NULL,
  `tiId` int(11) NOT NULL,
  `folio` varchar(80) NOT NULL,
  `archivoRuta` varchar(255) DEFAULT NULL,
  `comentario` text DEFAULT NULL,
  `creadoPor` int(11) NOT NULL,
  `creadoEn` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ticket_folio_entrada`
--

INSERT INTO `ticket_folio_entrada` (`tfeId`, `tiId`, `folio`, `archivoRuta`, `comentario`, `creadoPor`, `creadoEn`) VALUES
(1, 9, 'asas', 'folio_9_20251201015142.png', 'asas', 2001, '2025-12-01 01:51:42');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ticket_soporte`
--

CREATE TABLE `ticket_soporte` (
  `tiId` int(5) NOT NULL,
  `clId` int(5) NOT NULL,
  `csId` int(9) DEFAULT NULL,
  `usId` int(8) NOT NULL,
  `eqId` int(5) NOT NULL,
  `peId` int(11) DEFAULT NULL,
  `tiDescripcion` text NOT NULL,
  `tiEstatus` enum('Abierto','Pospuesto','Cerrado') NOT NULL,
  `tiProceso` enum('asignacion','revision inicial','logs','meet','revision especial','espera refaccion','visita','fecha asignada','espera ventana','espera visita','en camino','espera documentacion','encuesta satisfaccion','finalizado','cancelado','fuera de alcance','servicio por evento') DEFAULT 'asignacion',
  `tiTipoTicket` enum('Servicio','Preventivo','Extra') NOT NULL DEFAULT 'Servicio',
  `tiExtra` text NOT NULL DEFAULT '--',
  `tiNivelCriticidad` varchar(12) NOT NULL,
  `tiFechaCreacion` date NOT NULL,
  `tiVisita` datetime NOT NULL,
  `tiVisitaDuracionMins` int(11) DEFAULT NULL,
  `tiVisitaAutorId` int(11) DEFAULT NULL,
  `tiMeetActivo` enum('meet cliente','meet ingeniero','meet solicitado cliente','meet solicitado ingeniero') DEFAULT NULL,
  `tiDiagnostico` text DEFAULT NULL,
  `tiVisitaTipo` varchar(50) DEFAULT NULL,
  `tiVisitaModo` varchar(30) DEFAULT NULL,
  `tiVisitaAutor` int(11) DEFAULT NULL,
  `tiVisitaAutorNombre` varchar(120) DEFAULT NULL,
  `tiAccesoRequiereDatos` tinyint(1) NOT NULL DEFAULT 0,
  `tiAccesoExtraTexto` varchar(500) DEFAULT NULL,
  `tiAccesoFolio` varchar(100) DEFAULT NULL,
  `tiAccesoSoportePath` varchar(255) DEFAULT NULL,
  `tiVisitaFecha` date DEFAULT NULL,
  `tiVisitaHora` time DEFAULT NULL,
  `tiVisitaEstado` varchar(50) DEFAULT NULL,
  `tiFolioEntrada` varchar(80) DEFAULT NULL,
  `tiFolioArchivo` varchar(255) DEFAULT NULL,
  `tiFolioCreadoEn` datetime DEFAULT NULL,
  `tiFolioCreadoPor` int(11) DEFAULT NULL,
  `tiVisitaCancelBy` varchar(20) DEFAULT NULL,
  `tiVisitaCancelMotivo` text DEFAULT NULL,
  `tiVisitaCancelFecha` datetime DEFAULT NULL,
  `tiCitaPropuesta` datetime DEFAULT NULL,
  `tiVisitaConfirmada` tinyint(1) NOT NULL DEFAULT 0,
  `tiNombreContacto` varchar(50) NOT NULL,
  `tiNumeroContacto` varchar(25) NOT NULL DEFAULT '555-55-55-55',
  `tiCorreoContacto` varchar(50) NOT NULL DEFAULT 'example@correo.com',
  `usIdIng` int(5) NOT NULL DEFAULT 1002,
  `estatus` enum('Activo','Inactivo','Cambios','Error') NOT NULL DEFAULT 'Activo',
  `tiMeetFecha` date DEFAULT NULL,
  `tiMeetHora` time DEFAULT NULL,
  `tiMeetPlataforma` varchar(30) DEFAULT NULL,
  `tiMeetEnlace` varchar(255) DEFAULT NULL,
  `tiMeetModo` enum('propuesta_cliente','propuesta_ingeniero','asignado_cliente','asignado_ingeniero') DEFAULT NULL,
  `tiMeetEstado` enum('pendiente','confirmado','rechazado','reprogramar') DEFAULT 'pendiente',
  `tiMeetCancelBy` varchar(20) DEFAULT NULL,
  `tiMeetCancelMotivo` text DEFAULT NULL,
  `tiMeetCancelFecha` datetime DEFAULT NULL,
  `tiMeetAutorNombre` varchar(80) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ticket_soporte`
--

INSERT INTO `ticket_soporte` (`tiId`, `clId`, `csId`, `usId`, `eqId`, `peId`, `tiDescripcion`, `tiEstatus`, `tiProceso`, `tiTipoTicket`, `tiExtra`, `tiNivelCriticidad`, `tiFechaCreacion`, `tiVisita`, `tiVisitaDuracionMins`, `tiVisitaAutorId`, `tiMeetActivo`, `tiDiagnostico`, `tiVisitaTipo`, `tiVisitaModo`, `tiVisitaAutor`, `tiVisitaAutorNombre`, `tiAccesoRequiereDatos`, `tiAccesoExtraTexto`, `tiAccesoFolio`, `tiAccesoSoportePath`, `tiVisitaFecha`, `tiVisitaHora`, `tiVisitaEstado`, `tiFolioEntrada`, `tiFolioArchivo`, `tiFolioCreadoEn`, `tiFolioCreadoPor`, `tiVisitaCancelBy`, `tiVisitaCancelMotivo`, `tiVisitaCancelFecha`, `tiCitaPropuesta`, `tiVisitaConfirmada`, `tiNombreContacto`, `tiNumeroContacto`, `tiCorreoContacto`, `usIdIng`, `estatus`, `tiMeetFecha`, `tiMeetHora`, `tiMeetPlataforma`, `tiMeetEnlace`, `tiMeetModo`, `tiMeetEstado`, `tiMeetCancelBy`, `tiMeetCancelMotivo`, `tiMeetCancelFecha`, `tiMeetAutorNombre`) VALUES
(1, 1, 1, 1001, 2, 1, 'El equipo presenta un problema en disco duro \ny se necesita cambiar', 'Abierto', 'logs', 'Servicio', '--', '3', '2025-08-08', '2025-05-15 18:42:00', NULL, NULL, NULL, 'Prueba 2 de asignación de un análisis', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'confirmada', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 127, 'Jonh Due', '555-55-55-55', 'example@correo.com', 1001, 'Activo', NULL, NULL, NULL, NULL, NULL, 'pendiente', NULL, NULL, NULL, NULL),
(5, 1, 1, 1001, 47, 2, 'Muestra para pruebas MR2', 'Abierto', 'meet', 'Preventivo', '--', '1', '2025-08-02', '0000-00-00 00:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'confirmada', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'Andre Flores', '5534846421', 'andre.flores@mrsolutions.com.mx', 1002, 'Activo', NULL, NULL, NULL, NULL, NULL, 'pendiente', NULL, NULL, NULL, NULL),
(6, 1, 1, 1001, 47, 2, 'Muestra para pruebas MR3', 'Abierto', 'fecha asignada', 'Extra', '--', '2', '2025-08-02', '0000-00-00 00:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'confirmada', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'Andre Flores', '5534846421', 'andre.flores@mrsolutions.com.mx', 1002, 'Activo', NULL, NULL, NULL, NULL, NULL, 'pendiente', NULL, NULL, NULL, NULL),
(7, 2, 5, 2001, 3, 3, 'Prueba de ticket Puebla', 'Abierto', 'revision inicial', 'Servicio', '--', '3', '2025-08-08', '0000-00-00 00:00:00', NULL, NULL, NULL, 'Nuevo analisis 6 y final?', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'confirmada', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'Nailea FO', '5534846421', 'nailea@enel.com.mx', 1007, 'Activo', NULL, NULL, NULL, NULL, NULL, 'pendiente', NULL, NULL, NULL, NULL),
(9, 2, 6, 2001, 47, 5, 'Prueba de ticket Miyana', 'Abierto', 'visita', 'Extra', '--', '1', '2025-08-12', '2025-12-04 19:28:00', 60, 2001, NULL, 'Hola esta es una revisión', 'ingeniero', NULL, NULL, 'Nailea', 0, '', NULL, NULL, '2025-12-04', '09:30:00', 'confirmada', 'asas', 'folio_9_20251201015142.png', '2025-12-01 01:51:42', 2001, 'cliente', '', '2025-12-01 11:46:43', NULL, 1, 'Nailea O', '5534846421', 'andre.flores@mrsolutions.com.mx', 1005, 'Activo', '2025-12-25', '18:12:00', 'Google Meet', 'https://meet.google.com/gtu-ztqu-odf', 'propuesta_cliente', 'pendiente', NULL, NULL, NULL, 'Nailea'),
(10, 2, 6, 2001, 47, 5, 'Prueba de ticket Miyana', 'Pospuesto', 'meet', 'Extra', '--', '1', '2025-08-12', '0000-00-00 00:00:00', NULL, NULL, 'meet ingeniero', 'Hola esta es una revisión', 'cliente', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'confirmada', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 127, 'Nailea O', '5534846421', 'andre.flores@mrsolutions.com.mx', 1005, 'Activo', '2026-01-16', '03:40:00', 'Google Meet', '', 'propuesta_ingeniero', 'pendiente', 'cliente', '', '2026-01-03 03:37:50', 'Andre Gonzalo'),
(11, 2, 4, 2001, 3, 3, 'a', 'Abierto', 'asignacion', 'Servicio', '--', '3', '2025-11-30', '0000-00-00 00:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'Andre Flores', '5534846421', 'andre.flores@mrsolutions.com.mx', 1002, 'Activo', NULL, NULL, NULL, NULL, NULL, 'pendiente', NULL, NULL, NULL, NULL),
(14, 2, 6, 2001, 44, 4, 'Ninguno es prueba A', 'Abierto', 'asignacion', 'Servicio', '--', '3', '2025-12-01', '0000-00-00 00:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'Andre Flores', '5534846421', 'andre.flores@mrsolutions.com.mx', 1002, 'Activo', NULL, NULL, NULL, NULL, NULL, 'pendiente', NULL, NULL, NULL, NULL),
(15, 2, 6, 2001, 44, 4, 'Prueba con correo', 'Abierto', 'logs', 'Servicio', '--', '3', '2025-12-01', '0000-00-00 00:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'Andre Flores', '5534846421', 'andre.flores@mrsolutions.com.mx', 1002, 'Activo', NULL, NULL, NULL, NULL, NULL, 'pendiente', NULL, NULL, NULL, NULL),
(16, 2, 4, 2001, 3, 3, 'aasasasas', 'Abierto', 'encuesta satisfaccion', 'Servicio', '--', '1', '2025-12-02', '0000-00-00 00:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'Andre Flores', '5534846422', 'andre.flores@mrsolutions.com.mx', 1002, 'Activo', NULL, NULL, NULL, NULL, NULL, 'pendiente', NULL, NULL, NULL, NULL),
(17, 2, 6, 2001, 44, 4, 'sas', 'Abierto', 'revision especial', 'Servicio', '--', '2', '2025-12-02', '0000-00-00 00:00:00', NULL, NULL, NULL, 'Se requieren Logs', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'Andre Flores', '5534846421', 'andre.flores@mrsolutions.com.mx', 1004, 'Activo', NULL, NULL, NULL, NULL, NULL, 'pendiente', NULL, NULL, NULL, NULL),
(18, 2, 6, 2001, 44, 4, 'Problema prueba presental', 'Abierto', 'asignacion', 'Servicio', '--', '3', '2025-12-02', '0000-00-00 00:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'Andre Flores', '5534846421', 'andre.flores@mrsolutions.com.mx', 1002, 'Activo', NULL, NULL, NULL, NULL, NULL, 'pendiente', NULL, NULL, NULL, NULL),
(19, 2, 4, 2001, 3, 3, 'a', 'Abierto', 'asignacion', 'Servicio', '--', '3', '2026-01-04', '0000-00-00 00:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'Andre Flores', '5534846421', 'andregonzalo47@gmail.com', 1002, 'Activo', NULL, NULL, NULL, NULL, NULL, 'pendiente', NULL, NULL, NULL, NULL),
(20, 1, 1, 1001, 2, 1, 'hola', 'Abierto', 'asignacion', 'Servicio', '--', '3', '2026-01-06', '0000-00-00 00:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'Andre Gonzalo Flores Cabrera', '5534846421', 'andre.flores@mrsolutions.com.mx', 1002, 'Activo', NULL, NULL, NULL, NULL, NULL, 'pendiente', NULL, NULL, NULL, NULL),
(21, 1, 1, 1001, 2, 1, 'Health Check (mantenimiento preventivo) programado', 'Abierto', 'visita', 'Preventivo', '--', '3', '2026-01-06', '2026-01-15 18:00:00', 240, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'Andre Gonzalo Flores Cabrera', '5534846421', 'andre.flores@mrsolutions.com.mx', 1002, 'Activo', NULL, NULL, NULL, NULL, NULL, 'pendiente', NULL, NULL, NULL, NULL),
(22, 1, 1, 1001, 47, 2, 'Health Check (mantenimiento preventivo) programado', 'Abierto', 'visita', 'Preventivo', '--', '3', '2026-01-06', '2026-01-15 18:00:00', 240, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'Andre Gonzalo Flores Cabrera', '5534846421', 'andre.flores@mrsolutions.com.mx', 1002, 'Activo', NULL, NULL, NULL, NULL, NULL, 'pendiente', NULL, NULL, NULL, NULL),
(23, 2, 5, 2001, 47, 5, 'Health Check (mantenimiento preventivo) programado', 'Abierto', 'visita', 'Preventivo', '--', '3', '2026-01-06', '2026-01-30 00:00:00', 240, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'Nailea Fragoso Osorio', '5534846421', 'andre.flores@mrsolutions.com.mx', 1002, 'Activo', NULL, NULL, NULL, NULL, NULL, 'pendiente', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `usId` int(5) NOT NULL,
  `usNombre` varchar(20) NOT NULL,
  `usAPaterno` varchar(20) NOT NULL,
  `usAMaterno` varchar(20) NOT NULL,
  `usRol` enum('CLI','MRA','MRV','MRSA') NOT NULL DEFAULT 'CLI',
  `usCorreo` varchar(50) NOT NULL,
  `usPass` text NOT NULL,
  `usResetToken` bigint(34) NOT NULL,
  `usResetTokenExpira` datetime NOT NULL,
  `usTelefono` bigint(20) NOT NULL,
  `usTokenTelefono` text NOT NULL DEFAULT 'N/A',
  `usImagen` varchar(55) NOT NULL DEFAULT 'avatar_default.png',
  `usNotificaciones` varchar(15) NOT NULL DEFAULT 'N/A',
  `usTheme` enum('light','dark') NOT NULL DEFAULT 'light',
  `usNotifInApp` tinyint(1) NOT NULL DEFAULT 1,
  `usNotifMail` tinyint(1) NOT NULL DEFAULT 1,
  `usNotifTicketCambio` tinyint(1) NOT NULL DEFAULT 1,
  `usNotifMeet` tinyint(1) NOT NULL DEFAULT 1,
  `usNotifVisita` tinyint(1) NOT NULL DEFAULT 1,
  `usNotifFolio` tinyint(1) NOT NULL DEFAULT 1,
  `clId` int(5) NOT NULL,
  `usConfirmado` varchar(15) NOT NULL,
  `usEstatus` enum('Activo','Inactivo','NewPass','Error','Eliminado') NOT NULL DEFAULT 'Activo',
  `usUsername` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`usId`, `usNombre`, `usAPaterno`, `usAMaterno`, `usRol`, `usCorreo`, `usPass`, `usResetToken`, `usResetTokenExpira`, `usTelefono`, `usTokenTelefono`, `usImagen`, `usNotificaciones`, `usTheme`, `usNotifInApp`, `usNotifMail`, `usNotifTicketCambio`, `usNotifMeet`, `usNotifVisita`, `usNotifFolio`, `clId`, `usConfirmado`, `usEstatus`, `usUsername`) VALUES
(1000, 'Miguel', 'García', 'Contreras', 'MRA', 'miguel@mrsolutions.com.mx', '$2y$10$BM/012LgYwxjjR1GYbCAbuza69X7FaBU9sgexYU17JPjb.k5gvRha', 0, '2025-05-23 18:56:23', 55, 'N/A', '0', 'a', 'light', 1, 1, 1, 1, 1, 1, 1, 'Si', 'Activo', 'MiguelGC'),
(1001, 'Andre Gonzalo', 'Flores', 'Cabrera', 'MRSA', 'andre.flores@mrsolutions.com.mx', '$2y$10$BM/012LgYwxjjR1GYbCAbuza69X7FaBU9sgexYU17JPjb.k5gvRha', 0, '2025-05-23 18:56:23', 5534846421, 'N/A', '1', 'a', 'light', 1, 0, 1, 1, 1, 1, 1, 'Si', 'Activo', 'AndreFC47'),
(1002, 'Luis', 'Tostado', 'De los Santos', 'MRA', 'luis.tostado@mrsolutions.com.mx', '$2y$10$BM/012LgYwxjjR1GYbCAbuza69X7FaBU9sgexYU17JPjb.k5gvRha', 0, '2025-05-23 18:56:23', 55, 'N/A', '0', 'a', 'light', 1, 1, 1, 1, 1, 1, 1, 'Si', 'Activo', 'LuisTS'),
(1003, 'Alfredo', 'Meza', 'Cara', 'MRV', 'alfredo.meza@mrsolutions.com.mx', '$2y$10$BM/012LgYwxjjR1GYbCAbuza69X7FaBU9sgexYU17JPjb.k5gvRha', 0, '2025-05-23 18:56:23', 55, 'N/A', '1', 'a', 'light', 1, 1, 1, 1, 1, 1, 1, 'Si', 'Activo', 'AlfredoME'),
(1004, 'Manfredo Augusto', 'Gonzalez', 'Lozada', 'MRA', 'mgonzalez@mrsolutions.com.mx', '$2y$10$BM/012LgYwxjjR1GYbCAbuza69X7FaBU9sgexYU17JPjb.k5gvRha', 0, '2025-10-23 20:56:08', 55, 'N/A', '1', 'NA', 'light', 1, 1, 1, 1, 1, 1, 1, 'Si', 'Activo', 'ManfredoG'),
(1005, 'Jorge Antonio', 'Hernandez', 'García', 'MRA', 'jorge.hernandez@mrsolutions.com.mx', '$2y$10$BM/012LgYwxjjR1GYbCAbuza69X7FaBU9sgexYU17JPjb.k5gvRha', 0, '2025-10-23 20:56:08', 55, 'N/A', '1', 'a', 'light', 1, 1, 1, 1, 1, 1, 1, 'Si', 'Activo', 'JorgeH'),
(1006, 'Luis', 'Tostado', 'De los Santos', 'MRSA', 'luis.tostado@mrsolutions.com.mx', '$2y$10$BM/012LgYwxjjR1GYbCAbuza69X7FaBU9sgexYU17JPjb.k5gvRha', 0, '2025-10-23 20:56:08', 55, 'N/A', '1', 'a', 'light', 1, 1, 1, 1, 1, 1, 1, 'Si', 'Activo', 'LuisT'),
(1007, 'Christian', 'Mata', 'San Luis', 'MRA', 'chmata@mrsolutions.com.mx', '$2y$10$BM/012LgYwxjjR1GYbCAbuza69X7FaBU9sgexYU17JPjb.k5gvRha', 0, '2025-10-23 20:56:08', 55, 'N/A', '1', 'a', 'light', 1, 1, 1, 1, 1, 1, 1, 'Si', 'Activo', 'ChistianM'),
(1008, 'Juan Carlos', 'Zafra', 'Daniel', 'MRA', 'juan@mrsolutions.com.mx', '$2y$10$BM/012LgYwxjjR1GYbCAbuza69X7FaBU9sgexYU17JPjb.k5gvRha', 0, '2025-10-23 20:56:08', 55, 'N/A', '1', 'a', 'light', 1, 1, 1, 1, 1, 1, 1, 'Si', 'Activo', 'JuanZ'),
(1009, 'Norma', 'De la Cruz', '-', 'MRSA', 'norma@mrsolutions.com.mx', '$2y$10$BM/012LgYwxjjR1GYbCAbuza69X7FaBU9sgexYU17JPjb.k5gvRha', 0, '2025-10-23 20:56:08', 55, 'N/A', '1', 'a', 'light', 1, 1, 1, 1, 1, 1, 1, 'Si', 'Activo', 'NormaC'),
(2001, 'Nailea', 'Fragoso', 'Osorio', 'CLI', 'andre.flores@mrsolutions.com.mx', '$2y$12$pxcLCSvdwYmExxUyzP/0w.G/aKLz6egS74o0PJ01HuH3LykjQFzge', 0, '1970-01-01 00:00:00', 5534846421, 'N/A', '1', 'a', 'light', 1, 0, 1, 1, 1, 1, 2, 'Si', 'Activo', 'NaileaFO'),
(2002, 'Raymundo', 'Leonel', 'Arga', 'CLI', 'ray@enel.com.mx', '$2y$10$BM/012LgYwxjjR1GYbCAbuza69X7FaBU9sgexYU17JPjb.k5gvRha', 0, '2025-05-23 18:56:23', 5555643121, 'N/A', '0', 'a', 'light', 1, 1, 1, 1, 1, 1, 2, 'Si', 'Activo', 'RayAG'),
(2003, 'Jonh', 'Ted', 'Fred', 'CLI', 'fred@enel.com.mx', '$2y$10$BM/012LgYwxjjR1GYbCAbuza69X7FaBU9sgexYU17JPjb.k5gvRha', 0, '2025-05-23 18:56:23', 5520844358, 'N/A', '1', 'a', 'light', 1, 1, 1, 1, 1, 1, 2, 'Si', 'Activo', 'JonhTF'),
(2008, 'Andre Gonzalo', 'Flores', 'Cabrera', 'CLI', 'andregonzalo47@gmail.com', '$2y$10$QVta6FDofCg3bebFUVGfte5h3AXNDJAGoyt2sWKd2ehTy0n9L3xLO', 0, '0000-00-00 00:00:00', 5534846422, 'N/A', '0', 'N/A', 'light', 1, 1, 1, 1, 1, 1, 2, '', 'Activo', 'andrefc147');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario_cliente_rol`
--

CREATE TABLE `usuario_cliente_rol` (
  `ucrId` int(11) NOT NULL,
  `usId` int(5) NOT NULL,
  `clId` int(5) NOT NULL,
  `czId` int(9) DEFAULT NULL,
  `csId` int(9) DEFAULT NULL,
  `ucrRol` enum('ADMIN_GLOBAL','ADMIN_ZONA','ADMIN_SEDE','USUARIO','VISOR') NOT NULL,
  `ucrEstatus` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuario_cliente_rol`
--

INSERT INTO `usuario_cliente_rol` (`ucrId`, `usId`, `clId`, `czId`, `csId`, `ucrRol`, `ucrEstatus`) VALUES
(1, 2001, 2, NULL, NULL, 'ADMIN_GLOBAL', 'Activo'),
(3, 2002, 2, 2, 5, 'ADMIN_ZONA', 'Activo'),
(4, 2003, 2, 1, 4, 'USUARIO', 'Activo'),
(8, 2008, 2, 2, NULL, 'USUARIO', 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `webpush_subscriptions`
--

CREATE TABLE `webpush_subscriptions` (
  `id` int(11) NOT NULL,
  `usId` int(11) NOT NULL,
  `endpoint` text NOT NULL,
  `p256dh` varchar(255) NOT NULL,
  `auth` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`clId`);

--
-- Indices de la tabla `cliente_sede`
--
ALTER TABLE `cliente_sede`
  ADD PRIMARY KEY (`csId`),
  ADD UNIQUE KEY `uq_cs_nombre_por_cliente` (`clId`,`csNombre`),
  ADD KEY `ix_cs_clId` (`clId`),
  ADD KEY `fk_cs_zona` (`czId`);

--
-- Indices de la tabla `cliente_zona`
--
ALTER TABLE `cliente_zona`
  ADD PRIMARY KEY (`czId`),
  ADD UNIQUE KEY `uq_cz_nombre_por_cliente` (`clId`,`czNombre`),
  ADD KEY `ix_cz_clId` (`clId`);

--
-- Indices de la tabla `cuentas`
--
ALTER TABLE `cuentas`
  ADD PRIMARY KEY (`cuId`),
  ADD KEY `clId` (`clId`),
  ADD KEY `usIdIR` (`usId`),
  ADD KEY `pcId` (`pcId`);

--
-- Indices de la tabla `equipos`
--
ALTER TABLE `equipos`
  ADD PRIMARY KEY (`eqId`),
  ADD KEY `eqMarca` (`maId`);

--
-- Indices de la tabla `health_check`
--
ALTER TABLE `health_check`
  ADD PRIMARY KEY (`hcId`),
  ADD KEY `idx_hc_csId` (`csId`),
  ADD KEY `idx_hc_clId` (`clId`),
  ADD KEY `idx_hc_usId` (`usId`);

--
-- Indices de la tabla `health_check_items`
--
ALTER TABLE `health_check_items`
  ADD PRIMARY KEY (`hciId`),
  ADD KEY `idx_hci_hcId` (`hcId`),
  ADD KEY `idx_hci_eqId` (`eqId`),
  ADD KEY `idx_hci_tiId` (`tiId`);

--
-- Indices de la tabla `historial`
--
ALTER TABLE `historial`
  ADD PRIMARY KEY (`hId`),
  ADD KEY `aId` (`usId`);

--
-- Indices de la tabla `hojas_servicio`
--
ALTER TABLE `hojas_servicio`
  ADD PRIMARY KEY (`hsId`),
  ADD KEY `idx_cl` (`clId`),
  ADD KEY `idx_cs` (`csId`),
  ADD KEY `idx_ti` (`tiId`),
  ADD KEY `idx_folio` (`hsFolio`),
  ADD KEY `idx_tipo` (`hsTipo`);

--
-- Indices de la tabla `ingenieros`
--
ALTER TABLE `ingenieros`
  ADD PRIMARY KEY (`ingId`),
  ADD KEY `usId` (`usId`);

--
-- Indices de la tabla `inventario`
--
ALTER TABLE `inventario`
  ADD PRIMARY KEY (`invId`,`invSerialNumber`),
  ADD KEY `refPartNumber` (`refPartNumber`);

--
-- Indices de la tabla `marca`
--
ALTER TABLE `marca`
  ADD PRIMARY KEY (`maId`);

--
-- Indices de la tabla `polizascliente`
--
ALTER TABLE `polizascliente`
  ADD PRIMARY KEY (`pcId`),
  ADD UNIQUE KEY `uq_pc_identificador` (`pcIdentificador`),
  ADD KEY `clId` (`clId`),
  ADD KEY `ix_pc_csId` (`csId`);

--
-- Indices de la tabla `polizasequipo`
--
ALTER TABLE `polizasequipo`
  ADD PRIMARY KEY (`peId`),
  ADD KEY `eqId` (`eqId`),
  ADD KEY `pcId` (`pcId`),
  ADD KEY `ix_pe_csId` (`csId`);

--
-- Indices de la tabla `refaccion`
--
ALTER TABLE `refaccion`
  ADD PRIMARY KEY (`refId`,`refPartNumber`),
  ADD KEY `refMarca` (`maId`),
  ADD KEY `maId` (`maId`);

--
-- Indices de la tabla `ticket_acceso_ingeniero`
--
ALTER TABLE `ticket_acceso_ingeniero`
  ADD PRIMARY KEY (`taiId`),
  ADD KEY `fk_tai_ticket` (`tiId`),
  ADD KEY `fk_tai_usuario` (`usId`);

--
-- Indices de la tabla `ticket_archivos`
--
ALTER TABLE `ticket_archivos`
  ADD PRIMARY KEY (`taId`),
  ADD KEY `tiId` (`tiId`);

--
-- Indices de la tabla `ticket_folio_entrada`
--
ALTER TABLE `ticket_folio_entrada`
  ADD PRIMARY KEY (`tfeId`),
  ADD KEY `tiId` (`tiId`),
  ADD KEY `fk_tfe_usuario` (`creadoPor`);

--
-- Indices de la tabla `ticket_soporte`
--
ALTER TABLE `ticket_soporte`
  ADD PRIMARY KEY (`tiId`),
  ADD KEY `clId` (`clId`),
  ADD KEY `usId` (`usId`),
  ADD KEY `eqId` (`eqId`),
  ADD KEY `usIdIng` (`usIdIng`),
  ADD KEY `ix_ti_csId` (`csId`),
  ADD KEY `fk_ticket_pe` (`peId`),
  ADD KEY `idx_tiCitaEstado` (`tiVisitaEstado`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`usId`),
  ADD KEY `clId` (`clId`);

--
-- Indices de la tabla `usuario_cliente_rol`
--
ALTER TABLE `usuario_cliente_rol`
  ADD PRIMARY KEY (`ucrId`),
  ADD UNIQUE KEY `uq_ucr_combo` (`usId`,`clId`,`czId`,`csId`,`ucrRol`),
  ADD KEY `ix_ucr_usId` (`usId`),
  ADD KEY `ix_ucr_clId` (`clId`),
  ADD KEY `ix_ucr_czId` (`czId`),
  ADD KEY `ix_ucr_csId` (`csId`);

--
-- Indices de la tabla `webpush_subscriptions`
--
ALTER TABLE `webpush_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_endpoint` (`endpoint`(191)),
  ADD KEY `idx_user` (`usId`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `clId` int(5) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `cliente_sede`
--
ALTER TABLE `cliente_sede`
  MODIFY `csId` int(9) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `cliente_zona`
--
ALTER TABLE `cliente_zona`
  MODIFY `czId` int(9) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `cuentas`
--
ALTER TABLE `cuentas`
  MODIFY `cuId` int(5) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `health_check`
--
ALTER TABLE `health_check`
  MODIFY `hcId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `health_check_items`
--
ALTER TABLE `health_check_items`
  MODIFY `hciId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `historial`
--
ALTER TABLE `historial`
  MODIFY `hId` int(50) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `hojas_servicio`
--
ALTER TABLE `hojas_servicio`
  MODIFY `hsId` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ingenieros`
--
ALTER TABLE `ingenieros`
  MODIFY `ingId` int(5) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `inventario`
--
ALTER TABLE `inventario`
  MODIFY `invId` int(5) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `marca`
--
ALTER TABLE `marca`
  MODIFY `maId` int(5) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `polizascliente`
--
ALTER TABLE `polizascliente`
  MODIFY `pcId` int(5) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `polizasequipo`
--
ALTER TABLE `polizasequipo`
  MODIFY `peId` int(5) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `refaccion`
--
ALTER TABLE `refaccion`
  MODIFY `refId` int(5) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ticket_acceso_ingeniero`
--
ALTER TABLE `ticket_acceso_ingeniero`
  MODIFY `taiId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `ticket_archivos`
--
ALTER TABLE `ticket_archivos`
  MODIFY `taId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `ticket_folio_entrada`
--
ALTER TABLE `ticket_folio_entrada`
  MODIFY `tfeId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `ticket_soporte`
--
ALTER TABLE `ticket_soporte`
  MODIFY `tiId` int(5) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `usId` int(5) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2009;

--
-- AUTO_INCREMENT de la tabla `usuario_cliente_rol`
--
ALTER TABLE `usuario_cliente_rol`
  MODIFY `ucrId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `webpush_subscriptions`
--
ALTER TABLE `webpush_subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `cliente_sede`
--
ALTER TABLE `cliente_sede`
  ADD CONSTRAINT `cliente_sede_ibfk_1` FOREIGN KEY (`clId`) REFERENCES `clientes` (`clId`),
  ADD CONSTRAINT `fk_cs_zona` FOREIGN KEY (`czId`) REFERENCES `cliente_zona` (`czId`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `cliente_zona`
--
ALTER TABLE `cliente_zona`
  ADD CONSTRAINT `fk_cz_cliente` FOREIGN KEY (`clId`) REFERENCES `clientes` (`clId`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `cuentas`
--
ALTER TABLE `cuentas`
  ADD CONSTRAINT `cuentas_ibfk_2` FOREIGN KEY (`usId`) REFERENCES `usuarios` (`usId`),
  ADD CONSTRAINT `cuentas_ibfk_3` FOREIGN KEY (`pcId`) REFERENCES `polizascliente` (`pcId`),
  ADD CONSTRAINT `cuentas_ibfk_4` FOREIGN KEY (`clId`) REFERENCES `clientes` (`clId`);

--
-- Filtros para la tabla `equipos`
--
ALTER TABLE `equipos`
  ADD CONSTRAINT `equipos_ibfk_1` FOREIGN KEY (`maId`) REFERENCES `marca` (`maId`);

--
-- Filtros para la tabla `health_check_items`
--
ALTER TABLE `health_check_items`
  ADD CONSTRAINT `fk_hci_hc` FOREIGN KEY (`hcId`) REFERENCES `health_check` (`hcId`) ON DELETE CASCADE;

--
-- Filtros para la tabla `historial`
--
ALTER TABLE `historial`
  ADD CONSTRAINT `historial_ibfk_1` FOREIGN KEY (`usId`) REFERENCES `usuarios` (`usId`);

--
-- Filtros para la tabla `ingenieros`
--
ALTER TABLE `ingenieros`
  ADD CONSTRAINT `ingenieros_ibfk_1` FOREIGN KEY (`usId`) REFERENCES `usuarios` (`usId`);

--
-- Filtros para la tabla `inventario`
--
ALTER TABLE `inventario`
  ADD CONSTRAINT `inventario_ibfk_1` FOREIGN KEY (`refPartNumber`) REFERENCES `refaccion` (`refId`);

--
-- Filtros para la tabla `polizascliente`
--
ALTER TABLE `polizascliente`
  ADD CONSTRAINT `fk_pc_sede` FOREIGN KEY (`csId`) REFERENCES `cliente_sede` (`csId`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `polizascliente_ibfk_1` FOREIGN KEY (`clId`) REFERENCES `clientes` (`clId`);

--
-- Filtros para la tabla `polizasequipo`
--
ALTER TABLE `polizasequipo`
  ADD CONSTRAINT `fk_pe_sede` FOREIGN KEY (`csId`) REFERENCES `cliente_sede` (`csId`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `polizasequipo_ibfk_2` FOREIGN KEY (`eqId`) REFERENCES `equipos` (`eqId`),
  ADD CONSTRAINT `polizasequipo_ibfk_3` FOREIGN KEY (`pcId`) REFERENCES `polizascliente` (`pcId`);

--
-- Filtros para la tabla `refaccion`
--
ALTER TABLE `refaccion`
  ADD CONSTRAINT `refaccion_ibfk_1` FOREIGN KEY (`maId`) REFERENCES `marca` (`maId`);

--
-- Filtros para la tabla `ticket_acceso_ingeniero`
--
ALTER TABLE `ticket_acceso_ingeniero`
  ADD CONSTRAINT `fk_tai_ticket` FOREIGN KEY (`tiId`) REFERENCES `ticket_soporte` (`tiId`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tai_usuario` FOREIGN KEY (`usId`) REFERENCES `usuarios` (`usId`) ON DELETE SET NULL;

--
-- Filtros para la tabla `ticket_archivos`
--
ALTER TABLE `ticket_archivos`
  ADD CONSTRAINT `fk_ta_ticket` FOREIGN KEY (`tiId`) REFERENCES `ticket_soporte` (`tiId`) ON DELETE CASCADE;

--
-- Filtros para la tabla `ticket_folio_entrada`
--
ALTER TABLE `ticket_folio_entrada`
  ADD CONSTRAINT `fk_tfe_ticket` FOREIGN KEY (`tiId`) REFERENCES `ticket_soporte` (`tiId`),
  ADD CONSTRAINT `fk_tfe_usuario` FOREIGN KEY (`creadoPor`) REFERENCES `usuarios` (`usId`);

--
-- Filtros para la tabla `ticket_soporte`
--
ALTER TABLE `ticket_soporte`
  ADD CONSTRAINT `fk_ti_sede` FOREIGN KEY (`csId`) REFERENCES `cliente_sede` (`csId`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ticket_pe` FOREIGN KEY (`peId`) REFERENCES `polizasequipo` (`peId`),
  ADD CONSTRAINT `ticket_soporte_ibfk_1` FOREIGN KEY (`clId`) REFERENCES `clientes` (`clId`),
  ADD CONSTRAINT `ticket_soporte_ibfk_2` FOREIGN KEY (`usId`) REFERENCES `usuarios` (`usId`),
  ADD CONSTRAINT `ticket_soporte_ibfk_3` FOREIGN KEY (`eqId`) REFERENCES `equipos` (`eqId`),
  ADD CONSTRAINT `ticket_soporte_ibfk_4` FOREIGN KEY (`usIdIng`) REFERENCES `usuarios` (`usId`);

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`clId`) REFERENCES `clientes` (`clId`);

--
-- Filtros para la tabla `usuario_cliente_rol`
--
ALTER TABLE `usuario_cliente_rol`
  ADD CONSTRAINT `fk_ucr_cliente` FOREIGN KEY (`clId`) REFERENCES `clientes` (`clId`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ucr_sede` FOREIGN KEY (`csId`) REFERENCES `cliente_sede` (`csId`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ucr_user` FOREIGN KEY (`usId`) REFERENCES `usuarios` (`usId`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ucr_zona` FOREIGN KEY (`czId`) REFERENCES `cliente_zona` (`czId`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
