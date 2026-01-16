-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 16, 2026 at 10:43 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `excel_data`
--

-- --------------------------------------------------------

--
-- Table structure for table `excel_data`
--

CREATE TABLE `excel_data` (
  `id` int(11) NOT NULL,
  `tanggal_masuk` date NOT NULL,
  `nomor_aju` varchar(30) NOT NULL,
  `nomor_pendaftaran` varchar(50) DEFAULT NULL,
  `tanggal_dokumen` date DEFAULT NULL,
  `dokumen_pelengkap` varchar(255) DEFAULT NULL,
  `nama_customer` varchar(255) DEFAULT NULL,
  `jumlah_kemasan` varchar(50) DEFAULT NULL,
  `tipe_kemasan` varchar(50) DEFAULT NULL,
  `nomor_seri_barang` varchar(50) DEFAULT NULL,
  `kode_barang` varchar(100) DEFAULT NULL,
  `nama_item` varchar(255) DEFAULT NULL,
  `quantity_item` int(11) DEFAULT NULL,
  `satuan_barang` varchar(20) DEFAULT NULL,
  `netto` decimal(15,2) DEFAULT NULL,
  `bruto` decimal(15,2) DEFAULT NULL,
  `valuta` varchar(10) DEFAULT NULL,
  `cif` decimal(15,2) DEFAULT NULL,
  `ndpbm` int(11) DEFAULT NULL,
  `harga_penyerahan` decimal(15,2) DEFAULT NULL,
  `kode_tujuan_pengiriman` int(11) DEFAULT NULL,
  `tujuan_pengiriman` varchar(50) DEFAULT NULL,
  `is_fallback_bruto` tinyint(1) DEFAULT 0,
  `jenis_dokumen` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `excel_data`
--
ALTER TABLE `excel_data`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `excel_data`
--
ALTER TABLE `excel_data`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
