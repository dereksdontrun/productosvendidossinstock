<?php
/**
 * Gestión de pedidos de proveedores Dropshipping 16/02/2022 - integrado con módulo productosvendidossinstock
 *
 *  @author    Sergio™ <sergio@lafrikileria.com>
 *    
 */

if (!defined('_PS_VERSION_'))
    exit;

class AdminPedidosDropshippingController extends ModuleAdminController {
    
    public function __construct() {
        require_once (dirname(__FILE__) .'/../../productosvendidossinstock.php');

        $this->lang = false;
        $this->bootstrap = true;        
        $this->context = Context::getContext();
        
        parent::__construct();
        
    }
    
    /**
     * AdminController::init() override
     * @see AdminController::init()
     */
    public function init() {
        $this->display = 'add';
        parent::init();
    }
   
    /*
     *
     */
    public function setMedia(){
        parent::setMedia();
        $this->addJs($this->module->getPathUri().'views/js/back_pedidos_dropshipping.js');
        //añadimos la dirección para el css
        $this->addCss($this->module->getPathUri().'views/css/back_pedidos_dropshipping.css');
    }


    /**
     * AdminController::renderForm() override
     * @see AdminController::renderForm()
     */
    public function renderForm() {    

        //generamos el token de AdminPedidosDropshipping ya que lo vamos a usar en el archivo de javascript . Lo almacenaremos en un input hidden para acceder a el desde js
        $token_admin_modulo = Tools::getAdminTokenLite('AdminPedidosDropshipping');

        $this->fields_form = array(
            'legend' => array(
                'title' => 'Pedidos Dropshipping',
                'icon' => 'icon-pencil'
            ),
            'input' => array( 
                //input hidden con el token para usarlo por ajax etc
                array(  
                    'type' => 'hidden',                    
                    'name' => 'token_admin_modulo_'.$token_admin_modulo,
                    'id' => 'token_admin_modulo_'.$token_admin_modulo,
                    'required' => false,                                        
                ),                 
            ),
            
            // 'reset' => array('title' => 'Limpiar', 'icon' => 'process-icon-eraser icon-eraser'),   
            // 'submit' => array('title' => 'Guardar', 'icon' => 'process-icon-save icon-save'),            
        );

        // $this->displayInformation(
        //     'Revisar productos que actualmente se encuentran en la categoría Prepedido, vendidos o no, o revisar productos vendidos sin stock que se encuentran en pedidos en espera, con o sin categoría prepedido'
        // );
        
        return parent::renderForm();
    }

    public function postProcess() {

        parent::postProcess();

        
    }

    //función que devuelve al front los proveedores configurados como dropshipping para formar el SELECT en el controlador
    public function ajaxProcessObtenerProveedores() {
        $proveedores_dropshipping = explode(",", Configuration::get('PROVEEDORES_DROPSHIPPING'));

        $info_proveedores = array();

        foreach ($proveedores_dropshipping AS $id_supplier) {
            $proveedor = array();
            $proveedor['id_supplier'] = $id_supplier;
            $proveedor['name'] = Supplier::getNameById($id_supplier);

            $info_proveedores[] = $proveedor;
        }

        if ($info_proveedores) {
            //ordenamos el array por el campo name
            $columnas = array_column($info_proveedores, 'name');
            array_multisort($columnas, SORT_ASC, $info_proveedores);

            //devolvemos la lista 
            die(Tools::jsonEncode(array('message'=>'Info de proveedores obtenida correctamente', 'info_proveedores' => $info_proveedores)));
        } else { 
            //error al sacar los proveedores           
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'Error obteniendo la información de los proveedores')));
        }   
    }

    /*
    * Función que busca los pedidos dropshipping de la tabla lafrips_dropshipping. La llamamos mediante javascript/ajax al cargar la vista del controlador 
    *
    */
    public function ajaxProcessListaPedidos(){    
        //comprobamos si han llegado argumentos para la búsqueda y filtros        
        $buscar_id = Tools::getValue('buscar_id',0);
        $buscar_proveedor = Tools::getValue('buscar_proveedor',0);
        $buscar_estado = Tools::getValue('buscar_estado',0);
        $buscar_pedidos_desde = Tools::getValue('buscar_pedidos_desde',0);
        $buscar_pedidos_hasta = Tools::getValue('buscar_pedidos_hasta',0);
        $limite_pedidos = Tools::getValue('limite_pedidos',0);
        $pagina_actual = Tools::getValue('pagina_actual',0);
        $sentido_paginacion = Tools::getValue('paginacion',0);        

        //este valor indica como ordenar el resultado de búsqueda. De momento puede ser por id_order arriba y abajo, o fecha de pedido arriba y abajo
        $ordenar = Tools::getValue('ordenar',0);
        if ($ordenar == 'orden_fecha_abajo') {
            $order_by = ' ORDER BY dro.date_add DESC';
        } elseif ($ordenar == 'orden_fecha_arriba') {
            $order_by = ' ORDER BY dro.date_add ASC';
        } elseif ($ordenar == 'orden_id_abajo') {
            $order_by = ' ORDER BY dro.id_order DESC';
        } elseif ($ordenar == 'orden_id_arriba') {
            $order_by = ' ORDER BY dro.id_order ASC';
        } else {
            $order_by = ' ORDER BY dro.date_add DESC';
        }

        if ($buscar_id) {
            $where_id = ' AND dro.id_order = '.$buscar_id;
        } else {
            $where_id = '';
        }

        if ($buscar_proveedor) {
            $where_proveedor = ' AND dro.id_supplier = '.$buscar_proveedor;
        } else {
            $where_proveedor = '';
        }

        if ($buscar_estado) {
            if ($buscar_estado == 1) { //error
                $where_estado = ' AND dro.error = 1 ';
            } elseif ($buscar_estado == 2) { //cancelado
                $where_estado = ' AND dro.cancelado = 1 ';
            } elseif ($buscar_estado == 3) { //pendiente
                $where_estado = ' AND dro.error = 0 AND dro.cancelado = 0 AND dro.procesado = 0 AND dro.finalizado = 0 ';
            } elseif ($buscar_estado == 4) { //aceptado
                $where_estado = ' AND dro.procesado = 1 AND dro.finalizado = 0 ';
            } elseif ($buscar_estado == 5) { //finalizado
                $where_estado = ' AND dro.procesado = 1 AND dro.finalizado = 1 ';
            }
        } else {
            $where_estado = '';
        }

        if ($buscar_pedidos_desde && $buscar_pedidos_hasta) {
            $where_fecha = ' AND dro.date_add BETWEEN  "'.$buscar_pedidos_desde.'"  AND "'.$buscar_pedidos_hasta.'" + INTERVAL 1 DAY ';            
        } elseif ($buscar_pedidos_desde && !$buscar_pedidos_hasta) {
            $where_fecha = ' AND dro.date_add > "'.$buscar_pedidos_desde.'" ';  
        } elseif (!$buscar_pedidos_desde && $buscar_pedidos_hasta) {
            $where_fecha = ' AND dro.date_add < "'.$buscar_pedidos_hasta.'" ';    
        } else {
            $where_fecha = ''; 
        }

        //antes de sacar la lista y datos a mostrar necesitamos saber el total de pedidos que cumplen las condiciones de la petición sin limites ni offset, para pasarlo de veulta como parámetro y también poder usarlo en el offset si se pulsa la flecha de mostrar la última página
        $sql_total_pedidos = 'SELECT COUNT(dro.id_dropshipping)
        FROM lafrips_dropshipping dro
        JOIN lafrips_dropshipping_address dra ON dra.id_dropshipping_address = dro.id_dropshipping_address AND dra.id_order = dro.id_order
        WHERE 1 '.
        $where_id.
        $where_proveedor.
        $where_estado.
        $where_fecha;

        $total_pedidos = Db::getInstance()->getValue($sql_total_pedidos); 

        //para la paginación y limite tenemos el valor de la página en que nos encontramos, el número de pedidos a mostrar por página y si hay que mostrar otra página diferente en $sentido_paginacion.
        //si página actual es 1 y $sentido_paginacion está vacío es que se muestran los primeros pedidos hasta $limite_pedidos que aparezcan, $offset es 0.
        //si página actual no es 1 y $sentido_paginacion está vacío es que se muestran los primeros pedidos hasta $limite_pedidos que aparezcan, poniendo un offset de $limite_pedidos multiplicado por la página a la que vamos menos 1.

        $limite_y_offset = '';
        $offset = 0;
        $limit = 0;        
        
        if (!$limite_pedidos) {
            //si $limite_pedidos vale 0 significa que queremos todos los pedidos, luego no hay paginación ni offset, se pone página actual a 1             
            $pagina_actual = 1;

        } else if (!$sentido_paginacion) {
            //si $sentido_paginacion está vacío, se muestran los pedidos que corresponden al límite y página actual
            $offset = $limite_pedidos*($pagina_actual - 1);
            $limite_y_offset = " LIMIT $limite_pedidos OFFSET $offset";
        } else if ($sentido_paginacion) {
            //si $sentido_paginacion contiene una petición trabajamos con ella. Puede ser una página a izquierda (pagination_left) o a derecha (pagination_right) o primera página (pagination_left_left) o última página (pagination_right_right)
            if ($sentido_paginacion == 'pagination_left') {
                //formamos la variable limite_y_offset con el limite y la página actual menos uno por pagination_left
                $offset = $limite_pedidos*($pagina_actual - 2);
                $limite_y_offset = " LIMIT $limite_pedidos OFFSET $offset";
                $pagina_actual--;

            } else if ($sentido_paginacion == 'pagination_right') {
                //formamos la variable limite_y_offset con el limite y la página actual más uno por pagination_right
                $offset = $limite_pedidos*$pagina_actual;
                $limite_y_offset = " LIMIT $limite_pedidos OFFSET $offset";
                $pagina_actual++;
                
            } else if ($sentido_paginacion == 'pagination_left_left') {
                //formamos la variable limite_y_offset con el limite, sin offset al pedir la primera página
                $offset = 0;
                $limite_y_offset = " LIMIT $limite_pedidos OFFSET $offset";
                $pagina_actual = 1;
                
            } else if ($sentido_paginacion == 'pagination_right_right') {
                //usamos limite, pero para el offset tenemos que calcularlo en función del total de pedidos. Hay que calcular cual es la última página y cuantos pedidos la componen teniendo en cuenta el límite. Pej, limite 10 y hay 27 pedidos, hay que mostrar la página 3 que tendrá 7 pedidos.
                //el número de páginas se saca dividiendo el total de pedidos entre el límite redondeando arriba
                // 27/10= 2.7 => 3
                $pagina_actual = ceil($total_pedidos/$limite_pedidos);
                //el offset será el número de pedidos en las páginas anteriores a la última, es decir, (pagina_actual - 1)*limite_pedidos
                $offset = ($pagina_actual - 1)*$limite_pedidos;
                $limite_y_offset = " LIMIT $limite_pedidos OFFSET $offset";
                
            }
            
        }
                
        //obtenemos el token de AdminOrders para crear el enlace al pedido en backoffice
        $id_employee = Context::getContext()->employee->id;
        $tab = 'AdminOrders';
        $token_adminorders = Tools::getAdminToken($tab . (int) Tab::getIdFromClassName($tab) . (int) $id_employee);
        
        $url_base = Tools::getHttpHost(true).__PS_BASE_URI__;
        // index.php?controller=AdminOrders&id_order=362263&vieworder&token=b192540700c383eeb6b26f1da43998da
        $url_order_back = $url_base.'lfadminia/index.php?controller=AdminOrders&vieworder&token='.$token_adminorders.'&id_order=';         

        //lista pedidos
        $sql_pedidos = 'SELECT dro.id_dropshipping AS id_dropshipping, dro.id_order AS id_order, dro.id_supplier AS id_supplier, dro.supplier_name AS supplier_name, dro.procesado AS procesado, dro.finalizado AS finalizado, 
        dro.cancelado AS cancelado, dro.error AS error, dro.date_add AS date_add, dra.envio_almacen AS envio_almacen,
        CONCAT( "'.$url_order_back.'", dro.id_order) AS url_pedido
        FROM lafrips_dropshipping dro
        JOIN lafrips_dropshipping_address dra ON dra.id_dropshipping_address = dro.id_dropshipping_address AND dra.id_order = dro.id_order
        WHERE 1 '.
        $where_id.
        $where_proveedor.
        $where_estado.
        $where_fecha.        
        $order_by.
        $limite_y_offset;       
        
        if ($pedidos = Db::getInstance()->executeS($sql_pedidos)) {
            //por cada pedido buscamos su referencia de proveedor, el id que nos pasa (pej. ZZ682427 para Disfrazzes) y lo metemos con la info que devolvemos al front
            foreach ($pedidos AS &$pedido) { // & delante es lo que hace que se pueda añadir a $pedido que toca en el foreach
                $referencia_pedido = $this->referenciaPedido($pedido['id_supplier'], $pedido['id_dropshipping']);

                $pedido['referencia_pedido'] = $referencia_pedido;
            }
            //devolvemos la lista 
            die(Tools::jsonEncode(array('message'=>'Lista de pedidos obtenida correctamente', 'info_pedidos' => $pedidos, 'total_pedidos' => $total_pedidos, 'pagina_actual' => $pagina_actual)));
        } else { 
            //error al sacar los pedidos           
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'No hay pedidos o Error obteniendo lista de pedidos')));
        }      
    }

    //función que devuelve la referencia del pedido para el proveedor, recibiendo id de proveedor e id dropshipping.
    public function referenciaPedido($id_supplier, $id_dropshipping) {
        $referencia_pedido = '';

        switch ($id_supplier) {
            case 161: //disfrazzes tabla lafrips_dropshipping_disfrazzes. 
                $sql_referencia_pedido_disfrazzes = 'SELECT disfrazzes_reference
                FROM lafrips_dropshipping_disfrazzes
                WHERE id_dropshipping = '.$id_dropshipping;

                $referencia_pedido = Db::getInstance()->getValue($sql_referencia_pedido_disfrazzes);

                break;

            case 160: //DMI 
                $sql_referencia_pedido_dmi = 'SELECT ws_respuesta
                FROM lafrips_dropshipping_dmi
                WHERE id_dropshipping = '.$id_dropshipping;

                $referencia_pedido = Db::getInstance()->getValue($sql_referencia_pedido_dmi);
                
                break;

            case 156: //Globomatik 
                $sql_referencia_pedido_globomatik = 'SELECT globomatik_order_reference
                FROM lafrips_dropshipping_globomatik
                WHERE id_dropshipping = '.$id_dropshipping;

                $referencia_pedido = Db::getInstance()->getValue($sql_referencia_pedido_globomatik);
                
                break;

            case 159: //Mars Gaming sin configurar
                
                break;

            case 163: //Printful sin configurar
            
                break;

            default:
                //es un error, salimos
                               
        }

        if (!$referencia_pedido) {
            $referencia_pedido = 'No disponible';
        }

        return $referencia_pedido;
    }

    public function ajaxProcessVerPedido() {
        $response = true;

        //recogemos el id del pedido en la tabla lafrips_dropshipping que viene via ajax  
        $id_dropshipping = Tools::getValue('id_dropshipping',0);
                
        if (!$id_dropshipping) {
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'Se produjo un error al solicitar la información del pedido.')));
        }     

        //obtenemos el id_supplier con id_dropshipping
        $sql_id_supplier = 'SELECT id_supplier
        FROM lafrips_dropshipping 
        WHERE id_dropshipping = '.$id_dropshipping;

        $id_supplier = Db::getInstance()->getValue($sql_id_supplier);  

        //ahora, dependiendo del proveedor se accede a la información en una tabla u otra. Para los que aún no tenemos la API configurada o no tienen llamamos a una función genérica que saca datos genéricos de los productos para mostrar
        switch ($id_supplier) {
            case 161: //disfrazzes tabla lafrips_dropshipping_disfrazzes - productos. 
                if (!$info_pedido = $this->getPedidoDisfrazzes($id_dropshipping)) {
                    $response = false;
                }

                break;

            case 160: //DMI 
                if (!$info_pedido = $this->getPedidoDmi($id_dropshipping)) {
                    $response = false;
                }

                break;

            case 156: //Globomatik tabla lafrips_dropshipping_globomatik - productos. 
                if (!$info_pedido = $this->getPedidoGlobomatik($id_dropshipping)) {
                    $response = false;
                }

                break;

            case 159: //Mars Gaming sin configurar
                if (!$info_pedido = $this->getPedidoProveedorSinGestion($id_dropshipping)) {
                    $response = false;
                }

                break;

            case 163: //Printful sin configurar
                if (!$info_pedido = $this->getPedidoProveedorSinGestion($id_dropshipping)) {
                    $response = false;
                }

                break;

            default:
                //es un error, salimos
                $response = false;                
        }

        if ($response) {
            //devolvemos la petición
            die(Tools::jsonEncode(array('info_pedido' => $info_pedido, 'id_supplier' => $id_supplier)));
        } else { 
            //error al sacar petición           
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'No se encontraron datos para este pedido')));
        }          
    }

    //función que obtiene la información de un pedido de Disfrazzes para mostrar en el lateral en el controlador
    public function getPedidoDisfrazzes($id_dropshipping) {
        $info_pedido = array();

        //primero los datos de una posible respuesta de la API
        $sql_info_pedido_disfrazzes = 'SELECT id_dropshipping_disfrazzes, id_order, response_result, response_delivery_date, response_msg, disfrazzes_id, disfrazzes_reference, status_id, status_name, transportista, date_expedicion, tracking, url_tracking, cancelado, error
        FROM lafrips_dropshipping_disfrazzes
        WHERE id_dropshipping = '.$id_dropshipping;

        $info_pedido_disfrazzes = Db::getInstance()->executeS($sql_info_pedido_disfrazzes);
       
        $info_dropshipping = array();

        $info_dropshipping['id_order'] = $info_pedido_disfrazzes[0]['id_order'];
        $info_dropshipping['response_result'] = $info_pedido_disfrazzes[0]['response_result'];
        $info_dropshipping['response_delivery_date'] = $info_pedido_disfrazzes[0]['response_delivery_date'];
        $info_dropshipping['response_msg'] = $info_pedido_disfrazzes[0]['response_msg'];
        $info_dropshipping['disfrazzes_id'] = $info_pedido_disfrazzes[0]['disfrazzes_id'];
        $info_dropshipping['disfrazzes_reference'] = $info_pedido_disfrazzes[0]['disfrazzes_reference'];
        $info_dropshipping['status_id'] = $info_pedido_disfrazzes[0]['status_id'];
        $info_dropshipping['status_name'] = $info_pedido_disfrazzes[0]['status_name'];
        $info_dropshipping['transportista'] = $info_pedido_disfrazzes[0]['transportista'];                
        $info_dropshipping['date_expedicion'] = $info_pedido_disfrazzes[0]['date_expedicion'];
        $info_dropshipping['tracking'] = $info_pedido_disfrazzes[0]['tracking'];
        $info_dropshipping['url_tracking'] = $info_pedido_disfrazzes[0]['url_tracking'];
        $info_dropshipping['cancelado'] = $info_pedido_disfrazzes[0]['cancelado'];
        $info_dropshipping['error'] = $info_pedido_disfrazzes[0]['error'];
        $info_dropshipping['id_supplier'] = 161;  
        $info_dropshipping['id_dropshipping'] = $id_dropshipping;  

        //obtenemos el estado actual del pedido en prestashop
        $order = new Order($info_pedido_disfrazzes[0]['id_order']);
        $order_state = $order->getCurrentStateFull(1);
        $info_dropshipping['estado_prestashop'] = $order_state['name'];
        $info_dropshipping['supplier_name'] = 'Disfrazzes';

        $info_pedido['dropshipping'] = $info_dropshipping;

        //ahora los datos de productos
        $sql_info_productos = 'SELECT id_product, product_name, product_reference, product_supplier_reference,
        product_quantity, variant_result, variant_msg, variant_quantity_accepted
        FROM lafrips_dropshipping_disfrazzes_productos
        WHERE eliminado = 0 AND id_dropshipping_disfrazzes = '.$info_pedido_disfrazzes[0]['id_dropshipping_disfrazzes'];

        $info_productos = Db::getInstance()->executeS($sql_info_productos); 
        
        foreach ($info_productos AS $info_producto) {  
            $producto = array();

            //sacamos imagen de producto
            $product = new Product((int)$info_producto['id_product'], false, 1, 1);
            $image = Image::getCover((int)$info_producto['id_product']);			
            $image_link = new Link;//because getImageLInk is not static function
            $image_path = $image_link->getImageLink($product->link_rewrite, $image['id_image'], 'home_default');

            $producto['id_product'] = $info_producto['id_product'];                    
            $producto['image_path'] = $image_path;
            $producto['product_name'] = $info_producto['product_name'];
            $producto['product_reference'] = $info_producto['product_reference'];
            $producto['product_supplier_reference'] = $info_producto['product_supplier_reference'];
            $producto['product_quantity'] = $info_producto['product_quantity'];                    
            $producto['variant_result'] = $info_producto['variant_result'];
            $producto['variant_msg'] = $info_producto['variant_msg'];
            $producto['variant_quantity_accepted'] = $info_producto['variant_quantity_accepted'];                    

            $info_pedido['productos'][] = $producto;
        }    
        
        if ($info_pedido) {
            return $info_pedido;
        }        

        return false;
    }

    //función que obtiene la información de un pedido de Globomatik para mostrar en el lateral en el controlador
    public function getPedidoGlobomatik($id_dropshipping) {
        $info_pedido = array();

        //primero los datos de una posible respuesta de la API
        $sql_info_pedido_globomatik = 'SELECT id_dropshipping_globomatik, id_order, globomatik_order_reference, status_id, status_txt, shipping_cost, cod_transporte, tracking, url_tracking, cancelado, error
        FROM lafrips_dropshipping_globomatik
        WHERE id_dropshipping = '.$id_dropshipping;

        $info_pedido_globomatik = Db::getInstance()->executeS($sql_info_pedido_globomatik);
       
        $info_dropshipping = array();

        $info_dropshipping['id_order'] = $info_pedido_globomatik[0]['id_order'];
        $info_dropshipping['globomatik_order_reference'] = $info_pedido_globomatik[0]['globomatik_order_reference'];
        $info_dropshipping['status_id'] = $info_pedido_globomatik[0]['status_id'];
        $info_dropshipping['status_txt'] = $info_pedido_globomatik[0]['status_txt'];
        $info_dropshipping['shipping_cost'] = $info_pedido_globomatik[0]['shipping_cost'];
        $info_dropshipping['cod_transporte'] = $info_pedido_globomatik[0]['cod_transporte'];
        $info_dropshipping['tracking'] = $info_pedido_globomatik[0]['tracking'];
        $info_dropshipping['url_tracking'] = $info_pedido_globomatik[0]['url_tracking'];
        $info_dropshipping['cancelado'] = $info_pedido_globomatik[0]['cancelado'];                
        $info_dropshipping['error'] = $info_pedido_globomatik[0]['error'];   
        $info_dropshipping['id_supplier'] = 156;  
        $info_dropshipping['id_dropshipping'] = $id_dropshipping;             

        //obtenemos el estado actual del pedido en prestashop
        $order = new Order($info_pedido_globomatik[0]['id_order']);
        $order_state = $order->getCurrentStateFull(1);
        $info_dropshipping['estado_prestashop'] = $order_state['name'];
        $info_dropshipping['supplier_name'] = 'Globomatik';

        $info_pedido['dropshipping'] = $info_dropshipping;

        //ahora los datos de productos
        $sql_info_productos = 'SELECT id_product, product_name, product_reference, globomatik_sku,
        product_quantity, price, canon
        FROM lafrips_dropshipping_globomatik_productos
        WHERE eliminado = 0 AND id_dropshipping_globomatik = '.$info_pedido_globomatik[0]['id_dropshipping_globomatik'];

        $info_productos = Db::getInstance()->executeS($sql_info_productos); 
        
        foreach ($info_productos AS $info_producto) {  
            $producto = array();

            //sacamos imagen de producto
            $product = new Product((int)$info_producto['id_product'], false, 1, 1);
            $image = Image::getCover((int)$info_producto['id_product']);			
            $image_link = new Link;//because getImageLInk is not static function
            $image_path = $image_link->getImageLink($product->link_rewrite, $image['id_image'], 'home_default');

            $producto['id_product'] = $info_producto['id_product'];                    
            $producto['image_path'] = $image_path;
            $producto['product_name'] = $info_producto['product_name'];
            $producto['product_reference'] = $info_producto['product_reference'];
            $producto['product_supplier_reference'] = $info_producto['globomatik_sku'];
            $producto['product_quantity'] = $info_producto['product_quantity'];                    
            $producto['price'] = $info_producto['price'];
            $producto['canon'] = $info_producto['canon'];                                      

            $info_pedido['productos'][] = $producto;
        }      

        
        if ($info_pedido) {
            return $info_pedido;
        }        

        return false;
    }

    //función que obtiene la información de un pedido de Dmi para mostrar en el lateral en el controlador
    public function getPedidoDmi($id_dropshipping) {
        $info_pedido = array();

        //primero los datos de una posible respuesta de la API
        $sql_info_pedido_dmi = 'SELECT id_dropshipping_dmi, id_order, ws_respuesta, estado, num_factura, fecha_factura, url_tracking, transportista, expedicion_dmi, cancelado, error
        FROM lafrips_dropshipping_dmi
        WHERE id_dropshipping = '.$id_dropshipping;

        $info_pedido_dmi = Db::getInstance()->executeS($sql_info_pedido_dmi);
       
        $info_dropshipping = array();

        $info_dropshipping['id_order'] = $info_pedido_dmi[0]['id_order'];
        $info_dropshipping['ws_respuesta'] = $info_pedido_dmi[0]['ws_respuesta'];
        $info_dropshipping['estado'] = $info_pedido_dmi[0]['estado']; 
        $info_dropshipping['num_factura'] = $info_pedido_dmi[0]['num_factura'];
        $info_dropshipping['fecha_factura'] = $info_pedido_dmi[0]['fecha_factura'];
        $info_dropshipping['url_tracking'] = $info_pedido_dmi[0]['url_tracking'];
        $info_dropshipping['transportista'] = $info_pedido_dmi[0]['transportista'];
        $info_dropshipping['expedicion_dmi'] = $info_pedido_dmi[0]['expedicion_dmi'];       
        $info_dropshipping['cancelado'] = $info_pedido_dmi[0]['cancelado'];                
        $info_dropshipping['error'] = $info_pedido_dmi[0]['error'];   
        $info_dropshipping['id_supplier'] = 160;  
        $info_dropshipping['id_dropshipping'] = $id_dropshipping;             

        //obtenemos el estado actual del pedido en prestashop
        $order = new Order($info_pedido_dmi[0]['id_order']);
        $order_state = $order->getCurrentStateFull(1);
        $info_dropshipping['estado_prestashop'] = $order_state['name'];
        $info_dropshipping['supplier_name'] = 'Dmi';

        $info_pedido['dropshipping'] = $info_dropshipping;

        //ahora los datos de productos
        $sql_info_productos = 'SELECT id_product, product_name, product_reference, dmi_sku,
        product_quantity, price
        FROM lafrips_dropshipping_dmi_productos
        WHERE eliminado = 0 AND id_dropshipping_dmi = '.$info_pedido_dmi[0]['id_dropshipping_dmi'];

        $info_productos = Db::getInstance()->executeS($sql_info_productos); 
        
        foreach ($info_productos AS $info_producto) {  
            $producto = array();

            //sacamos imagen de producto
            $product = new Product((int)$info_producto['id_product'], false, 1, 1);
            $image = Image::getCover((int)$info_producto['id_product']);			
            $image_link = new Link;//because getImageLInk is not static function
            $image_path = $image_link->getImageLink($product->link_rewrite, $image['id_image'], 'home_default');

            $producto['id_product'] = $info_producto['id_product'];                    
            $producto['image_path'] = $image_path;
            $producto['product_name'] = $info_producto['product_name'];
            $producto['product_reference'] = $info_producto['product_reference'];
            $producto['product_supplier_reference'] = $info_producto['dmi_sku'];
            $producto['product_quantity'] = $info_producto['product_quantity'];                    
            $producto['price'] = $info_producto['price'];                                               

            $info_pedido['productos'][] = $producto;
        }      

        
        if ($info_pedido) {
            return $info_pedido;
        }        

        return false;
    }

    //función que obtiene la información de un pedido de los proveedores dropshipping que todavía no tenemos configurados con las apis o no tienen api (Mars gaming) para mostrar en el lateral en el controlador
    //01/08/2022 - Añadido Printful
    //05/09/2022 Recogemos también información sobre si está finalizado o cancelado manualmente, posibilidad que hemos añadido al back del pedido de prestashop para que los pedidos dropshipping sin gestión puedan ser marcados como finalizados para no quedar siempre como Aceptados.
    public function getPedidoProveedorSinGestion($id_dropshipping) {
        $info_pedido = array();

        //primero los datos del pedido, incluido si está marcado como finalizado o cancelado, y el id y fecha del empleado que lo hizo
        $sql_info_pedido_sin_gestion = 'SELECT id_order, id_supplier, supplier_name, finalizado, id_employee_finalizado, date_finalizado, cancelado, id_employee_cancelado, date_cancelado
        FROM lafrips_dropshipping
        WHERE id_dropshipping = '.$id_dropshipping;

        $info_pedido_sin_gestion = Db::getInstance()->getRow($sql_info_pedido_sin_gestion);
       
        $info_dropshipping = array();

        $info_dropshipping['id_order'] = $info_pedido_sin_gestion['id_order'];          
        $info_dropshipping['id_supplier'] = $info_pedido_sin_gestion['id_supplier'];      
        $info_dropshipping['supplier_name'] = $info_pedido_sin_gestion['supplier_name']; 
        
        //el pedido sin gestión puede no estar ni finalizado ni cancelado, o estar cancelado o estar finalizado (pero no ambas o sería un error)
        $info_dropshipping['empleado'] = '';
        $info_dropshipping['fecha_gestion'] = '';
        $info_dropshipping['finalizado'] = 0;
        $info_dropshipping['cancelado'] = 0;
        $info_dropshipping['error'] = 0;

        $finalizado = $info_pedido_sin_gestion['finalizado']; 
        $cancelado = $info_pedido_sin_gestion['cancelado']; 
        if ($finalizado && $cancelado) {
            //error, enviamos al front un mensaje de error junto a los datos del pedido
            $info_dropshipping['error'] = 1;
            $info_dropshipping['mensaje_estado'] = '<b>Error con el estado de dropshipping del pedido</b>';
        } elseif ($finalizado) {
            //pedido finalizado, obtenemos empleado si es manualmente
            $info_dropshipping['finalizado'] = 1;
            $info_dropshipping['mensaje_estado'] = '<b>Pedido marcado como Finalizado manualmente</b>';

            if ($info_pedido_sin_gestion['id_employee_finalizado']) {
                $sql_nombre_empleado = 'SELECT CONCAT(firstname," ",lastname) FROM lafrips_employee WHERE id_employee = '.$info_pedido_sin_gestion['id_employee_finalizado'];
                $info_dropshipping['empleado'] = Db::getInstance()->getValue($sql_nombre_empleado);

                $info_dropshipping['fecha_gestion'] = $info_pedido_sin_gestion['date_finalizado'];
            }
            
        } elseif ($cancelado) {
            //pedido cancelado, obtenemos empleado si es manualmente
            $info_dropshipping['cancelado'] = 1;
            $info_dropshipping['mensaje_estado'] = '<b>Pedido marcado como Cancelado manualmente</b>';

            if ($info_pedido_sin_gestion['id_employee_cancelado']) {
                $sql_nombre_empleado = 'SELECT CONCAT(firstname," ",lastname) FROM lafrips_employee WHERE id_employee = '.$info_pedido_sin_gestion['id_employee_cancelado'];
                $info_dropshipping['empleado'] = Db::getInstance()->getValue($sql_nombre_empleado);

                $info_dropshipping['fecha_gestion'] = $info_pedido_sin_gestion['date_cancelado'];
            }

        } else {
            //aún no se ha gestionado nada
            $info_dropshipping['mensaje_estado'] = 'No gestionado con proveedor, puedes modificar el estado de dropshipping del pedido desde el backoffice de Prestashop';
        }

        //obtenemos el estado actual del pedido en prestashop
        $order = new Order($info_pedido_sin_gestion['id_order']);
        $order_state = $order->getCurrentStateFull(1);
        $info_dropshipping['estado_prestashop'] = $order_state['name'];

        $info_pedido['dropshipping'] = $info_dropshipping;

        //ahora los datos de productos. Como no tenemos tabla dropshipping del proveedor, los sacamos de productos vendidos sin stock
        $sql_info_productos = 'SELECT id_product, product_name, product_reference, product_supplier_reference,
        product_quantity
        FROM lafrips_productos_vendidos_sin_stock
        WHERE eliminado = 0 
        AND dropshipping = 1
        AND id_order_detail_supplier = '.$info_pedido_sin_gestion['id_supplier'].'
        AND id_order = '.$info_pedido_sin_gestion['id_order'];

        $info_productos = Db::getInstance()->executeS($sql_info_productos); 
        
        foreach ($info_productos AS $info_producto) {  
            $producto = array();

            //sacamos imagen de producto
            $product = new Product((int)$info_producto['id_product'], false, 1, 1);
            $image = Image::getCover((int)$info_producto['id_product']);			
            $image_link = new Link;//because getImageLInk is not static function
            $image_path = $image_link->getImageLink($product->link_rewrite, $image['id_image'], 'home_default');

            $producto['id_product'] = $info_producto['id_product'];                    
            $producto['image_path'] = $image_path;
            $producto['product_name'] = $info_producto['product_name'];
            $producto['product_reference'] = $info_producto['product_reference'];
            $producto['product_supplier_reference'] = $info_producto['product_supplier_reference'];
            $producto['product_quantity'] = $info_producto['product_quantity'];   

            $info_pedido['productos'][] = $producto;
        }      

        
        if ($info_pedido) {
            return $info_pedido;
        }        

        return false;
    }

    //función que recibe el id_dropshipping y solicita a la api el estado del pedido si corresponde
    public function ajaxProcessEstadoPedido() {
        $response = true;

        //recogemos el id del proveedor en la tabla lafrips_dropshipping que viene via ajax  
        $id_dropshipping = Tools::getValue('id_dropshipping',0);
                
        if (!$id_dropshipping) {
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'Se produjo un error al solicitar la información del estado de pedido.')));
        }     

        //obtenemos el id_supplier con id_dropshipping
        $sql_id_supplier = 'SELECT id_supplier
        FROM lafrips_dropshipping 
        WHERE id_dropshipping = '.$id_dropshipping;

        $id_supplier = Db::getInstance()->getValue($sql_id_supplier);  

        //ahora, dependiendo del proveedor se llama a una Api u otra para solictar el estado. Para los que aún no tenemos la API configurada o no tienen no se realiza llamada, pero no debería haber botón para solicitarlo
        switch ($id_supplier) {
            case 161: //disfrazzes
                //llamamos a la API de Disfrazzes para obtener estado de pedido, devolverá true o false según resultado
                //el id_dropshipping debe ir en un array, de modo que podemos reutilizzar la función para varios pedidos a la vez
                $id_dropshipping_array = array();
                $id_dropshipping_array[] = $id_dropshipping;

                include_once dirname(__FILE__).'/../../classes/Disfrazzes.php';                

                $response = Disfrazzes::apiDisfrazzesStatus($id_dropshipping_array);

                break;

            case 160: //DMI 
                //llamamos a la API de Dmi para obtener estado de pedido, devolverá true o false según resultado
                //el id_dropshipping es único, la api de Dmi solo admite una petición de status cada vez 
                
                include_once dirname(__FILE__).'/../../classes/Dmi.php';

                $response = Dmi::apiDmiStatus($id_dropshipping);

                break;

            case 156: //Globomatik  
                //llamamos a la API de Globomatik para obtener estado de pedido, devolverá true o false según resultado
                //el id_dropshipping es único, la api de Globomatik solo admite una petición de status cada vez                    

                include_once dirname(__FILE__).'/../../classes/Globomatik.php';

                $response = Globomatik::apiGlobomatikStatus($id_dropshipping);

                break;

            // case 159: //Mars Gaming sin configurar
            //     $response = false;
            
            //     break;

            // case 163: //Printful sin configurar
            //     $response = false;
            
            //     break;

            default:
                //es un error o no proveedor con api, salimos
                $response = false;                
        }

        if ($response) {
            //devolvemos la petición 'Solicitud de estado correcta'
            die(Tools::jsonEncode(array('message' => 'Solicitud de estado correcta')));
        } else { 
            //error al sacar petición           
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'No se pudo solicitar el estado para este pedido')));
        }          
    }


}
