/*M!999999\- enable the sandbox mode */ 
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `shipment_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `properties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`properties`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `activity_logs_user_id_foreign` (`user_id`),
  KEY `activity_logs_shipment_id_foreign` (`shipment_id`),
  CONSTRAINT `activity_logs_shipment_id_foreign` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `carriers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `carriers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `fmc_no` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `charge_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `charge_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `item` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `default_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `apply_customer_discount` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `state_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cities_state_id_foreign` (`state_id`),
  CONSTRAINT `cities_state_id_foreign` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `consignees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consignees` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `shipper_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `country_id` bigint(20) unsigned DEFAULT NULL,
  `state_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `consignees_shipper_id_foreign` (`shipper_id`),
  KEY `consignees_country_id_foreign` (`country_id`),
  KEY `consignees_state_id_foreign` (`state_id`),
  CONSTRAINT `consignees_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `consignees_shipper_id_foreign` FOREIGN KEY (`shipper_id`) REFERENCES `shippers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `consignees_state_id_foreign` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `countries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `iso2` varchar(2) NOT NULL,
  `iso3` varchar(3) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `countries_iso2_unique` (`iso2`),
  UNIQUE KEY `countries_iso3_unique` (`iso3`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `default_shipment_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `default_shipment_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `carrier_id` bigint(20) unsigned DEFAULT NULL,
  `origin_port_id` bigint(20) unsigned DEFAULT NULL,
  `logistics_service` varchar(255) DEFAULT NULL,
  `shipping_mode` varchar(255) DEFAULT NULL,
  `shipment_status` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `invoice_status` varchar(255) DEFAULT NULL,
  `payment_status` varchar(255) DEFAULT NULL,
  `initial_vehicle_status` varchar(255) DEFAULT NULL,
  `payment_method_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `default_shipment_settings_origin_port_id_foreign` (`origin_port_id`),
  KEY `default_shipment_settings_carrier_id_foreign` (`carrier_id`),
  KEY `default_shipment_settings_payment_method_id_foreign` (`payment_method_id`),
  CONSTRAINT `default_shipment_settings_carrier_id_foreign` FOREIGN KEY (`carrier_id`) REFERENCES `carriers` (`id`),
  CONSTRAINT `default_shipment_settings_origin_port_id_foreign` FOREIGN KEY (`origin_port_id`) REFERENCES `ports` (`id`),
  CONSTRAINT `default_shipment_settings_payment_method_id_foreign` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `drivers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `drivers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `phone` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email_log_id` bigint(20) unsigned NOT NULL,
  `exception_message` text DEFAULT NULL,
  `smtp_response` text DEFAULT NULL,
  `attempted_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `email_attempts_email_log_id_foreign` (`email_log_id`),
  CONSTRAINT `email_attempts_email_log_id_foreign` FOREIGN KEY (`email_log_id`) REFERENCES `email_logs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `mailable_class` varchar(255) DEFAULT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `status` varchar(255) NOT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint(20) unsigned NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `gross_amount` decimal(14,2) DEFAULT NULL,
  `discount_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_items_invoice_id_foreign` (`invoice_id`),
  CONSTRAINT `invoice_items_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(255) NOT NULL,
  `shipment_id` bigint(20) unsigned NOT NULL,
  `status` varchar(255) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'USD',
  `subtotal` decimal(14,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `issued_at` timestamp NULL DEFAULT NULL,
  `due_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoices_invoice_number_unique` (`invoice_number`),
  UNIQUE KEY `invoices_shipment_id_unique` (`shipment_id`),
  CONSTRAINT `invoices_shipment_id_foreign` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `newsletters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `newsletters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `url` varchar(255) DEFAULT NULL,
  `mailer` varchar(255) NOT NULL DEFAULT 'failover',
  `sent_at` timestamp NULL DEFAULT NULL,
  `recipients_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) NOT NULL,
  `type` varchar(255) NOT NULL,
  `notifiable_type` varchar(255) NOT NULL,
  `notifiable_id` bigint(20) unsigned NOT NULL,
  `data` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_methods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_methods_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint(20) unsigned NOT NULL,
  `payment_method_id` bigint(20) unsigned NOT NULL,
  `transaction_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `status` varchar(255) NOT NULL,
  `transaction_ref` varchar(255) DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_invoice_id_unique` (`invoice_id`),
  KEY `payments_payment_method_id_foreign` (`payment_method_id`),
  KEY `payments_transaction_id_foreign` (`transaction_id`),
  CONSTRAINT `payments_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_payment_method_id_foreign` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`),
  CONSTRAINT `payments_transaction_id_foreign` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `country_id` bigint(20) unsigned NOT NULL,
  `state_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'destination',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `warehouse_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ports_country_id_foreign` (`country_id`),
  KEY `ports_state_id_foreign` (`state_id`),
  KEY `ports_warehouse_id_foreign` (`warehouse_id`),
  CONSTRAINT `ports_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ports_state_id_foreign` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ports_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prealerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `prealerts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `shipper_id` bigint(20) unsigned NOT NULL,
  `consignee_id` bigint(20) unsigned DEFAULT NULL,
  `carrier_id` bigint(20) unsigned DEFAULT NULL,
  `shipping_mode` varchar(255) NOT NULL DEFAULT 'roro',
  `shipment_id` bigint(20) unsigned DEFAULT NULL,
  `destination_port_id` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `notify_party_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prealerts_shipper_id_foreign` (`shipper_id`),
  KEY `prealerts_carrier_id_foreign` (`carrier_id`),
  KEY `prealerts_destination_port_id_foreign` (`destination_port_id`),
  KEY `prealerts_consignee_id_foreign` (`consignee_id`),
  KEY `prealerts_shipment_id_foreign` (`shipment_id`),
  KEY `prealerts_notify_party_id_foreign` (`notify_party_id`),
  CONSTRAINT `prealerts_carrier_id_foreign` FOREIGN KEY (`carrier_id`) REFERENCES `carriers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prealerts_consignee_id_foreign` FOREIGN KEY (`consignee_id`) REFERENCES `consignees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prealerts_destination_port_id_foreign` FOREIGN KEY (`destination_port_id`) REFERENCES `ports` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prealerts_notify_party_id_foreign` FOREIGN KEY (`notify_party_id`) REFERENCES `consignees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prealerts_shipment_id_foreign` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prealerts_shipper_id_foreign` FOREIGN KEY (`shipper_id`) REFERENCES `shippers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shipment_document_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `shipment_document_files` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `shipment_document_id` bigint(20) unsigned NOT NULL,
  `path` varchar(255) NOT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `shipment_document_files_shipment_document_id_foreign` (`shipment_document_id`),
  KEY `shipment_document_files_uploaded_by_foreign` (`uploaded_by`),
  CONSTRAINT `shipment_document_files_shipment_document_id_foreign` FOREIGN KEY (`shipment_document_id`) REFERENCES `shipment_documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `shipment_document_files_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shipment_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `shipment_documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `shipment_id` bigint(20) unsigned DEFAULT NULL,
  `document_type` varchar(255) NOT NULL DEFAULT 'other',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `shipment_documents_shipment_id_foreign` (`shipment_id`),
  CONSTRAINT `shipment_documents_shipment_id_foreign` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shipment_trackings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `shipment_trackings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `shipment_id` bigint(20) unsigned NOT NULL,
  `status` varchar(255) NOT NULL,
  `workshop_id` bigint(20) unsigned DEFAULT NULL,
  `note` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `recorded_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `shipment_trackings_shipment_id_foreign` (`shipment_id`),
  KEY `shipment_trackings_workshop_id_foreign` (`workshop_id`),
  CONSTRAINT `shipment_trackings_shipment_id_foreign` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `shipment_trackings_workshop_id_foreign` FOREIGN KEY (`workshop_id`) REFERENCES `workshops` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shipments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `shipments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(255) NOT NULL,
  `export_references` varchar(255) DEFAULT NULL,
  `loading_pier` varchar(255) DEFAULT NULL,
  `domestic_routing` text DEFAULT NULL,
  `shipper_id` bigint(20) unsigned NOT NULL,
  `consignee_id` bigint(20) unsigned NOT NULL,
  `carrier_id` bigint(20) unsigned NOT NULL,
  `origin_port_id` bigint(20) unsigned NOT NULL,
  `destination_port_id` bigint(20) unsigned NOT NULL,
  `logistics_service` varchar(255) NOT NULL,
  `shipping_mode` varchar(255) NOT NULL,
  `capacity` int(11) NOT NULL DEFAULT 1,
  `shipment_status` varchar(255) NOT NULL,
  `sealed_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `seal_closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `invoice_status` varchar(255) DEFAULT NULL,
  `payment_status` varchar(255) DEFAULT NULL,
  `payment_method_id` bigint(20) unsigned DEFAULT NULL,
  `bill_of_lading_number` varchar(255) DEFAULT NULL,
  `booking_number` varchar(255) DEFAULT NULL,
  `itn_number` varchar(255) DEFAULT NULL,
  `container_no` varchar(255) DEFAULT NULL,
  `seal_no` varchar(255) DEFAULT NULL,
  `container_type` varchar(255) DEFAULT NULL,
  `vessel_name` varchar(255) DEFAULT NULL,
  `voyage_no` varchar(255) DEFAULT NULL,
  `cut_off_date` date DEFAULT NULL,
  `departure_date` date DEFAULT NULL,
  `arrival_date` date DEFAULT NULL,
  `notify_party_id` bigint(20) unsigned DEFAULT NULL,
  `exporter_name` varchar(255) DEFAULT NULL,
  `exporter_address` text DEFAULT NULL,
  `exporter_state` varchar(255) DEFAULT NULL,
  `exporter_country` varchar(255) DEFAULT NULL,
  `exporter_zipcode` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shipments_reference_no_unique` (`reference_no`),
  KEY `shipments_shipper_id_foreign` (`shipper_id`),
  KEY `shipments_consignee_id_foreign` (`consignee_id`),
  KEY `shipments_origin_port_id_foreign` (`origin_port_id`),
  KEY `shipments_destination_port_id_foreign` (`destination_port_id`),
  KEY `shipments_carrier_id_foreign` (`carrier_id`),
  KEY `shipments_payment_method_id_foreign` (`payment_method_id`),
  KEY `shipments_notify_party_id_foreign` (`notify_party_id`),
  CONSTRAINT `shipments_carrier_id_foreign` FOREIGN KEY (`carrier_id`) REFERENCES `carriers` (`id`),
  CONSTRAINT `shipments_consignee_id_foreign` FOREIGN KEY (`consignee_id`) REFERENCES `consignees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `shipments_destination_port_id_foreign` FOREIGN KEY (`destination_port_id`) REFERENCES `ports` (`id`),
  CONSTRAINT `shipments_notify_party_id_foreign` FOREIGN KEY (`notify_party_id`) REFERENCES `consignees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `shipments_origin_port_id_foreign` FOREIGN KEY (`origin_port_id`) REFERENCES `ports` (`id`),
  CONSTRAINT `shipments_payment_method_id_foreign` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL,
  CONSTRAINT `shipments_shipper_id_foreign` FOREIGN KEY (`shipper_id`) REFERENCES `shippers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shippers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `shippers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `country_id` bigint(20) unsigned DEFAULT NULL,
  `state_id` bigint(20) unsigned DEFAULT NULL,
  `city_id` bigint(20) unsigned DEFAULT NULL,
  `discount_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `towing` tinyint(1) NOT NULL DEFAULT 1,
  `default_driver_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shippers_user_id_unique` (`user_id`),
  KEY `shippers_country_id_foreign` (`country_id`),
  KEY `shippers_state_id_foreign` (`state_id`),
  KEY `shippers_city_id_foreign` (`city_id`),
  KEY `shippers_default_driver_id_foreign` (`default_driver_id`),
  CONSTRAINT `shippers_city_id_foreign` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`),
  CONSTRAINT `shippers_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  CONSTRAINT `shippers_default_driver_id_foreign` FOREIGN KEY (`default_driver_id`) REFERENCES `drivers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `shippers_state_id_foreign` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`),
  CONSTRAINT `shippers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `staff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `job_title` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_user_id_unique` (`user_id`),
  CONSTRAINT `staff_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `states`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `states` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `country_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `states_country_id_foreign` (`country_id`),
  KEY `states_code_index` (`code`),
  CONSTRAINT `states_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) DEFAULT NULL,
  `logo` longtext DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `zipcode` varchar(255) DEFAULT NULL,
  `country_id` bigint(20) unsigned DEFAULT NULL,
  `state_id` bigint(20) unsigned DEFAULT NULL,
  `city_id` bigint(20) unsigned DEFAULT NULL,
  `auction_api_key` text DEFAULT NULL,
  `whatsapp_api_key` text DEFAULT NULL,
  `tracking_delivery_prefix` varchar(255) NOT NULL DEFAULT 'MRF',
  `tracking_digits` smallint(5) unsigned NOT NULL DEFAULT 5,
  `tracking_number_type` varchar(255) NOT NULL DEFAULT 'auto_increment',
  `tracking_random_digits` smallint(5) unsigned NOT NULL DEFAULT 10,
  `preferred_mailer` varchar(255) NOT NULL DEFAULT 'failover',
  `terms_and_conditions` longtext DEFAULT NULL,
  `storage_rates_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`storage_rates_json`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `fmc_number` varchar(255) DEFAULT NULL,
  `forwarding_agent_name` varchar(255) DEFAULT NULL,
  `forwarding_agent_address` varchar(255) DEFAULT NULL,
  `forwarding_agent_phone` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `system_settings_country_id_foreign` (`country_id`),
  KEY `system_settings_state_id_foreign` (`state_id`),
  KEY `system_settings_city_id_foreign` (`city_id`),
  CONSTRAINT `system_settings_city_id_foreign` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`),
  CONSTRAINT `system_settings_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  CONSTRAINT `system_settings_state_id_foreign` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `wallet_id` bigint(20) unsigned NOT NULL,
  `type` varchar(255) NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `balance_after` decimal(14,2) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transactions_wallet_id_foreign` (`wallet_id`),
  CONSTRAINT `transactions_wallet_id_foreign` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `two_factor_secret` text DEFAULT NULL,
  `two_factor_recovery_codes` text DEFAULT NULL,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vehicle_document_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehicle_document_files` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `vehicle_document_id` bigint(20) unsigned NOT NULL,
  `path` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vehicle_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehicle_documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `vehicle_id` bigint(20) unsigned NOT NULL,
  `document_type` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vehicle_documents_vehicle_id_foreign` (`vehicle_id`),
  CONSTRAINT `vehicle_documents_vehicle_id_foreign` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vehicle_trackings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehicle_trackings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `vehicle_id` bigint(20) unsigned NOT NULL,
  `status` varchar(255) NOT NULL,
  `workshop_id` bigint(20) unsigned DEFAULT NULL,
  `note` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vehicle_trackings_vehicle_id_foreign` (`vehicle_id`),
  KEY `vehicle_trackings_workshop_id_foreign` (`workshop_id`),
  CONSTRAINT `vehicle_trackings_vehicle_id_foreign` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vehicle_trackings_workshop_id_foreign` FOREIGN KEY (`workshop_id`) REFERENCES `workshops` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vehicles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehicles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `vin` varchar(255) DEFAULT NULL,
  `tracking_status` varchar(255) DEFAULT NULL,
  `lot_number` varchar(255) DEFAULT NULL,
  `gatepass_pin` varchar(11) DEFAULT NULL,
  `make` varchar(255) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `year` varchar(255) DEFAULT NULL,
  `series` varchar(255) DEFAULT NULL,
  `body_style` varchar(255) DEFAULT NULL,
  `color` varchar(255) DEFAULT NULL,
  `vehicle_type` varchar(255) DEFAULT NULL,
  `vehicle_is` varchar(255) DEFAULT NULL,
  `transmission` varchar(255) DEFAULT NULL,
  `fuel` varchar(255) DEFAULT NULL,
  `engine_type` varchar(255) DEFAULT NULL,
  `drive` varchar(255) DEFAULT NULL,
  `cylinders` int(10) unsigned DEFAULT NULL,
  `odometer` bigint(20) unsigned DEFAULT NULL,
  `car_keys` varchar(255) DEFAULT NULL,
  `doc_type` varchar(255) DEFAULT NULL,
  `auction_receipt` varchar(255) DEFAULT NULL,
  `primary_damage` varchar(255) DEFAULT NULL,
  `secondary_damage` varchar(255) DEFAULT NULL,
  `highlights` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `auction_name` varchar(255) DEFAULT NULL,
  `seller` varchar(255) DEFAULT NULL,
  `est_retail_value` decimal(14,2) DEFAULT NULL,
  `is_insurance` tinyint(1) NOT NULL DEFAULT 0,
  `currency_code_id` bigint(20) unsigned DEFAULT NULL,
  `api_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`api_snapshot`)),
  `api_fetched_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `shipment_id` bigint(20) unsigned DEFAULT NULL,
  `prealert_id` bigint(20) unsigned DEFAULT NULL,
  `driver_id` bigint(20) unsigned DEFAULT NULL,
  `workshop_id` bigint(20) unsigned DEFAULT NULL,
  `status_before_workshop` varchar(255) DEFAULT NULL,
  `value` decimal(14,2) DEFAULT NULL,
  `weight` decimal(10,2) DEFAULT NULL,
  `weight_unit` varchar(255) NOT NULL DEFAULT 'KG',
  `measurement` decimal(10,2) DEFAULT NULL,
  `measurement_unit` varchar(255) NOT NULL DEFAULT 'CBM',
  PRIMARY KEY (`id`),
  UNIQUE KEY `vehicles_vin_unique` (`vin`),
  KEY `vehicles_vin_index` (`vin`),
  KEY `vehicles_lot_number_index` (`lot_number`),
  KEY `vehicles_shipment_id_foreign` (`shipment_id`),
  KEY `vehicles_prealert_id_foreign` (`prealert_id`),
  KEY `vehicles_driver_id_foreign` (`driver_id`),
  KEY `vehicles_workshop_id_foreign` (`workshop_id`),
  CONSTRAINT `vehicles_driver_id_foreign` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vehicles_prealert_id_foreign` FOREIGN KEY (`prealert_id`) REFERENCES `prealerts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vehicles_shipment_id_foreign` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vehicles_workshop_id_foreign` FOREIGN KEY (`workshop_id`) REFERENCES `workshops` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wallet_top_ups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallet_top_ups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `wallet_id` bigint(20) unsigned NOT NULL,
  `shipper_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `receipt_path` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wallet_top_ups_approved_by_foreign` (`approved_by`),
  KEY `wallet_top_ups_wallet_id_foreign` (`wallet_id`),
  KEY `wallet_top_ups_shipper_id_foreign` (`shipper_id`),
  CONSTRAINT `wallet_top_ups_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `wallet_top_ups_shipper_id_foreign` FOREIGN KEY (`shipper_id`) REFERENCES `shippers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wallet_top_ups_wallet_id_foreign` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wallets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `shipper_id` bigint(20) unsigned NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'USD',
  `balance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wallets_shipper_id_unique` (`shipper_id`),
  CONSTRAINT `wallets_shipper_id_foreign` FOREIGN KEY (`shipper_id`) REFERENCES `shippers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warehouses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `warehouses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `warehouses_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `workshops`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `workshops` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

/*M!999999\- enable the sandbox mode */ 
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2025_08_14_170933_add_two_factor_columns_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2026_03_24_155718_create_permission_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2026_03_25_084427_remove_contact_phone_from_conignees_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2026_03_25_091043_add_is_default_to_consignees_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2026_03_29_103054_create_wallet_top_ups_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2026_03_30_000001_create_countries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2026_03_30_000002_create_states_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2026_03_30_000003_create_cities_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2026_03_30_000004_create_ports_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2026_03_30_000005_create_shipping_companies_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2026_03_30_000006_create_workshops_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2026_03_30_000007_create_payment_methods_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2026_03_30_000008_create_charge_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2026_03_30_000009_create_document_types_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2026_03_30_000010_create_shippers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2026_03_30_000011_create_staff_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2026_03_30_000012_create_drivers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2026_03_30_000013_create_consignees_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2026_03_30_000014_create_shipments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2026_03_30_000015_create_vehicles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2026_03_30_000016_create_shipment_trackings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2026_03_30_000017_create_shipment_documents_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2026_03_30_000018_create_shipment_document_files_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2026_03_30_000019_create_invoices_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2026_03_30_000020_create_invoice_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2026_03_30_000020_drop_contact_phone_from_conignees_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2026_03_30_000021_add_is_default_to_consignee_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2026_03_30_000021_create_payments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2026_03_30_000022_create_wallets_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2026_03_30_000023_create_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2026_03_30_000024_create_email_logs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2026_03_30_000025_create_email_attempts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2026_03_30_000026_create_activity_logs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2026_03_30_000027_create_notifications_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2026_03_30_000028_create_system_settings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2026_03_30_000029_change_system_settings_logo_to_long_text_for_base64',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2026_03_30_000030_create_default_shipment_settings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2026_03_30_000031_create_carriers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2026_03_30_000032_add_carrier_id_to_shipments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2026_03_30_000033_add_carrier_id_to_default_shipment_settings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2026_03_30_000034_make_vehicles_shipment_id_nullable_add_vin_unique_api_fetched_at',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2026_03_30_000035_create_prealerts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2026_03_30_000036_add_prealert_conversion_fields_to_prealerts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2026_03_30_000037_add_action_receipt_to_prealerts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2026_03_30_000037_add_gatepass_pin_to_shipments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2026_03_30_000038_add_tracking_fields_to_system_settings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2026_03_30_000039_add_auction_receipt_to_shipments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2026_03_30_000039_add_logo_path_to_system_settings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2026_03_30_000040_add_action_receipt_to_vehicles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2026_03_30_000041_add_consignee_id_to_prealerts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2026_03_30_000042_remove_audit_fields_from_prealerts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2026_03_30_000043_add_unique_constraints_to_prealerts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2026_03_30_000044_update_ports_table_schema',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2026_03_30_000045_add_vehicle_is_to_vehicles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2026_03_30_065044_drop_shipping_companies_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2026_03_30_092333_add_preferred_mailer_to_system_settings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2026_03_30_093925_create_newsletters_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2026_03_30_215455_add_invoice_and_payment_status_to_default_shipment_settings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2026_03_30_221916_remove_destination_port_id_from_default_shipment_settings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2026_03_30_222621_add_default_shipment_settings_permissions',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2026_03_30_223318_remove_notes_from_default_shipment_settings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2026_03_30_225820_add_invoice_and_payment_status_to_shipments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2026_03_30_232217_rename_status_and_remove_notes_from_shipments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2026_03_30_232749_rename_status_to_shipment_status_in_default_shipment_settings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2026_03_31_000000_move_shipment_to_vehicle_relationship',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2026_03_31_001453_add_status_to_prealerts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2026_03_31_013531_add_unique_vin_to_shipments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2026_03_31_013936_add_unique_vin_to_shipments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2026_03_31_140808_drop_name_from_drivers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2026_03_31_161610_add_wallet_top_ups_foreign_keys',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74,'2026_03_31_235030_drop_charge_item_and_qty_price_from_invoice_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2026_04_01_000000_update_drivers_table_add_email_company_drop_license',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (76,'2026_04_01_021910_add_payment_method_id_to_default_shipment_settings_and_shipments_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (77,'2026_04_02_000000_add_transaction_id_to_payments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (78,'2026_04_02_163149_add_email_to_system_settings',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2026_04_03_020352_add_invoice_discount_fields_to_charge_items_shippers_invoice_items',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (80,'2026_04_03_035835_add_workshop_snapshot_to_shipments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (81,'2026_04_03_120000_replace_shipment_document_type_with_enum_column',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (82,'2026_04_10_214500_create_vehicle_trackings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (83,'2026_04_10_214527_add_relations_and_logistics_to_vehicles',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (84,'2026_04_10_214907_add_capacity_to_shipments',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (85,'2026_04_10_214933_migrate_shipment_data_to_vehicles',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (86,'2026_04_10_223639_update_prealerts_and_vehicles_schema',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (87,'2026_04_12_172539_drop_legacy_vehicle_fields_from_prealerts_and_shipments',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (88,'2026_04_13_064006_add_initial_vehicle_status_to_default_shipment_settings_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (89,'2026_04_13_165841_add_vehicle_permissions',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (92,'2026_04_14_230733_update_shipments_and_documents_for_workflow',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (93,'2026_04_15_073230_create_vehicle_documents_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (94,'2026_04_15_073233_create_vehicle_document_files_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (95,'2026_04_15_210122_drop_vehicle_id_from_shipment_documents_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (97,'2026_04_16_081343_add_dock_receipt_fields_to_tables',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (98,'2026_04_16_130718_add_terms_and_storage_to_system_settings_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (99,'2026_04_16_144408_add_country_state_to_consignees_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (100,'2026_04_16_210133_uppercase_shipment_and_vehicle_statuses',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (101,'2026_04_17_141318_add_notify_party_to_shipments_and_prealerts_tables',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (102,'2026_04_18_154637_add_towing_and_default_driver_to_shippers_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (103,'2026_04_19_070747_add_wallet_payment_method',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (106,'2026_04_20_060335_create_warehouses_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (107,'2026_04_20_060348_add_warehouse_id_to_ports_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (108,'2026_04_20_071945_add_soft_deletes_to_warehouses_table',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (109,'2026_04_20_200252_add_exporter_details_to_shipments_table',16);
