-- MySQL dump 10.13  Distrib 5.7.39, for osx10.12 (x86_64)
--
-- Host: 127.0.0.1    Database: blueFoods
-- ------------------------------------------------------
-- Server version	5.7.39

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `entity` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` bigint(20) unsigned NOT NULL,
  `action` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `before_json` json DEFAULT NULL,
  `after_json` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_logs_user_id_foreign` (`user_id`),
  KEY `audit_logs_entity_entity_id_index` (`entity`,`entity_id`),
  KEY `audit_logs_created_at_index` (`created_at`),
  CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,NULL,'user',6,'created',NULL,'{\"id\": 6, \"name\": \"Micky Kwachie\", \"role\": \"management\", \"email\": \"manager@bluefoods.test\", \"phone\": \"+233200000001\", \"status\": \"active\", \"created_at\": \"2026-10-14 20:22:41\"}','2026-10-14 20:22:41'),(2,NULL,'user',7,'created',NULL,'{\"id\": 7, \"name\": \"Warehouse Desk\", \"role\": \"warehouse\", \"email\": \"warehouse@bluefoods.test\", \"phone\": \"+233200000002\", \"status\": \"active\", \"created_at\": \"2026-10-14 20:22:41\"}','2026-10-14 20:22:41'),(3,NULL,'user',8,'created',NULL,'{\"id\": 8, \"name\": \"Ama Owusu\", \"role\": \"marketing\", \"email\": \"ama@bluefoods.test\", \"phone\": \"+233200000003\", \"status\": \"active\", \"created_at\": \"2026-10-14 20:22:41\"}','2026-10-14 20:22:41'),(4,NULL,'user',9,'created',NULL,'{\"id\": 9, \"name\": \"Kofi Mensah\", \"role\": \"marketing\", \"email\": \"kofi@bluefoods.test\", \"phone\": \"+233200000004\", \"status\": \"active\", \"created_at\": \"2026-10-14 20:22:42\"}','2026-10-14 20:22:42'),(5,NULL,'product',1,'created',NULL,'{\"id\": 1, \"sku\": \"BF-TIL-500\", \"name\": \"Smoked tilapia 500g\", \"unit\": \"pack\", \"status\": \"active\", \"category\": \"Smoked fish\", \"created_at\": \"2026-10-14 20:22:42\", \"reorder_level\": 30}','2026-10-14 20:22:42'),(6,NULL,'product_price',1,'created',NULL,'{\"id\": 1, \"set_by\": 6, \"created_at\": \"2026-10-14 20:22:42\", \"product_id\": 1, \"effective_from\": \"2026-10-14 20:22:42\", \"unit_price_pesewas\": 3550}','2026-10-14 20:22:42'),(7,NULL,'product',2,'created',NULL,'{\"id\": 2, \"sku\": \"BF-MAC-1KG\", \"name\": \"Frozen mackerel 1kg\", \"unit\": \"pack\", \"status\": \"active\", \"category\": \"Frozen fish\", \"created_at\": \"2026-10-14 20:22:42\", \"reorder_level\": 40}','2026-10-14 20:22:42'),(8,NULL,'product_price',2,'created',NULL,'{\"id\": 2, \"set_by\": 6, \"created_at\": \"2026-10-14 20:22:42\", \"product_id\": 2, \"effective_from\": \"2026-10-14 20:22:42\", \"unit_price_pesewas\": 4200}','2026-10-14 20:22:42'),(9,NULL,'product',3,'created',NULL,'{\"id\": 3, \"sku\": \"BF-SHR-250\", \"name\": \"Dried shrimp 250g\", \"unit\": \"pack\", \"status\": \"active\", \"category\": \"Seafood\", \"created_at\": \"2026-10-14 20:22:42\", \"reorder_level\": 25}','2026-10-14 20:22:42'),(10,NULL,'product_price',3,'created',NULL,'{\"id\": 3, \"set_by\": 6, \"created_at\": \"2026-10-14 20:22:42\", \"product_id\": 3, \"effective_from\": \"2026-10-14 20:22:42\", \"unit_price_pesewas\": 2800}','2026-10-14 20:22:42'),(11,NULL,'product',4,'created',NULL,'{\"id\": 4, \"sku\": \"BF-SAR-TRY\", \"name\": \"Fresh sardines tray\", \"unit\": \"tray\", \"status\": \"active\", \"category\": \"Fresh fish\", \"created_at\": \"2026-10-14 20:22:42\", \"reorder_level\": 15}','2026-10-14 20:22:42'),(12,NULL,'product_price',4,'created',NULL,'{\"id\": 4, \"set_by\": 6, \"created_at\": \"2026-10-14 20:22:42\", \"product_id\": 4, \"effective_from\": \"2026-10-14 20:22:42\", \"unit_price_pesewas\": 6000}','2026-10-14 20:22:42'),(13,NULL,'stock_movement',1,'created',NULL,'{\"id\": 1, \"type\": \"PRODUCED\", \"quantity\": 200, \"created_at\": \"2026-10-14 20:22:42\", \"created_by\": 7, \"product_id\": 1, \"occurred_at\": \"2026-10-11 00:00:00\", \"to_location_id\": 1}','2026-10-14 20:22:42'),(14,NULL,'stock_movement',2,'created',NULL,'{\"id\": 2, \"type\": \"PRODUCED\", \"quantity\": 150, \"created_at\": \"2026-10-14 20:22:42\", \"created_by\": 7, \"product_id\": 2, \"occurred_at\": \"2026-10-12 00:00:00\", \"to_location_id\": 1}','2026-10-14 20:22:42'),(15,NULL,'stock_movement',3,'created',NULL,'{\"id\": 3, \"type\": \"PRODUCED\", \"quantity\": 60, \"created_at\": \"2026-10-14 20:22:42\", \"created_by\": 7, \"product_id\": 3, \"occurred_at\": \"2026-10-13 00:00:00\", \"to_location_id\": 1}','2026-10-14 20:22:42'),(16,NULL,'stock_movement',4,'created',NULL,'{\"id\": 4, \"type\": \"PRODUCED\", \"quantity\": 20, \"created_at\": \"2026-10-14 20:22:42\", \"created_by\": 7, \"product_id\": 4, \"occurred_at\": \"2026-10-14 20:22:42\", \"to_location_id\": 1}','2026-10-14 20:22:42'),(17,NULL,'stock_movement',5,'created',NULL,'{\"id\": 5, \"note\": \"Freezer door left open\", \"type\": \"ADJUSTED_OUT\", \"reason\": \"damaged\", \"quantity\": 4, \"created_at\": \"2026-10-14 20:22:42\", \"created_by\": 7, \"product_id\": 2, \"occurred_at\": \"2026-10-14 20:22:42\", \"from_location_id\": 1}','2026-10-14 20:22:42'),(18,NULL,'stock_movement',6,'created',NULL,'{\"id\": 6, \"type\": \"ASSIGNED\", \"quantity\": 40, \"created_at\": \"2026-10-14 20:22:42\", \"created_by\": 6, \"product_id\": 1, \"occurred_at\": \"2026-10-14 20:22:42\", \"to_location_id\": 3, \"from_location_id\": 1, \"unit_price_pesewas\": 3550}','2026-10-14 20:22:42'),(19,NULL,'stock_movement',7,'created',NULL,'{\"id\": 7, \"type\": \"ASSIGNED\", \"quantity\": 25, \"created_at\": \"2026-10-14 20:22:42\", \"created_by\": 6, \"product_id\": 2, \"occurred_at\": \"2026-10-14 20:22:42\", \"to_location_id\": 3, \"from_location_id\": 1, \"unit_price_pesewas\": 4200}','2026-10-14 20:22:42'),(20,NULL,'stock_movement',8,'created',NULL,'{\"id\": 8, \"type\": \"ASSIGNED\", \"quantity\": 30, \"created_at\": \"2026-10-14 20:22:42\", \"created_by\": 6, \"product_id\": 1, \"occurred_at\": \"2026-10-14 20:22:42\", \"to_location_id\": 4, \"from_location_id\": 1, \"unit_price_pesewas\": 3550}','2026-10-14 20:22:42'),(21,NULL,'stock_movement',9,'created',NULL,'{\"id\": 9, \"type\": \"ASSIGNED\", \"quantity\": 20, \"created_at\": \"2026-10-14 20:22:42\", \"created_by\": 6, \"product_id\": 3, \"occurred_at\": \"2026-10-14 20:22:42\", \"to_location_id\": 4, \"from_location_id\": 1, \"unit_price_pesewas\": 2800}','2026-10-14 20:22:42'),(22,NULL,'stock_movement',10,'created',NULL,'{\"id\": 10, \"type\": \"ALLOCATED_ONLINE\", \"quantity\": 50, \"created_at\": \"2026-10-14 20:22:42\", \"created_by\": 6, \"product_id\": 1, \"occurred_at\": \"2026-10-14 20:22:42\", \"to_location_id\": 2, \"from_location_id\": 1, \"unit_price_pesewas\": 3550}','2026-10-14 20:22:42'),(23,NULL,'stock_movement',11,'created',NULL,'{\"id\": 11, \"type\": \"ALLOCATED_ONLINE\", \"quantity\": 8, \"created_at\": \"2026-10-14 20:22:42\", \"created_by\": 6, \"product_id\": 4, \"occurred_at\": \"2026-10-14 20:22:42\", \"to_location_id\": 2, \"from_location_id\": 1, \"unit_price_pesewas\": 6000}','2026-10-14 20:22:42'),(24,NULL,'stock_movement',12,'created',NULL,'{\"id\": 12, \"type\": \"SOLD\", \"quantity\": 6, \"created_at\": \"2026-10-14 20:22:42\", \"created_by\": 8, \"product_id\": 1, \"occurred_at\": \"2026-10-14 20:22:42\", \"from_location_id\": 3, \"unit_price_pesewas\": 3550}','2026-10-14 20:22:42'),(25,NULL,'sale',1,'created',NULL,'{\"id\": 1, \"quantity\": 6, \"created_at\": \"2026-10-14 20:22:42\", \"product_id\": 1, \"movement_id\": 12, \"customer_note\": \"Auntie Esi, Madina market\", \"total_pesewas\": 21300, \"member_user_id\": 8, \"unit_price_pesewas\": 3550}','2026-10-14 20:22:42'),(26,NULL,'stock_movement',13,'created',NULL,'{\"id\": 13, \"type\": \"SOLD\", \"quantity\": 3, \"created_at\": \"2026-10-14 20:22:42\", \"created_by\": 8, \"product_id\": 2, \"occurred_at\": \"2026-10-14 20:22:42\", \"from_location_id\": 3, \"unit_price_pesewas\": 4200}','2026-10-14 20:22:42'),(27,NULL,'sale',2,'created',NULL,'{\"id\": 2, \"quantity\": 3, \"created_at\": \"2026-10-14 20:22:42\", \"product_id\": 2, \"movement_id\": 13, \"customer_note\": null, \"total_pesewas\": 12600, \"member_user_id\": 8, \"unit_price_pesewas\": 4200}','2026-10-14 20:22:42'),(28,NULL,'stock_movement',14,'created',NULL,'{\"id\": 14, \"type\": \"SOLD\", \"quantity\": 5, \"created_at\": \"2026-10-14 20:22:42\", \"created_by\": 9, \"product_id\": 1, \"occurred_at\": \"2026-10-14 20:22:42\", \"from_location_id\": 4, \"unit_price_pesewas\": 3550}','2026-10-14 20:22:42'),(29,NULL,'sale',3,'created',NULL,'{\"id\": 3, \"quantity\": 5, \"created_at\": \"2026-10-14 20:22:42\", \"product_id\": 1, \"movement_id\": 14, \"customer_note\": null, \"total_pesewas\": 17750, \"member_user_id\": 9, \"unit_price_pesewas\": 3550}','2026-10-14 20:22:42'),(30,NULL,'stock_movement',15,'created',NULL,'{\"id\": 15, \"type\": \"SOLD\", \"quantity\": 4, \"created_at\": \"2026-10-14 20:22:42\", \"created_by\": 9, \"product_id\": 3, \"occurred_at\": \"2026-10-14 20:22:42\", \"from_location_id\": 4, \"unit_price_pesewas\": 2800}','2026-10-14 20:22:42'),(31,NULL,'sale',4,'created',NULL,'{\"id\": 4, \"quantity\": 4, \"created_at\": \"2026-10-14 20:22:42\", \"product_id\": 3, \"movement_id\": 15, \"customer_note\": \"024 111 2222\", \"total_pesewas\": 11200, \"member_user_id\": 9, \"unit_price_pesewas\": 2800}','2026-10-14 20:22:42'),(32,NULL,'stock_movement',16,'created',NULL,'{\"id\": 16, \"type\": \"SALE_REVERSED\", \"reason\": \"Recorded against the wrong product\", \"quantity\": 5, \"created_at\": \"2026-10-14 20:22:42\", \"created_by\": 6, \"product_id\": 1, \"occurred_at\": \"2026-10-14 20:22:42\", \"to_location_id\": 4, \"ref_movement_id\": 14, \"unit_price_pesewas\": 3550}','2026-10-14 20:22:42'),(33,NULL,'sale',3,'reversed','{\"sale_id\": 3}','{\"reason\": \"Recorded against the wrong product\", \"reversal_movement_id\": 16}','2026-10-14 20:22:42'),(34,NULL,'return_request',1,'created',NULL,'{\"id\": 1, \"status\": \"requested\", \"quantity\": 6, \"product_id\": 3, \"requested_at\": \"2026-10-14 20:22:42\", \"member_user_id\": 9}','2026-10-14 20:22:42'),(35,8,'stock_movement',17,'created',NULL,'{\"id\": 17, \"type\": \"SOLD\", \"quantity\": 2, \"created_at\": \"2026-10-14 20:31:53\", \"created_by\": 8, \"product_id\": 2, \"occurred_at\": \"2026-10-14 20:31:53\", \"from_location_id\": 3, \"unit_price_pesewas\": 4200}','2026-10-14 20:31:53'),(36,8,'sale',5,'created',NULL,'{\"id\": 5, \"quantity\": 2, \"created_at\": \"2026-10-14 20:31:53\", \"product_id\": 2, \"movement_id\": 17, \"customer_note\": \"Test customer\", \"total_pesewas\": 8400, \"member_user_id\": 8, \"unit_price_pesewas\": 4200}','2026-10-14 20:31:53');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs FOR EACH ROW
                 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs is append-only' */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs FOR EACH ROW
                 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs is append-only' */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('blue-foods-cache-5c785c036466adea360111aa28563bfd556b5fba','i:1;',1792010647),('blue-foods-cache-5c785c036466adea360111aa28563bfd556b5fba:timer','i:1792010647;',1792010647),('laravel-cache-5c785c036466adea360111aa28563bfd556b5fba','i:1;',1792009451),('laravel-cache-5c785c036466adea360111aa28563bfd556b5fba:timer','i:1792009451;',1792009451);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `locations`
--

DROP TABLE IF EXISTS `locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `locations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `locations_user_id_unique` (`user_id`),
  KEY `locations_type_index` (`type`),
  CONSTRAINT `locations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `locations`
--

LOCK TABLES `locations` WRITE;
/*!40000 ALTER TABLE `locations` DISABLE KEYS */;
INSERT INTO `locations` VALUES (1,'warehouse',NULL,'2026-10-14 20:22:40','2026-10-14 20:22:40'),(2,'online_shop',NULL,'2026-10-14 20:22:40','2026-10-14 20:22:40'),(3,'member',8,'2026-10-14 20:22:41','2026-10-14 20:22:41'),(4,'member',9,'2026-10-14 20:22:42','2026-10-14 20:22:42');
/*!40000 ALTER TABLE `locations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(7,'2026_10_14_000001_add_roles_to_users_table',2),(8,'2026_10_14_000002_create_products_tables',2),(9,'2026_10_14_000003_create_stock_ledger_tables',2);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_prices`
--

DROP TABLE IF EXISTS `product_prices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_prices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `unit_price_pesewas` bigint(20) NOT NULL,
  `effective_from` datetime NOT NULL,
  `set_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_prices_set_by_foreign` (`set_by`),
  KEY `product_prices_product_id_effective_from_index` (`product_id`,`effective_from`),
  CONSTRAINT `product_prices_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `product_prices_set_by_foreign` FOREIGN KEY (`set_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_prices`
--

LOCK TABLES `product_prices` WRITE;
/*!40000 ALTER TABLE `product_prices` DISABLE KEYS */;
INSERT INTO `product_prices` VALUES (1,1,3550,'2026-10-14 20:22:42',6,'2026-10-14 20:22:42'),(2,2,4200,'2026-10-14 20:22:42',6,'2026-10-14 20:22:42'),(3,3,2800,'2026-10-14 20:22:42',6,'2026-10-14 20:22:42'),(4,4,6000,'2026-10-14 20:22:42',6,'2026-10-14 20:22:42');
/*!40000 ALTER TABLE `product_prices` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER product_prices_no_update BEFORE UPDATE ON product_prices FOR EACH ROW
                 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'product_prices is append-only' */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER product_prices_no_delete BEFORE DELETE ON product_prices FOR EACH ROW
                 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'product_prices is append-only' */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sku` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reorder_level` int(10) unsigned NOT NULL DEFAULT '0',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_sku_unique` (`sku`),
  KEY `products_status_index` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,'BF-TIL-500','Smoked tilapia 500g','Smoked fish','pack',30,'active','2026-10-14 20:22:42','2026-10-14 20:22:42'),(2,'BF-MAC-1KG','Frozen mackerel 1kg','Frozen fish','pack',40,'active','2026-10-14 20:22:42','2026-10-14 20:22:42'),(3,'BF-SHR-250','Dried shrimp 250g','Seafood','pack',25,'active','2026-10-14 20:22:42','2026-10-14 20:22:42'),(4,'BF-SAR-TRY','Fresh sardines tray','Fresh fish','tray',15,'active','2026-10-14 20:22:42','2026-10-14 20:22:42');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `return_requests`
--

DROP TABLE IF EXISTS `return_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `return_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `member_user_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `quantity` int(11) NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'requested',
  `requested_at` datetime NOT NULL,
  `confirmed_by` bigint(20) unsigned DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `decision_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `movement_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `return_requests_movement_id_unique` (`movement_id`),
  KEY `return_requests_product_id_foreign` (`product_id`),
  KEY `return_requests_confirmed_by_foreign` (`confirmed_by`),
  KEY `return_requests_member_user_id_product_id_status_index` (`member_user_id`,`product_id`,`status`),
  KEY `return_requests_status_index` (`status`),
  CONSTRAINT `return_requests_confirmed_by_foreign` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `return_requests_member_user_id_foreign` FOREIGN KEY (`member_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `return_requests_movement_id_foreign` FOREIGN KEY (`movement_id`) REFERENCES `stock_movements` (`id`),
  CONSTRAINT `return_requests_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `return_requests`
--

LOCK TABLES `return_requests` WRITE;
/*!40000 ALTER TABLE `return_requests` DISABLE KEYS */;
INSERT INTO `return_requests` VALUES (1,9,3,6,'requested','2026-10-14 20:22:42',NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `return_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales`
--

DROP TABLE IF EXISTS `sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `movement_id` bigint(20) unsigned NOT NULL,
  `member_user_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price_pesewas` bigint(20) NOT NULL,
  `total_pesewas` bigint(20) NOT NULL,
  `customer_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_movement_id_unique` (`movement_id`),
  KEY `sales_product_id_foreign` (`product_id`),
  KEY `sales_member_user_id_created_at_index` (`member_user_id`,`created_at`),
  KEY `sales_created_at_index` (`created_at`),
  CONSTRAINT `sales_member_user_id_foreign` FOREIGN KEY (`member_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `sales_movement_id_foreign` FOREIGN KEY (`movement_id`) REFERENCES `stock_movements` (`id`),
  CONSTRAINT `sales_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales`
--

LOCK TABLES `sales` WRITE;
/*!40000 ALTER TABLE `sales` DISABLE KEYS */;
INSERT INTO `sales` VALUES (1,12,8,1,6,3550,21300,'Auntie Esi, Madina market','2026-10-14 20:22:42'),(2,13,8,2,3,4200,12600,NULL,'2026-10-14 20:22:42'),(3,14,9,1,5,3550,17750,NULL,'2026-10-14 20:22:42'),(4,15,9,3,4,2800,11200,'024 111 2222','2026-10-14 20:22:42'),(5,17,8,2,2,4200,8400,'Test customer','2026-10-14 20:31:53');
/*!40000 ALTER TABLE `sales` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER sales_no_update BEFORE UPDATE ON sales FOR EACH ROW
                 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'sales is append-only' */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER sales_no_delete BEFORE DELETE ON sales FOR EACH ROW
                 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'sales is append-only' */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('6AeAXvpR2etnuhCZh0o9e7FFv2Q9AIwqY65a0WL1',8,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/155.0.0.0 Safari/537.36','YTo1OntzOjY6Il90b2tlbiI7czo0MDoiWTRDbGZDb2VzVEpFQjhpOEtjbVBiMnVZcmowdEJmRE5UZVhHa2pXUCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzA6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9teS9zYWxlcyI7czo1OiJyb3V0ZSI7czoxMjoibWVtYmVyLnNhbGVzIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6ODtzOjE3OiJwYXNzd29yZF9oYXNoX3dlYiI7czo2NDoiZjI3MjgwODEwMGYyMGYyYTk3NjIyYTQxM2RhMzFjNDg0M2JkZTM5NjEzYTczMjhhYjFkOGJkMTFlNzUzZjFhZSI7fQ==',1792009913),('B1YrJ5nYLdhE3ucMcpYfWwYpKlRX1vPIeSbpJGp9',1,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','YTo2OntzOjY6Il90b2tlbiI7czo0MDoid1J6Snh1RlZTTnVwWnBtMnZzbDZqamdNWTYyOG1Ud2dTeWI1SVpGNiI7czozOiJ1cmwiO2E6MDp7fXM6OToiX3ByZXZpb3VzIjthOjI6e3M6MzoidXJsIjtzOjM3OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvc3RvY2svbW92ZW1lbnRzIjtzOjU6InJvdXRlIjtzOjE1OiJzdG9jay5tb3ZlbWVudHMiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToxO3M6MTc6InBhc3N3b3JkX2hhc2hfd2ViIjtzOjY0OiJjODg1Njk3MTIyNmZiYzFmYTliZDdjZTg1ZGU2YWE3NDdhZmM5ZDIzMTgyZTI2ZTJlNmVlN2NiOTU3OTNmYTRkIjt9',1792011206),('B4inhNHqqrqeL02DZjjNUKeCPQcf8Qsl0R2E3Riq',6,'127.0.0.1','curl/8.7.1','YTo1OntzOjY6Il90b2tlbiI7czo0MDoiQk50aUpoTEVJbkluMUt1RVloOGswY1FWRm9aMEpvTFFiczlpanVYciI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzA6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9teS9zdG9jayI7czo1OiJyb3V0ZSI7czoxMToibWVtYmVyLmhvbWUiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aTo2O3M6MTc6InBhc3N3b3JkX2hhc2hfd2ViIjtzOjY0OiJhZDI2ZWJlNDdlM2E3ZGU5MWJlZDAzMDU2MDk4YzQwNzM4ZmY3ZmQxNDA5ODUxNGU0MDVlOTg4MTkyMjI0NTY5Ijt9',1792009640),('BAiIc01RTuZU1MKpKGjrfaNjnMac2YwXRFTuM2KW',6,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/155.0.0.0 Safari/537.36','YTo1OntzOjY6Il90b2tlbiI7czo0MDoidXpoTGFyTnQzVUFhS0M5ZWFuc2lhQ20yN1I4TjhaOFhleWJTUWpNMCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjk6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9wcm9maWxlIjtzOjU6InJvdXRlIjtzOjEyOiJwcm9maWxlLmVkaXQiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aTo2O3M6MTc6InBhc3N3b3JkX2hhc2hfd2ViIjtzOjY0OiJhZDI2ZWJlNDdlM2E3ZGU5MWJlZDAzMDU2MDk4YzQwNzM4ZmY3ZmQxNDA5ODUxNGU0MDVlOTg4MTkyMjI0NTY5Ijt9',1792009902),('fP9p6XycnquoDJcS6e4UzGXEGkKTtkr96YCpf4iA',8,'127.0.0.1','curl/8.7.1','YTo1OntzOjY6Il90b2tlbiI7czo0MDoibEQxUVNGaHNuRnN5NjBwb1c5YWhFRU9BNmtzeWN1OWZVR0FTR0xxQiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjc6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9zdG9jayI7czo1OiJyb3V0ZSI7czoxMToic3RvY2suaW5kZXgiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aTo4O3M6MTc6InBhc3N3b3JkX2hhc2hfd2ViIjtzOjY0OiJmMjcyODA4MTAwZjIwZjJhOTc2MjJhNDEzZGEzMWM0ODQzYmRlMzk2MTNhNzMyOGFiMWQ4YmQxMWU3NTNmMWFlIjt9',1792009642),('HeWB9b6AXesHoBxQhfu2ivh50ROW7jFKF5TdOuId',7,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/155.0.0.0 Safari/537.36','YTo1OntzOjY6Il90b2tlbiI7czo0MDoiZVVVdUNuRlVHMGFlWDB2YWNmbGttRGlqb2NFWVlrblJLbEJaeHI0ZCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjk6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9yZXR1cm5zIjtzOjU6InJvdXRlIjtzOjEzOiJyZXR1cm5zLmluZGV4Ijt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6NztzOjE3OiJwYXNzd29yZF9oYXNoX3dlYiI7czo2NDoiMDA5MjI0MWNiOTAzODRkNDVhZjliMTEzOWE2MjRhNmJkMTk4MmZjMTZkYTM0ZmZhMGM3MmYxZjRlNGYyODA4YiI7fQ==',1792009907),('jm3ausbuyqQjmU1sGzpXBBe97lbvaWLhB1Jp5u5x',NULL,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Cursor/3.13.10 Chrome/144.0.7559.236 Electron/40.10.3 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiSzNpQ3hWa3FDSGg4NWpaSzg3SlIzbXFrajNFOW5vS2dGekNMaUZHSSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMCI7czo1OiJyb3V0ZSI7Tjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1792008450),('NU8bVuddYA6mZj5BjFKPvW2Z6eOvOdRffm6UDzCg',NULL,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/155.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiSERGNzdFb2ljbzJEWG5CQllxdk02YlJLU1JuRjNNa1hiRm9xUTJYVSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjc6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9sb2dpbiI7czo1OiJyb3V0ZSI7czo1OiJsb2dpbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1792009695),('oHcZMKK6UOJOZuCz2sZWXVliP9pszWqmKrUUAMsT',7,'127.0.0.1','curl/8.7.1','YTo1OntzOjY6Il90b2tlbiI7czo0MDoiOWF0UHd6c3RFd0NpdEJTZFJTMGk2MWdsVWR6ekpjWTNGME9LNTRZdSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjc6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9zYWxlcyI7czo1OiJyb3V0ZSI7czoxMToic2FsZXMuaW5kZXgiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aTo3O3M6MTc6InBhc3N3b3JkX2hhc2hfd2ViIjtzOjY0OiIwMDkyMjQxY2I5MDM4NGQ0NWFmOWIxMTM5YTYyNGE2YmQxOTgyZmMxNmRhMzRmZmEwYzcyZjFmNGU0ZjI4MDhiIjt9',1792009641),('WIBZBMARfCWkcb6JpjBT0bX3TOpN0F0g8DLjOETR',1,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','YTo1OntzOjY6Il90b2tlbiI7czo0MDoib21Zb0UwWUc3MHZYRGhJeGxHc2FTUldKY2Z4anhOdjhTQk9WSElGZiI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJuZXciO2E6MDp7fXM6Mzoib2xkIjthOjA6e319czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjc6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9sb2dpbiI7czo1OiJyb3V0ZSI7czo1OiJsb2dpbiI7fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjE7czoxNzoicGFzc3dvcmRfaGFzaF93ZWIiO3M6NjQ6ImM4ODU2OTcxMjI2ZmJjMWZhOWJkN2NlODVkZTZhYTc0N2FmYzlkMjMxODJlMjZlMmU2ZWU3Y2I5NTc5M2ZhNGQiO30=',1792009512);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `stock_balances`
--

DROP TABLE IF EXISTS `stock_balances`;
/*!50001 DROP VIEW IF EXISTS `stock_balances`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `stock_balances` AS SELECT 
 1 AS `product_id`,
 1 AS `location_id`,
 1 AS `quantity`*/;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `stock_movements`
--

DROP TABLE IF EXISTS `stock_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_movements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int(11) NOT NULL,
  `from_location_id` bigint(20) unsigned DEFAULT NULL,
  `to_location_id` bigint(20) unsigned DEFAULT NULL,
  `unit_price_pesewas` bigint(20) DEFAULT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ref_movement_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `occurred_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_movements_from_location_id_foreign` (`from_location_id`),
  KEY `stock_movements_to_location_id_foreign` (`to_location_id`),
  KEY `stock_movements_ref_movement_id_foreign` (`ref_movement_id`),
  KEY `stock_movements_created_by_foreign` (`created_by`),
  KEY `stock_movements_product_id_to_location_id_index` (`product_id`,`to_location_id`),
  KEY `stock_movements_product_id_from_location_id_index` (`product_id`,`from_location_id`),
  KEY `stock_movements_type_occurred_at_index` (`type`,`occurred_at`),
  KEY `stock_movements_occurred_at_index` (`occurred_at`),
  CONSTRAINT `stock_movements_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `stock_movements_from_location_id_foreign` FOREIGN KEY (`from_location_id`) REFERENCES `locations` (`id`),
  CONSTRAINT `stock_movements_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `stock_movements_ref_movement_id_foreign` FOREIGN KEY (`ref_movement_id`) REFERENCES `stock_movements` (`id`),
  CONSTRAINT `stock_movements_to_location_id_foreign` FOREIGN KEY (`to_location_id`) REFERENCES `locations` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_movements`
--

LOCK TABLES `stock_movements` WRITE;
/*!40000 ALTER TABLE `stock_movements` DISABLE KEYS */;
INSERT INTO `stock_movements` VALUES (1,1,'PRODUCED',200,NULL,1,NULL,NULL,NULL,NULL,7,'2026-10-11 00:00:00','2026-10-14 20:22:42'),(2,2,'PRODUCED',150,NULL,1,NULL,NULL,NULL,NULL,7,'2026-10-12 00:00:00','2026-10-14 20:22:42'),(3,3,'PRODUCED',60,NULL,1,NULL,NULL,NULL,NULL,7,'2026-10-13 00:00:00','2026-10-14 20:22:42'),(4,4,'PRODUCED',20,NULL,1,NULL,NULL,NULL,NULL,7,'2026-10-14 20:22:42','2026-10-14 20:22:42'),(5,2,'ADJUSTED_OUT',4,1,NULL,NULL,'damaged','Freezer door left open',NULL,7,'2026-10-14 20:22:42','2026-10-14 20:22:42'),(6,1,'ASSIGNED',40,1,3,3550,NULL,NULL,NULL,6,'2026-10-14 20:22:42','2026-10-14 20:22:42'),(7,2,'ASSIGNED',25,1,3,4200,NULL,NULL,NULL,6,'2026-10-14 20:22:42','2026-10-14 20:22:42'),(8,1,'ASSIGNED',30,1,4,3550,NULL,NULL,NULL,6,'2026-10-14 20:22:42','2026-10-14 20:22:42'),(9,3,'ASSIGNED',20,1,4,2800,NULL,NULL,NULL,6,'2026-10-14 20:22:42','2026-10-14 20:22:42'),(10,1,'ALLOCATED_ONLINE',50,1,2,3550,NULL,NULL,NULL,6,'2026-10-14 20:22:42','2026-10-14 20:22:42'),(11,4,'ALLOCATED_ONLINE',8,1,2,6000,NULL,NULL,NULL,6,'2026-10-14 20:22:42','2026-10-14 20:22:42'),(12,1,'SOLD',6,3,NULL,3550,NULL,NULL,NULL,8,'2026-10-14 20:22:42','2026-10-14 20:22:42'),(13,2,'SOLD',3,3,NULL,4200,NULL,NULL,NULL,8,'2026-10-14 20:22:42','2026-10-14 20:22:42'),(14,1,'SOLD',5,4,NULL,3550,NULL,NULL,NULL,9,'2026-10-14 20:22:42','2026-10-14 20:22:42'),(15,3,'SOLD',4,4,NULL,2800,NULL,NULL,NULL,9,'2026-10-14 20:22:42','2026-10-14 20:22:42'),(16,1,'SALE_REVERSED',5,NULL,4,3550,'Recorded against the wrong product',NULL,14,6,'2026-10-14 20:22:42','2026-10-14 20:22:42'),(17,2,'SOLD',2,3,NULL,4200,NULL,NULL,NULL,8,'2026-10-14 20:31:53','2026-10-14 20:31:53');
/*!40000 ALTER TABLE `stock_movements` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER stock_movements_no_update BEFORE UPDATE ON stock_movements FOR EACH ROW
                 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'stock_movements is append-only' */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER stock_movements_no_delete BEFORE DELETE ON stock_movements FOR EACH ROW
                 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'stock_movements is append-only' */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'marketing',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_login_attempts` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `locked_until` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_phone_unique` (`phone`),
  KEY `users_role_index` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Eugene Osei','t@t.com',NULL,'management','active',NULL,'$2y$12$0VAQF2pCNWImG3ayJkmIYuE1mCTyHWKdaswJecl9KtL8oSlZiNSk2',0,NULL,NULL,'2026-10-14 20:08:32','2026-10-14 20:08:32'),(6,'Micky Kwachie','manager@bluefoods.test','+233200000001','management','active',NULL,'$2y$12$0W.ovE/L7NHP.7BSbjGg9.Qg/MgsDIWQuXTwbAQiAkVXCyXdTEAcK',0,NULL,NULL,'2026-10-14 20:22:41','2026-10-14 20:22:41'),(7,'Warehouse Desk','warehouse@bluefoods.test','+233200000002','warehouse','active',NULL,'$2y$12$Ty7mcKJuPJlN3asqz9TgGe7YSxRMt0xAJcOCcFN.g9dwnKwyxp9C.',0,NULL,NULL,'2026-10-14 20:22:41','2026-10-14 20:22:41'),(8,'Ama Owusu','ama@bluefoods.test','+233200000003','marketing','active',NULL,'$2y$12$Q2Y1iVGkC1r83hLVZa7F0OSsQHtGhaTT59O8AbbZwRTkDF8v6CXeS',0,NULL,NULL,'2026-10-14 20:22:41','2026-10-14 20:22:41'),(9,'Kofi Mensah','kofi@bluefoods.test','+233200000004','marketing','active',NULL,'$2y$12$5iAH6q8LDI0rcIVQ.FrO7emxru59o3FW29vPXKC4lvMt08zvvWU2G',0,NULL,NULL,'2026-10-14 20:22:42','2026-10-14 20:22:42');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'blueFoods'
--

--
-- Final view structure for view `stock_balances`
--

/*!50001 DROP VIEW IF EXISTS `stock_balances`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `stock_balances` AS select `m`.`product_id` AS `product_id`,`m`.`location_id` AS `location_id`,sum(`m`.`delta`) AS `quantity` from (select `bluefoods`.`stock_movements`.`product_id` AS `product_id`,`bluefoods`.`stock_movements`.`to_location_id` AS `location_id`,`bluefoods`.`stock_movements`.`quantity` AS `delta` from `bluefoods`.`stock_movements` where (`bluefoods`.`stock_movements`.`to_location_id` is not null) union all select `bluefoods`.`stock_movements`.`product_id` AS `product_id`,`bluefoods`.`stock_movements`.`from_location_id` AS `location_id`,-(`bluefoods`.`stock_movements`.`quantity`) AS `delta` from `bluefoods`.`stock_movements` where (`bluefoods`.`stock_movements`.`from_location_id` is not null)) `m` group by `m`.`product_id`,`m`.`location_id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-14 20:54:40
