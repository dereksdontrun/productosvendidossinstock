<?php
/**
 * Generador de pedidos de proveedor manuales a Cerdá
 *
 *  @author    Sergio™ <sergio@lafrikileria.com>
 *    
 */

if (!defined('_PS_VERSION_'))
    exit;

class AdminProductosPrepedidoController extends ModuleAdminController {
    
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
        $this->addJs($this->module->getPathUri().'views/js/back_productos_prepedido.js');
        //añadimos la dirección para el css
        $this->addCss($this->module->getPathUri().'views/css/back_productos_prepedido.css');
    }


    /**
     * AdminController::renderForm() override
     * @see AdminController::renderForm()
     */
    public function renderForm() {    

        //generamos el token de AdminProductosPrepedido ya que lo vamos a usar en el archivo de javascript . Lo almacenaremos en un input hidden para acceder a el desde js
        $token_admin_modulo = Tools::getAdminTokenLite('AdminProductosPrepedido');

        $this->fields_form = array(
            'legend' => array(
                'title' => 'Productos en Prepedido y productos vendidos en espera',
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

    /*
    * Función que busca los productos con categoría Prepedido, todos, y también los que solo tienen Permitir pedidos pero que están vendidos en pedidos en espera. La llamamos mediante javascript/ajax al cargar la vista del controlador 
    *
    */
    public function ajaxProcessListaPrepedidos(){    

        $response = true;

        //obtenemos el token de AdminProducts para crear el enlace al producto en backoffice
        $id_employee = Context::getContext()->employee->id;
        $tab = 'AdminProducts';
        $token_adminproducts = Tools::getAdminToken($tab . (int) Tab::getIdFromClassName($tab) . (int) $id_employee);
        
        $url_base = Tools::getHttpHost(true).__PS_BASE_URI__;
        // index.php?controller=AdminProducts&id_product=2765&updateproduct&token=1f1d270097f1d42ecc5dd1c6600dc3ac
        $url_product_back = $url_base.'lfadminia/index.php?controller=AdminProducts&updateproduct&token='.$token_adminproducts.'&id_product=';
                
        //lista completa de productos con categoría prepedido
        //aunque tengan atributos, solo se presenta el producto global
        //MODIFICAR test en url_imagen para producción CONCAT( "http://lafrikileria.com/test", "/"
        $sql_lista_prepedidos = 'SELECT  CONCAT(ava.id_product,"_",ava.id_product_attribute) AS el_producto,
        ava.id_product AS id_product, ava.id_product_attribute AS id_product_attribute, pro.reference AS referencia, pro.ean13 AS ean13, pla.name AS nombre, cla.name AS categoria,
        "Si" AS cat_prepedido_ahora,
        #si el id_product aparece > 1 en tabla atributos, es producto con atributos, enviamos valor 1
        IF ((SELECT COUNT(id_product) FROM lafrips_product_attribute WHERE id_product = ava.id_product) > 0,1,0) AS con_atributos,
        CASE
        WHEN (SELECT COUNT(id_product) FROM lafrips_product_attribute WHERE id_product = ava.id_product) > 0 THEN 
        (
        #si tiene atributos, comprobamos que todas las fechas sean iguales, y sacamos la que haya, si no mensaje error
            CASE
            WHEN (SELECT COUNT(DISTINCT available_date) FROM lafrips_product_attribute WHERE id_product = ava.id_product) > 1 THEN "Fechas diferentes"
            ELSE (SELECT DISTINCT available_date FROM lafrips_product_attribute WHERE id_product = ava.id_product)
            END
        )
        ELSE pro.available_date  
        END
        AS disponibilidad,
        ava.out_of_stock AS permite_ahora, pla.available_later AS mensaje,
        IFNULL((SELECT SUM(pvs.product_quantity)
        FROM lafrips_productos_vendidos_sin_stock pvs
        LEFT JOIN lafrips_order_detail ode ON ode.product_id = pvs.id_product AND ode.id_order = pvs.id_order  AND ode.product_attribute_id = pvs.id_product_attribute
        JOIN lafrips_orders ord ON ord.id_order = pvs.id_order 
            AND ord.valid = 1 AND ord.current_state IN (2, 3, 9, 17, 20, 26, 28, 41, 42, 43, 55, 56, 57, 58, 59)
        WHERE pvs.id_product = ava.id_product AND pvs.eliminado = 0), 0)
        AS unidades_espera,
        IFNULL((SELECT SUM(ode.product_quantity) FROM lafrips_order_detail ode JOIN lafrips_orders ord ON ord.id_order = ode.id_order 	
            WHERE ord.valid = 1
            AND ode.product_id = ava.id_product), 0)
        AS venta_total,
        CASE
        WHEN (SELECT COUNT(id_product) FROM lafrips_product_attribute WHERE id_product = ava.id_product) > 0 THEN 1
        ELSE psu.product_supplier_reference 
        END
        AS ref_proveedor, 
        pro.id_supplier AS id_proveedor, sup.name AS proveedor, ava.quantity AS online_mas_tienda,
        IFNULL((SELECT SUM(physical_quantity) FROM lafrips_stock WHERE id_product = ava.id_product AND id_warehouse = 1), 0) AS stock_online,
        IFNULL((SELECT SUM(physical_quantity) FROM lafrips_stock WHERE id_product = ava.id_product AND id_warehouse = 4), 0) AS stock_tienda,
        ima.id_image AS id_imagen, 
        CONCAT( "http://lafrikileria.com", "/", ima.id_image, "-home_default/", pla.link_rewrite, ".", "jpg") AS url_imagen,
        CONCAT( "'.$url_product_back.'", ava.id_product) AS url_producto
        FROM lafrips_stock_available ava
        LEFT JOIN lafrips_product pro ON pro.id_product = ava.id_product
        LEFT JOIN lafrips_product_lang pla ON pla.id_product = ava.id_product AND pla.id_lang = 1
        LEFT JOIN lafrips_supplier sup ON sup.id_supplier = pro.id_supplier
        LEFT JOIN lafrips_product_supplier psu ON psu.id_product = ava.id_product AND psu.id_supplier = pro.id_supplier
        JOIN lafrips_category_product cap ON cap.id_product = ava.id_product AND cap.id_category = 121
        JOIN lafrips_image ima ON ima.id_product = ava.id_product AND ima.cover = 1
        LEFT JOIN lafrips_category_lang cla ON pro.id_category_default = cla.id_category AND cla.id_lang = 1
        WHERE ava.id_product_attribute = 0
        AND psu.id_product_attribute = 0
        ORDER BY unidades_espera DESC, id_product ASC';

        if ($lista_prepedidos = Db::getInstance()->executeS($sql_lista_prepedidos)) {
            //devolvemos la lista 
            die(Tools::jsonEncode(array('message'=>'Lista de productos obtenida correctamente', 'info_productos' => $lista_prepedidos)));
        } else { 
            //error al sacar los productos           
            die(Tools::jsonEncode(array('message'=>'Error obteniendo lista de productos', 'info_productos' => $lista_prepedidos)));
        }      

        // if($response)
        //     die(Tools::jsonEncode(array('message'=>'Referencia encontrada en Prestashop', 'info_producto' => $info_producto)));
    }


    /*
    * Función que busca los productos con o sin categoría Prepedido, que están vendidos sin stock en pedidos en espera. La llamamos mediante javascript/ajax al cargar la vista del controlador 
    *
    */
    public function ajaxProcessListaEnEspera(){    

        $response = true;    
        
        //obtenemos el token de AdminProducts para crear el enlace al producto en backoffice
        $id_employee = Context::getContext()->employee->id;
        $tab = 'AdminProducts';
        $token_adminproducts = Tools::getAdminToken($tab . (int) Tab::getIdFromClassName($tab) . (int) $id_employee);
        
        $url_base = Tools::getHttpHost(true).__PS_BASE_URI__;
        // index.php?controller=AdminProducts&id_product=2765&updateproduct&token=1f1d270097f1d42ecc5dd1c6600dc3ac
        $url_product_back = $url_base.'lfadminia/index.php?controller=AdminProducts&updateproduct&token='.$token_adminproducts.'&id_product=';
        
        //lista completa de productos vendidos sin stock en pedidos en espera        
        //MODIFICAR test en url_imagen para producción CONCAT( "http://lafrikileria.com/test", "/"
        $sql_lista_en_espera = 'SELECT  CONCAT(pvs.id_product,"_",pvs.id_product_attribute) AS el_producto,
        pvs.id_product AS id_product, pvs.id_product_attribute AS id_product_attribute, pvs.product_reference AS referencia, 
        IFNULL(pat.ean13, pro.ean13) AS ean13, pvs.product_name AS nombre,
        cla.name AS categoria, 
        CASE
        WHEN (SELECT id_category FROM lafrips_category_product WHERE id_category = 121 AND id_product = pvs.id_product) THEN "Si"
        ELSE "No"
        END
        AS cat_prepedido_ahora,
        #si el id_product aparece > 1 en tabla atributos, es producto con atributos, enviamos valor 1
        IF ((SELECT COUNT(id_product) FROM lafrips_product_attribute WHERE id_product = pvs.id_product) > 0,1,0) AS con_atributos,
        IFNULL(pat.available_date, pro.available_date) AS disponibilidad,
        ava.out_of_stock AS permite_ahora, pla.available_later AS mensaje, SUM(pvs.product_quantity) AS unidades_espera, 
        (SELECT SUM(ode.product_quantity) FROM lafrips_order_detail ode JOIN lafrips_orders ord ON ord.id_order = ode.id_order             
            WHERE ord.valid = 1
            AND ode.product_id = pvs.id_product
            AND ode.product_attribute_id = pvs.id_product_attribute)
        AS venta_total,
        pvs.product_supplier_reference AS ref_proveedor, pro.id_supplier AS id_proveedor, sup.name AS proveedor,
        ava.quantity AS online_mas_tienda,
        IFNULL((SELECT SUM(physical_quantity) FROM lafrips_stock WHERE id_product = pvs.id_product AND id_product_attribute = pvs.id_product_attribute AND id_warehouse = 1), 0) AS stock_online,
        IFNULL((SELECT SUM(physical_quantity) FROM lafrips_stock WHERE id_product = pvs.id_product AND id_product_attribute = pvs.id_product_attribute AND id_warehouse = 4), 0) AS stock_tienda,
        ima.id_image AS id_imagen, 
        CONCAT( "http://lafrikileria.com", "/", ima.id_image, "-home_default/", pla.link_rewrite, ".", "jpg") AS url_imagen,
        CONCAT( "'.$url_product_back.'", pvs.id_product) AS url_producto
        FROM lafrips_productos_vendidos_sin_stock pvs
        LEFT JOIN lafrips_supplier sup ON sup.id_supplier = pvs.id_default_supplier
        LEFT JOIN lafrips_product_attribute pat ON pat.id_product = pvs.id_product AND pat.id_product_attribute = pvs.id_product_attribute
        LEFT JOIN lafrips_order_detail ode ON ode.product_id = pvs.id_product AND ode.product_attribute_id = pvs.id_product_attribute AND ode.id_order = pvs.id_order
        LEFT JOIN lafrips_product pro ON pro.id_product = pvs.id_product
        LEFT JOIN lafrips_stock_available ava ON ava.id_product = pvs.id_product AND ava.id_product_attribute = pvs.id_product_attribute
        LEFT JOIN lafrips_category_lang cla ON pro.id_category_default = cla.id_category AND cla.id_lang = 1
        #sacamos los que order es válido y que esteén en estado no enviado (Esperando productos, Sin stock pagado, Pago Aceptado, etc)
        JOIN lafrips_orders ord ON ord.id_order = pvs.id_order 
            AND ord.valid = 1 AND ord.current_state IN (2, 3, 9, 17, 20, 26, 28, 41, 42, 43, 55, 56, 57, 58, 59) 
        JOIN lafrips_image ima ON ima.id_product = pvs.id_product AND ima.cover = 1
        JOIN lafrips_product_lang pla ON pla.id_product = pvs.id_product AND pla.id_lang = 1
        #LEFT JOIN lafrips_category_product cap ON cap.id_product = pro.id_product AND cap.id_category = 121        
        WHERE pvs.eliminado = 0
        GROUP BY pvs.id_product, pvs.id_product_attribute, pvs.product_supplier_reference
        ORDER BY unidades_espera DESC, id_product ASC';

        if ($lista_en_espera = Db::getInstance()->executeS($sql_lista_en_espera)) {
            //devolvemos la lista 
            die(Tools::jsonEncode(array('message'=>'Lista de productos obtenida correctamente', 'info_productos' => $lista_en_espera)));
        } else { 
            //error al sacar los productos           
            die(Tools::jsonEncode(array('message'=>'Error obteniendo lista de productos', 'info_productos' => $lista_en_espera)));
        }      

        // if($response)
        //     die(Tools::jsonEncode(array('message'=>'Referencia encontrada en Prestashop', 'info_producto' => $info_producto)));
    }

    /*
    * Función que almacena la nueva fecha de disponibilidad en el campo available_date, dependiendo de si el producto tiene o no atributos
    *
    */
    public function ajaxProcessFechaDisponibilidad(){        
        //asignamos los datos que vienen via ajax  
        $id_product = trim(Tools::getValue('id_product',0));
        $fecha = trim(Tools::getValue('fecha',0));
        
        if(!$id_product) {
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'Se produjo un error con el producto.')));
        }        

        $response = true;

        //comprobamos si el producto tiene atributos, si tiene se pone por defecto la misma fecha a todas las combinaciones, si no se le pone a producto la fecha dada.
        if ($atributos = Product::getProductAttributesIds($id_product)) {
            //tiene atributos
            foreach ($atributos AS $atributo) {
                $combination = new Combination($atributo['id_product_attribute']); //para cambiar fecha en un atributo hay que instanciar la combinación con id_product_attribute y meter la fecha, después hacer update(). Con getProductAttributesIds se obtiene un array de arrays. Cada array interior contiene los id de atributos. Son array porque una combinación podría tener varios atributos (talla - color)   
                $combination->available_date = $fecha;
                if (!$combination->update()) {
                    $response = false;
                    $mensaje_error = 'Error al actualizar fecha de combinación';
                }               
            }
        } else {
            //no tiene atributos
            $producto = new Product($id_product);
            if (!$producto->setAvailableDate($fecha)) {
                $response = false;
                $mensaje_error = 'Error al actualizar la fecha del producto';
            }
        }          
        
        if($response) {
            $id_empleado = Context::getContext()->employee->id;
            $nombre_empleado = Context::getContext()->employee->firstname.' '.Context::getContext()->employee->lastname;
            $product = new Product($id_product);
            $nombre_producto = (is_array($product->name) ? $product->name[1] : $product->name);
            $referencia_producto = $product->reference;

            //introducimos log
            $insert_log = "INSERT INTO frik_log_productos_vendidos_sin_stock 
                (id_proceso, proceso, id_product, available_date, product_name, product_reference, id_employee, empleado, date_add) 
                VALUES (1 ,
                'fecha_disponibilidad_producto' ,
                ".$id_product." ,                              
                '".$fecha."',
                '".$nombre_producto."',
                '".$referencia_producto."',
                ".$id_empleado." ,
                '".$nombre_empleado."' ,                 
                NOW())";

            Db::getInstance()->Execute($insert_log);

            die(Tools::jsonEncode(array('message'=>'Producto actualizado', 'resultado' => 'Nueva fecha de disponibilidad guardada')));
        } else {
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'Error al modificar la disponibilidad - '.$mensaje_error)));
        }
    }


    /*
    * Función que almacena el nuevo mensaje de disponibilidad en el campo available_later de la tabla product_lang, para español
    *
    */
    public function ajaxProcessMensajeDisponibilidad(){        
        //asignamos los datos que vienen via ajax  
        $id_product = trim(Tools::getValue('id_product',0));
        $mensaje = trim(Tools::getValue('mensaje',0));
        
        if(!$id_product) {
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'Se produjo un error con el producto.')));
        }        

        $response = true;
        
        $producto = new Product($id_product);
        //23/08/2023 Para que no se quede un mensaje diferente en Portugal por ejemplo, vamos a meter el mismo mensaje personalizado a todos los idiomas, no solo español. Usamos la función generaIdiomas() que uso al crear productos
        // $producto->available_later = [1 => $mensaje];  
        $producto->available_later = $this->generaIdiomas($mensaje);
        
        if (!$producto->save()) {
            //no se guarda correctamente, mostramos mensaje
            $response = false;
            $mensaje_error = 'No se guardó nuevo mensaje';
        }      
        
        if($response) {
            $id_empleado = Context::getContext()->employee->id;
            $nombre_empleado = Context::getContext()->employee->firstname.' '.Context::getContext()->employee->lastname;
            $product = new Product($id_product);
            $nombre_producto = (is_array($product->name) ? $product->name[1] : $product->name);
            $referencia_producto = $product->reference;

            //introducimos log
            $insert_log = "INSERT INTO frik_log_productos_vendidos_sin_stock 
                (id_proceso, proceso, id_product, available_later, product_name, product_reference, id_employee, empleado, date_add) 
                VALUES (2 ,
                'mensaje_disponibilidad_producto' ,
                ".$id_product." ,                              
                '".$mensaje."',
                '".$nombre_producto."',
                '".$referencia_producto."',
                ".$id_empleado." ,
                '".$nombre_empleado."' ,                 
                NOW())";

            Db::getInstance()->Execute($insert_log);

            die(Tools::jsonEncode(array('message'=>'Producto actualizado', 'resultado' => 'Nuevo mensaje de disponibilidad guardado')));
        } else {
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'Error al modificar la disponibilidad - '.$mensaje_error)));
        }
    }


    /*
    * Función que pone o quita la categoría prepedido al producto enviado por ajax
    *
    */
    public function ajaxProcessCategoriaPrepedido(){        
        //asignamos los datos que vienen via ajax  
        $id_product = trim(Tools::getValue('id_product',0));
        $tarea = trim(Tools::getValue('tarea',0));
        
        if(!$id_product) {
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'Se produjo un error con el producto.')));
        }        

        $response = true;

        $producto = new Product($id_product);
        //averiguamos si tiene o no la categoría Prepedido id 121  
        $categorias = Product::getProductCategories($id_product);
        if ($tarea == 'eliminar') {
            if (in_array(121, $categorias)) {
                //quitamos categoría
                if (!$producto->deleteCategory(121)) {
                    $response = false;
                    $mensaje_error = 'No se pudo eliminar categoría Prepedido';
                } 
            } else {
                $response = false;
                $mensaje_error = 'No tiene categoría Prepedido';
            }
            
        } else {
            if (in_array(121, $categorias)) {
                $response = false;
                $mensaje_error = 'Ya tiene categoría Prepedido';
            } else {
                //ponemos categoría
                if (!$producto->addToCategories([121])) {
                    $response = false;
                    $mensaje_error = 'No se pudo añadir categoría Prepedido';
                }
            }
            
        }   

        
        if($response) {
            $id_empleado = Context::getContext()->employee->id;
            $nombre_empleado = Context::getContext()->employee->firstname.' '.Context::getContext()->employee->lastname;
            $product = new Product($id_product);
            $nombre_producto = (is_array($product->name) ? $product->name[1] : $product->name);
            $referencia_producto = $product->reference;

            if ($tarea == 'poner') {
                $id_proceso = 3;
                $proceso = 'poner_prepedido';
                $prepedido = 1;
            } else {
                $id_proceso = 4;
                $proceso = 'quitar_prepedido';
                $prepedido = 0;
            }

            //introducimos log
            $insert_log = "INSERT INTO frik_log_productos_vendidos_sin_stock 
                (id_proceso, proceso, id_product, prepedido, product_name, product_reference, id_employee, empleado, date_add) 
                VALUES (".$id_proceso." ,
                '".$proceso."' ,
                ".$id_product." ,                              
                ".$prepedido.",
                '".$nombre_producto."',
                '".$referencia_producto."',
                ".$id_empleado." ,
                '".$nombre_empleado."' ,                 
                NOW())";

            Db::getInstance()->Execute($insert_log);

            die(Tools::jsonEncode(array('message'=>'Producto actualizado', 'resultado' => ucfirst($tarea).' categoría Prepedido')));
        } else {
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'Se produjo un error - '.$mensaje_error)));
        }
    }


    /*
    * Función que pone o quita permitir pedido al producto enviado por ajax. Hay qe tener en cuenta si el producto está en catálogos de proveedores automatizado ya que puede que se le revierta el cambio automáticamente
    *
    */
    public function ajaxProcessPermitirPedidos(){        
        //asignamos los datos que vienen via ajax  
        $id_product = trim(Tools::getValue('id_product',0));
        $tarea = trim(Tools::getValue('tarea',0));
        
        if(!$id_product) {
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'Se produjo un error con el producto.')));
        }        

        $response = true;

        if ($tarea == 'poner') {
            //comprobamos out_of_stock está en NO (permitir pedido = 1)
            if (StockAvailableCore::outOfStock($id_product) != 1){
                //NO tiene permitir pedidos, se lo ponemos
                //comprobamos que no tenga marcada la categoría 2440 - No Permitir Pedido
                $categorias = Product::getProductCategories($id_product);
                if (in_array(2440, $categorias)){
                    //no se debería, mostramos mensaje
                    $response = false;
                    $mensaje_error = 'Tiene categoría NO PERMITIR PEDIDOS';
                } else {
                    //ponemos permitir
                    StockAvailable::setProductOutOfStock($id_product, 1);
                }
            } else {
                //está en Permitir, mostramos mensaje
                $response = false;
                $mensaje_error = 'Ya estaba en Permitir Pedidos'; 
            }
        } else {
            //comprobamos out_of_stock (permitir pedido = 1)
            if (StockAvailableCore::outOfStock($id_product) == 1){
                //tiene permitir pedidos, se lo quitamos
                StockAvailable::setProductOutOfStock($id_product, 2);

                //si está automatizado con los catálogos se cambiará de nuevo a Permitir, comprobamos su estado actual, si vuelve a tenerlo enviamos error de vuelta - CHEQUEAR ESTO EN PRODUCCIÓN
                if (StockAvailableCore::outOfStock($id_product) == 1){
                    //el producto ha vuelto a Permitir pedidos, mostramos mensaje
                    $response = false;
                    $mensaje_error = 'PRODUCTO CON CATÁLOGO AUTOMATIZADO';
                }

            } else {
                //está en No Permitir, mostramos mensaje
                $response = false;
                $mensaje_error = 'Ya estaba en NO Permitir Pedidos'; 
            }
        }            
        
        if($response) {
            $id_empleado = Context::getContext()->employee->id;
            $nombre_empleado = Context::getContext()->employee->firstname.' '.Context::getContext()->employee->lastname;
            $product = new Product($id_product);
            $nombre_producto = (is_array($product->name) ? $product->name[1] : $product->name);
            $referencia_producto = $product->reference;

            if ($tarea == 'poner') {
                $id_proceso = 5;
                $proceso = 'poner_permitir';
                $permitir = 1;
            } else {
                $id_proceso = 6;
                $proceso = 'quitar_permitir';
                $permitir = 0;
            }

            //introducimos log
            $insert_log = "INSERT INTO frik_log_productos_vendidos_sin_stock 
                (id_proceso, proceso, id_product, permitir_pedido, product_name, product_reference, id_employee, empleado, date_add) 
                VALUES (".$id_proceso." ,
                '".$proceso."' ,
                ".$id_product." ,                              
                ".$permitir.",
                '".$nombre_producto."',
                '".$referencia_producto."',
                ".$id_empleado." ,
                '".$nombre_empleado."' ,                 
                NOW())";

            Db::getInstance()->Execute($insert_log);

            die(Tools::jsonEncode(array('message'=>'Producto actualizado', 'resultado' => ucfirst($tarea).' permitir pedidos')));
        } else {
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'Error al modificar la disponibilidad - '.$mensaje_error)));
        }
    }

    /*
    * Función que busca los pedidos en espera que corresponden a un determinado producto. La llamamos mediante javascript/ajax al cargar la vista del controlador 
    *
    */
    public function ajaxProcessPedidosProducto(){    
        //asignamos los datos que vienen via ajax  
        $id_product = trim(Tools::getValue('id_product',0));
        $id_product_attribute = trim(Tools::getValue('id_product_attribute',0));
        
        if (!$id_product) {
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'Se produjo un error al consultar el producto.')));
        }        

        $response = true;

        //obtenemos el token de AdminOrders para crear el enlace al pedido en backoffice
        $id_employee = Context::getContext()->employee->id;
        $tab = 'AdminOrders';
        $token_adminorders = Tools::getAdminToken($tab . (int) Tab::getIdFromClassName($tab) . (int) $id_employee);
        
        $url_base = Tools::getHttpHost(true).__PS_BASE_URI__;
        // $url_order_back = $url_base.'lfadminia/index.php?controller=AdminOrders&id_order='.$idPedido.'&vieworder&token='.$token_adminorders;
        $url_order_back = $url_base.'lfadminia/index.php?controller=AdminOrders&vieworder&token='.$token_adminorders.'&id_order=';

        //obtenemos el token de AdminOrders para crear el enlace al pedido en backoffice        
        $tab = 'AdminCustomers';
        $token_admincustomers = Tools::getAdminToken($tab . (int) Tab::getIdFromClassName($tab) . (int) $id_employee); 
        
        $url_customer_back = $url_base.'lfadminia/index.php?tab=AdminCustomers&viewcustomer&token='.$token_admincustomers.'&id_customer=';
        
        //lista de pedidos en espera que contienen el producto        
        $sql_lista_pedidos = 'SELECT pvs.id_order AS id_order, pvs.date_add AS fecha_pedido, osl.name AS estado_actual, ord.payment AS modo_pago, 
        (SELECT COUNT(product_id) FROM lafrips_order_detail WHERE id_order = pvs.id_order) AS productos_pedido,
        (SELECT COUNT(id_productos_vendidos_sin_stock) FROM lafrips_productos_vendidos_sin_stock 
        WHERE id_order = pvs.id_order AND eliminado = 0) AS productos_sin_stock_pedido, #cuantos productos sin stock había en pedido
        pvs.product_reference AS referencia_producto, pvs.product_supplier_reference AS referencia_proveedor, sup.name AS proveedor,
        pvs.product_quantity AS cantidad, pvs.prepedido AS prepedido_compra,
        pvs.available_date AS available_date_compra, pvs.available_later AS mensaje_compra, pvs.fecha_disponible AS disponibilidad, 
        pvs.disponibilidad_avisada AS disponibilidad_avisada, pvs.fecha_ultimo_aviso AS fecha_ultimo_aviso, pvs.fecha_ultimo_aviso_automatico AS fecha_ultimo_aviso_automatico, 
        lpv.email_text AS contenido_email,
        IFNULL(pat.available_date, pro.available_date) AS available_date_actual,
        ord.id_customer AS id_customer, cus.firstname AS nombre, cus.lastname AS apellido, cus.email AS email,
        col.name AS pais_entrega, sta.name AS provincia, adr.city AS ciudad_entrega,
        (SELECT COUNT(id_order) FROM lafrips_orders WHERE valid = 1 AND id_customer = ord.id_customer) AS pedidos_cliente,
        CONCAT( "'.$url_order_back.'", pvs.id_order) AS url_pedido,
        CONCAT( "'.$url_customer_back.'", ord.id_customer) AS url_cliente
        FROM lafrips_productos_vendidos_sin_stock pvs
        JOIN lafrips_orders ord ON ord.id_order = pvs.id_order 
            AND ord.valid = 1 AND ord.current_state IN (2, 3, 9, 17, 20, 26, 28, 41, 42, 43, 55, 56, 57, 58, 59) 
        JOIN lafrips_order_detail ode ON ode.id_order = pvs.id_order 
            AND ode.product_id = pvs.id_product
            AND ode.product_attribute_id = pvs.id_product_attribute
            JOIN lafrips_product pro ON pro.id_product = pvs.id_product
        LEFT JOIN lafrips_product_attribute pat ON pat.id_product = pvs.id_product AND pat.id_product_attribute = pvs.id_product_attribute
        JOIN lafrips_customer cus ON cus.id_customer = ord.id_customer
        JOIN lafrips_order_state_lang osl ON osl.id_order_state = ord.current_state AND osl.id_lang = 1
        JOIN lafrips_address adr ON ord.id_address_delivery = adr.id_address
        LEFT JOIN lafrips_country_lang col ON col.id_country = adr.id_country AND col.id_lang = 1
        LEFT JOIN lafrips_state sta ON sta.id_state = adr.id_state
        LEFT JOIN lafrips_supplier sup ON sup.id_supplier = pvs.id_order_detail_supplier
        LEFT JOIN frik_log_productos_vendidos_sin_stock lpv ON lpv.id_log_productos_vendidos_sin_stock = pvs.email_id
        WHERE pvs.eliminado = 0
        AND pvs.id_product = '.$id_product.'
        AND pvs.id_product_attribute = '.$id_product_attribute.'
        ORDER BY pvs.id_order ASC';

        if ($lista_pedidos = Db::getInstance()->executeS($sql_lista_pedidos)) {
            //devolvemos la lista 
            die(Tools::jsonEncode(array('message'=>'Lista de pedidos obtenida correctamente', 'info_pedidos' => $lista_pedidos)));
        } else { 
            //error al sacar los productos           
            die(Tools::jsonEncode(array('message'=>'Error obteniendo lista de pedidos', 'info_pedidos' => $lista_pedidos)));
        }      

        // if($response)
        //     die(Tools::jsonEncode(array('message'=>'Referencia encontrada en Prestashop', 'info_producto' => $info_producto)));
    }

    /*
    * Función que almacena la nueva fecha de disponibilidad avisada al cliente (o que se debería avisar) en el campo disponibilidad avisada de la tabla productos_vendidso_sin_stock, para los pedidos seleccionados en la vista de pedidos del front del módulo
    *
    */
    public function ajaxProcessFechaDisponibilidadAvisada(){        
        //asignamos los datos que vienen via ajax, recibimos un array con los ids de pedido, de modo que si solo es uno no es necesario utilizar otra función 
        $id_product = trim(Tools::getValue('id_product',0));
        $id_product_attribute = trim(Tools::getValue('id_product_attribute',0));
        $pedidos = Tools::getValue('pedidos',0);
        $fecha = trim(Tools::getValue('fecha',0));
        
        if(!$id_product || !$pedidos || !$fecha) {
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'Se produjo un error con la información a modificar.')));
        }        

        $response = true;

        //hacemos update en lafrips_producto_vendidos_sin_stock en las líneas con id_product, id_product_attribute e id_order que se han recibido
        //creamos un string con los id de pedido
        $ids_pedidos = implode(',',$pedidos);

        $sql_update_productos_vendidos_sin_stock = 'UPDATE lafrips_productos_vendidos_sin_stock
                    SET
                    disponibilidad_avisada = "'.$fecha.'"
                    WHERE id_order IN ('.$ids_pedidos.')
                    AND id_product = '.$id_product.'
                    AND id_product_attribute = '.$id_product_attribute;

        if (count(Db::getInstance()->execute($sql_update_productos_vendidos_sin_stock)) < 1) {
            $response = false;
            $mensaje_error = 'Error al actualizar la fecha avisada de disponibilidad del producto';
        }
        
        if($response) {
            $id_empleado = Context::getContext()->employee->id;
            $nombre_empleado = Context::getContext()->employee->firstname.' '.Context::getContext()->employee->lastname;
            $product = new Product($id_product);
            $nombre_producto = (is_array($product->name) ? $product->name[1] : $product->name);
            $referencia_producto = $product->reference;

            //introducimos log. Uno para todos los pedidos 
            $insert_log = "INSERT INTO frik_log_productos_vendidos_sin_stock 
                (id_proceso, proceso, pedidos, id_product, id_product_attribute, disponibilidad_avisada, product_name, product_reference, id_employee, empleado, date_add) 
                VALUES (7 ,
                'disponibilidad_avisada' ,
                '".$ids_pedidos."' ,
                ".$id_product." ,
                ".$id_product_attribute." ,                              
                '".$fecha."' ,
                '".$nombre_producto."',
                '".$referencia_producto."',
                ".$id_empleado." ,
                '".$nombre_empleado."' ,                 
                NOW())";

            Db::getInstance()->Execute($insert_log);

            die(Tools::jsonEncode(array('message'=>'Fecha por pedido actualizada', 'resultado' => 'Nueva fecha de disponibilidad guardada')));
        } else {
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'Error al modificar la disponibilidad - '.$mensaje_error)));
        }
    }


    /*
    * Función que envia el email a los clientes de los pedidos escogidos
    *
    */
    public function ajaxProcessEnviaEmail(){        
        //asignamos los datos que vienen via ajax, recibimos un array con los ids de pedido, también el id_product para obtener la foto para el email, y el texto central del email
        $id_product = trim(Tools::getValue('id_product',0));  
        $id_product_attribute = trim(Tools::getValue('id_product_attribute',0));  
        if (!$id_product_attribute) {
            $id_product_attribute = 0;
        }     
        $pedidos = Tools::getValue('pedidos',0);     
        $texto = nl2br(Tools::getValue('texto',0));    
        
        if(!$id_product || !$pedidos) {
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'Se produjo un error con la información para el email.')));
        }        

        $response = true;
        $mensaje_error = '';

        //creamos un string con los id de pedido
        $ids_pedidos = implode(',',$pedidos);

        //sacamos la info necesaria de clientes para el email
        $sql_customer_orders = 'SELECT id_customer, id_order, date_add FROM lafrips_orders WHERE id_order IN ('.$ids_pedidos.')';

        if (!$customer_orders = Db::getInstance()->executeS($sql_customer_orders)) {
            die(Tools::jsonEncode(array('message'=>'Error obteniendo info de los clientes')));
        }

        // die(Tools::jsonEncode(array('message'=>'prueba Emails enviados', 'resultado' => $customers)));
        $id_empleado = Context::getContext()->employee->id;
        $nombre_empleado = Context::getContext()->employee->firstname.' '.Context::getContext()->employee->lastname;

        //usando el código de MailAlert()
        $link = new Link();
        $id_shop = 1;
		$id_lang = 1;		
        
        $product = new Product((int)$id_product, false, $id_lang, $id_shop);
        $product_link = $link->getProductLink($product, $product->link_rewrite, null, null, $id_lang, $id_shop);
        $nombre_producto = (is_array($product->name) ? $product->name[$id_lang] : $product->name);	
        $referencia_producto = $product->reference;
        
        //imagen			
        $image = Image::getCover((int)$id_product);			
        $image_link = new Link;//because getImageLInk is not static function
        $imagePath = $image_link->getImageLink($product->link_rewrite, $image['id_image'], 'home_default');

        // die(Tools::jsonEncode(array('message'=>'prueba Emails enviados', 'resultado' => 'link producto '.$product_link.' url_imagen '.$imagePath)));

		foreach ($customer_orders as $customer_order)
		{
			
            $customer = new Customer((int)$customer_order['id_customer']);
            $customer_email = $customer->email;
            $customer_id = (int)$customer->id;
            //customer name
            $customer_name = $customer->firstname;	
            $fecha_compra = $customer_order['date_add'];
            //formateamos fecha
            $fecha_compra = date_create($fecha_compra); 
            $fecha_compra = date_format($fecha_compra, 'd-m-Y');

            $id_order = (int)$customer_order['id_order'];		

			$template_vars = array(
				'{product}' => $nombre_producto,
				'{product_link}' => $product_link,
				'{customer_name}' => $customer_name,
				'{url_imagen}' => $imagePath,
                '{order_name}' => $id_order,
                '{order_date}' => $fecha_compra,
                '{mensaje}' => $texto
			);

			// $iso = Language::getIsoById($id_lang);
		
            if (Mail::Send(
                $id_lang,
                'aviso_prepedidos',
                //Mail::l('Product available', $id_lang),
                //'Información sobre el producto '.(is_array($product->name) ? $product->name[$id_lang] : $product->name), //añadimos el nombre del producto al asunto
                ' Información importante sobre tu pedido en lafrikileria.com', //asunto
                $template_vars,
                (string)$customer_email
            )) {
                //introducimos log por cada email enviado 
                $insert_log = "INSERT INTO frik_log_productos_vendidos_sin_stock 
                    (id_proceso, proceso, pedidos, id_order, id_product, id_product_attribute, email, email_text, fecha_email, product_name, product_reference, id_employee, empleado, date_add) 
                    VALUES (8 ,
                    'email' ,
                    '".$ids_pedidos."' ,
                    ".$id_order." ,
                    ".$id_product." ,
                    ".$id_product_attribute." ,                              
                    '".(string)$customer_email."' ,
                    '".pSQL($texto)."' ,
                    CURDATE() ,
                    '".$nombre_producto."' ,
                    '".$referencia_producto."' ,
                    ".$id_empleado." ,
                    '".$nombre_empleado."' ,                 
                    NOW())";

                Db::getInstance()->Execute($insert_log);               

                //obtenemos el id autoincrement generado para la línea recién insertada en log... para insetarlo en productos_vendidos_sin_stock como email_id de modo que se pueda acceder al texto del útlimo email enviado al cliente
                $last_insert_id = Db::getInstance()->getValue('SELECT LAST_INSERT_ID();');

                //email enviado correctamente, actualizamos campo fecha_ultimo_aviso de lafrips_productos_vendidos_sin_stock
                $sql_update_productos_vendidos_sin_stock = 'UPDATE lafrips_productos_vendidos_sin_stock
                            SET
                            fecha_ultimo_aviso = CURDATE(), 
                            email_id = '.$last_insert_id.'
                            WHERE id_order = '.$id_order.'
                            AND id_product = '.$id_product.'
                            AND id_product_attribute = '.$id_product_attribute;

                if (count(Db::getInstance()->execute($sql_update_productos_vendidos_sin_stock)) < 1) {
                    $response = false;
                    $mensaje_error .= '- Error al actualizar la fecha de último aviso por email Pedido - '.$id_order.' - ';
                }
                
            } else {
                //si falla el envío de email mostraremos error
                $response = false;
                $mensaje_error .= '- Error al enviar email para Pedido - '.$id_order.' - ';
            }
            

        }    

        // die(Tools::jsonEncode(array('message'=>'Emails enviados', 'resultado' => 'nombre producto '.$nombre_producto.' url_imagen '.$url_imagen_producto)));
        // die(Tools::jsonEncode(array('message'=>'Emails enviados', 'resultado' => 'producto '.$id_product.' pedidos '.$ids_pedidos.' texto '.$texto)));
        
        if($response) {
            die(Tools::jsonEncode(array('message'=>'Emails enviados', 'resultado' => 'Email o emails enviados correctamente')));
        } else {
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'Error envío de emails - '.$mensaje_error)));
        }
    }

    //23/08/2023 Función para generar array de lenguajes de modo que metemos le mismo mensaje de available_later a todos los idiomas y no queda por ejemplo en español "Llegará en octubre" y luego en Portugués Disponible 3 a 6 días.
    //función que recibe uno a tres parámetros, el nombre o lo que sea en español y si hay en inglés y portugués, para generar el array de lenguaje para crear el producto en función de los idiomas en Prestashop.
    public function generaIdiomas($spanish, $english = '', $portuguese = '') {
        if (!$spanish) {
            return false;
        }

        if (!$english) {
            $english = $spanish;
        }

        if (!$portuguese) {
            $portuguese = $spanish;
        }

        $idiomas = Language::getLanguages();

        $todos = array();
        foreach ($idiomas as $idioma) {
            if ($idioma['iso_code'] == 'es') {
                $todos[$idioma['id_lang']] = $spanish;
            } else if ($idioma['iso_code'] == 'pt') {
                $todos[$idioma['id_lang']] = $portuguese;
            } else {
                $todos[$idioma['id_lang']] = $english;
            }
        }

        return $todos;
    }   

    public function postProcess() {

        parent::postProcess();

        
    }

    



}
