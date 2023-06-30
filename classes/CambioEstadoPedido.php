<?php

require_once(dirname(__FILE__).'/../../../config/config.inc.php');
require_once(dirname(__FILE__).'/../../../init.php');

//https://lafrikileria.com/modules/productosvendidossinstock/classes/CambioEstadoPedido.php

//13/06/2023 proceso programado para cada hora que revisará los pedidos de productos vendidos sin stock, que hayándose en estado Verificando Stock tengan marcados como revisados todos sus productos vendidos sin stock, para cambiar el estado del pedido a Esperando Productos/Completando pedido

$a = new CambioEstadoPedido();

class CambioEstadoPedido
{
    public $id_verificando_stock;
    public $id_esperando_productos;
    public $fecha;
    public $pedidos;
    public $order;

    public function __construct() {
        $this->id_verificando_stock = Configuration::get(PS_OS_OUTOFSTOCK_PAID);
        $this->id_esperando_productos = Configuration::get(PS_ESPERANDO_PRODUCTOS);
        $this->fecha = date("d-m-Y H:i:s");
        
        if (!$this->pedidos = $this->getPedidosVerificandoStock()) {
            exit;
        }

        foreach ($this->pedidos AS $pedido) {
            $this->order = new Order($pedido['id_order']);
            $this->cambiaEstado();
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

            $this->insertPedidosCambiados();

            $this->generaMensajeInterno();
        }

        return;        
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
        $id_carrier_orders = (int)$order_carrier->id_carrier; 

        $insert_frik_pedidos_cambiados = "INSERT INTO frik_pedidos_cambiados 
        (id_order, estado_inicial, estado_final, transporte_inicial, transporte_final, proceso, date_add) 
        VALUES (".$this->order->id." ,
        ".$this->id_verificando_stock." ,
        ".$this->id_esperando_productos." ,
        ".$id_carrier_orders." ,
        ".$id_carrier_orders." ,
        'A Esperando Productos - Productos revisados - Automático',
        NOW())";

        Db::getInstance()->Execute($insert_frik_pedidos_cambiados);

        return;
    }

    public function generaMensajeInterno() {
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
            //generamos mensaje para pedido sin stock pagado para producto revisado            
            $mensaje_pedido_sin_stock_estado = 'Pedido con Productos Vendidos Sin Stock cambiado a Completando Pedido                     
            revisado automáticamente por el Santo Proceso el '.$this->fecha;

            $cm_interno_cambio_estado = new CustomerMessage();
            $cm_interno_cambio_estado->id_customer_thread = $ct->id;
            $cm_interno_cambio_estado->id_employee = 44; 
            $cm_interno_cambio_estado->message = $mensaje_pedido_sin_stock_estado;
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