-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Gostitelj: 127.0.0.1
-- Čas nastanka: 10. nov 2025 ob 18.08
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
(14, 121, 11, 'fdsfds', 'dasdas', '2025-10-26 13:59:00', '2025-10-30 13:59:55', NULL),
(15, 121, 11, 'Nova Cerkev', '67', '2025-11-08 15:41:00', '2025-11-01 15:41:54', NULL),
(16, 121, 11, 'Daddadan', 'da imas vse mozganske celica', '2025-11-16 16:03:00', '2025-11-01 16:03:54', NULL),
(17, 121, 11, 'Hrastnik je legenta not', '67', '2025-11-16 19:09:00', '2025-11-02 19:09:37', NULL),
(18, 121, 11, 'Gotenjec', '56', '2025-11-19 21:03:00', '2025-11-03 21:03:28', NULL),
(19, 121, 11, 'Upanje', 'zabava', '2025-11-16 15:13:00', '2025-11-08 15:14:19', 'uploads/naloge/11_1762611259.jpeg'),
(20, 126, 13, 'Angleščina 3', 'Bla bla bla', '2025-11-28 16:01:00', '2025-11-08 16:01:11', NULL),
(21, 121, 11, 'Test', 'To je test', '2025-11-15 16:28:00', '2025-11-08 16:28:25', NULL),
(22, 121, 11, 'Zebra', 'muuuu', '2025-11-14 16:48:00', '2025-11-08 16:48:15', NULL);

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
  `podaljsan_rok` datetime DEFAULT NULL,
  `besedilo_oddaje` text DEFAULT NULL,
  `pot_na_strezniku` varchar(500) DEFAULT NULL,
  `status` enum('Oddano','Prepozno','Ocenjeno') NOT NULL DEFAULT 'Oddano',
  `ocena` varchar(10) DEFAULT NULL,
  `komentar_ucitelj` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Odloži podatke za tabelo `oddaja`
--

INSERT INTO `oddaja` (`id_oddaja`, `id_naloga`, `id_ucenec`, `datum_oddaje`, `podaljsan_rok`, `besedilo_oddaje`, `pot_na_strezniku`, `status`, `ocena`, `komentar_ucitelj`) VALUES
(4, 6, 142, '2025-10-21 08:07:57', NULL, 'reseno x = 9', NULL, 'Ocenjeno', '5', 'super'),
(5, 7, 135, '2025-10-21 08:23:36', NULL, '1 nidsad je zdaj boljše', NULL, 'Ocenjeno', '4', 'dad'),
(6, 7, 142, '2025-10-21 08:45:14', NULL, 'dsad', NULL, 'Ocenjeno', '5', '67'),
(7, 9, 135, '2025-10-21 10:51:51', NULL, 'dad', NULL, 'Oddano', '1', 'slabo to puncka moja'),
(8, 10, 135, '2025-10-21 11:09:10', NULL, 'dadasd', NULL, 'Oddano', '1', '67'),
(9, 12, 135, '2025-10-21 11:30:37', NULL, 'dsadadads', NULL, 'Oddano', '1', ''),
(10, 17, 135, '2025-11-02 19:09:54', NULL, '67', NULL, 'Ocenjeno', '4', '67'),
(11, 16, 135, '2025-11-03 19:45:05', NULL, 'ta naloga je oddana', NULL, 'Ocenjeno', '3', 'upam da dela'),
(12, 17, 142, '2025-11-03 20:12:16', NULL, '4545', NULL, 'Ocenjeno', '3', 'super'),
(13, 19, 135, '2025-11-08 15:15:28', NULL, '', 'uploads/oddaje/252ac5d5b551fa66_1762611328.jpg', 'Oddano', NULL, NULL),
(14, 21, 141, '2025-11-08 16:33:20', '2025-11-15 16:33:46', 'to je oddaja', NULL, '', '1', 'slabo'),
(15, 19, 141, '2025-11-08 16:34:32', NULL, 'bla bla bla', NULL, 'Ocenjeno', '3', ''),
(16, 20, 141, '2025-11-08 16:36:03', '2025-11-15 16:36:20', 'trst je nas', NULL, '', '1', ''),
(17, 22, 135, '2025-11-08 16:48:46', '2025-11-15 16:49:41', 'oddano', NULL, '', '1', ''),
(18, 18, 135, '2025-11-08 17:18:25', NULL, '3123', NULL, 'Oddano', NULL, NULL);

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
(133, 11, 121),
(134, 11, 121),
(135, 11, 121),
(136, 11, 121),
(137, 11, 121),
(138, 11, 121),
(139, 11, 121),
(141, 11, 121),
(142, 11, 121),
(139, 12, 123),
(141, 12, 123),
(142, 12, 123),
(135, 12, 124),
(138, 12, 124),
(133, 13, 125),
(135, 13, 125),
(136, 13, 125),
(139, 13, 125),
(141, 13, 125),
(142, 13, 125),
(134, 13, 126),
(137, 13, 126),
(141, 16, 126),
(134, 15, 127),
(135, 15, 127),
(135, 16, 127),
(136, 14, 127),
(136, 16, 127),
(137, 15, 127),
(141, 14, 127),
(133, 14, 128),
(133, 15, 129),
(136, 15, 129),
(139, 15, 129),
(138, 16, 130),
(134, 17, 131),
(135, 17, 131),
(137, 17, 131),
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
(121, 13),
(122, 11),
(123, 12),
(124, 12),
(125, 13),
(126, 11),
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
(121, 'Helena', 'Viher', NULL, 'viher@sola.si', NULL, '$2y$10$miU2yLHxkL/odT2kVBiGi.IUVZIXM3rWuWLGCyY6FiMURvp.PlHDO', 'ucitelj', 'active', NULL, 'slike/icona_121_1762612008.jpg', 0),
(122, 'Tajda', 'Remic', NULL, 'remic@sola.si', NULL, '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'active', NULL, NULL, 1),
(123, 'Valentina', 'Hrastnik', NULL, 'hrastnik@sola.si', NULL, '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'active', NULL, NULL, 1),
(124, 'Katja', 'Kolar', NULL, 'kolar@sola.si', NULL, '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'active', NULL, NULL, 1),
(125, 'Rosana', 'Breznik', NULL, 'breznik@sola.si', NULL, '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'active', NULL, NULL, 1),
(126, 'Eva', 'Boh', NULL, 'boh@sola.si', NULL, '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'active', NULL, NULL, 1),
(127, 'Borut', 'Slemenšek', NULL, 'slemensek@sola.si', NULL, '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'active', NULL, NULL, 1),
(128, 'Jaka', 'Koren', NULL, 'koren@sola.si', NULL, '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'active', NULL, NULL, 1),
(129, 'Andraž', 'Pušnik', NULL, 'pusnik@sola.si', NULL, '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'active', NULL, NULL, 1),
(130, 'Boštjan', 'Lubej', NULL, 'lubej@sola.si', NULL, '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'active', NULL, NULL, 1),
(131, 'Matija', 'Lukner', NULL, 'lukner@sola.si', NULL, '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'active', NULL, NULL, 1),
(132, 'Sara', 'Padarič', NULL, 'padaric@sola.si', NULL, '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'active', NULL, NULL, 1),
(133, 'Ana', 'Novak', NULL, 'ana.novak@ucenec.si', NULL, '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'active', NULL, NULL, 0),
(134, 'Marko', 'Kovač', NULL, 'marko.kovac@ucenec.si', NULL, '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'active', NULL, NULL, 0),
(135, 'Tina', 'Horvat', NULL, 'tina.horvat@ucenec.si', NULL, '$2y$10$oGsWAoidQ.brrt0GHMsrG.XBGJDhNsnCg5xJkZGi5iHYPA9BxUD.a', 'ucenec', 'active', NULL, 'slike/icona_135_1762199013.jpeg', 0),
(136, 'Luka', 'Zupan', NULL, 'luka.zupan@ucenec.si', NULL, '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'active', NULL, NULL, 0),
(137, 'Petra', 'Mlakar', NULL, 'petra.mlakar@ucenec.si', NULL, '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'active', NULL, NULL, 1),
(138, 'Nejc', 'Potočnik', NULL, 'nejc.potocnik@ucenec.si', NULL, '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'active', NULL, NULL, 0),
(139, 'Maja', 'Vidmar', NULL, 'maja.vidmar@ucenec.si', NULL, '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'active', NULL, NULL, 0),
(141, 'Klara', 'Golob', NULL, 'klara.golob@ucenec.si', NULL, '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'active', NULL, NULL, 0),
(142, 'Žiga', 'Kranjc', NULL, 'ziga.kranjc@ucenec.si', NULL, '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'active', NULL, NULL, 0),
(143, 'Super', 'Admin', NULL, 'admin@skola.si', NULL, '$2y$10$rdwYDWvKfdvNE4gN8Srp5eCl24tVh0fcPXv6o/kiP2M6Aw5qlj4aG', 'admin', 'active', NULL, NULL, 0),
(144, 'sada', 'dasdasdsa', 'sadadsadasd', 'ddasdasd@gmail.com', NULL, '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'active', NULL, NULL, 1),
(146, 'Matija', 'Hrastnik', 'Ljubečna', 'matija.hrastnik@sola.si', NULL, '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'active', NULL, NULL, 1),
(147, 'Kajetan', 'Legenda', 'Socka', 'kajetan.legenda@sola.si', NULL, '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'active', NULL, NULL, 1),
(148, 'Zebra', 'Kozmic', 'Celje', 'zebra.kozmic@sola.si', 'zebra.kozmic@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'active', NULL, 'slike/icona_148_1762614221.jpeg', 1),
(149, 'Ana', 'Novak', 'Ljubljana', 'ana.novak@sola.si', 'ana.novak@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'pending', NULL, NULL, 0),
(150, 'Borut', 'Kovač', 'Maribor', 'borut.kovac@sola.si', 'borut.k@yahoo.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'pending', NULL, NULL, 0),
(151, 'Cilka', 'Krajnc', 'Celje', 'cilka.krajnc@sola.si', 'cilka.k@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'pending', NULL, NULL, 0),
(152, 'David', 'Zupan', 'Koper', 'david.zupan@sola.si', 'david.z@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'pending', NULL, NULL, 0),
(153, 'Ema', 'Jovanović', 'Kranj', 'ema.jovanovic@sola.si', 'ema.j@hotmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'pending', NULL, NULL, 0),
(154, 'Filip', 'Erjavec', 'Novo Mesto', 'filip.erjavec@sola.si', 'filip.e@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'pending', NULL, NULL, 0),
(155, 'Gaja', 'Horvat', 'Velenje', 'gaja.horvat@sola.si', 'gaja.h@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'pending', NULL, NULL, 0),
(156, 'Hugo', 'Perko', 'Jesenice', 'hugo.perko@sola.si', 'hugo.p@yahoo.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'pending', NULL, NULL, 0),
(157, 'Iza', 'Bizjak', 'Nova Gorica', 'iza.bizjak@sola.si', 'iza.b@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'pending', NULL, NULL, 0),
(158, 'Jan', 'Miler', 'Domžale', 'jan.miler@sola.si', 'jan.m@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'pending', NULL, NULL, 0),
(159, 'Klara', 'Rupnik', 'Izola', 'klara.rupnik@sola.si', 'klara.r@hotmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'pending', NULL, NULL, 0),
(160, 'Luka', 'Pahor', 'Piran', 'luka.pahor@sola.si', 'luka.p@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'pending', NULL, NULL, 0),
(161, 'Maja', 'Leban', 'Portorož', 'maja.leban@sola.si', 'maja.l@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'pending', NULL, NULL, 0),
(162, 'Nejc', 'Kolenc', 'Radovljica', 'nejc.kolenc@sola.si', 'nejc.k@yahoo.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'pending', NULL, NULL, 0),
(163, 'Olga', 'Volk', 'Škofja Loka', 'olga.volk@sola.si', 'olga.v@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'pending', NULL, NULL, 0),
(164, 'Peter', 'Kokalj', 'Tržič', 'peter.kokalj@sola.si', 'peter.k@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'pending', NULL, NULL, 0),
(165, 'Rebeka', 'Turk', 'Ajdovščina', 'rebeka.turk@sola.si', 'rebeka.t@hotmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'pending', NULL, NULL, 0),
(166, 'Samo', 'Pintar', 'Tolmin', 'samo.pintar@sola.si', 'samo.p@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'pending', NULL, NULL, 0),
(167, 'Tanja', 'Hribar', 'Sežana', 'tanja.hribar@sola.si', 'tanja.h@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'pending', NULL, NULL, 0),
(168, 'Urban', 'Omerzel', 'Litija', 'urban.omerzel@sola.si', 'urban.o@yahoo.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'pending', NULL, NULL, 0),
(169, 'Valerija', 'Šuštar', 'Logatec', 'valerija.sustar@sola.si', 'valerija.s@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'pending', NULL, NULL, 0),
(170, 'Žiga', 'Petrovič', 'Grosuplje', 'ziga.petrovic@sola.si', 'ziga.p@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'pending', NULL, NULL, 0),
(171, 'Tilen', 'Sitar', 'Vrhnika', 'tilen.sitar@sola.si', 'tilen.s@hotmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'pending', NULL, NULL, 0),
(172, 'Nika', 'Kos', 'Zagorje ob Savi', 'nika.kos@sola.si', 'nika.k@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'pending', NULL, NULL, 0),
(173, 'Miran', 'Rajk', 'Rogaška Slatina', 'miran.rajk@sola.si', 'miran.r@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'pending', NULL, NULL, 0),
(174, 'Saša', 'Butina', 'Brežice', 'sasa.butina@sola.si', 'sasa.b@yahoo.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'active', NULL, NULL, 0),
(175, 'Denis', 'Cesar', 'Črnomelj', 'denis.cesar@sola.si', 'denis.c@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'pending', NULL, NULL, 0),
(176, 'Erika', 'Potočnik', 'Kočevje', 'erika.potocnik@sola.si', 'erika.p@hotmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'pending', NULL, NULL, 0),
(177, 'Igor', 'Oblak', 'Lendava', 'igor.oblak@sola.si', 'igor.o@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'pending', NULL, NULL, 0),
(178, 'Zala', 'Mihelič', 'Murska Sobota', 'zala.mihelic@sola.si', 'zala.m@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucitelj', 'pending', NULL, NULL, 0),
(179, 'Marko', 'Hrovat', 'Ljubljana', 'marko.hrovat@sola.si', 'marko.h@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(180, 'Neža', 'Bizjak', 'Maribor', 'neza.bizjak@sola.si', 'neza.b@yahoo.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(181, 'Ožbej', 'Kolar', 'Celje', 'ozbej.kolar@sola.si', 'ozbej.k@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(182, 'Pia', 'Primožič', 'Koper', 'pia.primozic@sola.si', 'pia.p@hotmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(183, 'Rok', 'Novak', 'Kranj', 'rok.novak@sola.si', 'rok.n@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(184, 'Sara', 'Horvat', 'Novo Mesto', 'sara.horvat@sola.si', 'sara.h@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(185, 'Tadej', 'Zupančič', 'Velenje', 'tadej.zupancic@sola.si', 'tadej.z@yahoo.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(186, 'Urška', 'Kovač', 'Jesenice', 'urska.kovac@sola.si', 'urska.k@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(187, 'Vid', 'Krajnc', 'Nova Gorica', 'vid.krajnc@sola.si', 'vid.k@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(188, 'Zala', 'Erjavec', 'Domžale', 'zala.erjavec@sola.si', 'zala.e@hotmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(189, 'Aljaž', 'Jovanović', 'Izola', 'aljaz.jovanovic@sola.si', 'aljaz.j@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(190, 'Barbara', 'Perko', 'Piran', 'barbara.perko@sola.si', 'barbara.p@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(191, 'Črt', 'Miler', 'Portorož', 'crt.miler@sola.si', 'crt.m@yahoo.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(192, 'Darja', 'Rupnik', 'Radovljica', 'darja.rupnik@sola.si', 'darja.r@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(193, 'Erik', 'Pahor', 'Škofja Loka', 'erik.pahor@sola.si', 'erik.p@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(194, 'Eva', 'Leban', 'Tržič', 'eva.leban@sola.si', 'eva.l@hotmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(195, 'Gašper', 'Kolenc', 'Ajdovščina', 'gasper.kolenc@sola.si', 'gasper.k@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(196, 'Hana', 'Volk', 'Tolmin', 'hana.volk@sola.si', 'hana.v@yahoo.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(197, 'Ines', 'Kokalj', 'Sežana', 'ines.kokalj@sola.si', 'ines.k@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(198, 'Jaka', 'Turk', 'Litija', 'jaka.turk@sola.si', 'jaka.t@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(199, 'Katja', 'Pintar', 'Logatec', 'katja.pintar@sola.si', 'katja.p@hotmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(200, 'Lan', 'Hribar', 'Grosuplje', 'lan.hribar@sola.si', 'lan.h@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(201, 'Lina', 'Omerzel', 'Vrhnika', 'lina.omerzel@sola.si', 'lina.o@yahoo.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(202, 'Maj', 'Šuštar', 'Zagorje ob Savi', 'maj.sustar@sola.si', 'maj.s@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(203, 'Mia', 'Petrovič', 'Rogaška Slatina', 'mia.petrovic@sola.si', 'mia.p@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(204, 'Nace', 'Sitar', 'Brežice', 'nace.sitar@sola.si', 'nace.s@hotmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(205, 'Nuša', 'Kos', 'Črnomelj', 'nusa.kos@sola.si', 'nusa.k@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(206, 'Oskar', 'Rajk', 'Kočevje', 'oskar.rajk@sola.si', 'oskar.r@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(207, 'Pika', 'Butina', 'Lendava', 'pika.butina@sola.si', 'pika.b@yahoo.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(208, 'Robi', 'Cesar', 'Murska Sobota', 'robi.cesar@sola.si', 'robi.c@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(209, 'Špela', 'Potočnik', 'Ptuj', 'spela.potocnik@sola.si', 'spela.p@hotmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(210, 'Tjaž', 'Oblak', 'Slovenj Gradec', 'tjaz.oblak@sola.si', 'tjaz.o@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(211, 'Ula', 'Mihelič', 'Jesenice', 'ula.mihelic@sola.si', 'ula.m@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(212, 'Vito', 'Podobnik', 'Šentjur', 'vito.podobnik@sola.si', 'vito.p@yahoo.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(213, 'Alina', 'Černe', 'Sevnica', 'alina.cerne@sola.si', 'alina.c@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(214, 'Blaž', 'Dvoršak', 'Žalec', 'blaz.dvorsak@sola.si', 'blaz.d@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(215, 'Nataša', 'Jager', 'Bled', 'natasa.jager@sola.si', 'natasa.j@hotmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(216, 'Rok', 'Korelc', 'Bovec', 'rok.korelc@sola.si', 'rok.korelc@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(217, 'Lara', 'Oven', 'Kranjska Gora', 'lara.oven@sola.si', 'lara.oven@yahoo.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(218, 'Matic', 'Pintarič', 'Postojna', 'matic.pintaric@sola.si', 'matic.p@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(219, 'Neja', 'Simčič', 'Izlake', 'neja.simcic@sola.si', 'neja.s@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(220, 'Urban', 'Slana', 'Vipava', 'urban.slana@sola.si', 'urban.s@hotmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(221, 'Vesna', 'Tomažič', 'Rakek', 'vesna.tomazic@sola.si', 'vesna.t@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(222, 'Zala', 'Zorc', 'Šmarje pri Jelšah', 'zala.zorc@sola.si', 'zala.z@yahoo.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(223, 'Žan', 'Žnidaršič', 'Ilirska Bistrica', 'zan.znidarsic@sola.si', 'zan.z@gmail.com', '$2y$10$GfzIqswjz02Pt05Fqa/Fl.abz7J.TY3p2Y/BaeMNGqm8gf/vKTTAq', 'ucenec', 'pending', NULL, NULL, 1),
(224, 'Neža', 'Uberto', 'Nova Cerkev', 'neza.uberto@sola.si', NULL, '$2y$10$CeQvUYqQ94u/EuCCQpmf6.nw.0UqrMRmtlDynhgeY/VbwB68dGg3a', 'ucenec', 'pending', NULL, NULL, 1),
(225, 'Joze', 'Koze', 'Socka', 'joze.koze@sola.si', 'email@gmail.com', '$2y$10$W5r2f8uFL2SejuXeHO2SoergPCwDB6FA8oPGEM7vTY4jrU48byOq.', 'ucenec', 'active', NULL, NULL, 1),
(226, 'Helena', 'Bor', 'nimaveze', 'helena.bor@sola.si', NULL, '$2y$10$Cj5/pV8cG0tVFowsD2cm0us56Xv3Yl2iEYnX.cAAT1mC9MshkfiDi', 'ucenec', '', NULL, NULL, 0);

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
  MODIFY `id_naloga` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

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
  MODIFY `id_oddaja` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT tabele `predmet`
--
ALTER TABLE `predmet`
  MODIFY `id_predmet` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT tabele `uporabnik`
--
ALTER TABLE `uporabnik`
  MODIFY `id_uporabnik` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=227;

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
