-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Gostitelj: 127.0.0.1
-- Čas nastanka: 09. sep 2025 ob 11.04
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
  `id_predmet` int(10) UNSIGNED NOT NULL
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
  `email` varchar(100) NOT NULL,
  `geslo` varchar(255) NOT NULL,
  `vloga` enum('admin','ucitelj','ucenec') NOT NULL,
  `kraj` varchar(100) DEFAULT NULL,
  `icona_profila` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Odloži podatke za tabelo `uporabnik`
--

INSERT INTO `uporabnik` (`id_uporabnik`, `ime`, `priimek`, `email`, `geslo`, `vloga`, `kraj`, `icona_profila`) VALUES
(121, 'Helena', 'Viher', 'viher@sola.si', 'geslo', 'ucitelj', NULL, NULL),
(122, 'Tajda', 'Remic', 'remic@sola.si', 'geslo', 'ucitelj', NULL, NULL),
(123, 'Valentina', 'Hrastnik', 'hrastnik@sola.si', 'geslo', 'ucitelj', NULL, NULL),
(124, 'Katja', 'Kolar', 'kolar@sola.si', 'geslo', 'ucitelj', NULL, NULL),
(125, 'Rosana', 'Breznik', 'breznik@sola.si', 'geslo', 'ucitelj', NULL, NULL),
(126, 'Eva', 'Boh', 'boh@sola.si', 'geslo', 'ucitelj', NULL, NULL),
(127, 'Borut', 'Slemenšek', 'slemensek@sola.si', 'geslo', 'ucitelj', NULL, NULL),
(128, 'Jaka', 'Koren', 'koren@sola.si', 'geslo', 'ucitelj', NULL, NULL),
(129, 'Andraž', 'Pušnik', 'pusnik@sola.si', 'geslo', 'ucitelj', NULL, NULL),
(130, 'Boštjan', 'Lubej', 'lubej@sola.si', 'geslo', 'ucitelj', NULL, NULL),
(131, 'Matija', 'Lukner', 'lukner@sola.si', 'geslo', 'ucitelj', NULL, NULL),
(132, 'Sara', 'Padarič', 'padaric@sola.si', 'geslo', 'ucitelj', NULL, NULL);

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
-- Indeksi tabele `predmet`
--
ALTER TABLE `predmet`
  ADD PRIMARY KEY (`id_predmet`);

--
-- Indeksi tabele `ucenec_predmet`
--
ALTER TABLE `ucenec_predmet`
  ADD PRIMARY KEY (`id_ucenec`,`id_predmet`),
  ADD KEY `id_predmet` (`id_predmet`);

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
-- AUTO_INCREMENT tabele `predmet`
--
ALTER TABLE `predmet`
  MODIFY `id_predmet` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT tabele `uporabnik`
--
ALTER TABLE `uporabnik`
  MODIFY `id_uporabnik` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=133;

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
-- Omejitve za tabelo `ucenec_predmet`
--
ALTER TABLE `ucenec_predmet`
  ADD CONSTRAINT `ucenec_predmet_ibfk_1` FOREIGN KEY (`id_ucenec`) REFERENCES `uporabnik` (`id_uporabnik`),
  ADD CONSTRAINT `ucenec_predmet_ibfk_2` FOREIGN KEY (`id_predmet`) REFERENCES `predmet` (`id_predmet`);

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
