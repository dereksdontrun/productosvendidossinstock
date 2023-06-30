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

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once(dirname(__FILE__).'/classes/Disfrazzes.php');
require_once(dirname(__FILE__).'/classes/Globomatik.php');
require_once(dirname(__FILE__).'/classes/Dmi.php');

class Productosvendidossinstock extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'productosvendidossinstock';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Sergio';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        //colocamos el link al módulo en la pestaña lateral de existencias        
        $this->admin_tab[] = array('classname' => 'AdminProductosVendidosSinStock', 'parent' => 'AdminStock', 'displayname' => 'Productos vendidos sin Stock');

        //10/11/2020 tab para ir al formulario para crear pedidos manuales para Cerdá
        $this->admin_tab[] = array('classname' => 'AdminPedidosManualesCerda', 'parent' => 'AdminStock', 'displayname' => 'Pedido Manual Cerdá');

        //13/01/2021 tab para mostrar los productos PREPEDIDO
        $this->admin_tab[] = array('classname' => 'AdminProductosPrepedido', 'parent' => 'AdminStock', 'displayname' => 'Prepedidos y En espera');

        //02/03/2022 tab para pedidos dropshipping
        $this->admin_tab[] = array('classname' => 'AdminPedidosDropshipping', 'parent' => 'AdminOrders', 'displayname' => 'Pedidos Dropshipping');

        $this->displayName = $this->l('Gestión de productos vendidos sin stock y Dropshipping');
        $this->description = $this->l('Módulo que permite la gestión de los productos con permitir pedido activado y vendidos sin stock, así como los casos de proveedores dropshipping.');

        $this->confirmUninstall = $this->l('¿Quieres desinstalar el módulo?');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        Configuration::updateValue('PRODUCTOSVENDIDOSSINSTOCK_LIVE_MODE', false);

        //añadimos link en pestaña de existencias llamando a installTab
        foreach ($this->admin_tab as $tab)
            $this->installTab($tab['classname'], $tab['parent'], $this->name, $tab['displayname']);

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('actionOrderStatusPostUpdate') &&
            $this->registerHook('displayAdminOrder') && 
            $this->registerHook('displayBackOfficeHeader'); 
    }

    public function uninstall()
    {
        Configuration::deleteByName('PRODUCTOSVENDIDOSSINSTOCK_LIVE_MODE');

        //desinstalar el link de la pestaña lateral de existencias llamando a unistallTab
        foreach ($this->admin_tab as $tab)
            $this->unInstallTab($tab['classname']);

        return parent::uninstall();
    }

    /*
     * Crear el link en pestaña de menú lateral, dentro de Existencias
     */    
    protected function installTab($classname = false, $parent = false, $module = false, $displayname = false) {
        if (!$classname)
            return true;

        $tab = new Tab();
        $tab->class_name = $classname;
        if ($parent)
            if (!is_int($parent))
                $tab->id_parent = (int) Tab::getIdFromClassName($parent);
            else
                $tab->id_parent = (int) $parent;
        if (!$module)
            $module = $this->name;
        $tab->module = $module;
        $tab->active = true;
        if (!$displayname)
            $displayname = $this->displayName;
        $tab->name[(int) (Configuration::get('PS_LANG_DEFAULT'))] = $displayname;

        if (!$tab->add())
            return false;

        return true;
    }

    /*
     * Quitar el link en pestaña de menú lateral, dentro de Existencias
     */
    protected function unInstallTab($classname = false) {
        if (!$classname)
            return true;

        $idTab = Tab::getIdFromClassName($classname);
        if ($idTab) {
            $tab = new Tab($idTab);
            return $tab->delete();
            ;
        }
        return true;
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitProductosvendidossinstockModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitProductosvendidossinstockModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Activar gestión Dropshipping'),
                        'name' => 'DROPSHIPPING_PROCESO',
                        'is_bool' => true,
                        'desc' => $this->l('Permitir procesos de Dropshipping'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-envelope"></i>',
                        'desc' => $this->l('Enter a valid email address'),
                        'name' => 'PRODUCTOSVENDIDOSSINSTOCK_ACCOUNT_EMAIL',
                        'label' => $this->l('Email'),
                    ),
                    array(
                        'type' => 'password',
                        'name' => 'PRODUCTOSVENDIDOSSINSTOCK_ACCOUNT_PASSWORD',
                        'label' => $this->l('Password'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'DROPSHIPPING_PROCESO' => Configuration::get('DROPSHIPPING_PROCESO'),
            'PRODUCTOSVENDIDOSSINSTOCK_ACCOUNT_EMAIL' => Configuration::get('PRODUCTOSVENDIDOSSINSTOCK_ACCOUNT_EMAIL', 'contact@prestashop.com'),
            'PRODUCTOSVENDIDOSSINSTOCK_ACCOUNT_PASSWORD' => Configuration::get('PRODUCTOSVENDIDOSSINSTOCK_ACCOUNT_PASSWORD', null),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    //necesario para añadir css al back office de order. Parece que al usar el hook displayAdminOrder, meter el css en hookBackOfficeHeader no sirve y hay que añadir el hook hookDisplayBackOfficeHeader para esto ¿?
    public function hookDisplayBackOfficeHeader()
    {
        $this->context->controller->addJS($this->_path.'views/js/back_adminorder.js');
        $this->context->controller->addCSS($this->_path.'views/css/back_adminorder.css');
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    // public function hookHeader()
    // {
    //     $this->context->controller->addJS($this->_path.'/views/js/front.js');
    //     $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    // }

    // Con este hook, miramos el nuevo estado del pedido y si es Sin Stock Pagado (Verificando Stock), comprobamos los productos para averiguar cual o cuales están en permitir pedido y no tienen stock.
    // 04/08/2020 desactivamos el email de estado Sin Stock Pagado y lo enviamos aquí si en el pedido no hay ningún producto de frikilería kids. En caso de haberlo, no se envía email - Eliminado a parit de enero 2022, pasamos a enviarlo con connectif
    // 02/03/2022 Integramos le proceso para pedidos dropshipping aquí, dado que la detección de productos vendidos sin stock es la misma y se compraten muchos procesos. Primero haremos la gestión como producto vendido sin stock y después se analizará si corresponde a producto dropshipping etc
    //para poder reutilizar las funciones elhook se limitará a analizar los cambios de estado de pedido, y si el estado es el que buscamos, llamrá a otra función que analice el pedido, de este modo podemos llamar a esa nueva función sin depender del hook cuando queramos analizar un pedido el cual, sin cambio de estado, si que ha tenido cambios en los productos desde su "ficha" de pedido en backoffice (eliminar o añadir productos a petición de clinete o por error de stock con proveedor, etc)
    // array(
        // 'newOrderStatus' => (object) OrderState,
        // 'id_order' => (int) Order ID
        // );
    public function hookActionOrderStatusPostUpdate($params)
    {
        //vamos a comprobar cada cambio de estado, pero solo nos interesan los que van a Verificando stock, antiguo Sin Stock Pagado, ya que si entran en estado no pagado no queremos hacer todavía el pedido y si son pago aceptado es que tenemos stock y no hay que pedirlo
        if ($params) {
            $new_order_status = $params['newOrderStatus'];
            if (Validate::isLoadedObject($new_order_status) && $new_order_status->id == Configuration::get(PS_OS_OUTOFSTOCK_PAID)){ 
                
                $id_order = $params['id_order'];

                //el hook ya ha cumplido su cometido, llamamos a otra función que será la que comience el proceso y a la cual podemos llamar para analizar un pedido desde el backoffice del pedido. Para el proceso dropshipping queremos que se haga petición a APIs cuando llegamos aquí por cambio de estado a Verificando stock, o cuando se pida expresamente desde el backoffice, pero no cuando se hace una modificación en los productos de un pedido en backoffice, de modo que tenemos un parámetro de la siguiente función que indicará que después de procesar los productos, si hay dropshipping, se haga el proceso de API o no.
                $procesar_dropshipping = 1;
                $this->checkOrderProductosSinStock($id_order, $procesar_dropshipping);  

            } //if validate status and status=9

        } // if $params
    }

    //función que procesa un pedido, definiendo si contiene productos sin stock, si estos son dropshipping, etc. Se utiliza desde el hook de cambio de estado si este es a pedido sin stock pagado o para verificar un pedido desde el back office, en la ficha de pedido cuando se modifique este, añadiendo, eliminando o modificando algún producto
    //el parámetro $id_employee tendrá contenido cuando se llama a la función desde el back office (override adminorderscontroller) al modificar los productos de un pedido, de modo que se puede almacenar el id en la tabla productosvendidossinstock. Lo enviamos a procesaVendidosSinStock() para allí, si hay cambios en productos, utilizarlo
    //el parámetro $procesar_dropshipping valdrá 1 cuando queremos que si hay productos dropshipping y se puede llamar a API se haga. Si es 0 se estudiarán los productos y se procesará dropshipping, pero no se realizará llamada a API.
    //a día 16/03/2022 se comprobará el estado del pedido. Si no es un estado "activo" nos saldremos, ya que si no, si manipulamos un pedido antiguo enviado o cancelado, se procesarían sus productos como si fuera actual, estando probablemente todos descatalogados, y metiendose en productos vendidos sin stock. Esto hay que cambiarlo para que no se llame a esta función desde el ocntrolador como se hace ahora, sino que caundo se modifica, añade o elimina un producto, se estudie el pedido solo respecto a ese producto diferente
    public function checkOrderProductosSinStock($id_order, $procesar_dropshipping = 0, $id_employee = null) {
        $order = new Order($id_order);

        if (Validate::isLoadedObject($order)){  
            //comprobamos el estado actual del pedido. Solo continuamos si es un pedido "activo" - CAMBIAR
            if (in_array($order->current_state, array(4, 5, 6))) {            
                return;
            }

            if (!$info_dropshipping = $this->procesaPedidoSinStock($order, $procesar_dropshipping, $id_employee)) {
                //el pedido no tenía productos sin stock, o los tenía pero no había dropshipping y ya han sido procesados solo como vendidos sin stock, abandonamos el módulo
                return; 
    
            } else {                
                //si devuelve datos es que había productos dropshipping y continuamos procesándolos
                if (Configuration::get('DROPSHIPPING_PROCESO')) {
                    $this->procesaDropshipping($info_dropshipping);
                }

                return;
            }
        }

        return;
    }

    //comprobamos si un pedido tiene productos vendidos sin stock y los introducimos a la tabla productos_vendidos_sin_stock. Además, comprobamos si esos productos son dropshipping. La función devuelve false si no hay productos sin stock, también si los hay pero no son dropshipping, ya que no necesitan más proceso. Si hay dropshipping devuelve el array $info_dropshipping para procesarlo.
    //el parámetro $id_employee tendrá contenido cuando se llama a la función checkOrderProductosSinStock desde el back office (override adminorderscontroller) al modificar los productos de un pedido, de modo que se puede almacenar el id en la tabla productosvendidossinstock.
    //también puede tener un id cuando se llama a procesar un dropshipping desde back office, entonces $procesar_dropshipping será 1
    public function procesaPedidoSinStock($order, $procesar_dropshipping = 0, $id_employee = null) {
        //sacamos los productos del pedido                   
        $order_products = $order->getProducts();       
        
        //creamos un array que contendrá los ids de productos que encajen en vendido sin stock para hacer la comparación si el pedido resulta estar ya en productos_vendidos_sin_stock y poder marcar como eliminado un producto si ya no está contenido en el pedido original
        $productos_vendidos_sin_stock = array();
        
        $num_dropshipping = 0;        
        $info_dropshipping = array();
        $check_karactermania = 0;
        
        foreach ($order_products as $order_product){                                          
                        
            //si el producto no está en gestión avanzada, pasamos al siguiente
            if (!StockAvailableCore::dependsOnStock($order_product['product_id'])){
                
                continue;
            }           

            $id_product = $order_product['product_id']; 

            $product = new Product($id_product);

            //si el producto es pack nativo o virtual, pasamos a otro
            if ($product->cache_is_pack || $product->is_virtual){
               
                continue;
            }

            $id_product_attribute = $order_product['product_attribute_id'];                                    
            $product_quantity = $order_product['product_quantity']; 
            $product_quantity_in_stock = $order_product['product_quantity_in_stock']; 
            
            //product_quantity_in_stock indica las unidades disponibles en el momento de la compra sobre las compradas, es decir, si se compran 5 y hay 7, valdrá 5. Si se compran 5 y hay 3 valdrá 3. Salvo para pedidos de worten y amazon que su módulo calcula product_quantity_in_stock de otra manera. equivale al stock total disponible en el momento de compra (Product::getRealQuantity($id_product, $id_product_attribute,$id_warehouse)) para el almacén, teniendo en cuenta pedidos de materiales que suma¿?, menos el stock vendido, es decir stock disponible menos unidades en carrito, por lo que si hay 4 unidades y se vende 1 pondrá 3, si el producto no tiene stock , es 0 y se vende 1 y es uno de permitir pedido, pondrá -1.
            //si la cantidad en stock (ya signifique el total en stock del producto cuando entra por amazon o worten por error de ellos, o el total sobre las unidades necesarias en el pedido) es mayor o igual que las unidades compradas, no es sin stock, pasamos al siguiente.                   
            //hay un problema, y es que cuando se actualiza la cantidad de producto desde dentro de un pedido ya existente, solo se modifica product_quantity en order detail, product_quantity_in_stock permanece con la misma cifra que entró. Vamos a recalcular ese valor si tenemos un id_employee, que indica que venimos del backoffice de una modificación
            if ($id_employee) {
                $stock_producto = (int)Product::getQuantity($id_product, $id_product_attribute);
                //si al stock disponible le restamos las unidades vendidas y da menos de 0, el stock disponible para el pedido es el stock disponible total. Si da cero o más. el stock disponible para el pedido es el que se ha vendido
                $product_quantity_in_stock = ($stock_producto - $product_quantity < 0) ? $stock_producto : $product_quantity;
            }
            
            if ($product_quantity_in_stock >= $product_quantity) {
                
                continue;
            } else {
                //metemos ids de producto en array para comprobar a posteriori, si se da el caso de que el pedido ya entró en el proceso con anterioridad, tenemos algún producto del pedido almacenado en la tabla productos_vendidos_sin_stock que en esta ocasión ya no se encuentra en el pedido por haber sido eliminado (si por ejemplo, al ir a pedir el producto al proveedor nos dice que ya no lo tienen disponible)
                $productos_vendidos_sin_stock[] = array($id_product, $id_product_attribute);
            }                      
                                
            //como en este punto el stock se ha restado o no dependiendo del pago etc, sacamos el stock disponible sin más, con o sin transferencia
            //sacamos el método de pago.
            $payment_module = $order->module;
            if ($payment_module == 'bankwire'){                            
                //se consider un pedido por transferencia normal, que en este punto viene a Sin stock pagado después de haber entrado como sin stock no pagado, y ya se le había restado el stock
                $stock_disponible = StockAvailableCore::getQuantityAvailableByProduct($id_product, $id_product_attribute);
                                            
            } else {
                $stock_disponible = StockAvailableCore::getQuantityAvailableByProduct($id_product, $id_product_attribute) - $product_quantity;
            }       
            
                
            $product_name =  pSQL($order_product['product_name']); //añade una barra antes de las comillas o de otra barra, para que no de error sql
            $product_reference = pSQL($order_product['product_reference']); //es como addslashes($string)
            $product_supplier_reference = pSQL($order_product['product_supplier_reference']);  
            

            //14/01/2021 almacenamos en otro campo fecha_disponible la fecha available_date, y en caso de ser esta 0000-00-00, almacenamos la fecha del momento más 12 días, para pedidos de permitir pedido normal
            $available_date = $product->available_date;
            
            if ($available_date == '0000-00-00') {
                $hoy = date("Y-m-d");                            
                $hoy_mas_12 = date( "Y-m-d", strtotime( "$hoy +12 day" ) );
                $fecha_disponible = $hoy_mas_12;
            } else {
                $fecha_disponible = $available_date;
            }

            //14/01/2021 almacenamos el texto del campo de product_lang available_later del momento de compra                        
            $sql_available_later = 'SELECT available_later FROM lafrips_product_lang WHERE id_lang = 1 AND id_product = '.$id_product;
            $available_later = pSQL(Db::getInstance()->getValue($sql_available_later));            
            if (!$available_later) {
                $available_later = '';
            }

            //13/01/2021 comprobamos si tiene marcada la categoría Prepedidos - 121, si la tiene lo insertamos también
            $categorias = Product::getProductCategories($id_product); 
            $cat_prepedido = 0;                     
            if (in_array(121, $categorias)){
                $cat_prepedido = 1;
            }

            $id_default_supplier = $product->id_supplier;            
            $id_order_detail_supplier = $order_product['id_supplier'];            

            //24/06/2020 Para evitar que a veces se escapa algún producto que no tiene permitir pedido y tiene 1 de stock pero, por error, y estando en dos carros al mismo tiempo, se ha permitido la compra a dos clientes casi al mismo tiempo, quedando el stock del producto en negativo y entrando uno de los pedidos en Sin Stock Pagado, lo que hacemos es permitir que recoja este producto pero indicando luego que este producto NO tenía permitir pedido en el momento de la compra.
            //guardamos el valor en ese instante de la variable out_of_stock, así, si no lo tenía marcado lo indicaremos en la vista del módulo
            $out_of_stock = StockAvailableCore::outOfStock($id_product);

            //comprobamos si los datos ya existen en la tabla, insertamos si no, hacemos update si han cambiado, o no hacemos nada si son iguales.
            //si hacemos update, solo de cantidades, las fechas de disponibilidad etc, deben ser las del momento de la compra
            if ($info_productos_vendidos_sin_stock = $this->checkTablaVendidosSinStock($order->id, $id_product, $id_product_attribute)) {
                //devuelve un array con el id de la línea donde se encuentra, la línea existe, además devuelve quantity y eliminado
                $sql_quantity = '';
                $sql_eliminado = '';
                //si quantity es diferente la actualizamos, si eliminado es 1 lo ponemos en 0 ya que el producto vuelve a estar
                if ($product_quantity != $info_productos_vendidos_sin_stock[1]) {
                    $sql_quantity = ' product_quantity = '.$product_quantity.',
                    quantity_mod = 1, 
                    product_quantity_old = '.$info_productos_vendidos_sin_stock[1].',
                    date_mod = NOW(), ';
                }

                if ($info_productos_vendidos_sin_stock[2]) {
                    //está marcado eliminado
                    $sql_eliminado = ' eliminado = 0, ';
                }

                //aunque si estamos aquí tiene que ser porque el pedido ya existía y hemos entrado desde el back, nos aseguramos de que hay id_employee
                if ($id_employee) {
                    $sql_employee = ' id_employee_mod = '.$id_employee.', ';
                } else {
                    $sql_employee = '';
                }

                //si hay diferente cantidad, o estaba eliminado, o ambos, se hace update
                if ($sql_quantity != '' || $sql_eliminado != '') {
                    $sql_update_lafrips_productos_vendidos_sin_stock = 'UPDATE lafrips_productos_vendidos_sin_stock
                    SET
                    '.$sql_quantity.'
                    '.$sql_employee.'
                    '.$sql_eliminado.'
                    date_upd = NOW()
                    WHERE id_productos_vendidos_sin_stock = '.$info_productos_vendidos_sin_stock[0];

                    Db::getInstance()->Execute($sql_update_lafrips_productos_vendidos_sin_stock);
                }

            } else {
                //la línea no existe, hacemos insert
                //guardamos toda la info de los productos vendidos con permitir pedido y sin stock en una tabla
                //si en este punto id_employee contiene un id quiere decir que venimos del back office, y por tanto el pedido ya existía y por tanto este producto es un añadido al pedido a posteriori
                if ($id_employee) {                    
                    $sql_employee = '1,
                    '.$id_employee.',
                    NOW(), ';
                } else {
                    $sql_employee = "0,
                    0,
                    '0000-00-00 00:00:00', ";
                }

                $sql_insert_lafrips_productos_vendidos_sin_stock = "INSERT INTO lafrips_productos_vendidos_sin_stock 
                (id_order, id_order_status, payment_module, id_product, id_product_attribute, available_date, fecha_disponible, available_later, prepedido, out_of_stock, checked, product_name, stock_available, product_reference, product_supplier_reference, id_default_supplier, id_order_detail_supplier, product_quantity, anadido, id_employee_anadido, date_anadido, date_add) 
                VALUES (".$order->id." ,
                ".(int)$order->current_state." ,
                '".$payment_module."' ,
                ".$id_product." ,
                ".$id_product_attribute." ,
                '".$available_date."',
                '".$fecha_disponible."',
                '".$available_later."',
                ".$cat_prepedido." ,
                ".$out_of_stock." ,                        
                0 ,
                '".$product_name."' ,
                ".$stock_disponible." ,
                '".$product_reference."' ,
                '".$product_supplier_reference."' ,
                ".$id_default_supplier." ,
                ".$id_order_detail_supplier." ,
                ".$product_quantity." ,
                ".$sql_employee."
                NOW())";

                Db::getInstance()->Execute($sql_insert_lafrips_productos_vendidos_sin_stock);
            }

            //05/06/2023 Vamos a hacer pedidos a Karactermanía de forma automática metiendo un csv en su servidor cada vez que entre un pedido. Al no considerarse Dropshipping lo que hacemos es enviar los datos a otra función que lo gestione una vez repasado todo el pedido para productos vendidos sin stock
            if (($id_order_detail_supplier == 53) && Configuration::get('PEDIDOS_FTP_KARACTERMANIA')) {
                $check_karactermania = 1;
            }
            
            //analizamos si el producto es dropshipping. En este punto ya sabemos que es vendido sin stock
            //sacamos los id_supplier de  los proveedores que funcionan como dropshipping
            $proveedores_dropshipping = explode(",", Configuration::get('PROVEEDORES_DROPSHIPPING'));
            if (in_array($id_order_detail_supplier, $proveedores_dropshipping) &&  Configuration::get('DROPSHIPPING_PROCESO')) {   
                $num_dropshipping++;

                //marcamos en lafrips_productos_vendidos_sin_stock dropshipping = 1, esto nos será muy útil a la hora de utilizar el rescatador de pedidos y el pickpack para ignorar los productos que no pasan por almacén. Volvemos a utilizar la función checkTablaVendidosSinStock() que devuelve un array en cuya posición 0 está el id de la tabla. En este punto el producto ya tiene que estar en dicha tabla pero lo metemos en un if.
                if ($info_productos_vendidos_sin_stock = $this->checkTablaVendidosSinStock($order->id, $id_product, $id_product_attribute)) {                   
                    //devuelve un array con array(id de la tabla, cantidad, eliminado).  
                    $sql_update_lafrips_productos_vendidos_sin_stock = 'UPDATE lafrips_productos_vendidos_sin_stock
                    SET
                    dropshipping = 1
                    WHERE id_productos_vendidos_sin_stock = '.$info_productos_vendidos_sin_stock[0];
        
                    Db::getInstance()->Execute($sql_update_lafrips_productos_vendidos_sin_stock);
                } else {
                    //ha ocurrido algún error al buscar el producto en productos vendidos sin stock
                    $error = 'Error obteniendo el id de productos vendidos sin stock para marcar dropshipping, para id_order '.$order->id.', id_product '.$id_product.', id_product_attribute '.$id_product_attribute;

                    $this->insertDropshippingLog($error, $order->id, $id_order_detail_supplier);
                }

                $producto_dropshipping = array();
                //en este punto, se ha vendido un producto sin stock de proveedor dropshipping 
                $producto_dropshipping['id_supplier'] = $id_order_detail_supplier;  
                $producto_dropshipping['id_order_detail'] = $order_product['id_order_detail']; 
                $producto_dropshipping['id_product'] = $id_product;
                $producto_dropshipping['id_product_attribute'] = $id_product_attribute;
                $producto_dropshipping['product_name'] = $product_name;
                $producto_dropshipping['product_reference'] = $product_reference;
                $producto_dropshipping['product_supplier_reference'] = $product_supplier_reference;
                $producto_dropshipping['product_quantity'] = $product_quantity; 

                $info_dropshipping['dropshipping'][(int)$id_order_detail_supplier][] = $producto_dropshipping;

            }                    
                                               
        } 

        //esta función procesaPedidoSinStock() se puede ejecutar al entrar un pedido como verificando stock, o al cambiar de estado dicho pedido a verificando stock, y además cuando se hace una modificación de lso productos del pedido desde el  back office y cuando desde back office se pide ejecutar llamada a API de dropshipping. En este último caso la variable $procesar_dropshipping valdrá 1 y habrá id_employee. Si no hay id_employee pero si $procesar_dropshipping, sería el proceso natural de entrada de pedido, o cambio de estado        
        if ($num_dropshipping) {
            //hay dropshipping, sacamos información sobre el pedido y la metemos en la posición 0 del array info            
            $numero_proveedores = count($info_dropshipping['dropshipping']);
            $info_dropshipping['order']['procesar_dropshipping'] = $procesar_dropshipping; //indicamos si se llamará a API etc
            $info_dropshipping['order']['id_employee'] = $id_employee; 
            $info_dropshipping['order']['id_order'] = $order->id; 
            $info_dropshipping['order']['id_customer'] = $order->id_customer;  
            $info_dropshipping['order']['id_address_delivery'] = $order->id_address_delivery; 
            $info_dropshipping['order']['payment'] = $order->payment; 
            $info_dropshipping['order']['date_add'] = $order->date_add;             
        }        

        //enviamos a chequear si el pedido ya no contiene algún producto vendido sin stock que antes si contenía, ante la posibilidad de haber sido eliminado del pedido y no ser esta la primera vez que pasa el pedido por este proceso
        $this->checkProductosEliminados($order->id, $productos_vendidos_sin_stock, $id_employee);

        //05/06/2023 Si tenemos configurado el envío de pedidos a Karactermanía con FTP comprobamos si hay algún producto vendido sin stock suyo y procesamos
        if ($check_karactermania && Configuration::get('PEDIDOS_FTP_KARACTERMANIA')) {
            $this->gestionKaractermania($order->id);
        }

        if (empty($info_dropshipping)) {
            //si no había dropshipping devolvemos false
            return false;
        } else {
            return $info_dropshipping;
        }

    }

    //función que dado un id_order busca sus productos de Karactermanía en lafrips_productos_vendidos_sin_stock y genera el archivo necesario en el servidor FTP 
    //obtenemos de productos vendidos sin stock la info de la venta y la insertamos en lafrips_pedidos_karactermania si no existe de antemano. Si existe comprobamos cantidades y si son diferentes las pedimos (si ya fue pedido pedimos la diferencia si es superior) Después, si no está marcado como ya pedido, generamos el csv correspondiente al pedido. Finalmente comprobamos si en el pedido original hay más productos vendidos sin stock, marcamos estos como revisados y si no hay ninguno más pasamos el pedido a Esperando Productos.
    //comprobamos cada producto para saber si tiene  categoría prepedido 121, almacenado en lafrips_productos_vendidos_sin_stock, si la tienen no se solicitan por ftp
    public function gestionKaractermania($id_order) {
        $sql_productos_vendidos_sin_stock = "SELECT * FROM lafrips_productos_vendidos_sin_stock WHERE id_order_detail_supplier = 53 AND id_order = $id_order";

        $productos_karactermania = Db::getInstance()->ExecuteS($sql_productos_vendidos_sin_stock);

        if(count($productos_karactermania) > 0){ 
            foreach($productos_karactermania as $producto){
                $id_product = $producto['id_product'];
                $id_product_attribute = $producto['id_product_attribute'];
                $product_name = $producto['product_name'];
                $referencia_prestashop = $producto['product_reference'];
                $referencia_karactermania = $producto['product_supplier_reference'];
                $unidades = $producto['product_quantity'];
                $prepedido = $producto['prepedido'];
                $date_original = $producto['date_add'];

                $ean = $this->getEan($id_product, $id_product_attribute) ? $this->getEan($id_product, $id_product_attribute) : "";

                $sql_tabla_karactermania = "SELECT id_pedidos_karactermania, unidades, ftp 
                FROM lafrips_pedidos_karactermania
                WHERE id_order = $id_order
                AND id_product = $id_product
                AND id_product_attribute = $id_product_attribute";

                if ($tabla_karactermania = Db::getInstance()->getRow($sql_tabla_karactermania)) {
                    //el producto y pedido ya se encuentran, si además ftp = 1 es que ya se generó en el servidor, ignoramos la línea, si se quisiera pedir otra vez u otras cantidades para el mismo producto se hará a mano
                    if ($tabla_karactermania['ftp']) {
                        continue;
                        // if (($unidades - $tabla_karactermania['unidades']) > 0) {
                        //     $unidades = $unidades - $tabla_karactermania['unidades'];

                        //     $sql_update_tabla_karactermania = "UPDATE lafrips_pedidos_karactermania
                        //     SET
                        //     ftp = 0,
                        //     unidades = $unidades,
                        //     comentario = CONCAT(comentario, ' | Linea existia: ftp 1 a 0, unidades ".$tabla_karactermania['unidades']." a $unidades - ', DATE_FORMAT(NOW(),'%d-%m-%Y %H:%i:%s')),
                        //     date_upd = NOW()
                        //     WHERE id_order = $id_order 
                        //     AND id_product = $id_product
                        //     AND id_product_attribute = $id_product_attribute";

                        //     Db::getInstance()->execute($sql_update_tabla_karactermania);
                            
                        // } else {
                        //     continue;
                        // }
                    } else {
                        //no fue pedido por ftp, comparamos cantidad a pedir y hacemos update si es diferente. En caso de ser prepedido se modificará pero igualmente no se generará en FTP 
                        if ($unidades != $tabla_karactermania['unidades']) {                           

                            $sql_update_tabla_karactermania = "UPDATE lafrips_pedidos_karactermania
                            SET                            
                            unidades = $unidades,                            
                            comentario = CONCAT(comentario, ' | Linea existia: ftp 0 a 0, unidades ".$tabla_karactermania['unidades']." a $unidades - ', DATE_FORMAT(NOW(),'%d-%m-%Y %H:%i:%s')),
                            date_upd = NOW()
                            WHERE id_order = $id_order 
                            AND id_product = $id_product
                            AND id_product_attribute = $id_product_attribute";

                            Db::getInstance()->execute($sql_update_tabla_karactermania);
                            
                        } else {
                            continue;
                        }   
                    } 
                    
                } else {
                    //hacemos insert
                    $sql_insert = "INSERT INTO lafrips_pedidos_karactermania
                    (id_order, id_product, id_product_attribute, referencia_karactermania, unidades, prepedido, product_name, ean, referencia_prestashop, date_original, date_add)
                    VALUES
                    (
                        $id_order, $id_product, $id_product_attribute, '$referencia_karactermania', $unidades, $prepedido, '$product_name', '$ean', '$referencia_prestashop', '$date_original', NOW()
                    )";

                    Db::getInstance()->execute($sql_insert);

                }                
            }

            //ahora tenemos en lafrips_pedidos_karactermania los productos con ftp 0 y los ids correspondientes para generar el archivo. Si eran productos ya pedidos y con ftp=1 o prepedidos, se ignorarán en la sql de la función de crear ftp
            if ($this->setKaractermaniaFTP($id_order)) {
                return;
            } else {
                return;
            }
        }   

        return;
    }

    public function setKaractermaniaFTP($id_order) {
        $error_ftp = 0;
        $mensaje = "";
        $info_pedido = "";
        $ids_pedidos_karactermania = array();

        $sql_info_ftp = "SELECT id_pedidos_karactermania, id_product, id_product_attribute, product_name, referencia_prestashop, referencia_karactermania, unidades
        FROM lafrips_pedidos_karactermania
        WHERE ftp = 0
        AND prepedido = 0
        AND error = 0
        AND id_order = $id_order";

        if ($info_ftp = Db::getInstance()->executeS($sql_info_ftp)) {
            //preparamos el archivo para subir al FTP
            $delimiter = ";";            
            $path = _PS_ROOT_DIR_."/proveedores/karactermania/pedidos/";
            $filename = "PEDIDO_".date("ymd_His").".csv";

            $id_cliente = "430080613";
                
            //creamos el puntero del csv, para escritura
            //$f = fopen('php://memory', 'w');
            $file = fopen($path.$filename,'w');

            foreach($info_ftp AS $info) {
                $linea_csv = array($id_cliente, $info['referencia_karactermania'], $info['unidades']);
                fputcsv($file, $linea_csv, $delimiter);

                $info_pedido .= $id_cliente.';'.$info['referencia_karactermania'].';'.$info['unidades'].'<br>';
                $mensaje .= $id_cliente.';'.$info['referencia_karactermania'].';'.$info['unidades'].'<br>';

                //hacemos update a lafrips_productos_vendidos_sin_stock para marcar como revisado y a lafrips_pedidos_karactermania para marcar ftp a 1
                $sql_update_productos_vendidos_sin_stock = "UPDATE lafrips_productos_vendidos_sin_stock
                SET                                                      
                checked = 1,
                date_checked = NOW(),
                id_employee = 44, 
                date_upd = NOW()  
                WHERE id_order = $id_order
                AND id_product = ".$info['id_product']."
                AND id_product_attribute = ".$info['id_product_attribute'];

                Db::getInstance()->execute($sql_update_productos_vendidos_sin_stock);

                $sql_update_tabla_karactermania = "UPDATE lafrips_pedidos_karactermania
                SET                            
                ftp = 1,
                date_upd = NOW()
                WHERE id_pedidos_karactermania = ".$info['id_pedidos_karactermania'];

                Db::getInstance()->execute($sql_update_tabla_karactermania);

                //alamcenamos cada id de la tabla por si hay que revertir ftp = 1
                $ids_pedidos_karactermania[] = $info['id_pedidos_karactermania'];
            }

            //cerramos el puntero / archivo csv
            fclose($file);

            //obtenemos credenciales para FTP de Karactermanía
            $secrets_json = file_get_contents(dirname(__FILE__).'/secrets/ftp_karactermania.json');
            
            $secrets = json_decode($secrets_json, true);

            //sacamos credenciales FTP
            $ftp_server = $secrets['ftp_server']; 
            $ftp_username = $secrets['ftp_username']; 
            $ftp_password = $secrets['ftp_password']; 
            // $id_cliente = $secrets['id_cliente']; 

            //conectamos al servidor FTP
            $ftp_connection = ftp_connect($ftp_server);
            if (!$ftp_connection) {
                $error_ftp = 1;
                $mensaje .= "<br><br>Error conectando al servidor FTP - ".$ftp_server;                
            } else {
                //hacemos login en el servidor FTP
                $ftp_login = ftp_login($ftp_connection, $ftp_username, $ftp_password);
                if (!$ftp_login) {
                    $error_ftp = 1;
                    $mensaje .= "<br><br>Error haciendo login en servidor FTP";                      
                } else {
                    //creamos el archivo origen en el servidor de destino, en la carpeta por defecto a la que nos conectamos
                    //ftp_put(conexion, nombre_archivo_destino, ruta_y_nombre_archivo_origen, transfer mode FTP_ASCII o FTP_BINARY)
                    if (ftp_put($ftp_connection, $filename, $path.$filename, FTP_ASCII)) {
                        //correcto, pero nos aseguramos de que exista el archivo en destino

                    } else {
                        $error_ftp = 1;
                        $error = error_get_last();
                        $mensaje .= "<br><br>Error subiendo archivo a servidor FTP - ".$error['message'];                         
                    }
                }
            }
            
            $error_archivo = "";
            $encontrado = 0;
            if ($error_ftp) {
                $error_archivo = 'ERROR - ARCHIVO NO SUBIDO A FTP - ';
                $mensaje .= '<br><br>Información para generar a mano: <br><br>'.$filename.'<br><br>'.$info_pedido;
            } else {
                //comprobamos que el archivo exista en destino. Sacamos la lista de archivos de la carpeta destino con ftp_nlist
                //el parámetro "." indica mirar archivos de la carpeta destino o raíz. Devuelve los archivos con ./ delante del nombre
                $file_list = ftp_nlist($ftp_connection, ".");
                
                foreach ($file_list AS $key => $value) {                    
                    if ($value == "./".$filename) {
                        $encontrado = 1;
                    }
                }

                if (!$encontrado) {
                    $error_archivo = 'ERROR - ARCHIVO NO SUBIDO A FTP - ';
                    $mensaje .= '<br><br>Archivo no encontrado en destino después de crearlo a FTP
                    <br><br>Información para generar a mano: <br><br>'.$filename.'<br><br>'.$info_pedido;                    
                }
            }

            //si hubo errores ahora actualizamos el error en las tablas
            if ($error_ftp || !$encontrado) {
                //marcamos ftp a 0 para cada línea del csv, cuyos ids de tabla pedidos_karactermania están en el array $ids_pedidos_karactermania 
                foreach ($ids_pedidos_karactermania AS $id_pedidos_karactermania) {
                    $sql_update_tabla_karactermania = "UPDATE lafrips_pedidos_karactermania
                    SET                            
                    ftp = 0,
                    error = 1,
                    comentario = CONCAT(comentario, ' | Error: CSV no generado en directorio FTP destino - ', DATE_FORMAT(NOW(),'%d-%m-%Y %H:%i:%s')),
                    date_upd = NOW()
                    WHERE id_pedidos_karactermania = $id_pedidos_karactermania";

                    Db::getInstance()->execute($sql_update_tabla_karactermania);
                } 
                //quitamos revisado de lafrips_productos_vendidos_sin_stock
                foreach($info_ftp AS $info) {    
                    //hacemos update a lafrips_productos_vendidos_sin_stock para marcar como revisado 0
                    $sql_update_productos_vendidos_sin_stock = "UPDATE lafrips_productos_vendidos_sin_stock
                    SET                                                      
                    checked = 0,
                    date_checked = NOW(),
                    id_employee = 44, 
                    date_upd = NOW() 
                    WHERE id_order = $id_order
                    AND id_product = ".$info['id_product']."
                    AND id_product_attribute = ".$info['id_product_attribute'];
    
                    Db::getInstance()->execute($sql_update_productos_vendidos_sin_stock);
                }       
            } else {
                //si no ha habido errores metemos un mensaje CustomerMessage dentro del pedido sobre el pedido a Karactermanía y después comprobamos si el pedido contiene algún otro producto vendido sin stock que no esté revisado. Si lo tiene no hacemos nada más, si no lo tienecambiamos el estado a Esperando productos
                //primero ponemos el mensaje al pedido
                if (!$id_customer_thread = $this->setMensajePedido($id_order, $info_ftp)) {
                    $error_archivo .= "WARNING - Error mensaje interno para pedido - ";
                    $mensaje .= "<br><br>Error añadiendo mensaje interno de compra a pedido";
                }

                //ahora procesamos si hay que cambiar de estado - LO PONEMOS EN PROCESO PROGRAMADO PARA TODOS PROVEEDORES Y PEDIDOS
                // if (!$this->checkCambioEsperandoProductos($id_order, $id_customer_thread)) {
                //     $error_archivo .= "WARNING - Error cambiando estado pedido - ";
                //     $mensaje .= "<br><br>Error cambiando estado de pedido a Esperando Productos";
                // }
            }

            //cerramos conexión
            ftp_close($ftp_connection);

            //enviamos email de aviso con mensaje, dependiendo de si hay error
            $info = [];                
            $info['{firstname}'] = 'Sergio';
            $info['{archivo_expediciones}'] = $error_archivo.'Pedido a Karactermanía '.date("Y-m-d H:i:s");
            $info['{errores}'] = $mensaje;
            // print_r($info);
            // $info['{order_name}'] = $order->getUniqReference();
            @Mail::Send(
                1,
                'aviso_error_expedicion_cerda', //plantilla
                Mail::l($error_archivo.'Pedido realizado a Karactermanía '.date("Y-m-d H:i:s"), 1),
                $info,
                'sergio@lafrikileria.com',
                'Sergio',
                null,
                null,
                null,
                null,
                _PS_MAIL_DIR_,
                true,
                1
            );

        }

        return;
    }

    //función que añade mensaje interno a pedido para los productos sin stock vendidos (por ahora Karactermanía)
    //devuelve false si no se puede generar mensajes, o id de customer thread si es correcto, para posible cambio de estado
    public function setMensajePedido($id_order, $info_productos) {
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
                $mensaje_pedido_sin_stock_producto_karactermania = 'Producto Karactermanía vendido sin stock: 
                Nombre: '.$producto['product_name'].'
                Ref. Prestashop: '.trim($producto['referencia_prestashop']).' 
                Ref. Proveedor: '.trim($producto['referencia_karactermania']).'
                revisado automáticamente al entrar a Verificando Stock el '.$fecha;

                $cm_interno = new CustomerMessage();
                $cm_interno->id_customer_thread = $ct->id;
                $cm_interno->id_employee = 44; 
                $cm_interno->message = $mensaje_pedido_sin_stock_producto_karactermania;
                $cm_interno->private = 1;                
                $cm_interno->add();
            }            
        } else {
            return false;
        }     

        return $ct->id;
    }

    //función que comprueba si el pedido tiene productos vendidos sin stock sin revisar y dependiendo del caso cambia el estado de pedido a esperando productos, añadiendo mensaje al pedido
    public function checkCambioEsperandoProductos($id_order, $id_customer_thread) {
        //comprobamos si después de revisar los productos aún quedan más productos vendidos sin stock en ese pedido
        $sql_otros_productos_pedido = "SELECT id_product
        FROM lafrips_productos_vendidos_sin_stock 
        WHERE checked = 0
        AND id_order = $id_order";

        //si no encuentra más productos en el pedido, comprobamos su estado actual, y si es verificando stock/sin stock pagado lo pasamos a Esperando productos, si es cualquier otro estado, no hacemos nada
        if (!Db::getInstance()->ExecuteS($sql_otros_productos_pedido)) { 
            $order = new Order($id_order);   
            
            //si el estado actual es verificando stock/sin stock pagado lo cambiamos
            if ($order->current_state == Configuration::get(PS_OS_OUTOFSTOCK_PAID)){
                //cambiamos estado y metemos mensaje a pedido, actualizamos el estado en lafrips_productos_vendidos_sin_stock
                //sacamos id_status de Esperando productos
                $sql_id_esperando_productos = "SELECT ost.id_order_state
                FROM lafrips_order_state ost
                JOIN lafrips_order_state_lang osl ON osl.id_order_state = ost.id_order_state AND osl.id_lang = 1
                WHERE osl.name = 'Esperando productos'
                AND ost.deleted = 0";
                $id_esperando_productos = Db::getInstance()->getValue($sql_id_esperando_productos);                

                //se genera un objeto $history para crear los movimientos, asignandole el id del pedido sobre el que trabajamos            
                //cambiamos estado de orden a Esperando productos, ponemos id_employee 44 que es Automatizador, para log
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

				$insert_frik_pedidos_cambiados = "INSERT INTO frik_pedidos_cambiados 
				(id_order, estado_inicial, estado_final, transporte_inicial, transporte_final, proceso, date_add) 
				VALUES ($id_order ,
				".$order->current_state." ,
				$id_esperando_productos ,
				$id_carrier_orders ,
                $id_carrier_orders ,
                'A Esperando Productos - Pedido Karactermanía - Automático',
				NOW())";

				Db::getInstance()->Execute($insert_frik_pedidos_cambiados);

                //metemos mensaje privado al pedido
                //comprobamos si vino valor en $id_customer_thread, si es false no metemos mensaje al pedido, si no añadimos mensaje del cambio de estado
                if ($id_customer_thread) {
                    $fecha = date("d-m-Y H:i:s");
                    $mensaje_pedido_sin_stock_estado = 'Pedido Karactermanía cambiado a Esperando Productos                     
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

    public function getEan($id_product, $id_product_attribute) {
        $sql_ean = "SELECT IFNULL(pat.ean13, pro.ean13) AS ean 
        FROM lafrips_product pro
        JOIN lafrips_stock_available ava ON ava.id_product = pro.id_product
        LEFT JOIN lafrips_product_attribute pat ON pat.id_product = ava.id_product AND pat.id_product_attribute = ava.id_product_attribute
        WHERE ava.id_product = $id_product
        AND ava.id_product_attribute = $id_product_attribute";

        return Db::getInstance()->getValue($sql_ean);
    }

    //comprobamos si un pedido - producto ya existe en la tabla productos vendidos sin stock
    public function checkTablaVendidosSinStock($id_order, $id_product, $id_product_attribute) {
        $sql_existe_linea = 'SELECT id_productos_vendidos_sin_stock, product_quantity, eliminado 
        FROM lafrips_productos_vendidos_sin_stock 
        WHERE id_order = '.$id_order.'
        AND id_product = '.$id_product.'
        AND id_product_attribute = '.$id_product_attribute;

        if ($existe_linea = Db::getInstance()->ExecuteS($sql_existe_linea)) {
            return array($existe_linea[0]['id_productos_vendidos_sin_stock'], $existe_linea[0]['product_quantity'], $existe_linea[0]['eliminado']);
        }    

        return false;     
    }

    //función que comprueba que en el caso de existir ya el pedido en productos vendidos sin stock, si se ha eliminado algún producto del pedido y ya no se encontraba, se marque como eliminado
    public function checkProductosEliminados($id_order, $productos_vendidos_sin_stock, $id_employee = null) {
        //en $productos_vendidos_sin_stock tenemos los productos sin stock del pedido en este momento, comprobamos si en la tabla hay algún producto más para ese pedido, es decir, sacamos los que NO coinciden con los parámetros
        //tenemos  que crear la sql en función del contenido del array. Formato select
        // SELECT id_productos_vendidos_sin_stock 
        //     FROM frikileria_bd_test.lafrips_productos_vendidos_sin_stock
        //     WHERE NOT (id_product = 23908 AND id_product_attribute = 40083)
        //         AND NOT (id_product = 8683 AND id_product_attribute = 0)
        //     AND id_order = 320365
        // AND eliminado = 0
        //hacemos directamente el UPDATE
        // UPDATE frikileria_bd_test.lafrips_productos_vendidos_sin_stock
        // SET
        // eliminado = 1,
        // date_upd = NOW()
        // WHERE NOT (id_product = 23908 AND id_product_attribute = 40083)
        //     AND NOT (id_product = 1993 AND id_product_attribute = 0)
        //     AND NOT (id_product = 8683 AND id_product_attribute = 0)
        // AND id_order = 320365

        //esta función se ejcuta siempre que entramos en el proceso, independientemente si se ha lanzado al crearse el pedido o por una modificación en los porductos del pedido desde el back office. Para indicar si se ha eliminado un producto por un empleado, sabemos que la primera vez que se detecte el producto eliminado, aquí tendrá eliminado = 0, y además vendremos del backoffice, por lo tanto id_employee contendrá un id, de modo que si tenemos un producto que no está en el array de productos $productos_vendidos_sin_stock y tiene eliminado 0, y id_employee tiene un id, podemos hacer update de eliminado a 1, y el id y date de eliminado y empleado . Si eliminado ya está a 1 , no hay que hacerle nada y no entrará en la consulta      
        if ($id_employee) {
            $sql_employee = ' id_employee_eliminado = '.$id_employee.', ';
        } else {
            $sql_employee = '';
        }

        $sql_productos_eliminados = 'UPDATE lafrips_productos_vendidos_sin_stock
        SET
        eliminado = 1,
        '.$sql_employee.'
        date_eliminado = NOW(),
        date_upd = NOW()
        WHERE NOT ';
        $contador = 0;
        foreach ($productos_vendidos_sin_stock AS $producto) {
            if (!$contador) {
                $sql_productos_eliminados .= '(id_product = '.$producto[0].' AND id_product_attribute = '.$producto[1].') ';
            } else {
                $sql_productos_eliminados .= 'AND NOT (id_product = '.$producto[0].' AND id_product_attribute = '.$producto[1].') ';
            }
            $contador++;
        }
        $sql_productos_eliminados .= 'AND eliminado = 0 AND id_order = '.$id_order;

        Db::getInstance()->Execute($sql_productos_eliminados);

        return;
    }

    //función que procesa la información sobre el pedido relativa a dropshipping
    public function procesaDropshipping($info_dropshipping) {
        //hay que comprobar si el pedido dropshipping ya está recogido, y si se hizo la petición correctamente. Si el pedido existe en la tabla dropshipping y está marcado como procesado o cancelado, no continuamos. Cancelado se puede marcar o desmarcar desde el backoffice.
        //si existe pero no procesado o cancelado, continuamos procesando el resto.
        // $estado = $this->estadoPedidoDropshipping();


        //si un pedido ya se solictó a la API del proveedor y se marcó procesado,es decir, correcto y pendiente de envío, no lo volvemos a gestionar salvo para actualizar su estado. Esta comprobación debería ser hecha por proveedor dropshipping en pedido. Si se hace aquí es el $info_dropshipping con todo, si hay dos proveedores dropshipping y uno está finalizado y otro no, aquí no sale bien
        //si se gestionó pero quedó sin confirmar por error de stock o lo que sea, se debe actualizar sus datos (comprobar dirección y productos) pero no hacer la petición, a partir de la primera la pasamos a manual.
        //también hay que tener en cuenta si al entrar el pedido se hizo el intento y falló la conexión. En ese caso si que debe intentarse de nuevo, con un cron¿?
        //también hay que dar la opción tanto a cancelar el pedido a la api como a repetir la solicitud a la api

        //TO DO - en este punto comprobamos la existencia y estado del pedido dropshipping si está procesado. Si no existe en lafrips_dropshipping continuamos normalmente, obteniendo datos y llamando a api (es primera vez que el estado pasa por verificando stock o es un pedido al que se le ha metido producto dropshipping a posteriori). 
        //Si existe y el estado del pedido es "ok" (campo procesado en tabla dropshipping? que se marca si la api devuelve ok?) nos saltaremos todo esto que viene, el pedido está cerrado para dropshipping. 
        //En caso de existir pero no ok, tenemos que repasar todos los datos del pedido como si fuera nuevo, pero sin lanzar petición api, ya que la idea es que esto se procese también cuando cambia un producto dentro del pedido, y no debe intentar hacerse petición a la api con cada posible cambio, sino manualmente
        //tiene que poder diferenciarse una ejecución desde cambio de estado de una ejecución por cambio en los productos. La clave sería que si entra por el hook añadimos una variable y si entra desde el override del controlador de order añadimos otra, o viceversa, o solo una.
        //cada vez que se modifique algo de productos dentro de un pedido, se lanza todo el proceso de detección de productos sin stock y de dropshipping. Pero la llamada a api no se hará más que con el cambio de estado a verificando stock, que sucede automáticamente al entrar el pedido.
        //cuando falla la primera llamada a api para un pedido, debemos ejecutar un cron que la repita cada por ejemplo 15 minutos. Pero dicha llamada solo debe hacerse con pedidos que han dado error en la api, no que haya fallado el pedido por falta de stock o error en datos, de modo que unos deben marcarse como error 
        //también posibilidad de marcar Enviar a almacén aunque no sea necesario según lo programado. O al contrario, forzar entrega en dirección de cliente. Además de un botón que actualice la dirección, es decir, revise la dirección almacenada para dropshipping y la compare con la actual del pedido, si se ha cambiado después de entrar el pedido
        //habría que hacer update en lafrips_dropshipping de envio_almacen. Por ahora es general para el pedido, pero si hay varios proveedores dropshipping con diferentes condiciones de entrega, quizás haya que separar este proceso
        
        
        //necesitamos la información de la dirección de entrega, ya sea al cliente o a nuestro almacén
        $id_order = $info_dropshipping['order']['id_order'];

        //llamamos a setAddressInfo() que nos devuelve el id de la dirección que se usará en lafrips_dropshipping_address (sea nueva o una anterior del mismo pedido)
        if (!$id_last_insert_lafrips_dropshipping_address = $this->setAddressInfo($id_order)) {
            //ha ocurrido algún error con la dirección, metemos un log y salimos
            $error = 'Error obteniendo o almacenando la dirección de entrega';

            $this->insertDropshippingLog($error, $id_order);

            return;
        }

        foreach ($info_dropshipping['dropshipping'] AS $key => $info_productos) {
            //$key es el id_supplier de dropshipping e info_productos el array que contiene cada producto dropshipping para ese proveedor. Hacemos un insert en la tabla de pedidos dropshipping por cada proveedor implicado, y un insert por producto en la tabla de dropshipping de cada proveedor
            $id_supplier = $key;

            //intentamos insertar en lafrips_dropshipping los datos del pedido/proveedor. Si devuelve un id es correcto (se acaba de insertar o ya existía y está no cancelado y no procesado) y pasamos a analizar los productos. Si devuelve null habría dado un error, y si devuelve 'stop' se trata de un pedido que ya existe en la tabla y está o bien cancelado o bien procesado y no queremos continuar. Si es cancelado se dará la opción de quitar cancelado desde el back office
            $id_last_insert_lafrips_dropshipping = $this->insertDropshipping($info_dropshipping, $id_supplier, $id_last_insert_lafrips_dropshipping_address );
            if (!$id_last_insert_lafrips_dropshipping) {
                //ha ocurrido algún error con el pedido, metemos un log y salimos
                $error = 'Error insertando la información de pedido / proveedor en lafrips_dropshipping';

                $this->insertDropshippingLog($error, $id_order, $id_supplier, null, $id_last_insert_lafrips_dropshipping_address);

                continue;
            } elseif ($id_last_insert_lafrips_dropshipping == 'stop') {
                //pedido procesado o cancelado
                continue;
            }
            
            //ahora enviamos los productos a la función correspondiente a cada proveedor dropshipping, los distribuimos con un switch que habrá que actualizar cada vez que se añada un proveedor dropshipping
            //a 18/02/2022 solo trabajamos con Disfrazzes
            //02/06/2022 añadir Globomatik
            //14/06/2022 - 01/08/2022 Añadimos Printful como dropshipping, de momento sin gestión
            //22/06/2022 añado gestión DMI
            //en $info_dropshipping['order']['procesar_dropshipping'] tenemos 1 si hay que llegar hasta hacer petición API y 0 si solo procesar pedido y productos. en $info_dropshipping['order']['id_employee'] tenemos el id_employee del empleado que ha pedido llamada a API. Si es null es que hemos llegado por cambio de estado.
            switch ($id_supplier) {
                case 161: //disfrazzes
                    //primero comprobamos si lafrips_dropshipping_disfrazzes contiene ya el pedido o hay que insertarlo
                    if (!$id_dropshipping_disfrazzes = Disfrazzes::checkTablaDropshippingDisfrazzes($id_order, $id_last_insert_lafrips_dropshipping)) {
                        //error introduciendo el pedido a DropshippingDisfrazzes, marcamos la tabla dropshipping con error = 1
                        $sql_update_lafrips_dropshipping = 'UPDATE lafrips_dropshipping      
                        SET                                
                        error = 1, 
                        date_upd = NOW()
                        WHERE id_dropshipping = '.$id_last_insert_lafrips_dropshipping;

                        Db::getInstance()->Execute($sql_update_lafrips_dropshipping);
                        
                        $error = 'Error introduciendo pedido a tabla Disfrazzes';

                        $this->insertDropshippingLog($error, $id_order, $id_supplier, null, null, $id_last_insert_lafrips_dropshipping);

                        break;
                    }

                    //ahora chequeamos los productos disfrazzes y los metemos a lafrips_dropshipping_disfrazzes_productos
                    if (!Disfrazzes::productosProveedorDisfrazzes($info_productos, $id_order, $id_dropshipping_disfrazzes)) {    
                        //ha ocurrido algún error con los productos, metemos un log y salimos
                        $error = 'Error insertando/comprobando la información de productos Disfrazzes';

                        $this->insertDropshippingLog($error, $id_order, $id_supplier, null, $id_last_insert_lafrips_dropshipping_address, $id_last_insert_lafrips_dropshipping);
                    } else {
                        //llamamos a la función que hace el pedido a la API, 
                        //en $info_dropshipping['order']['procesar_dropshipping'] tenemos 1 si hay que llegar hasta hacer petición API y 0 si solo procesar pedido y productos. en $info_dropshipping['order']['id_employee'] tenemos el id_employee del empleado que ha pedido llamada a API. Si es null es que hemos llegado por cambio de estado.
                        if ($info_dropshipping['order']['procesar_dropshipping']) {
                            if (!Disfrazzes::apiDisfrazzesSolicitud($id_last_insert_lafrips_dropshipping, $info_dropshipping['order']['id_employee'])) {
                                //error procesando los productos o haciendo el pedido, marcamos la tabla dropshipping con error = 1
                                $sql_update_lafrips_dropshipping = 'UPDATE lafrips_dropshipping      
                                SET                                
                                error = 1, 
                                date_upd = NOW()
                                WHERE id_dropshipping = '.$id_last_insert_lafrips_dropshipping;
    
                                Db::getInstance()->Execute($sql_update_lafrips_dropshipping);
                                
                                $error = 'Error haciendo petición a API Disfrazzes';
    
                                $this->insertDropshippingLog($error, $id_order, $id_supplier, null, null, $id_last_insert_lafrips_dropshipping);
                               
                            }
                        }
                        
                    }

                    break;

                case 160: //DMI
                    //primero comprobamos si lafrips_dropshipping_dmi contiene ya el pedido o hay que insertarlo
                    if (!$id_dropshipping_dmi = Dmi::checkTablaDropshippingDmi($id_order, $id_last_insert_lafrips_dropshipping)) {
                        //error introduciendo el pedido a DropshippingDmi, marcamos la tabla dropshipping con error = 1
                        $sql_update_lafrips_dropshipping = 'UPDATE lafrips_dropshipping      
                        SET                                
                        error = 1, 
                        date_upd = NOW()
                        WHERE id_dropshipping = '.$id_last_insert_lafrips_dropshipping;

                        Db::getInstance()->Execute($sql_update_lafrips_dropshipping);
                        
                        $error = 'Error introduciendo pedido a tabla DMI';

                        $this->insertDropshippingLog($error, $id_order, $id_supplier, null, null, $id_last_insert_lafrips_dropshipping);

                        break;
                    }

                    //ahora chequeamos los productos dmi y los metemos a lafrips_dropshipping_dmi_productos
                    if (!Dmi::productosProveedorDmi($info_productos, $id_order, $id_dropshipping_dmi)) {    
                        //ha ocurrido algún error con los productos, metemos un log y salimos
                        $error = 'Error insertando/comprobando la información de productos DMI';

                        $this->insertDropshippingLog($error, $id_order, $id_supplier, null, $id_last_insert_lafrips_dropshipping_address, $id_last_insert_lafrips_dropshipping);
                    } else {
                        //llamamos a la función que hace el pedido a la API, 
                        //en $info_dropshipping['order']['procesar_dropshipping'] tenemos 1 si hay que llegar hasta hacer petición API y 0 si solo procesar pedido y productos. en $info_dropshipping['order']['id_employee'] tenemos el id_employee del empleado que ha pedido llamada a API. Si es null es que hemos llegado por cambio de estado.
                        if ($info_dropshipping['order']['procesar_dropshipping']) {
                            if (!Dmi::apiDmiSolicitud($id_last_insert_lafrips_dropshipping, $info_dropshipping['order']['id_employee'])) {
                                //error procesando los productos o haciendo el pedido, marcamos la tabla dropshipping con error = 1
                                $sql_update_lafrips_dropshipping = 'UPDATE lafrips_dropshipping      
                                SET                                
                                error = 1, 
                                date_upd = NOW()
                                WHERE id_dropshipping = '.$id_last_insert_lafrips_dropshipping;
    
                                Db::getInstance()->Execute($sql_update_lafrips_dropshipping);
                                
                                $error = 'Error haciendo petición a API DMI';
    
                                $this->insertDropshippingLog($error, $id_order, $id_supplier, null, null, $id_last_insert_lafrips_dropshipping);
                               
                            }
                        }
                        
                    }

                    break;

                case 156: //Globomatik
                    //primero comprobamos si lafrips_dropshipping_globomatik contiene ya el pedido o hay que insertarlo
                    if (!$id_dropshipping_globomatik = Globomatik::checkTablaDropshippingGlobomatik($id_order, $id_last_insert_lafrips_dropshipping)) {
                        //error introduciendo el pedido a DropshippingGlobomatik, marcamos la tabla dropshipping con error = 1
                        $sql_update_lafrips_dropshipping = 'UPDATE lafrips_dropshipping      
                        SET                                
                        error = 1, 
                        date_upd = NOW()
                        WHERE id_dropshipping = '.$id_last_insert_lafrips_dropshipping;

                        Db::getInstance()->Execute($sql_update_lafrips_dropshipping);
                        
                        $error = 'Error introduciendo pedido a tabla Globomatik';

                        $this->insertDropshippingLog($error, $id_order, $id_supplier, null, null, $id_last_insert_lafrips_dropshipping);

                        break;
                    }

                    //ahora chequeamos los productos globomatik y los metemos a lafrips_dropshipping_globomatik_productos
                    if (!Globomatik::productosProveedorGlobomatik($info_productos, $id_order, $id_dropshipping_globomatik)) {    
                        //ha ocurrido algún error con los productos, metemos un log y salimos
                        $error = 'Error insertando/comprobando la información de productos Globomatik';

                        $this->insertDropshippingLog($error, $id_order, $id_supplier, null, $id_last_insert_lafrips_dropshipping_address, $id_last_insert_lafrips_dropshipping);
                    } else {
                        //llamamos a la función que hace el pedido a la API, 
                        //en $info_dropshipping['order']['procesar_dropshipping'] tenemos 1 si hay que llegar hasta hacer petición API y 0 si solo procesar pedido y productos. en $info_dropshipping['order']['id_employee'] tenemos el id_employee del empleado que ha pedido llamada a API. Si es null es que hemos llegado por cambio de estado.
                        if ($info_dropshipping['order']['procesar_dropshipping']) {
                            if (!Globomatik::apiGlobomatikSolicitud($id_last_insert_lafrips_dropshipping, $info_dropshipping['order']['id_employee'])) {
                                //error procesando los productos o haciendo el pedido, marcamos la tabla dropshipping con error = 1
                                $sql_update_lafrips_dropshipping = 'UPDATE lafrips_dropshipping      
                                SET                                
                                error = 1, 
                                date_upd = NOW()
                                WHERE id_dropshipping = '.$id_last_insert_lafrips_dropshipping;
    
                                Db::getInstance()->Execute($sql_update_lafrips_dropshipping);
                                
                                $error = 'Error haciendo petición a API Globomatik';
    
                                $this->insertDropshippingLog($error, $id_order, $id_supplier, null, null, $id_last_insert_lafrips_dropshipping);
                               
                            }
                        }
                        
                    }

                    break;

                case 159: //Mars Gaming
                    //aviso por email desde apiMarsgaming(), de momento no tenemos proceso de API   
                    if ($info_dropshipping['order']['procesar_dropshipping']) {                 
                        $this->apiMarsgaming($id_order);
                    }
                    
                    break;

                case 163: //Printful
                    //aviso por email desde apiPrintful()
                    if ($info_dropshipping['order']['procesar_dropshipping']) {
                        $this->apiPrintful($id_order);
                    }

                    break;

                default:
                    //el id_supplier no corresponde a ninguno de los proveedores dropshipping que tenemos contemplados, o aún no he actualizado este módulo para un nuevo proveedor.  Marcamos error en lafrips_dropshipping y envío email aviso de momento
                    $mensaje = '<h1>Pedido dropshipping id '.$id_order.' - Su id_supplier '.$id_supplier.' no corresponde a ninguno de los configurados</h1>';
        
                    $cuentas = array('sergio@lafrikileria.com');

                    $this->enviaEmail($cuentas, $mensaje, 'Error - Proveedor desconocido', $id_order); 

                                        
            }
        }

        return;
    }

    //función que recibe el id_order del pedido y prepara los datos correctos de la dirección de entrega. Tiene en cuenta que los pedidos a España península y Baleares respetarán los datos del cliente ya que a 21/02/2022 Disfrazzes los enviará a ellos directamente. Los pedidos a cualquier otro destino (Canarias, Ceuta y Melilla, extranjero) llevarán los datos del almacén para recibirlos nosotros y enviarlos desde nuestro almacén.
    //25/04/2022 hasta acotar que "vendedores" dropshipping envían a cada sitio, Baleares también pasa a implicar envío previo a almacén (Globomatik no envía a Baleares)
    //habría que marcar como envio_almacen en la tabla lafrips_dropshipping a 1 por cada proveedor si cada uno tiene unas condiciones, pero de momento se hace general
    // Los pedidos con pago Contra reembolso los dirigimos a almacén también
    //devuelve el id del insert en la tabla como retorno de la llamada a la función insertDropshippingAddress    
    public function setAddressInfo($id_order) {
        //sacamos el método de pago, si es Contrareembolso o ClickCanarias, se enviará a almacén, sino, comprobamos id_address_delivery para saber a donde va y si se desvía a almacén. 
        $order = new Order($id_order);

        if (Validate::isLoadedObject($order)) {
            $almacen = 0;

            if ($order->module == 'codfee' || $order->module == 'clickcanarias') {
                $almacen = 1;
            } 
            
            //obtenemos los datos de la dirección de entrega
            //20/09/2022 Obtenemos solo un teléfono aunque haya dos, ya que alguna Api no acepta algo tan largo como 2 teléfonos unidos. Si está phone_mobile y si no phone.
            // CONCAT(IF(CHAR_LENGTH(adr.phone) < 7,"", adr.phone), IF(CHAR_LENGTH(adr.phone) < 7 OR CHAR_LENGTH(adr.phone_mobile) < 7,"","-"), IF(CHAR_LENGTH(adr.phone_mobile) < 7,"", adr.phone_mobile)) AS phone
            $sql_info_address = 'SELECT adr.id_address AS id_address_delivery, adr.id_customer AS id_customer, adr.firstname AS firstname, 
            adr.lastname AS lastname, adr.company AS company, IFNULL(cus.email,"No email") AS email, 
            IF(adr.phone_mobile != "",adr.phone_mobile, adr.phone) AS phone,
            CONCAT(adr.address1,IF(adr.address2 = "",""," "),IF(adr.address2 = "","",adr.address2)) AS address1, 
            adr.postcode AS postcode, adr.city AS city, sta.name AS provincia, col.name AS country,
            adr.other AS other, adr.dni AS dni, adr.id_country AS id_country, adr.id_state AS id_state
            FROM lafrips_address adr
            LEFT JOIN lafrips_customer cus ON cus.id_customer = adr.id_customer
            LEFT JOIN lafrips_state sta ON adr.id_state = sta.id_state
            LEFT JOIN lafrips_country_lang col ON adr.id_country = col.id_country AND col.id_lang = 1
            WHERE adr.id_address = '.$order->id_address_delivery;

            if ($info_address = Db::getInstance()->ExecuteS($sql_info_address)) {
                //comprobamos si el pedido tiene como destino España península o Baleares (Baleares no desde 25/04/2022, añado if para id_country 6 e id_state 321)  
                //si id_country no es 6 , que engloba península y Baleares, enviamos a almacén.
                //Además, por errores que a veces se crea una dirección con id_country 6 pero provincia de Canarias o Ceuta-Melilla, si state es 363 o 364, 368 o 369 (Ceuta y Melilla) o 339, 351, 365, 366 (Canarias), enviamos a almacén
                if (($info_address[0]['id_country'] != 6) || in_array($info_address[0]['id_state'], array(339, 351, 363, 364, 365, 366, 368, 369)) || ($info_address[0]['id_country'] == 6 && $info_address[0]['id_state'] == 321)) {
                    $almacen = 1;
                } 

                $info_address[0]['envio_almacen'] = $almacen;
                $info_address[0]['id_order'] = $id_order;
                $info_address[0]['direccion_ok'] = 1;

                return $this->insertDropshippingAddress($info_address);
            }
                
        }             
        //si hay error en pedido o no se obtiene dirección (clickcanarias "rompe" la dirección de envío del pedido) se interpreta como entrega en almacén, llamamos a insertar como dirección vacía y entrega en almacén
        $info_address = array();
        $info_address[0]['direccion_ok'] = 0;
        $info_address[0]['id_order'] = $id_order;
        return $this->insertDropshippingAddress($info_address);
    }

    //función que recibe la info de la dirección y la inserta en lafrips_dropshipping_address, devolviendo el id del insert. 
    // para gestionar los casos en que un pedido se esté modificando desde back office y se le cambie la dirección de entrega, debemos comprobar que el id_addrerss_delivery del pedido es el mismo que ya existe, si no lo es marcaremos la dirección que tengamos como deleted e insertaremos la nueva. Si lo es haremos update a los campos diferentes si los hay. Si no existe dirección para el pedido utilizamos la que llega en la función ya que sería la primera vez que se ejcuta para el pedido
    public function insertDropshippingAddress($info_address) {
        //si $info_address direccion_ok es 0, se marca como envio almacén, etc        
        if (!$info_address[0]['direccion_ok']) {
            //no tenemos dirección, marcamos el pedido con error = 1¿? Enviamos a almacén
            $envio_almacen = 1;
            $id_order = $info_address[0]['id_order'];
            $id_customer = '';
            $id_address_delivery = '';
            $firstname = '';
            $lastname = '';
            $company = '';
            $email = '';
            $phone = '';
            $address1 = '';
            $postcode = '';
            $city = '';
            $provincia = '';
            $country = '';
            $other = '';
            $dni = '';
        } else {
            $envio_almacen = $info_address[0]['envio_almacen'];
            $id_order = $info_address[0]['id_order'];
            $id_customer = $info_address[0]['id_customer'];
            $id_address_delivery = $info_address[0]['id_address_delivery'];
            $firstname = trim($info_address[0]['firstname']);
            $lastname = trim($info_address[0]['lastname']);
            $company = trim($info_address[0]['company']);
            $email = $info_address[0]['email'];
            $phone = trim($info_address[0]['phone']);
            $address1 = trim($info_address[0]['address1']);
            $postcode = $info_address[0]['postcode'];
            $city = $info_address[0]['city'];
            $provincia = $info_address[0]['provincia'];
            $country = $info_address[0]['country'];
            $other = trim($info_address[0]['other']);
            $dni = trim($info_address[0]['dni']);
        }

        //comprobamos si existe dirección para entrega de este pedido dropshipping. 
        $existe = $this->checkTablaDropshippingAddress($info_address);

        //la función checkTablaDropshippingAddress() devuelve 'insert' para indicar que hagamosinsert de cero con la dirección actual, y devuelve un id para hacer update a ese id_dropshipping_address dado que ya existe una dirección con el mismo id_address_delivery para el pedido
        if ($existe[0] == 'insert') {
            //almacenamos la dirección en lafrips_dropshipping_address y guardamos el id de la inserción
            $sql_insert_lafrips_dropshipping_address = 'INSERT INTO lafrips_dropshipping_address
                (id_order, id_customer, email, id_address_delivery, firstname, lastname, company, address1, postcode, city, provincia, country, phone, other, dni, envio_almacen, date_add) 
                VALUES 
                ('.$id_order.',
                '.$id_customer.',
                "'.$email.'", 
                '.$id_address_delivery.', 
                "'.$firstname.'", 
                "'.$lastname.'", 
                "'.$company.'", 
                "'.$address1.'", 
                "'.$postcode.'", 
                "'.$city.'", 
                "'.$provincia.'", 
                "'.$country.'", 
                "'.$phone.'", 
                "'.$other.'", 
                "'.$dni.'", 
                '.$envio_almacen.',
                NOW())';
            Db::getInstance()->executeS($sql_insert_lafrips_dropshipping_address);

            //devolvemos el id_dropshipping_address recién insertado
            return Db::getInstance()->Insert_ID();  

        } elseif ($existe[0] == 'update') {
            //Algún campo de la dirección, que ya tenemos, es diferente. hacemos update sobre la línea de dropshipping_address con el id que hemos recibido en el segundo campo del array $existe. No cambiamos id_customer ni email
            $sql_update_lafrips_dropshipping_address = 'UPDATE lafrips_dropshipping_address
            SET
            email = "'.$email.'",
            firstname = "'.$firstname.'",
            lastname = "'.$lastname.'", 
            company = "'.$company.'", 
            address1 = "'.$address1.'", 
            postcode = "'.$postcode.'", 
            city = "'.$city.'",
            provincia = "'.$provincia.'",
            country = "'.$country.'", 
            phone = "'.$phone.'",
            other = "'.$other.'", 
            dni = "'.$dni.'", 
            envio_almacen = '.$envio_almacen.',
            date_upd = NOW()
            WHERE id_dropshipping_address = '.$existe[1];

            Db::getInstance()->executeS($sql_update_lafrips_dropshipping_address); 

            //devolvemos el id_dropshipping_address
            return $existe[1];
        } else {
            //la dirección existe y es igual en todos sus campos, devolvemos el id_dropshipping_address
            return $existe[1];
        }          
    }

    //función que comprueba si una dirección ya existe en lafrips_dropshipping_address. 
    //Casos: 
    //la dirección no existe y el pedido no tiene ninguna, insertamos esta.
    //existe una dirección pero el id_address_delivery es diferente, marcamos deleted la que existe e insertamos esta    
    //existe una dirección y el id_address_delivery es el mismo, comprobamos si es igual y hacemos update si no
    //no debería haber nunca más de una dirección no deleted para el pedido, pero sacamos todas las que haya, y mientras no coincida id_address_delivery se marcan deleted
    //La función devuelve un array tal que array('insert', 0) si no encuentra el id de address, indicando que hagamso insert, array('update', id_address) indicando que se ha encontardo la dirección con el id_address_delivery pero algún parámetro es diferente. asique hacemos update de toda, y por último array('ok', id_address) indicando que la dirección existe es la misma y usamos el id (retornamos a setAddressInfo())
    public function checkTablaDropshippingAddress($info_address) {
        $id_order = $info_address[0]['id_order'];        
        $id_address_delivery = $info_address[0]['id_address_delivery'];

        $sql_busca_address = 'SELECT id_dropshipping_address, id_address_delivery 
        FROM lafrips_dropshipping_address 
        WHERE deleted = 0
        AND id_order = '.$id_order;

        $busca_address = Db::getInstance()->executeS($sql_busca_address); 

        $return_action = 'insert';
        $return_id_address = 0;

        //si no encuentra direcciones, devolvemos la orden insert para insertar la nueva. Si encuentra pero todas tienen diferente id_address_delivery, marcamos deleted y devolvemos también insert. Si encuentra con el mismo id_address_delivery, comprobamos si es igual a la actual y en función de ello devolvemos update o ok y el id_dropshipping_address.
        if (count($busca_address) > 0) {
            foreach ($busca_address AS $address) {
                if ($address['id_address_delivery'] != $id_address_delivery) {
                    //marcamos deleted
                    $sql_update_address = 'UPDATE lafrips_dropshipping_address
                    SET
                    deleted = 1,          
                    date_deleted = NOW()
                    WHERE id_dropshipping_address = '.$address['id_dropshipping_address'];
    
                    Db::getInstance()->executeS($sql_update_address); 
                } else {
                    //si coincide el id_address_delivery, comprobamos si todos los campos son iguales, si es que no devolvemos update+id_address, si es que si devolvemos ok+id_address
                    $sql_misma_address = 'SELECT id_dropshipping_address 
                    FROM lafrips_dropshipping_address 
                    WHERE deleted = 0
                    AND id_order = '.$info_address[0]['id_order'].' 
                    AND id_customer = '.$info_address[0]['id_customer'].'
                    AND email = "'.$email = $info_address[0]['email'].'"
                    AND id_address_delivery = '.$id_address_delivery.'
                    AND firstname = "'.$info_address[0]['firstname'].'" 
                    AND lastname = "'.$info_address[0]['lastname'].'" 
                    AND company = "'.$info_address[0]['company'].'" 
                    AND address1 = "'.$info_address[0]['address1'].'"
                    AND postcode = "'.$info_address[0]['postcode'].'" 
                    AND city = "'.$info_address[0]['city'].'" 
                    AND provincia = "'.$info_address[0]['provincia'].'" 
                    AND country = "'.$info_address[0]['country'].'" 
                    AND phone = "'.$info_address[0]['phone'].'"
                    AND other = "'.$info_address[0]['other'].'"
                    AND dni = "'.$info_address[0]['dni'].'"';
             
                    $misma_address = Db::getInstance()->executeS($sql_misma_address);

                    //la consulta solo devolverá resultado si coinciden todos los AND de la select
                    if ($misma_address[0]['id_dropshipping_address'] == $address['id_dropshipping_address']) {
                        //la dirección es igual
                        $return_action = 'ok';
                        $return_id_address = $address['id_dropshipping_address'];

                    } else {
                        //la dirección ha sido modificada
                        $return_action = 'update';
                        $return_id_address = $address['id_dropshipping_address'];
                    }

                }
            }
        } 
            
        return array($return_action, $return_id_address);
    }

    //función para insertar los parámetros en lafrips_dropshipping. Devuelve el id del insert en la tabla o 'stop' si el pedido existe y está cancelado o procesado ya. Tenemos que comprobar si la línea pedido/proveedor ya existe en lafrips_dropshipping y en qué estado llamando a checkTablaDropshipping()
    public function insertDropshipping($info_dropshipping, $id_supplier, $id_dropshipping_address) {
        //primero comprobamos si ya existe el pedido para el proveedor en la tabla lafrips_dropshipping
        $existe = $this->checkTablaDropshipping($info_dropshipping, $id_supplier, $id_dropshipping_address);

        //la función checkTablaDropshipping() devuelve array($return_action, $return_id_dropshipping); devuelve 'insert' para indicar que hagamos insert de cero con los datos del pedido / proveedor, devuelve 'ok' y el id_dropshipping para indicar que no hay que tocar el pedido y ese es el id, devuelve 'update' y el id para indicar que algún dato es diferente y hacemos update a ese id de todos los datos. Devuelve 'stop' si encuentra el pedido pero está cancelado o procesado ya.
        if ($existe[0] == 'insert') {
            //almacenamos los datos del pedido y guardamos el id de la inserción
            $supplier_name = Supplier::getNameById($id_supplier);

            $sql_insert_lafrips_dropshipping = 'INSERT INTO lafrips_dropshipping
            (id_supplier, supplier_name, id_order, id_customer, id_address_delivery, id_dropshipping_address, date_add) 
            VALUES 
            ('.$id_supplier.',
            "'.$supplier_name.'", 
            '.$info_dropshipping['order']['id_order'].',
            '.$info_dropshipping['order']['id_customer'].',
            '.$info_dropshipping['order']['id_address_delivery'].', 
            '.$id_dropshipping_address.',             
            NOW())';
            Db::getInstance()->executeS($sql_insert_lafrips_dropshipping);

            return Db::getInstance()->Insert_ID();

        } elseif ($existe[0] == 'update') {
            //Algún campo del pedido, que ya tenemos, es diferente. hacemos update sobre la línea de dropshipping con el id que hemos recibido en el segundo campo del array $existe. 
            $sql_update_lafrips_dropshipping = 'UPDATE lafrips_dropshipping
            SET
            id_address_delivery = '.$info_dropshipping['order']['id_address_delivery'].',
            id_dropshipping_address = '.$id_dropshipping_address.',             
            date_upd = NOW()
            WHERE id_dropshipping = '.$existe[1];

            Db::getInstance()->executeS($sql_update_lafrips_dropshipping); 

            //devolvemos el id_dropshipping_address
            return $existe[1];

        } elseif ($existe[0] == 'ok') {
            //el pedido dropshipping existe y es igual en todos sus campos, devolvemos el id_dropshipping
            return $existe[1];

        } elseif ($existe[0] == 'stop') {
            //el pedido dropshipping existe y está cancelado o procesado, devolvemos 'stop' también
            return 'stop';

        } else {
            //hay algún error
            return null;
        }        
    }

    //función que busca si ya existe el pedido dropshipping (order/supplier) para saber si insertar o hacer update. Si el pedido existe se comprueba el id_dropshipping_address para el pedido actual, el número de productos etc y se hace update.
    //si el pedido está marcado como cancelado o procesado, no se continua. Cancelado se puede revertir desde el back office manualmente
    //devolvemos array con 'insert', 'ok', 'update' o 'stop' dependiendo de lo que haya que hacer, acompañado del id_dropshipping si corresponde.
    public function checkTablaDropshipping($info_dropshipping, $id_supplier, $id_dropshipping_address) {
        $id_order = $info_dropshipping['order']['id_order']; 

        $sql_busca_pedido = 'SELECT id_dropshipping, cancelado, procesado 
        FROM lafrips_dropshipping
        WHERE id_order = '.$id_order.'
        AND id_supplier = '.$id_supplier;

        $busca_pedido = Db::getInstance()->executeS($sql_busca_pedido); 

        $return_action = 'insert';
        $return_id_dropshipping = 0;

        //si no encuentra el pedido, devolvemos la orden insert para insertarlo. Si encuentra pero cancelado o procesado valen 1, devolvemos 'stop'. Si lo encuentra con cancelado y procesado a 0, comprobamos si los datos coinciden con los nuevos, si es así devolvemos el id_dropshipping y 'ok', si  son diferente, devolvemos id_dropshipping y update.
        if (count($busca_pedido) > 0) {
            foreach ($busca_pedido AS $pedido) {
                if ($pedido['cancelado'] || $pedido['procesado']) {
                    //pedido cancelado o procesado uy terminado (de moemnto no damos opción a otro envío desde el back de pedido) Paramos el proceso dropshipping para este pedido/supplier
                    $return_action = 'stop';    
                    
                } else {
                    //encontrado pedido y no cancelado ni procesado, comprobamos si los datos coinciden, si si, devolvemos 'ok' y el id_dropshipping, si no, devolvemos 'update' e id
                    $sql_mismos_datos = 'SELECT id_dropshipping 
                    FROM lafrips_dropshipping 
                    WHERE id_order = '.$id_order.'
                    AND id_supplier = '.$id_supplier.'
                    AND id_customer = '.$info_dropshipping['order']['id_customer'].'
                    AND id_address_delivery = '.$info_dropshipping['order']['id_address_delivery'].'
                    AND id_dropshipping_address = '.$id_dropshipping_address;
             
                    $mismos_datos = Db::getInstance()->executeS($sql_mismos_datos);

                    //la consulta solo devolverá resultado si coinciden todos los AND de la select
                    if ($mismos_datos[0]['id_dropshipping'] == $pedido['id_dropshipping']) {
                        //los datos coinciden
                        $return_action = 'ok';
                        $return_id_dropshipping = $pedido['id_dropshipping'];

                    } else {
                        //los datos no coinciden
                        $return_action = 'update';
                        $return_id_dropshipping = $pedido['id_dropshipping'];
                    }
                }
            }
        } 

        return array($return_action, $return_id_dropshipping);
    }

    //función para insertar un error en lafrips_dropshipping_log
    public static function insertDropshippingLog($error = 'Error sin mensaje ¿?', $id_order = null, $id_supplier = null, $id_dropshipping_supplier = null, $id_dropshipping_address = null, $id_dropshipping = null) {

        $date_add = date("Y-m-d H:i:s");

        Db::getInstance()->insert('dropshipping_log', array(
            'id_dropshipping' => $id_dropshipping,
            'id_dropshipping_address' => $id_dropshipping_address,
            'id_dropshipping_supplier' => $id_dropshipping_supplier,
            'id_supplier' => $id_supplier,
            'id_order' => $id_order,
            'error' => $error,
            'date_add' => $date_add,
        ));

        //29/06/2022 Cuando llegamos aquí conviene enviar un email de aviso para ir corrigiendo errores
        if (!$id_order) {
            $id_order = ' ERROR id_order ';
        } else {
            $id_order = 'ERROR '.$id_order;
        }

        //01/02/2023 Si algún valor es nulo se perdía el mensaje y no había email. Añadimos condicionales para sustituir por "No disponible"
        $id_dropshipping = is_null($id_dropshipping) ? "No disponible" : $id_dropshipping;
        $id_dropshipping_address = is_null($id_dropshipping_address) ? "No disponible" : $id_dropshipping_address;
        $id_dropshipping_supplier = is_null($id_dropshipping_supplier) ? "No disponible" : $id_dropshipping_supplier;
        $id_supplier = is_null($id_supplier) ? 0 : $id_supplier;
        
        $mensaje = '<br><br>Error: <br>'.$error.'<br><br>
        id_dropshipping= '.$id_dropshipping.'<br>
        id_dropshipping_address= '.$id_dropshipping_address.'<br>
        id_dropshipping_supplier= '.$id_dropshipping_supplier.'<br>
        id_supplier= '.$id_supplier;

        $cuentas = array('sergio@lafrikileria.com');

        if ($id_supplier) {
            $proveedor = Supplier::getNameById($id_supplier).' - ERROR';
        } else {
            $proveedor = 'Error Nombre Proveedor';
        }

        //01/02/2023 Llamamos así a enviaEmail() porque si venimos a insertDropshippingLog() desde una de las clases (Disfrazzes.php, DMI.php etc) no podemos utilizar $this->
        Productosvendidossinstock::enviaEmail($cuentas, $mensaje, $proveedor, $id_order);
        
    }    

    //función a la que llamar desde el proceso petición manual desde backoffice, recibe el proveedor, id_dropshipping que identifica al pedido y el id_employee que indica petición manual (o nada si es petición de estado). Con id_supplier dirigimos la petición a cada función de API. Devuelve true o false dependiendo de si completó la llamada.
    //elcampo $funcion establece el tipo de llamada para la API. De moemnto, con Disfrazzes, tenemos la creación de pedido y obtener estado de pedido, llamaremos a una función u otra
    //07/06/2022 añdimos Globomatik
    //14/06/2022 añadimos Printful
    //22/06/2022 añado DMI
    public function selectorAPI($id_supplier, $id_dropshipping, $funcion, $id_employee = null) {
        //montamos otro switch para enviar la petición a su API correspondiente
        switch ($id_supplier) {
            case 161: //Disfrazzes
                if ($funcion == 'solicitud') {
                    //llamamos a la API de Disfrazzes para crear pedido, devolverá true o false según resultado
                    return Disfrazzes::apiDisfrazzesSolicitud($id_dropshipping, $id_employee);
                } elseif ($funcion == 'estado') {
                    //llamamos a la API de Disfrazzes para obtener estado de pedido, devolverá true o false según resultado
                    //el id_dropshipping debe ir en un array, de modo que podemos reutilizzar la función para varios pedidos a la vez
                    $id_dropshipping_array = array();
                    $id_dropshipping_array[] = $id_dropshipping;

                    return Disfrazzes::apiDisfrazzesStatus($id_dropshipping_array);
                } else {
                    return false;
                }                

            break;

            case 160: //DMI
                if ($funcion == 'solicitud') {
                    //llamamos a la API de DMI para crear pedido, devolverá true o false según resultado
                    return Dmi::apiDmiSolicitud($id_dropshipping, $id_employee);
                } elseif ($funcion == 'estado') {
                    //llamamos a la API de Dmi para obtener estado de pedido, devolverá true o false según resultado
                    //el id_dropshipping es único, la api de Dmi solo admite una petición de status cada vez                    

                    return Dmi::apiDmiStatus($id_dropshipping);
                } else {
                    return false;
                }                    

                break;

            case 156: //Globomatik
                if ($funcion == 'solicitud') {
                    //llamamos a la API de Globomatik para crear pedido, devolverá true o false según resultado
                    return Globomatik::apiGlobomatikSolicitud($id_dropshipping, $id_employee);
                } elseif ($funcion == 'estado') {
                    //llamamos a la API de Globomatik para obtener estado de pedido, devolverá true o false según resultado
                    //el id_dropshipping es único, la api de Globomatik solo admite una petición de status cada vez                    

                    return Globomatik::apiGlobomatikStatus($id_dropshipping);
                } else {
                    return false;
                } 
                
                break;

            case 159: //Mars Gaming
                //aviso por email desde apiMarsgaming(), de momento no tenemos proceso de API    
                //provisional
                $sql_id_order = 'SELECT id_order
                FROM lafrips_dropshipping 
                WHERE id_dropshipping = '.$id_dropshipping;  

                $id_order = Db::getInstance()->getValue($sql_id_order);

                $this->apiMarsgaming($id_order);
                
                break;

            case 163: //Printful
                //aviso por email desde apiPrintful(), de momento no tenemos proceso de API  
                //provisional
                $sql_id_order = 'SELECT id_order
                FROM lafrips_dropshipping 
                WHERE id_dropshipping = '.$id_dropshipping;  

                $id_order = Db::getInstance()->getValue($sql_id_order);

                $this->apiPrintful($id_order);                    

                break;

            default:
                //el id_supplier no corresponde a ninguno de los proveedores dropshipping que tenemos contemplados, o aún no he actualizado este módulo para un nuevo proveedor.  Marcamos error en lafrips_dropshipping y envío email aviso?
                return false;

                                    
        }

        //el switch ha finalizado su trabajo. Si las funciones que llaman a API han funcionado correctamente, no se llega aquí
        return true;       

    }
    

    //función que llama a la API MarsGaming para hacer el pedido
    public function apiMarsgaming($id_order) {
        //prueba, enviar email
        //preparamos los parámetros para la llamada, info del pedido y de los productos. Tenemos el id de la tabla dropshipping del pedido
        // $mensaje = '<pre>'.json_encode($info_productos).'</pre>';
        $mensaje = '<h1>Pedido con productos Mars Gaming id '.$id_order.'</h1>';
        
        $cuentas = array('sergio@lafrikileria.com');

        $this->enviaEmail($cuentas, $mensaje, 'MarsGaming', $id_order);

    }

    //función que llama a la API Printful para hacer el pedido
    public function apiPrintful($id_order) {
        //prueba, enviar email
        //preparamos los parámetros para la llamada, info del pedido y de los productos. Tenemos el id de la tabla dropshipping del pedido
        // $mensaje = '<pre>'.json_encode($info_productos).'</pre>';
        $mensaje = '<h1>Pedido con productos Printful id '.$id_order.'</h1>';
        
        $cuentas = array('sergio@lafrikileria.com');

        $this->enviaEmail($cuentas, $mensaje, 'Printful', $id_order);       

    }    

    //función que envía un email al correo/s especificado (se recibe un array $cuentas) y con el mensaje especificado y proveedor que corresponde
    public static function enviaEmail($cuentas, $mensaje, $proveedor, $id_order = '') {

        $asunto = 'Pedido '.$id_order.' de '.$proveedor.' para dropshipping '.date("Y-m-d H:i:s");       
        $info = [];                
        $info['{firstname}'] = 'Usuario';
        $info['{archivo_expediciones}'] = 'Hora ejecución '.date("Y-m-d H:i:s");
        $info['{errores}'] = $mensaje;
        // print_r($info);
        // $info['{order_name}'] = $order->getUniqReference();
        @Mail::Send(
            1,
            'aviso_error_expedicion_cerda', //plantilla
            Mail::l($asunto, 1),
            $info,
            $cuentas,
            'Usuario',
            null,
            null,
            null,
            null,
            _PS_MAIL_DIR_,
            true,
            1
        );

        return true;

    }

    // Mostramos un nuevo bloque dentro de la ficha de pedido    
    public function hookDisplayAdminOrder($params)
    {
        if (!Configuration::get('DROPSHIPPING_PROCESO')) {
            return;
        }
        //botón de actualizar, deberá recargar la página, de modo que el panel de dropshipping se cargue de nuevo con los datos actuales, pero no repetir el análisis del pedido. Si que tiene que analizar la dirección de entrega por si ha sido modificada (si se cambia por otra lo podemos detectar, pero no si se modifica ya que abandona la página y una vez modificada vuelve)
        if (Tools::isSubmit('submitActualizarVistaDropshipping')) { 
            //comprobamos si la dirección permanece o ha sido modificada y afecta al dropshipping. .
            $id_pedido = (int)$params['id_order'];
            
            $this->revisarDireccion($id_pedido);
            
            //preparamos el link al pedido, para recargar desde botón actualizar            
            $token_adminorders = Tools::getAdminTokenLite('AdminOrders');
            $url_base = Tools::getHttpHost(true).__PS_BASE_URI__;
            $url_pedido = $url_base.'lfadminia/index.php?controller=AdminOrders&token='.$token_adminorders.'&id_order='.$id_pedido.'&vieworder';            
                                        
            Tools::redirectAdmin($url_pedido);            
        }

        //llamar a la ejecución de todo el proceso con llamada a API desde el cuadro de dropshipping en backoffice, incluimos id_employee para guardarlo en lafrips_dropshipping para pedido/proveedor. El id_supplier va en el value del botón submit
        if (Tools::isSubmit('submitSolicitarDropshipping')) {
            $id_empleado = Context::getContext()->employee->id;
            $id_pedido = (int)$params['id_order'];
            $id_proveedor = Tools::getValue('submitSolicitarDropshipping');
            
            //obtenemos id_dropshipping con id_supplier e id_order
            $sql_id_dropshipping = 'SELECT id_dropshipping
            FROM lafrips_dropshipping 
            WHERE id_order = '.$id_pedido.'
            AND id_supplier = '.$id_proveedor;

            if ($id_dropshipping = Db::getInstance()->getValue($sql_id_dropshipping)) {
                if (!$this->selectorAPI($id_proveedor, $id_dropshipping, 'solicitud', $id_empleado)) {
                    //error procesando la solictud a API, marcamos la tabla dropshipping con error = 1
                    $sql_update_lafrips_dropshipping = 'UPDATE lafrips_dropshipping      
                    SET                                
                    error = 1, 
                    date_upd = NOW()
                    WHERE id_dropshipping = '.$id_dropshipping;
    
                    Db::getInstance()->Execute($sql_update_lafrips_dropshipping);
                    
                    $error = 'Error haciendo solicitud manual a API';
    
                    $this->insertDropshippingLog($error, $id_pedido, $id_proveedor, null, null, $id_dropshipping);
                   
                }
            } else {
                //error obteniendo dato, marcamos la tabla dropshipping con error = 1
                $sql_update_lafrips_dropshipping = 'UPDATE lafrips_dropshipping      
                SET                                
                error = 1, 
                date_upd = NOW()
                WHERE id_dropshipping = '.$id_dropshipping;

                Db::getInstance()->Execute($sql_update_lafrips_dropshipping);
                
                $error = 'Error obteniendo id_dropshipping para solicitud manual a API';

                $this->insertDropshippingLog($error, $id_pedido, $id_proveedor);
            }      
            
        }

        //los botones tienen el mismo name para diferentes suppliers, pero cada botón tiene un value, que es el id_supplier. Si se hace submit en eun submitCancelarDropshipping podemos obtener luego el id_supplier con getValue del submit (del pulsado)
        if (Tools::isSubmit('submitCancelarDropshipping')) {
            //hacemos update a lafrips_dropshipping con procesado, error o id_employee manual
            $id_empleado = Context::getContext()->employee->id;
            if ($id_empleado) {
                $sql_empleado = ' id_employee_cancelado = '.$id_empleado.',
                date_cancelado = NOW(),';
            } else {
                $sql_empleado = '';
            }

            $id_proveedor = Tools::getValue('submitCancelarDropshipping');
            $id_pedido =  (int) $params['id_order'];

            $sql_update_dropshipping = 'UPDATE lafrips_dropshipping
            SET
            cancelado = 1,
            finalizado = 0,
            '.$sql_empleado.'
            date_upd = NOW()
            WHERE id_supplier = '.$id_proveedor.'
            AND id_order = '.$id_pedido;

            Db::getInstance()->executeS($sql_update_dropshipping); 

            // $this->confirmations[] = $this->l('Cancelado dropshipping');
            // $this->displayWarning('Cancelado dropshipping???');
            // $this->errors[] = Tools::displayError('Cancelado dropshipping CANCELADO');
        }

        //si se pulsa sobre reactivar, quitaremos cancelado
        if (Tools::isSubmit('submitReactivarDropshipping')) {            
            $id_proveedor = Tools::getValue('submitReactivarDropshipping');
            $id_pedido =  (int) $params['id_order'];
            
            //comprobamos que el pedido tenga algún producto en su tabla de productos correspondiente que no esté eliminado, si no no debemos reactivar
            if ($this->checkProductosDropshippingActivosEnPedido($id_proveedor, $id_pedido)) {
                $id_empleado = Context::getContext()->employee->id;
                if ($id_empleado) {
                    $sql_empleado = ' id_employee_reactivado = '.$id_empleado.',
                    date_reactivado = NOW(),';
                } else {
                    $sql_empleado = '';
                }

                $sql_update_dropshipping = 'UPDATE lafrips_dropshipping
                SET
                cancelado = 0,
                reactivado = 1,
                '.$sql_empleado.'
                date_upd = NOW()
                WHERE id_supplier = '.$id_proveedor.'
                AND id_order = '.$id_pedido;

                Db::getInstance()->executeS($sql_update_dropshipping); 
            }

            // $this->confirmations[] = $this->l('Reactivado dropshipping');
            // $this->displayWarning('Cancelado dropshipping???');
            // $this->errors[] = Tools::displayError('Cancelado dropshipping CANCELADO');
        }

        //pulsando sobre el botón Estado, si está disponible, se llama a la API correspondiente para actualizar el estado del pedido, normalmente de recibido a enviado, por ejemplo, suponiendo que todos los proveedores tendrán algo similar (de moemnto Disfrazzes)
        if (Tools::isSubmit('submitEstadoDropshipping')) {
            //hacemos update a lafrips_dropshipping con procesado, error o id_employee manual            
            $id_proveedor = Tools::getValue('submitEstadoDropshipping');
            $id_pedido =  (int) $params['id_order'];

            //obtenemos id_dropshipping con id_supplier e id_order
            $sql_id_dropshipping = 'SELECT id_dropshipping
            FROM lafrips_dropshipping 
            WHERE id_order = '.$id_pedido.'
            AND id_supplier = '.$id_proveedor;

            if ($id_dropshipping = Db::getInstance()->getValue($sql_id_dropshipping)) {
                if (!$this->selectorAPI($id_proveedor, $id_dropshipping, 'estado')) {
                    //error procesando la solictud de estado a API, marcamos la tabla dropshipping con error = 1
                    $sql_update_lafrips_dropshipping = 'UPDATE lafrips_dropshipping      
                    SET                                
                    error = 1, 
                    date_upd = NOW()
                    WHERE id_dropshipping = '.$id_dropshipping;
    
                    Db::getInstance()->Execute($sql_update_lafrips_dropshipping);
                    
                    $error = 'Error solicitando estado de pedido a API';
    
                    $this->insertDropshippingLog($error, $id_pedido, $id_proveedor, null, null, $id_dropshipping);
                   
                }
            } else {
                //error obteniendo dato, marcamos la tabla dropshipping con error = 1
                $sql_update_lafrips_dropshipping = 'UPDATE lafrips_dropshipping      
                SET                                
                error = 1, 
                date_upd = NOW()
                WHERE id_dropshipping = '.$id_dropshipping;

                Db::getInstance()->Execute($sql_update_lafrips_dropshipping);
                
                $error = 'Error obteniendo id_dropshipping para solicitud de estado de pedido a API';

                $this->insertDropshippingLog($error, $id_pedido, $id_proveedor);
            }  
            
        }

        //05/09/2022 Añadimos botón para finalizar un pedido cuando son de proveedor sin gestión. El proceso simplemente marca como finalizado el pedido por cuestión de visualización.
        if (Tools::isSubmit('submitFinalizarDropshipping')) {
            //hacemos update a lafrips_dropshipping con finalizado y el id de empleado y fecha 
            $id_empleado = Context::getContext()->employee->id;
            if ($id_empleado) {
                $sql_empleado = ' id_employee_finalizado = '.$id_empleado.',
                date_finalizado = NOW(),';
            } else {
                $sql_empleado = '';
            }

            $id_proveedor = Tools::getValue('submitFinalizarDropshipping');
            $id_pedido =  (int) $params['id_order'];            

            //si está procesado nos aseguramos de limpiar marcadores de error si los hay
            $sql_update_finalizado = 'UPDATE lafrips_dropshipping
            SET
            error = 0,
            error_api = 0,
            procesado = 1,
            finalizado = 1, 
            cancelado = 0, 
            '.$sql_empleado.'                                                   
            date_upd = NOW()
            WHERE id_supplier = '.$id_proveedor.'
            AND id_order = '.$id_pedido;

            Db::getInstance()->executeS($sql_update_finalizado);

            // $this->confirmations[] = $this->l('Cancelado dropshipping');
            // $this->displayWarning('Cancelado dropshipping???');
            // $this->errors[] = Tools::displayError('Cancelado dropshipping CANCELADO');
        }


        //asignamos variablesmarty con la url para las imágenes, que usaremos para mostrar un logo en backoffice
        $this->context->smarty->assign('dropshipping_img_path', $this->_path.'views/img/');

        //obtenemos id_order en que nos encontramos
        $id_order = (int) $params['id_order'];

        //comprobamos si este pedido lo tenemos en la tabla dropshipping
        $es_dropshipping = $this->checkPedidoProcesado($id_order);        

        if (!$es_dropshipping) {
            // El pedido no es dropshipping, asignamos la plantilla del hook sin dropshipping 
            return $this->context->smarty->fetch($this->local_path.'views/templates/hook/dropshipping-admin-order-no-dropshipping.tpl');
        }
        //el pedido está en tabla dropshipping, obtenemos sus datos
        $info = $this->getDropshippingDetails($id_order); 
        
        //pedido dropshipping, asignamos los datos a la plantilla 
        $this->context->smarty->assign(array(
            'plantillas' => $this->local_path.'views/templates/hook', //metemos la localización de las plantillas en una variable. Sin / final porque lo necesito como separador en dropshipping-admin-order-left.tpl
            'info' => $info,
            'id_empleado' => Context::getContext()->employee->id //meto el id para solo mostrar el array de datos a mi usuario mientras hago pruebas            
        ));

        return $this->context->smarty->fetch($this->local_path.'views/templates/hook/dropshipping-admin-order-left.tpl');
    }

    //función que comprueba si un pedido existe en la tabla dropshipping para mostrarlo en la plantilla de back office o para saber si un producto añadido está ya en la tabla o su pedido ya existe. Recibe id_order y devuelve un array asociativo que tendrá como key el id_supplier y value otro array con la info común a los pedidos/proveedor de lafrips_dropshipping, eso por cada línea que encuentre. Si no encuentra nada devuelve false.
    public function checkPedidoProcesado($id_order) 
    {        
        $sql_existe_pedido = 'SELECT id_dropshipping, id_supplier, id_customer, id_address_delivery, id_dropshipping_address, procesado
        FROM lafrips_dropshipping                     
        WHERE id_order = '.$id_order;

        if ($existe_pedido = Db::getInstance()->ExecuteS($sql_existe_pedido)) {
            $info_pedido = array();

            foreach ($existe_pedido AS $pedido) {
                $info_pedido[$pedido['id_supplier']] = array($pedido['id_customer'], $pedido['id_address_delivery'], $pedido['id_dropshipping_address'], $pedido['procesado'], $pedido['id_dropshipping']);
            }

            return $info_pedido;
        }

        return false;
    }

    //función que devuelve todos los detalles del pedido dropshipping al hook en ficha de pedido o false si no existe el pedido para dropshipping
    public function getDropshippingDetails($id_order) {
        //hay que tener en cuenta que en un pedido podría haber varios productos dropshipping y además de varios proveedores dropshipping. Obtenemos primero los datos indicando el proveedor y en función de ello solicitamos la info correspondiente a la tabla de cada proveedor.
        $info = array();
        //sacamos la info del pedido por proveedor dropshipping
        $sql_info_pedido = 'SELECT id_supplier, supplier_name, id_customer, id_dropshipping, id_dropshipping_address, procesado, finalizado, cancelado, error
        FROM lafrips_dropshipping 
        WHERE id_order = '.$id_order;

        $info_pedido = Db::getInstance()->executeS($sql_info_pedido);        
        
        $id_dropshipping_address = $info_pedido[0]['id_dropshipping_address'];        

        foreach ($info_pedido AS $pedido) {    
            $info_proveedor = array();

            $info_proveedor['id_supplier'] = $pedido['id_supplier'];
            $info_proveedor['supplier_name'] = $pedido['supplier_name'];
            $info_proveedor['procesado'] = $pedido['procesado'];
            $info_proveedor['finalizado'] = $pedido['finalizado'];
            $info_proveedor['cancelado'] = $pedido['cancelado'];
            $info_proveedor['error'] = $pedido['error'];            

            $info['proveedores'][$pedido['id_supplier']] = $info_proveedor; 

            //sacamos los productos por proveedor y la info de pedido necesaria respecto a la API. De momento solo hemos almacenado para Disfrazzes id 161
            //07/06/2022 añado Globomatik id 156
            //14/06/2022 añado un genérico para mostrar el producto dropshipping aunque no tengamos gestión api del proveedor, lo pongo como última opción else y lo mostraremos en una plantilla supplier_sin_gestion.tpl
            //22/06/2022 añado DMI
            //05/09/2022 Añadimos el nombre y fecha de empleado para finalización o cancelación de pedidos sin gestión API
            if ($pedido['id_supplier'] == (int)Supplier::getIdByName('Disfrazzes')) {
                //primero los datos de una posible respuesta de la API
                $sql_info_pedido_disfrazzes = 'SELECT id_dropshipping_disfrazzes, response_result, response_delivery_date, response_msg, disfrazzes_id, disfrazzes_reference, status_id, status_name, transportista, date_expedicion, tracking, url_tracking, cancelado, error
                FROM lafrips_dropshipping_disfrazzes
                WHERE id_dropshipping = '.$pedido['id_dropshipping'];

                $info_pedido_disfrazzes = Db::getInstance()->executeS($sql_info_pedido_disfrazzes);
               
                $info_dropshipping = array();

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

                $info['proveedores'][$pedido['id_supplier']]['dropshipping'] = $info_dropshipping;

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

                    $info['proveedores'][$pedido['id_supplier']]['productos'][] = $producto;
                }      

            } elseif ($pedido['id_supplier'] == (int)Supplier::getIdByName('Globomatik')) {
                //primero los datos de una posible respuesta de la API
                $sql_info_pedido_globomatik = 'SELECT id_dropshipping_globomatik, globomatik_order_reference, status_id, status_txt, shipping_cost, cod_transporte, tracking, url_tracking, cancelado, error
                FROM lafrips_dropshipping_globomatik
                WHERE id_dropshipping = '.$pedido['id_dropshipping'];

                $info_pedido_globomatik = Db::getInstance()->executeS($sql_info_pedido_globomatik);
               
                $info_dropshipping = array();

                $info_dropshipping['globomatik_order_reference'] = $info_pedido_globomatik[0]['globomatik_order_reference'];
                $info_dropshipping['status_id'] = $info_pedido_globomatik[0]['status_id'];
                $info_dropshipping['status_txt'] = $info_pedido_globomatik[0]['status_txt'];
                $info_dropshipping['shipping_cost'] = $info_pedido_globomatik[0]['shipping_cost'];
                $info_dropshipping['cod_transporte'] = $info_pedido_globomatik[0]['cod_transporte'];
                $info_dropshipping['tracking'] = $info_pedido_globomatik[0]['tracking'];
                $info_dropshipping['url_tracking'] = $info_pedido_globomatik[0]['url_tracking'];
                $info_dropshipping['cancelado'] = $info_pedido_globomatik[0]['cancelado'];                
                $info_dropshipping['error'] = $info_pedido_globomatik[0]['error'];                

                $info['proveedores'][$pedido['id_supplier']]['dropshipping'] = $info_dropshipping;

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

                    $info['proveedores'][$pedido['id_supplier']]['productos'][] = $producto;
                }      

            } elseif ($pedido['id_supplier'] == (int)Supplier::getIdByName('DMI')) {
                //primero los datos de una posible respuesta de la API
                $sql_info_pedido_dmi = 'SELECT id_dropshipping_dmi, id_order, ws_respuesta, estado, num_factura, fecha_factura, url_tracking, transportista, expedicion_dmi, cancelado, error
                FROM lafrips_dropshipping_dmi
                WHERE id_dropshipping = '.$pedido['id_dropshipping'];

                $info_pedido_dmi = Db::getInstance()->executeS($sql_info_pedido_dmi);
               
                $info_dropshipping = array();

                $info_dropshipping['ws_respuesta'] = $info_pedido_dmi[0]['ws_respuesta'];
                $info_dropshipping['estado'] = $info_pedido_dmi[0]['estado'];
                $info_dropshipping['num_factura'] = $info_pedido_dmi[0]['num_factura'];
                $info_dropshipping['fecha_factura'] = $info_pedido_dmi[0]['fecha_factura'];
                $info_dropshipping['url_tracking'] = $info_pedido_dmi[0]['url_tracking'];
                $info_dropshipping['transportista'] = $info_pedido_dmi[0]['transportista'];
                $info_dropshipping['expedicion_dmi'] = $info_pedido_dmi[0]['expedicion_dmi']; 
                $info_dropshipping['cancelado'] = $info_pedido_dmi[0]['cancelado'];
                $info_dropshipping['error'] = $info_pedido_dmi[0]['error'];                               

                $info['proveedores'][$pedido['id_supplier']]['dropshipping'] = $info_dropshipping;

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

                    $info['proveedores'][$pedido['id_supplier']]['productos'][] = $producto;
                }      

            } else {
                //de momento, el supplier está como dropshipping pero no lo tenemos configurado, mostraremos información del producto/s de forma genérica y sin detalles de api etc
                //no hay datos de una posible respuesta de la API      
                $info['proveedores'][$pedido['id_supplier']]['dropshipping'] = '';

                //si el pedido ha sido finalizado o cancelado manualmente, que es lo que se hace si no hay gestión manual, obtenemos los datos para mostrare el nombre de empleado y fecha
                $info_proveedor['empleado'] = '';
                $info_proveedor['fecha_gestion'] = '';

                if ($info_proveedor['finalizado']) {
                    $sql_info_finalizado = 'SELECT id_employee_finalizado, date_finalizado
                    FROM lafrips_dropshipping 
                    WHERE id_order = '.$id_order.'
                    AND id_supplier = '.$pedido['id_supplier'];

                    $info_finalizado = Db::getInstance()->getRow($sql_info_finalizado); 

                    if ($info_finalizado['id_employee_finalizado']) {
                        $sql_nombre_empleado = 'SELECT CONCAT(firstname," ",lastname) FROM lafrips_employee WHERE id_employee = '.$info_finalizado['id_employee_finalizado'];

                        $info_proveedor['empleado'] = Db::getInstance()->getValue($sql_nombre_empleado);

                        $info_proveedor['fecha_gestion'] = $info_finalizado['date_finalizado'];
                    }

                } elseif ($info_proveedor['cancelado']) {
                    $sql_info_cancelado = 'SELECT id_employee_cancelado, date_cancelado
                    FROM lafrips_dropshipping 
                    WHERE id_order = '.$id_order.'
                    AND id_supplier = '.$pedido['id_supplier'];

                    $info_cancelado = Db::getInstance()->getRow($sql_info_cancelado); 

                    if ($info_cancelado['id_employee_cancelado']) {
                        $sql_nombre_empleado = 'SELECT CONCAT(firstname," ",lastname) FROM lafrips_employee WHERE id_employee = '.$info_cancelado['id_employee_cancelado'];

                        $info_proveedor['empleado'] = Db::getInstance()->getValue($sql_nombre_empleado);

                        $info_proveedor['fecha_gestion'] = $info_cancelado['date_cancelado'];
                    }
                }       
                
                //añadimos datos a info
                $info['proveedores'][$pedido['id_supplier']]['empleado'] = $info_proveedor['empleado']; 
                $info['proveedores'][$pedido['id_supplier']]['fecha_gestion'] = $info_proveedor['fecha_gestion']; 

                //ahora los datos de productos. Como no tenemos tabla dropshipping del proveedor, los sacamos de productos vendidos sin stock
                $sql_info_productos = 'SELECT id_product, product_name, product_reference, product_supplier_reference,
                product_quantity
                FROM lafrips_productos_vendidos_sin_stock
                WHERE eliminado = 0 
                AND dropshipping = 1
                AND id_order_detail_supplier = '.$pedido['id_supplier'].'
                AND id_order = '.$id_order;

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

                    $info['proveedores'][$pedido['id_supplier']]['productos'][] = $producto;
                }                      
            }
            
        }
        
        //sacamos la dirección de entrega
        $sql_info_direccion = 'SELECT firstname, lastname, address1, postcode, city, provincia, envio_almacen
        FROM lafrips_dropshipping_address
        WHERE id_dropshipping_address = '.$id_dropshipping_address.'
        AND deleted = 0';

        $info_direccion = Db::getInstance()->executeS($sql_info_direccion); 

        $info['direccion']['envio_almacen'] = $info_direccion[0]['envio_almacen'];
        $info['direccion']['firstname'] = $info_direccion[0]['firstname'];
        $info['direccion']['lastname'] = $info_direccion[0]['lastname'];
        $info['direccion']['address1'] = $info_direccion[0]['address1'];
        $info['direccion']['postcode'] = $info_direccion[0]['postcode'];
        $info['direccion']['city'] = $info_direccion[0]['city'];
        $info['direccion']['provincia'] = $info_direccion[0]['provincia'];
        
        if ($info) {
            return $info;
        }        

        return false;
    }

    //función que es llamada cuando en el backoffice se edita un producto de un pedido, recibe id_order, un array con id_product e id_product_attribute y referencia de proveedor del producto modificado, un array con la cantidad anterior y la nueva cantidad, y el id de empleado.
    //la función debe estudiar si afecta y como tanto a productos vendidos sin stock como a dropshipping
    public function checkPedidoProductoEditadoBackoffice($id_order, $info_producto_editado, $cantidades_producto_editado, $id_employee) {
        //en el caso de modificar la cantidad, simplemente comprobamos si el producto y pedido se encuentran en las tablas de productos vendidos sin stock y dropshipping, si es así se actualizarán cantidades
        $id_product = $info_producto_editado[0];
        $id_product_attribute = $info_producto_editado[1];
        $supplier_reference = $info_producto_editado[2];
        $cantidad_old = $cantidades_producto_editado[0];
        $cantidad_new = $cantidades_producto_editado[1];
        
        //primero comprobamos si el producto/pedido está en lafrips_productos_vendidos_sin_stock. Si no lo está tampoco deberá estar en dropshipping
        if ($info_productos_vendidos_sin_stock = $this->checkTablaVendidosSinStock($id_order, $id_product, $id_product_attribute)) {
            //devuelve un array con array(id de la tabla, cantidad, eliminado). En este caso nos da igual eliminado, lo marcamos 0 para asegurar y ponemos como quantity_old la que había en la tabla
            $sql_update_lafrips_productos_vendidos_sin_stock = 'UPDATE lafrips_productos_vendidos_sin_stock
            SET
            product_quantity = '.$cantidad_new.',
            quantity_mod = 1, 
            product_quantity_old = '.$info_productos_vendidos_sin_stock[1].',
            date_mod = NOW(), 
            id_employee_mod = '.$id_employee.',   
            eliminado = 0,          
            date_upd = NOW()
            WHERE id_productos_vendidos_sin_stock = '.$info_productos_vendidos_sin_stock[0];

            Db::getInstance()->Execute($sql_update_lafrips_productos_vendidos_sin_stock);

         

            $proveedores_dropshipping = explode(",", Configuration::get('PROVEEDORES_DROPSHIPPING'));
            //ahora hacemos la comprobación para dropshipping. Sacamos id_supplier de lafrips_product_supplier con la referencia de proveedor y los ids de producto
            $sql_id_supplier = 'SELECT id_supplier 
            FROM lafrips_product_supplier
            WHERE id_product = '.$id_product.'
            AND id_product_attribute = '.$id_product_attribute.'
            AND product_supplier_reference = "'.$supplier_reference.'"';

            $id_supplier = Db::getInstance()->getValue($sql_id_supplier);         

            if (in_array($id_supplier, $proveedores_dropshipping) && Configuration::get('DROPSHIPPING_PROCESO')) {   
                //el producto es dropshipping, comprobamos si está en la tabla correspondiente, pero asegurándonos de que el pedido no esté procesado, finalizado o cancelado para dropshipping, en cuyo caso terminamos
                $sql_id_dropshipping = 'SELECT id_dropshipping 
                FROM lafrips_dropshipping 
                WHERE cancelado = 0
                AND procesado = 0
                AND finalizado = 0
                AND id_order = '.$id_order.'
                AND id_supplier = '.$id_supplier;

                if ($id_dropshipping = Db::getInstance()->getValue($sql_id_dropshipping)) {
                    //ahora tenemos que saber que tabla de dropshipping contiene el producto en base al proveedor, usamos switch con id_supplier
                    //22/06/2022 añado DMI
                    switch ($id_supplier) {
                        case 161: //disfrazzes tabla lafrips_dropshipping_disfrazzes - productos. 
                            //hacemos update de la nueva cantidad en su lugar correspondiente, si los datos son reales       
                            $sql_update_quantity_producto = 'UPDATE lafrips_dropshipping_disfrazzes_productos ddp
                            JOIN lafrips_dropshipping_disfrazzes ddi ON ddi.id_dropshipping_disfrazzes = ddp.id_dropshipping_disfrazzes
                            SET 
                            ddp.product_quantity = '.$cantidad_new.',
                            ddp.date_product_quantity_mod = NOW(), 
                            ddp.date_upd = NOW()
                            WHERE ddi.id_dropshipping = '.$id_dropshipping.'
                            AND ddp.id_product = '.$id_product.'
                            AND ddp.id_product_attribute = '.$id_product_attribute;

                            Db::getInstance()->Execute($sql_update_quantity_producto);

                            break;

                        case 160:  //DMI tabla lafrips_dropshipping_dmi - productos. 
                            //hacemos update de la nueva cantidad en su lugar correspondiente, si los datos son reales       
                            $sql_update_quantity_producto = 'UPDATE lafrips_dropshipping_dmi_productos ddp
                            JOIN lafrips_dropshipping_dmi ddm ON ddm.id_dropshipping_dmi = ddp.id_dropshipping_dmi
                            SET 
                            ddp.product_quantity = '.$cantidad_new.',
                            ddp.date_product_quantity_mod = NOW(), 
                            ddp.date_upd = NOW()
                            WHERE ddm.id_dropshipping = '.$id_dropshipping.'
                            AND ddp.id_product = '.$id_product.'
                            AND ddp.id_product_attribute = '.$id_product_attribute;

                            Db::getInstance()->Execute($sql_update_quantity_producto);
                            
                            break;

                        case 156: //Globomatik tabla lafrips_dropshipping_globomatik - productos. 
                            //hacemos update de la nueva cantidad en su lugar correspondiente, si los datos son reales       
                            $sql_update_quantity_producto = 'UPDATE lafrips_dropshipping_globomatik_productos dgp
                            JOIN lafrips_dropshipping_globomatik dgl ON dgl.id_dropshipping_globomatik = dgp.id_dropshipping_globomatik
                            SET 
                            dgp.product_quantity = '.$cantidad_new.',
                            dgp.date_product_quantity_mod = NOW(), 
                            dgp.date_upd = NOW()
                            WHERE dgl.id_dropshipping = '.$id_dropshipping.'
                            AND dgp.id_product = '.$id_product.'
                            AND dgp.id_product_attribute = '.$id_product_attribute;

                            Db::getInstance()->Execute($sql_update_quantity_producto);

                            break;

                        case 159: //Mars Gaming sin configurar
                            
                            break;

                        case 163: //Printful sin configurar
                        
                            break;

                        default:
                            //es un error, volvemos
                            return;

                    }

                    return;

                } else {
                    return;
                }

            }
        }

    }

    //función que es llamada cuando en el backoffice se elimina un producto de un pedido, recibe id_order, un array con id_product e id_product_attribute y referencia proveedor del producto eliminado, y el id de empleado.
    //la función debe estudiar si afecta y como tanto a productos vendidos sin stock como a dropshipping
    public function checkPedidoProductoEliminadoBackoffice($id_order, $info_producto_eliminado, $id_employee) {
        //buscaremos el producto y pedido en las tablas de productos vendidos sin stock y dropshipping. Si está se actualizará marcando eliminado, editando las tablas necesarias, y comprobaremos, en caso de ser dropshipping, si hay más productos dropshipping para este proveedor y pedido, en caso de que no, se marcará como cancelado en lafrips_dropshipping para ese pedido y proveedor
        $id_product = $info_producto_eliminado[0];
        $id_product_attribute = $info_producto_eliminado[1];
        $supplier_reference = $info_producto_eliminado[2];        

        //primero comprobamos si el producto/pedido está en lafrips_productos_vendidos_sin_stock. Si no lo está tampoco deberá estar en dropshipping
        if ($info_productos_vendidos_sin_stock = $this->checkTablaVendidosSinStock($id_order, $id_product, $id_product_attribute)) {
            //devuelve un array con array(id de la tabla, cantidad, eliminado). Eliminado, lo marcamos 1 
            $sql_update_lafrips_productos_vendidos_sin_stock = 'UPDATE lafrips_productos_vendidos_sin_stock
            SET
            eliminado = 1,
            id_employee_eliminado = '.$id_employee.',  
            date_eliminado = NOW(),  
            date_upd = NOW()
            WHERE id_productos_vendidos_sin_stock = '.$info_productos_vendidos_sin_stock[0];

            Db::getInstance()->Execute($sql_update_lafrips_productos_vendidos_sin_stock);
        

            $proveedores_dropshipping = explode(",", Configuration::get('PROVEEDORES_DROPSHIPPING'));
            //ahora hacemos la comprobación para dropshipping. Sacamos id_supplier de lafrips_product_supplier con la referencia de proveedor y los ids de producto
            $sql_id_supplier = 'SELECT id_supplier 
            FROM lafrips_product_supplier
            WHERE id_product = '.$id_product.'
            AND id_product_attribute = '.$id_product_attribute.'
            AND product_supplier_reference = "'.$supplier_reference.'"';

            $id_supplier = Db::getInstance()->getValue($sql_id_supplier);          

            if (in_array($id_supplier, $proveedores_dropshipping) && Configuration::get('DROPSHIPPING_PROCESO')) {   
                //el producto es dropshipping, comprobamos si está en la tabla correspondiente, pero asegurándonos de que el pedido no esté procesado, finalizado o cancelado para dropshipping, en cuyo caso terminamos
                $sql_id_dropshipping = 'SELECT id_dropshipping 
                FROM lafrips_dropshipping 
                WHERE cancelado = 0
                AND procesado = 0
                AND finalizado = 0
                AND id_order = '.$id_order.'
                AND id_supplier = '.$id_supplier;

                if ($id_dropshipping = Db::getInstance()->getValue($sql_id_dropshipping)) {
                    //ahora tenemos que saber que tabla de dropshipping contiene el producto en base al proveedor, usamos switch con id_supplier
                    //22/06/2022 añado DMI
                    switch ($id_supplier) {
                        case 161: //disfrazzes tabla lafrips_dropshipping_disfrazzes - productos. 
                            //hacemos update eliminado 1 en su lugar correspondiente, si los datos son reales       
                            $sql_update_eliminado_producto = 'UPDATE lafrips_dropshipping_disfrazzes_productos ddp
                            JOIN lafrips_dropshipping_disfrazzes ddi ON ddi.id_dropshipping_disfrazzes = ddp.id_dropshipping_disfrazzes
                            SET 
                            ddp.eliminado = 1,
                            ddp.date_eliminado = NOW(), 
                            ddp.date_upd = NOW()
                            WHERE ddi.id_dropshipping = '.$id_dropshipping.'
                            AND ddp.id_product = '.$id_product.'
                            AND ddp.id_product_attribute = '.$id_product_attribute;

                            Db::getInstance()->Execute($sql_update_eliminado_producto);

                            //ahora tenemos que comprobar si para este pedido y proveedor quedan más productos dropshipping, si no es así marcaremos cancelado el pedido en lafrips_dropshipping
                            if (!$this->checkProductosDropshippingActivosEnPedido($id_supplier, $id_order)) {
                                //no se ha obtenido nada, el pedido no tiene más productos dropshipping "activos", marcamos el pedido cancelado
                                $sql_update_pedido_cancelado = 'UPDATE lafrips_dropshipping
                                SET 
                                cancelado = 1,
                                date_cancelado = NOW(), 
                                date_upd = NOW()
                                WHERE id_dropshipping = '.$id_dropshipping;

                                Db::getInstance()->Execute($sql_update_pedido_cancelado);
                            }
                            
                            break;

                        case 160: //Dmi  tabla lafrips_dropshipping_dmi - productos. 
                            //hacemos update eliminado 1 en su lugar correspondiente, si los datos son reales       
                            $sql_update_eliminado_producto = 'UPDATE lafrips_dropshipping_dmi_productos ddp
                            JOIN lafrips_dropshipping_dmi ddm ON ddm.id_dropshipping_dmi = ddp.id_dropshipping_dmi
                            SET 
                            ddp.eliminado = 1,
                            ddp.date_eliminado = NOW(), 
                            ddp.date_upd = NOW()
                            WHERE ddm.id_dropshipping = '.$id_dropshipping.'
                            AND ddp.id_product = '.$id_product.'
                            AND ddp.id_product_attribute = '.$id_product_attribute;

                            Db::getInstance()->Execute($sql_update_eliminado_producto);

                            //ahora tenemos que comprobar si para este pedido y proveedor quedan más productos dropshipping, si no es así marcaremos cancelado el pedido en lafrips_dropshipping
                            if (!$this->checkProductosDropshippingActivosEnPedido($id_supplier, $id_order)) {
                                //no se ha obtenido nada, el pedido no tiene más productos dropshipping "activos", marcamos el pedido cancelado
                                $sql_update_pedido_cancelado = 'UPDATE lafrips_dropshipping
                                SET 
                                cancelado = 1,
                                date_cancelado = NOW(), 
                                date_upd = NOW()
                                WHERE id_dropshipping = '.$id_dropshipping;

                                Db::getInstance()->Execute($sql_update_pedido_cancelado);
                            }
                            
                            break;

                        case 156: //Globomatik  tabla lafrips_dropshipping_globomatik - productos. 
                            //hacemos update eliminado 1 en su lugar correspondiente, si los datos son reales       
                            $sql_update_eliminado_producto = 'UPDATE lafrips_dropshipping_globomatik_productos dgp
                            JOIN lafrips_dropshipping_globomatik dgl ON dgl.id_dropshipping_globomatik = dgp.id_dropshipping_globomatik
                            SET 
                            dgp.eliminado = 1,
                            dgp.date_eliminado = NOW(), 
                            dgp.date_upd = NOW()
                            WHERE dgl.id_dropshipping = '.$id_dropshipping.'
                            AND dgp.id_product = '.$id_product.'
                            AND dgp.id_product_attribute = '.$id_product_attribute;

                            Db::getInstance()->Execute($sql_update_eliminado_producto);

                            //ahora tenemos que comprobar si para este pedido y proveedor quedan más productos dropshipping, si no es así marcaremos cancelado el pedido en lafrips_dropshipping
                            if (!$this->checkProductosDropshippingActivosEnPedido($id_supplier, $id_order)) {
                                //no se ha obtenido nada, el pedido no tiene más productos dropshipping "activos", marcamos el pedido cancelado
                                $sql_update_pedido_cancelado = 'UPDATE lafrips_dropshipping
                                SET 
                                cancelado = 1,
                                date_cancelado = NOW(), 
                                date_upd = NOW()
                                WHERE id_dropshipping = '.$id_dropshipping;

                                Db::getInstance()->Execute($sql_update_pedido_cancelado);
                            }
                            
                            break;

                        case 159: //Mars Gaming sin configurar
                            
                            break;

                        case 163: //Printful sin configurar
                    
                            break;

                        default:
                            //es un error, volvemos
                            return;

                    }

                    return;

                } else {
                    return;
                }

            }
        }
        
    }

    //función que comprueba si un pedido dropshipping tiene productos no eliminados, es decir, si debe seguir siendo procesable o hay que marcar cancelado, si por ejemplo se elimina de un pedido un producto dropshipping y ya no hay más de ese proveedor
    //con un switch dirigimos a la tabla que corresponda y devuelve 1 o 0 según haya o no productos "activos"
    public function checkProductosDropshippingActivosEnPedido($id_supplier, $id_order) {
        //según el proveedor buscaremos en una tabla u otra
        //22/06/2022 añado DMI
        switch ($id_supplier) {
            case 161: //disfrazzes tabla lafrips_dropshipping_disfrazzes - productos. 
                $sql_mas_productos = 'SELECT ddp.id_dropshipping_disfrazzes_productos 
                FROM lafrips_dropshipping_disfrazzes_productos ddp
                JOIN lafrips_dropshipping_disfrazzes ddi ON ddi.id_dropshipping_disfrazzes = ddp.id_dropshipping_disfrazzes
                JOIN lafrips_dropshipping dro ON dro.id_dropshipping = ddi.id_dropshipping
                WHERE ddp.eliminado = 0
                AND dro.id_order = '.$id_order.'
                AND dro.id_supplier = '.$id_supplier;

                if ($mas_productos = Db::getInstance()->getValue($sql_mas_productos)) {
                    return 1;
                } else {
                    return 0;
                }
                
                break;

            case 160: //Dmi tabla lafrips_dropshipping_dmi - productos. 
                $sql_mas_productos = 'SELECT ddp.id_dropshipping_dmi_productos 
                FROM lafrips_dropshipping_dmi_productos ddp
                JOIN lafrips_dropshipping_dmi ddm ON ddm.id_dropshipping_dmi = ddp.id_dropshipping_dmi
                JOIN lafrips_dropshipping dro ON dro.id_dropshipping = ddm.id_dropshipping
                WHERE ddp.eliminado = 0
                AND dro.id_order = '.$id_order.'
                AND dro.id_supplier = '.$id_supplier;

                if ($mas_productos = Db::getInstance()->getValue($sql_mas_productos)) {
                    return 1;
                } else {
                    return 0;
                }
                
                break;

            case 156: //Globomatik tabla lafrips_dropshipping_globomatik - productos. 
                $sql_mas_productos = 'SELECT dgp.id_dropshipping_globomatik_productos 
                FROM lafrips_dropshipping_globomatik_productos dgp
                JOIN lafrips_dropshipping_globomatik dgl ON dgl.id_dropshipping_globomatik = dgp.id_dropshipping_globomatik
                JOIN lafrips_dropshipping dro ON dro.id_dropshipping = dgl.id_dropshipping
                WHERE dgp.eliminado = 0
                AND dro.id_order = '.$id_order.'
                AND dro.id_supplier = '.$id_supplier;

                if ($mas_productos = Db::getInstance()->getValue($sql_mas_productos)) {
                    return 1;
                } else {
                    return 0;
                }
                
                break;

            case 159: //Mars Gaming sin configurar
                
                break;

            case 163: //Printful sin configurar
                    
                break;

            default:
                //es un error, volvemos
                return 0;

        }        
    }

    //función que es llamada cuando en el backoffice se añade un producto a un pedido, recibe id_order, un array con id_product e id_product_attribute y id_order_detail del producto añadido, y el id de empleado.
    //la función debe estudiar si afecta y cómo tanto a productos vendidos sin stock como a dropshipping. Comprobará si el producto ya estuvo en el pedido pero se marcó eliminado y ahora vuelve
    public function checkPedidoProductoAnadidoBackoffice($id_order, $ids_producto_anadido, $id_employee) {
        //comprobaremos si el producto entra como sin stock, en ese caso se procesará para vendidos sin stock añadiéndolo a un pedido existente o creando nuevo pedido, lo mismo para dropshipping.
        $id_product = $ids_producto_anadido[0];
        $id_product_attribute = $ids_producto_anadido[1];
        $id_order_detail = $ids_producto_anadido[2];

        $order_detail = new OrderDetail($id_order_detail);

        //no me fio de sacar id_supplier de lafrips_products porque si después de comprar se cambia el proveedor por defecto nos estaría dando un dato falso, así que sacamos con la referencia de proveedor del producto almacenada en el momento de la venta (podría estar repetida para dos proveedores pero es menos probable)
        $id_supplier = Db::getInstance()->getValue('SELECT id_supplier 
            FROM lafrips_product_supplier
            WHERE id_product = '.$id_product.'
            AND id_product_attribute = '.$id_product_attribute.'
            AND product_supplier_reference = "'.$order_detail->product_supplier_reference.'"'); 

        //primero tenemos que comprobar los datos de order detail para saber si el producto ha entrado como sin stock, en ese caso lo meteremos en productos vendidos sin stock
        $product = new Product($id_product); 

        //si el producto no está en gestión avanzada, es pack o virtual, lo pasamos
        if (!StockAvailableCore::dependsOnStock($id_product) || $product->cache_is_pack || $product->is_virtual){
            return;
        }    

        $product_quantity = $order_detail->product_quantity; 
        $product_quantity_in_stock = $order_detail->product_quantity_in_stock; 
        //comprobamos si tiene stock suficiente o entra como sin stock
        if ($product_quantity_in_stock >= $product_quantity) {
            return;
        }

        //preparamos datos comunes a vendidos sin stock y dropshipping
        $product_name =  pSQL($order_detail->product_name); //añade una barra antes de las comillas o de otra barra, para que no de error sql
        $product_reference = pSQL($order_detail->product_reference); //es como addslashes($string)
        $product_supplier_reference = pSQL($order_detail->product_supplier_reference); 

        //si ha entrado sin stock, comprobamos si existía ya en la tabla (pero fue eliminado) Si no lo está tampoco deberá estar en dropshipping
        if ($info_productos_vendidos_sin_stock = $this->checkTablaVendidosSinStock($id_order, $id_product, $id_product_attribute)) {
            //tanto el pedido como el producto se encuentran en la tabla, cambiamos eliminado y aseguramos que la cantidad sea la correcta
            //devuelve un array con array(id de la tabla, cantidad, eliminado).  
            $sql_update_lafrips_productos_vendidos_sin_stock = 'UPDATE lafrips_productos_vendidos_sin_stock
            SET
            product_quantity = '.$product_quantity.',
            anadido = 1,
            id_employee_anadido = '.$id_employee.',              
            date_anadido = NOW(),  
            eliminado = 0,
            date_upd = NOW()
            WHERE id_productos_vendidos_sin_stock = '.$info_productos_vendidos_sin_stock[0];

            Db::getInstance()->Execute($sql_update_lafrips_productos_vendidos_sin_stock);
        } else {
            //el producto no está en la tabla, lo insertamos. Sacamos todos los datos de producto y pedido necesarios
            $order = new Order($id_order);

            $payment_module = $order->module;
            if ($payment_module == 'bankwire'){                            
                //se consider un pedido por transferencia normal, que en este punto viene a Sin stock pagado después de haber entrado como sin stock no pagado, y ya se le había restado el stock
                $stock_disponible = StockAvailableCore::getQuantityAvailableByProduct($id_product, $id_product_attribute);
                                            
            } else {
                $stock_disponible = StockAvailableCore::getQuantityAvailableByProduct($id_product, $id_product_attribute) - $product_quantity;
            }     

            //aunque quizás no sea necesario al ser un proceso manual de un empleado, procesamos las fechas como en cualquier otro producto. almacenamos en otro campo fecha_disponible la fecha available_date, y en caso de ser esta 0000-00-00, almacenamos la fecha del momento más 12 días, para pedidos de permitir pedido normal
            $available_date = $product->available_date;
            
            if ($available_date == '0000-00-00') {
                $hoy = date("Y-m-d");                            
                $hoy_mas_12 = date( "Y-m-d", strtotime( "$hoy +12 day" ) );
                $fecha_disponible = $hoy_mas_12;
            } else {
                $fecha_disponible = $available_date;
            }

            //almacenamos el texto del campo de product_lang available_later del momento de compra                        
            $sql_available_later = 'SELECT available_later FROM lafrips_product_lang WHERE id_lang = 1 AND id_product = '.$id_product;
            $available_later = Db::getInstance()->getValue($sql_available_later);            
            if (!$available_later) {
                $available_later = '';
            }

            //comprobamos si tiene marcada la categoría Prepedidos - 121, si la tiene lo insertamos también
            $categorias = Product::getProductCategories($id_product); 
            $cat_prepedido = 0;                     
            if (in_array(121, $categorias)){
                $cat_prepedido = 1;
            }
               
            $out_of_stock = StockAvailableCore::outOfStock($id_product);

            //procedemos a meterlo a productos vendidos sin stock
            $sql_insert_lafrips_productos_vendidos_sin_stock = "INSERT INTO lafrips_productos_vendidos_sin_stock 
            (id_order, id_order_status, payment_module, id_product, id_product_attribute, available_date, fecha_disponible, available_later, prepedido, out_of_stock, checked, product_name, stock_available, product_reference, product_supplier_reference, id_default_supplier, id_order_detail_supplier, product_quantity, anadido, id_employee_anadido, date_anadido, date_add) 
            VALUES (".$id_order." ,
            ".(int)$order->current_state." ,
            '".$payment_module."' ,
            ".$id_product." ,
            ".$id_product_attribute." ,
            '".$available_date."',
            '".$fecha_disponible."',
            '".$available_later."',
            ".$cat_prepedido." ,
            ".$out_of_stock." ,                        
            0 ,
            '".$product_name."' ,
            ".$stock_disponible." ,
            '".$product_reference."' ,
            '".$product_supplier_reference."' ,
            ".$id_supplier." ,
            ".$id_supplier." ,
            ".$product_quantity." ,
            1,
            ".$id_employee." ,
            NOW(),
            NOW())";

            Db::getInstance()->Execute($sql_insert_lafrips_productos_vendidos_sin_stock);
        }

        //comprobamos si es dropshipping y si está activo el módulo
        $proveedores_dropshipping = explode(",", Configuration::get('PROVEEDORES_DROPSHIPPING'));
        if (!in_array($id_supplier, $proveedores_dropshipping) || !Configuration::get('DROPSHIPPING_PROCESO')) {  
            return;
        }

        //marcamos en lafrips_productos_vendidos_sin_stock dropshipping = 1, esto nos será muy útil a la hora de utilizar el rescatador de pedidos y el pickpack para ignorar los productos que no pasan por almacén. Volvemos a utilizar la función checkTablaVendidosSinStock() que devuelve un array en cuya posición 0 está el id de la tabla. En este punto el producto ya tiene que estar en dicha tabla pero lo metemos en un if.
        if ($info_productos_vendidos_sin_stock = $this->checkTablaVendidosSinStock($id_order, $id_product, $id_product_attribute)) {                   
            //devuelve un array con array(id de la tabla, cantidad, eliminado).  
            $sql_update_lafrips_productos_vendidos_sin_stock = 'UPDATE lafrips_productos_vendidos_sin_stock
            SET
            dropshipping = 1
            WHERE id_productos_vendidos_sin_stock = '.$info_productos_vendidos_sin_stock[0];

            Db::getInstance()->Execute($sql_update_lafrips_productos_vendidos_sin_stock);
        } else {
            //ha ocurrido algún error al buscar el producto en productos vendidos sin stock
            $error = 'Error obteniendo el id de productos vendidos sin stock para marcar dropshipping, para id_order '.$id_order.', id_product '.$id_product.', id_product_attribute '.$id_product_attribute;

            $this->insertDropshippingLog($error, $id_order, $id_supplier);
        }

        //comprobamos si existe el pedido dropshipping, y si el producto ya estuvo en él pero se eliminó. si el pedido ya existe para dropshipping introducimos el producto (puede estar cancelado o procesado etc¿?) si no hay que crear todos los datos para dropshipping
        //un pedido puede tener productos dropshipping de varios proveedores, los cuales a 18/03/2022 compartirán los datos sobre dirección, de modo que lo comprobamos para usar los mismos  datos o crearlo desde cero
        if ($info_pedido_dropshipping = $this->checkPedidoProcesado($id_order)) {
            //el pedido existe para dropshipping, comprobamos si en los datos devueltos ya existe para este proveedor, comprobamos si está procesado. Si lo está no podemos meter el producto.
            //$info_pedido_dropshipping es un array key=> value, key son id de supplier, value un array con los datos del pedido de ese supplier y procesado 0 o 1. Buscamos si existe para nuestro id_supplier
            // $info_pedido[$pedido['id_supplier']] = array($pedido['id_customer'], $pedido['id_address_delivery'], $pedido['id_dropshipping_address'], $pedido['procesado']);
            if (is_null($info_pedido_dropshipping[$id_supplier])) {
                //no existe, hay que introducir un nuevo pedido para este proveedor, con el resto de datos igual que el proveedor que ya existe (dirección...)
                //sacamos los datos coincidentes a todos del pedido/proveedor que ya existe
                foreach ($info_pedido_dropshipping AS $id => $info) {
                    $id_customer = $info[0];
                    $id_address_delivery = $info[1];
                    $id_dropshipping_address = $info[2];                    

                    //como estos datos deben coincidir para cada proveedor dropshipping, salimos del foreach en su primera vuelta
                    break;
                }

                //construimos $info_dropshipping para poder usar las funciones insertDropshipping() etc
                $info_dropshipping = array();
                $info_dropshipping['order']['id_order'] = $id_order;
                $info_dropshipping['order']['id_customer'] = $id_customer;
                $info_dropshipping['order']['id_address_delivery'] = $id_address_delivery;

                //llamamos a insertDropshipping() que en este caso generará el nuevo pedido/proveedor en lafrips_dropshipping 
                $id_lafrips_dropshipping = $this->insertDropshipping($info_dropshipping, $id_supplier, $id_dropshipping_address );
                if (!$id_lafrips_dropshipping) {
                    //ha ocurrido algún error con el pedido, metemos un log y salimos
                    $error = 'Error insertando la información de pedido / proveedor en lafrips_dropshipping para producto añadido Backoffice';

                    $this->insertDropshippingLog($error, $id_order, $id_supplier, null, $id_dropshipping_address);

                    return;
                }

                //ya se ha creado el nuevo en lafrips_dropshipping, ahora dependiendo del proveedor se tiene que ir a una u otra tabla (solo Disfrazzes a 18/03/2022)
                //14/06/2022 añado Globomatik
                //22/06/2022 añado DMI
                switch ($id_supplier) {
                    case 161: //disfrazzes
                        //primero llamamos a la función que busca el pedido en tabla disfrazzes, que en este caso no encontrará, y lo inserta
                        if (!$id_dropshipping_disfrazzes = Disfrazzes::checkTablaDropshippingDisfrazzes($id_order, $id_lafrips_dropshipping)) {
                            //error introduciendo el pedido a DropshippingDisfrazzes, marcamos la tabla dropshipping con error = 1
                            $sql_update_lafrips_dropshipping = 'UPDATE lafrips_dropshipping      
                            SET                                
                            error = 1, 
                            date_upd = NOW()
                            WHERE id_dropshipping = '.$id_lafrips_dropshipping;
    
                            Db::getInstance()->Execute($sql_update_lafrips_dropshipping);
                            
                            $error = 'Error introduciendo pedido a tabla Disfrazzes por producto añadido Backoffice';
    
                            $this->insertDropshippingLog($error, $id_order, $id_supplier, null, null, $id_lafrips_dropshipping);
    
                            break;
                        }

                        //creamos $info_producto para aprovechar función productosProveedorDisfrazzes()
                        $info_productos = array();
                        $producto = array();
                        $producto['id_order_detail'] = $id_order_detail;
                        $producto['id_product'] = $id_product;
                        $producto['id_product_attribute'] = $id_product_attribute;
                        $producto['product_supplier_reference'] = $product_supplier_reference;
                        $producto['product_quantity'] = $product_quantity;
                        $producto['product_name'] = $product_name;
                        $producto['product_reference'] = $product_reference;

                        $info_productos[] = $producto;
    
                        //ahora chequeamos los productos disfrazzes y los metemos a lafrips_dropshipping_disfrazzes_productos
                        if (!Disfrazzes::productosProveedorDisfrazzes($info_productos, $id_order, $id_dropshipping_disfrazzes, false)) {    
                            //ha ocurrido algún error con los productos, metemos un log y salimos
                            $error = 'Error insertando/comprobando la información de productos Disfrazzes al añadir producto Backoffice';
    
                            $this->insertDropshippingLog($error, $id_order, $id_supplier, null, $id_dropshipping_address, $id_lafrips_dropshipping);
                        }

                        break;
    
                    case 160: //DMI
                        //primero llamamos a la función que busca el pedido en tabla dmi, que en este caso no encontrará, y lo inserta
                        if (!$id_dropshipping_dmi = Dmi::checkTablaDropshippingDmi($id_order, $id_lafrips_dropshipping)) {
                            //error introduciendo el pedido a Dropshippingdmi, marcamos la tabla dropshipping con error = 1
                            $sql_update_lafrips_dropshipping = 'UPDATE lafrips_dropshipping      
                            SET                                
                            error = 1, 
                            date_upd = NOW()
                            WHERE id_dropshipping = '.$id_lafrips_dropshipping;
    
                            Db::getInstance()->Execute($sql_update_lafrips_dropshipping);
                            
                            $error = 'Error introduciendo pedido a tabla DMI por producto añadido Backoffice';
    
                            $this->insertDropshippingLog($error, $id_order, $id_supplier, null, null, $id_lafrips_dropshipping);
    
                            break;
                        }

                        //creamos $info_producto para aprovechar función productosProveedorDmi()
                        $info_productos = array();
                        $producto = array();
                        $producto['id_order_detail'] = $id_order_detail;
                        $producto['id_product'] = $id_product;
                        $producto['id_product_attribute'] = $id_product_attribute;
                        $producto['product_supplier_reference'] = $product_supplier_reference;
                        $producto['product_quantity'] = $product_quantity;
                        $producto['product_name'] = $product_name;
                        $producto['product_reference'] = $product_reference;

                        $info_productos[] = $producto;
    
                        //ahora chequeamos los productos dmi y los metemos a lafrips_dropshipping_dmi_productos
                        if (!Dmi::productosProveedorDmi($info_productos, $id_order, $id_dropshipping_dmi, false)) {    
                            //ha ocurrido algún error con los productos, metemos un log y salimos
                            $error = 'Error insertando/comprobando la información de productos DMI al añadir producto Backoffice';
    
                            $this->insertDropshippingLog($error, $id_order, $id_supplier, null, $id_dropshipping_address, $id_lafrips_dropshipping);
                        }                        
    
                        break;
    
                    case 156: //Globomatik
                        //primero llamamos a la función que busca el pedido en tabla globomatik, que en este caso no encontrará, y lo inserta
                        if (!$id_dropshipping_globomatik = Globomatik::checkTablaDropshippingGlobomatik($id_order, $id_lafrips_dropshipping)) {
                            //error introduciendo el pedido a Dropshippingglobomatik, marcamos la tabla dropshipping con error = 1
                            $sql_update_lafrips_dropshipping = 'UPDATE lafrips_dropshipping      
                            SET                                
                            error = 1, 
                            date_upd = NOW()
                            WHERE id_dropshipping = '.$id_lafrips_dropshipping;
    
                            Db::getInstance()->Execute($sql_update_lafrips_dropshipping);
                            
                            $error = 'Error introduciendo pedido a tabla Globomatik por producto añadido Backoffice';
    
                            $this->insertDropshippingLog($error, $id_order, $id_supplier, null, null, $id_lafrips_dropshipping);
    
                            break;
                        }

                        //creamos $info_producto para aprovechar función productosProveedorGlobomatik()
                        $info_productos = array();
                        $producto = array();
                        $producto['id_order_detail'] = $id_order_detail;
                        $producto['id_product'] = $id_product;
                        $producto['id_product_attribute'] = $id_product_attribute;
                        $producto['product_supplier_reference'] = $product_supplier_reference;
                        $producto['product_quantity'] = $product_quantity;
                        $producto['product_name'] = $product_name;
                        $producto['product_reference'] = $product_reference;

                        $info_productos[] = $producto;
    
                        //ahora chequeamos los productos globomatik y los metemos a lafrips_dropshipping_globomatik_productos
                        if (!Globomatik::productosProveedorGlobomatik($info_productos, $id_order, $id_dropshipping_globomatik, false)) {    
                            //ha ocurrido algún error con los productos, metemos un log y salimos
                            $error = 'Error insertando/comprobando la información de productos Globomatik al añadir producto Backoffice';
    
                            $this->insertDropshippingLog($error, $id_order, $id_supplier, null, $id_dropshipping_address, $id_lafrips_dropshipping);
                        }                        
    
                        break;
    
                    case 159: //Mars Gaming
                        
                        
                        break;

                    case 163: //Printful sin configurar

                    
                        break;
    
                    default:
                        //el id_supplier no corresponde a ninguno de los proveedores dropshipping que tenemos contemplados, o aún no he actualizado este módulo para un nuevo proveedor.  Marcamos error en lafrips_dropshipping y envío email aviso?
    
                                            
                }

            } else {
                //ya hay pedido dropshipping para este proveedor, comprobamos si está procesado, si estaba el producto, la cantidad y marcamos eliminado = 0 o insertamos nuevo producto. 
                //$info_pedido[$pedido['id_supplier']] = array($pedido['id_customer'], $pedido['id_address_delivery'], $pedido['id_dropshipping_address'], $pedido['procesado'], $pedido['id_dropshipping']);
                $info_pedido = $info_pedido_dropshipping[$id_supplier];
                if ($info_pedido[3]) {
                    //el pedido está procesado, salimos
                    return;
                }
                //tenemos el id de lafrips_dropshipping en $info_pedido, en la última posición del array
                $id_lafrips_dropshipping = (int)$info_pedido[4];

                //según el proveedor, comprobamos si su tabla existe (en este caso debería)
                switch ($id_supplier) {
                    case 161: //disfrazzes
                        //primero llamamos a la función que busca el pedido en tabla disfrazzes, que en este caso si encontrará
                        if (!$id_dropshipping_disfrazzes = Disfrazzes::checkTablaDropshippingDisfrazzes($id_order, $id_lafrips_dropshipping)) {
                            //error introduciendo el pedido a DropshippingDisfrazzes, marcamos la tabla dropshipping con error = 1
                            $sql_update_lafrips_dropshipping = 'UPDATE lafrips_dropshipping      
                            SET                                
                            error = 1, 
                            date_upd = NOW()
                            WHERE id_dropshipping = '.$id_lafrips_dropshipping;
    
                            Db::getInstance()->Execute($sql_update_lafrips_dropshipping);
                            
                            $error = 'Error al buscar pedido en tabla Disfrazzes por producto añadido Backoffice';
    
                            $this->insertDropshippingLog($error, $id_order, $id_supplier, null, null, $id_lafrips_dropshipping);
    
                            break;
                        }

                        //creamos $info_producto para aprovechar función productosProveedorDisfrazzes()
                        $info_productos = array();
                        $producto = array();
                        $producto['id_order_detail'] = $id_order_detail;
                        $producto['id_product'] = $id_product;
                        $producto['id_product_attribute'] = $id_product_attribute;
                        $producto['product_supplier_reference'] = $product_supplier_reference;
                        $producto['product_quantity'] = $product_quantity;
                        $producto['product_name'] = $product_name;
                        $producto['product_reference'] = $product_reference;

                        $info_productos[] = $producto;
    
                        //ahora chequeamos los productos disfrazzes y los metemos a lafrips_dropshipping_disfrazzes_productos
                        if (!Disfrazzes::productosProveedorDisfrazzes($info_productos, $id_order, $id_dropshipping_disfrazzes, false)) {    
                            //ha ocurrido algún error con los productos, metemos un log y salimos
                            $error = 'Error insertando/comprobando la información de productos Disfrazzes al añadir producto Backoffice';
    
                            $this->insertDropshippingLog($error, $id_order, $id_supplier, null, null, $id_lafrips_dropshipping);
                        }

                        break;
    
                    case 160: //DMI
                        //primero llamamos a la función que busca el pedido en tabla dmi, que en este caso si encontrará
                        if (!$id_dropshipping_dmi = Dmi::checkTablaDropshippingDmi($id_order, $id_lafrips_dropshipping)) {
                            //error introduciendo el pedido a DropshippingDmi, marcamos la tabla dropshipping con error = 1
                            $sql_update_lafrips_dropshipping = 'UPDATE lafrips_dropshipping      
                            SET                                
                            error = 1, 
                            date_upd = NOW()
                            WHERE id_dropshipping = '.$id_lafrips_dropshipping;
    
                            Db::getInstance()->Execute($sql_update_lafrips_dropshipping);
                            
                            $error = 'Error al buscar pedido en tabla DMI por producto añadido Backoffice';
    
                            $this->insertDropshippingLog($error, $id_order, $id_supplier, null, null, $id_lafrips_dropshipping);
    
                            break;
                        }

                        //creamos $info_producto para aprovechar función productosProveedorDmi()
                        $info_productos = array();
                        $producto = array();
                        $producto['id_order_detail'] = $id_order_detail;
                        $producto['id_product'] = $id_product;
                        $producto['id_product_attribute'] = $id_product_attribute;
                        $producto['product_supplier_reference'] = $product_supplier_reference;
                        $producto['product_quantity'] = $product_quantity;
                        $producto['product_name'] = $product_name;
                        $producto['product_reference'] = $product_reference;

                        $info_productos[] = $producto;
    
                        //ahora chequeamos los productos Dmi y los metemos a lafrips_dropshipping_dmi_productos
                        if (!Dmi::productosProveedorDmi($info_productos, $id_order, $id_dropshipping_dmi, false)) {    
                            //ha ocurrido algún error con los productos, metemos un log y salimos
                            $error = 'Error insertando/comprobando la información de productos DMI al añadir producto Backoffice';
    
                            $this->insertDropshippingLog($error, $id_order, $id_supplier, null, null, $id_lafrips_dropshipping);
                        }
    
                        break;
    
                    case 156: //Globomatik
                        //primero llamamos a la función que busca el pedido en tabla globomatik, que en este caso si encontrará
                        if (!$id_dropshipping_globomatik = Globomatik::checkTablaDropshippingGlobomatik($id_order, $id_lafrips_dropshipping)) {
                            //error introduciendo el pedido a DropshippingGlobomatik, marcamos la tabla dropshipping con error = 1
                            $sql_update_lafrips_dropshipping = 'UPDATE lafrips_dropshipping      
                            SET                                
                            error = 1, 
                            date_upd = NOW()
                            WHERE id_dropshipping = '.$id_lafrips_dropshipping;
    
                            Db::getInstance()->Execute($sql_update_lafrips_dropshipping);
                            
                            $error = 'Error al buscar pedido en tabla Globomatik por producto añadido Backoffice';
    
                            $this->insertDropshippingLog($error, $id_order, $id_supplier, null, null, $id_lafrips_dropshipping);
    
                            break;
                        }

                        //creamos $info_producto para aprovechar función productosProveedorGlobomatik()
                        $info_productos = array();
                        $producto = array();
                        $producto['id_order_detail'] = $id_order_detail;
                        $producto['id_product'] = $id_product;
                        $producto['id_product_attribute'] = $id_product_attribute;
                        $producto['product_supplier_reference'] = $product_supplier_reference;
                        $producto['product_quantity'] = $product_quantity;
                        $producto['product_name'] = $product_name;
                        $producto['product_reference'] = $product_reference;

                        $info_productos[] = $producto;
    
                        //ahora chequeamos los productos Globomatik y los metemos a lafrips_dropshipping_globomatik_productos
                        if (!Globomatik::productosProveedorGlobomatik($info_productos, $id_order, $id_dropshipping_globomatik, false)) {    
                            //ha ocurrido algún error con los productos, metemos un log y salimos
                            $error = 'Error insertando/comprobando la información de productos Globomatik al añadir producto Backoffice';
    
                            $this->insertDropshippingLog($error, $id_order, $id_supplier, null, null, $id_lafrips_dropshipping);
                        }
    
                        break;
    
                    case 159: //Mars Gaming
                        
                        
                        break;

                    case 163: //Printful sin configurar

                    
                        break;
    
                    default:
                        //el id_supplier no corresponde a ninguno de los proveedores dropshipping que tenemos contemplados, o aún no he actualizado este módulo para un nuevo proveedor.  Marcamos error en lafrips_dropshipping y envío email aviso?
    
                                            
                }
                
            }


        } else {
            //hay que generar el pedido entero para dropshipping, lanzamos el proceso completo, sin llamada a API. Necesitamos generar el array info_dropshipping para llamar a procesaDropshipping()
            $info_dropshipping = array();
            //primero la info del producto dentro del array info_productos
            $info_productos = array();
            $producto = array();
            $producto['id_order_detail'] = $id_order_detail;
            $producto['id_product'] = $id_product;
            $producto['id_product_attribute'] = $id_product_attribute;
            $producto['product_supplier_reference'] = $product_supplier_reference;
            $producto['product_quantity'] = $product_quantity;
            $producto['product_name'] = $product_name;
            $producto['product_reference'] = $product_reference;

            $info_productos[] = $producto;

            //metemos producto a info_dropshipping
            $info_dropshipping['dropshipping'][$id_supplier] = $info_productos;
            //sería lo mismo esto - por si refactorizo
            //$info_dropshipping['dropshipping'][$id_supplier][] = $producto;

            //info del pedido, comprobamos que esté cargado $order
            if (!Validate::isLoadedObject($order) || (int)$order->id != (int)$id_order){ 
                $order = new Order($id_order);
            }            

            $info_dropshipping['order']['procesar_dropshipping'] = 0; //para evitar llamada a API
            $info_dropshipping['order']['id_employee'] = null; 
            $info_dropshipping['order']['id_order'] = $id_order; 
            $info_dropshipping['order']['id_customer'] = $order->id_customer;  
            $info_dropshipping['order']['id_address_delivery'] = $order->id_address_delivery; 
            $info_dropshipping['order']['payment'] = $order->payment; 
            $info_dropshipping['order']['date_add'] = $order->date_add; 

            //llamamos a procesaDropshipping()
            $this->procesaDropshipping($info_dropshipping);
        }    
        
    }

    //función que revisa la dirección de entrega de un pedido YA EXISTENTE para dropshipping, recibiendo id_order repasa y actualiza lo necesario en caso de haberse modificado la dirección
    public function revisarDireccion($id_order) {
        //Llamamos a setAddressinfo($id_order) que nos devuelve el id_dropshipping_address para el pedido (sea la misma, una modificada con mismo id, o una nueva). Comprobamos si en lafrips_dropshipping el pedido tiene ese id para dirección, si no es así hacemos update. Por ahora, usamos la misma dirección para todos los proveedores por pedido, envio alamcén o cliente en los mismos casos. Cuando eso cambie habrá que modificar las funciones aplicando condiciones para cada proveedor
        $id_dropshipping_direccion = $this->setAddressinfo($id_order);
        //con el id sacamos de la tabla dropshipping address el id_address_delivery
        $sql_id_address_delivery = 'SELECT id_address_delivery FROM lafrips_dropshipping_address WHERE id_dropshipping_address = '.$id_dropshipping_direccion;
        $id_address_delivery = Db::getInstance()->getValue($sql_id_address_delivery);

        //ahora tenemos que asegurarnos de que los pedidos con este id_order en lafrips_dropshipping, no cancelados, ni procesados, ni finalizados, tengan como id_address_delivery y id_dropshipping_address los que acabamos de obtener
        $sql_update_lafrips_dropshipping = 'UPDATE lafrips_dropshipping
        SET 
        id_address_delivery = '.$id_address_delivery.',
        id_dropshipping_address = '.$id_dropshipping_direccion.',
        date_upd = NOW()
        WHERE cancelado = 0
        AND procesado = 0
        AND finalizado = 0
        AND id_order = '.$id_order.'
        AND (id_dropshipping_address != '.$id_dropshipping_direccion.' OR id_address_delivery != '.$id_address_delivery.')';

        Db::getInstance()->Execute($sql_update_lafrips_dropshipping);
    }

    

}

