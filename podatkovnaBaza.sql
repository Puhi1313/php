-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Gostitelj: 127.0.0.1
-- Čas nastanka: 21. okt 2025 ob 11.35
-- Različica strežnika: 10.4.32-MariaDB
-- Različica PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Zbirka podatkov: `redovalnica_test1`
--

-- --------------------------------------------------------

--
-- Struktura tabele `gradivo`
--

CREATE TABLE `gradivo` (
  `id_gradivo` int(10) UNSIGNED NOT NULL,
  `id_ucitelj` int(10) UNSIGNED NOT NULL,
  `id_predmet` int(10) UNSIGNED NOT NULL,
  `ime_datoteke` varchar(255) NOT NULL,
  `pot_na_strezniku` varchar(500) NOT NULL,
  `datum_objave` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabele `naloga`
--

CREATE TABLE `naloga` (
  `id_naloga` int(10) UNSIGNED NOT NULL,
  `id_ucitelj` int(10) UNSIGNED NOT NULL,
  `id_predmet` int(10) UNSIGNED NOT NULL,
  `naslov` varchar(255) NOT NULL,
  `opis_naloge` text DEFAULT NULL,
  `rok_oddaje` datetime NOT NULL,
  `datum_objave` datetime NOT NULL DEFAULT current_timestamp(),
  `pot_na_strezniku` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Odloži podatke za tabelo `naloga`
--

INSERT INTO `naloga` (`id_naloga`, `id_ucitelj`, `id_predmet`, `naslov`, `opis_naloge`, `rok_oddaje`, `datum_objave`, `pot_na_strezniku`) VALUES
(6, 121, 11, 'matematika 1', 'naloge 67', '2025-10-21 08:12:00', '2025-10-21 08:06:46', NULL),
(7, 121, 11, 'mat 2', '6767676', '2025-10-21 08:50:00', '2025-10-21 08:22:23', NULL),
(9, 121, 11, 'naloga 2', '676777', '2025-10-21 14:50:00', '2025-10-21 10:50:51', NULL),
(10, 121, 11, 'dadasd', 'dasdad', '2025-10-21 15:52:00', '2025-10-21 10:52:12', NULL),
(11, 121, 11, 'zabava', '67 is funny', '2025-10-21 11:14:00', '2025-10-21 11:09:33', NULL),
(12, 121, 11, 'dsadasdsa', 'dadasd', '2025-10-21 11:34:00', '2025-10-21 11:30:31', NULL),
(13, 121, 11, 'dsadsad', 'dsad', '2025-10-21 11:34:00', '2025-10-21 11:31:11', NULL);

-- --------------------------------------------------------

--
-- Struktura tabele `naloga_ucenec`
--

CREATE TABLE `naloga_ucenec` (
  `id_naloga_ucenec` int(10) UNSIGNED NOT NULL,
  `id_naloga_ucitelj` int(10) UNSIGNED NOT NULL,
  `id_ucenec` int(10) UNSIGNED NOT NULL,
  `ime_datoteke` varchar(255) NOT NULL,
  `pot_na_strezniku` varchar(500) NOT NULL,
  `datum_oddaje` date NOT NULL,
  `status_naloge` enum('oddano','sprejeto','zavrnjeno') DEFAULT 'oddano'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabele `naloga_ucitelj`
--

CREATE TABLE `naloga_ucitelj` (
  `id_naloga_ucitelj` int(10) UNSIGNED NOT NULL,
  `id_ucitelj` int(10) UNSIGNED NOT NULL,
  `id_predmet` int(10) UNSIGNED NOT NULL,
  `naslov` varchar(255) NOT NULL,
  `opis` text DEFAULT NULL,
  `datum_objave` date NOT NULL,
  `rok_oddaje` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabele `oddaja`
--

CREATE TABLE `oddaja` (
  `id_oddaja` int(10) UNSIGNED NOT NULL,
  `id_naloga` int(10) UNSIGNED NOT NULL,
  `id_ucenec` int(10) UNSIGNED NOT NULL,
  `datum_oddaje` datetime NOT NULL DEFAULT current_timestamp(),
  `besedilo_oddaje` text DEFAULT NULL,
  `pot_na_strezniku` varchar(500) DEFAULT NULL,
  `status` enum('Oddano','Prepozno','Ocenjeno') NOT NULL DEFAULT 'Oddano',
  `ocena` varchar(10) DEFAULT NULL,
  `komentar_ucitelj` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Odloži podatke za tabelo `oddaja`
--

INSERT INTO `oddaja` (`id_oddaja`, `id_naloga`, `id_ucenec`, `datum_oddaje`, `besedilo_oddaje`, `pot_na_strezniku`, `status`, `ocena`, `komentar_ucitelj`) VALUES
(4, 6, 142, '2025-10-21 08:07:57', 'reseno x = 9', NULL, 'Ocenjeno', '5', 'super'),
(5, 7, 135, '2025-10-21 08:23:36', '1 nidsad je zdaj boljše', NULL, 'Ocenjeno', '4', 'dad'),
(6, 7, 142, '2025-10-21 08:45:14', 'dsad', NULL, 'Oddano', NULL, NULL),
(7, 9, 135, '2025-10-21 10:51:51', 'dad', NULL, 'Oddano', NULL, NULL),
(8, 10, 135, '2025-10-21 11:09:10', 'dadasd', NULL, 'Oddano', NULL, NULL),
(9, 12, 135, '2025-10-21 11:30:37', 'dsadadads', NULL, 'Oddano', '1', '');

-- --------------------------------------------------------

--
-- Struktura tabele `predmet`
--

CREATE TABLE `predmet` (
  `id_predmet` int(10) UNSIGNED NOT NULL,
  `ime_predmeta` varchar(100) NOT NULL,
  `opis` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Odloži podatke za tabelo `predmet`
--

INSERT INTO `predmet` (`id_predmet`, `ime_predmeta`, `opis`) VALUES
(11, 'Matematika', 'Matematika in funkcije'),
(12, 'Slovenščina', 'Jezik in književnost'),
(13, 'Angleščina', 'Angleški jezik'),
(14, 'RPR', 'Računalniški praktikum'),
(15, 'SMV', 'Strojništvo in mehanika'),
(16, 'NUP', 'Naravoslovni projekti'),
(17, 'ŠVZ', 'Športna vzgoja');

-- --------------------------------------------------------

--
-- Struktura tabele `ucenec_predmet`
--

CREATE TABLE `ucenec_predmet` (
  `id_ucenec` int(10) UNSIGNED NOT NULL,
  `id_predmet` int(10) UNSIGNED NOT NULL,
  `id_ucitelj` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Odloži podatke za tabelo `ucenec_predmet`
--

INSERT INTO `ucenec_predmet` (`id_ucenec`, `id_predmet`, `id_ucitelj`) VALUES
(135, 11, 121),
(136, 11, 121),
(138, 11, 121),
(141, 11, 121),
(142, 11, 121),
(142, 12, 123),
(135, 12, 124),
(138, 12, 124),
(135, 13, 125),
(136, 13, 125),
(142, 13, 125),
(135, 15, 127),
(135, 16, 127),
(136, 14, 127),
(136, 16, 127),
(136, 15, 129),
(138, 16, 130),
(135, 17, 131),
(138, 17, 131);

-- --------------------------------------------------------

--
-- Struktura tabele `ucenec_urnik`
--

CREATE TABLE `ucenec_urnik` (
  `id_ucenec` int(10) UNSIGNED NOT NULL,
  `id_predmet` int(10) UNSIGNED NOT NULL,
  `id_ucitelj` int(10) UNSIGNED NOT NULL,
  `dan` enum('Ponedeljek','Torek','Sreda','Četrtek','Petek') NOT NULL,
  `ura` tinyint(3) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabele `ucitelj_predmet`
--

CREATE TABLE `ucitelj_predmet` (
  `id_ucitelj` int(10) UNSIGNED NOT NULL,
  `id_predmet` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Odloži podatke za tabelo `ucitelj_predmet`
--

INSERT INTO `ucitelj_predmet` (`id_ucitelj`, `id_predmet`) VALUES
(121, 11),
(122, 11),
(123, 12),
(124, 12),
(125, 13),
(126, 13),
(127, 14),
(127, 15),
(127, 16),
(128, 14),
(129, 15),
(130, 16),
(131, 17),
(132, 17);

-- --------------------------------------------------------

--
-- Struktura tabele `uporabnik`
--

CREATE TABLE `uporabnik` (
  `id_uporabnik` int(10) UNSIGNED NOT NULL,
  `ime` varchar(50) NOT NULL,
  `priimek` varchar(50) NOT NULL,
  `mesto` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `osebni_email` varchar(255) DEFAULT NULL,
  `geslo` varchar(255) NOT NULL,
  `vloga` enum('admin','ucitelj','ucenec') NOT NULL,
  `status` enum('pending','active','rejected') NOT NULL DEFAULT 'pending',
  `kraj` varchar(100) DEFAULT NULL,
  `icona_profila` varchar(255) DEFAULT NULL,
  `prvi_vpis` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Odloži podatke za tabelo `uporabnik`
--

INSERT INTO `uporabnik` (`id_uporabnik`, `ime`, `priimek`, `mesto`, `email`, `osebni_email`, `geslo`, `vloga`, `status`, `kraj`, `icona_profila`, `prvi_vpis`) VALUES
(121, 'Helena', 'Viher', NULL, 'viher@sola.si', NULL, '$2y$10$0X4LmskLUHyKO1HxEzMX0ug7MNRfLpgh8oA4NGM6t/HKBMf2qTIiu', 'ucitelj', 'active', NULL, NULL, 1),
(122, 'Tajda', 'Remic', NULL, 'remic@sola.si', NULL, '$2y$10$0X4LmskLUHyKO1HxEzMX0ug7MNRfLpgh8oA4NGM6t/HKBMf2qTIiu', 'ucitelj', 'active', NULL, NULL, 1),
(123, 'Valentina', 'Hrastnik', NULL, 'hrastnik@sola.si', NULL, '$2y$10$0X4LmskLUHyKO1HxEzMX0ug7MNRfLpgh8oA4NGM6t/HKBMf2qTIiu', 'ucitelj', 'active', NULL, NULL, 1),
(124, 'Katja', 'Kolar', NULL, 'kolar@sola.si', NULL, '$2y$10$0X4LmskLUHyKO1HxEzMX0ug7MNRfLpgh8oA4NGM6t/HKBMf2qTIiu', 'ucitelj', 'active', NULL, NULL, 1),
(125, 'Rosana', 'Breznik', NULL, 'breznik@sola.si', NULL, '$2y$10$0X4LmskLUHyKO1HxEzMX0ug7MNRfLpgh8oA4NGM6t/HKBMf2qTIiu', 'ucitelj', 'active', NULL, NULL, 1),
(126, 'Eva', 'Boh', NULL, 'boh@sola.si', NULL, '$2y$10$0X4LmskLUHyKO1HxEzMX0ug7MNRfLpgh8oA4NGM6t/HKBMf2qTIiu', 'ucitelj', 'active', NULL, NULL, 1),
(127, 'Borut', 'Slemenšek', NULL, 'slemensek@sola.si', NULL, '$2y$10$0X4LmskLUHyKO1HxEzMX0ug7MNRfLpgh8oA4NGM6t/HKBMf2qTIiu', 'ucitelj', 'active', NULL, NULL, 1),
(128, 'Jaka', 'Koren', NULL, 'koren@sola.si', NULL, '$2y$10$0X4LmskLUHyKO1HxEzMX0ug7MNRfLpgh8oA4NGM6t/HKBMf2qTIiu', 'ucitelj', 'active', NULL, NULL, 1),
(129, 'Andraž', 'Pušnik', NULL, 'pusnik@sola.si', NULL, '$2y$10$0X4LmskLUHyKO1HxEzMX0ug7MNRfLpgh8oA4NGM6t/HKBMf2qTIiu', 'ucitelj', 'active', NULL, NULL, 1),
(130, 'Boštjan', 'Lubej', NULL, 'lubej@sola.si', NULL, '$2y$10$0X4LmskLUHyKO1HxEzMX0ug7MNRfLpgh8oA4NGM6t/HKBMf2qTIiu', 'ucitelj', 'active', NULL, NULL, 1),
(131, 'Matija', 'Lukner', NULL, 'lukner@sola.si', NULL, '$2y$10$0X4LmskLUHyKO1HxEzMX0ug7MNRfLpgh8oA4NGM6t/HKBMf2qTIiu', 'ucitelj', 'active', NULL, NULL, 1),
(132, 'Sara', 'Padarič', NULL, 'padaric@sola.si', NULL, '$2y$10$0X4LmskLUHyKO1HxEzMX0ug7MNRfLpgh8oA4NGM6t/HKBMf2qTIiu', 'ucitelj', 'active', NULL, NULL, 1),
(133, 'Ana', 'Novak', NULL, 'ana.novak@ucenec.si', NULL, '$2y$10$0X4LmskLUHyKO1HxEzMX0ug7MNRfLpgh8oA4NGM6t/HKBMf2qTIiu', 'ucenec', 'active', NULL, NULL, 0),
(134, 'Marko', 'Kovač', NULL, 'marko.kovac@ucenec.si', NULL, '$2y$10$0X4LmskLUHyKO1HxEzMX0ug7MNRfLpgh8oA4NGM6t/HKBMf2qTIiu', 'ucenec', 'active', NULL, NULL, 1),
(135, 'Tina', 'Horvat', NULL, 'tina.horvat@ucenec.si', NULL, '$2y$10$0X4LmskLUHyKO1HxEzMX0ug7MNRfLpgh8oA4NGM6t/HKBMf2qTIiu', 'ucenec', 'active', NULL, NULL, 0),
(136, 'Luka', 'Zupan', NULL, 'luka.zupan@ucenec.si', NULL, '$2y$10$0X4LmskLUHyKO1HxEzMX0ug7MNRfLpgh8oA4NGM6t/HKBMf2qTIiu', 'ucenec', 'active', NULL, NULL, 0),
(137, 'Petra', 'Mlakar', NULL, 'petra.mlakar@ucenec.si', NULL, '$2y$10$0X4LmskLUHyKO1HxEzMX0ug7MNRfLpgh8oA4NGM6t/HKBMf2qTIiu', 'ucenec', 'active', NULL, NULL, 1),
(138, 'Nejc', 'Potočnik', NULL, 'nejc.potocnik@ucenec.si', NULL, '$2y$10$0X4LmskLUHyKO1HxEzMX0ug7MNRfLpgh8oA4NGM6t/HKBMf2qTIiu', 'ucenec', 'active', NULL, NULL, 0),
(139, 'Maja', 'Vidmar', NULL, 'maja.vidmar@ucenec.si', NULL, '$2y$10$0X4LmskLUHyKO1HxEzMX0ug7MNRfLpgh8oA4NGM6t/HKBMf2qTIiu', 'ucenec', 'active', NULL, NULL, 1),
(140, 'Jure', 'Bizjak', NULL, 'jure.bizjak@ucenec.si', NULL, '$2y$10$0X4LmskLUHyKO1HxEzMX0ug7MNRfLpgh8oA4NGM6t/HKBMf2qTIiu', 'ucenec', 'active', NULL, NULL, 1),
(141, 'Klara', 'Golob', NULL, 'klara.golob@ucenec.si', NULL, '$2y$10$0X4LmskLUHyKO1HxEzMX0ug7MNRfLpgh8oA4NGM6t/HKBMf2qTIiu', 'ucenec', 'active', NULL, NULL, 1),
(142, 'Žiga', 'Kranjc', NULL, 'ziga.kranjc@ucenec.si', NULL, '$2y$10$0X4LmskLUHyKO1HxEzMX0ug7MNRfLpgh8oA4NGM6t/HKBMf2qTIiu', 'ucenec', 'active', NULL, NULL, 0),
(143, 'Super', 'Admin', NULL, 'admin@skola.si', NULL, '$2y$10$202casgkbNfhi2JwHaNfeOOTbaDHk.Qjj0DpIpqleQas0/kC14y06', 'admin', 'active', NULL, NULL, 0),
(144, 'sada', 'dasdasdsa', 'sadadsadasd', 'ddasdasd@gmail.com', NULL, '$2y$10$0X4LmskLUHyKO1HxEzMX0ug7MNRfLpgh8oA4NGM6t/HKBMf2qTIiu', 'ucenec', 'active', NULL, NULL, 1),
(145, 'Nik', 'Gorenjec', 'Radece', 'nidadadsa@gmail.com', NULL, '$2y$10$Is8c6i3/y9BPYuhQRc6YMup2dRx6w2owH/mQTJLsYPajNgvgLzowS', 'ucenec', 'active', NULL, NULL, 1),
(146, 'Matija', 'Hrastnik', 'Ljubečna', 'matija.hrastnik@sola.si', NULL, '$2y$10$WjCAagQeaU6GP0aHFFUsde3d4iVTQTFapaOb7.MZoU2AMDrfE8Biu', 'ucenec', 'active', NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Struktura tabele `urnik`
--

CREATE TABLE `urnik` (
  `id_urnik` int(10) UNSIGNED NOT NULL,
  `id_ucitelj` int(10) UNSIGNED NOT NULL,
  `id_predmet` int(10) UNSIGNED NOT NULL,
  `dan` varchar(20) NOT NULL,
  `ura` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Odloži podatke za tabelo `urnik`
--

INSERT INTO `urnik` (`id_urnik`, `id_ucitelj`, `id_predmet`, `dan`, `ura`) VALUES
(13, 121, 11, 'Ponedeljek', 1),
(14, 124, 12, 'Ponedeljek', 2),
(15, 128, 14, 'Ponedeljek', 3),
(16, 131, 17, 'Ponedeljek', 4),
(17, 125, 13, 'Ponedeljek', 5),
(18, 130, 16, 'Ponedeljek', 6),
(19, 122, 11, 'Torek', 1),
(20, 123, 12, 'Torek', 2),
(21, 129, 15, 'Torek', 3),
(22, 126, 13, 'Torek', 4),
(23, 132, 17, 'Torek', 5),
(24, 127, 14, 'Torek', 6),
(25, 121, 11, 'Sreda', 1),
(26, 125, 13, 'Sreda', 2),
(27, 123, 12, 'Sreda', 3),
(28, 127, 16, 'Sreda', 4),
(29, 127, 15, 'Sreda', 5),
(30, 131, 17, 'Sreda', 6),
(31, 124, 12, 'Četrtek', 1),
(32, 126, 13, 'Četrtek', 2),
(33, 122, 11, 'Četrtek', 3),
(34, 128, 14, 'Četrtek', 4),
(35, 129, 15, 'Četrtek', 5),
(36, 132, 17, 'Četrtek', 6),
(37, 121, 11, 'Petek', 1),
(38, 123, 12, 'Petek', 2),
(39, 125, 13, 'Petek', 3),
(40, 127, 14, 'Petek', 4),
(41, 130, 16, 'Petek', 5),
(42, 131, 17, 'Petek', 6);

--
-- Indeksi zavrženih tabel
--

--
-- Indeksi tabele `gradivo`
--
ALTER TABLE `gradivo`
  ADD PRIMARY KEY (`id_gradivo`),
  ADD KEY `id_ucitelj` (`id_ucitelj`),
  ADD KEY `id_predmet` (`id_predmet`);

--
-- Indeksi tabele `naloga`
--
ALTER TABLE `naloga`
  ADD PRIMARY KEY (`id_naloga`),
  ADD KEY `id_ucitelj` (`id_ucitelj`),
  ADD KEY `id_predmet` (`id_predmet`);

--
-- Indeksi tabele `naloga_ucenec`
--
ALTER TABLE `naloga_ucenec`
  ADD PRIMARY KEY (`id_naloga_ucenec`),
  ADD KEY `id_naloga_ucitelj` (`id_naloga_ucitelj`),
  ADD KEY `id_ucenec` (`id_ucenec`);

--
-- Indeksi tabele `naloga_ucitelj`
--
ALTER TABLE `naloga_ucitelj`
  ADD PRIMARY KEY (`id_naloga_ucitelj`),
  ADD KEY `id_ucitelj` (`id_ucitelj`),
  ADD KEY `id_predmet` (`id_predmet`);

--
-- Indeksi tabele `oddaja`
--
ALTER TABLE `oddaja`
  ADD PRIMARY KEY (`id_oddaja`),
  ADD UNIQUE KEY `idx_naloga_ucenec` (`id_naloga`,`id_ucenec`),
  ADD KEY `id_ucenec` (`id_ucenec`);

--
-- Indeksi tabele `predmet`
--
ALTER TABLE `predmet`
  ADD PRIMARY KEY (`id_predmet`);

--
-- Indeksi tabele `ucenec_predmet`
--
ALTER TABLE `ucenec_predmet`
  ADD PRIMARY KEY (`id_ucenec`,`id_predmet`),
  ADD KEY `id_predmet` (`id_predmet`),
  ADD KEY `fk_ucitelj` (`id_ucitelj`);

--
-- Indeksi tabele `ucenec_urnik`
--
ALTER TABLE `ucenec_urnik`
  ADD PRIMARY KEY (`id_ucenec`,`id_predmet`,`id_ucitelj`,`dan`,`ura`),
  ADD KEY `id_predmet` (`id_predmet`),
  ADD KEY `id_ucitelj` (`id_ucitelj`);

--
-- Indeksi tabele `ucitelj_predmet`
--
ALTER TABLE `ucitelj_predmet`
  ADD PRIMARY KEY (`id_ucitelj`,`id_predmet`),
  ADD KEY `id_predmet` (`id_predmet`);

--
-- Indeksi tabele `uporabnik`
--
ALTER TABLE `uporabnik`
  ADD PRIMARY KEY (`id_uporabnik`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indeksi tabele `urnik`
--
ALTER TABLE `urnik`
  ADD PRIMARY KEY (`id_urnik`),
  ADD KEY `id_ucitelj` (`id_ucitelj`),
  ADD KEY `id_predmet` (`id_predmet`);

--
-- AUTO_INCREMENT zavrženih tabel
--

--
-- AUTO_INCREMENT tabele `gradivo`
--
ALTER TABLE `gradivo`
  MODIFY `id_gradivo` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT tabele `naloga`
--
ALTER TABLE `naloga`
  MODIFY `id_naloga` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT tabele `naloga_ucenec`
--
ALTER TABLE `naloga_ucenec`
  MODIFY `id_naloga_ucenec` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT tabele `naloga_ucitelj`
--
ALTER TABLE `naloga_ucitelj`
  MODIFY `id_naloga_ucitelj` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT tabele `oddaja`
--
ALTER TABLE `oddaja`
  MODIFY `id_oddaja` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT tabele `predmet`
--
ALTER TABLE `predmet`
  MODIFY `id_predmet` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT tabele `uporabnik`
--
ALTER TABLE `uporabnik`
  MODIFY `id_uporabnik` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=147;

--
-- AUTO_INCREMENT tabele `urnik`
--
ALTER TABLE `urnik`
  MODIFY `id_urnik` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- Omejitve tabel za povzetek stanja
--

--
-- Omejitve za tabelo `gradivo`
--
ALTER TABLE `gradivo`
  ADD CONSTRAINT `gradivo_ibfk_1` FOREIGN KEY (`id_ucitelj`) REFERENCES `uporabnik` (`id_uporabnik`),
  ADD CONSTRAINT `gradivo_ibfk_2` FOREIGN KEY (`id_predmet`) REFERENCES `predmet` (`id_predmet`);

--
-- Omejitve za tabelo `naloga`
--
ALTER TABLE `naloga`
  ADD CONSTRAINT `naloga_ibfk_1` FOREIGN KEY (`id_ucitelj`) REFERENCES `uporabnik` (`id_uporabnik`),
  ADD CONSTRAINT `naloga_ibfk_2` FOREIGN KEY (`id_predmet`) REFERENCES `predmet` (`id_predmet`);

--
-- Omejitve za tabelo `naloga_ucenec`
--
ALTER TABLE `naloga_ucenec`
  ADD CONSTRAINT `naloga_ucenec_ibfk_1` FOREIGN KEY (`id_naloga_ucitelj`) REFERENCES `naloga_ucitelj` (`id_naloga_ucitelj`),
  ADD CONSTRAINT `naloga_ucenec_ibfk_2` FOREIGN KEY (`id_ucenec`) REFERENCES `uporabnik` (`id_uporabnik`);

--
-- Omejitve za tabelo `naloga_ucitelj`
--
ALTER TABLE `naloga_ucitelj`
  ADD CONSTRAINT `naloga_ucitelj_ibfk_1` FOREIGN KEY (`id_ucitelj`) REFERENCES `uporabnik` (`id_uporabnik`),
  ADD CONSTRAINT `naloga_ucitelj_ibfk_2` FOREIGN KEY (`id_predmet`) REFERENCES `predmet` (`id_predmet`);

--
-- Omejitve za tabelo `oddaja`
--
ALTER TABLE `oddaja`
  ADD CONSTRAINT `oddaja_ibfk_1` FOREIGN KEY (`id_naloga`) REFERENCES `naloga` (`id_naloga`),
  ADD CONSTRAINT `oddaja_ibfk_2` FOREIGN KEY (`id_ucenec`) REFERENCES `uporabnik` (`id_uporabnik`);

--
-- Omejitve za tabelo `ucenec_predmet`
--
ALTER TABLE `ucenec_predmet`
  ADD CONSTRAINT `fk_ucitelj` FOREIGN KEY (`id_ucitelj`) REFERENCES `uporabnik` (`id_uporabnik`),
  ADD CONSTRAINT `ucenec_predmet_ibfk_1` FOREIGN KEY (`id_ucenec`) REFERENCES `uporabnik` (`id_uporabnik`),
  ADD CONSTRAINT `ucenec_predmet_ibfk_2` FOREIGN KEY (`id_predmet`) REFERENCES `predmet` (`id_predmet`);

--
-- Omejitve za tabelo `ucenec_urnik`
--
ALTER TABLE `ucenec_urnik`
  ADD CONSTRAINT `ucenec_urnik_ibfk_1` FOREIGN KEY (`id_ucenec`) REFERENCES `uporabnik` (`id_uporabnik`),
  ADD CONSTRAINT `ucenec_urnik_ibfk_2` FOREIGN KEY (`id_predmet`) REFERENCES `predmet` (`id_predmet`),
  ADD CONSTRAINT `ucenec_urnik_ibfk_3` FOREIGN KEY (`id_ucitelj`) REFERENCES `uporabnik` (`id_uporabnik`);

--
-- Omejitve za tabelo `ucitelj_predmet`
--
ALTER TABLE `ucitelj_predmet`
  ADD CONSTRAINT `ucitelj_predmet_ibfk_1` FOREIGN KEY (`id_ucitelj`) REFERENCES `uporabnik` (`id_uporabnik`),
  ADD CONSTRAINT `ucitelj_predmet_ibfk_2` FOREIGN KEY (`id_predmet`) REFERENCES `predmet` (`id_predmet`);

--
-- Omejitve za tabelo `urnik`
--
ALTER TABLE `urnik`
  ADD CONSTRAINT `urnik_ibfk_1` FOREIGN KEY (`id_ucitelj`) REFERENCES `uporabnik` (`id_uporabnik`),
  ADD CONSTRAINT `urnik_ibfk_2` FOREIGN KEY (`id_predmet`) REFERENCES `predmet` (`id_predmet`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
