-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: TechParts2
-- ------------------------------------------------------
-- Server version	8.0.44

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `TechParts2`
--

/*!40000 DROP DATABASE IF EXISTS `TechParts2`*/;

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `TechParts2` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `TechParts2`;

--
-- Table structure for table `category`
--

DROP TABLE IF EXISTS `category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `category` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `Name` varchar(255) NOT NULL,
  `Parts` varchar(255) DEFAULT 'N/A',
  `Status` enum('Active','Inactive','Archived') DEFAULT 'Inactive',
  `Description` text,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Name` (`Name`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `category`
--

LOCK TABLES `category` WRITE;
/*!40000 ALTER TABLE `category` DISABLE KEYS */;
INSERT INTO `category` (`ID`, `Name`, `Parts`, `Status`, `Description`) VALUES (1,'CPU / Processors','Processors, Coolers','Active','Central Processing Units and cooling solutions'),(2,'Motherboards','ATX, mATX, ITX','Active','Desktop and server motherboards'),(3,'Memory (RAM)','DDR4, DDR5','Active','System memory modules'),(4,'Storage','SSD, HDD, NVMe','Active','Solid-state and hard disk drives'),(5,'Graphics Cards (GPU)','NVIDIA, AMD','Active','Dedicated graphics processing units'),(6,'Power Supply (PSU)','ATX PSU','Active','Power supply units'),(7,'Cases & Cabinets','ATX, mATX, ITX Cases','Active','PC chassis and enclosures'),(8,'Peripherals','Keyboard, Mouse, Monitor','Active','Input devices and displays'),(9,'Networking','NIC, Router, Switch','Active','Network interface cards and equipment'),(10,'Cooling','Fans, Liquid Cooling','Active','CPU and case cooling solutions');
/*!40000 ALTER TABLE `category` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product`
--

DROP TABLE IF EXISTS `product`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `Name` varchar(255) NOT NULL,
  `Description` text,
  `Price` decimal(10,2) NOT NULL,
  `Brand` varchar(255) NOT NULL,
  `ImageURL` varchar(500) DEFAULT NULL,
  `Category_ID` int NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Name` (`Name`),
  KEY `Category_ID` (`Category_ID`),
  CONSTRAINT `product_ibfk_1` FOREIGN KEY (`Category_ID`) REFERENCES `category` (`ID`),
  CONSTRAINT `chk_price` CHECK ((`Price` > 0))
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product`
--

LOCK TABLES `product` WRITE;
/*!40000 ALTER TABLE `product` DISABLE KEYS */;
INSERT INTO `product` (`ID`, `Name`, `Description`, `Price`, `Brand`, `ImageURL`, `Category_ID`) VALUES (1,'Intel Core i5-13600K','14-core (6P+8E) LGA1700 processor, 125W TDP',15990.00,'Intel',NULL,1),(2,'Intel Core i7-13700K','16-core (8P+8E) LGA1700 processor, 125W TDP',24990.00,'Intel',NULL,1),(3,'AMD Ryzen 5 7600X','6-core/12-thread AM5 processor, PCIe 5.0',14990.00,'AMD',NULL,1),(4,'AMD Ryzen 7 7700X','8-core/16-thread AM5 processor, PCIe 5.0',22990.00,'AMD',NULL,1),(5,'ASUS ROG Strix B660-F','LGA1700, DDR5, PCIe 5.0, ATX',12990.00,'ASUS',NULL,2),(6,'MSI MAG B650 TOMAHAWK','AM5, DDR5, PCIe 5.0, ATX',13490.00,'MSI',NULL,2),(7,'Gigabyte B660M DS3H','LGA1700, DDR4, mATX, budget pick',4990.00,'Gigabyte',NULL,2),(8,'Corsair Vengeance DDR5 32GB','2x16GB DDR5-5600 CL36',7490.00,'Corsair',NULL,3),(9,'G.Skill Trident Z5 32GB','2x16GB DDR5-6000 CL30 RGB',8990.00,'G.Skill',NULL,3),(10,'Kingston Fury Beast 16GB','2x8GB DDR4-3200 CL16',2990.00,'Kingston',NULL,3),(11,'Samsung 990 Pro 1TB NVMe','PCIe 4.0 NVMe SSD, 7450/6900 MB/s',6490.00,'Samsung',NULL,4),(12,'WD Blue 1TB SSD','SATA SSD 2.5\", 560/530 MB/s',2490.00,'WD',NULL,4),(13,'Seagate Barracuda 2TB HDD','3.5\" 7200 RPM SATA HDD',2290.00,'Seagate',NULL,4),(14,'NVIDIA RTX 4070 Super','Ada Lovelace, 12GB GDDR6X, DLSS 3',44990.00,'NVIDIA',NULL,5),(15,'AMD RX 7800 XT','RDNA3, 16GB GDDR6, 256-bit',36990.00,'AMD',NULL,5),(16,'NVIDIA RTX 4060','Ada Lovelace, 8GB GDDR6, excellent 1080p',22990.00,'NVIDIA',NULL,5),(17,'Seasonic Focus GX-750','750W 80+ Gold, fully modular',5990.00,'Seasonic',NULL,6),(18,'Corsair RM850x','850W 80+ Gold, fully modular, silent',6990.00,'Corsair',NULL,6),(19,'Lian Li O11 Dynamic EVO','Mid-tower, dual-chamber, tempered glass',6990.00,'Lian Li',NULL,7),(20,'NZXT H510','Compact ATX mid-tower, PSU shroud',3990.00,'NZXT',NULL,7),(21,'Logitech MX Keys','Wireless mechanical-like keyboard, backlit',3490.00,'Logitech',NULL,8),(22,'Razer DeathAdder V3','Ergonomic gaming mouse, 30000 DPI',2990.00,'Razer',NULL,8),(23,'LG 27GP850-B 27\"','27\" IPS 165Hz 1ms QHD gaming monitor',18990.00,'LG',NULL,8),(24,'TP-Link AX3000 Wi-Fi 6','PCIe Wi-Fi 6 card, Bluetooth 5.0',1490.00,'TP-Link',NULL,9),(25,'Noctua NH-D15','Dual-tower CPU cooler, 2x 140mm fans',4490.00,'Noctua',NULL,10),(26,'NZXT Kraken X63 280mm','AIO liquid cooler, 2x140mm, LCD cap',7990.00,'NZXT',NULL,10);
/*!40000 ALTER TABLE `product` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_has_supplier`
--

DROP TABLE IF EXISTS `product_has_supplier`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_has_supplier` (
  `Product_ID` int NOT NULL,
  `Supplier_ID` int NOT NULL,
  PRIMARY KEY (`Product_ID`,`Supplier_ID`),
  KEY `Supplier_ID` (`Supplier_ID`),
  CONSTRAINT `product_has_supplier_ibfk_1` FOREIGN KEY (`Product_ID`) REFERENCES `product` (`ID`) ON DELETE CASCADE,
  CONSTRAINT `product_has_supplier_ibfk_2` FOREIGN KEY (`Supplier_ID`) REFERENCES `supplier` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_has_supplier`
--

LOCK TABLES `product_has_supplier` WRITE;
/*!40000 ALTER TABLE `product_has_supplier` DISABLE KEYS */;
INSERT INTO `product_has_supplier` (`Product_ID`, `Supplier_ID`) VALUES (1,1),(2,1),(5,1),(11,1),(14,1),(19,1),(22,1),(26,1),(1,2),(3,2),(4,2),(7,2),(10,2),(15,2),(20,2),(24,2),(3,3),(6,3),(12,3),(18,3),(23,3),(8,4),(9,4),(21,4),(25,4),(13,5),(16,5),(17,5);
/*!40000 ALTER TABLE `product_has_supplier` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchaseorder`
--

DROP TABLE IF EXISTS `purchaseorder`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchaseorder` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `OrderDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ArrivalDate` date DEFAULT NULL,
  `Status` enum('Pending','Approved','Ordered','Received','Cancelled') DEFAULT 'Pending',
  `Notes` text,
  `Supplier_ID` int NOT NULL,
  `User_ID` int NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `Supplier_ID` (`Supplier_ID`),
  KEY `User_ID` (`User_ID`),
  CONSTRAINT `purchaseorder_ibfk_1` FOREIGN KEY (`Supplier_ID`) REFERENCES `supplier` (`ID`),
  CONSTRAINT `purchaseorder_ibfk_2` FOREIGN KEY (`User_ID`) REFERENCES `user` (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchaseorder`
--

LOCK TABLES `purchaseorder` WRITE;
/*!40000 ALTER TABLE `purchaseorder` DISABLE KEYS */;
INSERT INTO `purchaseorder` (`ID`, `OrderDate`, `ArrivalDate`, `Status`, `Notes`, `Supplier_ID`, `User_ID`) VALUES (1,'2025-03-10 01:00:00','2025-03-17','Received','Monthly stock replenishment',1,1),(2,'2025-03-20 02:00:00','2025-03-27','Received','GPU restock order',2,1),(3,'2025-04-01 00:30:00','2025-04-08','Approved','Q2 initial order',3,1),(5,'2025-04-15 06:00:00','2025-04-22','Ordered','Urgent NVMe restock',5,1);
/*!40000 ALTER TABLE `purchaseorder` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
/*!50032 DROP TRIGGER IF EXISTS trg_add_stock_on_po_received */;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_add_stock_on_po_received` AFTER UPDATE ON `purchaseorder` FOR EACH ROW BEGIN
    IF NEW.Status = 'Received' AND OLD.Status != 'Received' THEN
        INSERT INTO Stock (Quantity, Product_ID, Supplier_ID)
        SELECT poi.QuantityOrdered, poi.Product_ID, NEW.Supplier_ID
        FROM PurchaseOrderItem poi
        WHERE poi.PurchaseOrder_ID = NEW.ID
        ON DUPLICATE KEY UPDATE Quantity = Quantity + VALUES(Quantity);
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `purchaseorderitem`
--

DROP TABLE IF EXISTS `purchaseorderitem`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchaseorderitem` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `QuantityOrdered` int NOT NULL,
  `UnitCost` decimal(10,2) NOT NULL,
  `Product_ID` int NOT NULL,
  `PurchaseOrder_ID` int NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `Product_ID` (`Product_ID`),
  KEY `PurchaseOrder_ID` (`PurchaseOrder_ID`),
  CONSTRAINT `purchaseorderitem_ibfk_1` FOREIGN KEY (`Product_ID`) REFERENCES `product` (`ID`),
  CONSTRAINT `purchaseorderitem_ibfk_2` FOREIGN KEY (`PurchaseOrder_ID`) REFERENCES `purchaseorder` (`ID`) ON DELETE CASCADE,
  CONSTRAINT `chk_item_cost` CHECK ((`UnitCost` > 0)),
  CONSTRAINT `chk_item_qty` CHECK ((`QuantityOrdered` > 0))
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchaseorderitem`
--

LOCK TABLES `purchaseorderitem` WRITE;
/*!40000 ALTER TABLE `purchaseorderitem` DISABLE KEYS */;
INSERT INTO `purchaseorderitem` (`ID`, `QuantityOrdered`, `UnitCost`, `Product_ID`, `PurchaseOrder_ID`) VALUES (1,20,13500.00,1,1),(2,15,21000.00,2,1),(3,10,4200.00,7,1),(4,10,40000.00,14,2),(5,12,32000.00,15,2),(6,15,20000.00,16,2),(7,25,2100.00,12,3),(8,30,1900.00,13,3),(9,20,2600.00,10,3),(13,20,5500.00,11,5),(14,15,6100.00,25,5),(15,10,7200.00,26,5);
/*!40000 ALTER TABLE `purchaseorderitem` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `saleitem`
--

DROP TABLE IF EXISTS `saleitem`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `saleitem` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `Quantity` int NOT NULL,
  `UnitPrice` decimal(10,2) NOT NULL,
  `Subtotal` decimal(10,2) GENERATED ALWAYS AS ((`Quantity` * `UnitPrice`)) STORED,
  `Product_ID` int NOT NULL,
  `Transaction_ID` int NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `Product_ID` (`Product_ID`),
  KEY `Transaction_ID` (`Transaction_ID`),
  CONSTRAINT `saleitem_ibfk_1` FOREIGN KEY (`Product_ID`) REFERENCES `product` (`ID`),
  CONSTRAINT `saleitem_ibfk_2` FOREIGN KEY (`Transaction_ID`) REFERENCES `transaction` (`ID`) ON DELETE CASCADE,
  CONSTRAINT `chk_sale_price` CHECK ((`UnitPrice` > 0)),
  CONSTRAINT `chk_sale_qty` CHECK ((`Quantity` > 0))
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `saleitem`
--

LOCK TABLES `saleitem` WRITE;
/*!40000 ALTER TABLE `saleitem` DISABLE KEYS */;
/*!40000 ALTER TABLE `saleitem` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
/*!50032 DROP TRIGGER IF EXISTS trg_deduct_stock_after_sale */;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_deduct_stock_after_sale` AFTER INSERT ON `saleitem` FOR EACH ROW BEGIN
    UPDATE Stock
    SET Quantity = Quantity - NEW.Quantity
    WHERE Product_ID = NEW.Product_ID
    LIMIT 1;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `stock`
--

DROP TABLE IF EXISTS `stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `Quantity` int DEFAULT '0',
  `MinStock` int DEFAULT '5',
  `LastUpdated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `Product_ID` int NOT NULL,
  `Supplier_ID` int NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `Product_ID` (`Product_ID`),
  KEY `Supplier_ID` (`Supplier_ID`),
  CONSTRAINT `stock_ibfk_1` FOREIGN KEY (`Product_ID`) REFERENCES `product` (`ID`),
  CONSTRAINT `stock_ibfk_2` FOREIGN KEY (`Supplier_ID`) REFERENCES `supplier` (`ID`),
  CONSTRAINT `chk_qty` CHECK ((`Quantity` >= 0))
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock`
--

LOCK TABLES `stock` WRITE;
/*!40000 ALTER TABLE `stock` DISABLE KEYS */;
INSERT INTO `stock` (`ID`, `Quantity`, `MinStock`, `LastUpdated`, `Product_ID`, `Supplier_ID`) VALUES (1,25,5,'2026-05-17 09:08:53',1,1),(2,18,5,'2026-05-16 16:28:32',2,1),(3,29,5,'2026-05-17 08:44:35',3,2),(4,22,5,'2026-05-16 16:28:32',4,2),(5,15,3,'2026-05-16 16:28:32',5,1),(6,20,3,'2026-05-16 16:28:32',6,3),(7,40,5,'2026-05-16 16:28:32',7,2),(8,35,5,'2026-05-16 17:39:11',8,4),(9,28,5,'2026-05-16 16:28:32',9,4),(10,59,10,'2026-05-16 16:28:32',10,2),(11,20,5,'2026-05-16 16:28:32',11,1),(12,49,10,'2026-05-16 16:28:32',12,3),(13,45,10,'2026-05-16 16:28:32',13,5),(14,7,3,'2026-05-16 16:28:32',14,1),(15,12,3,'2026-05-16 16:28:32',15,2),(16,24,5,'2026-05-16 16:28:32',16,2),(17,30,5,'2026-05-16 16:28:32',17,5),(18,25,5,'2026-05-16 16:28:32',18,3),(19,18,5,'2026-05-16 16:28:32',19,3),(20,20,5,'2026-05-16 16:28:32',20,2),(21,34,5,'2026-05-16 16:28:32',21,1),(22,37,5,'2026-05-16 16:28:32',22,2),(23,5,2,'2026-05-16 16:28:32',23,4),(24,50,10,'2026-05-16 16:28:32',24,2),(25,15,5,'2026-05-17 08:44:35',25,4),(26,10,3,'2026-05-16 16:28:32',26,1);
/*!40000 ALTER TABLE `stock` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `supplier`
--

DROP TABLE IF EXISTS `supplier`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supplier` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `Name` varchar(255) NOT NULL,
  `Phone` varchar(20) NOT NULL,
  `Email` varchar(254) NOT NULL,
  `Address` varchar(255) NOT NULL,
  `IsActive` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Phone` (`Phone`),
  UNIQUE KEY `Email` (`Email`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier`
--

LOCK TABLES `supplier` WRITE;
/*!40000 ALTER TABLE `supplier` DISABLE KEYS */;
INSERT INTO `supplier` (`ID`, `Name`, `Phone`, `Email`, `Address`, `IsActive`) VALUES (1,'TechDistrib PH','09171234567','sales@techdistrib.ph','Fuente Osmeña, Cebu City',1),(2,'PC Parts Express','09281234567','orders@pcpartsexpress.ph','Colon St., Cebu City',1),(3,'DataLink Wholesale','09391234567','info@datalink.ph','A.S. Fortuna, Mandaue City',1),(4,'NovaTech Supply','09451234567','supply@novatech.ph','Mactan, Lapu-Lapu City',1),(5,'ByteSource Corp','09561234567','contact@bytesource.ph','IT Park, Cebu City',1);
/*!40000 ALTER TABLE `supplier` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transaction`
--

DROP TABLE IF EXISTS `transaction`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transaction` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `TransactionDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `CustomerName` varchar(150) DEFAULT 'Walk-in Customer',
  `CustomerPhone` varchar(20) DEFAULT NULL,
  `PaymentMethod` enum('Cash','Card','GCash','PayMaya','Other') DEFAULT 'Cash',
  `AmountTendered` decimal(10,2) DEFAULT '0.00',
  `Discount` decimal(5,2) DEFAULT '0.00',
  `TotalAmount` decimal(10,2) NOT NULL,
  `Status` enum('Completed','Voided','Refunded') DEFAULT 'Completed',
  `Notes` text,
  `Cashier_ID` int NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `Cashier_ID` (`Cashier_ID`),
  CONSTRAINT `transaction_ibfk_1` FOREIGN KEY (`Cashier_ID`) REFERENCES `user` (`ID`),
  CONSTRAINT `chk_discount` CHECK ((`Discount` between 0 and 100)),
  CONSTRAINT `chk_tendered` CHECK ((`AmountTendered` >= 0)),
  CONSTRAINT `chk_total` CHECK ((`TotalAmount` >= 0))
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transaction`
--

LOCK TABLES `transaction` WRITE;
/*!40000 ALTER TABLE `transaction` DISABLE KEYS */;
INSERT INTO `transaction` (`ID`, `TransactionDate`, `CustomerName`, `CustomerPhone`, `PaymentMethod`, `AmountTendered`, `Discount`, `TotalAmount`, `Status`, `Notes`, `Cashier_ID`) VALUES (1,'2025-04-14 02:23:00','Carlo Reyes','09171110001','Cash',20000.00,0.00,15990.00,'Voided',NULL,2),(2,'2025-04-14 05:45:00','Walk-in Customer',NULL,'Cash',5000.00,0.00,4980.00,'Completed',NULL,2),(3,'2025-04-15 01:10:00','Ana Villanueva','09281110002','Cash',0.00,5.00,21241.00,'Completed',NULL,2),(4,'2025-04-15 03:30:00','Mark Lim','09391110003','Card',0.00,0.00,44990.00,'Completed',NULL,2),(5,'2025-04-16 06:00:00','Jenny Tan','09451110004','Cash',10000.00,0.00,9480.00,'Completed',NULL,2),(6,'2025-04-17 08:20:00','Walk-in Customer',NULL,'Cash',3000.00,0.00,2990.00,'Completed',NULL,2),(7,'2025-04-18 02:05:00','Roel Santos','09171110005','Cash',0.00,10.00,16191.00,'Voided',NULL,2),(8,'2025-04-19 07:40:00','Walk-in Customer',NULL,'Cash',8000.00,0.00,7490.00,'Voided',NULL,2);
/*!40000 ALTER TABLE `transaction` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
/*!50032 DROP TRIGGER IF EXISTS trg_restore_stock_on_void */;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_restore_stock_on_void` AFTER UPDATE ON `transaction` FOR EACH ROW BEGIN
    IF NEW.Status IN ('Voided','Refunded') AND OLD.Status = 'Completed' THEN
        UPDATE Stock s
        INNER JOIN SaleItem si ON si.Product_ID = s.Product_ID
        SET s.Quantity = s.Quantity + si.Quantity
        WHERE si.Transaction_ID = NEW.ID;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `Name` varchar(100) NOT NULL,
  `Email` varchar(254) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `Role` enum('Admin','Cashier') NOT NULL,
  `IsActive` tinyint(1) DEFAULT '1',
  `CreatedAt` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Email` (`Email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user`
--

LOCK TABLES `user` WRITE;
/*!40000 ALTER TABLE `user` DISABLE KEYS */;
INSERT INTO `user` (`ID`, `Name`, `Email`, `Password`, `Role`, `IsActive`, `CreatedAt`) VALUES (1,'Admin User','admin@techparts.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Admin',1,'2026-05-16 16:28:32'),(2,'Cashier User','cashier@techparts.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Cashier',1,'2026-05-16 16:28:32'),(3,'System Project Creation','john@techhub.com','$2y$10$EjfW5esIGB5ZOekv0BGmpOxGp45fRVfoM2zaxMHcyrFrQzc48WmpC','Cashier',1,'2026-05-16 16:56:23');
/*!40000 ALTER TABLE `user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'TechParts2'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-19  9:39:25
