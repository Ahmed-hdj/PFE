-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : sam. 24 mai 2025 à 13:07
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `pfe`
--

-- --------------------------------------------------------

--
-- Structure de la table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`) VALUES
(1, 'Cultural and Heritage'),
(2, 'Religious'),
(3, 'Beaches'),
(4, 'Desert'),
(5, 'Museums'),
(6, 'Shopping'),
(7, 'Nature'),
(8, 'Sport'),
(9, 'Amusement');

-- --------------------------------------------------------

--
-- Structure de la table `lieu`
--

CREATE TABLE `lieu` (
  `lieu_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `wilaya_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `content` text NOT NULL,
  `location` varchar(100) NOT NULL,
  `status` enum('pending','approved') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `lieu`
--

INSERT INTO `lieu` (`lieu_id`, `user_id`, `category_id`, `wilaya_id`, `title`, `content`, `location`, `status`, `created_at`) VALUES
(2, 8, 7, 10, 'djurdjura', 'des belles montanges au millieux de la kabylie entre bouira et tizi ouzo', 'Djurdjura, Saharidj', 'approved', '2025-05-19 19:49:58'),
(3, 10, 1, 16, 'casbah', 'une ancienne vile de l\'ere otmanienne', 'Casbah,Alger', 'approved', '2025-05-20 15:04:29'),
(8, 1, 4, 11, 'hoggar', 'Le Hoggar est un massif montagneux circulaire au cœur du Sahara central, sous le tropique du Cancer. Dominé par le plateau de l\'Assekrem', 'hoggar,tamanrasset', '', '2025-05-23 14:25:28'),
(10, 8, 2, 16, 'grande mosquee d\'Alger', ' est une grande mosquée située à Alger, en Algérie. La mosquée est achevée en avril 2019. Elle est la plus grande mosquée d\'Afrique et la troisième plus grande mosquée du monde après celles de Médine et de La Mecque', 'Mohammadia,Alger', 'approved', '2025-05-23 19:55:57'),
(11, 1, 4, 11, 'hoggar', 'Le Hoggar est un massif montagneux circulaire au cœur du Sahara central, sous le tropique du Cancer. Dominé par le plateau de l\'Assekrem, il s\'élève à altitude moyenne de 900 mètres. Il se trouve intégralement en Algérie.', 'hoggar,Tamanrasset', 'approved', '2025-05-23 20:08:56');

-- --------------------------------------------------------

--
-- Structure de la table `lieu_comments`
--

CREATE TABLE `lieu_comments` (
  `comment_id` int(11) NOT NULL,
  `lieu_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `lieu_comments`
--

INSERT INTO `lieu_comments` (`comment_id`, `lieu_id`, `user_id`, `content`, `created_at`) VALUES
(1, 2, 8, 'beutiful', '2025-05-19 20:55:02'),
(2, 2, 8, 'beutiful', '2025-05-19 20:56:14'),
(3, 2, 7, 'amazing', '2025-05-19 20:58:29'),
(4, 2, 10, 'wondrful', '2025-05-19 21:03:18'),
(5, 2, 10, 'wonderful', '2025-05-19 21:16:02');

-- --------------------------------------------------------

--
-- Structure de la table `lieu_images`
--

CREATE TABLE `lieu_images` (
  `image_id` int(11) NOT NULL,
  `lieu_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status_photo` enum('approved','pending') DEFAULT 'approved'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `lieu_images`
--

INSERT INTO `lieu_images` (`image_id`, `lieu_id`, `user_id`, `image_url`, `uploaded_at`, `status_photo`) VALUES
(1, 2, 8, 'uploads/places/682b8b6645961.jpeg', '2025-05-19 19:49:58', 'approved'),
(2, 3, 10, 'uploads/places/682c99fd46a77.jpeg', '2025-05-20 15:04:29', 'pending'),
(4, 3, 1, 'uploads/places/682dae97f22e4.jpeg', '2025-05-21 10:44:39', 'approved'),
(9, 8, 1, 'uploads/places/683085584b96d_images (4).jpeg', '2025-05-23 14:25:28', 'approved'),
(11, 10, 8, 'uploads/places/6830d2cd90238.jpeg', '2025-05-23 19:55:57', 'approved'),
(12, 11, 1, 'uploads/places/6830d5d84d743.jpg', '2025-05-23 20:08:56', 'approved'),
(14, 10, 8, 'uploads/lieu_images/68319c9979cdf_images (6).jpeg', '2025-05-24 10:16:57', 'approved'),
(15, 11, 8, 'uploads/lieu_images/68319fb1c52f5_images (4).jpeg', '2025-05-24 10:30:09', 'pending');

-- --------------------------------------------------------

--
-- Structure de la table `lieu_ratings`
--

CREATE TABLE `lieu_ratings` (
  `rating_id` int(11) NOT NULL,
  `lieu_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` between 1 and 5),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `lieu_ratings`
--

INSERT INTO `lieu_ratings` (`rating_id`, `lieu_id`, `user_id`, `rating`, `created_at`) VALUES
(3, 2, 10, 5, '2025-05-20 16:58:57'),
(4, 2, 8, 3, '2025-05-20 17:15:48'),
(5, 3, 12, 5, '2025-05-21 15:43:51'),
(7, 11, 1, 5, '2025-05-23 20:09:44'),
(8, 10, 8, 4, '2025-05-23 20:11:39');

-- --------------------------------------------------------

--
-- Structure de la table `lieu_saves`
--

CREATE TABLE `lieu_saves` (
  `save_id` int(11) NOT NULL,
  `lieu_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `lieu_saves`
--

INSERT INTO `lieu_saves` (`save_id`, `lieu_id`, `user_id`, `created_at`) VALUES
(24, 2, 1, '2025-05-23 13:05:37'),
(33, 3, 1, '2025-05-23 15:27:29');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `city` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_picture` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `city`, `password`, `role`, `created_at`, `profile_picture`, `bio`) VALUES
(1, 'Chachoua Rahim', 'chachouarahim6@gmail.com', 'Alger', '$2y$10$pF/AdK8tQfoVUA8e2C1lBesh6QWUCQ/NBjr6IXtlRbgXtCDGKpY4S', 'admin', '2025-05-18 18:36:55', '682dc7f6f23a85.01879493.jpeg', NULL),
(7, 'Chachoua Walid', 'chwalid13@gmail.com', 'lille', '$2y$10$U1KOgIgke.RbRs0sfNtWkus2ZvNdT8chWVZXCfEMc91V43ETubmaC', 'user', '2025-05-18 18:48:52', '682a2b9445ca3.webp', NULL),
(8, 'hmed japonais', 'hmed1@gmail.com', 'Alger', '$2y$10$MC/cVEx6j3KGPNDoXaOFL.lEiOq1XFiFvzPlq7By2N2/Ojscb2x.K', 'user', '2025-05-19 10:02:26', NULL, NULL),
(10, 'nazim idir', 'nazim1@gmail.com', 'Alger', '$2y$10$hNQN72wILMLyRcOjJiZcM.4AjRDeJtLldj3DUXPcwuoh8N92BlpaC', 'user', '2025-05-19 21:02:45', '682b9c754778a.webp', NULL),
(12, 'walid saidi', 'walid@gmail.com', 'Alger', '$2y$10$gnmPdsJL/GBHRyTndOhsxeH2WnMYmmas2fvF8.lGMQC9LhJtxtmz2', 'admin', '2025-05-21 15:37:56', '682df35494f71.jpeg', NULL),
(13, 'bentilijan souheil', 'souheil@gmail.com', 'Alger', '$2y$10$uwEcD5KCSMdfhb6fxcRExOK0StkEq5/g50KSUu2AOWo5M7ZBdKtGO', 'admin', '2025-05-24 10:58:30', '6831a656901cb.png', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `wilayas`
--

CREATE TABLE `wilayas` (
  `wilaya_number` int(11) NOT NULL,
  `wilaya_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `wilayas`
--

INSERT INTO `wilayas` (`wilaya_number`, `wilaya_name`) VALUES
(1, 'Adrar'),
(2, 'Chlef'),
(3, 'Laghouat'),
(4, 'Oum El Bouaghi'),
(5, 'Batna'),
(6, 'Béjaïa'),
(7, 'Biskra'),
(8, 'Béchar'),
(9, 'Blida'),
(10, 'Bouira'),
(11, 'Tamanrasset'),
(12, 'Tébessa'),
(13, 'Tlemcen'),
(14, 'Tiaret'),
(15, 'Tizi Ouzou'),
(16, 'Alger'),
(17, 'Djelfa'),
(18, 'Jijel'),
(19, 'Sétif'),
(20, 'Saïda'),
(21, 'Skikda'),
(22, 'Sidi Bel Abbès'),
(23, 'Annaba'),
(24, 'Guelma'),
(25, 'Constantine'),
(26, 'Médéa'),
(27, 'Mostaganem'),
(28, 'MSila'),
(29, 'Mascara'),
(30, 'Ouargla'),
(31, 'Oran'),
(32, 'El Bayadh'),
(33, 'Illizi'),
(34, 'Bordj Bou Arréridj'),
(35, 'Boumerdès'),
(36, 'El Tarf'),
(37, 'Tindouf'),
(38, 'Tissemsilt'),
(39, 'El Oued'),
(40, 'Khenchela'),
(41, 'Souk Ahras'),
(42, 'Tipaza'),
(43, 'Mila'),
(44, 'Aïn Defla'),
(45, 'Naâma'),
(46, 'Aïn Témouchent'),
(47, 'Ghardaïa'),
(48, 'Relizane'),
(49, 'Timimoun'),
(50, 'Bordj Badji Mokhtar'),
(51, 'Ouled Djellal'),
(52, 'Béni Abbès'),
(53, 'In Salah'),
(54, 'In Guezzam'),
(55, 'Touggourt'),
(56, 'Djanet'),
(57, 'El M\'Ghair'),
(58, 'El Meniaa');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Index pour la table `lieu`
--
ALTER TABLE `lieu`
  ADD PRIMARY KEY (`lieu_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `wilaya_id` (`wilaya_id`);

--
-- Index pour la table `lieu_comments`
--
ALTER TABLE `lieu_comments`
  ADD PRIMARY KEY (`comment_id`),
  ADD KEY `lieu_id` (`lieu_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `lieu_images`
--
ALTER TABLE `lieu_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `lieu_id` (`lieu_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `lieu_ratings`
--
ALTER TABLE `lieu_ratings`
  ADD PRIMARY KEY (`rating_id`),
  ADD UNIQUE KEY `unique_rating` (`lieu_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `lieu_saves`
--
ALTER TABLE `lieu_saves`
  ADD PRIMARY KEY (`save_id`),
  ADD UNIQUE KEY `unique_like` (`lieu_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Index pour la table `wilayas`
--
ALTER TABLE `wilayas`
  ADD PRIMARY KEY (`wilaya_number`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `lieu`
--
ALTER TABLE `lieu`
  MODIFY `lieu_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT pour la table `lieu_comments`
--
ALTER TABLE `lieu_comments`
  MODIFY `comment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `lieu_images`
--
ALTER TABLE `lieu_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT pour la table `lieu_ratings`
--
ALTER TABLE `lieu_ratings`
  MODIFY `rating_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `lieu_saves`
--
ALTER TABLE `lieu_saves`
  MODIFY `save_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `lieu`
--
ALTER TABLE `lieu`
  ADD CONSTRAINT `lieu_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `lieu_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`),
  ADD CONSTRAINT `lieu_ibfk_3` FOREIGN KEY (`wilaya_id`) REFERENCES `wilayas` (`wilaya_number`);

--
-- Contraintes pour la table `lieu_comments`
--
ALTER TABLE `lieu_comments`
  ADD CONSTRAINT `lieu_comments_ibfk_1` FOREIGN KEY (`lieu_id`) REFERENCES `lieu` (`lieu_id`),
  ADD CONSTRAINT `lieu_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Contraintes pour la table `lieu_images`
--
ALTER TABLE `lieu_images`
  ADD CONSTRAINT `lieu_images_ibfk_1` FOREIGN KEY (`lieu_id`) REFERENCES `lieu` (`lieu_id`),
  ADD CONSTRAINT `lieu_images_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Contraintes pour la table `lieu_ratings`
--
ALTER TABLE `lieu_ratings`
  ADD CONSTRAINT `lieu_ratings_ibfk_1` FOREIGN KEY (`lieu_id`) REFERENCES `lieu` (`lieu_id`),
  ADD CONSTRAINT `lieu_ratings_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Contraintes pour la table `lieu_saves`
--
ALTER TABLE `lieu_saves`
  ADD CONSTRAINT `lieu_saves_ibfk_1` FOREIGN KEY (`lieu_id`) REFERENCES `lieu` (`lieu_id`),
  ADD CONSTRAINT `lieu_saves_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
