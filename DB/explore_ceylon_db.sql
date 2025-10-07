-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 06, 2025 at 12:09 PM
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
  `Progress` varchar(100) NOT NULL,
  `Price` decimal(10,0) NOT NULL,
  `User_ID` int(11) NOT NULL,
  `Driver_ID` int(11) NOT NULL,
  `Guide_ID` int(11) NOT NULL,
  `Package_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `booking_destinations`
--

CREATE TABLE `booking_destinations` (
  `Destination_ID` int(11) NOT NULL,
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
(11, 'dote', 'qweqweowqe wowoiuuweqiewq qwiueqoiewiq euwquwqeqw wquiwqiqweu', 'Matale', 8.000000, 81.000000, NULL);

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
(22, 'uploads/1758230134_mihin-579cb4447312.webp', 'Photo of dote', 11);

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
  `Vehicle_Category` varchar(300) NOT NULL,
  `Vehicle_No` varchar(300) NOT NULL,
  `Fixed_Price` decimal(10,0) NOT NULL,
  `PricePer_Km` decimal(10,0) NOT NULL,
  `Seating_Capacity` int(11) NOT NULL,
  `Status` varchar(100) NOT NULL,
  `Rating` varchar(100) NOT NULL,
  `User_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `Rating` varchar(300) NOT NULL,
  `Status` varchar(300) NOT NULL,
  `User_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `guide`
--

INSERT INTO `guide` (`Guide_ID`, `F_Name`, `L_Name`, `NIC_or_Pass`, `Description`, `Rating`, `Status`, `User_ID`) VALUES
(2, 'qwer1', 'Rajapaksha', '200203290203', '22k wdnwkdw dwjdnwkjd  dwdnjd dwjwdj dwjdw dwdjwd wdkd wdw dwjd', '', 'Available', 11),
(4, 'sakalabujan', 'kotakalisam', '216143142194', 'sfhbsjfsnjfbsujhf', '', 'Available', 13);

-- --------------------------------------------------------

--
-- Table structure for table `inquiry`
--

CREATE TABLE `inquiry` (
  `Inquiry_ID` int(11) NOT NULL,
  `Subject` varchar(300) NOT NULL,
  `Message` varchar(1000) NOT NULL,
  `Reply` varchar(1000) NOT NULL,
  `Date&Time` date NOT NULL,
  `User_ID` int(11) NOT NULL
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
(113, 1, 'Kadny', 'kadny tour', 18),
(114, 2, 'kandy', 'kadny test', 19),
(115, 1, 'Kandy', 'kadny test', 17),
(118, 1, 'qqqqq', 'qqqqqqqqqqqqqqqqqqq', 24),
(119, 1, 'kandy', 'Explore Gal Vihara, royal palace ruins, and Parakrama Samudra reservoir. Cycle through archaeological sites and admire ancient engineering.', 25),
(121, 1, 'kandy', 'ejhdggahdagdhcakdj cabcshcbakcbcsj', 26),
(122, 1, 'qqqq qqwwquy', 'qweqweowqe wowoiuuweqiewq qwiueqoiewiq euwquwqeqw wquiwqiqweu', 27),
(123, 2, 'qewqe wqeew', 'qweqweowqe wowoiuuweqiewq qwiueqoiewiq euwquwqeqw wquiwqiqweu', 27);

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

CREATE TABLE `notifications` (
  `Notification_ID` int(11) NOT NULL,
  `Subject` varchar(300) NOT NULL,
  `Message` varchar(1000) NOT NULL,
  `Date&Time` date NOT NULL,
  `User_ID` int(11) NOT NULL,
  `Booking_ID` int(11) NOT NULL,
  `Rental_ID` int(11) NOT NULL,
  `Ticket_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(53, 'uploads/1759088329_des2.jpg', 'Package Image', 17),
(54, 'uploads/1759088329_des3.jpg', 'Package Image', 17),
(55, 'uploads/1759088329_WhatsApp_Image_2025-09-28_at_01.55.51_6500977d.jpg', 'Package Image', 17),
(56, 'uploads/1759091211_des2.jpg', 'Package Image', 18),
(57, 'uploads/1759091211_des3.jpg', 'Package Image', 18),
(58, 'uploads/1759091211_WhatsApp_Image_2025-09-28_at_01.55.51_6500977d.jpg', 'Package Image', 18),
(59, 'uploads/1759093921_des2.jpg', 'Package Image', 19),
(60, 'uploads/1759093921_des3.jpg', 'Package Image', 19),
(61, 'uploads/1759093921_WhatsApp_Image_2025-09-28_at_01.55.51_6500977d.jpg', 'Package Image', 19),
(66, 'uploads/Packages/1759521045_1758230354_dabulla.jpg', 'Package Image', 24),
(67, 'uploads/Packages/1759521045_1758230354_mihin.webp', 'Package Image', 24),
(68, 'uploads/Packages/1759521045_1758230354_polunna.webp', 'Package Image', 24),
(69, 'uploads/Packages/1759521045_1758260488_anura.jpg', 'Package Image', 24),
(70, 'uploads/Packages/1759521350_1757420593_polunna.webp', 'Package Image', 25),
(71, 'uploads/Packages/1759521350_1758230354_polunna.webp', 'Package Image', 25),
(72, 'uploads/Packages/1759521350_1758260488_dabulla.jpg', 'Package Image', 25),
(73, 'uploads\\Packages/1759521796_1757374640_trinco1.jpg', 'Package Image', 26),
(74, 'uploads\\Packages/1759521796_1757378881_polunna.webp', 'Package Image', 26),
(75, 'uploads\\Packages/1759521796_1757378881_sigiriya1.jpg', 'Package Image', 26),
(76, 'uploads\\Packages/1759521796_1757379702_2.jpg', 'Package Image', 26),
(77, 'uploads\\Packages/1759521796_1757420593_polunna.webp', 'Package Image', 26),
(78, 'uploads/Packages/1759522077_1757378881_sigiriya1.jpg', 'Package Image', 26),
(79, 'uploads/Packages/1759522077_1757379702_2.jpg', 'Package Image', 26),
(80, 'uploads/Packages/1759522077_1757420593_polunna.webp', 'Package Image', 26),
(81, 'uploads/Packages/1759522077_1757571501_2.jpg', 'Package Image', 26),
(82, 'uploads/1759525329_1757378881_anura.jpg', 'Package Image', 27),
(83, 'uploads/1759525329_1757378881_dabulla.jpg', 'Package Image', 27),
(84, 'uploads/1759525329_1757378881_kandy1.jpg', 'Package Image', 27),
(85, 'uploads/1759525329_1757378881_mihin.webp', 'Package Image', 27);

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
(17, 'test package', 'sub title check', 'description check check skdhsdjhkdsjd dshdksdsdhsd dsdjdsdd dsjdhksjdd sdshd dsdjhdsjkdsd djshkd', 'this is long description shjdsjdhsdjhd', 5, 200.00, 'uploads/1759088329_des2.jpg', 1),
(18, 'another name', 'subtitel chekc', 'short desc', 'long desc test', 4, 200.00, 'uploads/1759091211_Final_Project_ER.jpg', 1),
(19, 'agaga', 'asasnbsa', 'asamam,sna,msnsa,msba,sans,qmasnsa', 'tgggffvg jhnhbnb jmnbb  jkkkk,', 3, 2222.00, 'uploads/1759093921_Final_Project_ER.jpg', 1),
(20, 'Pavith Rajapaksha', 'eeeee', 'sesfffsff', '44444', 4, 444.00, 'uploads/1759093956_des3.jpg', 1),
(21, 'pavith', 'qwqqw', 'sdsdsdsd dsdsds dsdsdsds', 'fdsgdfgdfg dgdfgd gdfg dgdffgfgd dfgf', 1, 1000.00, 'uploads/1759094019_Error500.jpg', 1),
(22, 'jack', 'ggdgdfd gfg dfgfd dgd d gdf g', 'flgdfgdkl gfdg fdkgflkgdjfdlkg', 'vghvgvg hgfhggv hgfg hgfgf', 2, 46678.00, 'uploads/1759094082_des3.jpg', 1),
(24, 'admin', 'asdf', 'adadssda', 'ssadasdadad', 2, 2212.00, 'uploads/Packages/1759521045_1757373151_kandy3.jpg', 1),
(25, 'Charana', 'asdf', 'adadssda', 'ssadasdadad', 2, 2212.00, 'uploads/Packages/1759521350_1758677890_IMG-20240828-WA0011.jpg', 1),
(26, '1111111', 'asdf', 'adadssda', 'ssadasdadad', 2, 2212.00, 'uploads/Packages/1759522077_1757373151_kandy3.jpg', 1),
(27, 'admin', 'asdf', 'adadssda', 'ssadasdadad', 2, 2212.00, 'uploads/1759525329_1757571501_polunna.webp', 1);

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
(9, 'asasas', 'asass@gmaiul.com', '$2y$10$TnNdlE4uIvYJCJMWnYGMXeL5fN29OB2gnSNN7syIq69DkNpLmJK0W', 'asss', 'uploads/UserProfiles/user_1759742869_3e5efe860262.jpg', 'User'),
(10, 'qwer', 'qwer1@gmail.com', '$2y$10$9TinBngu8EDDqYeHGy.qEOx6L7bPJ.9maMYN4e7/hUen2ENoqBKpa', '0713704941', '/uploads/UserProfiles/user_1759737268_b374f8fabdc3.jpg', 'Guide'),
(11, 'qwer', 'qwer1@gmail.com', '$2y$10$7iPCiZbk204v4K.sJHkIW..hp5VY787OE4M8KnOLyNYKE2kRU0Rqy', '0713704941', '/uploads/UserProfiles/user_1759741623_f4e1a25f45cd.jpg', 'Guide'),
(12, 'bujan', 'bujan@gmail.com', '$2y$10$jYtpO7rlW/o4jCPWn/v.9eWHwnHqKLmdh0ci9DuDqDN5B3RKkhzx2', '0775586954', '/uploads/UserProfiles/user_1759741693_e62ea199102a.jpg', 'Guide'),
(13, 'bujan', 'bujan@gmail.com', '$2y$10$8gmY1wWiFHMhKxK6r3kcg.iH3bxUjIqOga/DG0S3MND68mGODGkYa', '0775586954', '1759742194_5fa971e44db2_IMG_5746.jpg', 'Guide'),
(14, 'kenul bus driver', 'kenul@gmail.com', '$2y$10$0rRTiJPTqJu5j5ois9ylMOcu4rMXncvAysMUoWHixM.9xC0XGvSjW', '0775486542', '1759742835_d0b8addfdafc_des2.jpg', 'Driver');

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
(17, 'Mini_Car', '7000', 4, 'ZCV-0099', 'Available', 1);

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_rentals`
--

CREATE TABLE `vehicle_rentals` (
  `Rental_ID` int(11) NOT NULL,
  `Name` varchar(300) NOT NULL,
  `Email` varchar(300) NOT NULL,
  `NIC_or_Pass` varchar(300) NOT NULL,
  `Phone_No` int(11) NOT NULL,
  `Start_Date` date NOT NULL,
  `End_Date` date NOT NULL,
  `Start_Location` varchar(300) NOT NULL,
  `Vehicle_ID` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicle_rentals`
--

INSERT INTO `vehicle_rentals` (`Rental_ID`, `Name`, `Email`, `NIC_or_Pass`, `Phone_No`, `Start_Date`, `End_Date`, `Start_Location`, `Vehicle_ID`, `User_ID`) VALUES
(14, 'test1', 'mandirachamath@gmail.com', '200629101137', 713704931, '2025-09-21', '2025-09-22', 'kandy', 10, 1),
(15, 'test2', 'mandirachamath@gmail.com', '200629101137', 713704931, '2025-09-21', '2025-09-22', 'kandy', 11, 1),
(16, 'test3', 'mandirachamath@gmail.com', '200629101137', 713704931, '2025-09-21', '2025-09-22', 'kandy', 12, 1),
(17, 'test4', 'mandirachamath@gmail.com', '200629101137', 713704931, '2025-09-21', '2025-09-22', 'kandy', 13, 1),
(18, 'test5', 'mandirachamath@gmail.com', '200629101137', 713704931, '2025-09-23', '2025-09-24', 'kandy', 10, 1),
(19, 'test6', 'mandirachamath@gmail.com', '200629101137', 713704931, '2025-09-23', '2025-09-24', 'kandy', 11, 1),
(20, 'test7', 'mandirachamath@gmail.com', '200629101137', 713704931, '2025-09-23', '2025-09-24', 'kandy', 12, 1),
(21, 'kaushal', 'kaushal@gmail.com', '200629101137', 773538444, '2025-09-24', '2025-09-24', 'kandy', 14, 1);

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
  ADD KEY `User_ID` (`User_ID`);

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
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`Notification_ID`);

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
  MODIFY `Booking_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `booking_destinations`
--
ALTER TABLE `booking_destinations`
  MODIFY `Destination_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `destinations`
--
ALTER TABLE `destinations`
  MODIFY `Destination_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `destination_imgs`
--
ALTER TABLE `destination_imgs`
  MODIFY `Image_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `driver`
--
ALTER TABLE `driver`
  MODIFY `Driver_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `guide`
--
ALTER TABLE `guide`
  MODIFY `Guide_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `inquiry`
--
ALTER TABLE `inquiry`
  MODIFY `Inquiry_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `itinerary`
--
ALTER TABLE `itinerary`
  MODIFY `ItineraryID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=124;

--
-- AUTO_INCREMENT for table `language`
--
ALTER TABLE `language`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `Notification_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `packageimages`
--
ALTER TABLE `packageimages`
  MODIFY `ImageID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT for table `packages`
--
ALTER TABLE `packages`
  MODIFY `Package_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `User_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `vehicle`
--
ALTER TABLE `vehicle`
  MODIFY `Vehicle_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

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
  ADD CONSTRAINT `inquiry_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `user` (`User_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

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
