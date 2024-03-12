<?php
/**
* 2007-2020 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2020 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

//funciones comunes de manera estática

class HerramientasVentaSinStock
{
    public static function getEan($id_product, $id_product_attribute) {
        $sql_ean = "SELECT IFNULL(pat.ean13, pro.ean13) AS ean 
        FROM lafrips_product pro
        JOIN lafrips_stock_available ava ON ava.id_product = pro.id_product
        LEFT JOIN lafrips_product_attribute pat ON pat.id_product = ava.id_product AND pat.id_product_attribute = ava.id_product_attribute
        WHERE ava.id_product = $id_product
        AND ava.id_product_attribute = $id_product_attribute";

        return Db::getInstance()->getValue($sql_ean);
    }

    //función que devuelve la referencia de producto, teniendo en cuenta si es atributo
    public static function getProductReference($id_product, $id_product_attribute) {
        return Db::getInstance()->getValue("SELECT IFNULL(pat.reference, pro.reference) AS product_reference
        FROM lafrips_product pro
        JOIN lafrips_stock_available ava ON ava.id_product = pro.id_product
        LEFT JOIN lafrips_product_attribute pat ON pat.id_product = ava.id_product AND pat.id_product_attribute = ava.id_product_attribute
        WHERE pro.id_product = $id_product
        AND ava.id_product_attribute = $id_product_attribute");
    }

    //función que comprueba si el pedido tiene productos vendidos sin stock sin revisar y dependiendo del caso cambia el estado de pedido a Completando Pedido, añadiendo mensaje al pedido
    //POR AHORA NO LA USAMOS DESDE ENTRADA EN VERIFICANDO YA QUE CAMBIA EL ESTADO ANTES DE TENER ASIGNADO VERIFICANDO, la dejo aquí. Lo haremos con proceso horario, pero quizás la llame desde el controlador de productosvendidossinstock cuando se pulsa Revisar producto.
    public static function checkCambioEsperandoProductos($id_order, $id_customer_thread) {
        //comprobamos si después de revisar los productos aún quedan más productos vendidos sin stock en ese pedido
        $sql_otros_productos_pedido = "SELECT id_product
        FROM lafrips_productos_vendidos_sin_stock 
        WHERE checked = 0
        AND id_order = $id_order";

        //si no encuentra más productos en el pedido, comprobamos su estado actual, y si es verificando stock/sin stock pagado lo pasamos a Completando Pedido, si es cualquier otro estado, no hacemos nada
        if (!Db::getInstance()->ExecuteS($sql_otros_productos_pedido)) { 
            $order = new Order($id_order);   
            
            //si el estado actual es verificando stock/sin stock pagado lo cambiamos
            if ($order->current_state == Configuration::get(PS_OS_OUTOFSTOCK_PAID)){
                //cambiamos estado y metemos mensaje a pedido, actualizamos el estado en lafrips_productos_vendidos_sin_stock
                //sacamos id_status de Esperando productos
                // $sql_id_esperando_productos = "SELECT ost.id_order_state
                // FROM lafrips_order_state ost
                // JOIN lafrips_order_state_lang osl ON osl.id_order_state = ost.id_order_state AND osl.id_lang = 1
                // WHERE osl.name = 'Esperando productos'
                // AND ost.deleted = 0";
                // $id_esperando_productos = Db::getInstance()->getValue($sql_id_esperando_productos);  
                
                //30/06/2023
                $id_esperando_productos = Configuration::get(PS_ESPERANDO_PRODUCTOS);

                //se genera un objeto $history para crear los movimientos, asignandole el id del pedido sobre el que trabajamos            
                //cambiamos estado de orden a Completando Pedido, ponemos id_employee 44 que es Automatizador, para log
                $history = new OrderHistory();
                $history->id_order = $id_order;
                $history->id_employee = 44;
                //comprobamos si ya tiene el invoice, payment etc, porque puede duplicar el método de pago. hasInvoice() devuelve true o false, y se pone como tercer argumento de changeIdOrderState(). Primero tenemos que instanciar el pedido
                // $order = new Order($producto['id_order']);
				$use_existing_payment = !$order->hasInvoice();
                $history->changeIdOrderState($id_esperando_productos, $id_order, $use_existing_payment); 
                $history->add(true);
                if (!$history->save()) {
                    return false;
                }

                //cambiamos estado en lafrips_productos_vendidos_sin_stock
                $sql_update_productos_vendidos_sin_stock = 'UPDATE lafrips_productos_vendidos_sin_stock
                            SET                                                      
                            id_order_status = '.$id_esperando_productos.'
                            WHERE id_order = '.$id_order;

                Db::getInstance()->execute($sql_update_productos_vendidos_sin_stock);

                //ya se ha cambiado el estado, guardamos en frik_pedidos_cambiados el cambio de estado 
				$order_carrier = new OrderCarrier($order->getIdOrderCarrier());
				$id_carrier_orders = (int)$order_carrier->id_carrier; 

                // $supplier_name = Supplier::getNameById($id_supplier);

				$insert_frik_pedidos_cambiados = "INSERT INTO frik_pedidos_cambiados 
				(id_order, estado_inicial, estado_final, transporte_inicial, transporte_final, proceso, date_add) 
				VALUES ($id_order ,
				".$order->current_state." ,
				$id_esperando_productos ,
				$id_carrier_orders ,
                $id_carrier_orders ,
                'A Completando Pedido - Pedido Venta Sin Stock - Automático',
				NOW())";

				Db::getInstance()->Execute($insert_frik_pedidos_cambiados);

                //metemos mensaje privado al pedido
                //comprobamos si vino valor en $id_customer_thread, si es false no metemos mensaje al pedido, si no añadimos mensaje del cambio de estado
                if ($id_customer_thread) {
                    $fecha = date("d-m-Y H:i:s");
                    $mensaje_pedido_sin_stock_estado = 'Pedido Venta Sin Stock cambiado a Completando Pedido                     
                    revisado automáticamente al entrar a Verificando Stock el '.$fecha;

                    $cm_interno_cambio_estado = new CustomerMessage();
                    $cm_interno_cambio_estado->id_customer_thread = $id_customer_thread;
                    $cm_interno_cambio_estado->id_employee = 44; 
                    $cm_interno_cambio_estado->message = $mensaje_pedido_sin_stock_estado;
                    $cm_interno_cambio_estado->private = 1;                    
                    $cm_interno_cambio_estado->add();
                }
            }

        }

        return true;
    }

    //función que añade mensaje interno a pedido para los productos sin stock vendidos (por ahora Karactermanía)
    //devuelve false si no se puede generar mensajes, o id de customer thread si es correcto, para posible cambio de estado
    public static function setMensajePedido($id_order, $info_productos) {
        //por cada producto del pedido generamos un mensaje interno. Primero obtenemos si existe, o generamos, un customer thread, para lo que necesitamos el id_order y el customer email:
        $error = 0;
        $order = new Order($id_order);        
        $customer = new Customer($order->id_customer);
        //comprobamos si hay customer thread para el pedido, si existe sacamos su id
        $id_customer_thread = CustomerThread::getIdCustomerThreadByEmailAndIdOrder($customer->email, $id_order);            

        if ($id_customer_thread) {
            //si ya existiera lo instanciamos para tener los datos para el mensaje y el envío de email
            $ct = new CustomerThread($id_customer_thread);
        } else {
            //si no existe lo creamos
            $ct = new CustomerThread();
            $ct->id_shop = 1; // (int)$this->context->shop->id;
            $ct->id_lang = 1; // (int)$this->context->language->id;
            $ct->id_contact = 0; 
            $ct->id_customer = $order->id_customer;
            $ct->id_order = $id_order;
            //$ct->id_product = 0;
            $ct->status = 'open';
            $ct->email = $customer->email;
            $ct->token = Tools::passwdGen(12);  // hay que generar un token para el hilo
            $ct->add();
        }  

        //si hay id de customer_thread continuamos
        if ($ct->id){
            //por cada producto metemos un mensaje
            $fecha = date("d-m-Y H:i:s");
            foreach ($info_productos AS $producto) {
                //un mensaje interno para que aparezca la fecha de revisado del producto sin stock y el empleado (automatizador aquí) en el back de pedidos
                $supplier_name = Supplier::getNameById($producto['id_supplier']);

                $mensaje_producto_pedido_sin_stock = 'Producto '.$supplier_name.' vendido sin stock: 
                Nombre: '.$producto['product_name'].'
                Ref. Prestashop: '.trim($producto['referencia_prestashop']).' 
                Ref. Proveedor: '.trim($producto['referencia_proveedor']).'
                revisado automáticamente al entrar a Verificando Stock el '.$fecha;

                $cm_interno = new CustomerMessage();
                $cm_interno->id_customer_thread = $ct->id;
                $cm_interno->id_employee = 44; 
                $cm_interno->message = $mensaje_producto_pedido_sin_stock;
                $cm_interno->private = 1;                
                $cm_interno->add();
            }            
        } else {
            return false;
        }     

        return $ct->id;
    }

    //24/01/2024 Función que recibe el id de proveedor y averigua el delay en días que tarda el paquete en llegar desde el proveedor, buscando en la tabla lafrips_mensaje_disponibilidad. Se calcula la fecha supuesta de llegada y en función de la fecha de hoy hay que asegurarse de que entre la fecha de hoy y la de llegada hay dias laborables igual a los dias del delay, sin fin de semana por en medio. Devuelve expected_delivery_date para el pedido de materiales.
    public static function setSupplyOrderDeliveryDate($id_supplier) {        
        //date('N') devuelve el día de la semana de hoy, de 1 a 7 siendo 1 lunes.
        //para sacar el dia de la semana de una fecha dada:
        // $dateString = '2023-12-02';
        // $timestamp = strtotime($dateString);
        // $dayOfWeekNumber = date('N', $timestamp);
    
        //primero obtenemos el delay para el proveedor. Si no está en la tabla o pone 0 se aplica 5 días por defecto.
        $sql_supply_order_delay = "SELECT supply_order_delay FROM lafrips_mensaje_disponibilidad 
            WHERE id_lang = 1 AND id_supplier = $id_supplier";
        if (!$supply_order_delay = Db::getInstance()->getValue($sql_supply_order_delay)) {
            $supply_order_delay = 5;
        }
    
        //ahora, a partir de la fecha de hoy, momento de ejecución de este proceso, hay que sumar los días del delay, comprobando el día de la semana que es cada uno, e ignorando los que caigan en fin de semana (6 y 7)
        $hoy = date('Y-m-d');
        //si el usuario o el proceso que ejecute esta función lo hace durante el fin de semana, el dia de ejecución no debe contar, por tanto, si $hoy es sábado o domingo, sumamos uno a $supply_order_delay para que tenga en cuenta el día de ejecución como no válido. Si no, si ejecutas esto un sábado, con un proveedor de un día por ejemplo, se pondría como fecha de llegada el lunes, caundo debe ser el martes.
        if (date('N') > 5) {
            //hoy es fin de semana
            $supply_order_delay++;
        }

        $timestamp = strtotime($hoy);
        while ($supply_order_delay > 0) {
            //sumamos un día a $hoy
            $timestamp = strtotime('+1 day', $timestamp);
    
            //comprobamos si el día resultante es sábado o domingo, si lo es, no restamos a $supply_order_delay           
            if (date('N', $timestamp) < 6) {
                //es de 1 a 5, luego laborable, restamos
                $supply_order_delay--;
            }
        }
    
        //en $timestamp tenemos el día resultante de haber sumado a $hoy x días, evitando fin de semana. Lo devolvemos a fecha utilizable para crear pedido de materiales
        return  date('Y-m-d', $timestamp).' 00:00:00';
    }
    
}
