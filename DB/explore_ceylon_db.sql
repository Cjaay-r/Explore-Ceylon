-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 10, 2025 at 09:17 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `explore_ceylon_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `Booking_ID` int(11) NOT NULL,
  `F_Name` varchar(300) NOT NULL,
  `L_Name` varchar(300) NOT NULL,
  `Email` varchar(300) NOT NULL,
  `Phone_No` varchar(20) NOT NULL,
  `NIC_or_Paasport` varchar(50) NOT NULL,
  `Start_Date_Time` date NOT NULL,
  `End_Date_Time` date NOT NULL,
  `Pickup_Location` varchar(200) NOT NULL,
  `End_Location` varchar(200) NOT NULL,
  `Number_of_People` int(11) NOT NULL,
  `Booking_Type` enum('customize','Package') NOT NULL,
  `Guide_Preferences` tinyint(1) NOT NULL,
  `Status` enum('Pending','Confirmed','In_Progress','Completed','Cancelled') NOT NULL,
  `Completed_At` datetime NOT NULL,
  `Progress` varchar(100) NOT NULL,
  `Price` decimal(10,0) NOT NULL,
  `Payment_Method` enum('Cash','Online') NOT NULL,
  `Payment_Status` enum('Paid','Unpaid') NOT NULL,
  `Driver_earning` decimal(10,0) NOT NULL,
  `Guide_earning` decimal(10,0) NOT NULL,
  `User_ID` int(11) NOT NULL,
  `Driver_ID` int(11) NOT NULL,
  `Guide_ID` int(11) DEFAULT NULL,
  `Package_ID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`Booking_ID`, `F_Name`, `L_Name`, `Email`, `Phone_No`, `NIC_or_Paasport`, `Start_Date_Time`, `End_Date_Time`, `Pickup_Location`, `End_Location`, `Number_of_People`, `Booking_Type`, `Guide_Preferences`, `Status`, `Completed_At`, `Progress`, `Price`, `Payment_Method`, `Payment_Status`, `Driver_earning`, `Guide_earning`, `User_ID`, `Driver_ID`, `Guide_ID`, `Package_ID`) VALUES
(1, 'Ranil', 'Wickremesinghe', 'ranil@gmail.com', '0776543845', '4646434235346', '2025-10-10', '2025-10-11', 'Kandy, Sri Lanka', 'Kurunegala, Sri Lanka', 3, 'Package', 1, 'Pending', '0000-00-00 00:00:00', '', 40000, 'Online', 'Unpaid', 0, 0, 7, 5, 2, 28),
(9, 'shshsh', 'shshsh', 'mahinda@gmail.com', '0776543845', '4646434235346', '2025-10-16', '2025-10-17', 'Kandy, Sri Lanka', 'Kurunegala, Sri Lanka', 3, 'Package', 1, 'Pending', '0000-00-00 00:00:00', '', 40000, 'Online', 'Unpaid', 0, 0, 22, 5, 2, 28);

-- --------------------------------------------------------

--
-- Table structure for table `booking_destinations`
--

CREATE TABLE `booking_destinations` (
  `Destination_ID` int(11) NOT NULL,
  `Day` int(11) NOT NULL,
  `Destination` varchar(300) NOT NULL,
  `Booking_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `destinations`
--

CREATE TABLE `destinations` (
  `Destination_ID` int(11) NOT NULL,
  `Name` varchar(300) NOT NULL,
  `Description` varchar(2000) NOT NULL,
  `District` varchar(200) NOT NULL,
  `latitude` decimal(10,6) DEFAULT NULL,
  `longitude` decimal(10,6) DEFAULT NULL,
  `User_ID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `destinations`
--

INSERT INTO `destinations` (`Destination_ID`, `Name`, `Description`, `District`, `latitude`, `longitude`, `User_ID`) VALUES
(11, 'dote', 'qweqweowqe wowoiuuweqiewq qwiueqoiewiq euwquwqeqw wquiwqiqweu', 'Matale', 8.000000, 81.000000, NULL),
(12, 'admin', 'dsasdsds dsdds sdff', 'Kandy', 8.000000, 81.000000, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `destination_imgs`
--

CREATE TABLE `destination_imgs` (
  `Image_ID` int(11) NOT NULL,
  `Image_Url` varchar(512) NOT NULL,
  `AltText` varchar(200) DEFAULT NULL,
  `Destination_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `destination_imgs`
--

INSERT INTO `destination_imgs` (`Image_ID`, `Image_Url`, `AltText`, `Destination_ID`) VALUES
(19, 'uploads/1757378881_dabulla-5f28ef9a6fde.jpg', 'Photo of dote', 11),
(21, 'uploads/1757571501_polunna-f72af7eb2eca.webp', 'Photo of dote', 11),
(22, 'uploads/1758230134_mihin-579cb4447312.webp', 'Photo of dote', 11),
(23, 'uploads/Destinations/kandy3-77d4ef4c6037.jpg', 'Photo of admin', 12),
(24, 'uploads/Destinations/kandy1-a57085a0afbd.jpg', 'Photo of admin', 12),
(25, 'uploads/Destinations/kandy2-7d773ea46482.jpg', 'Photo of admin', 12);

-- --------------------------------------------------------

--
-- Table structure for table `driver`
--

CREATE TABLE `driver` (
  `Driver_ID` int(11) NOT NULL,
  `F_Name` varchar(200) NOT NULL,
  `L_Name` varchar(200) NOT NULL,
  `NIC_or_Pass` varchar(200) NOT NULL,
  `Description` varchar(400) NOT NULL,
  `Vehicle_Category` enum('Bike','Tuk-Tuk','Mini-Car','Car','Van','Bus') NOT NULL,
  `Vehicle_No` varchar(300) NOT NULL,
  `Fixed_Price` decimal(10,0) NOT NULL,
  `PricePer_Km` decimal(10,0) NOT NULL,
  `Total_Income` decimal(10,0) NOT NULL,
  `Status` enum('Available','Un_available','On_trip') NOT NULL,
  `Rating` decimal(10,0) NOT NULL,
  `Completed_trips` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `driver`
--

INSERT INTO `driver` (`Driver_ID`, `F_Name`, `L_Name`, `NIC_or_Pass`, `Description`, `Vehicle_Category`, `Vehicle_No`, `Fixed_Price`, `PricePer_Km`, `Total_Income`, `Status`, `Rating`, `Completed_trips`, `User_ID`) VALUES
(4, 'Mahinda', 'Rajapaksha', '867487687387', '', 'Van', 'N2334', 5000, 110, 0, 'Available', 5, 0, 20),
(5, 'Charana', 'wijesinghe', '6477567823', '', 'Car', 'CAG5745', 2500, 90, 0, 'Available', 5, 0, 21);

-- --------------------------------------------------------

--
-- Table structure for table `guide`
--

CREATE TABLE `guide` (
  `Guide_ID` int(11) NOT NULL,
  `F_Name` varchar(300) NOT NULL,
  `L_Name` varchar(200) NOT NULL,
  `NIC_or_Pass` varchar(200) NOT NULL,
  `Description` varchar(400) NOT NULL,
  `Price_per_Day` decimal(10,0) NOT NULL,
  `Rating` decimal(10,0) NOT NULL,
  `Status` enum('Available','Un_available','On_trip') NOT NULL,
  `Total_Income` decimal(10,0) NOT NULL,
  `Completed_trips` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `guide`
--

INSERT INTO `guide` (`Guide_ID`, `F_Name`, `L_Name`, `NIC_or_Pass`, `Description`, `Price_per_Day`, `Rating`, `Status`, `Total_Income`, `Completed_trips`, `User_ID`) VALUES
(2, 'Namal', 'Rajapaksha', '5473536353', 'im good in english language', 0, 5, 'Available', 0, 0, 15);

-- --------------------------------------------------------

--
-- Table structure for table `inquiry`
--

CREATE TABLE `inquiry` (
  `Inquiry_ID` int(11) NOT NULL,
  `Subject` varchar(300) NOT NULL,
  `Message` varchar(1000) NOT NULL,
  `Reply` varchar(1000) DEFAULT NULL,
  `Date&Time` date NOT NULL,
  `User_ID` int(11) NOT NULL,
  `Driver_ID` int(11) DEFAULT NULL,
  `Guide_ID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `itinerary`
--

CREATE TABLE `itinerary` (
  `ItineraryID` int(11) NOT NULL,
  `DayNumber` int(11) NOT NULL,
  `Location` varchar(200) NOT NULL,
  `Description` varchar(1000) NOT NULL,
  `PackageID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `itinerary`
--

INSERT INTO `itinerary` (`ItineraryID`, `DayNumber`, `Location`, `Description`, `PackageID`) VALUES
(125, 1, 'kandy', 'Step into one of the oldest continuously inhabited cities in the world. Explore enormous dagobas (stupas), the sacred Sri Maha Bodhi tree—grown from a cutting of the original Bodhi tree in India—and ancient monasteries that showcase exquisite stone carvings and inscriptions. Don’t miss the iconic Ruwanwelisaya stupa, an architectural marvel, and the Jetavanaramaya, once one of the tallest brick structures of the ancient world. Cycling or walking through the ruins gives a serene and reflective experience of Sri Lanka’s Buddhist heritage.', 28),
(126, 1, 'Polonnaruwa', 'Step into one of the oldest continuously inhabited cities in the world. Explore enormous dagobas (stupas), the sacred Sri Maha Bodhi tree—grown from a cutting of the original Bodhi tree in India—and ancient monasteries that showcase exquisite stone carvings and inscriptions. Don’t miss the iconic Ruwanwelisaya stupa, an architectural marvel, and the Jetavanaramaya, once one of the tallest brick structures of the ancient world. Cycling or walking through the ruins gives a serene and reflective experience of Sri Lanka’s Buddhist heritage.', 28);

-- --------------------------------------------------------

--
-- Table structure for table `language`
--

CREATE TABLE `language` (
  `ID` int(11) NOT NULL,
  `Language` varchar(300) NOT NULL,
  `Guide_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--
-- Error reading structure for table explore_ceylon_db.notifications: #1932 - Table 'explore_ceylon_db.notifications' doesn't exist in engine
-- Error reading data for table explore_ceylon_db.notifications: #1064 - You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near 'FROM `explore_ceylon_db`.`notifications`' at line 1

-- --------------------------------------------------------

--
-- Table structure for table `packageimages`
--

CREATE TABLE `packageimages` (
  `ImageID` int(11) NOT NULL,
  `ImageUrl` varchar(300) NOT NULL,
  `AltText` varchar(150) NOT NULL,
  `Package_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `packageimages`
--

INSERT INTO `packageimages` (`ImageID`, `ImageUrl`, `AltText`, `Package_ID`) VALUES
(91, 'uploads/Packages/1759664721_safari.jpg', 'Package Image', 28),
(92, 'uploads/Packages/1759664721_Scenic-Sri-Lanka-Desktop-image.jpg', 'Package Image', 28),
(93, 'uploads/Packages/1759664721_Sigiriya_Rock_Fortress.jpg', 'Package Image', 28),
(94, 'uploads/Packages/1759664721_sri-lanka.jpg', 'Package Image', 28),
(95, 'uploads/Packages/1759664721_walking.jpg', 'Package Image', 28);

-- --------------------------------------------------------

--
-- Table structure for table `packages`
--

CREATE TABLE `packages` (
  `Package_ID` int(11) NOT NULL,
  `Name` varchar(250) NOT NULL,
  `Subtitle` varchar(250) NOT NULL,
  `Description` varchar(1000) NOT NULL,
  `Long_Des` varchar(1000) NOT NULL,
  `DurationDays` int(11) NOT NULL,
  `Price` decimal(10,2) NOT NULL,
  `Root_img` varchar(300) NOT NULL,
  `User_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `packages`
--

INSERT INTO `packages` (`Package_ID`, `Name`, `Subtitle`, `Description`, `Long_Des`, `DurationDays`, `Price`, `Root_img`, `User_ID`) VALUES
(28, 'admin', 'asdndsbsmnd', 'adkds dsjkdsd dkdhsjds sdksudsdn dskdsndskjd djksdhskjd dsjdhdsd sdjkds sdjnsd sm dsijdsd s dkljdnsd  djssnbdsds ddjsds,d dsadkjnsd', 'adkds dsjkdsd dkdhsjds sdksudsdn dskdsndskjd djksdhskjd dsjdhdsd sdjkds sdjnsd sm dsijdsd s dkljdnsd  djssnbdsds ddjsds,d dsadkjnsd adkds dsjkdsd dkdhsjds sdksudsdn dskdsndskjd djksdhskjd dsjdhdsd sdjkds sdjnsd sm dsijdsd s dkljdnsd  djssnbdsds ddjsds,d dsadkjnsd adkds dsjkdsd dkdhsjds sdksudsdn dskdsndskjd djksdhskjd dsjdhdsd sdjkds sdjnsd sm dsijdsd s dkljdnsd  djssnbdsds ddjsds,d dsadkjnsd adkds dsjkdsd dkdhsjds sdksudsdn dskdsndskjd djksdhskjd dsjdhdsd sdjkds sdjnsd sm dsijdsd s dkljdnsd  djssnbdsds ddjsds,d dsadkjnsd', 2, 40000.00, 'uploads/Packages/1759664721_kandy1.jpg', 1);

-- --------------------------------------------------------

--
-- Table structure for table `review`
--

CREATE TABLE `review` (
  `Review_ID` int(11) NOT NULL,
  `Review` int(100) NOT NULL,
  `User_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tickets`
--

CREATE TABLE `tickets` (
  `Ticket_ID` int(11) NOT NULL,
  `Name` varchar(300) NOT NULL,
  `Contact_No` int(11) NOT NULL,
  `Destination` varchar(300) NOT NULL,
  `Category` enum('Cultural','Nature','Wildlife','zoos','Museum') NOT NULL,
  `No_Of_People` int(11) NOT NULL,
  `Purchased_Date` datetime NOT NULL,
  `Valid_Date` date NOT NULL,
  `Total_Price` decimal(10,0) NOT NULL,
  `Qr` varchar(300) NOT NULL,
  `Payment_Status` enum('Paid','Unpaid') NOT NULL,
  `User_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tickets`
--

INSERT INTO `tickets` (`Ticket_ID`, `Name`, `Contact_No`, `Destination`, `Category`, `No_Of_People`, `Purchased_Date`, `Valid_Date`, `Total_Price`, `Qr`, `Payment_Status`, `User_ID`) VALUES
(6, 'Domin Arachchi Athukoralage Mandira Chamath', 9090909, 'Dhalada Maligawa', 'Cultural', 1, '2025-10-08 04:49:41', '2025-10-10', 1500, 'QR_68e5a00dcff7e', 'Paid', 1),
(7, 'Domin Arachchi Athukoralage Mandira Chamath', 9090909, 'Dhalada Maligawa', 'Cultural', 1, '2025-10-08 04:53:56', '2025-10-10', 1500, 'QR_68e5a10c9d32b', 'Paid', 1),
(8, 'Domin Arachchi Athukoralage Mandira Chamath', 9090909, 'Dhalada Maligawa', 'Cultural', 1, '2025-10-08 04:54:38', '2025-10-10', 1500, 'QR_68e5a136bdc82', 'Paid', 1),
(9, 'Domin Arachchi Athukoralage Mandira Chamath', 9090909, 'Dhalada Maligawa', 'Cultural', 1, '2025-10-08 05:01:33', '2025-10-10', 1500, 'QR_68e5a2d5b104c', 'Paid', 1),
(10, 'Domin Arachchi Athukoralage Mandira Chamath', 9090909, 'Dhalada Maligawa', 'Cultural', 1, '2025-10-08 05:02:30', '2025-10-10', 1500, 'QR_68e5a30ece554', 'Paid', 1),
(11, 'Domin Arachchi Athukoralage Mandira Chamath', 9090909, 'Sinharaja Forest Reserve', 'Nature', 4, '2025-10-08 18:20:25', '2025-10-10', 8000, 'QR_68e65e11d6b54', 'Paid', 1),
(12, 'Domin Arachchi Athukoralage Mandira Chamath', 9090909, 'Colombo National Museum', 'Museum', 4, '2025-10-08 23:31:48', '2025-10-10', 4800, 'QR_68e6a70c4874a', 'Paid', 1),
(13, 'Domin Arachchi Athukoralage Mandira Chamath', 9090909, 'Seethawaka Botanical Garden', 'Nature', 4, '2025-10-08 23:32:32', '2025-10-10', 8000, 'QR_68e6a7386d5c6', 'Paid', 1),
(14, 'Domin Arachchi Athukoralage Mandira Chamath', 9090909, 'Mirijjawila Dry Zone Botanical Garden', 'Nature', 4, '2025-10-08 23:33:37', '2025-10-10', 8000, 'QR_68e6a779b2f9e', 'Paid', 1);

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `User_ID` int(11) NOT NULL,
  `Username` varchar(300) NOT NULL,
  `Email` varchar(200) NOT NULL,
  `Password` varchar(200) NOT NULL,
  `Phone_No` varchar(20) NOT NULL,
  `User_Profile` varchar(300) NOT NULL,
  `User_Type` enum('Admin','Guide','Driver','User') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`User_ID`, `Username`, `Email`, `Password`, `Phone_No`, `User_Profile`, `User_Type`) VALUES
(1, 'Chamath', 'mandirachamath@gmail.com', 'test', '0713704931', '', 'User'),
(2, 'Chamath', 'mandirachamath@gmail.com', 'test', '0713704931', '', 'User'),
(3, 'Mandira Chamath', 'mandirachamath1@gmail.com', '$2y$10$FUGd/aFvxhuTuALQRCnsgO3bJwq.hh5K7NM.Y0H12.Mck.081fSf6', '0713704931', '1758677911_IMG-20240828-WA0011.jpg', 'User'),
(4, 'test', 'test@gmail.com', '$2y$10$FjXBbpADeemNydHs9jCA4OC4a65nwVUF95..Y0BGddCJYEPLDiGo6', '0713704931', '1758683856_thumb-1920-1343589.jpeg', 'User'),
(5, 'Mandira Chamath', 'mandirachamath1@gmail.com', '$2y$10$nC1qHURuflJ1Elh/t81ouefw.Z8hd04WLbBzEpHlT8j1a1mzJEE/6', '0713704931', '1758678492_IMG-20240828-WA0011.jpg', 'User'),
(6, 'test3', 'test2@gmail.com', '$2y$10$ehScBSn/KF6OzAoTzcuOmuNkbgkPHTr/TxkX7U/IeTPn.0B5dQa5a', '0713704931', 'default.png', 'User'),
(7, 'Admin', 'admin@gmail.com', '$2y$10$7YIKJBbRPDjgi3b4gE5hZOHp8lzqHWjfcDQ2M6GI/PG7UljhA.cqi', '0713704931', 'default.png', 'Admin'),
(8, 'cj', 'cj@gmail.com', '$2y$10$815MynSXyWAKO4pi8/tdM.4HP.1K8kw0GlXkeK6ntCOqtiT1Ol.W2', '+94 71 370 4931', '1759586133_images-removebg-preview.png', 'User'),
(9, 'cj2', 'cj2@gmail.com', '$2y$10$aV9..X6Fh0Y.0Olz69APr.JLlKifnNWf4y0RLu/6Ld7KurodtbMVO', '0713704931', '1759663148_download.png', 'User'),
(10, 'cj3', 'cj3@gmail.com', '$2y$10$mhIJg86OJttpCHl4Nhn1cO3z8pPRVe5Ycy.E3dYV.OjBM2vqvuHHm', '0713704931', 'uploads/UserProfiles/user_1759735226_2edd809c341c.webp', 'User'),
(12, 'Saman', 'saman@gmail.com', '$2y$10$D/gdW0uNVnD2nF16/bCOO.QjkLuGgNV3YCWPcjhNH3CVlYLbb4Wa.', '+94 71 370 4931', '', 'Driver'),
(13, 'Saman', 'kasun@gmail.com', '$2y$10$WNSsqkNOw75LtbXAY0o6L.UOFGvyYOSuE3gS7B7B7GnweO06d8EZ2', '+94 71 370 4931', 'uploads/UserProfiles/user_1759735203_b373f275e2ec.jpg', 'Guide'),
(14, 'Mahinda', 'mahinda@gmail.com', '$2y$10$4imbUR4yzMUFG5CsJtQLieXezAyQ779y5NjSYEIj1LnRaHS3zCqbS', '0776543845', '1760085764_3ab31f4585f7_driver.jpg', 'Driver'),
(15, 'Namal', 'namal@gmail.com', '$2y$10$vwONVHwN.8Y/9LSu7/Ws2.TF0rvYZQucLyMpf5rI1mGzfTKjNLiKS', '077645624', '1760085885_d242356292c9_guide.jpg', 'Guide'),
(16, 'Mahinda', 'mahinda@gmail.com', '$2y$10$rxGhzPOG73sXDz6K/USfq.U/IwT0b720lvqLQeQrgl6iOg34sJZ3O', '0776543845', '1760085927_a299d91cd30e_driver.jpg', 'Driver'),
(17, 'Mahinda', 'mahinda@gmail.com', '$2y$10$LnFENwba7D4ND8.ufRBiQuHZZVcXsq7OyWGpae2.gbnq11w.6n1zC', '0776543845', '1760086426_81027474c935_driver.jpg', 'Driver'),
(18, 'Mahinda', 'mahinda@gmail.com', '$2y$10$cKhDZ1O52tRtB12VxE5PZ.BANmk3CPOn0gcFhsUhddYmIWoXlVXKe', '0776543845', '1760086475_8ffb16dc78a6_driver.jpg', 'Driver'),
(19, 'Mahinda', 'mahinda@gmail.com', '$2y$10$RUHnLphP5p74SalJ7Rrkee/Yab2K4YJ.pUT9kKcyd5d75PTzzK3WK', '0776543845', '1760088005_ddf4d04f1dfd_driver.jpg', 'Driver'),
(20, 'Mahinda', 'mahinda@gmail.com', '$2y$10$9H68jYktA38OryWGDst3g.1J7qAPkRLQNAJ3jyokT3ac/5ZuToFsq', '0776543845', '1760088317_5b751e02d0a1_driver.jpg', 'Driver'),
(21, 'Charana', 'charana@gmail.com', '$2y$10$NXdiZe24Dti7UEQeh/VQz.dF/WPzGQnH.Pie0lod2kP.Jdax8UoLe', '077563345', '1760088565_af42bcd2db5b_driver.jpg', 'Driver'),
(22, 'madawa', 'madawa@gmail.com', '$2y$10$fvLN07CiVeJ4RNbWjMJcYuI8EnzEQORcljTJHLE6mtuD9MxS3dZAG', '0765674321', 'default.png', 'User');

-- --------------------------------------------------------

--
-- Table structure for table `vehicle`
--

CREATE TABLE `vehicle` (
  `Vehicle_ID` int(11) NOT NULL,
  `Category` enum('Tuk','Bike','Mini_Car','Car','Mini_Van','Van') NOT NULL,
  `Price_Per_Day` varchar(300) NOT NULL,
  `Seating_Capacity` int(11) NOT NULL,
  `Vehicle_Number` varchar(300) NOT NULL,
  `Status` varchar(100) NOT NULL,
  `User_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicle`
--

INSERT INTO `vehicle` (`Vehicle_ID`, `Category`, `Price_Per_Day`, `Seating_Capacity`, `Vehicle_Number`, `Status`, `User_ID`) VALUES
(10, 'Tuk', '4000', 3, 'ABC-3043', 'Available\r\n', 1),
(11, 'Tuk', '4000\r\n', 3, 'QWE -4089', 'Available', 1),
(12, 'Tuk', '4000', 3, 'ABC-3043', 'Available\r\n', 1),
(13, 'Tuk', '4000\r\n', 3, 'QWE -4089', 'Available', 1),
(14, 'Bike', '3000', 2, 'ABG-2992', 'Available', 1),
(15, 'Bike', '3000', 2, 'ATG-3899', 'Available', 1),
(16, 'Mini_Car', '7000', 4, 'OKO-7788', 'Available', 1),
(17, 'Mini_Car', '7000', 4, 'ZCV-0099', 'Available', 1),
(18, 'Tuk', '4000', 3, 'qwe 2020', 'Available', 7),
(19, 'Mini_Car', '7000', 4, 'caf4567', 'Available', 7);

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_rentals`
--

CREATE TABLE `vehicle_rentals` (
  `Rental_ID` int(11) NOT NULL,
  `Name` varchar(300) NOT NULL,
  `Email` varchar(300) NOT NULL,
  `NIC_or_Pass` varchar(300) NOT NULL,
  `Phone_No` varchar(300) NOT NULL,
  `Start_Date` date NOT NULL,
  `End_Date` date NOT NULL,
  `Start_Location` varchar(300) NOT NULL,
  `Payment_Method` enum('Cash','Online') NOT NULL,
  `Payment_Status` enum('Paid','Unpaid') NOT NULL,
  `Vehicle_ID` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicle_rentals`
--

INSERT INTO `vehicle_rentals` (`Rental_ID`, `Name`, `Email`, `NIC_or_Pass`, `Phone_No`, `Start_Date`, `End_Date`, `Start_Location`, `Payment_Method`, `Payment_Status`, `Vehicle_ID`, `User_ID`) VALUES
(14, 'test1', 'mandirachamath@gmail.com', '200629101137', '713704931', '2025-09-21', '2025-09-22', 'kandy', 'Cash', 'Paid', 10, 1),
(15, 'test2', 'mandirachamath@gmail.com', '200629101137', '713704931', '2025-09-21', '2025-09-22', 'kandy', 'Cash', 'Paid', 11, 1),
(16, 'test3', 'mandirachamath@gmail.com', '200629101137', '713704931', '2025-09-21', '2025-09-22', 'kandy', 'Cash', 'Paid', 12, 1),
(17, 'test4', 'mandirachamath@gmail.com', '200629101137', '713704931', '2025-09-21', '2025-09-22', 'kandy', 'Cash', 'Paid', 13, 1),
(18, 'test5', 'mandirachamath@gmail.com', '200629101137', '713704931', '2025-09-23', '2025-09-24', 'kandy', 'Cash', 'Paid', 10, 1),
(19, 'test6', 'mandirachamath@gmail.com', '200629101137', '713704931', '2025-09-23', '2025-09-24', 'kandy', 'Cash', 'Paid', 11, 1),
(20, 'test7', 'mandirachamath@gmail.com', '200629101137', '713704931', '2025-09-23', '2025-09-24', 'kandy', 'Cash', 'Paid', 12, 1),
(21, 'kaushal', 'kaushal@gmail.com', '200629101137', '773538444', '2025-09-24', '2025-09-24', 'kandy', 'Cash', 'Paid', 14, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`Booking_ID`),
  ADD KEY `Booking_ID` (`Booking_ID`),
  ADD KEY `Booking_ID_2` (`Booking_ID`),
  ADD KEY `User_ID` (`User_ID`),
  ADD KEY `Driver_ID` (`Driver_ID`),
  ADD KEY `Guide_ID` (`Guide_ID`),
  ADD KEY `Package_ID` (`Package_ID`);

--
-- Indexes for table `booking_destinations`
--
ALTER TABLE `booking_destinations`
  ADD PRIMARY KEY (`Destination_ID`),
  ADD KEY `Destination_ID` (`Destination_ID`),
  ADD KEY `Booking_ID` (`Booking_ID`);

--
-- Indexes for table `destinations`
--
ALTER TABLE `destinations`
  ADD PRIMARY KEY (`Destination_ID`),
  ADD KEY `Destination_ID` (`Destination_ID`),
  ADD KEY `User_ID` (`User_ID`);

--
-- Indexes for table `destination_imgs`
--
ALTER TABLE `destination_imgs`
  ADD PRIMARY KEY (`Image_ID`),
  ADD KEY `Destination_ID` (`Destination_ID`);

--
-- Indexes for table `driver`
--
ALTER TABLE `driver`
  ADD PRIMARY KEY (`Driver_ID`),
  ADD KEY `User_ID` (`User_ID`),
  ADD KEY `Driver_ID` (`Driver_ID`);

--
-- Indexes for table `guide`
--
ALTER TABLE `guide`
  ADD PRIMARY KEY (`Guide_ID`),
  ADD KEY `User_ID` (`User_ID`),
  ADD KEY `Guide_ID` (`Guide_ID`);

--
-- Indexes for table `inquiry`
--
ALTER TABLE `inquiry`
  ADD PRIMARY KEY (`Inquiry_ID`),
  ADD KEY `User_ID` (`User_ID`),
  ADD KEY `Driver_ID` (`Driver_ID`),
  ADD KEY `Guide_ID` (`Guide_ID`);

--
-- Indexes for table `itinerary`
--
ALTER TABLE `itinerary`
  ADD PRIMARY KEY (`ItineraryID`),
  ADD KEY `PackageID` (`PackageID`);

--
-- Indexes for table `language`
--
ALTER TABLE `language`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `Guide_ID` (`Guide_ID`);

--
-- Indexes for table `packageimages`
--
ALTER TABLE `packageimages`
  ADD PRIMARY KEY (`ImageID`),
  ADD KEY `Package_ID` (`Package_ID`);

--
-- Indexes for table `packages`
--
ALTER TABLE `packages`
  ADD PRIMARY KEY (`Package_ID`),
  ADD KEY `Package_ID` (`Package_ID`),
  ADD KEY `User_ID` (`User_ID`),
  ADD KEY `User_ID_2` (`User_ID`);

--
-- Indexes for table `review`
--
ALTER TABLE `review`
  ADD KEY `User_ID` (`User_ID`);

--
-- Indexes for table `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`Ticket_ID`),
  ADD KEY `User_ID` (`User_ID`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`User_ID`),
  ADD KEY `User_ID` (`User_ID`),
  ADD KEY `User_ID_2` (`User_ID`);

--
-- Indexes for table `vehicle`
--
ALTER TABLE `vehicle`
  ADD PRIMARY KEY (`Vehicle_ID`),
  ADD KEY `User_ID` (`User_ID`),
  ADD KEY `Vehicle_ID` (`Vehicle_ID`);

--
-- Indexes for table `vehicle_rentals`
--
ALTER TABLE `vehicle_rentals`
  ADD PRIMARY KEY (`Rental_ID`),
  ADD KEY `User_ID` (`User_ID`),
  ADD KEY `Rental_ID` (`Rental_ID`),
  ADD KEY `Rental_ID_2` (`Rental_ID`),
  ADD KEY `Vehicle_ID` (`Vehicle_ID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `Booking_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `booking_destinations`
--
ALTER TABLE `booking_destinations`
  MODIFY `Destination_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `destinations`
--
ALTER TABLE `destinations`
  MODIFY `Destination_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `destination_imgs`
--
ALTER TABLE `destination_imgs`
  MODIFY `Image_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `driver`
--
ALTER TABLE `driver`
  MODIFY `Driver_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `guide`
--
ALTER TABLE `guide`
  MODIFY `Guide_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `inquiry`
--
ALTER TABLE `inquiry`
  MODIFY `Inquiry_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `itinerary`
--
ALTER TABLE `itinerary`
  MODIFY `ItineraryID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=127;

--
-- AUTO_INCREMENT for table `language`
--
ALTER TABLE `language`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `packageimages`
--
ALTER TABLE `packageimages`
  MODIFY `ImageID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=96;

--
-- AUTO_INCREMENT for table `packages`
--
ALTER TABLE `packages`
  MODIFY `Package_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `tickets`
--
ALTER TABLE `tickets`
  MODIFY `Ticket_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `User_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `vehicle`
--
ALTER TABLE `vehicle`
  MODIFY `Vehicle_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `vehicle_rentals`
--
ALTER TABLE `vehicle_rentals`
  MODIFY `Rental_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `user` (`User_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`Package_ID`) REFERENCES `packages` (`Package_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_3` FOREIGN KEY (`Driver_ID`) REFERENCES `driver` (`Driver_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_4` FOREIGN KEY (`Guide_ID`) REFERENCES `guide` (`Guide_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `booking_destinations`
--
ALTER TABLE `booking_destinations`
  ADD CONSTRAINT `booking_destinations_ibfk_1` FOREIGN KEY (`Booking_ID`) REFERENCES `bookings` (`Booking_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `destinations`
--
ALTER TABLE `destinations`
  ADD CONSTRAINT `destinations_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `user` (`User_ID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `destination_imgs`
--
ALTER TABLE `destination_imgs`
  ADD CONSTRAINT `destination_imgs_ibfk_1` FOREIGN KEY (`Destination_ID`) REFERENCES `destinations` (`Destination_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `driver`
--
ALTER TABLE `driver`
  ADD CONSTRAINT `driver_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `user` (`User_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `guide`
--
ALTER TABLE `guide`
  ADD CONSTRAINT `guide_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `user` (`User_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `inquiry`
--
ALTER TABLE `inquiry`
  ADD CONSTRAINT `inquiry_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `user` (`User_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `inquiry_ibfk_2` FOREIGN KEY (`Guide_ID`) REFERENCES `guide` (`Guide_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `inquiry_ibfk_3` FOREIGN KEY (`Driver_ID`) REFERENCES `driver` (`Driver_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `itinerary`
--
ALTER TABLE `itinerary`
  ADD CONSTRAINT `itinerary_ibfk_1` FOREIGN KEY (`PackageID`) REFERENCES `packages` (`Package_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `language`
--
ALTER TABLE `language`
  ADD CONSTRAINT `language_ibfk_1` FOREIGN KEY (`Guide_ID`) REFERENCES `guide` (`Guide_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `packageimages`
--
ALTER TABLE `packageimages`
  ADD CONSTRAINT `packageimages_ibfk_1` FOREIGN KEY (`Package_ID`) REFERENCES `packages` (`Package_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `packages`
--
ALTER TABLE `packages`
  ADD CONSTRAINT `packages_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `user` (`User_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `review`
--
ALTER TABLE `review`
  ADD CONSTRAINT `review_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `user` (`User_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `user` (`User_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `vehicle`
--
ALTER TABLE `vehicle`
  ADD CONSTRAINT `vehicle_ibfk_2` FOREIGN KEY (`User_ID`) REFERENCES `user` (`User_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `vehicle_rentals`
--
ALTER TABLE `vehicle_rentals`
  ADD CONSTRAINT `vehicle_rentals_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `user` (`User_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `vehicle_rentals_ibfk_2` FOREIGN KEY (`Vehicle_ID`) REFERENCES `vehicle` (`Vehicle_ID`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
