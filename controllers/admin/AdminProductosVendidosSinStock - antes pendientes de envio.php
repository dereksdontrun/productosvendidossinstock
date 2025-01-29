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
        //24/01/2024 incluimos la clase de herramientas porque contiene la función estática setSupplyOrderDeliveryDate()
        require_once (dirname(__FILE__).'/../../classes/HerramientasVentaSinStock.php');

        $this->bootstrap = true;
        $this->context = Context::getContext();
        $this->identifier = 'id_productos_vendidos_sin_stock';
        $this->table = 'productos_vendidos_sin_stock';
        //$this->list_id = 'inventory';
        //$this->className = 'PickPackOrder';
        // $this->explicitSelect = true;
        $this->allow_export = true; //muestra el botón que saca por csv los datos de la lista
        $this->lang = false;

        $this->id_employee = Context::getContext()->employee->id;
        $this->employee_name = Context::getContext()->employee->firstname; 
        //almacenamos los id_supplier de proveedores a los cuales se solicitan los productos de manera automática
        //Cerdá id 65
        //Karactermanía 53
        //Globomatik 156
        //DMI 160
        //Disfrazzes 161
        $this->proveedores_automatizados = array(53, 65, 156, 160, 161);

        //08/11/2023 Para el id_supplier tiramos de id_supplier_solicitar
        // a.id_default_supplier AS id_supplier,
        $this->_select = '
        a.id_order AS id_order,
        a.id_product AS id_product,
        IF (a.checked, "Si", "No") AS checked,        
        a.id_supplier_solicitar AS id_supplier,
        a.product_reference AS product_reference,
        a.product_supplier_reference AS product_supplier_reference,
        a.product_name AS product_name,
        a.product_quantity AS product_quantity,
        a.date_add AS date_add,
        osl.name AS estado_prestashop,
        sup.name AS supplier_name,
        ims.id_image AS id_image,
        IF (a.solicitado, "Si", "No") AS solicitado,
        ROUND(psu.product_supplier_price_te, 2) AS coste';
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
        // 08/11/2023 cambiamos join  LEFT JOIN lafrips_supplier sup ON sup.id_supplier = pro.id_supplier por a.id_supplier_solicitar para que se muestre en la lista con el que está solictado o planeado solicitar y no el default    
        //17/11/2023 Quitamos de la vista los pedidos no valid
        //LEFT JOIN lafrips_orders ord ON a.id_order = ord.id_order
        $this->_join = '        
        JOIN lafrips_orders ord ON a.id_order = ord.id_order AND ord.valid = 1
        LEFT JOIN lafrips_order_state ors ON ors.id_order_state = ord.current_state
        LEFT JOIN lafrips_order_state_lang osl ON ors.id_order_state = osl.id_order_state AND osl.id_lang = 1
        JOIN lafrips_product pro ON pro.id_product = a.id_product AND a.eliminado = 0
        LEFT JOIN lafrips_supplier sup ON sup.id_supplier = a.id_supplier_solicitar
        LEFT JOIN lafrips_image_shop ims ON ims.id_product = a.id_product AND ims.id_shop = 1 AND ims.cover = 1';

        // MOD: Sergio - 30/04/2021 Sacar clasificación ABC del producto. Hacemos LEFT JOIN con lafrips_consumos. Si el producto no está, es C, si su antiguedad es menor de 61 días, es B, si está en la tabla y no es novedad, es lo que ponga en la tabla. 
        // 10/06/2021 ya no se calcula abc ni si es novedad, lo india todo la tabla     
        $this->_join .= ' LEFT JOIN lafrips_consumos con ON con.id_product = a.id_product AND con.id_product_attribute = a.id_product_attribute ';

        // MOD: 26/07/2023 Añadimos el stock disponible a la vista, haciendo join a stock_available
        $this->_join .= ' LEFT JOIN lafrips_stock_available ava ON ava.id_product = a.id_product AND ava.id_product_attribute = a.id_product_attribute ';

        
        // MOD: 14/11/2023 Añadimos el coste de proveedor
        $this->_join .= ' LEFT JOIN lafrips_product_supplier psu ON psu.id_product = a.id_product AND psu.id_product_attribute = a.id_product_attribute AND psu.id_supplier = a.id_supplier_solicitar';

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
            // MOD: 14/11/2023 Añadimos coste
            'coste' => array(
                'title' => $this->l('Coste'),
                'align' => 'text-center',
                'class' => 'fixed-width-xs',
                'type' => 'text',
                'filter_key' => 'psu!product_supplier_price_te',
                // 'filter_type' => 'int',
                'search' => false,
            ),     
            'checked' => array(
                'title' => $this->l('Revisado'),
                'type' => 'select',
                'class' => 'fixed-width-xs',
                'list' => array(0 => 'No', 1 => 'Si'),
                'filter_key' => 'a!checked',                               
            ),         
            //14/11/2023 hacemos esto para poner si o no en un select indicando si el producto está en un pedido o no, es decir, en la consulta sería si id_supply_order = 0 o no.    
            // 'solicitado' => array(
            //     'title' => $this->l('Solicitado'),
            //     'type' => 'select',
            //     'class' => 'fixed-width-xs',
            //     'list' => array('No' => 'No', 'Si' => 'Si'),
            //     'filter_key' => 'solicitado',   
            //     'havingFilter' => true,
            //     'filter_type' => 'text',                        
            // ),
            'solicitado' => array(
                'title' => $this->l('Solicitado'),
                'type' => 'select',
                'class' => 'fixed-width-xs',
                'list' => array(0 => 'No', 1 => 'Si'),
                'filter_key' => 'a!solicitado',                    
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
        $this->addRowAction('marcarsolicitado'); //parece que no podemos poner solo "solicitado" ya que existe ese nombre en las columnas a mostrar     

        //07/11/2023 Añadimos bulk_actions, que añade el checkbox a la izquierda de cada línea, para marcar revisado en bloque
        $this->bulk_actions = array(
            'updateRevisadoStatus' => array('text' => 'Marcar Revisado', 'icon' => 'icon-refresh'),
            'updateSolicitadoStatus' => array('text' => 'Marcar Solicitado', 'icon' => 'icon-refresh')
        );

        parent::__construct();        
    }

    /*
     * Hace accesible js y css desde la página de controller/AdminProductosVendidosSinStock
     */
    public function setMedia(){
        parent::setMedia();
        $this->addJs($this->module->getPathUri().'views/js/back_pvs.js');
        //añadimos la dirección para el css
        $this->addCss($this->module->getPathUri().'views/css/back_pvs.css');
    }

    public function displayRevisadoLink($token = null, $id, $name = null)
    {
        // $token will contain token variable
        // $id will hold id_identifier value
        // $name will hold display name

        $tpl = $this->createTemplate('helpers/list/list_action_revisado.tpl');
        // $href = 'linkhere';
        $tpl->assign(array(
            'id' => $id,        
            'action' => 'Marcar Revisado'
        ));
        return $tpl->fetch();
    }

    public function displayMarcarSolicitadoLink($token = null, $id, $name = null)
    {
        // $token will contain token variable
        // $id will hold id_identifier value
        // $name will hold display name

        $tpl = $this->createTemplate('helpers/list/list_action_solicitado.tpl');
        // $href = 'linkhere';
        $tpl->assign(array(
            'id' => $id,        
            'action' => 'Marcar Solicitado'
        ));
        return $tpl->fetch();
    }

    //añadimos función para carga inicial del select de proveedores del panel para generar pedidos de materiales
    /**
     * @see parent::initContent
     */
    public function initContent()
    {
        //sacamos los proveedores dentro de lafrips_productos_vendidos_sin_stock en las líneas que no tienen generado pedido, para no poner un select con toodos los proveedores de Prestashop
        //14/03/2024 Si se hace una compra, se marca revisado y se cancela el pedido esta consulta lo sacaría indefinidamente, de modo que vamos a cruzar con lafrips_orders para que solo valga para pedidos valid = 1
        $sql_suppliers_disponibles = "SELECT DISTINCT pvs.id_supplier_solicitar 
        FROM lafrips_productos_vendidos_sin_stock pvs
        JOIN lafrips_orders ord ON ord.id_order = pvs.id_order
        WHERE pvs.id_supply_order = 0
        AND pvs.eliminado = 0
        AND pvs.checked = 1
        AND pvs.solicitado = 0
        AND ord.valid = 1";

        $suppliers_disponibles = Db::getInstance()->ExecuteS($sql_suppliers_disponibles);

        if (!$suppliers_disponibles || empty($suppliers_disponibles)) {
            $this->displayWarning('No se encontraron productos para solicitar para ningún proveedor');
        } else {
            $info_proveedores = array();
            //por cada línea de pedido trabajaremos
            foreach ($suppliers_disponibles AS $id_supplier){ 
                $info_proveedores[$id_supplier['id_supplier_solicitar']] = Supplier::getNameById($id_supplier['id_supplier_solicitar']);
            }
            
            //ordenamos el array por orden alfabético
            asort($info_proveedores);

            //comprobamos si había un nombre en el input de referencia de pedido
            if (Tools::getValue('referencia_pedido_materiales')) {
                $referencia_pedido = pSQL(Tools::getValue('referencia_pedido_materiales'));
            } else {
                $referencia_pedido = "";
            }

            //si viene el parámetro get id_supply_order es que hemos encontrado un pedido en creación, y solo uno, y vuelto redirigidos, pasando de nuevo por aquí. Mandamos varable smarty para indicar que el formulario cambie para preguntar si queremos utilizarlo o si por el contrario no queremos continuar y lo vamos a revisar.
            $referencia_pedido_existente = "";
            $id_supply_order = 0;
            if (isset($_GET['id_supply_order'])) {
                $id_supply_order = $_GET['id_supply_order'];
                $referencia_pedido_existente = SupplyOrder::getReferenceById($_GET['id_supply_order']);
                // $this->errors[] = Tools::displayError('encontrado id_supply_order: '.$_GET['id_supply_order']); 
            }

            //generamos la fecha para rellenar el input y facilitar
            $date_input = date('_Y-m-d_H:i:s');

            $this->context->smarty->assign(
                array(
                    'info_proveedores' => $info_proveedores,
                    'referencia_pedido' => $referencia_pedido,
                    'referencia_pedido_existente' => $referencia_pedido_existente,
                    'date_input' => $date_input,
                    'id_supply_order' => $id_supply_order,
                    'token' => Tools::getAdminTokenLite('AdminProductosVendidosSinStock'),
                    'url_base' => Tools::getHttpHost(true).__PS_BASE_URI__.'lfadminia/'
                )               
            );
        }  
        
        parent::initContent();
    }

    //cuando se pulse sobre el botón de Revisado de una línea de producto
    public function postProcess()
    {
        //para que cargue bien el controlador y se pueda filtrar
        parent::postProcess();

        //si se pulsa el botón de Marcar Revisado para el producto individual
        if (Tools::isSubmit('submitRevisado')) {            
            $id_productos_vendidos_sin_stock = Tools::getValue('submitRevisado');

            $this->revisarProducto($id_productos_vendidos_sin_stock);
            
        } //fin if submitRevisado 

        //si se pulsa el botón de Marcar Solicitado para el producto individual
        if (Tools::isSubmit('submitSolicitado')) {            
            $id_productos_vendidos_sin_stock = Tools::getValue('submitSolicitado');

            $this->marcarSolicitadoProducto($id_productos_vendidos_sin_stock);
            
        } //fin if submitSolicitado 

        //si se pulsa el botón de Revisado para todos los productos iguales que estén pendientes de revisión
        if (Tools::isSubmit('submitTodosRevisados')) {
            //sacamos el id de la tabla del producto del que queremos buscar todas las ventas sin revisar
            $id_producto_vendido = Tools::getValue('submitTodosRevisados');           
            
            //primero necesitamos el id_product y id_product_attribute del producto
            $sql_ids_producto = 'SELECT id_product, id_product_attribute
            FROM lafrips_productos_vendidos_sin_stock             
            WHERE id_productos_vendidos_sin_stock = '.$id_producto_vendido;

            $ids_producto = Db::getInstance()->getRow($sql_ids_producto);

            $id_product = $ids_producto['id_product'];
            $id_product_attribute = $ids_producto['id_product_attribute'];            

            //ahora cada línea de la tabla con ese producto sin revisar (checked = 0)
            $sql_lineas_productos_vendido_sin_stock = "SELECT pvs.id_productos_vendidos_sin_stock AS id_productos_vendidos_sin_stock
            FROM lafrips_productos_vendidos_sin_stock pvs
            JOIN lafrips_orders ord ON ord.id_order = pvs.id_order
            WHERE pvs.checked = 0
            AND ord.valid = 1
            AND pvs.id_product = $id_product
            AND pvs.id_product_attribute = $id_product_attribute";

            $lineas_productos_vendido_sin_stock = Db::getInstance()->ExecuteS($sql_lineas_productos_vendido_sin_stock);

            //por cada línea de pedido trabajaremos
            foreach ($lineas_productos_vendido_sin_stock AS $linea){               
                
                $this->revisarProducto($linea['id_productos_vendidos_sin_stock']);
                
            } //foreach pedido con producto sin stock sin revisar

        } //fin if submitTodosRevisados

        //si se pulsa el botón de cambiar proveedor
        if (Tools::isSubmit('submitCambiaProveedor')) {
            //sacamos el id de la tabla del producto
            $id_productos_vendidos_sin_stock = Tools::getValue('submitCambiaProveedor');  

            //id del proveedor a asignar, sacado del select
            $id_nuevo_supplier = Tools::getValue('otros_proveedores');              

            //sacamos id_order e id_supplier original para el mensaje interno
            $sql_info_pedido = "SELECT id_order, id_supplier_solicitar, product_name, product_reference 
            FROM lafrips_productos_vendidos_sin_stock
            WHERE id_productos_vendidos_sin_stock = $id_productos_vendidos_sin_stock";

            $info_pedido = Db::getInstance()->getRow($sql_info_pedido); 

            $id_order = $info_pedido['id_order'];
            $id_supplier_original = $info_pedido['id_supplier_solicitar'];
            $product_name = $info_pedido['product_name'];
            $product_reference = $info_pedido['product_reference'];

            //ponemos el nuevo id_supplier
            $sql_update_productos_vendidos_sin_stock = "UPDATE lafrips_productos_vendidos_sin_stock
                SET                                                      
                id_supplier_solicitar = $id_nuevo_supplier,
                id_employee_cambio_supplier = ".$this->id_employee.",
                date_cambio_supplier = NOW(),
                date_upd = NOW() 
                WHERE id_productos_vendidos_sin_stock = $id_productos_vendidos_sin_stock";

            if (Db::getInstance()->execute($sql_update_productos_vendidos_sin_stock) === true) {
                //generamos mensaje para pedido sin stock pagado para producto revisado
                $fecha = date("d-m-Y H:i:s");
                $mensaje_cambio_proveedor = 'Cambio de proveedor a solicitar: 
                Nombre: '.$product_name.' 
                Referencia: '.$product_reference.'
                Proveedor original: '.Supplier::getNameById($id_supplier_original).'
                Cambiado a proveedor: '.Supplier::getNameById($id_nuevo_supplier).'
                cambiado por '.$this->employee_name.' el '.$fecha;

                if (!$this->insertarMensaje($id_order, $mensaje_cambio_proveedor)) {
                    $this->errors[] = Tools::displayError('Error generando mensaje interno de cambio de proveedor en pedido '.$id_order);   
                }  

                //redirigimos dentro de la línea de producto
                $this->redirect_after = self::$currentIndex.'&id_productos_vendidos_sin_stock='.$id_productos_vendidos_sin_stock.'&viewproductos_vendidos_sin_stock&token='.$this->token;
            } else {
                $this->errors[] = Tools::displayError('Error asignando nuevo proveedor a '.$product_reference.' en pedido '.$id_order); 
            }                        

        } //fin if submitCambiaProveedor

        //si se pulsa el botón de generar pedido de materiales
        if (Tools::isSubmit('submitGenerarPedido')) {               
            //id del proveedor a generar pedido, sacado del select
            if ($id_supplier_pedido = Tools::getValue('proveedores_solicitar')) {
                //recogemos el contenido del input para la referencia de pedido
                if (strlen(pSQL(Tools::getValue('referencia_pedido_materiales'))) > 6) {
                    $this->confirmations[] = "Proveedor ".Supplier::getNameById($id_supplier_pedido)." seleccionado";
                    $this->generarPedidoMateriales($id_supplier_pedido, pSQL(Tools::getValue('referencia_pedido_materiales')));
                } else {
                    $this->errors[] = Tools::displayError('La referencia de pedido debe tener más de 6 caracteres'); 
                }
                
            } else {
                $this->errors[] = Tools::displayError('Error seleccionando proveedor'); 
            }            
            
        } //fin if submitGenerarPedido 

        //si se pulsa el botón de generar pedido de materiales desde la confirmación una vez mostrado mensaje de que ya existe un pedido en creación para ese proveedor
        if (Tools::isSubmit('submitContinuarPedido')) {     
            $id_supply_order = Tools::getValue('submitContinuarPedido');

            //llamamos para completar pedido
            $this->completaPedidoMateriales($id_supply_order);
            
        }//fin if submitContinuarPedido

    } // fin postProcess
    
    //cuando se pulse sobre la línea del producto o el botón ver, mostramos info sobre este y sus proveedores disponibles
    public function renderView(){
        //como no hemos generado una clase etc para el controlador, recogemos de la url el valor del id del producto en lafrips_productos_vendidos_sin_stock. Con eso, sacamos todoos los datos necesarios y los asignamos a variables smarty para mostrarlo en el tpl que asignamos también más abajo, que será productosvendidossinstock.tpl
        //27/08/2020 Después de añadir a la web Cerdá Kids, queremos que cuando se trate de uno de esos productos sea bien claro. Se les diferencia bien por su id_manufacturer = 76

        //23/09/2024 Vamos a mostrar un mensaje si se cambia el proveedor al que solicitar el producto y se vuelve a entrar en la ficha, de modo que aparezca el proveedor original durante la compra, a cual cambió y quién y cuando lo hizo, todos datos que quedan registrados al hacer el cambio. id_employee_cambio_supplier, id_default_supplier AS id_original_supplier, date_cambio_supplier
        
        $id_producto_vendido_sin_stock = Tools::getValue('id_productos_vendidos_sin_stock');

        //sacamos la info del producto en tabla productos_vendido_sin_stock y también foto
        $sql_producto_vendido_sin_stock = "SELECT pvs.id_product AS id_product, pvs.id_product_attribute AS id_product_attribute, ode.product_ean13 AS ean, 
        pvs.product_name AS product_name, pvs.product_reference AS product_reference, pvs.out_of_stock AS out_of_stock, pvs.checked AS checked, 
        pvs.id_supplier_solicitar, pvs.product_supplier_reference, pvs.product_quantity, pvs.id_order AS id_order, osl.name AS estado_pedido,
        CONCAT( 'http://lafrikileria.com', '/', img.id_image, '-home_default/', 
                pla.link_rewrite, '.', 'jpg') AS imagen, img.id_image AS existe_imagen, pvs.date_checked AS date_checked, pvs.id_employee AS revisador, 
        pro.id_manufacturer AS id_manufacturer, pvs.id_supply_order AS id_supply_order, pvs.id_employee_supply_order AS id_employee_supply_order, pvs.date_supply_order AS date_supply_order, pvs.solicitado AS solicitado, pvs.id_employee_solicitado AS id_employee_solicitado, pvs.date_solicitado AS date_solicitado, pvs.id_employee_cambio_supplier AS id_employee_cambio_supplier, pvs.id_default_supplier AS id_original_supplier, pvs.date_cambio_supplier AS date_cambio_supplier
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

        $producto_vendido_sin_stock = Db::getInstance()->getRow($sql_producto_vendido_sin_stock);

        $id_product = $producto_vendido_sin_stock['id_product'];
        $id_product_attribute = $producto_vendido_sin_stock['id_product_attribute'];
        $out_of_stock = $producto_vendido_sin_stock['out_of_stock'];
        $ean = trim($producto_vendido_sin_stock['ean']);
        $product_name = $producto_vendido_sin_stock['product_name'];
        $product_reference = $producto_vendido_sin_stock['product_reference'];
        $product_quantity = $producto_vendido_sin_stock['product_quantity'];
        $id_order = $producto_vendido_sin_stock['id_order'];   
        $estado_pedido = $producto_vendido_sin_stock['estado_pedido'];        
        $checked = $producto_vendido_sin_stock['checked'];
        $date_checked = $producto_vendido_sin_stock['date_checked'];
        //formateamos fecha
        $date_checked = date_create($date_checked); 
        $date_checked = date_format($date_checked, 'd-m-Y H:i:s');

        $id_revisador = $producto_vendido_sin_stock['revisador'];
        //sacamos nombre empleado revisador
        $revisador = new Employee($id_revisador);
        $nombre_revisador = $revisador->firstname.' '.$revisador->lastname;

        $id_supplier_solicitar = $producto_vendido_sin_stock['id_supplier_solicitar'];
        $product_supplier_reference = trim($producto_vendido_sin_stock['product_supplier_reference']);
        //sacamos precio de coste
        $wholesale_price = ProductSupplier::getProductPrice($id_supplier_solicitar, $id_product, $id_product_attribute, $converted_price = false);

        //comprobamos si el producto es Cerdá Kids o Adult 08/10/2020
        //id de manufacturer por nombre
        $id_manufacturer_cerda_kids = (int)Manufacturer::getIdByName('Cerdá Kids');
        $id_manufacturer_cerda_adult = (int)Manufacturer::getIdByName('Cerdá Adult');
        $id_manufacturer = $producto_vendido_sin_stock['id_manufacturer'];
        $producto_kids = 0;
        if (($id_manufacturer == $id_manufacturer_cerda_kids) || ($id_manufacturer == $id_manufacturer_cerda_adult)) {
            $producto_kids = 1;
        }

                
        //si en $existe_imagen no hay un id ponemos logo
        if (empty($producto_vendido_sin_stock['existe_imagen'])) {
            $imagen = 'https://lafrikileria.com/img/logo_producto_medium_default.jpg';
        } else {
            $imagen = $producto_vendido_sin_stock['imagen'];
        }

        $solicitado = $producto_vendido_sin_stock['solicitado'];
        $id_employee_solicitado = $producto_vendido_sin_stock['id_employee_solicitado'];
        $employee_solicitado = new Employee($id_employee_solicitado);
        $employee_solicitado_name = $employee_solicitado->firstname;
        $date_solicitado = $producto_vendido_sin_stock['date_solicitado']; 
        //formateamos fecha
        $date_solicitado = date_create($date_solicitado); 
        $date_solicitado = date_format($date_solicitado, 'd-m-Y H:i:s');   

        //solicitado tendrá valor 1 si se considera solicitado a proveedor, ya sea por haberse incluido en un pedido o por marcarse manualmente como solicitado. Si tiene valor 0, id_supply_order debería ser 0. Si tiene valor 1 pueden pasar 3 cosas. Es un pedido antiguo, lo he marcado por BD como solicitado para que no aparezcan. A esos les pondré id_supply_order = 1 para reconocerlos en la plantilla. caso 2, que haya sido marcado como solicitado y tiene id_supply_order = 0, es decir, no se ha metido a pedido de materiales. El tercer caso son los productos de Cerdá, Karactermanía, y proveedores dropshipping a los que se hace el pedido mediante API, etc. Estos se quedan al entrar como id_supply_order = 0, pero aquí creamos una variable para reconocerlos:
        $proveedor_automatizado = 0;
        if (in_array($id_supplier_solicitar, $this->proveedores_automatizados)) {
            $proveedor_automatizado = 1;
        }

        $id_supply_order = $producto_vendido_sin_stock['id_supply_order'];
        $id_employee_supply_order = $producto_vendido_sin_stock['id_employee_supply_order'];
        $date_supply_order = $producto_vendido_sin_stock['date_supply_order']; 
        $supply_order_reference = SupplyOrder::getReferenceById($id_supply_order);
        $employee_supply_order = new Employee($id_employee_supply_order);
        $employee_supply_order_name = $employee_supply_order->firstname;
        //formateamos fecha
        $date_supply_order = date_create($date_supply_order); 
        $date_supply_order = date_format($date_supply_order, 'd-m-Y H:i:s');                

        $supplier_name = Supplier::getNameById($id_supplier_solicitar);

        //23/09/2024 datos de cambio de proveedor (si existe)
        $id_employee_cambio_supplier = $producto_vendido_sin_stock['id_employee_cambio_supplier'];
        $employee_cambio_supplier_name = '';
        //si tenemos id de empleado de cambio de proveedor es que se ha hecho, sacamos el nombre
        if ($id_employee_cambio_supplier) {
            $employee_cambio_supplier = new Employee($id_employee_cambio_supplier);
            $employee_cambio_supplier_name = $employee_cambio_supplier->firstname;
        }
        $id_original_supplier = $producto_vendido_sin_stock['id_original_supplier'];
        $original_supplier = Supplier::getNameById($id_original_supplier);
        $date_cambio_supplier = $producto_vendido_sin_stock['date_cambio_supplier'];
        //formateamos fecha
        $date_cambio_supplier = date_create($date_cambio_supplier); 
        $date_cambio_supplier = date_format($date_cambio_supplier, 'd-m-Y H:i:s'); 

        
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
        $sql_unidades_vendidas_sin_stock = "SELECT SUM(product_quantity)
        FROM lafrips_productos_vendidos_sin_stock         
        WHERE checked = 0
        AND id_product = $id_product
        AND id_product_attribute = $id_product_attribute";

        $unidades_vendidas_sin_stock = Db::getInstance()->getValue($sql_unidades_vendidas_sin_stock);
        
        //stock disponible del producto
        $stock_disponible = StockAvailableCore::getQuantityAvailableByProduct($id_product);
        
        //última venta
        $sql_ultima_venta = "SELECT ord.date_add
        FROM lafrips_orders ord 
        JOIN lafrips_order_detail ode ON ord.id_order = ode.id_order 
        WHERE ode.product_id = $id_product
        AND ode.product_attribute_id = $id_product_attribute
        AND ord.valid = 1 
        ORDER BY ord.id_order DESC";    

        $fecha_ultima_venta = Db::getInstance()->getValue($sql_ultima_venta);
        if (!$fecha_ultima_venta) {
            $ultima_venta = 'Error fecha';
        } else {
            $ultima_venta = date_create($fecha_ultima_venta); 
            $ultima_venta = date_format($ultima_venta, 'd-m-Y');
        }        

        //ventas totales, sin packs antiguos
        $sql_ventas_totales = "SELECT SUM(ode.product_quantity)
        FROM lafrips_order_detail ode 
        JOIN lafrips_orders ord ON ord.id_order = ode.id_order
        WHERE ord.valid = 1
        AND ode.product_id = $id_product
        AND ode.product_attribute_id = $id_product_attribute";    

        $ventas_totales = Db::getInstance()->getValue($sql_ventas_totales);

        //ventas últimos 6 meses
        $sql_ultimos6meses = "SELECT SUM(ode.product_quantity)
        FROM lafrips_order_detail ode 
        JOIN lafrips_orders ord ON ord.id_order = ode.id_order
        WHERE ord.date_add > DATE_SUB(NOW(), INTERVAL 6 MONTH) 
        AND ord.valid = 1
        AND ode.product_id = $id_product
        AND ode.product_attribute_id = $id_product_attribute";    

        $ultimos6meses = Db::getInstance()->getValue($sql_ultimos6meses);
        if (!$ultimos6meses) {
            $ultimos6meses = 'No';
        }

        //última compra a proveedor (si se hizo bien el pedido a proveedores)
        $sql_ultima_compra = "SELECT sor.date_add
        FROM lafrips_supply_order sor 
        JOIN lafrips_supply_order_detail sod ON sor.id_supply_order = sod.id_supply_order 
        WHERE sod.id_product = $id_product 
        AND sod.id_product_attribute = $id_product_attribute
        ORDER BY sor.id_supply_order DESC";       

        $fecha_ultima_compra = Db::getInstance()->getValue($sql_ultima_compra);
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
        JOIN lafrips_supplier sup ON sup.id_supplier = pvs.id_supplier_solicitar
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
                'id_supplier' => $id_supplier_solicitar,
                'wholesale_price' => $wholesale_price,
                'imagen' => $imagen,
                'supplier_name' => $supplier_name,  
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
                'solicitado' => $solicitado,
                'employee_solicitado_name' => $employee_solicitado_name,
                'date_solicitado' => $date_solicitado,                
                'id_supply_order' => $id_supply_order,
                'supply_order_reference' => $supply_order_reference,
                'employee_supply_order_name' => $employee_supply_order_name,
                'date_supply_order' => $date_supply_order,
                'proveedor_automatizado' => $proveedor_automatizado,
                'employee_cambio_supplier_name' => $employee_cambio_supplier_name,
                'original_supplier' => $original_supplier,
                'date_cambio_supplier' => $date_cambio_supplier
            )
        );         
            
        return $tpl->fetch();

    }

    public function revisarProducto($id_productos_vendidos_sin_stock) {       
        
        //sacamos el contenido del textarea si lo hay. Si viene de ejecución en lista de productos no habrá
        if (Tools::getValue('mensaje_pedidos') && strlen(Tools::getValue('mensaje_pedidos')) > 1){
            $mensaje_manual = pSQL(Tools::getValue('mensaje_pedidos'));
        }
        
        //sacamos toda la info necesaria de lafrips_productos_vendidos_sin_stock y también la info para cambiar el estado de pedido, poner mensaje, etc
        $sql_producto_vendido_sin_stock_etc = 'SELECT pvs.id_order AS id_order, ord.current_state AS current_state, pvs.id_order_status AS id_order_status, pvs.product_name AS product_name, 
        pvs.product_reference AS product_reference, pvs.checked AS checked, ord.id_customer AS id_customer, pvs.id_supplier_solicitar AS id_supplier_solicitar
        FROM lafrips_productos_vendidos_sin_stock pvs
        JOIN lafrips_orders ord ON ord.id_order = pvs.id_order
        WHERE pvs.id_productos_vendidos_sin_stock = '.$id_productos_vendidos_sin_stock;

        $producto_vendido_sin_stock_etc = Db::getInstance()->getRow($sql_producto_vendido_sin_stock_etc);

        $id_order = $producto_vendido_sin_stock_etc['id_order'];
        $current_state = $producto_vendido_sin_stock_etc['current_state'];
        $id_order_status = $producto_vendido_sin_stock_etc['id_order_status'];
        $product_name = pSQL($producto_vendido_sin_stock_etc['product_name']);
        $product_reference = $producto_vendido_sin_stock_etc['product_reference'];
        $checked = $producto_vendido_sin_stock_etc['checked'];
        $id_customer = $producto_vendido_sin_stock_etc['id_customer'];  
        $id_supplier_solicitar = $producto_vendido_sin_stock_etc['id_supplier_solicitar'];    
        
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

            $this->errors[] = Tools::displayError($product_name.' en pedido '.$id_order.' ya estaba revisado');

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

            $this->errors[] = Tools::displayError('El pedido '.$id_order.' se encuentra cancelado');

            return;
        }
        
        //si no está marcado, lo marcamos como checked, ponemos fecha date_checked,metemos un mensaje en su pedido diciendo que el producto está revisado por tal empleado, comprobamos si hay más productos vendidos sin stock en ese pedido, si los hay comprobamos si están checked. Si no lo están, lo dejamos así, si todos están checked comprobamos el estado actual del pedido, si es Sin Stock Pagado, lo pasariamos a Completando Pedido, añadiendo también un mensaje
        //08/11/2023 Además añadimos otro mensaje al pedido indicando el proveedor al que se supone que se ha pedido, que será el que venga en lafrips_productos_vendidos_sin_stock como id_supplier_solicitar

        //marcamos checked y date checked, y empleado en lafrips_productos_vendidos_sin_stock.
        $sql_update_productos_vendidos_sin_stock = 'UPDATE lafrips_productos_vendidos_sin_stock
                SET                                                      
                checked = 1,
                date_checked = NOW(),
                id_employee = '.$this->id_employee.' 
                WHERE id_productos_vendidos_sin_stock = '.$id_productos_vendidos_sin_stock;

        Db::getInstance()->execute($sql_update_productos_vendidos_sin_stock);

        //ponemos mensaje en el pedido de producto revisado
        //generamos mensaje para pedido sin stock pagado para producto revisado
        $fecha = date("d-m-Y H:i:s");
        $mensaje_pedido_sin_stock_producto = 'Producto vendido sin stock: 
        Nombre: '.$product_name.' 
        Referencia: '.$product_reference.'
        Solicitado a : '.Supplier::getNameById($id_supplier_solicitar).'
        revisado por '.$this->employee_name.' el '.$fecha;

        if (!$this->insertarMensaje($id_order, $mensaje_pedido_sin_stock_producto)) {
            $this->errors[] = Tools::displayError('Error generando mensaje interno de Revisado en pedido '.$id_order);   
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
            - '.$this->employee_name.' el '.$fecha;

            if (!$this->insertarMensaje($id_order, $mensaje_manual_pedido)) {
                $this->errors[] = Tools::displayError('Error generando mensaje manual de usuario en pedido '.$id_order);   
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

            $this->displayWarning('El pedido '.$id_order.' contiene otros productos sin revisar.');

            return;

        } elseif ($id_esperando_productos) {
            //todos los productos vendidos sin stock en el pedido están revisados. comprobamos si el estado actual de pedido es Sin Stock Pagado, o si ya está en Completando Pedido. Si está en Sin stock pagado lo pasamos a Completando Pedido, con mensaje de success y mensaje privado en pedido. Si el pedido está ya en Completando Pedido, mostramos warning diciéndolo. Si no está en ninguno de los dos estados, no lo cambiamos, pero mostramos aviso.
            //22/08/2023 Ya no vamos a cambiar el estado de pedido si se revisa y no hay más productos para revisar ya que en el mismo proceso se harán otras cosas como cambiar el transporte a GLS24 según el caso, etc y lo centralizamos todo en la tarea CambiaEstadoPedido.php, de modo que si no hay más productos a revisar se muestra un mensaje y nada más
            if ($current_state == Configuration::get(PS_OS_OUTOFSTOCK_PAID)){

                $this->confirmations[] = $this->l('Producto en pedido '.$id_order.' marcado como Revisado. ');
                $this->displayWarning('El pedido '.$id_order.' se revisará para cambiar a Completando Pedido en el proceso horario. Todos sus productos vendidos sin stock están revisados. ');

                return;

                
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
    }

    public function processBulkUpdateRevisadoStatus()
    {
        if (!Tools::getValue('productos_vendidos_sin_stockBox')) {
            $this->errors[] = Tools::displayError('Debes seleccionar algún producto para marcar como revisado');
        } else {
            // el checkbox lleva el id de la tabla productos_vendidos_sin_stock
            foreach (Tools::getValue('productos_vendidos_sin_stockBox') as $id_productos_vendidos_sin_stock) {

                $this->revisarProducto($id_productos_vendidos_sin_stock);
                
            }
        }             
    }

    //función para poner mensaje en pedido. Tiene que recibir el mensaje a meter y el id_order para obtener los datos necesarios
    public function insertarMensaje($id_order, $mensaje) {
        
        //sacamos el id_customer del pedido
        $order = new Order($id_order); 
        $id_customer = $order->id_customer;

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
            $cm_interno->id_employee = $this->id_employee; 
            $cm_interno->message = $mensaje;
            $cm_interno->private = 1;                
            $cm_interno->add();

            return true;
        } else {
            return false;
        } 
    }

    //función que recibiendo un id_supplier organiza la creación de un pedido de materiales con los productos de productos vendidos sin stock que no tengan asignado un pedido en id_supply_order
    //Tendremos que buscar los productos de la tabla cuyo id_supplier_solicitar coincida con el escogido y cuyo campo id_supply_order valga 0. De esos productos sacaremos las unidades a pedir e iremos generando un nuevo pedido, pero comprobando primero si ya existe un pedido para dicho proveedor que esté en estado pedido en creación, id 1, en cuyo caso los añadiremos al pedido.
    //la función llamará a otra con el id_supply_order a utilizar
    public function generarPedidoMateriales($id_supplier, $referencia_pedido) {
        //primero comprobamos si existe algún pedido de materiales para este proveedor en estado pedido en creación en curso
        if ($id_supply_order = $this->pedidosCreacionCurso($id_supplier)) {
            //existe pedido. comprobamos que la función devuelve solo un valor, que será el id de pedido. Si devuelve varios mostramos error con las referencias de los pedidos de proveedores.
            if (count($id_supply_order) > 1) {
                $mensaje_error_referencias = "";
                foreach ($id_supply_order AS $supply_order) {
                    $mensaje_error_referencias .= $supply_order['reference'].", ";
                }

                $this->errors[] = Tools::displayError('Existen varios pedidos en Creación en curso para el proveedor '.Supplier::getNameById($id_supplier).': '.$mensaje_error_referencias."\nPara continuar no puede haber más de un pedido en estado Creación en curso para este proveedor");                       
                
                return;
            } else {
                //tenemos un pedido en Creación en curso con id $id_supply_order para el proveedor, lo utilizaremos para insertar los productos, pero primero volvemos (a través de initContent()) para pedir confirmación sobre si utilizar este pedido o en caso contrario pedir que lo cambien de estado para generar uno nuevo. Redireccionamos añadiendo como parámetro GET el id_supply_order . En la recarga se comprobará si el submit es de continuar con pedido y se llamará a la función compeltaPedidoMateriales()           
                $this->redirect_after = self::$currentIndex.'&token='.$this->token.'&id_supply_order='.$id_supply_order;
            }
        } else {
            //no hay pedidos en Creación en curso para el proveedor, generamos uno nuevo
            if (!$id_supply_order = $this->crearPedidoMateriales($id_supplier, $referencia_pedido)) {
                $this->errors[] = Tools::displayError('Error generando el pedido para el proveedor '.Supplier::getNameById($id_supplier)); 

                return;
            }

            //pedido generado, lo completamos con los productos
            $this->completaPedidoMateriales($id_supply_order);
        }         

        // return;        
    }

    //función que recibe el id_supply_order y busca y organiza los productos para meter
    public function completaPedidoMateriales($id_supply_order) {
        //obtenemos el id_supplier
        $sql_id_supplier = "SELECT id_supplier FROM lafrips_supply_order WHERE id_supply_order = $id_supply_order";

        $id_supplier = Db::getInstance()->getValue($sql_id_supplier);
        
        //tenemos en $id_supply_order el id de pedido al que añadir productos, ya sea nuevo o existente. obtenemos los productos a incluir
        if (!$info_productos = $this->getProductos($id_supplier)) {
            $this->errors[] = Tools::displayError('No se encuentran productos revisados para generar pedido para el proveedor '.Supplier::getNameById($id_supplier)); 

            return;
        } else {
            $this->confirmations[] = "<br>Encontrados ".count($info_productos)." productos para generar pedido para el proveedor ".Supplier::getNameById($id_supplier); 

            foreach($info_productos AS $info_producto) {
                $this->setSupplyOrderDetail($id_supply_order, $info_producto);

                //generamos mensaje para pedido indicando el epdido de materiales
                $fecha = date("d-m-Y H:i:s");
                $mensaje_pedido_materiales_producto = 'Producto vendido sin stock: 
                Nombre: '.$info_producto['product_name'].' 
                Referencia: '.$info_producto['product_reference'].'
                Solicitado a : '.Supplier::getNameById($id_supplier).'
                Añadido a Pedido de Materiales: Id: '.$id_supply_order.' - <b>'.SupplyOrder::getReferenceById($id_supply_order).'</b>
                por '.$this->employee_name.' el '.$fecha;

                if (!$this->insertarMensaje($info_producto['id_order'], $mensaje_pedido_materiales_producto)) {
                    $this->errors[] = Tools::displayError('Error generando mensaje interno de producto añadido a pedido de materiales en pedido '.$info_producto['id_order']);   
                }        
            }            
        }
    }

    //función que dispone de los datos necesarios para añadir una línea de pedido a un pedido de materiales existente. Comprueba primero si el producto a añadir se encuentra ya en el pedido. Si es así añade la cantidad al pedido, si no, añade el producto al pedido.
    public function setSupplyOrderDetail($id_supply_order, $info_producto) {        

        //obtenemos los productos en pedido de materiales
        $supply_order = new SupplyOrder($id_supply_order);

        $entries = $supply_order->getEntries();

        $id_supply_order_detail = null;

        foreach ($entries AS $entry) {
            if ($entry['id_product'] == $info_producto['id_product'] && $entry['id_product_attribute'] == $info_producto['id_product_attribute']) {
                $id_supply_order_detail = $entry['id_supply_order_detail'];
            }
        }

        //si hemos encontrado el producto de esta línea en el pedido de materiales, tendremos su id en $id_supply_order_detail
        if ($id_supply_order_detail) {
            //el producto está en el pedido, sumamos la cantidad esperada
            $supply_order_detail = new SupplyOrderDetail($id_supply_order_detail);
            $supply_order_detail->quantity_expected = $supply_order_detail->quantity_expected + $info_producto['product_quantity'];
            $supply_order_detail->update();
            //hacemos update del supply_order con el que estamos
            $supply_order->update();           

        } else {
            //el producto no está en el pedido, generamos nueva línea de pedido
            $supply_order_detail = new SupplyOrderDetail();
            // sets parameters
            $supply_order_detail->id_supply_order = $id_supply_order;
            $supply_order_detail->id_product = $info_producto['id_product'];
            $supply_order_detail->id_product_attribute = $info_producto['id_product_attribute'];   
            $supply_order_detail->ean13 = $info_producto['ean13'];  
            $supply_order_detail->name = $info_producto['product_name'];               
            $supply_order_detail->id_currency = 1;
            $supply_order_detail->exchange_rate = 1;
            $supply_order_detail->supplier_reference = $info_producto['product_supplier_reference'];                    
            $supply_order_detail->reference = $info_producto['product_reference'];                
            $supply_order_detail->upc = '';
            // $supply_order_detail->force_id = (bool)$force_ids;
            $supply_order_detail->unit_price_te = (float)$info_producto['product_supplier_price_te'];      
            $supply_order_detail->quantity_expected = (int)$info_producto['product_quantity'];
            $supply_order_detail->discount_rate = 0;
            $supply_order_detail->tax_rate = 0;

            $supply_order_detail->add();
            //hacemos update del supply_order con el que estamos
            $supply_order->update();                                    
        }

        $this->updateProductosVendidosSinStock($id_supply_order, $info_producto['id_productos_vendidos_sin_stock']);

        //"liberamos" las variables
        unset($supply_order_detail);
        unset($supply_order);

        return;
    }

    //función que busca los productos a añadir a pedido para un proveedor
    public function getProductos($id_supplier) {
        //17/11/2023 Nos aseguramos de que las líneas obtenidas sean de pedidos de cliente válidos, porque peuden haber sido cancelados a posteriori
        $sql_info_productos = "SELECT pvs.id_productos_vendidos_sin_stock, pvs.id_order, pvs.id_product, pvs.id_product_attribute, pvs.product_name, pvs.product_quantity,
        psu.product_supplier_reference, psu.product_supplier_price_te,
        IFNULL(pat.reference, pro.reference) AS product_reference, IFNULL(pat.ean13, pro.ean13) AS ean13
        FROM lafrips_productos_vendidos_sin_stock pvs
        JOIN lafrips_product_supplier psu ON psu.id_product = pvs.id_product 
            AND psu.id_product_attribute = pvs.id_product_attribute
            AND psu.id_supplier = $id_supplier
        JOIN lafrips_product pro ON pro.id_product = pvs.id_product
        LEFT JOIN lafrips_product_attribute pat ON pat.id_product = pvs.id_product AND pat.id_product_attribute = pvs.id_product_attribute
        JOIN lafrips_orders ord ON ord.id_order = pvs.id_order AND ord.valid = 1
        WHERE pvs.checked = 1
        AND pvs.solicitado = 0
        AND pvs.eliminado = 0
        AND pvs.dropshipping = 0
        AND pvs.id_supply_order = 0
        AND pvs.id_supplier_solicitar = $id_supplier";
                
        return Db::getInstance()->ExecuteS($sql_info_productos);
    }

    //función que genera un nuevo pedido de materiales con el id de proveedor que llega como parámetro. Devuelve el id del nuevo pedido
    public function crearPedidoMateriales($id_supplier, $supply_order_reference) { 
        
        // $supply_order_reference = "KAR_".date('Ymd_His');

        //la fecha de entrega p.ej. cinco días a partir de hoy
        //creamos la fecha minima para el input date, hoy más cinco en segundos, pasado de nuevo a fecha Y-m-d
        // $hoymascincoensegundos = strtotime(date('Y-m-d')) + (86400*5);
        // $date_delivery_expected = date('Y-m-d', $hoymascincoensegundos).' 00:00:00';   

        
        //23/01/2024 Hemos metido una columna supply_order_delay a lafrips_mensaje_disponibilidad que nos indica los días que tarda un pedido de materiales en llegar para cada proveedor. Es aproximado, pero si el valor es dos por ejemplo, pondremos $date_delivery_expected hoy + dos días, etc        

        //Generamos la fecha  en setSupplyOrderDeliveryDate() para tener en cuenta fines de semana etc.  Está en la clase HerramientasVentaSinStock del módulo productosvendidossinstock, la hecho required arriba.
        $date_delivery_expected = HerramientasVentaSinStock::setSupplyOrderDeliveryDate($id_supplier);
        
        $supply_order = new SupplyOrder();                
        $supply_order->id_supplier = $id_supplier;
        $supply_order->id_lang = 1;
        $supply_order->id_warehouse = 1;
        $supply_order->id_currency = 1;
        $supply_order->reference = pSQL($supply_order_reference);
        $supply_order->date_delivery_expected = pSQL($date_delivery_expected);
        $supply_order->discount_rate = 0;
        $supply_order->id_ref_currency = 1;
        $supply_order->supplier_name = Supplier::getNameById($id_supplier);
        //estado inicial 1, Creación en curso
        $supply_order->id_supply_order_state = 1;

        if ($supply_order->add()) {
            $this->confirmations[] = "<br>Generado pedido en Creación en curso para proveedor ".Supplier::getNameById($id_supplier)." con referencia ".pSQL($supply_order_reference);

            return $supply_order->id;
        } else {
            return false;
        }
    }

    //función que comprueba si existe algún pedido de materiales en Creación en curso para el id_supplier recibido, y si existe devuelve el id de pedido, si no devuelve false
    public function pedidosCreacionCurso($id_supplier) {
        $sql_pedido_pendiente = "SELECT id_supply_order, reference FROM lafrips_supply_order WHERE id_supply_order_state = 1 AND id_supplier = $id_supplier";
        $pedido_pendiente = Db::getInstance()->executeS($sql_pedido_pendiente);
        //si no hay pedidos devolvemos false, pero si hay más de uno devolvemos las referencias para enviar aviso de error al usuario. No debe haber varios pedidos del mismo proveedor en Creación en curso
        if (!$pedido_pendiente) {
            return false;
        } else if (count($pedido_pendiente) > 1) {
            //hay varios pedidos, devolvemos resultado para meter a array de errores            
            return $pedido_pendiente;
        } else {
            //todo correcto, devolvemos el id de pedido de materiales
            $this->confirmations[] = "<br>Encontrado pedido en Creación en curso para proveedor ".Supplier::getNameById($id_supplier)." con referencia ".SupplyOrder::getReferenceById($pedido_pendiente[0]['id_supply_order']);

            return $pedido_pendiente[0]['id_supply_order'];
        }
        
    }    

    //función que actualiza solicitado y id_supply_order en lafrips_productos_vendidso_sin_stock cada vez que se añade un producto a un pedido
    public function updateProductosVendidosSinStock($id_supply_order, $id_productos_vendidos_sin_stock) {
        $sql_update_productos_vendidos_sin_stock = "UPDATE lafrips_productos_vendidos_sin_stock
            SET 
            solicitado = 1,                                                     
            id_supply_order = $id_supply_order,
            id_employee_supply_order = ".$this->id_employee.",
            date_supply_order = NOW(),
            date_upd = NOW() 
            WHERE id_productos_vendidos_sin_stock = $id_productos_vendidos_sin_stock";

        $sql_referencia = "SELECT product_reference FROM lafrips_productos_vendidos_sin_stock WHERE id_productos_vendidos_sin_stock = $id_productos_vendidos_sin_stock";

        $referencia = Db::getInstance()->getValue($sql_referencia);

        if (Db::getInstance()->execute($sql_update_productos_vendidos_sin_stock) !== true) {       
            $this->errors[] = Tools::displayError('Error actualizando datos de pedido de materiales para producto '.$referencia);            
        } else {
            $this->confirmations[] = "<br>Producto con referencia ".$referencia." añadido correctamente a pedido ".SupplyOrder::getReferenceById($id_supply_order);
        }

        return;
    }
    
    //función para poner solictado a 1 para evitar productos si queremos, sin meter a pedido de materiales
    public function marcarSolicitadoProducto($id_productos_vendidos_sin_stock) {
        //sacamos toda la info necesaria de lafrips_productos_vendidos_sin_stock y también la info para cambiar el estado de pedido, poner mensaje, etc
        $sql_producto_vendido_sin_stock_etc = 'SELECT id_order, product_name, product_reference
        FROM lafrips_productos_vendidos_sin_stock         
        WHERE id_productos_vendidos_sin_stock = '.$id_productos_vendidos_sin_stock;

        $producto_vendido_sin_stock_etc = Db::getInstance()->getRow($sql_producto_vendido_sin_stock_etc);

        $id_order = $producto_vendido_sin_stock_etc['id_order'];
        $product_name = pSQL($producto_vendido_sin_stock_etc['product_name']);
        $product_reference = $producto_vendido_sin_stock_etc['product_reference'];        

        //marcamos solicitado y date solicitado, y empleado en lafrips_productos_vendidos_sin_stock.
        $sql_update_productos_vendidos_sin_stock = 'UPDATE lafrips_productos_vendidos_sin_stock
                SET                                                      
                solicitado = 1,
                id_employee_solicitado = '.$this->id_employee.',
                date_solicitado = NOW(),
                date_upd = NOW()                 
                WHERE id_productos_vendidos_sin_stock = '.$id_productos_vendidos_sin_stock;

        Db::getInstance()->execute($sql_update_productos_vendidos_sin_stock);

        //ponemos mensaje en el pedido de producto marcado solicitado, no incluido en pedido        
        $fecha = date("d-m-Y H:i:s");
        $mensaje_pedido_producto_solicitado = 'Producto vendido sin stock: 
        Nombre: '.$product_name.' 
        Referencia: '.$product_reference.'
        MARCADO SOLICITADO sin incluir en pedido de materiales
        por '.$this->employee_name.' el '.$fecha;

        if (!$this->insertarMensaje($id_order, $mensaje_pedido_producto_solicitado)) {
            $this->errors[] = Tools::displayError('Error generando mensaje interno de producto marcadado como solicitado, en pedido '.$id_order);   
        }   
        
        return;
    }

    //marcar solicitado todas las líneas con check
    public function processBulkUpdateSolicitadoStatus()
    {
        if (!Tools::getValue('productos_vendidos_sin_stockBox')) {
            $this->errors[] = Tools::displayError('Debes seleccionar algún producto para marcar como solicitado');
        } else {
            // el checkbox lleva el id de la tabla productos_vendidos_sin_stock
            foreach (Tools::getValue('productos_vendidos_sin_stockBox') as $id_productos_vendidos_sin_stock) {

                $this->marcarSolicitadoProducto($id_productos_vendidos_sin_stock);
                
            }
        }             
    }
}

?>

