<?php

/**
* Utilidad para gestionar los productos vendidos sin stock, configurados para permitir pedido 
*
* @author    Sergio™ <sergio@lafrikileria.com>
*/


if (!defined('_PS_VERSION_'))
    exit;

class AdminProductosVendidosSinStockController extends ModuleAdminController {

    public function __construct()
    {
        $this->bootstrap = true;
        $this->context = Context::getContext();
        $this->identifier = 'id_productos_vendidos_sin_stock';
        $this->table = 'productos_vendidos_sin_stock';
        //$this->list_id = 'inventory';
        //$this->className = 'PickPackOrder';
        $this->lang = false;

        $this->_select = '
        a.id_order AS id_order,
        a.id_product AS id_product,
        IF (a.checked, "Si", "No") AS checked,        
        a.id_default_supplier AS id_supplier,
        a.product_reference AS product_reference,
        a.product_supplier_reference AS product_supplier_reference,
        a.product_name AS product_name,
        a.product_quantity AS product_quantity,
        a.date_add AS date_add,
        osl.name AS estado_prestashop,
        sup.name AS supplier_name,
        ims.id_image AS id_image';
        // MOD: Sergio - 30/04/2021 Sacar clasificación ABC del producto. Hacemos LEFT JOIN con lafrips_consumos. Si el producto no está, es C, si su antiguedad es menor o igual de 60 días, es B, si está en la tabla y no es novedad, es lo que ponga en la tabla.  Para sacar como mínimo 1, a los valores de menos de 0.5 los ponemos como uno ya que ROUND los dejaría en 0
        // 20/05/2021 Los valores de consumo que indican si es A,B o C ahora se guardan en lafrips_configuration. Los añadimos como variable a la consulta.
        // 10/06/2021 Al cambiar la forma de gestionar los consumos ya no necesitamos calcular si es novedad, viene en el campo consumos novedad. La clasificación ABC también viene calculada en la tabla.
        // $novedad = Configuration::get('CLASIFICACIONABC_NOVEDAD', 0);
        // $maxC = Configuration::get('CLASIFICACIONABC_MAX_C');
        // $minA = Configuration::get('CLASIFICACIONABC_MAX_B');

        $this->_select .= ' , CASE                                
                                WHEN con.novedad = 1 THEN "N"
                                WHEN con.consumo IS NULL THEN 1
                            ELSE IF((con.consumo < 0.5), 1, ROUND(con.consumo, 0))
                            END AS consumo,                            

                            CASE                                
                                WHEN con.novedad = 1 THEN 0
                                WHEN con.consumo IS NULL THEN 0
                            ELSE IF(con.abc = "A", 1, 0) 
                            END AS badge_danger,
                            CASE                                
                                WHEN con.novedad = 1 THEN 1
                                WHEN con.consumo IS NULL THEN 0
                            ELSE IF(con.abc = "B", 1, 0) 
                            END AS badge_warning,
                            CASE                                
                                WHEN con.novedad = 1 THEN 0
                                WHEN con.consumo IS NULL THEN 1
                            ELSE IF(con.abc = "C", 1, 0)
                            END AS badge_success';
        
        // MOD: 26/07/2023 Añadimos el stock disponible a la vista
        $this->_select .= ' , ava.quantity AS stock_available';

        // MOD: Sergio - 12/11/2021 En el JOIN con lafrips_product pongo AND eliminado = 0 para evitar que muestre productos eliminados
        // LEFT JOIN lafrips_product pro ON pro.id_product = a.id_product        
        $this->_join = '        
        LEFT JOIN lafrips_orders ord ON a.id_order = ord.id_order 
        LEFT JOIN lafrips_order_state ors ON ors.id_order_state = ord.current_state
        LEFT JOIN lafrips_order_state_lang osl ON ors.id_order_state = osl.id_order_state AND osl.id_lang = 1
        JOIN lafrips_product pro ON pro.id_product = a.id_product AND a.eliminado = 0
        LEFT JOIN lafrips_supplier sup ON sup.id_supplier = pro.id_supplier
        LEFT JOIN lafrips_image_shop ims ON ims.id_product = a.id_product AND ims.id_shop = 1 AND ims.cover = 1';

        // MOD: Sergio - 30/04/2021 Sacar clasificación ABC del producto. Hacemos LEFT JOIN con lafrips_consumos. Si el producto no está, es C, si su antiguedad es menor de 61 días, es B, si está en la tabla y no es novedad, es lo que ponga en la tabla. 
        // 10/06/2021 ya no se calcula abc ni si es novedad, lo india todo la tabla     
        $this->_join .= ' LEFT JOIN lafrips_consumos con ON con.id_product = a.id_product AND con.id_product_attribute = a.id_product_attribute ';

        // MOD: 26/07/2023 Añadimos el stock disponible a la vista, haciendo join a stock_available
        $this->_join .= ' LEFT JOIN lafrips_stock_available ava ON ava.id_product = a.id_product AND ava.id_product_attribute = a.id_product_attribute ';

        $this->_orderBy = 'id_order';
        $this->_orderWay = 'DESC';
        $this->_use_found_rows = true;


        //sacamos los estados de pedido de prestashop
        $statuses = OrderState::getOrderStates((int)$this->context->language->id);
        foreach ($statuses as $status) {
            $this->statuses_array[$status['id_order_state']] = $status['name'];
        }

        //metemos los nombres de proveedor según id_supplier en un array
        $sql_suppliers = 'SELECT id_supplier, name FROM lafrips_supplier ORDER BY name ASC';
        $suppliers = Db::getInstance()->ExecuteS($sql_suppliers);
        foreach ($suppliers as $supplier) {
            $this->suppliers_array[$supplier['id_supplier']] = $supplier['name'];
        }

        $this->fields_list = array(
            'id_image' => array(
                'title' => $this->l('Imagen'),
                'align' => 'center',
                'image' => 'p',
                'orderby' => false,
                'filter' => false,
                'search' => false
            ),
            'id_order' => array(
                'title' => $this->l('Pedido'),
                'align' => 'text-center',
                'class' => 'fixed-width-xs',
                'type' => 'text',
                'filter_key' => 'a!id_order',  
                'filter_type' => 'int',              
            ), 
            'estado_prestashop' => array(
                'title' => $this->l('Estado Pedido'),
                'type' => 'select',
                'color' => 'color_presta',
                'class' => 'fixed-width-xs',
                'list' => $this->statuses_array,
                'filter_key' => 'ors!id_order_state',
                'filter_type' => 'int',
                'order_key' => 'osl!name'
            ),      
            'id_product' => array(
                'title' => $this->l('Producto'),
                'align' => 'text-center',
                'class' => 'fixed-width-xs',
                'type' => 'text',
                'filter_key' => 'a!id_product',
                'filter_type' => 'int',
            ), 
            'consumo' => array(
                'title' => $this->l('ABC'),
                'align' => 'text-center',
                'class' => 'fixed-width-xs',
                'type' => 'text',
                // 'filter_key' => 'a!id_product',
                // 'filter_type' => 'int',
                'havingFilter' => true,
                'orderby' => false,
                'search' => false,
                'badge_danger' => true,
                'badge_success' => true,
                'badge_warning' => true
            ), 
            'product_name' => array(
                'title' => $this->l('Nombre'),
                'type' => 'text',
                'align' => 'text-center',
                //'class' => 'fixed-width-xl',
                'filter_key' => 'a!product_name',
                'filter_type' => 'text',
                'order_key' => 'a!product_name'
            ),  
            'product_reference' => array(
                'title' => $this->l('Referencia'),
                'type' => 'text',
                'align' => 'text-center',
                //'class' => 'fixed-width-xl',
                'filter_key' => 'a!product_reference',
                'filter_type' => 'text',
                'order_key' => 'a!product_reference'
            ), 
            // MOD: 26/07/2023 Añadimos stock disponible 
            'stock_available' => array(
                'title' => $this->l('Disponible'),
                'align' => 'text-center',
                'class' => 'fixed-width-xs',
                'type' => 'text',
                'filter_key' => 'ava!quantity',
                'filter_type' => 'int',
            ),    
            'product_quantity' => array(
                'title' => $this->l('Cantidad'),
                'align' => 'text-center',
                'class' => 'fixed-width-xs',
                'type' => 'text',
                'filter_key' => 'a!product_quantity',
                'filter_type' => 'int',
            ),        
            // 'id_supplier' => array(
            //     'title' => $this->l('Id Proveedor'),
            //     'align' => 'text-center',
            //     'class' => 'fixed-width-xs',
            //     'type' => 'text',
            //     'filter_key' => 'a!id_supplier',
            //     'filter_type' => 'int',
            // ), 
            'supplier_name' => array(
                'title' => $this->l('Proveedor'),
                'type' => 'select',
                //'color' => 'color_pickpack',
                'list' => $this->suppliers_array,
                'filter_key' => 'sup!id_supplier',
                'filter_type' => 'int',
                'order_key' => 'sup!name'
            ),
            'product_supplier_reference' => array(
                'title' => $this->l('Referencia Proveedor'),
                'type' => 'text',
                'align' => 'text-center',
                //'class' => 'fixed-width-xl',
                'filter_key' => 'a!product_supplier_reference',
                'filter_type' => 'text',
                'order_key' => 'a!product_supplier_reference'
            ),   
            'checked' => array(
                'title' => $this->l('Revisado'),
                'type' => 'select',
                'class' => 'fixed-width-xs',
                'list' => array(0 => 'No', 1 => 'Si'),
                'filter_key' => 'a!checked',                               
            ),
            'date_add' => array(
                'title' => $this->l('Añadido'),
                'width' => 40,
                'type' => 'datetime',
                'filter_key' => 'a!date_add',
                'filter_type' => 'datetime',
            ),
        );
        
        // $this->actions = array('view'); 
        //añadimos un botón para ver el producto y el botón de revisar. 
        $this->addRowAction('view'); 
        $this->addRowAction('revisado'); 

        parent::__construct();
        
    }

    /*
     * Hace accesible js y css desde la página de controller/AdminProductosVendidosSinStock
     */
    public function setMedia(){
        parent::setMedia();
        $this->addJs($this->module->getPathUri().'views/js/back.js');
        //añadimos la dirección para el css
        $this->addCss($this->module->getPathUri().'views/css/back.css');
    }

    public function displayRevisadoLink($token = null, $id, $name = null)
    {
        // $token will contain token variable
        // $id will hold id_identifier value
        // $name will hold display name

        $tpl = $this->createTemplate('helpers/list/list_action_revisado.tpl');
        $href = 'linkhere';
        $tpl->assign(array(
            'id' => $id,        
            'action' => $this->l('Revisado')
        ));
        return $tpl->fetch();
    }

    //cuando se pulse sobre el botón de Revisado de una línea de producto
    public function postProcess()
    {
        //para que cargue bien el controlador y se pueda filtrar
        parent::postProcess();

        //si se pulsa el botón de Revisado para el producto individual
        if (Tools::isSubmit('submitRevisado')) {
            $id_empleado = Context::getContext()->employee->id;
            $nombre_empleado = Context::getContext()->employee->firstname; 
            $id_producto_vendido = Tools::getValue('submitRevisado');
            //sacamos el contenido del textarea si lo hay
            if (Tools::getValue('mensaje_pedidos') && strlen(Tools::getValue('mensaje_pedidos')) > 1){
                $mensaje_manual = pSQL(Tools::getValue('mensaje_pedidos'));
            }
            
            //sacamos toda la info necesaria de lafrips_productos_vendidos_sin_stock y también la info para cambiar el estado de pedido, poner mensaje, etc
            $sql_producto_vendido_sin_stock_etc = 'SELECT pvs.id_order AS id_order, ord.current_state AS current_state, pvs.id_order_status AS id_order_status, pvs.product_name AS product_name, 
            pvs.product_reference AS product_reference, pvs.checked AS checked, ord.id_customer AS id_customer
            FROM lafrips_productos_vendidos_sin_stock pvs
            JOIN lafrips_orders ord ON ord.id_order = pvs.id_order
            WHERE pvs.id_productos_vendidos_sin_stock = '.$id_producto_vendido;

            $producto_vendido_sin_stock_etc = Db::getInstance()->ExecuteS($sql_producto_vendido_sin_stock_etc);

            $id_order = $producto_vendido_sin_stock_etc[0]['id_order'];
            $current_state = $producto_vendido_sin_stock_etc[0]['current_state'];
            $id_order_status = $producto_vendido_sin_stock_etc[0]['id_order_status'];
            $product_name = pSQL($producto_vendido_sin_stock_etc[0]['product_name']);
            $product_reference = $producto_vendido_sin_stock_etc[0]['product_reference'];
            $checked = $producto_vendido_sin_stock_etc[0]['checked'];
            $id_customer = $producto_vendido_sin_stock_etc[0]['id_customer'];

            //sacamos id_status de Esperando productos
            // $sql_id_esperando_productos = "SELECT ost.id_order_state as id_esperando_productos
            // FROM lafrips_order_state ost
            // JOIN lafrips_order_state_lang osl ON osl.id_order_state = ost.id_order_state AND osl.id_lang = 1
            // WHERE osl.name = 'Esperando productos'
            // AND ost.deleted = 0";
            // $id_esperando_productos = Db::getInstance()->executeS($sql_id_esperando_productos)[0]['id_esperando_productos'];

            //30/06/2023
            $id_esperando_productos = Configuration::get(PS_ESPERANDO_PRODUCTOS);

            //comprobamos si ese producto ya está como checked en la tabla, si está mostramos error
            if ($checked){
                //si el estado de pedido no coincide en la tabla orders y en productos_vendidos_sin_stock, lo actualizamos en la última
                if ($current_state != $id_order_status){
                    $sql_update_productos_vendidos_sin_stock = 'UPDATE lafrips_productos_vendidos_sin_stock
                                SET                                                      
                                id_order_status = '.$current_state.'
                                WHERE id_order = '.$id_order;

                    Db::getInstance()->execute($sql_update_productos_vendidos_sin_stock);
                }
                $this->errors[] = Tools::displayError('Ese producto ya ha sido revisado');
                return;
            }
            //si el pedido está cancelado, mostramos error
            if ($current_state == Configuration::get(PS_OS_CANCELED)){
                //cambiamos estado de pedido en lafrips_productos_vendidos_sin_stock a cancelado
                if ($current_state != $id_order_status){
                    $sql_update_productos_vendidos_sin_stock = 'UPDATE lafrips_productos_vendidos_sin_stock
                                SET                                                      
                                id_order_status = '.$current_state.'
                                WHERE id_order = '.$id_order;

                    Db::getInstance()->execute($sql_update_productos_vendidos_sin_stock);
                }
                $this->errors[] = Tools::displayError('El pedido correspondiente se encuentra cancelado');
                return;
            }
            
            //si no está marcado, lo marcamos como checked, ponemos fecha date_checked,metemos un mensaje en su pedido diciendo que el producto está revisado por tal empleado, comprobamos si hay más productos vendidos sin stock en ese pedido, si los hay comprobamos si están checked. Si no lo están, lo dejamos así, si todos están checked comprobamos el estado actual del pedido, si es Sin Stock Pagado, lo pasariamos a Completando Pedido, añadiendo también un mensaje

            //marcamos checked y date checked, y empleado en lafrips_productos_vendidos_sin_stock.
            $sql_update_productos_vendidos_sin_stock = 'UPDATE lafrips_productos_vendidos_sin_stock
                                SET                                                      
                                checked = 1,
                                date_checked = NOW(),
                                id_employee = '.$id_empleado.' 
                                WHERE id_productos_vendidos_sin_stock = '.$id_producto_vendido;

            Db::getInstance()->execute($sql_update_productos_vendidos_sin_stock);

            //ponemos mensaje en el pedido de producto revisado
            //generamos mensaje para pedido sin stock pagado para producto revisado
            $fecha = date("d-m-Y H:i:s");
            $mensaje_pedido_sin_stock_producto = 'Producto vendido sin stock: 
            Nombre: '.$product_name.' 
            Referencia: '.$product_reference.'
            revisado por '.$nombre_empleado.' el '.$fecha;

            //luego generamos un nuevo customer_thread, debería existir uno para este cliente y pedido pero lo comprobamos  
            $customer = new Customer($id_customer);                
            //si existe ya un customer_thread para este pedido lo sacamos
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
                $ct->id_customer = $id_customer;
                $ct->id_order = $id_order;
                //$ct->id_product = 0;
                $ct->status = 'open';
                $ct->email = $customer->email;
                $ct->token = Tools::passwdGen(12);  // hay que generar un token para el hilo
                $ct->add();
            }           
            
            //si hay id de customer_thread continuamos
            if ($ct->id){
                //un mensaje interno para que aparezca la fecha de revisado del producto sin stock y el empleado en el back de pedidos
                $cm_interno = new CustomerMessage();
                $cm_interno->id_customer_thread = $ct->id;
                $cm_interno->id_employee = $id_empleado; 
                $cm_interno->message = $mensaje_pedido_sin_stock_producto;
                $cm_interno->private = 1;                
                $cm_interno->add();
            }  

            //si el usuario a puesto algún mensaje en el textarea, creamos un nuevo mensaje para el pedido
            if ($mensaje_manual) {
                //generamos mensaje manual para este producto revisado concreto
                $fecha = date("d-m-Y H:i:s");
                $mensaje_manual_pedido = 'Mensaje manual: 
                Producto: '.$product_name.' 
                Referencia: '.$product_reference.'
                Mensaje: 
                '.$mensaje_manual.'
                - '.$nombre_empleado.' el '.$fecha;

                if ($ct->id){
                    $cm_interno = new CustomerMessage();
                    $cm_interno->id_customer_thread = $ct->id;
                    $cm_interno->id_employee = $id_empleado; 
                    $cm_interno->message = $mensaje_manual_pedido;
                    $cm_interno->private = 1;                
                    $cm_interno->add();
                }
            }


            //comprobamos si después de poner checked = 1 en este producto aún quedan más productos vendidos sin stock en ese pedido
            $sql_otros_productos_pedido = 'SELECT id_product
            FROM lafrips_productos_vendidos_sin_stock 
            WHERE checked = 0
            AND id_order = '.$id_order;

            if (Db::getInstance()->ExecuteS($sql_otros_productos_pedido)) {
                //existen productos vendidos sin stock aún sin revisar en ese pedido, no hacemos nada respecto al estado de pedido en prestashop pero actualizamos el estado en lafrips_productos_vendidos_sin_stock, mostramos warning
                $sql_update_productos_vendidos_sin_stock = 'UPDATE lafrips_productos_vendidos_sin_stock
                                SET                                                      
                                id_order_status = '.$current_state.'
                                WHERE id_order = '.$id_order;

                Db::getInstance()->execute($sql_update_productos_vendidos_sin_stock);

                //mensaje success
                $this->confirmations[] = $this->l('Producto en pedido '.$id_order.' marcado como Revisado. ');

                $this->displayWarning('El pedido '.$id_order.' contiene otros productos sin revisar. ');
                return;

            } elseif ($id_esperando_productos) {
                //todos los productos vendidos sin stock en el pedido están revisados. comprobamos si el estado actual de pedido es Sin Stock Pagado, o si ya está en Completando Pedido. Si está en Sin stock pagado lo pasamos a Completando Pedido, con mensaje de success y mensaje privado en pedido. Si el pedido está ya en Completando Pedido, mostramos warning diciéndolo. Si no está en ninguno de los dos estados, no lo cambiamos, pero mostramos aviso.
                //22/08/2023 Ya no vamos a cambiar el estado de pedido si se revisa y no hay más productos para revisar ya que en el mismo proceso se harán otras cosas como cambiar el transporte a GLS24 según el caso, etc y lo centralizamos todo en la tarea CambiaEstadoPedido.php, de modo que si no hay más productos a revisar se muestra un mensaje y nada más
                if ($current_state == Configuration::get(PS_OS_OUTOFSTOCK_PAID)){

                    $this->confirmations[] = $this->l('Producto en pedido '.$id_order.' marcado como Revisado. ');
                    $this->displayWarning('El pedido se revisará para cambiar a Completando Pedido en el proceso horario. Todos sus productos vendidos sin stock están revisados. ');

                    return;

                    /*
                    //cambiamos estado, metemos mensaje a pedido y mostramos mensaje success ¿?. actualizamos el estado en lafrips_productos_vendidos_sin_stock
                    //se genera un objeto $history para crear los movimientos, asignandole el id del pedido sobre el que trabajamos            
                    //cambiamos estado de orden a Completando Pedido, ponemos id_employee 44 que es Automatizador, para log
                    $history = new OrderHistory();
                    $history->id_order = $id_order;
                    $history->id_employee = 44;
                    //comprobamos si ya tiene el invoice, payment etc, porque puede duplicar el método de pago. hasInvoice() devuelve true o false, y se pone como tercer argumento de changeIdOrderState() Primero tenemos que instanciar el pedido
                    $order = new Order($id_order);
                    $use_existing_payment = !$order->hasInvoice();
                    $history->changeIdOrderState($id_esperando_productos, $id_order, $use_existing_payment); 
                    $history->add(true);
                    $history->save();

                    //cambiamos esatdo en lafrips_productos_vendidos_sin_stock
                    $sql_update_productos_vendidos_sin_stock = 'UPDATE lafrips_productos_vendidos_sin_stock
                                SET                                                      
                                id_order_status = '.$id_esperando_productos.'
                                WHERE id_order = '.$id_order;

                    Db::getInstance()->execute($sql_update_productos_vendidos_sin_stock);

                    //metemos mensaje privado al pedido, ya tenemos abierto el customer_thread del mensaje de producto revisado
                    //generamos mensaje para pedido sin stock pagado para producto revisado
                    $fecha = date("d-m-Y H:i:s");
                    $mensaje_pedido_sin_stock_estado = 'Pedido cambiado a Completando Pedido                     
                    revisado por '.$nombre_empleado.' el '.$fecha;

                    if ($ct->id){
                        $cm_interno_cambio_estado = new CustomerMessage();
                        $cm_interno_cambio_estado->id_customer_thread = $ct->id;
                        $cm_interno_cambio_estado->id_employee = $id_empleado; 
                        $cm_interno_cambio_estado->message = $mensaje_pedido_sin_stock_estado;
                        $cm_interno_cambio_estado->private = 1;                    
                        $cm_interno_cambio_estado->add();
                    }

                    //mostramos mensaje de ok 
                    $this->confirmations[] = $this->l("Producto en pedido ".$id_order." marcado como Revisado.");
                    $this->confirmations[] = $this->l('Pedido '.$id_order.' pasado a Completando Pedido, con todos sus productos vendidos sin stock revisados. ');
                    //$this->displayWarning('Pedido pasado a Completando Pedido, con todos sus productos vendidos sin stock están revisados');
                    return;
                    */
                    
                } elseif ($current_state == $id_esperando_productos){
                    //solo mostramos warning de que ya estaba en ese estado (alguien debe haberlo cambiado manualmente), actualizamos el estado en lafrips_productos_vendidos_sin_stock
                    //cambiamos esatdo en lafrips_productos_vendidos_sin_stock
                    $sql_update_productos_vendidos_sin_stock = 'UPDATE lafrips_productos_vendidos_sin_stock
                                SET                                                      
                                id_order_status = '.$id_esperando_productos.'
                                WHERE id_order = '.$id_order;

                    Db::getInstance()->execute($sql_update_productos_vendidos_sin_stock);

                    $this->confirmations[] = $this->l('Producto en pedido '.$id_order.' marcado como Revisado. ');
                    $this->displayWarning('El pedido '.$id_order.' ya se encuentra en estado Pedido Sin Stock Pagado, todos sus productos vendidos sin stock están revisados. ');
                    return;

                } else {
                    //está en otro estado, mostramos warning pero no cambiamos a Completando Pedido, actualizamos el estado en lafrips_productos_vendidos_sin_stock
                    //cambiamos esatdo en lafrips_productos_vendidos_sin_stock
                    $sql_update_productos_vendidos_sin_stock = 'UPDATE lafrips_productos_vendidos_sin_stock
                                SET                                                      
                                id_order_status = '.$current_state.'
                                WHERE id_order = '.$id_order;

                    Db::getInstance()->execute($sql_update_productos_vendidos_sin_stock);

                    $this->confirmations[] = $this->l('Producto en pedido '.$id_order.' marcado como Revisado. ');
                    $this->displayWarning('El pedido '.$id_order.' no está en estado Pedido Sin Stock Pagado - No se cambia a Completando Pedido. Todos sus productos vendidos sin stock están revisados. ');
                    $this->errors[] = Tools::displayError('Revisar estado de pedido '.$id_order.' manualmente. ');
                    return;
                }

            } else {
                $this->errors[] = Tools::displayError('El estado Completando Pedido no se encuentra, imposible cambiar');
                return;
            }

        } //fin if submitRevisado 

        //si se pulsa el botón de Revisado para todos los productos iguales que estén pendientes de revisión
        if (Tools::isSubmit('submitTodosRevisados')) {
            $id_empleado = Context::getContext()->employee->id;
            $nombre_empleado = Context::getContext()->employee->firstname; 
            $id_producto_vendido = Tools::getValue('submitTodosRevisados');
            //sacamos el contenido del textarea si lo hay
            if (Tools::getValue('mensaje_pedidos') && strlen(Tools::getValue('mensaje_pedidos')) > 1) {
                $mensaje_manual_todos = pSQL(Tools::getValue('mensaje_pedidos'));
            }
            
            //sacamos toda la info necesaria de lafrips_productos_vendidos_sin_stock y también la info para cambiar el estado de pedido, poner mensaje, etc, para cada producto igual que está vendido sin stock y no revisado
            //primero necesitamos el id_product y id_product_attribute del producto, nombre y referencia
            $sql_ids_producto = 'SELECT id_product, id_product_attribute, product_name, product_reference
            FROM lafrips_productos_vendidos_sin_stock             
            WHERE id_productos_vendidos_sin_stock = '.$id_producto_vendido;

            $ids_producto = Db::getInstance()->ExecuteS($sql_ids_producto);

            $id_product = $ids_producto[0]['id_product'];
            $id_product_attribute = $ids_producto[0]['id_product_attribute'];
            $product_name = pSQL($ids_producto[0]['product_name']);
            $product_reference = $ids_producto[0]['product_reference'];

            //ahora la info de cada pedido con ese producto sin revisar (checked = 0)
            $sql_pedido_productos_vendido_sin_stock_etc = 'SELECT pvs.id_order AS id_order, ord.current_state AS current_state, pvs.id_order_status AS id_order_status, ord.id_customer AS id_customer, pvs.id_productos_vendidos_sin_stock AS id_productos_vendidos_sin_stock
            FROM lafrips_productos_vendidos_sin_stock pvs
            JOIN lafrips_orders ord ON ord.id_order = pvs.id_order
            WHERE pvs.checked = 0
            AND ord.valid = 1
            AND pvs.id_product = '.$id_product.' 
            AND pvs.id_product_attribute = '.$id_product_attribute;

            $pedido_productos_vendido_sin_stock_etc = Db::getInstance()->ExecuteS($sql_pedido_productos_vendido_sin_stock_etc);

            //por cada línea de pedido trabajaremos
            foreach ($pedido_productos_vendido_sin_stock_etc AS $pedido){
                $id_order = $pedido['id_order'];
                $current_state = $pedido['current_state'];
                $id_order_status = $pedido['id_order_status'];                
                $id_customer = $pedido['id_customer'];
                $id_productos_vendidos_sin_stock = $pedido['id_productos_vendidos_sin_stock'];

                //sacamos id_status de Esperando productos
                // $sql_id_esperando_productos = "SELECT ost.id_order_state as id_esperando_productos
                // FROM lafrips_order_state ost
                // JOIN lafrips_order_state_lang osl ON osl.id_order_state = ost.id_order_state AND osl.id_lang = 1
                // WHERE osl.name = 'Esperando productos'
                // AND ost.deleted = 0";
                // $id_esperando_productos = Db::getInstance()->executeS($sql_id_esperando_productos)[0]['id_esperando_productos'];

                //30/06/2023
                $id_esperando_productos = Configuration::get(PS_ESPERANDO_PRODUCTOS);

                //por cada pedido y producto, marcamos checked y date checked, y empleado en lafrips_productos_vendidos_sin_stock.
                $sql_update_productos_vendidos_sin_stock = 'UPDATE lafrips_productos_vendidos_sin_stock
                SET                                                      
                checked = 1,
                date_checked = NOW(),
                id_employee = '.$id_empleado.' 
                WHERE id_productos_vendidos_sin_stock = '.$id_productos_vendidos_sin_stock;

                Db::getInstance()->execute($sql_update_productos_vendidos_sin_stock);

                //ponemos mensaje en el pedido de producto revisado
                //generamos mensaje para pedido sin stock pagado para producto revisado
                $fecha = date("d-m-Y H:i:s");
                $mensaje_pedido_sin_stock_producto = 'Producto vendido sin stock: 
                Nombre: '.$product_name.' 
                Referencia: '.$product_reference.'
                revisado por '.$nombre_empleado.' el '.$fecha;

                //luego generamos un nuevo customer_thread, debería existir uno para este cliente y pedido pero lo comprobamos  
                $customer = new Customer($id_customer);                
                //si existe ya un customer_thread para este pedido lo sacamos
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
                    $ct->id_customer = $id_customer;
                    $ct->id_order = $id_order;
                    //$ct->id_product = 0;
                    $ct->status = 'open';
                    $ct->email = $customer->email;
                    $ct->token = Tools::passwdGen(12);  // hay que generar un token para el hilo
                    $ct->add();
                }           
                
                //si hay id de customer_thread continuamos
                if ($ct->id){
                    //un mensaje interno para que aparezca la fecha de revisado del producto sin stock y el empleado en el back de pedidos
                    $cm_interno = new CustomerMessage();
                    $cm_interno->id_customer_thread = $ct->id;
                    $cm_interno->id_employee = $id_empleado; 
                    $cm_interno->message = $mensaje_pedido_sin_stock_producto;
                    $cm_interno->private = 1;                
                    $cm_interno->add();
                }  

                //si el usuario a puesto algún mensaje en el textarea, creamos un nuevo mensaje para el pedido y producto
                if ($mensaje_manual_todos) {
                    //generamos mensaje manual para este producto revisado concreto
                    $fecha = date("d-m-Y H:i:s");
                    $mensaje_manual_todos_pedido = 'Mensaje manual: 
                    Producto: '.$product_name.' 
                    Referencia: '.$product_reference.'
                    Mensaje: 
                    '.$mensaje_manual_todos.'
                    - '.$nombre_empleado.' el '.$fecha;

                    if ($ct->id){
                        $cm_interno = new CustomerMessage();
                        $cm_interno->id_customer_thread = $ct->id;
                        $cm_interno->id_employee = $id_empleado; 
                        $cm_interno->message = $mensaje_manual_todos_pedido;
                        $cm_interno->private = 1;                
                        $cm_interno->add();
                    }
                }

                //comprobamos si después de poner checked = 1 en este producto aún quedan más productos vendidos sin stock en ese pedido
                $sql_otros_productos_pedido = 'SELECT id_product
                FROM lafrips_productos_vendidos_sin_stock 
                WHERE checked = 0
                AND id_order = '.$id_order;

                if (Db::getInstance()->ExecuteS($sql_otros_productos_pedido)) {
                    //existen productos vendidos sin stock aún sin revisar en ese pedido, no hacemos nada respecto al estado de pedido en prestashop pero actualizamos el estado en lafrips_productos_vendidos_sin_stock, mostramos warning
                    $sql_update_productos_vendidos_sin_stock = 'UPDATE lafrips_productos_vendidos_sin_stock
                                    SET                                                      
                                    id_order_status = '.$current_state.'
                                    WHERE id_order = '.$id_order;

                    Db::getInstance()->execute($sql_update_productos_vendidos_sin_stock);

                    //mensaje success
                    $this->confirmations[] = $this->l('Producto en pedido '.$id_order.' marcado como Revisado. ');

                    $this->displayWarning('El pedido '.$id_order.' contiene otros productos sin revisar. ');
                    continue;

                } elseif ($id_esperando_productos) {
                    //todos los productos vendidos sin stock en el pedido están revisados. comprobamos si el estado actual de pedido es Sin Stock Pagado, o si ya está en Completando Pedido. Si está en Sin stock pagado lo pasamos a Completando Pedido, con mensaje de success y mensaje privado en pedido. Si el pedido está ya en Completando Pedido, mostramos warning diciéndolo. Si no está en ninguno de los dos estados, no lo cambiamos, pero mostramos aviso.
                    //22/08/2023 Ya no vamos a cambiar el estado de pedido si se revisa y no hay más productos para revisar ya que en el mismo proceso se harán otras cosas como cambiar el transporte a GLS24 según el caso, etc y lo centralizamos todo en la tarea CambiaEstadoPedido.php, de modo que si no hay más productos a revisar se muestra un mensaje y nada más
                    if ($current_state == Configuration::get(PS_OS_OUTOFSTOCK_PAID)){
                        
                        $this->confirmations[] = $this->l('Producto en pedido '.$id_order.' marcado como Revisado. ');
                        $this->displayWarning('El pedido '.$id_order.' se revisará para cambiar a Completando Pedido en el proceso horario. Todos sus productos vendidos sin stock están revisados. ');

                        continue;

                        /*
                        //cambiamos estado, metemos mensaje a pedido y mostramos mensaje success. Actualizamos el estado en lafrips_productos_vendidos_sin_stock
                        //se genera un objeto $history para crear los movimientos, asignándole el id del pedido sobre el que trabajamos            
                        //cambiamos estado de orden a Completando Pedido, ponemos id_employee 44 que es Automatizador, para log
                        $history = new OrderHistory();
                        $history->id_order = $id_order;
                        $history->id_employee = 44;
                        //comprobamos si ya tiene el invoice, payment etc, porque puede duplicar el método de pago. hasInvoice() devuelve true o false, y se pone como tercer argumento de changeIdOrderState() Primero tenemos que instanciar el pedido
                        $order = new Order($id_order);
				        $use_existing_payment = !$order->hasInvoice();
                        $history->changeIdOrderState($id_esperando_productos, $id_order, $use_existing_payment); 
                        $history->add(true);
                        $history->save();

                        //cambiamos esatdo en lafrips_productos_vendidos_sin_stock
                        $sql_update_productos_vendidos_sin_stock = 'UPDATE lafrips_productos_vendidos_sin_stock
                                    SET                                                      
                                    id_order_status = '.$id_esperando_productos.'
                                    WHERE id_order = '.$id_order;

                        Db::getInstance()->execute($sql_update_productos_vendidos_sin_stock);

                        //metemos mensaje privado al pedido, ya tenemos abierto el customer_thread del mensaje de producto revisado
                        //generamos mensaje para pedido sin stock pagado para producto revisado
                        $fecha = date("d-m-Y H:i:s");
                        $mensaje_pedido_sin_stock_estado = 'Pedido cambiado a Completando Pedido                     
                        revisado por '.$nombre_empleado.' el '.$fecha;

                        $cm_interno_cambio_estado = new CustomerMessage();
                        $cm_interno_cambio_estado->id_customer_thread = $ct->id;
                        $cm_interno_cambio_estado->id_employee = $id_empleado; 
                        $cm_interno_cambio_estado->message = $mensaje_pedido_sin_stock_estado;
                        $cm_interno_cambio_estado->private = 1;                    
                        $cm_interno_cambio_estado->add();

                        //mostramos mensaje de ok 
                        $this->confirmations[] = $this->l('Producto en pedido '.$id_order.' marcado como Revisado. ');
                        $this->confirmations[] = $this->l('Pedido '.$id_order.' pasado a Completando Pedido, con todos sus productos vendidos sin stock revisados. ');
                        //$this->displayWarning('Pedido pasado a Completando Pedido, con todos sus productos vendidos sin stock están revisados');
                        continue;
                        */
                        
                    } elseif ($current_state == $id_esperando_productos){
                        //solo mostramos warning de que ya estaba en ese estado (alguien debe haberlo cambiado manualmente), actualizamos el estado en lafrips_productos_vendidos_sin_stock
                        //cambiamos esatdo en lafrips_productos_vendidos_sin_stock
                        $sql_update_productos_vendidos_sin_stock = 'UPDATE lafrips_productos_vendidos_sin_stock
                                    SET                                                      
                                    id_order_status = '.$id_esperando_productos.'
                                    WHERE id_order = '.$id_order;

                        Db::getInstance()->execute($sql_update_productos_vendidos_sin_stock);

                        $this->confirmations[] = $this->l('Producto en pedido '.$id_order.' marcado como Revisado. ');
                        $this->displayWarning('El pedido '.$id_order.' ya se encuentra en estado Pedido Sin Stock Pagado, todos sus productos vendidos sin stock están revisados. ');
                        continue;

                    } else {
                        //está en otro estado, mostramos warning pero no cambiamos a Completando Pedido, actualizamos el estado en lafrips_productos_vendidos_sin_stock
                        //cambiamos estado en lafrips_productos_vendidos_sin_stock
                        $sql_update_productos_vendidos_sin_stock = 'UPDATE lafrips_productos_vendidos_sin_stock
                                    SET                                                      
                                    id_order_status = '.$current_state.'
                                    WHERE id_order = '.$id_order;

                        Db::getInstance()->execute($sql_update_productos_vendidos_sin_stock);

                        $this->confirmations[] = $this->l('Producto en pedido '.$id_order.' marcado como Revisado. ');
                        $this->displayWarning('El pedido '.$id_order.' no está en estado Pedido Sin Stock Pagado - No se cambia a Completando Pedido. Todos sus productos vendidos sin stock están revisados. ');
                        $this->errors[] = Tools::displayError('Revisar estado de pedido '.$id_order.' manualmente. ');
                        continue;
                    }

                } else {
                    $this->errors[] = Tools::displayError('El estado Completando Pedido no se encuentra, imposible cambiar');
                    continue;
                }

            } //foreach pedido con producto sin stock sin revisar

        } //fin if submitTodosRevisados

    } // fin postProcess
    
    //cuando se pulse sobre la línea del producto o el botón ver, mostramos info sobre este y sus proveedores disponibles
    public function renderView(){
        //como no hemos generado una clase etc para el controlador, recogemos de la url el valor del id del producto en lafrips_productos_vendidos_sin_stock. Con eso, sacamos todoos los datos necesarios y los asignamos a variables smarty para mostrarlo en el tpl que asignamos también más abajo, que será productosvendidossinstock.tpl
        //27/08/2020 Después de añadir a la web Cerdá Kids, queremos que cuando se trate de uno de esos productos sea bien claro. Se lesdiferencia bien por su id_manufacturer = 76
        
        $id_producto_vendido_sin_stock = Tools::getValue('id_productos_vendidos_sin_stock');

        //sacamos la info del producto en tabla productos_vendido_sin_stock y también foto
        $sql_producto_vendido_sin_stock = "SELECT pvs.id_product AS id_product, pvs.id_product_attribute AS id_product_attribute, ode.product_ean13 AS ean, 
        pvs.product_name AS product_name, pvs.product_reference AS product_reference, pvs.out_of_stock AS out_of_stock, pvs.checked AS checked, 
        pvs.id_default_supplier, pvs.product_supplier_reference, pvs.product_quantity, pvs.id_order AS id_order, osl.name AS estado_pedido,
        CONCAT( 'http://lafrikileria.com', '/', img.id_image, '-home_default/', 
                pla.link_rewrite, '.', 'jpg') AS imagen, img.id_image AS existe_imagen, pvs.date_checked AS date_checked, pvs.id_employee AS revisador, 
        pro.id_manufacturer AS id_manufacturer
        FROM lafrips_productos_vendidos_sin_stock pvs
        JOIN lafrips_orders ord ON ord.id_order = pvs.id_order
        JOIN lafrips_order_state_lang osl ON osl.id_order_state = ord.current_state AND osl.id_lang = 1
        LEFT JOIN lafrips_order_detail ode ON ode.id_order = pvs.id_order 
            AND ode.product_id = pvs.id_product 	
            AND ode.product_attribute_id = pvs.id_product_attribute 
        LEFT JOIN lafrips_image img ON pvs.id_product = img.id_product
                AND img.cover = 1
        JOIN lafrips_product pro ON pro.id_product = pvs.id_product 
        JOIN lafrips_product_lang pla ON pla.id_product = pvs.id_product AND pla.id_lang = 1 
        WHERE pvs.id_productos_vendidos_sin_stock = ".$id_producto_vendido_sin_stock;

        $producto_vendido_sin_stock = Db::getInstance()->ExecuteS($sql_producto_vendido_sin_stock);

        $id_product = $producto_vendido_sin_stock[0]['id_product'];
        $id_product_attribute = $producto_vendido_sin_stock[0]['id_product_attribute'];
        $out_of_stock = $producto_vendido_sin_stock[0]['out_of_stock'];
        $ean = trim($producto_vendido_sin_stock[0]['ean']);
        $product_name = $producto_vendido_sin_stock[0]['product_name'];
        $product_reference = $producto_vendido_sin_stock[0]['product_reference'];
        $product_quantity = $producto_vendido_sin_stock[0]['product_quantity'];
        $id_order = $producto_vendido_sin_stock[0]['id_order'];   
        $estado_pedido = $producto_vendido_sin_stock[0]['estado_pedido'];        
        $checked = $producto_vendido_sin_stock[0]['checked'];
        $date_checked = $producto_vendido_sin_stock[0]['date_checked'];
        //formateamos fecha
        $date_checked = date_create($date_checked); 
        $date_checked = date_format($date_checked, 'd-m-Y H:i:s');

        $id_revisador = $producto_vendido_sin_stock[0]['revisador'];
        //sacamos nombre empleado revisador
        $revisador = new Employee($id_revisador);
        $nombre_revisador = $revisador->firstname.' '.$revisador->lastname;

        $id_default_supplier = $producto_vendido_sin_stock[0]['id_default_supplier'];
        $product_supplier_reference = trim($producto_vendido_sin_stock[0]['product_supplier_reference']);
        //sacamos precio de coste
        $wholesale_price = ProductSupplier::getProductPrice($id_default_supplier, $id_product, $id_product_attribute, $converted_price = false);

        //comprobamos si el producto es Cerdá Kids o Adult 08/10/2020
        //id de manufacturer por nombre
        $id_manufacturer_cerda_kids = (int)Manufacturer::getIdByName('Cerdá Kids');
        $id_manufacturer_cerda_adult = (int)Manufacturer::getIdByName('Cerdá Adult');
        $id_manufacturer = $producto_vendido_sin_stock[0]['id_manufacturer'];
        $producto_kids = 0;
        if (($id_manufacturer == $id_manufacturer_cerda_kids) || ($id_manufacturer == $id_manufacturer_cerda_adult)) {
            $producto_kids = 1;
        }

                
        //si en $existe_imagen no hay un id ponemos logo
        if (empty($producto_vendido_sin_stock[0]['existe_imagen'])) {
            $imagen = 'https://lafrikileria.com/img/logo_producto_medium_default.jpg';
        } else {
            $imagen = $producto_vendido_sin_stock[0]['imagen'];
        }

        $default_supplier = Supplier::getNameById($id_default_supplier);
        
        //sacamos los proveedores que tiene asignados, sacando el objeto prestashop collection y extrayendo los suppliers
        $collection_proveedores_producto = ProductSupplier::getSupplierCollection($id_product); 
        $proveedores_producto = array();                 
        $info_proveedores_producto = array();  
        foreach ($collection_proveedores_producto as $associated_supplier){ 
            $proveedores_producto[] = $associated_supplier->id_supplier; 
            $info_proveedores_producto[$associated_supplier->id_supplier]['id_supplier'] = $associated_supplier->id_supplier;
            $info_proveedores_producto[$associated_supplier->id_supplier]['product_supplier_reference'] = $associated_supplier->product_supplier_reference;
            $info_proveedores_producto[$associated_supplier->id_supplier]['product_supplier_price_te'] = $associated_supplier->product_supplier_price_te;
            //el nombre de proveedor no viene en la colección
            $info_proveedores_producto[$associated_supplier->id_supplier]['name'] = Supplier::getNameById($associated_supplier->id_supplier);               
        }

        //sacamos los proveedores y precios, y si está disponible, en caso de que el producto se encuentre en la tabla frik_import_catalogos. Se busca por ean, si lo tiene
        if ((!empty($ean))&&($ean !== '')){
            $sql_proveedores_import_catalogos = "SELECT referencia_proveedor, id_proveedor, nombre_proveedor, precio, disponibilidad
            FROM frik_import_catalogos
            WHERE ean = ".$ean;  

            $proveedores_import_catalogos = Db::getInstance()->executeS($sql_proveedores_import_catalogos);
        }

        //unidades vendidas en otros pedidos sin stock pagado, que aún no han sido revisadas (checked = 0)
        $sql_unidades_vendidas_sin_stock = "SELECT SUM(product_quantity) AS total
        FROM lafrips_productos_vendidos_sin_stock         
        WHERE checked = 0
        AND id_product = ".$id_product."
        AND id_product_attribute = ".$id_product_attribute;

        $unidades_vendidas_sin_stock = Db::getInstance()->ExecuteS($sql_unidades_vendidas_sin_stock);
        //unidades totales 
        $unidades_vendidas_sin_stock = $unidades_vendidas_sin_stock[0]['total'];
        //stock disponible del producto
        $stock_disponible = StockAvailableCore::getQuantityAvailableByProduct($id_product);
        
        //última venta
        $sql_ultima_venta = "SELECT ord.date_add AS ultima_venta
        FROM lafrips_orders ord 
        JOIN lafrips_order_detail ode ON ord.id_order = ode.id_order 
        WHERE ode.product_id = ".$id_product."
        AND ode.product_attribute_id = ".$id_product_attribute."
        AND ord.valid = 1 
        ORDER BY ord.id_order DESC LIMIT 1";    

        $fecha_ultima_venta = Db::getInstance()->ExecuteS($sql_ultima_venta)[0]['ultima_venta'];
        if (!$fecha_ultima_venta) {
            $ultima_venta = 'Error fecha';
        } else {
            $ultima_venta = date_create($fecha_ultima_venta); 
            $ultima_venta = date_format($ultima_venta, 'd-m-Y');
        }        

        //ventas totales, sin packs antiguos
        $sql_ventas_totales = "SELECT SUM(ode.product_quantity) AS ventas_totales
        FROM lafrips_order_detail ode 
        JOIN lafrips_orders ord ON ord.id_order = ode.id_order
        WHERE ord.valid = 1
        AND ode.product_id = ".$id_product."
        AND ode.product_attribute_id = ".$id_product_attribute;    

        $ventas_totales = Db::getInstance()->ExecuteS($sql_ventas_totales)[0]['ventas_totales'];

        //ventas últimos 6 meses
        $sql_ultimos6meses = "SELECT SUM(ode.product_quantity) AS ultimos6meses
        FROM lafrips_order_detail ode 
        JOIN lafrips_orders ord ON ord.id_order = ode.id_order
        WHERE ord.date_add > DATE_SUB(NOW(), INTERVAL 6 MONTH) 
        AND ord.valid = 1
        AND ode.product_id = ".$id_product."
        AND ode.product_attribute_id = ".$id_product_attribute;    

        $ultimos6meses = Db::getInstance()->ExecuteS($sql_ultimos6meses)[0]['ultimos6meses'];
        if (!$ultimos6meses) {
            $ultimos6meses = 'No';
        }

        //última compra a proveedor (si se hizo bien el pedido a proveedores)
        $sql_ultima_compra = "SELECT sor.date_add AS ultima_compra
        FROM lafrips_supply_order sor 
        JOIN lafrips_supply_order_detail sod ON sor.id_supply_order = sod.id_supply_order 
        WHERE sod.id_product = ".$id_product." 
        AND sod.id_product_attribute = ".$id_product_attribute."
        ORDER BY sor.id_supply_order DESC LIMIT 1";       

        $fecha_ultima_compra = Db::getInstance()->ExecuteS($sql_ultima_compra)[0]['ultima_compra'];
        if (!$fecha_ultima_compra) {
            $ultima_compra = 'Sin pedidos a proveedor';
        } else {
            $ultima_compra = date_create($fecha_ultima_compra); 
            $ultima_compra = date_format($ultima_compra, 'd-m-Y');
        }           

        //sacamos los productos que quedan vendidos sin stock en ese pedido sin marcar revisados, si hay más aparte de este
        $sql_otros_productos_pedido = 'SELECT pvs.id_product AS id_product, pvs.product_name AS product_name, pvs.product_reference AS product_reference, 
        pvs.product_quantity AS product_quantity, sup.name AS supplier
        FROM lafrips_productos_vendidos_sin_stock pvs
        JOIN lafrips_supplier sup ON sup.id_supplier = pvs.id_default_supplier
        WHERE pvs.checked = 0
        AND pvs.id_order = '.$id_order.'
        AND pvs.id_productos_vendidos_sin_stock != '.$id_producto_vendido_sin_stock;

        $otros_productos_pedidos = Db::getInstance()->ExecuteS($sql_otros_productos_pedido);
        
        //construimos link para poder visitar el pedido en que se encuentra el producto
        //https://lafrikileria.com/lfadminia/index.php?controller=AdminOrders&token=b192540700c383eeb6b26f1da43998da&id_order=225726&vieworder
        $token_adminorders = Tools::getAdminTokenLite('AdminOrders');
        $url_base = Tools::getHttpHost(true).__PS_BASE_URI__;
        $url_pedido = $url_base.'lfadminia/index.php?controller=AdminOrders&token='.$token_adminorders.'&id_order='.$id_order.'&vieworder';

        //buscamos el producto en la tabla cima_gpe que es la que almacena los productos del gestor de prepedidos de Cima. Como la tabla de cima deja como null el id_attribute si no lo tiene no puedo comparar con 0. Si el producto tiene buscamos, si no tiene atributo ignoramos en la sql
        if ($id_product_attribute) {
            $sql_atributo = ' AND product_attribute_id = '.$id_product_attribute;
        } else {
            $sql_atributo = '';
        }

        $sql_unidades_gestor_cima = 'SELECT uds_solicitadas, fecha
        FROM cima_gpe 
        WHERE product_id = '.$id_product.$sql_atributo;

        if ($unidades_gestor_cima = Db::getInstance()->ExecuteS($sql_unidades_gestor_cima)) {
            $unidades_gestor = $unidades_gestor_cima[0]['uds_solicitadas'];
            $fecha_gestor = $unidades_gestor_cima[0]['fecha'];
            $fecha_gestor = date_create($fecha_gestor); 
            $fecha_gestor = date_format($fecha_gestor, 'd-m-Y');
        } else {
            $unidades_gestor = 'No Solicitado';
            $fecha_gestor = '';
        }
        //url a gestor prepedidos
        $gestor_token = Tools::getAdminToken('Gestionpedidos_cima'.(int)Tab::getIdFromClassName('Gestionpedidos_cima').(int)Context::getContext()->employee->id);
        $url_gestor_prepedidos = $url_base.'modules/gestionpedidos_cima/gestionpedidos.php?token='.$gestor_token;
        

        //asignamos la plantilla a esta vista
        $tpl = $this->context->smarty->createTemplate(dirname(__FILE__).'/../../views/templates/admin/productosvendidossinstock.tpl');

        //asignamos a smarty la info de cliente y pedido y productos en pedido
        $tpl->assign(
            array(   
                'id' => $id_producto_vendido_sin_stock,                         
                'id_product' => $id_product,
                'id_product_attribute' => $id_product_attribute, 
                'out_of_stock' => $out_of_stock,
                'ean' => $ean,
                'product_name' => $product_name, 
                'product_reference' => $product_reference,
                'checked' => $checked,
                'date_checked' => $date_checked,
                'nombre_revisador' => $nombre_revisador,
                'id_default_supplier' => $id_default_supplier,
                'wholesale_price' => $wholesale_price,
                'imagen' => $imagen,
                'default_supplier' => $default_supplier,  
                'product_supplier_reference' => $product_supplier_reference, 
                'info_proveedores_producto' => $info_proveedores_producto,
                'proveedores_import_catalogos' => $proveedores_import_catalogos, 
                'product_quantity' => $product_quantity, 
                'unidades_vendidas_sin_stock' => $unidades_vendidas_sin_stock,
                'stock_disponible' => $stock_disponible,
                'producto_kids' => $producto_kids,
                'id_order' => $id_order,
                'estado_pedido' => $estado_pedido, 
                'ventas_totales' => $ventas_totales,
                'ultima_venta' => $ultima_venta,
                'ultimos6meses' => $ultimos6meses,
                'ultima_compra' => $ultima_compra, 
                'otros_productos_pedidos' => $otros_productos_pedidos, 
                'url_pedido' => $url_pedido, 
                'unidades_gestor_prepedidos' => $unidades_gestor, 
                'fecha_gestor_prepedidos' => $fecha_gestor,
                'url_gestor_prepedidos' => $url_gestor_prepedidos,
                'token' => Tools::getAdminTokenLite('AdminProductosVendidosSinStock'),
                'url_base' => Tools::getHttpHost(true).__PS_BASE_URI__.'lfadminia/',
            )
        );    
            
        return $tpl->fetch();

    }

}

?>

