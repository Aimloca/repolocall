-- phpMyAdmin SQL Dump
-- version 5.1.1deb5ubuntu1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 02, 2025 at 06:00 AM
-- Server version: 10.6.22-MariaDB-0ubuntu0.22.04.1-log
-- PHP Version: 8.3.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `AIM_ISME`
--
CREATE DATABASE IF NOT EXISTS `iserveme_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `iserveme_db`;

-- --------------------------------------------------------

--
-- Table structure for table `ADMIN`
--

DROP TABLE IF EXISTS `ADMIN`;
CREATE TABLE `ADMIN` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `name` varchar(250) NOT NULL COMMENT 'Name',
  `email` varchar(250) NOT NULL COMMENT 'Email',
  `pass` varchar(250) NOT NULL COMMENT 'Password',
  `language` int(11) NOT NULL DEFAULT 0 COMMENT 'Language: 0=English 1=Greek 2=Russian',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Administrators';

-- --------------------------------------------------------

--
-- Table structure for table `BUY_SERIES`
--

DROP TABLE IF EXISTS `BUY_SERIES`;
CREATE TABLE `BUY_SERIES` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `company_id` int(11) NOT NULL COMMENT 'Company',
  `name` varchar(250) NOT NULL COMMENT 'Name',
  `sequence` int(11) NOT NULL DEFAULT 0 COMMENT 'Sequence',
  `affects_quantity` int(11) NOT NULL DEFAULT 0 COMMENT 'Affects quantity [0: No, 1: Yes Positive, 2: Yes: Negative]',
  `affects_price` int(11) NOT NULL DEFAULT 0 COMMENT 'Affects price [0: No, 1: Yes Positive, 2: Yes: Negative]',
  `active` tinyint(4) NOT NULL DEFAULT 1 COMMENT 'Active',
  `layout` text NOT NULL COMMENT 'Layout',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Purchases Document series';

-- --------------------------------------------------------

--
-- Table structure for table `COMPANY`
--

DROP TABLE IF EXISTS `COMPANY`;
CREATE TABLE `COMPANY` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `name_en` varchar(250) NOT NULL COMMENT 'Name english',
  `address_en` varchar(250) NOT NULL COMMENT 'Address english',
  `city_en` varchar(250) NOT NULL COMMENT 'City english',
  `region_en` varchar(250) NOT NULL COMMENT 'Region english',
  `name_gr` varchar(250) NOT NULL COMMENT 'Name greek',
  `address_gr` varchar(250) NOT NULL COMMENT 'Address greek',
  `city_gr` varchar(250) NOT NULL COMMENT 'City greek',
  `region_gr` varchar(250) NOT NULL COMMENT 'Region greek',
  `name_ru` varchar(250) NULL COMMENT 'Name russian',
  `address_ru` varchar(250) NULL COMMENT 'Address russian',
  `region_ru` varchar(250) NULL COMMENT 'Region russian',
  `tax_number` varchar(250) NOT NULL COMMENT 'Tax number',
  `tax_office` varchar(250) NOT NULL COMMENT 'Tex office',
  `city_ru` varchar(250) NULL COMMENT 'City russian',
  `phone` varchar(250) NOT NULL COMMENT 'Phone',
  `fax` varchar(250) NULL COMMENT 'Fax',
  `email` varchar(250) NOT NULL COMMENT 'Email',
  `contact_email` varchar(250) NULL COMMENT 'Contact email',
  `contact_phone` varchar(250) NULL COMMENT 'Contact phone',
  `contact_name` varchar(250) NULL COMMENT 'Contact name',
  `icon` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Icon',
  `image` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Image',
  `payment_gateway` int(11) NOT NULL DEFAULT 0 COMMENT 'Payment gateway',
  `commission` decimal(9,4) NOT NULL DEFAULT 0.0000 COMMENT 'Commission',
  `salesman_commission` decimal(9,4) NOT NULL DEFAULT 0.0000 COMMENT 'Salesman commission',
  `extra_charge` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Extra charge',
  `mydata_username` varchar(250) NULL COMMENT 'Company MyData Username',
  `mydata_apikey` varchar(250) NULL COMMENT 'Company MyData API Key',
  `country_iso_code` varchar(250) NULL COMMENT 'Company ISO Country code',
  `active` tinyint(4) NOT NULL DEFAULT 1 COMMENT 'Active',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Companies';

-- --------------------------------------------------------

--
-- Table structure for table `COMPANY_CUSTOMER`
--

DROP TABLE IF EXISTS `COMPANY_CUSTOMER`;
CREATE TABLE `COMPANY_CUSTOMER` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `company_id` int(11) NOT NULL COMMENT 'Company id',
  `name` varchar(250) NOT NULL COMMENT 'Name',
  `email` varchar(250) NULL COMMENT 'Email',
  `phone` varchar(250) NULL COMMENT 'Phone',
  `address` varchar(250) NULL COMMENT 'Address',
  `city` varchar(250) NULL COMMENT 'City',
  `region` varchar(250) NULL COMMENT 'Region',
  `postal` varchar(250) NULL COMMENT 'Postal code',
  `tax_number` varchar(250) NULL COMMENT 'Tax number',
  `tax_office` varchar(250) NULL COMMENT 'Tax office',
  `company_name` varchar(250) NULL COMMENT 'Company name',
  `company_commercial_title` varchar(250) DEFAULT NULL COMMENT 'Company commercial title',
  `company_address` varchar(250) NULL COMMENT 'Company address',
  `company_city` varchar(250) NULL COMMENT 'Company city',
  `company_region` varchar(250) NULL COMMENT 'Company region',
  `company_postal` varchar(250) NULL COMMENT 'Company postal code',
  `company_tax_number` varchar(250) NULL COMMENT 'Company tax number',
  `company_tax_office` varchar(250) NULL COMMENT 'Company tax office',
  `company_phone` varchar(250) NULL COMMENT 'Company phone',
  `company_email` varchar(250) NULL COMMENT 'Company email',
  `comments` text NULL COMMENT 'Comments',
  `active` tinyint(4) NOT NULL DEFAULT 1 COMMENT 'Active',
  `language` int(11) NOT NULL DEFAULT 0 COMMENT 'Language: 0=English 1=Greek 2=Russian',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Customers';

-- --------------------------------------------------------

--
-- Table structure for table `CUSTOMER`
--

DROP TABLE IF EXISTS `CUSTOMER`;
CREATE TABLE `CUSTOMER` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `name` varchar(250) NOT NULL COMMENT 'Name',
  `email` varchar(250) NOT NULL COMMENT 'Email',
  `pass` varchar(250) NOT NULL COMMENT 'Password',
  `phone` varchar(250) NOT NULL COMMENT 'Phone',
  `address` varchar(250) NOT NULL DEFAULT '-' COMMENT 'Address',
  `city` varchar(250) NOT NULL DEFAULT '-' COMMENT 'City',
  `region` varchar(250) NOT NULL DEFAULT '-' COMMENT 'Region',
  `tax_number` varchar(250) NOT NULL DEFAULT '-' COMMENT 'Tax number',
  `tax_office` varchar(250) NOT NULL DEFAULT '-' COMMENT 'Tax office',
  `language` int(11) NOT NULL DEFAULT 0 COMMENT 'Language:\r\n0=English\r\n1=Greek\r\n2=Russian',
  `activated` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Activated',
  `active` tinyint(4) NOT NULL DEFAULT 1 COMMENT 'Active',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Customers';

-- --------------------------------------------------------

--
-- Table structure for table `DAY_END`
--

DROP TABLE IF EXISTS `DAY_END`;
CREATE TABLE `DAY_END` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `company_id` int(11) NOT NULL COMMENT 'Company id',
  `day_end` datetime NOT NULL COMMENT 'Day end date time',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Day end';

-- --------------------------------------------------------

--
-- Table structure for table `DEPARTMENT`
--

DROP TABLE IF EXISTS `DEPARTMENT`;
CREATE TABLE `DEPARTMENT` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `company_id` int(11) NOT NULL COMMENT 'Company id',
  `name_en` varchar(250) NOT NULL COMMENT 'Name english',
  `name_gr` varchar(250) NOT NULL COMMENT 'Name greek',
  `name_ru` varchar(250) NULL COMMENT 'Name russian',
  `form_header` text NOT NULL COMMENT 'Form header [default value is set by form]',
  `form_products` text NOT NULL COMMENT 'Form products [default value is set by form]',
  `printable_id` int(11) NULL COMMENT 'Id for printable',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Departments';

-- --------------------------------------------------------

--
-- Table structure for table `DEVICE`
--

DROP TABLE IF EXISTS `DEVICE`;
CREATE TABLE `DEVICE` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `uuid` varchar(250) NOT NULL COMMENT 'Uudi',
  `user_id` int(11) NULL COMMENT 'User id',
  `customer_id` int(11) NULL COMMENT 'Customer id',
  `model` varchar(250) NOT NULL COMMENT 'Model',
  `os` varchar(250) NULL COMMENT 'OS',
  `gcm_token` text NULL COMMENT 'GCM token',
  `hms_token` text NULL COMMENT 'HMS token',
  `ios_token` text NULL COMMENT 'IOS token',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Devices';

-- --------------------------------------------------------

--
-- Table structure for table `MYDATA_DOC_TYPES`
--

DROP TABLE IF EXISTS `MYDATA_DOC_TYPES`;
CREATE TABLE `MYDATA_DOC_TYPES` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `code` varchar(250) NOT NULL COMMENT 'Code',
  `name_en` varchar(250) NOT NULL COMMENT 'Name En',
  `name_gr` varchar(250) NOT NULL COMMENT 'Name Gr',
  `name_ru` varchar(250) NULL COMMENT 'Name Ru',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation date'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='MyData document types';

-- --------------------------------------------------------

--
-- Table structure for table `MYDATA_INCOME_CATEGORY`
--

DROP TABLE IF EXISTS `MYDATA_INCOME_CATEGORY`;
CREATE TABLE `MYDATA_INCOME_CATEGORY` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `code` varchar(250) NOT NULL COMMENT 'Code',
  `name_en` varchar(250) NOT NULL COMMENT 'Name En',
  `name_gr` varchar(250) NOT NULL COMMENT 'Name Gr',
  `name_ru` varchar(250) NULL COMMENT 'Name Ru',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation date'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='MyData document types';

-- --------------------------------------------------------

--
-- Table structure for table `MYDATA_INCOME_TYPE`
--

DROP TABLE IF EXISTS `MYDATA_INCOME_TYPE`;
CREATE TABLE `MYDATA_INCOME_TYPE` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `code` varchar(250) NOT NULL COMMENT 'Code',
  `name_en` varchar(250) NOT NULL COMMENT 'Name En',
  `name_gr` varchar(250) NOT NULL COMMENT 'Name Gr',
  `name_ru` varchar(250) NULL COMMENT 'Name Ru',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation date'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='MyData document types';

-- --------------------------------------------------------

--
-- Table structure for table `MYDATA_LOGS`
--

DROP TABLE IF EXISTS `MYDATA_LOGS`;
CREATE TABLE `MYDATA_LOGS` (
  `id` int(11) NOT NULL,
  `sale_document_id` int(11) NOT NULL,
  `inputXML` text NOT NULL,
  `response` text NOT NULL,
  `status` tinyint(11) NOT NULL COMMENT '0=success, 1=send with error, -1=fail'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `MYDATA_PROVIDER`
--

DROP TABLE IF EXISTS `MYDATA_PROVIDER`;
CREATE TABLE `MYDATA_PROVIDER` (
  `id` int(11) NOT NULL,
  `name` varchar(250) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `NOTIFICATION`
--

DROP TABLE IF EXISTS `NOTIFICATION`;
CREATE TABLE `NOTIFICATION` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `occasion_hash` varchar(250) NULL COMMENT 'Occasion hash',
  `from_session` varchar(250) NULL COMMENT 'From session',
  `from_company_id` int(11) NULL COMMENT 'From company',
  `from_user_id` int(11) NULL COMMENT 'From user',
  `from_customer_id` int(11) NULL COMMENT 'From customer',
  `from_company_customer_id` int(11) NULL COMMENT 'From shop customer',
  `from_admin_id` int(11) NULL COMMENT 'From administrator',
  `title_en` text NOT NULL COMMENT 'Title EN',
  `message_en` text NOT NULL COMMENT 'Message EN',
  `title_gr` text NOT NULL COMMENT 'Title GR',
  `title_ru` text NULL COMMENT 'Title RU',
  `message_gr` text NOT NULL COMMENT 'Message GR',
  `message_ru` text NULL COMMENT 'Message RU',
  `buttons` text NULL COMMENT 'Buttons JSON',
  `action` text NULL COMMENT 'Action',
  `to_session` varchar(250) NULL COMMENT 'To session',
  `to_company_id` int(11) NULL COMMENT 'To company',
  `to_user_id` int(11) NULL COMMENT 'To user',
  `to_customer_id` int(11) NULL COMMENT 'To customer',
  `to_company_customer_id` int(11) NULL COMMENT 'To shop customer',
  `to_admin_id` int(11) NULL COMMENT 'To administrator',
  `visible` tinyint(4) NOT NULL DEFAULT 1 COMMENT 'Visible',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date created',
  `date_sent` datetime NULL COMMENT 'Date sent',
  `date_read` datetime NULL COMMENT 'Date read',
  `date_actioned` datetime NULL COMMENT 'Date actioned',
  `date_deleted` datetime NULL COMMENT 'Date deleted'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Notifications';

-- --------------------------------------------------------

--
-- Table structure for table `ORDERS`
--

DROP TABLE IF EXISTS `ORDERS`;
CREATE TABLE `ORDERS` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `session_id` int(11) NOT NULL COMMENT 'Session id',
  `company_id` int(11) NOT NULL COMMENT 'Company id',
  `waiter_id` int(11) NULL COMMENT 'Waiter',
  `customer_id` int(11) NOT NULL COMMENT 'Customer id',
  `products_amount` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Products amount',
  `products_net_amount` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Products net amount',
  `products_vat_amount` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Products VAT amount',
  `total_amount` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Total amount',
  `paid_amount` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Paid amount',
  `tip_amount` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Tip amount',
  `customer_order` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Customer order',
  `completed` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Completed',
  `date_canceled` datetime NULL COMMENT 'Cancelation date',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Orders';

-- --------------------------------------------------------

--
-- Table structure for table `ORDER_PRODUCT`
--

DROP TABLE IF EXISTS `ORDER_PRODUCT`;
CREATE TABLE `ORDER_PRODUCT` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `order_id` int(11) NOT NULL COMMENT 'Order id',
  `product_id` int(11) NOT NULL COMMENT 'Product id',
  `unit_id` int(11) NOT NULL COMMENT 'Unit id',
  `price` decimal(6,2) NOT NULL COMMENT 'Price',
  `price_specs` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Price specs',
  `quantity` decimal(6,2) NOT NULL DEFAULT 1.00 COMMENT 'Quantity',
  `discount` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Discount',
  `amount` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount',
  `vat_percent` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Vat percent',
  `vat_amount` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Vat amount',
  `net_amount` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Net amount',
  `comment` text NULL COMMENT 'Comment',
  `sent` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Sent',
  `prepared` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Prepared',
  `delivered` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Delivered',
  `paid` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Paid',
  `paid_amount` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Paid amount',
  `paid_quantity` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Paid quantity',
  `sent_to_department_id` int(11) NULL COMMENT 'Sent to department',
  `to_be_canceled` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'To be canceled',
  `date_canceled` datetime NULL COMMENT 'Date canceled',
  `date_printed` datetime NULL COMMENT 'Date time printing',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Order products';

-- --------------------------------------------------------

--
-- Table structure for table `ORDER_PRODUCT_SPEC`
--

DROP TABLE IF EXISTS `ORDER_PRODUCT_SPEC`;
CREATE TABLE `ORDER_PRODUCT_SPEC` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `order_id` int(11) NOT NULL COMMENT 'Order',
  `order_product_row_id` int(11) NOT NULL COMMENT 'Product row id',
  `order_product_id` int(11) NOT NULL COMMENT 'Order product',
  `product_spec_id` int(11) NOT NULL COMMENT 'Product spec id',
  `price` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Price',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Order product specs';

-- --------------------------------------------------------

--
-- Table structure for table `ORDER_TABLE`
--

DROP TABLE IF EXISTS `ORDER_TABLE`;
CREATE TABLE `ORDER_TABLE` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `order_id` int(11) NOT NULL COMMENT 'Order id',
  `table_id` int(11) NOT NULL COMMENT 'Table id',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Order tables';

-- --------------------------------------------------------

--
-- Table structure for table `PARAMETERS`
--

DROP TABLE IF EXISTS `PARAMETERS`;
CREATE TABLE `PARAMETERS` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `company_id` int(11) NOT NULL COMMENT 'Company',
  `mydata_debug` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'MyData debug',
  `mydata_username` varchar(250) NULL COMMENT 'MyData username',
  `mydata_api_key` varchar(250) NULL COMMENT 'MyData API key',
  `mydata_country_iso_code` varchar(250) NULL COMMENT 'MyData country ISO code',
  `mydata_username_debug` varchar(250) NULL COMMENT 'MyData username debug',
  `mydata_api_key_debug` varchar(250) NULL COMMENT 'MyData API Key debug',
  `mydata_on_error_proceed` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'MyData proceed on error',
  `mydata_send_realtime` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'MyData send realtime data',
  `sale_document_group_items` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Group items in sales  documents',
  `aade_username` varchar(250) NOT NULL COMMENT 'AADE Username',
  `aade_password` varchar(250) NOT NULL COMMENT 'AADE Password',
  `aade_caller` varchar(250) NOT NULL COMMENT 'AADE Caller',
  `every_pay_debug` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Every pay debug',
  `every_pay_public_key` varchar(250) NULL COMMENT 'Every pay public key',
  `every_pay_private_key` varchar(250) NULL COMMENT 'Every pay private key',
  `every_pay_public_key_debug` varchar(250) NULL COMMENT 'Every pay public key debug',
  `every_pay_private_key_debug` varchar(250) NULL COMMENT 'Every pay private key debug',
  `viva_debug` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Viva debug',
  `viva_merchant_id` varchar(250) NULL COMMENT 'Viva merchant id',
  `viva_api_key` varchar(250) NULL COMMENT 'Viva api key',
  `viva_merchant_id_debug` varchar(250) NULL COMMENT 'Viva merchant id debug',
  `viva_api_key_debug` varchar(250) NULL COMMENT 'Viva api key debug',
  `auto_send_order_product_to_department_to_prepared` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'auto print order product  printer department',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation date'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Parameters';

-- --------------------------------------------------------

--
-- Table structure for table `PAYMENT`
--

DROP TABLE IF EXISTS `PAYMENT`;
CREATE TABLE `PAYMENT` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `company_id` int(11) NOT NULL COMMENT 'Company',
  `order_id` int(11) NOT NULL COMMENT 'Order',
  `customer_id` int(11) NOT NULL COMMENT 'Customer',
  `user_id` int(11) NULL COMMENT 'User id',
  `type` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Type [0: Cash, 1: Card]',
  `gateway` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Gateway [0: Cash, 1: EveryPay, 2: Viva]',
  `amount` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount',
  `order_products_rows_ids` text NOT NULL COMMENT 'Order products rows ids',
  `products` text NOT NULL COMMENT 'Products',
  `products_amount` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Products amount',
  `rows_ids_qnt` text NULL COMMENT 'Rows ids and quantities',
  `tip_user_id` int(11) NULL COMMENT 'Tip for waiter',
  `tip_amount` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Tip amount',
  `session_id` varchar(250) NOT NULL COMMENT 'Session',
  `uuid` text NULL COMMENT 'UUID',
  `token` text NULL COMMENT 'Token',
  `form_response` text NULL COMMENT 'Form response',
  `charge_response` text NULL COMMENT 'Charge response',
  `completed` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Completed',
  `holder_user_id` int(11) NULL COMMENT 'Holder user id',
  `status` text NULL COMMENT 'Status',
  `error` text NULL COMMENT 'Error',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation date'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Payments';

-- --------------------------------------------------------

--
-- Table structure for table `PRODUCT`
--

DROP TABLE IF EXISTS `PRODUCT`;
CREATE TABLE `PRODUCT` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `company_id` int(11) NOT NULL COMMENT 'Company id',
  `category_id` int(11) NOT NULL COMMENT 'Category id',
  `name_en` varchar(250) NOT NULL COMMENT 'Name english',
  `description_en` text NOT NULL COMMENT 'Description english',
  `name_gr` varchar(250) NOT NULL COMMENT 'Name greek',
  `description_gr` text NOT NULL COMMENT 'Description greek',
  `name_ru` varchar(250) NULL COMMENT 'Name russian',
  `description_ru` text NULL COMMENT 'Description russian',
  `icon` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Icon',
  `image` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Image',
  `image1` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Image 1',
  `image2` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Image 2',
  `saleable` tinyint(4) NOT NULL DEFAULT 1 COMMENT 'Saleable',
  `visible` tinyint(4) NOT NULL DEFAULT 1 COMMENT 'Visible',
  `basic_unit_id` int(11) NULL COMMENT 'Basic unit',
  `basic_unit_price` double(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Basic price',
  `basic_unit_quantity` double(6,2) NOT NULL DEFAULT 1.00 COMMENT 'Basic unit quantity',
  `vat_category_id` int(11) NOT NULL COMMENT 'Vat category',
  `path_en` varchar(250) NOT NULL COMMENT 'Path EN',
  `path_gr` varchar(250) NOT NULL COMMENT 'Path GR',
  `path_ru` varchar(250) NULL COMMENT 'Path RU',
  `sorting` int(11) NOT NULL DEFAULT 1 COMMENT 'Sorting',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Products';

-- --------------------------------------------------------

--
-- Table structure for table `PRODUCT_CATEGORY`
--

DROP TABLE IF EXISTS `PRODUCT_CATEGORY`;
CREATE TABLE `PRODUCT_CATEGORY` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `company_id` int(11) NOT NULL COMMENT 'Company id',
  `parent_id` int(11) NOT NULL DEFAULT 0 COMMENT 'Parent id',
  `department_id` int(11) NOT NULL,
  `name_en` varchar(250) NOT NULL COMMENT 'Name english',
  `description_en` text NOT NULL COMMENT 'Description english',
  `name_gr` varchar(250) NOT NULL COMMENT 'Name greek',
  `description_gr` text NOT NULL COMMENT 'Description greek',
  `name_ru` varchar(250) NULL COMMENT 'Name russian',
  `description_ru` text NULL COMMENT 'Description russian',
  `path_en` text NULL COMMENT 'Path En',
  `path_gr` text NULL COMMENT 'Path Gr',
  `path_ru` text NULL COMMENT 'Path Ru',
  `path_ids` varchar(250) NULL COMMENT 'Path ids',
  `mydata_income_category_id` int(11) NULL COMMENT 'MyData income category',
  `mydata_retail_income_type` int(11) NULL COMMENT 'MyData retail income type',
  `mydata_wholesale_income_type` int(11) NULL COMMENT 'MyData wholesale income type',
  `icon` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Icon',
  `image` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Image',
  `active` tinyint(4) NOT NULL DEFAULT 1 COMMENT 'Active',
  `visible` tinyint(4) NOT NULL DEFAULT 1 COMMENT 'Visible',
  `sorting` int(11) NOT NULL DEFAULT 1 COMMENT 'Sorting',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Product categories';

-- --------------------------------------------------------

--
-- Table structure for table `PRODUCT_COMPOSITION`
--

DROP TABLE IF EXISTS `PRODUCT_COMPOSITION`;
CREATE TABLE `PRODUCT_COMPOSITION` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `product_id` int(11) NOT NULL COMMENT 'Product',
  `component_id` int(11) NOT NULL COMMENT 'Component',
  `unit_id` int(11) NOT NULL COMMENT 'Unit',
  `quantity` double(6,2) NOT NULL COMMENT 'Quantity',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Products compositions';

-- --------------------------------------------------------

--
-- Table structure for table `PRODUCT_SPEC`
--

DROP TABLE IF EXISTS `PRODUCT_SPEC`;
CREATE TABLE `PRODUCT_SPEC` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `product_id` int(11) NOT NULL COMMENT 'Product id',
  `spec_id` int(11) NOT NULL COMMENT 'Spec id',
  `name_en` varchar(250) NOT NULL DEFAULT '' COMMENT 'Name english',
  `description_en` text NOT NULL COMMENT 'Description english',
  `name_gr` varchar(250) NULL COMMENT 'Name greek',
  `description_gr` text NULL COMMENT 'Description greek',
  `name_ru` varchar(250) NULL COMMENT 'Name russian',
  `description_ru` text NULL COMMENT 'Description russian',
  `price` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Price',
  `sequence` int(11) NOT NULL DEFAULT 0 COMMENT 'Sequence',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Product spec';

-- --------------------------------------------------------

--
-- Table structure for table `PRODUCT_UNIT`
--

DROP TABLE IF EXISTS `PRODUCT_UNIT`;
CREATE TABLE `PRODUCT_UNIT` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `product_id` int(11) NOT NULL COMMENT 'Product id',
  `unit_id` int(11) NOT NULL COMMENT 'Unit id',
  `quantity` decimal(6,2) NOT NULL DEFAULT 1.00 COMMENT 'Quantity',
  `price` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Price',
  `saleable` tinyint(4) NOT NULL DEFAULT 1 COMMENT 'Saleable',
  `sequence` int(11) NOT NULL DEFAULT 0 COMMENT 'Sequence',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Product units';

-- --------------------------------------------------------

--
-- Table structure for table `ROOM`
--

DROP TABLE IF EXISTS `ROOM`;
CREATE TABLE `ROOM` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `company_id` int(11) NOT NULL COMMENT 'Company',
  `name` varchar(250) NOT NULL COMMENT 'Name',
  `sorting` int(11) NOT NULL DEFAULT 0 COMMENT 'Sorting',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Rooms';

-- --------------------------------------------------------

--
-- Table structure for table `SALESMAN`
--

DROP TABLE IF EXISTS `SALESMAN`;
CREATE TABLE `SALESMAN` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `name` varchar(250) NOT NULL COMMENT 'Name',
  `email` varchar(250) NOT NULL COMMENT 'Email',
  `pass` varchar(250) NOT NULL COMMENT 'Password',
  `icon` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Icon',
  `active` tinyint(4) NOT NULL DEFAULT 1 COMMENT 'Active',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Salesmen';

-- --------------------------------------------------------

--
-- Table structure for table `SALE_DOCUMENT`
--

DROP TABLE IF EXISTS `SALE_DOCUMENT`;
CREATE TABLE `SALE_DOCUMENT` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `company_id` int(11) NOT NULL COMMENT 'Company id',
  `series_id` int(11) NOT NULL COMMENT 'Series id',
  `code_sequence` varchar(250) NULL COMMENT 'Series code sequence',
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE current_timestamp() COMMENT 'Date',
  `customer_id` int(11) NOT NULL COMMENT 'Customer',
  `printed` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Printed',
  `relative_document_id` int(11) NULL COMMENT 'Relative document',
  `relative_order_id` int(11) NULL COMMENT 'Relative order',
  `products_amount` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Products amount',
  `products_net_amount` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Products net amount',
  `products_vat_amount` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Products VAT amount',
  `tip_amount` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Tip amount',
  `total_amount` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Total amount',
  `comments` text NULL COMMENT 'Comments',
  `customer_order` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Customer order',
  `cancelled` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Cancelled',
  `mydata_doc_type` int(11) NULL,
  `mydata_mark` varchar(250) NULL COMMENT 'MyData mark',
  `mydata_uid` varchar(250) NULL,
  `mydata_authentication_code` varchar(250) NULL,
  `mydata_qr_url` text NULL COMMENT 'MyData QR url',
  `mydata_provider` int(11) NOT NULL,
  `date_printed` datetime NULL COMMENT 'Print date',
  `date_cancelled` datetime NULL COMMENT 'Cancellation date',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date created',
  `mydata_xml` longtext NULL,
  `peppol_xml` longtext NULL,
  `response_xml` longtext NULL,
  `reprocess_flag` int(11) NOT NULL COMMENT '1=reprint, 2=mydata_resend',
  `request_xml` tinyint(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Sales documents';

-- --------------------------------------------------------

--
-- Table structure for table `SALE_DOCUMENT_PRODUCT`
--

DROP TABLE IF EXISTS `SALE_DOCUMENT_PRODUCT`;
CREATE TABLE `SALE_DOCUMENT_PRODUCT` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `sale_document_id` int(11) NOT NULL COMMENT 'Document',
  `product_id` int(11) NOT NULL COMMENT 'Product',
  `unit_id` int(11) NOT NULL COMMENT 'Unit',
  `specs_json` text NOT NULL COMMENT 'Specs',
  `quantity` decimal(6,2) NOT NULL DEFAULT 1.00 COMMENT 'Quantity',
  `price` decimal(6,2) NOT NULL COMMENT 'Price',
  `discount` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Discount',
  `amount` decimal(6,2) NOT NULL COMMENT 'Amount',
  `vat_percent` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Vat percent',
  `vat_amount` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'VAT amount',
  `net_amount` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Net amount',
  `mydata_income_category` int(11) NULL,
  `mydata_income_type` int(11) NULL,
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation date'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Sale document products';

-- --------------------------------------------------------

--
-- Table structure for table `SALE_SERIES`
--

DROP TABLE IF EXISTS `SALE_SERIES`;
CREATE TABLE `SALE_SERIES` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `company_id` int(11) NOT NULL COMMENT 'Company',
  `name` varchar(250) NOT NULL COMMENT 'Name',
  `code` varchar(250) NOT NULL COMMENT 'Code',
  `sequence` int(11) NOT NULL DEFAULT 0 COMMENT 'Sequence',
  `allow_mydata_send` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Upload to MyData',
  `retail` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Retail',
  `mydata_doc_type_id` int(11) NULL COMMENT 'MyData document type',
  `affects_quantity` int(11) NOT NULL DEFAULT 0 COMMENT 'Affects quantity [0: No, 1: Yes Positive, 2: Yes: Negative]',
  `affects_price` int(11) NOT NULL DEFAULT 0 COMMENT 'Affects price [0: No, 1: Yes Positive, 2: Yes: Negative]',
  `creates_payment` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Creates payment',
  `is_default` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Default',
  `cancelling` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Cancelling',
  `active` tinyint(4) NOT NULL DEFAULT 1 COMMENT 'Active',
  `form_header` text NULL COMMENT 'Form header',
  `form_products` text NULL COMMENT 'Form products',
  `form_footer` text NULL COMMENT 'Form footer',
  `printable_id` int(11) NOT NULL COMMENT 'Print server printable_id',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Sales series';

-- --------------------------------------------------------

--
-- Table structure for table `SPEC`
--

DROP TABLE IF EXISTS `SPEC`;
CREATE TABLE `SPEC` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `company_id` int(11) NOT NULL COMMENT 'Company id',
  `name_en` varchar(250) NOT NULL COMMENT 'Name english',
  `name_gr` varchar(250) NOT NULL COMMENT 'Name greek',
  `name_ru` varchar(250) NULL COMMENT 'Name russian',
  `description_en` text NOT NULL COMMENT 'Description english',
  `description_gr` text NOT NULL COMMENT 'Description greek',
  `description_ru` text NULL COMMENT 'Description russian',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Specs';

-- --------------------------------------------------------

--
-- Table structure for table `STRINGS`
--

DROP TABLE IF EXISTS `STRINGS`;
CREATE TABLE `STRINGS` (
  `id` varchar(250) NOT NULL COMMENT 'Id',
  `en` text NULL COMMENT 'English',
  `gr` text NULL COMMENT 'Greek',
  `ru` text NOT NULL COMMENT 'Russian',
  `position` varchar(400) NULL COMMENT 'Position',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Strings';

-- --------------------------------------------------------

--
-- Table structure for table `STRINGS_MISSING`
--

DROP TABLE IF EXISTS `STRINGS_MISSING`;
CREATE TABLE `STRINGS_MISSING` (
  `id` varchar(250) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Missing strings';

-- --------------------------------------------------------

--
-- Table structure for table `TABLES`
--

DROP TABLE IF EXISTS `TABLES`;
CREATE TABLE `TABLES` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `company_id` int(11) NOT NULL COMMENT 'Company id',
  `room_id` int(11) NOT NULL COMMENT 'Room',
  `qr_code` varchar(250) NOT NULL COMMENT 'QR code',
  `name` varchar(250) NOT NULL COMMENT 'Name',
  `reserved` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Reserved',
  `occupied` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Occupied',
  `sorting` int(11) NOT NULL DEFAULT 100 COMMENT 'Sorting',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Tables';

-- --------------------------------------------------------

--
-- Table structure for table `TIP`
--

DROP TABLE IF EXISTS `TIP`;
CREATE TABLE `TIP` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `company_id` int(11) NOT NULL COMMENT 'Company',
  `type` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Type [0=Amount, 1=Percent]',
  `value` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Value'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Tips';

-- --------------------------------------------------------

--
-- Table structure for table `UNIT`
--

DROP TABLE IF EXISTS `UNIT`;
CREATE TABLE `UNIT` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `company_id` int(11) NOT NULL COMMENT 'Company id',
  `name_en` varchar(250) NOT NULL COMMENT 'Name english',
  `name_gr` varchar(250) NOT NULL COMMENT 'Name greek',
  `name_ru` varchar(250) NULL COMMENT 'Name russian',
  `is_integer` tinyint(4) NOT NULL DEFAULT 1 COMMENT 'Integer',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Units';

-- --------------------------------------------------------

--
-- Table structure for table `USER`
--

DROP TABLE IF EXISTS `USER`;
CREATE TABLE `USER` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `company_id` int(11) NOT NULL COMMENT 'Company id',
  `name` varchar(250) NOT NULL COMMENT 'Name',
  `email` varchar(250) NOT NULL COMMENT 'Email',
  `pass` varchar(250) NOT NULL COMMENT 'Password',
  `position` int(11) NOT NULL DEFAULT 0 COMMENT 'Position: \r\n-1=Administrator\r\n0=Shop manager\r\n1=Barista\r\n2=Preparation\r\n3=Waiter\r\n',
  `department_id` int(11) NULL COMMENT 'Department',
  `icon` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Icon',
  `customer_notification` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Receive customer notification',
  `active` tinyint(4) NOT NULL DEFAULT 1 COMMENT 'Active',
  `language` int(11) NOT NULL DEFAULT 0 COMMENT 'Language: 0=English 1=Greek 2=Russian',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Users';

-- --------------------------------------------------------

--
-- Table structure for table `VAT_CATEGORY`
--

DROP TABLE IF EXISTS `VAT_CATEGORY`;
CREATE TABLE `VAT_CATEGORY` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `name` varchar(250) NOT NULL COMMENT 'Name',
  `percent` decimal(6,2) NOT NULL COMMENT 'Percent',
  `low_percent` decimal(6,2) NOT NULL COMMENT 'Low Percent',
  `vat_category_mydata_code` int(11) NOT NULL,
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation date'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Vat Categories';

-- --------------------------------------------------------

--
-- Table structure for table `WAITER_TABLES`
--

DROP TABLE IF EXISTS `WAITER_TABLES`;
CREATE TABLE `WAITER_TABLES` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `user_id` int(11) NOT NULL COMMENT 'User id (waiter)',
  `table_id` int(11) NOT NULL COMMENT 'Table id',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Waiter tables';

-- --------------------------------------------------------

--
-- Table structure for table `WORKING_HOURS`
--

DROP TABLE IF EXISTS `WORKING_HOURS`;
CREATE TABLE `WORKING_HOURS` (
  `id` int(11) NOT NULL COMMENT 'Id',
  `company_id` int(11) NOT NULL COMMENT 'Company id',
  `day_start` time NOT NULL DEFAULT '07:00:00' COMMENT 'Day start',
  `monday_start` time DEFAULT '07:00:00' COMMENT 'Monday start',
  `monday_end` time DEFAULT '23:00:00' COMMENT 'Monday end',
  `tuesday_start` time DEFAULT '07:00:00' COMMENT 'Tuesday start',
  `tuesday_end` time DEFAULT '23:00:00' COMMENT 'Tuesday end',
  `wednesday_start` time DEFAULT '07:00:00' COMMENT 'Wednesday start',
  `wednesday_end` time DEFAULT '23:00:00' COMMENT '	\r\nWednesday end',
  `thursday_start` time DEFAULT '07:00:00' COMMENT 'Thursday start',
  `thursday_end` time DEFAULT '23:00:00' COMMENT 'Thursday end',
  `friday_start` time DEFAULT '07:00:00' COMMENT 'Friday start',
  `friday_end` time DEFAULT '23:00:00' COMMENT 'Friday end',
  `saturday_start` time DEFAULT '07:00:00' COMMENT 'Saturday start',
  `saturday_end` time DEFAULT '23:00:00' COMMENT 'Saturday end',
  `sunday_start` time DEFAULT '07:00:00' COMMENT 'Sunday start',
  `sunday_end` time DEFAULT '23:00:00' COMMENT 'Sunday end',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Working hours';

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ADMIN`
--
ALTER TABLE `ADMIN`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `BUY_SERIES`
--
ALTER TABLE `BUY_SERIES`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_BUY_SERIES__COMPANY_ID___COMPANY__ID` (`company_id`);

--
-- Indexes for table `COMPANY`
--
ALTER TABLE `COMPANY`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `COMPANY_CUSTOMER`
--
ALTER TABLE `COMPANY_CUSTOMER`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_COMPANY_CUSTOMER__COMPANY_ID___COMPANY__ID` (`company_id`);

--
-- Indexes for table `CUSTOMER`
--
ALTER TABLE `CUSTOMER`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `DAY_END`
--
ALTER TABLE `DAY_END`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `DEPARTMENT`
--
ALTER TABLE `DEPARTMENT`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_DEPARTMENT__COMPANY_ID___COMPANY__ID` (`company_id`);

--
-- Indexes for table `DEVICE`
--
ALTER TABLE `DEVICE`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_DEVICE__USER_ID___USER__ID` (`user_id`),
  ADD KEY `FK_DEVICE__CUSTOMER_ID___CUSTOMER__ID` (`customer_id`);

--
-- Indexes for table `MYDATA_DOC_TYPES`
--
ALTER TABLE `MYDATA_DOC_TYPES`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `MYDATA_INCOME_CATEGORY`
--
ALTER TABLE `MYDATA_INCOME_CATEGORY`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `MYDATA_INCOME_TYPE`
--
ALTER TABLE `MYDATA_INCOME_TYPE`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `MYDATA_LOGS`
--
ALTER TABLE `MYDATA_LOGS`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `MYDATA_PROVIDER`
--
ALTER TABLE `MYDATA_PROVIDER`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `NOTIFICATION`
--
ALTER TABLE `NOTIFICATION`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ORDERS`
--
ALTER TABLE `ORDERS`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_ORDERS__COMPANY_ID___COMPANY__ID` (`company_id`);

--
-- Indexes for table `ORDER_PRODUCT`
--
ALTER TABLE `ORDER_PRODUCT`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_ORDER_PRODUCT__PRODUCT_ID___PRODUCT__ID` (`product_id`),
  ADD KEY `FK_ORDER_PRODUCT__ORDER_ID___ORDERS__ID` (`order_id`),
  ADD KEY `FK_ORDER_PRODUCT__UNIT_ID___UNIT__ID` (`unit_id`);

--
-- Indexes for table `ORDER_PRODUCT_SPEC`
--
ALTER TABLE `ORDER_PRODUCT_SPEC`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_O_P_S__PRODUCT_ROW_ID___O_P__ID` (`order_product_row_id`),
  ADD KEY `FK_ORDER_PRODUCT_SPEC__SPEC_ID___PRODUCT_SPEC__ID` (`product_spec_id`);

--
-- Indexes for table `ORDER_TABLE`
--
ALTER TABLE `ORDER_TABLE`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_ORDER_TABLE__ORDER_ID___ORDER__ID` (`order_id`),
  ADD KEY `FK_ORDER_TABLE__TABLE_ID___TABLES__ID` (`table_id`);

--
-- Indexes for table `PARAMETERS`
--
ALTER TABLE `PARAMETERS`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_PARAMETERS__COMPANY_ID___COMPANY__ID` (`company_id`);

--
-- Indexes for table `PAYMENT`
--
ALTER TABLE `PAYMENT`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `PRODUCT`
--
ALTER TABLE `PRODUCT`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_PRODUCT__COMPANY_ID___COMPANY__ID` (`company_id`),
  ADD KEY `FK_PRODUCT__CATEGORY_ID___PRODUCT_CATEGORY__ID` (`category_id`),
  ADD KEY `FK_PRODUCT__VAT_CATEGORY_ID___VAT_CATEGORY__ID` (`vat_category_id`);

--
-- Indexes for table `PRODUCT_CATEGORY`
--
ALTER TABLE `PRODUCT_CATEGORY`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_PRODUCT_CATEGORY__COMPANY_ID___COMPANY__ID` (`company_id`),
  ADD KEY `FK_PRODUCT_CATEGORY__MD_IC_ID___MYDATA_INCOME_CATEGORY__ID` (`mydata_income_category_id`),
  ADD KEY `FK_PRODUCT_CATEGORY__MD_WS_IT_ID__MYDATA_INCOME_TYPE__ID` (`mydata_wholesale_income_type`),
  ADD KEY `FK_PRODUCT_CATEGORY__MD_RT_IT_ID__MYDATA_INCOME_TYPE__ID` (`mydata_retail_income_type`);

--
-- Indexes for table `PRODUCT_COMPOSITION`
--
ALTER TABLE `PRODUCT_COMPOSITION`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `PRODUCT_SPEC`
--
ALTER TABLE `PRODUCT_SPEC`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_PRODUCT_SPEC__PRODUCT_ID___PRODUCT__ID` (`product_id`),
  ADD KEY `FK_PRODUCT_SPEC__SPEC_ID___SPEC__ID` (`spec_id`);

--
-- Indexes for table `PRODUCT_UNIT`
--
ALTER TABLE `PRODUCT_UNIT`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_PRODUCT_UNIT__PRODUCT_ID___PRODUCT__ID` (`product_id`),
  ADD KEY `FK_PRODUCT_UNIT__UNIT_ID___UNIT__ID` (`unit_id`);

--
-- Indexes for table `ROOM`
--
ALTER TABLE `ROOM`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_ROOM__COMPANY_ID___COMPANY__ID` (`company_id`);

--
-- Indexes for table `SALESMAN`
--
ALTER TABLE `SALESMAN`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `SALE_DOCUMENT`
--
ALTER TABLE `SALE_DOCUMENT`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_SALE_DOCUMENT__COMPANY_ID___COMPANY__ID` (`company_id`),
  ADD KEY `FK_SALE_DOCUMENT__SERIES_ID___SALE_SERIES__ID` (`series_id`),
  ADD KEY `FK_SALE_DOCUMENT__CUSTOMER_ID___COMPANY_CUSTOMER__ID` (`customer_id`),
  ADD KEY `FK_SALE_DOC__MD_DOC_TYPE_ID__MYDATA_DOC_TYPES__ID` (`mydata_doc_type`);

--
-- Indexes for table `SALE_DOCUMENT_PRODUCT`
--
ALTER TABLE `SALE_DOCUMENT_PRODUCT`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_SDP__SALE_DOCUMENT_ID___SD__ID` (`sale_document_id`),
  ADD KEY `FK_SDP__PRODUCT_ID___PRODUCT__ID` (`product_id`),
  ADD KEY `FK_SDP_UNIT_ID___UNIT__ID` (`unit_id`),
  ADD KEY `FK_SALE_DOC_PRD__MD_INCOME_CATEG_ID__MYDATA_INCOME_CATEGORY__ID` (`mydata_income_category`),
  ADD KEY `FK_SALE_DOC_PRD__MD_INCOME_TYPE_ID__MYDATA_INCOME_TYPE__ID` (`mydata_income_type`);

--
-- Indexes for table `SALE_SERIES`
--
ALTER TABLE `SALE_SERIES`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UK_SALE_SERIES__CODE` (`code`,`company_id`) USING BTREE,
  ADD KEY `FK_SALE_SERIES__COMPANY_ID___COMPANY__ID` (`company_id`),
  ADD KEY `FK_PRODUCT_CATEGORY__MD_DT_ID__MYDATA_DOC_TYPES__ID` (`mydata_doc_type_id`);

--
-- Indexes for table `SPEC`
--
ALTER TABLE `SPEC`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_SPEC__COMPANY_ID___COMPANY__ID` (`company_id`);

--
-- Indexes for table `STRINGS`
--
ALTER TABLE `STRINGS`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `STRINGS_MISSING`
--
ALTER TABLE `STRINGS_MISSING`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `TABLES`
--
ALTER TABLE `TABLES`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_TABLES__COMPANY_ID___COMPANY__ID` (`company_id`),
  ADD KEY `FK_TABLES__ROOM_ID___ROOM__ID` (`room_id`);

--
-- Indexes for table `TIP`
--
ALTER TABLE `TIP`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `UNIT`
--
ALTER TABLE `UNIT`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_UNIT__COMPANY_ID___COMPANY__ID` (`company_id`);

--
-- Indexes for table `USER`
--
ALTER TABLE `USER`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_USER__COMPANY_ID___COMPANY__ID` (`company_id`);

--
-- Indexes for table `VAT_CATEGORY`
--
ALTER TABLE `VAT_CATEGORY`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `WAITER_TABLES`
--
ALTER TABLE `WAITER_TABLES`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_WAITER_TABLES__USER_ID___USER__ID` (`user_id`),
  ADD KEY `FK_WAITER_TABLES__TABLE_ID___TABLES__ID` (`table_id`);

--
-- Indexes for table `WORKING_HOURS`
--
ALTER TABLE `WORKING_HOURS`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_WORKING_HOURS__COMPANY_ID___COMPANY__ID` (`company_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ADMIN`
--
ALTER TABLE `ADMIN`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `BUY_SERIES`
--
ALTER TABLE `BUY_SERIES`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `COMPANY`
--
ALTER TABLE `COMPANY`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `COMPANY_CUSTOMER`
--
ALTER TABLE `COMPANY_CUSTOMER`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `CUSTOMER`
--
ALTER TABLE `CUSTOMER`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `DAY_END`
--
ALTER TABLE `DAY_END`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `DEPARTMENT`
--
ALTER TABLE `DEPARTMENT`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `DEVICE`
--
ALTER TABLE `DEVICE`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `MYDATA_DOC_TYPES`
--
ALTER TABLE `MYDATA_DOC_TYPES`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `MYDATA_INCOME_CATEGORY`
--
ALTER TABLE `MYDATA_INCOME_CATEGORY`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `MYDATA_INCOME_TYPE`
--
ALTER TABLE `MYDATA_INCOME_TYPE`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `MYDATA_LOGS`
--
ALTER TABLE `MYDATA_LOGS`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `MYDATA_PROVIDER`
--
ALTER TABLE `MYDATA_PROVIDER`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `NOTIFICATION`
--
ALTER TABLE `NOTIFICATION`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `ORDERS`
--
ALTER TABLE `ORDERS`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `ORDER_PRODUCT`
--
ALTER TABLE `ORDER_PRODUCT`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `ORDER_PRODUCT_SPEC`
--
ALTER TABLE `ORDER_PRODUCT_SPEC`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `ORDER_TABLE`
--
ALTER TABLE `ORDER_TABLE`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `PARAMETERS`
--
ALTER TABLE `PARAMETERS`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `PAYMENT`
--
ALTER TABLE `PAYMENT`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `PRODUCT`
--
ALTER TABLE `PRODUCT`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `PRODUCT_CATEGORY`
--
ALTER TABLE `PRODUCT_CATEGORY`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `PRODUCT_COMPOSITION`
--
ALTER TABLE `PRODUCT_COMPOSITION`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `PRODUCT_SPEC`
--
ALTER TABLE `PRODUCT_SPEC`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `PRODUCT_UNIT`
--
ALTER TABLE `PRODUCT_UNIT`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `ROOM`
--
ALTER TABLE `ROOM`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `SALESMAN`
--
ALTER TABLE `SALESMAN`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `SALE_DOCUMENT`
--
ALTER TABLE `SALE_DOCUMENT`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `SALE_DOCUMENT_PRODUCT`
--
ALTER TABLE `SALE_DOCUMENT_PRODUCT`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `SALE_SERIES`
--
ALTER TABLE `SALE_SERIES`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `SPEC`
--
ALTER TABLE `SPEC`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `TABLES`
--
ALTER TABLE `TABLES`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `TIP`
--
ALTER TABLE `TIP`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `UNIT`
--
ALTER TABLE `UNIT`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `USER`
--
ALTER TABLE `USER`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `VAT_CATEGORY`
--
ALTER TABLE `VAT_CATEGORY`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `WAITER_TABLES`
--
ALTER TABLE `WAITER_TABLES`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- AUTO_INCREMENT for table `WORKING_HOURS`
--
ALTER TABLE `WORKING_HOURS`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id';

--
-- Constraints for dumped tables
--

--
-- Constraints for table `BUY_SERIES`
--
ALTER TABLE `BUY_SERIES`
  ADD CONSTRAINT `FK_BUY_SERIES__COMPANY_ID___COMPANY__ID` FOREIGN KEY (`company_id`) REFERENCES `COMPANY` (`id`);

--
-- Constraints for table `COMPANY_CUSTOMER`
--
ALTER TABLE `COMPANY_CUSTOMER`
  ADD CONSTRAINT `FK_COMPANY_CUSTOMER__COMPANY_ID___COMPANY__ID` FOREIGN KEY (`company_id`) REFERENCES `COMPANY` (`id`);

--
-- Constraints for table `DEPARTMENT`
--
ALTER TABLE `DEPARTMENT`
  ADD CONSTRAINT `FK_DEPARTMENT__COMPANY_ID___COMPANY__ID` FOREIGN KEY (`company_id`) REFERENCES `COMPANY` (`id`);

--
-- Constraints for table `DEVICE`
--
ALTER TABLE `DEVICE`
  ADD CONSTRAINT `FK_DEVICE__CUSTOMER_ID___CUSTOMER__ID` FOREIGN KEY (`customer_id`) REFERENCES `CUSTOMER` (`id`),
  ADD CONSTRAINT `FK_DEVICE__USER_ID___USER__ID` FOREIGN KEY (`user_id`) REFERENCES `USER` (`id`);

--
-- Constraints for table `ORDERS`
--
ALTER TABLE `ORDERS`
  ADD CONSTRAINT `FK_ORDERS__COMPANY_ID___COMPANY__ID` FOREIGN KEY (`company_id`) REFERENCES `COMPANY` (`id`);

--
-- Constraints for table `ORDER_PRODUCT`
--
ALTER TABLE `ORDER_PRODUCT`
  ADD CONSTRAINT `FK_ORDER_PRODUCT__ORDER_ID___ORDERS__ID` FOREIGN KEY (`order_id`) REFERENCES `ORDERS` (`id`),
  ADD CONSTRAINT `FK_ORDER_PRODUCT__PRODUCT_ID___PRODUCT__ID` FOREIGN KEY (`product_id`) REFERENCES `PRODUCT` (`id`),
  ADD CONSTRAINT `FK_ORDER_PRODUCT__UNIT_ID___UNIT__ID` FOREIGN KEY (`unit_id`) REFERENCES `UNIT` (`id`);

--
-- Constraints for table `ORDER_PRODUCT_SPEC`
--
ALTER TABLE `ORDER_PRODUCT_SPEC`
  ADD CONSTRAINT `FK_ORDER_PRODUCT_SPEC__SPEC_ID___PRODUCT_SPEC__ID` FOREIGN KEY (`product_spec_id`) REFERENCES `SPEC` (`id`),
  ADD CONSTRAINT `FK_O_P_S__PRODUCT_ROW_ID___O_P__ID` FOREIGN KEY (`order_product_row_id`) REFERENCES `ORDER_PRODUCT` (`id`);

--
-- Constraints for table `ORDER_TABLE`
--
ALTER TABLE `ORDER_TABLE`
  ADD CONSTRAINT `FK_ORDER_TABLE__ORDER_ID___ORDER__ID` FOREIGN KEY (`order_id`) REFERENCES `ORDERS` (`id`),
  ADD CONSTRAINT `FK_ORDER_TABLE__TABLE_ID___TABLES__ID` FOREIGN KEY (`table_id`) REFERENCES `TABLES` (`id`);

--
-- Constraints for table `PARAMETERS`
--
ALTER TABLE `PARAMETERS`
  ADD CONSTRAINT `FK_PARAMETERS__COMPANY_ID___COMPANY__ID` FOREIGN KEY (`company_id`) REFERENCES `COMPANY` (`id`);

--
-- Constraints for table `PRODUCT`
--
ALTER TABLE `PRODUCT`
  ADD CONSTRAINT `FK_PRODUCT__CATEGORY_ID___PRODUCT_CATEGORY__ID` FOREIGN KEY (`category_id`) REFERENCES `PRODUCT_CATEGORY` (`id`),
  ADD CONSTRAINT `FK_PRODUCT__COMPANY_ID___COMPANY__ID` FOREIGN KEY (`company_id`) REFERENCES `COMPANY` (`id`),
  ADD CONSTRAINT `FK_PRODUCT__VAT_CATEGORY_ID___VAT_CATEGORY__ID` FOREIGN KEY (`vat_category_id`) REFERENCES `VAT_CATEGORY` (`id`);

--
-- Constraints for table `PRODUCT_CATEGORY`
--
ALTER TABLE `PRODUCT_CATEGORY`
  ADD CONSTRAINT `FK_PRODUCT_CATEGORY__COMPANY_ID___COMPANY__ID` FOREIGN KEY (`company_id`) REFERENCES `COMPANY` (`id`),
  ADD CONSTRAINT `FK_PRODUCT_CATEGORY__MD_IC_ID___MYDATA_INCOME_CATEGORY__ID` FOREIGN KEY (`mydata_income_category_id`) REFERENCES `MYDATA_INCOME_CATEGORY` (`id`),
  ADD CONSTRAINT `FK_PRODUCT_CATEGORY__MD_RT_IT_ID__MYDATA_INCOME_TYPE__ID` FOREIGN KEY (`mydata_retail_income_type`) REFERENCES `MYDATA_INCOME_TYPE` (`id`),
  ADD CONSTRAINT `FK_PRODUCT_CATEGORY__MD_WS_IT_ID__MYDATA_INCOME_TYPE__ID` FOREIGN KEY (`mydata_wholesale_income_type`) REFERENCES `MYDATA_INCOME_TYPE` (`id`);

--
-- Constraints for table `PRODUCT_SPEC`
--
ALTER TABLE `PRODUCT_SPEC`
  ADD CONSTRAINT `FK_PRODUCT_SPEC__PRODUCT_ID___PRODUCT__ID` FOREIGN KEY (`product_id`) REFERENCES `PRODUCT` (`id`),
  ADD CONSTRAINT `FK_PRODUCT_SPEC__SPEC_ID___SPEC__ID` FOREIGN KEY (`spec_id`) REFERENCES `SPEC` (`id`);

--
-- Constraints for table `PRODUCT_UNIT`
--
ALTER TABLE `PRODUCT_UNIT`
  ADD CONSTRAINT `FK_PRODUCT_UNIT__PRODUCT_ID___PRODUCT__ID` FOREIGN KEY (`product_id`) REFERENCES `PRODUCT` (`id`),
  ADD CONSTRAINT `FK_PRODUCT_UNIT__UNIT_ID___UNIT__ID` FOREIGN KEY (`unit_id`) REFERENCES `UNIT` (`id`);

--
-- Constraints for table `ROOM`
--
ALTER TABLE `ROOM`
  ADD CONSTRAINT `FK_ROOM__COMPANY_ID___COMPANY__ID` FOREIGN KEY (`company_id`) REFERENCES `COMPANY` (`id`);

--
-- Constraints for table `SALE_DOCUMENT`
--
ALTER TABLE `SALE_DOCUMENT`
  ADD CONSTRAINT `FK_SALE_DOCUMENT__COMPANY_ID___COMPANY__ID` FOREIGN KEY (`company_id`) REFERENCES `COMPANY` (`id`),
  ADD CONSTRAINT `FK_SALE_DOCUMENT__CUSTOMER_ID___COMPANY_CUSTOMER__ID` FOREIGN KEY (`customer_id`) REFERENCES `COMPANY_CUSTOMER` (`id`),
  ADD CONSTRAINT `FK_SALE_DOCUMENT__SERIES_ID___SALE_SERIES__ID` FOREIGN KEY (`series_id`) REFERENCES `SALE_SERIES` (`id`),
  ADD CONSTRAINT `FK_SALE_DOC__MD_DOC_TYPE_ID__MYDATA_DOC_TYPES__ID` FOREIGN KEY (`mydata_doc_type`) REFERENCES `MYDATA_DOC_TYPES` (`id`);

--
-- Constraints for table `SALE_DOCUMENT_PRODUCT`
--
ALTER TABLE `SALE_DOCUMENT_PRODUCT`
  ADD CONSTRAINT `FK_SALE_DOC_PRD__MD_INCOME_CATEG_ID__MYDATA_INCOME_CATEGORY__ID` FOREIGN KEY (`mydata_income_category`) REFERENCES `MYDATA_INCOME_CATEGORY` (`id`),
  ADD CONSTRAINT `FK_SALE_DOC_PRD__MD_INCOME_TYPE_ID__MYDATA_INCOME_TYPE__ID` FOREIGN KEY (`mydata_income_type`) REFERENCES `MYDATA_INCOME_TYPE` (`id`),
  ADD CONSTRAINT `FK_SDP_UNIT_ID___UNIT__ID` FOREIGN KEY (`unit_id`) REFERENCES `UNIT` (`id`),
  ADD CONSTRAINT `FK_SDP__PRODUCT_ID___PRODUCT__ID` FOREIGN KEY (`product_id`) REFERENCES `PRODUCT` (`id`),
  ADD CONSTRAINT `FK_SDP__SALE_DOCUMENT_ID___SD__ID` FOREIGN KEY (`sale_document_id`) REFERENCES `SALE_DOCUMENT` (`id`);

--
-- Constraints for table `SALE_SERIES`
--
ALTER TABLE `SALE_SERIES`
  ADD CONSTRAINT `FK_PRODUCT_CATEGORY__MD_DT_ID__MYDATA_DOC_TYPES__ID` FOREIGN KEY (`mydata_doc_type_id`) REFERENCES `MYDATA_DOC_TYPES` (`id`),
  ADD CONSTRAINT `FK_SALE_SERIES__COMPANY_ID___COMPANY__ID` FOREIGN KEY (`company_id`) REFERENCES `COMPANY` (`id`),
  ADD CONSTRAINT `FK_SS_MYDATA_DOC_TYPE_ID___MDT__ID` FOREIGN KEY (`mydata_doc_type_id`) REFERENCES `MYDATA_DOC_TYPES` (`id`);

--
-- Constraints for table `SPEC`
--
ALTER TABLE `SPEC`
  ADD CONSTRAINT `FK_SPEC__COMPANY_ID___COMPANY__ID` FOREIGN KEY (`company_id`) REFERENCES `COMPANY` (`id`);

--
-- Constraints for table `TABLES`
--
ALTER TABLE `TABLES`
  ADD CONSTRAINT `FK_TABLES__COMPANY_ID___COMPANY__ID` FOREIGN KEY (`company_id`) REFERENCES `COMPANY` (`id`),
  ADD CONSTRAINT `FK_TABLES__ROOM_ID___ROOM__ID` FOREIGN KEY (`room_id`) REFERENCES `ROOM` (`id`);

--
-- Constraints for table `UNIT`
--
ALTER TABLE `UNIT`
  ADD CONSTRAINT `FK_UNIT__COMPANY_ID___COMPANY__ID` FOREIGN KEY (`company_id`) REFERENCES `COMPANY` (`id`);

--
-- Constraints for table `USER`
--
ALTER TABLE `USER`
  ADD CONSTRAINT `FK_USER__COMPANY_ID___COMPANY__ID` FOREIGN KEY (`company_id`) REFERENCES `COMPANY` (`id`);

--
-- Constraints for table `WAITER_TABLES`
--
ALTER TABLE `WAITER_TABLES`
  ADD CONSTRAINT `FK_WAITER_TABLES__TABLE_ID___TABLES__ID` FOREIGN KEY (`table_id`) REFERENCES `TABLES` (`id`),
  ADD CONSTRAINT `FK_WAITER_TABLES__USER_ID___USER__ID` FOREIGN KEY (`user_id`) REFERENCES `USER` (`id`);

--
-- Constraints for table `WORKING_HOURS`
--
ALTER TABLE `WORKING_HOURS`
  ADD CONSTRAINT `FK_WORKING_HOURS__COMPANY_ID___COMPANY__ID` FOREIGN KEY (`company_id`) REFERENCES `COMPANY` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
