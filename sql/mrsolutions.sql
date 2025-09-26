-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 22-05-2025 a las 23:22:04
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
-- Base de datos: `mrsolutions`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventario`
--

CREATE TABLE `inventario` (
  `invId` int(11) NOT NULL,
  `invSerialNumber` int(11) NOT NULL,
  `refPartNumber` varchar(15) NOT NULL,
  `invUbicación` varchar(15) NOT NULL,
  `invEstatus` int(5) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `marca`
--

CREATE TABLE `marca` (
  `marId` int(11) NOT NULL,
  `marNombre` varchar(50) NOT NULL,
  `marEstatus` enum('Activo','Inactivo','Fallado','Pausado') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `refaccion`
--

CREATE TABLE `refaccion` (
  `refId` int(5) NOT NULL,
  `refPartNumber` varchar(15) NOT NULL,
  `refDescripcion` text NOT NULL,
  `refTipoRefaccion` enum('Network Card','Video Card','RAID Card','PCIE Card','Motherboard','Hard Disk','DIMM','Processador','Fan Module','Gbics','Power Supply','Cinta LTO','Backplain','Nodo','Flash Card','Disipador de Calor','Manage Card','Diagnostic Card','Caddy','Sistema Operativo','Swicth Module') NOT NULL,
  `refInterfaz` varchar(25) NOT NULL,
  `refTipo` varchar(15) NOT NULL,
  `refMarca` varchar(25) NOT NULL,
  `refCapacidad` decimal(10,2) NOT NULL,
  `refTpCapacidad` varchar(15) NOT NULL,
  `refVelocidad` decimal(10,2) NOT NULL,
  `refTpVelocidad` varchar(15) NOT NULL,
  `refEstatus` int(3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tablas volcadas
--

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
  ADD PRIMARY KEY (`marId`),
  ADD UNIQUE KEY `marNombre` (`marNombre`);

--
-- Indices de la tabla `refaccion`
--
ALTER TABLE `refaccion`
  ADD PRIMARY KEY (`refId`,`refPartNumber`),
  ADD KEY `refMarca` (`refMarca`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `inventario`
--
ALTER TABLE `inventario`
  MODIFY `invId` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `marca`
--
ALTER TABLE `marca`
  MODIFY `marId` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `refaccion`
--
ALTER TABLE `refaccion`
  MODIFY `refId` int(5) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
