<!-- SQL para crear tablas del módulo. -->

CREATE TABLE `lafrips_productos_vendidos_sin_stock` (
   `id_productos_vendidos_sin_stock` int(10) NOT NULL AUTO_INCREMENT,
   `id_order` int(10) NOT NULL,
   `id_order_status` int(10) NOT NULL,
   `payment_module` varchar(50) NOT NULL,
   `id_product` int(10) NOT NULL,
   `id_product_attribute` int(10) NOT NULL,
   `available_date` date NOT NULL,
   `fecha_disponible` date NOT NULL,
   `available_later` varchar(255) NOT NULL,
   `disponibilidad_avisada` date NOT NULL,
   `fecha_ultimo_aviso` date NOT NULL,
   `fecha_ultimo_aviso_automatico` date NOT NULL,
   `email_id` int(10) NOT NULL,
   `prepedido` tinyint(1) NOT NULL,
   `out_of_stock` tinyint(1) DEFAULT NULL,
   `checked` tinyint(1) NOT NULL,
   `date_checked` datetime NOT NULL,
   `id_employee` int(10) NOT NULL,
   `product_name` varchar(255) NOT NULL,
   `stock_available` int(10) NOT NULL,
   `product_reference` varchar(32) NOT NULL,
   `product_supplier_reference` varchar(32) NOT NULL,
   `id_default_supplier` int(10) NOT NULL,
   `id_order_detail_supplier` int(10) NOT NULL,
   `product_quantity` int(10) NOT NULL,
   `quantity_mod` tinyint(4) NOT NULL,
   `product_quantity_old` int(10) NOT NULL,
   `id_employee_mod` int(10) NOT NULL,
   `date_mod` datetime NOT NULL,
   `anadido` tinyint(1) NOT NULL,
   `id_employee_anadido` int(10) NOT NULL,
   `date_anadido` datetime NOT NULL,
   `eliminado` tinyint(4) NOT NULL,
   `id_employee_eliminado` int(10) NOT NULL,
   `date_eliminado` datetime NOT NULL,
   `date_add` datetime DEFAULT NULL,
   `date_upd` datetime NOT NULL,
   PRIMARY KEY (`id_productos_vendidos_sin_stock`)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

 CREATE TABLE `frik_log_productos_vendidos_sin_stock` (
   `id_log_productos_vendidos_sin_stock` int(10) NOT NULL AUTO_INCREMENT,
   `id_proceso` tinyint(1) NOT NULL,
   `proceso` varchar(64) NOT NULL,
   `pedidos` varchar(1000) DEFAULT NULL,
   `id_order` int(10) DEFAULT NULL,
   `id_product` int(10) NOT NULL,
   `id_product_attribute` int(10) DEFAULT NULL,
   `available_date` date DEFAULT NULL,
   `available_later` varchar(255) DEFAULT NULL,
   `prepedido` tinyint(1) DEFAULT NULL,
   `permitir_pedido` tinyint(1) DEFAULT NULL,
   `disponibilidad_avisada` date DEFAULT NULL,
   `email` varchar(128) DEFAULT NULL,
   `email_text` varchar(2000) DEFAULT NULL,
   `fecha_email` date DEFAULT NULL,
   `product_name` varchar(255) NOT NULL,
   `product_reference` varchar(32) NOT NULL,
   `id_employee` int(10) NOT NULL,
   `empleado` varchar(64) NOT NULL,
   `date_add` datetime NOT NULL,
   PRIMARY KEY (`id_log_productos_vendidos_sin_stock`)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8

 CREATE TABLE IF NOT EXISTS `lafrips_dropshipping` (
  `id_dropshipping` int(10) NOT NULL AUTO_INCREMENT,
  `id_supplier` int(10) NOT NULL,
  `supplier_name` varchar(64) NOT NULL,
  `id_order` int(10) NOT NULL,
  `id_customer` int(10) NOT NULL,  
  `id_address_delivery` int(10) NOT NULL,
  `id_dropshipping_address` int(10) NOT NULL, <!-- id de la tabla dropshipping_address donde está almacenada la dirección de entrega --> 
  `envio_almacen` tinyint(1) NOT NULL, <!-- indica si este pedido y proveedor va a almacén o a cliente -->   
  `new_id_order` varchar(64) NOT NULL, <!-- si separamos pedido metemos id_order separados coma --> 
  `procesado` tinyint(1) NOT NULL,
  `finalizado` tinyint(1) NOT NULL,
  `cancelado` tinyint(1) NOT NULL,
  `error` tinyint(1) NOT NULL, <!-- se marcará por ejemplo si no hay dirección de entrega, etc para mostrar error en el controlador, pedido ha entrado pero no se ha solicitado --> 
  `error_api` tinyint(1) NOT NULL,
  `error_api_avisado` tinyint(1) NOT NULL,
  `date_error_api` datetime NOT NULL,
  `id_employee_cancelado` int(10) NOT NULL,
  `date_cancelado` datetime NOT NULL, 
  `reactivado` tinyint(1) NOT NULL,
  `id_employee_reactivado` int(10) NOT NULL,
  `date_reactivado` datetime NOT NULL, 
  `id_employee_manual` int(10) NOT NULL, <!-- cuando se llame a ejecutar petición API desde back office --> 
  `date_manual` datetime NOT NULL, 
  `date_add` datetime NOT NULL, 
  `date_upd` datetime NOT NULL, 
  PRIMARY KEY (`id_dropshipping`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `lafrips_dropshipping_address` (
  `id_dropshipping_address` int(10) NOT NULL AUTO_INCREMENT,  
  `id_order` int(10) NOT NULL,
  `id_customer` int(10) NOT NULL,
  `email` varchar(128) NOT NULL, <!-- lo guardamos, pero posiblemente no lo enviemos a los proveedores --> 
  `id_address_delivery` int(10) NOT NULL,
  `firstname` varchar(32) NOT NULL,
  `lastname` varchar(32) NOT NULL,  
  `company` varchar(64) NOT NULL,
  `address1` varchar(256) NOT NULL, <!-- concat de address1 y address2 --> 
  `postcode` varchar(12) NOT NULL,
  `city` varchar(64) NOT NULL,
  `provincia` varchar(64) NOT NULL,
  `country` varchar(64) NOT NULL,
  `phone` varchar(64) NOT NULL,
  `other` varchar(64) NOT NULL,
  `dni` varchar(16) NOT NULL,  
  `envio_almacen` tinyint(1) NOT NULL, <!-- se marcará 1 si se debe enviar a almacén --> 
  `error` tinyint(1) NOT NULL, <!-- se marcará por ejemplo si no hay dirección de entrega --> 
  `deleted` tinyint(1) NOT NULL, <!-- se marcará si se procesa otra vez el pedido y la id_address_delivery de order no coincide --> 
  `date_deleted` datetime NOT NULL, 
  `date_add` datetime NOT NULL, 
  `date_upd` datetime NOT NULL, 
  PRIMARY KEY (`id_dropshipping_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `lafrips_dropshipping_log` (
  `id_dropshipping_log` int(10) NOT NULL AUTO_INCREMENT,
  `id_dropshipping` int(10),  
  `id_dropshipping_address` int(10), 
  `id_dropshipping_supplier` int(10), 
  `id_supplier` int(10), 
  `id_order` int(10),
  `error` text NOT NULL,
  `date_add` datetime NOT NULL,  
  PRIMARY KEY (`id_dropshipping_log`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

<!-- Para proveedor Disfrazzes -->

CREATE TABLE IF NOT EXISTS `lafrips_dropshipping_disfrazzes` (
  `id_dropshipping_disfrazzes` int(10) NOT NULL AUTO_INCREMENT, 
  `id_dropshipping` int(10) NOT NULL, <!-- id al pedido que corresponde en lafrips_dropshipping    -->
  `id_order` int(10) NOT NULL,
  `api_call_parameters` text, <!-- el json de parámetros de la llamada a API a register_order -->
  `api_call_response` text, <!-- el json de respuesta de la llamada a API a register_order -->
  `response_result` int(10) NOT NULL, <!-- result de la api --> 
  `response_delivery_date` date NOT NULL, <!-- result de la api -->
  `response_msg` varchar(255) NOT NULL, <!--  mensaje devuelto en register_order -->
  `disfrazzes_id` int(10) NOT NULL, <!--  id de pedido para proveedor devuelto -->
  `disfrazzes_reference` varchar(64) NOT NULL, <!-- referencia de pedido para proveedor -->
  `status_id` int(2) NOT NULL,
  `status_name` varchar(64) NOT NULL, 
  `transportista` varchar(32) NOT NULL,
  `date_expedicion` date NOT NULL, 
  `tracking` varchar(32) NOT NULL, <!-- tracking y url de seguimiento devuelta por disfrazzes -->
  `url_tracking` varchar(255) NOT NULL,
  `cancelado` tinyint(1) NOT NULL,
  `id_employee_cancelado` int(10) NOT NULL,
  `date_cancelado` datetime NOT NULL, 
  `error` tinyint(1) NOT NULL,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_dropshipping_disfrazzes`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `lafrips_dropshipping_disfrazzes_productos` (
  `id_dropshipping_disfrazzes_productos` int(10) NOT NULL AUTO_INCREMENT, 
  `id_dropshipping_disfrazzes` int(10) NOT NULL, <!-- id al pedido que corresponde en lafrips_dropshipping_disfrazzes -->
  `id_order` int(10) NOT NULL,
  `id_order_detail` int(10) NOT NULL,   
  `id_product` int(10) NOT NULL,
  `id_product_attribute` int(10) NOT NULL,
  `product_supplier_reference` varchar(32) NOT NULL,
  `product_quantity` int(10) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_reference` varchar(32) NOT NULL,
  `product_id` int(10) NOT NULL, 
  `variant_id` int(10) NOT NULL,     
  `variant_result` int(10) NOT NULL, <!-- result de la api para variante -->
  `variant_msg` varchar(255) NOT NULL, <!--  mensaje devuelto en register_order -->
  `variant_quantity_accepted` int(10) NOT NULL, <!-- result de la api para variante -->
  `variant_row_id` int(10) NOT NULL, <!-- result de la api para variante   -->
  `eliminado` tinyint(1) NOT NULL,  
  `date_eliminado` datetime NOT NULL, 
  `date_product_quantity_mod` datetime NOT NULL, 
  `error` tinyint(1) NOT NULL,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_dropshipping_disfrazzes_productos`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;