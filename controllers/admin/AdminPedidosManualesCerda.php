<?php
/**
 * Generador de pedidos de proveedor manuales a Cerdá
 *
 *  @author    Sergio™ <sergio@lafrikileria.com>
 *    
 */

if (!defined('_PS_VERSION_'))
    exit;

class AdminPedidosManualesCerdaController extends ModuleAdminController {
    
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
        $this->addJs($this->module->getPathUri().'views/js/back_pedido_cerda_manual.js');
        //añadimos la dirección para el css
        $this->addCss($this->module->getPathUri().'views/css/back_pedido_cerda_manual.css');
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
                $this->errors[] = Tools::displayError('Error en la creación del pedido');         
            } 
        } 
        
        //generamos el token de AdminPedidosManualesCerda ya que lo vamos a usar en el archivo de javascript (pej para botón cancelar). Lo almacenaremos en un input hidden para acceder a el desde js
        $token_admin_modulo = Tools::getAdminTokenLite('AdminPedidosManualesCerda');

        $this->fields_form = array(
            'legend' => array(
                'title' => 'pedidos manuales a Cerdá',
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
            ),
            
            // 'reset' => array('title' => 'Limpiar', 'icon' => 'process-icon-eraser icon-eraser'),   
            // 'submit' => array('title' => 'Guardar', 'icon' => 'process-icon-save icon-save'),            
        );
        
        $this->displayInformation(
                'Introduce la referencia de cada producto que quieres pedir, si es un atributo, asegurate de que sea la referencia completa del proveedor Cerdá y pulsa buscar.<br>
                Comprueba los datos obtenidos, recuerda que, por seguridad, solo puedes solicitar productos que ya tenemos en Prestashop. Si el producto no existe en Prestashop, pero lo tenemos en el catálogo disponible de Cerdá, podrás crearlo con el importador si es de Cerdá Adult, o tendrás que pedir que te lo creen si es de Cerdá Kids.<br>
                Si todo es correcto, introduce la cantidad que deseas pedir. Pulsa Añadir Producto si quieres solicitar otros productos o Crear Pedido para terminar el pedido.'
        );
        return parent::renderForm();
    }

   

    public function postProcess() {

        parent::postProcess();
        
        //Tools::isSubmit('submitAddconfiguration')) se refiere al input hidden que se crea automáticamente con el formulario¿?
        if (((bool)Tools::isSubmit('submitAddconfiguration')) == true) {
            //var_dump($_POST);

            //sacamos los id_manufacturer de kids y adult para poner en frik_pedidos_cerda el target_cerda
            //id de manufacturer kids
            $id_manufacturer_kids = (int)Manufacturer::getIdByName('Cerdá Kids');

            //id de manufacturer adult
            $id_manufacturer_adult = (int)Manufacturer::getIdByName('Cerdá Adult');
            
            // $this->errors[] = Tools::displayError('Número inputs = '.Tools::getValue('numero_productos', false));
            // $this->errors[] = Tools::displayError('Número inputs = '.Tools::getValue('numero_productos', false));

            //preparamos el csv que vamos a generar, uno para la carpeta compartida con Cerdá en el servidor y otra copia para mi 
            $delimiter = ";";
            $filename = "frikileria_".date("Y-m-d_His").".csv";

            //para mi pongo que es manual en el nombre
            $filename_copia = "frikileria_".date("Y-m-d_His")."_MANUAL.csv";
                
            //creamos el puntero del csv, para escritura
            //$f = fopen('php://memory', 'w');
            // TEST $f = fopen('ftp://lafrikileria.com:L4fr1k12018@ftp.lafrikileria.com/html/proveedores/subidas/'.$filename,'w');
            $f = fopen('ftp://lafrikileria.com:L4fr1k12018@ftp.lafrikileria.com/html/proveedores/cerda/pedidos/'.$filename,'w');

            //otro para guardarme una copia
            // TEST $copia = fopen('ftp://lafrikileria.com:L4fr1k12018@ftp.lafrikileria.com/html/proveedores/subidas/prueba/'.$filename,'w');
            $copia = fopen('ftp://lafrikileria.com:L4fr1k12018@ftp.lafrikileria.com/html/proveedores/subidas/cerda_pedidos/'.$filename_copia,'w');

            //ponemos los campos para cabecera del csv
            $fields = array('referencia', 'cantidad', 'pedido');
            fputcsv($f, $fields, $delimiter);
            //copia para mi
            fputcsv($copia, $fields, $delimiter);
            
            //por cada línea resultante de la consulta, metemos los datos en un array que con fputcsv introducimos en el csv, con el delimitador ','
            $mensaje = '';
            $info_pedido = 'referencia;cantidad;pedido<br>';
            //creamos un pedido de cliente ficticio ya que hay que enviarselo a Cerdá. El número lo sacamos de la tabla frik_pedido_cerda, recogiendo el mayor id_order de un pedido manual, y sumándole 1. Empezamos desde 1000000. Sacamos el id:
            $sql_id_order = 'SELECT MAX(id_order) AS id_order FROM frik_pedidos_cerda WHERE pedido_manual = 1';
            $id_order = Db::getInstance()->executeS($sql_id_order);
            $id_order = $id_order[0]['id_order'] + 1;

            //sacamos la cadena con los números de los inputs enviados desde el valor del input hidden numero_productos. Hacemos explode por el _ que separa los números
            $numeros_ids_inputs = explode('_',Tools::getValue('numero_productos', false));

            //iniciamos manualok, variable que pasaremos por url al recargar el proceso en renderform y que vale 1 si todo ok o 2 si hay errores
            $manualok = 1;

            //por cada número de input debe haber una referencia de proveedor y una cantidad, por cada uno generaremos una línea para el csv            
            foreach ($numeros_ids_inputs as $numero_id) {
                //por cada producto queremos volver a comprobar que está en la tabla product_supplier, sacando su id_product y su id_product_attribute para añadir la info a la tabla de pedidos frik_pedidos_cerda                
                $referencia_proveedor = trim(Tools::getValue('supplier_reference_'.$numero_id, false));
                $unidades = (int) Tools::getValue('unidades_'.$numero_id, false);  

                //buscar id_product e id_product_attribute, ean e id_manufacturer. Primero hacemos query a prestashop, product_supplier, si no da resultado mostraremos error (Jquery no debería dejar pasar el producto en la vista de creación del pedido)
                $sql_producto_en_prestashop = 'SELECT psu.id_product AS id_product, psu.id_product_attribute AS id_product_attribute, pro.id_manufacturer AS id_manufacturer,
                IFNULL(CONCAT(pla.name, " : ", CONCAT(agl.name, " - ", atl.name)), pla.name) AS nombre, 
                IFNULL(pat.ean13, pro.ean13) AS ean13
                FROM lafrips_product_supplier psu
                JOIN lafrips_product pro ON psu.id_product = pro.id_product
                JOIN lafrips_product_lang pla ON psu.id_product = pla.id_product AND pla.id_lang = 1 
                LEFT JOIN lafrips_product_attribute pat ON pat.id_product = psu.id_product AND pat.id_product_attribute = psu.id_product_attribute
                LEFT JOIN lafrips_product_attribute_combination pac ON pac.id_product_attribute = pat.id_product_attribute
                LEFT JOIN lafrips_attribute atr ON atr.id_attribute = pac.id_attribute
                LEFT JOIN lafrips_attribute_lang atl ON atl.id_attribute = atr.id_attribute AND atl.id_lang = 1
                LEFT JOIN lafrips_attribute_group_lang agl ON agl.id_attribute_group = atr.id_attribute_group AND agl.id_lang = 1
                WHERE psu.id_supplier = 65
                AND psu.product_supplier_reference = "'.$referencia_proveedor.'"';

                $producto_en_prestashop = Db::getInstance()->executeS($sql_producto_en_prestashop);

                if(!$producto_en_prestashop[0]['nombre']){ 
                    //producto NO existe en Prestashop
                    //javascript no debería haber dejado crearse el pedido
                    //generamos error que mostraremos con ayuda de la varible manualok en la url, que interpretamos en renderform

                    //de momento, pasamos del producto y enviamos error a render form
                    $manualok = 2;                       

                } elseif (count($producto_en_prestashop) > 1) {
                    //referencia repetida, error
                    //javascript no debería haber dejado crearse el pedido
                    //generamos error que mostraremos con ayuda de la varible manualok en la url, que interpretamos en renderform
                    
                    //de momento, pasamos del producto y enviamos error a render form
                    $manualok = 2;

                }else {
                    //Encontrado un producto con esa referencia
                    //$manualok = 1;
                    $id_product = $producto_en_prestashop[0]['id_product'];
                    $id_product_attribute = $producto_en_prestashop[0]['id_product_attribute'];
                    $id_manufacturer = $producto_en_prestashop[0]['id_manufacturer'];
                    $nombre = $producto_en_prestashop[0]['nombre'];
                    $ean13 = $producto_en_prestashop[0]['ean13'];  

                    //si el producto es kids pondremos target kids y si es adult pondremos adult
                    if ($id_manufacturer == $id_manufacturer_kids) {
                        $target_cerda = 'KIDS';
                    } elseif ($id_manufacturer == $id_manufacturer_adult) {
                        $target_cerda = 'ADULT';
                    } else {
                        //pueden ser productos Cerdá antiguos que no tiene id_manufacturer correcto
                        $target_cerda = 'Error';
                    }

                    //si la cosa es correcta metemos el producto al csv
                    $lineData = array($referencia_proveedor, $unidades, $id_order);
                    fputcsv($f, $lineData, $delimiter);
                    //copia para mi
                    fputcsv($copia, $lineData, $delimiter);

                    //vamos generando mensaje para email
                    $info_pedido .= $referencia_proveedor.';'.$unidades.';'.$id_order.'<br>';
                    $mensaje .= '<br>'.$referencia_proveedor.','.$unidades.','.$id_order;
                    $mensaje .= '<br>'.$nombre.' - Id: '.$id_product.' - Ref: '.$referencia_proveedor.' - Ean: '.$ean13.', '.$unidades.' unidades<br>';

                    $id_empleado = Context::getContext()->employee->id;     
                                  

                    //añadimos línea a frik_pedidos_cerda
                    $sql_insert = 'INSERT INTO frik_pedidos_cerda
                    (id_product,
                    id_product_attribute,
                    product_name,
                    ean,
                    referencia,
                    target_cerda,
                    unidades,
                    id_order,
                    pedido_manual,
                    id_empleado,                    
                    date_add)
                    VALUES
                    ('.$id_product.',
                    '.$id_product_attribute.',
                    "'.$nombre.'",
                    "'.$ean13.'",
                    "'.$referencia_proveedor.'",
                    "'.$target_cerda.'",
                    '.$unidades.',
                    '.$id_order.',
                    1,
                    '.$id_empleado.',                    
                    NOW())';

                    Db::getInstance()->execute($sql_insert);                   

                }    

            }   

            //cerramos el puntero / archivo csv
            fclose($f);
            //cerramos copia
            fclose($copia);

            //20/12/2021 A veces por akguna razón no se genera el archivo, vamos a asegurarnos de que el archivo existe en la carpeta de proveedores/cerda, y si no enviamos un email con los datos para crear el pedido a mano. Vamos a la carpeta donde se crea el archivo y sacamos todos los archivos, comprobando que el que acabamos de crear está en la carpeta
            $path = '/var/www/vhost/lafrikileria.com/home/html/proveedores/cerda/pedidos/';
            $files = glob($path.'*.csv');

            $existe_archivo = 0;
            $error_archivo = '';

            foreach($files as $file) {
                if (preg_match('/'.$filename.'/', basename($file))){
                    $existe_archivo = 1;
                }
            }

            if (!$existe_archivo) {
                //no se ha encontrado el archivo
                $error_archivo = 'ERROR - ARCHIVO NO CREADO - ';   
                $mensaje .= '<br><br>ERROR - ARCHIVO NO GENERADO';             
            }

            $mensaje .= '<br><br>Información para generar a mano: <br><br>'.$filename.'<br><br>'.$info_pedido;            

            $nombre_empleado = Context::getContext()->employee->firstname.' '.Context::getContext()->employee->lastname;              
            $mensaje .= '<br><br> - Creado: '.$nombre_empleado;
            
            //enviamos email al email correspondiente al empleado que haya creado el pedido

            $email_empleado = Context::getContext()->employee->email;

            //enviamos un email a mi cuenta con la info
            $info = [];                
            $info['{firstname}'] = $nombre_empleado;
            $info['{archivo_expediciones}'] = 'Pedido MANUAL a Proveedor Cerdá '.date("Y-m-d H:i:s");
            $info['{errores}'] = $mensaje;
            // print_r($info);
            // $info['{order_name}'] = $order->getUniqReference();
            @Mail::Send(
                1,
                'aviso_error_expedicion_cerda', //plantilla
                Mail::l($error_archivo.'Pedido MANUAL realizado a Cerdá '.date("Y-m-d H:i:s"), 1),
                $info,
                [$email_empleado,'sergio@lafrikileria.com'],
                $nombre_empleado,
                null,
                null,
                null,
                null,
                _PS_MAIL_DIR_,
                true,
                1
            );

 
            //redirigimos al controlador, enviando por url la variable manualok, con valor 1 si es pedido correcto y valor 2 si fuera error. Se detecta en la función renderForm()
            $token = Tools::getAdminTokenLite('AdminPedidosManualesCerda');
                
            $url_base = _PS_BASE_URL_.__PS_BASE_URI__;
            
            $url = $url_base.'/lfadminia/index.php?controller=AdminPedidosManualesCerda?manualok='.$manualok.'&token='.$token;  
            
            header("Location: $url");

            
            // $this->confirmations[] = $this->l('Pedido realizado');

            // $this->errors[] = Tools::displayError('Ese producto ya ha sido revisado');
            // $this->confirmations[] = $this->l('Producto en pedido '.$id_order.' marcado como Revisado. ');
            // $this->displayWarning('El pedido '.$id_order.' contiene otros productos sin revisar. ');
        }

        //parent::postProcess();
    }

    /*
    * Función que busca el producto correspondiente a la referencia de proveedor insertada. Si no lo encuentra en Prestashop (no está creado) buscará en la tabla de catálogo de Cerdá, frik_catalogo_cerda_crear, y si no está ahí, mostrará error. Devolverá algunos datos del producto, incluyendo la foto principal.
    *
    */
    public function ajaxProcessBuscaProducto(){        
        //asignamos a $referencia_buscar la referencia introdcida por el usuario y que viene via ajax  
        $referencia_buscar = trim(Tools::getValue('referencia_buscar',0));
        if(!$referencia_buscar) {
            die(Tools::jsonEncode(array('error'=> true, 'message'=>'No has introducido referencia de proveedor.')));
        }

        $response = true;

        $info_producto = array();
        $info_producto['multiple_resultado_prestashop'] = 0;
        $info_producto['multiple_resultado_cerda'] = 0;
        $info_producto['en_prestashop'] = 0;
        $info_producto['en_catalogo_cerda'] = 0;

        //primero buscamos la referencia en lafrips_product_supplier y sacamos los datos que necesitamos si la encontramos, incluido si tiene permitr pedido, para el caso de que se use este formulario por los de atención cliente para saber stock en productos
        //MODIFICAR test en url_imagen para producción CONCAT( "http://lafrikileria.com/test", "/"
        $sql_product_supplier = 'SELECT IFNULL(CONCAT(pla.name, " : ", CONCAT(agl.name, " - ", atl.name)), pla.name) AS nombre, 
        IFNULL(pat.reference, pro.reference) AS referencia, IFNULL(pat.ean13, pro.ean13) AS ean13, 
        ima.id_image AS id_imagen, CONCAT( "http://lafrikileria.com", "/", ima.id_image, "-home_default/", 
                pla.link_rewrite, ".", "jpg") AS url_imagen,
        ava.quantity AS stock, ava.out_of_stock AS out_of_stock
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
        WHERE psu.id_supplier = 65
        AND psu.product_supplier_reference = "'.$referencia_buscar.'"';

        $product_supplier = Db::getInstance()->executeS($sql_product_supplier);

        //ponemos [0]['nombre'] porque la consulta, aunque no encuentre nada da Null, lo cual es un resultado y $product_supplier no sería null
        if(!$product_supplier[0]['nombre']){ 
            //si la referencia no existe en Prestashop, la buscamos en frik_catalogo_cerda_crear
            $sql_catalogo_cerda = 'SELECT nombre, subfamilia, personaje, imagen, desc_talla, ean
            FROM frik_catalogo_cerda_crear 
            WHERE referencia = "'.$referencia_buscar.'"';

            $catalogo_cerda = Db::getInstance()->executeS($sql_catalogo_cerda);

            if(!$catalogo_cerda){
                //si la referencia no existe en frik_catalogo_cerda_crear avisaremos de que no lo encontramos para que el usuario compruebe la referencia y actue a su discreción. $info_producto contiene los valores 'en_prestashop' y 'en_catalogo_cerda' a 0       
                die(Tools::jsonEncode(array('message'=>'Referencia no encontrada', 'info_producto' => $info_producto)));    
                
            } else {
                //la referencia está en frik_catalogo_cerda_crear, comprobamos que el resultado es único, si no lo es mostramos error y si no enviamos los datos obtenidos            
                $info_producto['en_catalogo_cerda'] = 1;
                $info_producto['info_catalogo_cerda'] = $catalogo_cerda;

                if (COUNT($catalogo_cerda) > 1)  {
                    //referencia duplicada en catálogo, error de catálogo, avisamos
                    $info_producto['multiple_resultado_cerda'] = 1;
                }
                die(Tools::jsonEncode(array('message'=>'Referencia encontrada en Catálogo Cerdá', 'info_producto' => $info_producto)));
            }
            

        } elseif (COUNT($product_supplier) > 1)  {
            //la referencia está en lafrips_product_supplier pero corresponde a más de un producto, buscamos la info y enviamos los datos obtenidos
            $info_producto['en_prestashop'] = 1;
            $info_producto['multiple_resultado_prestashop'] = 1;
            $info_producto['product_supplier'] = $product_supplier;
            die(Tools::jsonEncode(array('message'=>'Referencia duplicada', 'info_producto' => $info_producto))); 

        } else {
            //la referencia está en lafrips_product_supplier, ofreciendo solo un resultado, buscamos la info y enviamos los datos obtenidos 
            $info_producto['en_prestashop'] = 1;
            $info_producto['product_supplier'] = $product_supplier;
            die(Tools::jsonEncode(array('message'=>'Referencia encontrada en Prestashop', 'info_producto' => $info_producto)));
        }      
        


        
        // if($response)
        //     die(Tools::jsonEncode(array('message'=>'Referencia encontrada en Prestashop', 'info_producto' => $info_producto)));
    }



}
