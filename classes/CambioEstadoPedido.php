<?php

require_once(dirname(__FILE__).'/../../../config/config.inc.php');
require_once(dirname(__FILE__).'/../../../init.php');

//https://lafrikileria.com/modules/productosvendidossinstock/classes/CambioEstadoPedido.php

//13/06/2023 proceso programado para cada hora que revisará los pedidos de productos vendidos sin stock, que hayándose en estado Verificando Stock tengan marcados como revisados todos sus productos vendidos sin stock, para cambiar el estado del pedido a Esperando Productos/Completando pedido

//02/08/2023 Vamos a añadir a este proceso un repaso de los productos del pedido sin stock. Si estos pertenecen a un proveedor considerado rápido en enviar el producto al almacén y no hay otros productos a la espera de proveedores no rápidos en el pedido, y además el cliente escogió GLS para la entrega (no pudiendo escoger GLS 24 ya que para productos sin stock no estaría disponible) cambiariamos también el transporte a GLS 24. Generaremos también un mensaje en el pedido explicándolo.
//no cambiaremos si algún producto es prepedido (categoría) o es dropshipping con entrega en almacén. Tampoco si entrara de amazon, que puede llevar GLS domicilio
//Esto no funcionará si alguien cambia el estado del pedido manualmente y por tanto no pasa por aquí.

$a = new CambioEstadoPedido();

class CambioEstadoPedido
{
    public $id_verificando_stock;
    public $id_esperando_productos;
    public $fecha;
    public $pedidos;
    public $order;
    public $id_order_carrier_original; //carrier original
    public $id_carrier_gls_domicilio;
    public $id_carrier_gls_24;
    public $proveedores_rapidos = array();

    public function __construct() {
        $this->id_verificando_stock = Configuration::get(PS_OS_OUTOFSTOCK_PAID);
        $this->id_esperando_productos = Configuration::get(PS_ESPERANDO_PRODUCTOS);
        $this->fecha = date("d-m-Y H:i:s");
        
        if (!$this->pedidos = $this->getPedidosVerificandoStock()) {
            exit;
        }

        if (Configuration::get(CAMBIAR_TRANSPORTE_GLS_24)) {
            //obtenemos los id_carrier de GLS
            $this->getIdsCarrier();
            //obtenemos los id_supplier de proveedores "rápidos"
            $this->getSuppliers();
        }        

        foreach ($this->pedidos AS $pedido) {
            $this->order = new Order($pedido['id_order']);

            $order_carrier = new OrderCarrier($this->order->getIdOrderCarrier());
            $this->id_order_carrier_original = (int)$order_carrier->id_carrier; 

            if ($this->cambiaEstado()) {
                //si está activo lo de cambiar transporte a GLS 24 y el carrier del pedido es GLS Domicilio, estudiamos los proveedores de los productos
                if (Configuration::get(CAMBIAR_TRANSPORTE_GLS_24) && ($this->id_order_carrier_original == $this->id_carrier_gls_domicilio)) {
                    //comprobamos proveedores
                    if ($this->checkProductsSuppliers()) {
                        $this->changeCarrier();
                    }
                }  

                $this->insertPedidosCambiados();
            }               
        }

        exit;   

    }

    public function cambiaEstado() {
        //cambiamos estado de orden a Completando Pedido, ponemos id_employee 44 que es Automatizador, para log
        $history = new OrderHistory();
        $history->id_order = $this->order->id;
        $history->id_employee = 44;
        //comprobamos si ya tiene el invoice, payment etc, porque puede duplicar el método de pago. hasInvoice() devuelve true o false, y se pone como tercer argumento de changeIdOrderState(). Tenemos instanciado el pedido en $this->order        
        $use_existing_payment = !$this->order->hasInvoice();
        $history->changeIdOrderState($this->id_esperando_productos, $this->order->id, $use_existing_payment); 
        $history->add(true);
        if ($history->save()) {
            $this->cambiaEstadoProductosVendidosSinStock();  
            
            //generamos mensaje para pedido sin stock pagado para producto revisado            
            $mensaje_pedido_sin_stock_estado = 'Pedido con Productos Vendidos Sin Stock cambiado a Completando Pedido                     
            revisado automáticamente por el Santo Proceso el '.$this->fecha;

            $this->generaMensajeInterno($mensaje_pedido_sin_stock_estado);

            return true;
        }

        return false;        
    }

    public function cambiaEstadoProductosVendidosSinStock() {
        //cambiamos estado en lafrips_productos_vendidos_sin_stock
        $sql_update_productos_vendidos_sin_stock = "UPDATE lafrips_productos_vendidos_sin_stock
        SET                                                      
        id_order_status = ".$this->id_esperando_productos.",
        date_upd = NOW()
        WHERE id_order = ".$this->order->id;

        Db::getInstance()->execute($sql_update_productos_vendidos_sin_stock);

        return;
    }

    public function insertPedidosCambiados() {
        //ya se ha cambiado el estado, guardamos en frik_pedidos_cambiados el cambio de estado 
        $order_carrier = new OrderCarrier($this->order->getIdOrderCarrier());
        $id_carrier_orders_now = (int)$order_carrier->id_carrier; 

        $insert_frik_pedidos_cambiados = "INSERT INTO frik_pedidos_cambiados 
        (id_order, estado_inicial, estado_final, transporte_inicial, transporte_final, proceso, date_add) 
        VALUES (".$this->order->id." ,
        ".$this->id_verificando_stock." ,
        ".$this->id_esperando_productos." ,
        ".$this->id_order_carrier_original." ,
        ".$id_carrier_orders_now." ,
        'A Esperando Productos - Productos revisados - Automático',
        NOW())";

        Db::getInstance()->Execute($insert_frik_pedidos_cambiados);

        return;
    }

    public function generaMensajeInterno($mensaje) {
        //generamos un nuevo customer_thread si no lo hay  
        $customer = new Customer($this->order->id_customer);                
        //si existe ya un customer_thread para este pedido lo sacamos
        $id_customer_thread = CustomerThread::getIdCustomerThreadByEmailAndIdOrder($customer->email, $this->order->id);            

        if ($id_customer_thread) {
            //si ya existiera lo instanciamos para tener los datos para el mensaje
            $ct = new CustomerThread($id_customer_thread);
        } else {
            //si no existe lo creamos
            $ct = new CustomerThread();
            $ct->id_shop = 1; // (int)$this->context->shop->id;
            $ct->id_lang = 1; // (int)$this->context->language->id;
            $ct->id_contact = 0; 
            $ct->id_customer = $this->order->id_customer;
            $ct->id_order = $this->order->id;
            //$ct->id_product = 0;
            $ct->status = 'open';
            $ct->email = $customer->email;
            $ct->token = Tools::passwdGen(12);  // hay que generar un token para el hilo
            $ct->add();
        }     

        if ($ct->id){         
            $cm_interno_cambio_estado = new CustomerMessage();
            $cm_interno_cambio_estado->id_customer_thread = $ct->id;
            $cm_interno_cambio_estado->id_employee = 44; 
            $cm_interno_cambio_estado->message = $mensaje;
            $cm_interno_cambio_estado->private = 1;                    
            $cm_interno_cambio_estado->add();
        }

        return;
    }

    //desde 30/06/2023 cambia el nombre a Completando Pedido. Para evitar estas búsquedas meto en lafrips_configuration una variable  PS_ESPERANDO_PRODUCTOS con value 41 que es el id.
    //NO SE UTILIZA
    public function getIdEsperandoProductos() {
        $sql_id_esperando_productos = "SELECT ost.id_order_state
        FROM lafrips_order_state ost
        JOIN lafrips_order_state_lang osl ON osl.id_order_state = ost.id_order_state AND osl.id_lang = 1
        WHERE osl.name = 'Esperando productos'
        AND ost.deleted = 0";

        return Db::getInstance()->getValue($sql_id_esperando_productos);  
    }

    public function getIdsCarrier() {
        //obtenemos primero el id_reference de la tabla de configuración para ambos GLS y con el sacamos el id_carrier de la tabla carriers
        $id_reference_gls_24 = (int)Configuration::get('GLS_SERVICIO_SELECCIONADO_GLS24');

        $this->id_carrier_gls_24 = Db::getInstance()->getValue("SELECT id_carrier FROM lafrips_carrier WHERE active = 1 AND deleted = 0 AND id_reference = $id_reference_gls_24 ORDER BY id_carrier DESC");

        $id_reference_gls_domicilio = (int)Configuration::get('GLS_SERVICIO_SELECCIONADO_GLSECO');

        $this->id_carrier_gls_domicilio = Db::getInstance()->getValue("SELECT id_carrier FROM lafrips_carrier WHERE active = 1 AND deleted = 0 AND id_reference = $id_reference_gls_domicilio ORDER BY id_carrier DESC");

        return;
    }

    //función que obtiene los proveedores considerados como rápidos para productos sin stock
    public function getSuppliers() {
        //a 02/08/2023 y hasta que me confirmen, saco los id_supplier con rapido = 1 de la tabla lafrips_mensaje_disponibilidad, que son los que cuando entra un pedido sin stock y comunicamos a Connectif se envían como si fueran pedidos con stock dado que son "rápidos". Si no les vale creo aquí el array y ya.
        $sql_suppliers = "SELECT DISTINCT(id_supplier) FROM lafrips_mensaje_disponibilidad WHERE rapido = 1";

        $proveedores_rapidos = Db::getInstance()->ExecuteS($sql_suppliers);

        foreach ($proveedores_rapidos AS $proveedor) {
            $this->proveedores_rapidos[] =  $proveedor['id_supplier'];
        }

        return;
    }

    //función que , sabiendo que aquí están todos los productos vendidos sin stock del pedido dados por revisado, obtiene los ids de proveedor de cada uno de ellos, y comprueba si todos corresponden a proveedores "rápidos" sin stock, devolviendo true, que significa que se puede cambiar de transportista, o false si no. También cuenta con si algún producto entró con categoría prepedido, en cuyo caso el pedido ya no pasará a GLS 24.
    //comprobamos si el pedido es amazon, si lo es no cambiamos
    //$this->proveedores_rapidos es un array que contiene los id_supplier de proveedores rápidos
    public function checkProductsSuppliers() {
        //no tenemos en cuenta los que sean dropshipping salvo que vayan a almacén
        $sql_productos_pedido = "SELECT pvs.id_order_detail_supplier, pvs.prepedido, pvs.dropshipping, ord.module,
        IFNULL((SELECT envio_almacen FROM lafrips_dropshipping_address WHERE deleted = 0 AND id_order = ".$this->order->id."), 0) AS dropshipping_envio_almacen 
        FROM lafrips_productos_vendidos_sin_stock pvs
        JOIN lafrips_orders ord ON ord.id_order = pvs.id_order
        WHERE pvs.eliminado = 0        
        AND pvs.id_order = ".$this->order->id;

        if (!$productos_pedido = Db::getInstance()->ExecuteS($sql_productos_pedido)) {
            return false;
        } else {
            //si algún producto no cumple lo necesario devolvemos false            
            foreach ($productos_pedido AS $producto) {
                //si el proveedor no es "rápido" o el producto es prepedido o es dropshipping con entrega en almacén, o el pedido es de Amazon, devolvemos false para no cambiar transporte
                if (!in_array($producto['id_order_detail_supplier'], $this->proveedores_rapidos) 
                    || $producto['prepedido']
                    || ($producto['dropshipping'] && $producto['dropshipping_envio_almacen'])
                    || ($producto['module'] == 'amazon')) {
                        return false;
                    }
            }

            return true;
        }
    }

    //función que cambia el carrier de GLS Domicilio a 24 y mete un mensaje al pedido
    public function changeCarrier() {
        $order_carrier = new OrderCarrier($this->order->getIdOrderCarrier());

        //si ahora cambiamos el carrier de order directamente y hacemos update, el cambio de estado se pierde para la tabla lafrips_orders, de modo que volvemos a instanciarlo y cambiamos carrier
        unset($this->order);

        $this->order = new Order($order_carrier->id_order);

        $this->order->id_carrier = $this->id_carrier_gls_24;
        $order_carrier->id_carrier = $this->id_carrier_gls_24;
        $this->order->update();  
        $order_carrier->update();  

        //generamos mensaje para cambio de transportista           
        $mensaje_pedido_cambio_carrier = 'Cambiado transportista automáticamente de GLS Domicilio ('.$this->id_carrier_gls_domicilio.') a GLS 24 ('.$this->id_carrier_gls_24.')
        por considerarse todos los proveedores sin stock del pedido como "rápidos".
        Modificado por el Santo Proceso el '.$this->fecha;

        $this->generaMensajeInterno($mensaje_pedido_cambio_carrier);

        return;
    }

    public function getPedidosVerificandoStock() {
        //sacamos los pedidos de lafrips_productos_vendidos_sin_stock que se encuentren en estado Verificando Stock y cuyos productos estén todos revisados
        $sql_pedidos_verificando_stock = "SELECT pvs.id_order AS id_order 
        FROM lafrips_productos_vendidos_sin_stock pvs
        JOIN lafrips_orders ord ON ord.id_order = pvs.id_order
        WHERE ord.current_state = ".$this->id_verificando_stock."
        AND (SELECT COUNT(id_product)
                FROM lafrips_productos_vendidos_sin_stock 
                WHERE checked = 0
                AND eliminado = 0
                AND id_order = pvs.id_order) = 0
        GROUP BY pvs.id_order";

        return Db::getInstance()->ExecuteS($sql_pedidos_verificando_stock);  
    }
}

?>