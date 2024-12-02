-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 10, 2024 at 04:20 PM
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
-- Database: `mims`
--

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `client_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone_number` varchar(15) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `insurance_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`client_id`, `first_name`, `last_name`, `phone_number`, `email`, `insurance_id`) VALUES
(1, 'Prince', 'CYUBAHIRO Ishimwe', '0788730404', 'cyubahiro222@gmail.com', 1),
(2, 'Derrick', 'MUGABO', '0780896222', 'mugabo@gmail.com', 1),
(3, 'Frank', 'MUSIRIKARE', '0782971397', 'musirikare@gmail.com', 1),
(4, 'Eric', 'MUNGUANIPE', '0782971397', 'ericnipe@gmail.com', 3),
(5, 'Tamim Brown', 'KAGABO', '0789703370', 'tamim@gmail.com', 3);

-- --------------------------------------------------------

--
-- Table structure for table `insurance_companies`
--

CREATE TABLE `insurance_companies` (
  `insurance_id` int(11) NOT NULL,
  `insurance_name` varchar(100) NOT NULL,
  `coverage_percentage` decimal(5,2) NOT NULL,
  `email` varchar(60) NOT NULL,
  `phonenumber` varchar(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `insurance_companies`
--

INSERT INTO `insurance_companies` (`insurance_id`, `insurance_name`, `coverage_percentage`, `email`, `phonenumber`) VALUES
(1, 'RSSB', 85.00, 'info@rssb.rw', '4044'),
(2, 'RADIANT', 80.00, 'info@radiant.rw', '0788381093'),
(3, 'SONARWA LIFE', 80.00, 'info@sonarwalife.co.rw', '0788500144'),
(5, 'Prime Life Insurance', 80.00, 'info@prime.rw', '0788150100'),
(8, 'MMI', 85.00, 'info@mmi.gov.rw', '1535');

-- --------------------------------------------------------

--
-- Table structure for table `insurance_coverage`
--

CREATE TABLE `insurance_coverage` (
  `coverage_id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `insurance_id` int(11) NOT NULL,
  `covered_amount` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `insurance_users`
--

CREATE TABLE `insurance_users` (
  `insurance_user_id` int(11) NOT NULL,
  `first_name` varchar(30) NOT NULL,
  `last_name` varchar(30) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `reset_key` varchar(50) DEFAULT NULL,
  `role` enum('insurance_admin','insurance_employee') DEFAULT NULL,
  `insurance_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `insurance_users`
--

INSERT INTO `insurance_users` (`insurance_user_id`, `first_name`, `last_name`, `username`, `email`, `password`, `reset_key`, `role`, `insurance_id`) VALUES
(1, 'Hygor', 'MANIRAMBONA', 'hygor', 'hygor@rssb.rw', '$2y$10$Cp/1eLyWsaj3MbSRWhBpVuc9JzITpHifcGCA6zacX2d6x/cLnCj9C', NULL, 'insurance_admin', 1),
(2, 'Derrick', 'MUGABO', 'derrick', 'mugabo@sonarwalife.co.rw', '$2y$10$ddKedBRTztH2PPwr74af7OnLjYujP1wV7wHV9eRbauda2d.tiMWhW', NULL, 'insurance_admin', 3),
(3, 'Monia', 'GIHOZO', 'monia', 'monia@rssb.rw', '$2y$10$f7bsqK.e05tbw9hT4.Gufu6u2Y2ch7pFNf/FBFp/EF02O7xg3vnMK', NULL, 'insurance_admin', 1),
(5, 'Agape', 'NSHUTI', 'agape', 'nshuti@sonarwalife.co.rw', '$2y$10$FBelpuxn7S1MRbYCCdZo6evrCtMgvrckHo2XNXhzEvWVsYIrKGtOm', NULL, 'insurance_employee', 3);

-- --------------------------------------------------------

--
-- Table structure for table `medications`
--

CREATE TABLE `medications` (
  `medication_id` int(11) NOT NULL,
  `medication_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `insurance_coverage` tinyint(1) NOT NULL DEFAULT 0,
  `unit_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medications`
--

INSERT INTO `medications` (`medication_id`, `medication_name`, `description`, `insurance_coverage`, `unit_price`) VALUES
(1, 'Paracetamol', 'Uses: Headache, muscle aches, toothache, fever, and other minor pains.   \r\n\r\nDosage: Follow the instructions on the product label or as directed by a healthcare professional.', 1, 20.00),
(2, 'Amoxycilline 250MG', 'Amoxicillin is a penicillin antibiotic. It is used to treat bacterial infections, such as chest infections (including pneumonia) and dental abscesses. It can also be used together with other antibiotics and medicines to treat stomach ulcers.', 1, 140.00),
(3, 'EFFERALGAN CODEINE 500mg/200mg', 'Pain', 1, 1800.00),
(4, 'Ibuprofen 400mg', 'Pain killers', 1, 40.00),
(5, 'Asonor', 'For Temporary Relief of Snoring', 1, 12000.00),
(6, 'Paracetamol Syrup 125mg/5ml 100ml', 'Paracetamol 125 mg Syrup is the most widely used over-the-counter (OTC) medication. It is used for the treatment of fever and pain.', 1, 320.00),
(7, 'Sterovit 100ml', 'Sterovit is a multivitamin that supplements a balanced and healthy diet.', 1, 5000.00),
(8, 'Rovamycine 1.5MUI 16 Comp.', 'Spiramycin is an antibiotic that belongs to the class of medications called macrolide antibiotics. It is used to treat certain types of infections that are caused by bacteria. It is most commonly used to treat infections of the lung, skin, and mouth. Spiramycin is sometimes used to treat gonorrhea for people who are allergic to penicillin.', 1, 7200.00),
(9, 'Griseofulvine 250mg', 'Griseofulvin belongs to the group of medicines called antifungals. It is used to treat fungus infections of the body, feet, groin and thighs, scalp, skin, fingernails, and toenails. This medicine may be taken alone or used along with medicines that are applied to the skin for fungus infections.', 1, 280.00),
(10, 'Cardioaspirine 100mg 30 Comp', 'Cardioaspirine 100 mg is an enteric-coated aspirin tablet used to prevent heart attacks, strokes, and blood clots after a heart attack. It works by reducing the risk of blood clots forming in the arteries.', 1, 1800.00),
(11, 'Zyrtec 10mg - 10 tablets', 'For fast 24 Hour Relief of hayfever and Allergy Symptoms Sneezing, Runny Nose, Watery/Itchy eyes, Itchy Skin. For the treatment of seasonal allergic rhinitis, perennial allergic rhinitis and chronic idiopathic urticaria', 1, 10790.00),
(12, 'Efferalgan Vitamine C  500mg/200mg', 'Fever, Headache, and other pain', 1, 1800.00);

-- --------------------------------------------------------

--
-- Table structure for table `pharmacies`
--

CREATE TABLE `pharmacies` (
  `pharmacy_id` int(11) NOT NULL,
  `pharmacy_name` varchar(100) NOT NULL,
  `email` varchar(50) NOT NULL,
  `phone_number` varchar(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pharmacies`
--

INSERT INTO `pharmacies` (`pharmacy_id`, `pharmacy_name`, `email`, `phone_number`) VALUES
(1, 'Pharmacie La Divine', 'pharmacieladivine@gmail.com', '0781837080'),
(2, 'Sugira Pharmacy', 'sugirapharmacy@gmail.com', '0788730404'),
(4, 'AFIA Pharma', 'info@afiapharma.com', '0785831256');

-- --------------------------------------------------------

--
-- Table structure for table `pharmacy_users`
--

CREATE TABLE `pharmacy_users` (
  `pharmacy_user_id` int(11) NOT NULL,
  `first_name` varchar(30) NOT NULL,
  `last_name` varchar(30) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `reset_key` varchar(50) DEFAULT NULL,
  `role` enum('pharmacy_admin','pharmacist') DEFAULT NULL,
  `pharmacy_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pharmacy_users`
--

INSERT INTO `pharmacy_users` (`pharmacy_user_id`, `first_name`, `last_name`, `username`, `email`, `password`, `reset_key`, `role`, `pharmacy_id`) VALUES
(1, 'Hygues', 'CYUBAHIRO', 'cyubahiro', 'ndayizeyekevin@yahoo.com', '$2y$10$jIvd2G8IZLqrxceo4ExXG.f0f6y7bdJMpBpEqb4e8cS1z8DEV0atu', '36ac5525843508b89069', 'pharmacy_admin', 1),
(3, 'Chrispin', 'Izere KANAMUGIRE', 'chrispin', 'kanamugire@gmail.com', '$2y$10$JljDSWGeSYGXJSw0rLbY7OJTGk/vLcmyUdxSMM7VfeM6IWgoh.dB2', NULL, 'pharmacy_admin', 2),
(4, 'Christ Lee Scott', 'ARAKAZA', 'arakaza', 'arakaza@gmail.com', '$2y$10$Hvu1miOKzxN0cNVyiC28/eCFh/y50RbrMhdv/O6fWF0YjbA6y2PF6', NULL, 'pharmacist', 1);

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `pharmacy_user_id` int(13) DEFAULT NULL,
  `transaction_date` datetime NOT NULL DEFAULT current_timestamp(),
  `prescription_attachment` varchar(255) DEFAULT NULL,
  `client_payment` decimal(10,2) DEFAULT NULL,
  `insurance_payout` decimal(12,2) NOT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `pharmacy_id` int(11) NOT NULL,
  `insurance_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`transaction_id`, `client_id`, `pharmacy_user_id`, `transaction_date`, `prescription_attachment`, `client_payment`, `insurance_payout`, `total_amount`, `pharmacy_id`, `insurance_id`) VALUES
(1, 1, 1, '2024-10-05 00:42:41', '../uploads/prescriptions/presc_6701b1dc585a32.30350092_prescription-drug-sample-receipt-en.jpg', 270.00, 1530.00, 1800.00, 1, 1),
(2, 2, 1, '2024-10-05 00:43:41', '../uploads/prescriptions/presc_6701b13be5ddf8.16994819_doctor-s-prescription-pad-envelopes-1000x1000.jpg', 294.00, 1666.00, 1960.00, 1, 1),
(3, 2, 1, '2024-10-05 00:44:22', '../uploads/prescriptions/presc_6701b4f56d4e69.96814477_prescription-drug-sample-receipt-en.jpg', 540.00, 3060.00, 3600.00, 1, 1),
(4, 3, 1, '2024-10-05 00:51:54', '../uploads/prescriptions/presc_6700718ac4f416.75846702_interface.png', 1065.00, 6035.00, 7100.00, 1, 1),
(5, 4, 1, '2024-10-06 19:51:39', '../uploads/prescriptions/presc_6702ce2b55b1a7.23348685_download.png', 1200.00, 4800.00, 6000.00, 1, 3),
(9, 4, 1, '2024-10-06 22:01:17', '../uploads/prescriptions/presc_6702ec8d144178.30960155_images.jpeg', 336.00, 1344.00, 1680.00, 1, 3),
(11, 2, 3, '2024-10-06 22:07:32', '../uploads/prescriptions/presc_6702ee042f5675.14674005_images.jpeg', 192.00, 1088.00, 1280.00, 2, 1),
(12, 3, 1, '2024-10-10 00:33:45', NULL, 270.00, 1530.00, 1800.00, 1, 1),
(13, 3, 1, '2024-10-10 00:34:55', '../uploads/prescriptions/presc_6707050fca87b9.18582925_B241007155207ZYG3.pdf', 1618.50, 9171.50, 10790.00, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `transaction_medications`
--

CREATE TABLE `transaction_medications` (
  `transaction_medication_id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `medication_id` int(11) NOT NULL,
  `status` enum('approved','pending','rejected') NOT NULL DEFAULT 'pending',
  `price` decimal(12,2) NOT NULL,
  `quantity` int(13) NOT NULL,
  `rejection_comment` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transaction_medications`
--

INSERT INTO `transaction_medications` (`transaction_medication_id`, `transaction_id`, `medication_id`, `status`, `price`, `quantity`, `rejection_comment`) VALUES
(1, 1, 3, 'approved', 1800.00, 1, ''),
(2, 2, 2, 'approved', 140.00, 14, ''),
(3, 3, 2, 'approved', 140.00, 20, ''),
(4, 3, 4, 'approved', 40.00, 20, ''),
(5, 4, 2, 'approved', 140.00, 30, ''),
(6, 4, 4, 'approved', 40.00, 20, ''),
(7, 4, 2, 'approved', 140.00, 15, ''),
(8, 5, 2, 'pending', 140.00, 30, ''),
(9, 5, 3, 'pending', 1800.00, 1, ''),
(10, 9, 9, 'pending', 280.00, 6, ''),
(11, 11, 6, 'rejected', 320.00, 4, 'Prescription not clear to read it\'s contents.'),
(12, 12, 10, 'rejected', 1800.00, 1, 'No pprescription attached'),
(13, 13, 11, 'approved', 10790.00, 1, '');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `reset_key` varchar(255) DEFAULT NULL,
  `role` enum('pharmacist','pharmacy_admin','insurance_admin','admin') NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `reset_key`, `role`, `first_name`, `last_name`) VALUES
(1, 'admin', 'ndayizeyekevin6@gmail.com', '$2y$10$0E/NCsjj.ILAB5zyFxCK2.fEi1hA7V51V5avNbKAgbWht7EQ2vMLm', 'EnIiwJHUYFUfzTheA12H', 'admin', 'Kevin', 'NDAYIZEYE');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`client_id`),
  ADD KEY `insurance_id` (`insurance_id`);

--
-- Indexes for table `insurance_companies`
--
ALTER TABLE `insurance_companies`
  ADD PRIMARY KEY (`insurance_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `phonenumber` (`phonenumber`);

--
-- Indexes for table `insurance_coverage`
--
ALTER TABLE `insurance_coverage`
  ADD PRIMARY KEY (`coverage_id`),
  ADD KEY `transaction_id` (`transaction_id`),
  ADD KEY `insurance_id` (`insurance_id`);

--
-- Indexes for table `insurance_users`
--
ALTER TABLE `insurance_users`
  ADD PRIMARY KEY (`insurance_user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `insurance_id` (`insurance_id`);

--
-- Indexes for table `medications`
--
ALTER TABLE `medications`
  ADD PRIMARY KEY (`medication_id`);

--
-- Indexes for table `pharmacies`
--
ALTER TABLE `pharmacies`
  ADD PRIMARY KEY (`pharmacy_id`);

--
-- Indexes for table `pharmacy_users`
--
ALTER TABLE `pharmacy_users`
  ADD PRIMARY KEY (`pharmacy_user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `pharmacy_id` (`pharmacy_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `user_id` (`pharmacy_user_id`),
  ADD KEY `insurance_id` (`insurance_id`);

--
-- Indexes for table `transaction_medications`
--
ALTER TABLE `transaction_medications`
  ADD PRIMARY KEY (`transaction_medication_id`),
  ADD KEY `transaction_id` (`transaction_id`),
  ADD KEY `medication_id` (`medication_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `client_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `insurance_companies`
--
ALTER TABLE `insurance_companies`
  MODIFY `insurance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `insurance_coverage`
--
ALTER TABLE `insurance_coverage`
  MODIFY `coverage_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `insurance_users`
--
ALTER TABLE `insurance_users`
  MODIFY `insurance_user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `medications`
--
ALTER TABLE `medications`
  MODIFY `medication_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `pharmacies`
--
ALTER TABLE `pharmacies`
  MODIFY `pharmacy_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `pharmacy_users`
--
ALTER TABLE `pharmacy_users`
  MODIFY `pharmacy_user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `transaction_medications`
--
ALTER TABLE `transaction_medications`
  MODIFY `transaction_medication_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `clients`
--
ALTER TABLE `clients`
  ADD CONSTRAINT `clients_ibfk_1` FOREIGN KEY (`insurance_id`) REFERENCES `insurance_companies` (`insurance_id`) ON DELETE SET NULL;

--
-- Constraints for table `insurance_coverage`
--
ALTER TABLE `insurance_coverage`
  ADD CONSTRAINT `insurance_coverage_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`transaction_id`),
  ADD CONSTRAINT `insurance_coverage_ibfk_2` FOREIGN KEY (`insurance_id`) REFERENCES `insurance_companies` (`insurance_id`) ON DELETE CASCADE;

--
-- Constraints for table `insurance_users`
--
ALTER TABLE `insurance_users`
  ADD CONSTRAINT `insurance_users_ibfk_1` FOREIGN KEY (`insurance_id`) REFERENCES `insurance_companies` (`insurance_id`);

--
-- Constraints for table `pharmacy_users`
--
ALTER TABLE `pharmacy_users`
  ADD CONSTRAINT `pharmacy_users_ibfk_1` FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies` (`pharmacy_id`);

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`pharmacy_user_id`) REFERENCES `pharmacy_users` (`pharmacy_user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transactions_ibfk_3` FOREIGN KEY (`insurance_id`) REFERENCES `insurance_companies` (`insurance_id`);

--
-- Constraints for table `transaction_medications`
--
ALTER TABLE `transaction_medications`
  ADD CONSTRAINT `transaction_medications_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`transaction_id`),
  ADD CONSTRAINT `transaction_medications_ibfk_2` FOREIGN KEY (`medication_id`) REFERENCES `medications` (`medication_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
