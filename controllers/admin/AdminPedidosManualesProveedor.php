<?php
/**
 * Generador de pedidos de manuales a proveedor. Solo para los que sea posible.
 * 25/08/2023 Cerdá, Karactermanía
 *
 *  @author    Sergio™ <sergio@lafrikileria.com>
 *    
 */

if (!defined('_PS_VERSION_'))
    exit;

class AdminPedidosManualesProveedorController extends ModuleAdminController {
    
    public function __construct() {
        require_once (dirname(__FILE__) .'/../../productosvendidossinstock.php');
        require_once (dirname(__FILE__).'/../../classes/Karactermania.php');
        require_once (dirname(__FILE__).'/../../classes/Cerda.php');
        require_once (dirname(__FILE__).'/../../classes/Heo.php');

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
        $this->addJs($this->module->getPathUri().'views/js/back_pedido_manual_proveedor.js');
        //añadimos la dirección para el css
        $this->addCss($this->module->getPathUri().'views/css/back_pedido_manual_proveedor.css');
    }


    /**
     * AdminController::renderForm() override
     * @see AdminController::renderForm()
     */
    public function renderForm() {    

        //primero buscamos el parámetro manualok en la url para ver si venimos de la creación de un pedido, y si es así mostrar mensaje
        if ($creado_correcto = Tools::getValue('manualok')) {
            if ($creado_correcto == 1) {
                //el anterior pedido se creó correctamente                
                $this->confirmations[] = $this->l('Pedido creado correctamente');                 
            } elseif ($creado_correcto == 2) {
                //el anterior pedido ha tenido algún error  
                $this->errors[] = Tools::displayError('¡¡ATENCIÓN!! Error en la creación del pedido');                         
            } 
        } 

        //preparamos le contenido del select para proveedor. a 30/08/2023 solo hacemos pedidos manuales de Cerdá y Karactermanía
        //29/08/2025 Añadimos Heo
        $suppliers = array(
            array('id_supplier'=> 0, 'name'=> 'Selecciona proveedor'),
            array('id_supplier'=> 65, 'name'=> 'Cerdá'),
            array('id_supplier'=> 4, 'name'=> 'Heo'),
            array('id_supplier'=> 53, 'name'=> 'Karactermanía')
        );
        
        //generamos el token de AdminPedidosManualesProveedor ya que lo vamos a usar en el archivo de javascript (pej para botón cancelar). Lo almacenaremos en un input hidden para acceder a el desde js
        $token_admin_modulo = Tools::getAdminTokenLite('AdminPedidosManualesProveedor');

        $this->fields_form = array(
            'legend' => array(
                'title' => 'pedidos manuales a proveedores',
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
                //input hidden donde almacenaremos una cadena con la parte numérica de los ids de los inputs válidos que hay que sacar al procesar el formualrio en postProcess
                array(  
                    'type' => 'hidden',                    
                    'name' => 'numero_productos',
                    'id' => 'numero_productos',
                    'required' => false,                                        
                ), 
                //Select con los proveedores disponibles para hacer pedidos manuales
                array(
                    'type' => 'select',
                    'label' => 'Proveedor',
                    'name' => 'id_supplier',
                    'required' => true,
                    'options' => array(
                        'query' => $suppliers,
                        'id' => 'id_supplier',
                        'name' => 'name'
                    ),
                    'hint' => 'Selecciona el proveedor del producto',
                ),
            ),            
            
            // 'reset' => array('title' => 'Limpiar', 'icon' => 'process-icon-eraser icon-eraser'),   
            // 'submit' => array('title' => 'Guardar', 'icon' => 'process-icon-save icon-save'),            
        );
        
        $this->displayInformation(
                'Selecciona el proveedor al que quieres hacer el pedido.<br>
                Introduce la referencia de cada producto que quieres pedir, si es un atributo, asegurate de que sea la referencia completa del atributo y pulsa buscar.<br>
                PULSA BUSCAR<br>
                Comprueba los datos obtenidos, recuerda que, por seguridad, solo puedes solicitar productos que ya existen en Prestashop.<br>
                Si todo es correcto, introduce la cantidad que deseas pedir. Pulsa Añadir Producto si quieres solicitar otros productos o Crear Pedido para terminar el pedido.'
        );

        return parent::renderForm();
    }

   

    public function postProcess() {

        parent::postProcess();
        
        //Tools::isSubmit('submitAddconfiguration')) se refiere al input hidden que se crea automáticamente con el formulario¿?
        if (((bool)Tools::isSubmit('submitAddconfiguration')) == true) {
            //var_dump($_POST);

            //obtenemos el proveedor
            $id_supplier = (int) Tools::getValue('id_supplier', false);

            if (in_array($id_supplier, array(4, 53, 65))) {
                $this->gestionPedido($id_supplier);
            } else {               
                //redirigimos al controlador, enviando por url la variable manualok, con valor 1 si es pedido correcto y valor 2 si fuera error. Se detecta en la función renderForm()
                $token = Tools::getAdminTokenLite('AdminPedidosManualesProveedor');
                    
                $url_base = _PS_BASE_URL_.__PS_BASE_URI__;
                
                $url = $url_base.'/lfadminia/index.php?controller=AdminPedidosManualesProveedor?manualok=2&token='.$token;  
                
                header("Location: $url");
            }      
            // $this->confirmations[] = $this->l('Pedido realizado');

            // $this->errors[] = Tools::displayError('Ese producto ya ha sido revisado');
            // $this->confirmations[] = $this->l('Producto en pedido '.$id_order.' marcado como Revisado. ');
            // $this->displayWarning('El pedido '.$id_order.' contiene otros productos sin revisar. ');
        }

        //parent::postProcess();
    }

    // función que lee el formulario para obtener las referencias de los productos y unidades a pedir y llamando a la clase Karactermania, Cerdá o Heo del módulo, en función del id_supplier detectado del select, que generará los archivos necesarios para el pedido o la llamada a API
    public function gestionPedido($id_supplier) {
        //iniciamos manualok, variable que pasaremos por url al recargar el proceso en renderform y que vale 1 si todo ok o 2 si hay errores
        $manualok = 1;

        //creamos el array a enviar
        $info_pedido = array();
        
        //sacamos la cadena con los números de los inputs enviados desde el valor del input hidden numero_productos. Hacemos explode por el _ que separa los números
        $numeros_ids_inputs = explode('_',Tools::getValue('numero_productos', false));            

        //por cada número de input debe haber una referencia de proveedor y una cantidad, metemos la info en un array que enviaremos a la función gestionXXXXXManual() de la clase del proveedor que corresponda
        foreach ($numeros_ids_inputs as $numero_id) {
            //por cada producto queremos volver a comprobar que está en la tabla product_supplier, pero se hará en la clase                              
            $referencia_proveedor = trim(Tools::getValue('supplier_reference_'.$numero_id, false));
            $unidades = (int) Tools::getValue('unidades_'.$numero_id, false);  

            $info_pedido[] = array(
                "referencia" => $referencia_proveedor,
                "unidades" => $unidades
            );
        }

        if ($id_supplier == 65) {
            //llamamos a gestionKaractermaniaManual() de la clase Karactermania
            if (!Cerda::gestionCerdaManual($info_pedido)) {
                //si devuelve false no se generó el pedido correctamente
                $manualok = 2;
            }
        } elseif ($id_supplier == 53) {
            //llamamos a gestionKaractermaniaManual() de la clase Karactermania
            if (!Karactermania::gestionKaractermaniaManual($info_pedido)) {
                //si devuelve false no se generó el pedido correctamente
                $manualok = 2;
            }
        } elseif ($id_supplier == 4) {
            //llamamos a gestionHeoManual() de la clase Heo
            if (!Heo::gestionHeoManual($info_pedido)) {
                //si devuelve false no se generó el pedido correctamente
                $manualok = 2;
            }
        }           

        //redirigimos al controlador, enviando por url la variable manualok, con valor 1 si es pedido correcto y valor 2 si fuera error. Se detecta en la función renderForm()
        $token = Tools::getAdminTokenLite('AdminPedidosManualesProveedor');
            
        $url_base = _PS_BASE_URL_.__PS_BASE_URI__;
        
        $url = $url_base.'/lfadminia/index.php?controller=AdminPedidosManualesProveedor?manualok='.$manualok.'&token='.$token;  
        
        header("Location: $url");
    }   

    /*
    * Función que busca el producto correspondiente a la referencia de proveedor insertada. Si no lo encuentra en Prestashop (no está creado) buscará en la tabla de catálogo de Cerdá, frik_catalogo_cerda_crear, y si no está ahí, mostrará error. Devolverá algunos datos del producto, incluyendo la foto principal.
    01/09/2023 Hemos añadido Karactermanía al módulo, de modo que hay que distinguir por proveedor. DEJAMOS DE BUSCAR EN CATALOGO CERDÁ
    //29/08/2025 Añadimos Heo para hacer los pedidos con su API
    *
    */
    public function ajaxProcessBuscaProducto(){        
        //asignamos a $referencia_buscar la referencia introducida por el usuario y que viene via ajax
        //01/09/2023 Añadido proveedor  
        $referencia_buscar = trim(Tools::getValue('referencia_buscar',0));
        $id_supplier = trim(Tools::getValue('id_supplier',0));
        if(!$referencia_buscar || !$id_supplier) {
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'No has introducido referencia de proveedor o seleccionado proveedor')));
        }

        $response = true;

        $info_producto = array();
        $info_producto['multiple_resultado_prestashop'] = 0;
        // $info_producto['multiple_resultado_cerda'] = 0;
        $info_producto['en_prestashop'] = 0;
        // $info_producto['en_catalogo_cerda'] = 0;

        //primero buscamos la referencia en lafrips_product_supplier y sacamos los datos que necesitamos si la encontramos, incluido si tiene permitir pedido, para el caso de que se use este formulario por los de atención cliente para saber stock en productos
        //MODIFICAR test en url_imagen para producción CONCAT( "http://lafrikileria.com/test", "/"

        //26/02/2024 Corrección al sacar el nombre, que salía duplicado si tenía dobles atributos, dando lugar al error de producto duplicado. Lo solucionamos usando GROUP_CONCAT para unir 
        // IFNULL(CONCAT(pla.name, " : ", CONCAT(agl.name, " - ", atl.name)), pla.name) AS nombre,
        $sql_product_supplier = 'SELECT IFNULL(CONCAT(pla.name, " : ", GROUP_CONCAT(DISTINCT agl.name, " - ", atl.name order by agl.name SEPARATOR ", ")), pla.name) AS nombre, 
        IFNULL(pat.reference, pro.reference) AS referencia, IFNULL(pat.ean13, pro.ean13) AS ean13, 
        ima.id_image AS id_imagen, CONCAT( "http://lafrikileria.com", "/", ima.id_image, "-home_default/", 
                pla.link_rewrite, ".", "jpg") AS url_imagen,
        ava.quantity AS stock, ava.out_of_stock AS out_of_stock, pro.id_product, IF(pro.id_product IN (SELECT id_product FROM lafrips_category_product WHERE id_category = 121), 1, 0) AS prepedido
        FROM lafrips_product_supplier psu
        JOIN lafrips_product pro ON psu.id_product = pro.id_product
        JOIN lafrips_product_lang pla ON psu.id_product = pla.id_product AND pla.id_lang = 1
        JOIN lafrips_image ima ON ima.id_product = psu.id_product AND ima.cover = 1
        JOIN lafrips_stock_available ava ON ava.id_product = psu.id_product AND ava.id_product_attribute = psu.id_product_attribute
        LEFT JOIN lafrips_product_attribute pat ON pat.id_product = psu.id_product AND pat.id_product_attribute = psu.id_product_attribute
        LEFT JOIN lafrips_product_attribute_combination pac ON pac.id_product_attribute = pat.id_product_attribute
        LEFT JOIN lafrips_attribute atr ON atr.id_attribute = pac.id_attribute
        LEFT JOIN lafrips_attribute_lang atl ON atl.id_attribute = atr.id_attribute AND atl.id_lang = 1
        LEFT JOIN lafrips_attribute_group_lang agl ON agl.id_attribute_group = atr.id_attribute_group AND agl.id_lang = 1
        WHERE psu.id_supplier = '.$id_supplier.'
        AND psu.product_supplier_reference = "'.$referencia_buscar.'"';

        // die(Tools::jsonEncode(array('message'=>$sql_product_supplier))); 

        $product_supplier = Db::getInstance()->executeS($sql_product_supplier);

        //ponemos [0]['nombre'] porque la consulta, aunque no encuentre nada da Null, lo cual es un resultado y $product_supplier no sería null
        if(!$product_supplier[0]['nombre']){ 
            //01/09/2023 Al añadir otro proveedor ya no buscamos en catálogo de Cerdá
            die(Tools::jsonEncode(array('message'=>'Referencia no encontrada', 'info_producto' => $info_producto))); 


            //si la referencia no existe en Prestashop, la buscamos en frik_catalogo_cerda_crear
            // $sql_catalogo_cerda = 'SELECT nombre, subfamilia, personaje, imagen, desc_talla, ean
            // FROM frik_catalogo_cerda_crear 
            // WHERE referencia = "'.$referencia_buscar.'"';

            // $catalogo_cerda = Db::getInstance()->executeS($sql_catalogo_cerda);

            // if(!$catalogo_cerda){
            //     //si la referencia no existe en frik_catalogo_cerda_crear avisaremos de que no lo encontramos para que el usuario compruebe la referencia y actue a su discreción. $info_producto contiene los valores 'en_prestashop' y 'en_catalogo_cerda' a 0       
            //     die(Tools::jsonEncode(array('message'=>'Referencia no encontrada', 'info_producto' => $info_producto)));    
                
            // } else {
            //     //la referencia está en frik_catalogo_cerda_crear, comprobamos que el resultado es único, si no lo es mostramos error y si no enviamos los datos obtenidos            
            //     $info_producto['en_catalogo_cerda'] = 1;
            //     $info_producto['info_catalogo_cerda'] = $catalogo_cerda;

            //     if (COUNT($catalogo_cerda) > 1)  {
            //         //referencia duplicada en catálogo, error de catálogo, avisamos
            //         $info_producto['multiple_resultado_cerda'] = 1;
            //     }
            //     die(Tools::jsonEncode(array('message'=>'Referencia encontrada en Catálogo Cerdá', 'info_producto' => $info_producto)));
            // }
            

        } elseif (COUNT($product_supplier) > 1)  {
            //la referencia está en lafrips_product_supplier pero corresponde a más de un producto, buscamos la info y enviamos los datos obtenidos
            $info_producto['en_prestashop'] = 1;
            $info_producto['multiple_resultado_prestashop'] = 1;
            $info_producto['product_supplier'] = $product_supplier;
            die(Tools::jsonEncode(array('message'=>'Referencia duplicada', 'info_producto' => $info_producto))); 

        } else {
            //la referencia está en lafrips_product_supplier, ofreciendo solo un resultado, buscamos el producto en frik_import_catalogos para ver si tiene disponibilidad = 1, ya que si el producto tiene stock no le ponemos permitir pedido y no sabemos si está disponible en proveedor, aunque la tabla import_catalogos puede llevar mucho tiempo sin actualizarse. 
            $sql_import_catalogos = "SELECT id_import_catalogos, disponibilidad, date_add, date_upd FROM frik_import_catalogos WHERE id_proveedor = $id_supplier
            AND referencia_proveedor = '$referencia_buscar'";
            
            $import_catalogos = Db::getInstance()->executeS($sql_import_catalogos);

            if(!$import_catalogos[0]['id_import_catalogos']){
                //no encontrado en tabla de catálogos
                // $product_supplier['catalogos'] = false;
                $product_supplier['disponibilidad_catalogos'] = 0;
                $product_supplier['mensaje_catalogos'] = "El producto no se encontró en tabla de catálogos,<br> no se puede confirmar su disponibilidad";

            } elseif (COUNT($import_catalogos) > 1)  {
                //corresponde a varias líneas en import_catalogos
                // $product_supplier['catalogos'] = false;
                $product_supplier['disponibilidad_catalogos'] = 0;
                $product_supplier['mensaje_catalogos'] = "Error, la referencia corresponde a <br>varios productos en tabla de catálogos, <br>no se puede confirmar su disponibilidad";

            } else {
                // $product_supplier['catalogos'] = true;
                //solo hay una línea que corresponda. Vamos a enviar la última fecha en que se reviso el producto en un catálogo. Sacmos date_add y date_upd, si date_upd existe es la que usamos, si es null cogemos date_add
                if ($import_catalogos[0]['date_upd'] === null) {
                    $ultima_fecha_catalogo = $import_catalogos[0]['date_add'];
                } else {
                    $ultima_fecha_catalogo = $import_catalogos[0]['date_upd'];
                }

                //formateamos fecha
                $dateTime = new DateTime($ultima_fecha_catalogo);
                
                $ultima_fecha_catalogo = $dateTime->format('d-m-Y H:i:s');

                //Comprobamos si disponibilidad es 1, otro valor sería NO
                if ($import_catalogos[0]['disponibilidad'] == 1) {
                    $product_supplier['disponibilidad_catalogos'] = 1;
                    $product_supplier['mensaje_catalogos'] = "Última fecha confirmado disponible<br> en catálogo - ".$ultima_fecha_catalogo;
                } else {
                    $product_supplier['disponibilidad_catalogos'] = 0;
                    $product_supplier['mensaje_catalogos'] = "Última fecha confirmado No disponible<br> en catálogo - ".$ultima_fecha_catalogo;
                }
            }            

            $info_producto['en_prestashop'] = 1;
            $info_producto['product_supplier'] = $product_supplier;
            die(Tools::jsonEncode(array('message'=>'Referencia encontrada en Prestashop', 'info_producto' => $info_producto)));
        }            
        
        // if($response)
        //     die(Tools::jsonEncode(array('message'=>'Referencia encontrada en Prestashop', 'info_producto' => $info_producto)));
    }



}
