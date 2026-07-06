-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jul 03, 2026 at 12:59 PM
-- Server version: 8.0.45-0ubuntu0.24.04.1
-- PHP Version: 8.3.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dental_clinic`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int NOT NULL,
  `clinic_id` int NOT NULL,
  `patient_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `patient_phone` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `service` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `time_slot` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `source` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'online',
  `problem_note` text COLLATE utf8mb4_unicode_ci,
  `is_emergency` tinyint(1) NOT NULL DEFAULT '0',
  `treatment_performed` text COLLATE utf8mb4_unicode_ci,
  `doctor_notes` text COLLATE utf8mb4_unicode_ci,
  `medicines_instructions` text COLLATE utf8mb4_unicode_ci,
  `follow_up_date` date DEFAULT NULL,
  `follow_up_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `follow_up_completed` tinyint(1) NOT NULL DEFAULT '0',
  `treatment_cost` decimal(10,2) DEFAULT NULL,
  `discount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `amount_paid` decimal(10,2) NOT NULL DEFAULT '0.00',
  `payment_method` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `clinic_id`, `patient_name`, `patient_phone`, `service`, `date`, `time_slot`, `status`, `source`, `problem_note`, `is_emergency`, `treatment_performed`, `doctor_notes`, `medicines_instructions`, `follow_up_date`, `follow_up_note`, `follow_up_completed`, `treatment_cost`, `discount`, `amount_paid`, `payment_method`, `payment_status`) VALUES
(1, 1, 'vikas', '9754640521', 'General Checkup', '2026-06-29', '10:00', 'confirmed', 'phone', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0.00, 0.00, NULL, 'unpaid'),
(2, 1, 'yogendra', '+919754640521', 'General Checkup', '2026-06-30', '12:30', 'no_show', 'online', 'severe pain', 1, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0.00, 0.00, NULL, 'unpaid'),
(3, 1, 'yogendra', '+919754640521', 'General Checkup', '2026-07-01', '10:30', 'pending', 'online', 'pain in teeth', 1, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0.00, 0.00, NULL, 'unpaid'),
(4, 1, 'Yogendra Sisodiya', '+919754640521', 'Teeth Cleaning', '2026-06-29', '10:30', 'confirmed', 'phone', 'pain happening', 1, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0.00, 0.00, NULL, 'unpaid'),
(5, 1, 'Vikas sharma', '+919926719320', 'General Checkup', '2026-07-01', '12:00', 'pending', 'online', 'Wisdom tooth pain issue', 0, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0.00, 0.00, NULL, 'unpaid'),
(6, 1, 'rani yadav', '+919754640521', 'Teeth Cleaning', '2026-06-30', '14:45', 'confirmed', 'walkin', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0.00, 0.00, NULL, 'unpaid'),
(7, 1, 'jitendra sharma', '+919786574321', 'General Checkup', '2026-06-30', '12:00', 'confirmed', 'phone', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0.00, 0.00, NULL, 'unpaid'),
(8, 1, 'yogendra sisodiya', '+919754640521', 'pain in the tooth', '2026-07-06', '18:00', 'cancelled', 'online', 'having pain in the wisdon tooth', 0, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0.00, 0.00, NULL, 'unpaid'),
(9, 1, 'vikas patil', '+910026719320', 'Dental Checkup & X-Rays', '2026-07-04', '14:30', 'pending', 'online', 'teeth checkup', 0, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0.00, 0.00, NULL, 'unpaid'),
(10, 1, 'manoj sharma', '+919617145663', 'Dental Checkup & X-Rays', '2026-07-06', '15:30', 'pending', 'online', 'teeth checkup', 0, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0.00, 0.00, NULL, 'unpaid'),
(11, 1, 'rani yadav', '+919765460543', 'Dental Checkup & X-Rays', '2026-07-09', '17:00', 'pending', 'online', 'dental checkup', 0, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0.00, 0.00, NULL, 'unpaid'),
(12, 1, 'Swati kunrawat', '+919926719320', 'Dental Checkup & X-Rays', '2026-07-03', '10:00', 'confirmed', 'phone', '', 1, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0.00, 0.00, NULL, 'unpaid');

-- --------------------------------------------------------

--
-- Table structure for table `clinics`
--

CREATE TABLE `clinics` (
  `id` int NOT NULL,
  `username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `services` json NOT NULL,
  `working_hours` json NOT NULL,
  `slot_duration_min` int NOT NULL DEFAULT '30',
  `contact_phone` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_map_url` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `admin_password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `admin_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slot_mode` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'fixed',
  `custom_slots` json DEFAULT NULL,
  `custom_domain` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `doctor_pin_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_otp` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_otp_expires` datetime DEFAULT NULL,
  `google_review_link` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `config` json DEFAULT NULL,
  `visibility_settings` json DEFAULT NULL,
  `whatsapp_phone_number_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviews` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `clinics`
--

INSERT INTO `clinics` (`id`, `username`, `name`, `services`, `working_hours`, `slot_duration_min`, `contact_phone`, `contact_address`, `contact_map_url`, `admin_password_hash`, `admin_email`, `slot_mode`, `custom_slots`, `custom_domain`, `doctor_pin_hash`, `reset_otp`, `reset_otp_expires`, `google_review_link`, `config`, `visibility_settings`, `whatsapp_phone_number_id`, `reviews`) VALUES
(1, 'clinic_001', 'Your dental care', '[{\"id\": \"svc_1\", \"name\": \"Dental Checkup & X-Rays\", \"image\": \"/uploads/assets/clinic_001/service/service_1.png\", \"description\": \"Comprehensive oral examination with digital X-rays to detect cavities, gum disease, and other issues early.\", \"durationMin\": 30, \"priceDisplay\": \"₹500\"}, {\"id\": \"svc_2\", \"name\": \"Orthodontics (Braces)\", \"image\": \"/uploads/assets/clinic_001/service/service_2.png\", \"description\": \"Metal and ceramic braces to correct misaligned teeth and bite for a confident, straight smile.\", \"durationMin\": 60, \"priceDisplay\": \"₹1200\"}, {\"id\": \"svc_3\", \"name\": \"Dental Implants\", \"image\": \"/uploads/assets/clinic_001/service/service_3.png\", \"description\": \"Permanent titanium implants that look, feel, and function like natural teeth for missing tooth replacement.\", \"durationMin\": 60, \"priceDisplay\": \"₹4000\"}, {\"id\": \"svc_4\", \"name\": \"Crowns and Bridges\", \"image\": \"/uploads/assets/clinic_001/service/service_4.png\", \"description\": \"Custom-crafted ceramic crowns and bridges to restore damaged or missing teeth with natural appearance.\", \"durationMin\": 60, \"priceDisplay\": \"₹6500\"}, {\"id\": \"svc_5\", \"name\": \"Root Canal Treatment (RCT)\", \"image\": \"/uploads/assets/clinic_001/service/service_5.png\", \"description\": \"Pain-free root canal therapy to save infected or severely damaged teeth and eliminate tooth pain.\", \"durationMin\": 60, \"priceDisplay\": \"\"}, {\"id\": \"svc_6\", \"name\": \"Teeth Whitening & Bleaching\", \"image\": \"/uploads/assets/clinic_001/service/service_6.png\", \"description\": \"Professional in-office whitening treatment to remove stains and brighten your smile by several shades.\", \"durationMin\": 45, \"priceDisplay\": \"\"}, {\"id\": \"svc_7\", \"name\": \"Teeth Cleaning & Polishing\", \"image\": \"/uploads/assets/clinic_001/service/service_7.png\", \"description\": \"Professional scaling and polishing to remove tartar, plaque, and stains for healthier gums and teeth.\", \"durationMin\": 30, \"priceDisplay\": \"\"}, {\"id\": \"svc_8\", \"name\": \"Smile Designing\", \"image\": \"/uploads/assets/clinic_001/service/service_8.png\", \"description\": \"Complete smile makeover combining whitening, veneers, and contouring to create your perfect smile.\", \"durationMin\": 60, \"priceDisplay\": \"\"}, {\"id\": \"svc_9\", \"name\": \"Kids Dentistry\", \"image\": \"/uploads/assets/clinic_001/service/service_9.png\", \"description\": \"Gentle, friendly dental care for children including checkups, fluoride, and sealants in a kid-safe environment.\", \"durationMin\": 30, \"priceDisplay\": \"\"}, {\"id\": \"svc_10\", \"name\": \"Wisdom Teeth Extraction\", \"image\": \"/uploads/assets/clinic_001/service/service_10.png\", \"description\": \"Safe and comfortable removal of wisdom teeth causing pain, crowding, or infection.\", \"durationMin\": 45, \"priceDisplay\": \"\"}, {\"id\": \"svc_11\", \"name\": \"Tooth Colored Fillings\", \"image\": \"/uploads/assets/clinic_001/service/service_11.png\", \"description\": \"Natural-looking composite resin fillings that blend perfectly with your teeth — no dark metal.\", \"durationMin\": 30, \"priceDisplay\": \"\"}, {\"id\": \"svc_12\", \"name\": \"Aligners and Gum Surgery\", \"image\": \"/uploads/assets/clinic_001/service/service_12.png\", \"description\": \"Clear removable aligners for discreet teeth straightening, plus gum contouring for a balanced smile.\", \"durationMin\": 60, \"priceDisplay\": \"\"}, {\"id\": \"svc_13\", \"name\": \"Full Mouth Rehabilitation\", \"image\": \"/uploads/assets/clinic_001/service/service_13.png\", \"description\": \"Comprehensive restoration of all teeth combining implants, crowns, and cosmetic treatments.\", \"durationMin\": 90, \"priceDisplay\": \"\"}, {\"id\": \"svc_14\", \"name\": \"Facial Aesthetic\", \"image\": \"/uploads/assets/clinic_001/service/service_14.png\", \"description\": \"Non-surgical facial treatments including Botox and fillers to complement your dental transformation.\", \"durationMin\": 45, \"priceDisplay\": \"\"}, {\"id\": \"svc_15\", \"name\": \"Cosmetic & Laser Dental Treatment\", \"image\": \"/uploads/assets/clinic_001/service/service_15.png\", \"description\": \"Advanced laser procedures for painless gum reshaping, cavity treatment, and smile enhancement.\", \"durationMin\": 60, \"priceDisplay\": \"\"}, {\"id\": \"svc_16\", \"name\": \"Dental Veneers and Laminates\", \"image\": \"/uploads/assets/clinic_001/service/service_16.png\", \"description\": \"Ultra-thin porcelain shells bonded to teeth to correct shape, color, and size for a flawless smile.\", \"durationMin\": 60, \"priceDisplay\": \"\"}]', '{\"fri\": {\"open\": \"10:00\", \"close\": \"19:00\"}, \"mon\": {\"open\": \"10:00\", \"close\": \"19:00\"}, \"sat\": {\"open\": \"10:00\", \"close\": \"15:00\"}, \"sun\": null, \"thu\": {\"open\": \"10:00\", \"close\": \"19:00\"}, \"tue\": {\"open\": \"10:00\", \"close\": \"19:00\"}, \"wed\": {\"open\": \"10:00\", \"close\": \"19:00\"}}', 30, '+91XXXXXXXXXX', '123 MG Road, City', 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d29436.842455096437!2d75.8874112!3d22.7429071!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x39631d54503f21d3%3A0xc114629679b13584!2sBombay%20Hospital!5e0!3m2!1sen!2sin!4v1782892257006!5m2!1sen!2sin', '$2y$10$HriNYjFePXSSV7vSb/tLBelMOqvA8NVZyBcR7g75h6KIca2I1ahn6', 'drvikas@gmail.com', 'fixed', NULL, 'drvikas.dental.com', NULL, NULL, NULL, '', '{\"hero\": {\"stats\": [{\"label\": \"Happy Patients\", \"value\": 500, \"suffix\": \"+\"}, {\"label\": \"Years Experience\", \"value\": 10, \"suffix\": \"+\"}, {\"label\": \"Sterilized Clinic\", \"value\": 100, \"suffix\": \"%\"}], \"subtext\": \"From the first consultation to a lifetime of care – we are with you every step of your dental journey.\", \"headline\": \"A Healthy Smile<br>Begins Here\", \"badgeText\": \"Your Smile, Our Priority\", \"heroImage\": \"/uploads/assets/clinic_001/hero/hero.jpg\", \"heroVideo\": \"/uploads/assets/clinic_001/video/hero-loop.mp4\", \"journeySteps\": [{\"image\": \"/uploads/assets/clinic_001/step/hero_card1.png\", \"label\": \"Consultation & Planning\", \"description\": \"Choose your convenient date & time.\"}, {\"image\": \"/uploads/assets/clinic_001/step/hero_card2.png\", \"label\": \"Diagnostics & X-Rays\", \"description\": \"Advanced scan & expert evaluation.\"}, {\"image\": \"/uploads/assets/clinic_001/step/hero_card3.png\", \"label\": \"Personalized Care\", \"description\": \"Personalized care with comfort & precision.\"}, {\"image\": \"/uploads/assets/clinic_001/step/hero_card4.png\", \"label\": \"Healthy Maintenance\", \"description\": \"Long-term care for a healthy, beautiful smile.\"}], \"journeyTitle\": \"Your Dental Journey\", \"floatingBadge\": {\"title\": \"ISO Certified Clinic\", \"subtitle\": \"All equipment sterilized\"}}, \"logo\": \"/uploads/assets/clinic_001/logo/logo.png\", \"name\": \"Your dental care\", \"theme\": {\"font\": \"Outfit\", \"preset\": \"warm\", \"cardStyle\": \"soft\", \"iconStyle\": \"line\", \"heroLayout\": \"journey\", \"accentColor\": \"#2DD4BF\", \"defaultLanguage\": \"en\"}, \"contact\": {\"mapEmbedUrl\": \"https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d29436.842455096437!2d75.8874112!3d22.7429071!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x39631d54503f21d3%3A0xc114629679b13584!2sBombay%20Hospital!5e0!3m2!1sen!2sin!4v1782892257006!5m2!1sen!2sin\"}, \"doctors\": [{\"bio\": \"12+ years experience in family dentistry.\", \"name\": \"Dr. Anita Sharma\", \"photo\": \"/uploads/assets/clinic_001/doctor/doctor1.jpg\", \"credentials\": [\"Certified Dental Practitioner\", \"Member of IDA\", \"Invisalign Specialist\"], \"description\": \"Dr. Anita Sharma is an Orthodontist and Aesthetic dentist who believes that only quality work leads to a great clinical practice.\", \"qualification\": \"BDS, MDS (Orthodontics)\"}], \"tagline\": \"Modern Technology. Compassionate Care. Beautiful Results.\", \"whatsapp\": {\"features\": [], \"connected\": 1, \"access_token\": \"\", \"clinicNumber\": \"+91XXXXXXXXXX\", \"phone_number_id\": \"\", \"business_account_id\": \"\", \"confirmation_enabled\": true}, \"super_admin_only\": true, \"google_review_link\": \"\"}', '{\"show_gallery\": true, \"show_pricing\": true, \"show_ratings\": true, \"show_lead_form\": true, \"show_stats_bar\": true, \"show_whatsapp_fab\": true, \"show_working_hours\": true, \"show_doctor_section\": true, \"show_google_review_btn\": true}', NULL, '[{\"name\": \"Priya Sharma\", \"text\": \"Excellent experience! Dr. Sharma was very gentle and explained everything clearly. My root canal was completely painless.\", \"rating\": 5}, {\"name\": \"Rahul Mehta\", \"text\": \"Best dental clinic in the area. Staff is friendly and clinic is spotless. Highly recommend for families.\", \"rating\": 5}, {\"name\": \"Anjali Verma\", \"text\": \"I was terrified of dentists but they made me feel so comfortable. My smile transformation has boosted my confidence!\", \"rating\": 4}, {\"name\": \"Vikram Patel\", \"text\": \"Very professional team. Got my teeth cleaned and they look amazing. Booking was super easy through the website.\", \"rating\": 5}, {\"name\": \"yogendra\", \"text\": \"Very professional team. Got my teeth cleaned and they look amazing. Booking was super easy through the website.\", \"rating\": 5}]');


CREATE TABLE `gallery` (
  `id` int NOT NULL,
  `clinic_id` int NOT NULL,
  `type` enum('single','single_ba','before_after') NOT NULL DEFAULT 'single',
  `image_url` varchar(500) DEFAULT NULL,
  `before_url` varchar(500) DEFAULT NULL,
  `after_url` varchar(500) DEFAULT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 utf8mb4_unicode_ci;

--
-- Dumping data for table `gallery`
--

INSERT INTO `gallery` (`id`, `clinic_id`, `type`, `image_url`, `before_url`, `after_url`, `caption`, `created_at`) VALUES
(11, 1, 'single', '/uploads/assets/clinic_001/gallery/1783062375-136b8916a4530d34.png', NULL, NULL, '', '2026-07-03 12:36:15'),
(12, 1, 'single', '/uploads/assets/clinic_001/gallery/1783062384-c4ccc340122f9229.jpg', NULL, NULL, '', '2026-07-03 12:36:24'),
(13, 1, 'single', '/uploads/assets/clinic_001/gallery/1783062392-d1c55fcd5f71b13b.jpeg', NULL, NULL, '', '2026-07-03 12:36:32'),
(14, 1, 'single', '/uploads/assets/clinic_001/gallery/1783062402-e70008fb5c4ff4eb.webp', NULL, NULL, '', '2026-07-03 12:36:42');

-- --------------------------------------------------------

--
-- Table structure for table `leads`
--

CREATE TABLE `leads` (
  `id` int NOT NULL,
  `clinic_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `interested_service` varchar(255) DEFAULT NULL,
  `notes` text,
  `status` varchar(20) NOT NULL DEFAULT 'new',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 utf8mb4_unicode_ci;

--
-- Dumping data for table `leads`
--

INSERT INTO `leads` (`id`, `clinic_id`, `name`, `phone`, `interested_service`, `notes`, `status`, `created_at`) VALUES
(1, 1, 'yogendra sisodiya', '+919754640521', 'Other / General Inquiry', NULL, 'new', '2026-06-29 07:36:17');

-- --------------------------------------------------------

--
-- Table structure for table `patient_documents`
--

CREATE TABLE `patient_documents` (
  `id` int NOT NULL,
  `clinic_id` int NOT NULL,
  `patient_phone` varchar(50) NOT NULL,
  `appointment_id` int DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `slots_blocked`
--

CREATE TABLE `slots_blocked` (
  `id` int NOT NULL,
  `clinic_id` int NOT NULL,
  `date` date NOT NULL,
  `time_slot` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `slots_blocked`
--

INSERT INTO `slots_blocked` (`id`, `clinic_id`, `date`, `time_slot`) VALUES
(2, 1, '2026-07-03', '17:00');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_clinic_phone` (`clinic_id`,`patient_phone`),
  ADD KEY `idx_clinic_followup` (`clinic_id`,`follow_up_date`),
  ADD KEY `idx_clinic_date` (`clinic_id`,`date`),
  ADD KEY `idx_clinic_phone_apt` (`clinic_id`,`patient_phone`),
  ADD KEY `idx_clinic_date_status` (`clinic_id`,`date`,`status`),
  ADD KEY `idx_clinic_slot` (`clinic_id`,`date`,`time_slot`);

--
-- Indexes for table `clinics`
--
ALTER TABLE `clinics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_whatsapp_phone_number_id` (`whatsapp_phone_number_id`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_custom_domain` (`custom_domain`);

--
-- Indexes for table `gallery`
--
ALTER TABLE `gallery`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_clinic_id` (`clinic_id`);

--
-- Indexes for table `leads`
--
ALTER TABLE `leads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_clinic_id_leads` (`clinic_id`);

--
-- Indexes for table `patient_documents`
--
ALTER TABLE `patient_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `idx_clinic_phone` (`clinic_id`,`patient_phone`);

--
-- Indexes for table `slots_blocked`
--
ALTER TABLE `slots_blocked`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_clinic_date_slot` (`clinic_id`,`date`,`time_slot`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `clinics`
--
ALTER TABLE `clinics`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `gallery`
--
ALTER TABLE `gallery`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `leads`
--
ALTER TABLE `leads`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `patient_documents`
--
ALTER TABLE `patient_documents`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `slots_blocked`
--
ALTER TABLE `slots_blocked`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `gallery`
--
ALTER TABLE `gallery`
  ADD CONSTRAINT `gallery_ibfk_1` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leads`
--
ALTER TABLE `leads`
  ADD CONSTRAINT `leads_ibfk_1` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patient_documents`
--
ALTER TABLE `patient_documents`
  ADD CONSTRAINT `patient_documents_ibfk_1` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `patient_documents_ibfk_2` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `slots_blocked`
--
ALTER TABLE `slots_blocked`
  ADD CONSTRAINT `slots_blocked_ibfk_1` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
