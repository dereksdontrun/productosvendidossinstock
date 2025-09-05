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

//28/08/2025
//todas las funciones para trabajar con pedidos Heo de manera estática

class Heo
{
    private static $test = false;

    private static $api_credentials = [];


    //función que dado un id_order busca sus productos de Heo en lafrips_productos_vendidos_sin_stock y realiza la petición API a su sistema 
    //obtenemos de productos vendidos sin stock la info de la venta y la insertamos en lafrips_pedidos_heo si no existe de antemano. Si existe comprobamos cantidades y si son diferentes las pedimos (si ya fue pedido pedimos la diferencia si es superior) Después, si no está marcado como ya pedido, generamos el csv correspondiente al pedido. Finalmente marcamos estos como revisados en lafrips_productos_vendidos_sin_stock
    
    public static function gestionHeo($id_order) {
        $sql_productos_vendidos_sin_stock = "SELECT * FROM lafrips_productos_vendidos_sin_stock WHERE id_order_detail_supplier = 4 AND id_order = $id_order";

        $productos_heo = Db::getInstance()->ExecuteS($sql_productos_vendidos_sin_stock);

        if(count($productos_heo) > 0){ 
            foreach($productos_heo as $producto){
                $id_product = $producto['id_product'];
                $id_product_attribute = $producto['id_product_attribute'];
                $product_name = pSQL($producto['product_name']); 
                $referencia_prestashop = $producto['product_reference'];               
                $referencia_heo = $producto['product_supplier_reference'];
                $unidades = $producto['product_quantity'];                
                $date_order_accepted = $producto['date_add'];

                $ean = HerramientasVentaSinStock::getEan($id_product, $id_product_attribute) ? HerramientasVentaSinStock::getEan($id_product, $id_product_attribute) : "";

                $sql_tabla_heo = "SELECT id_pedidos_heo, unidades, api_ok
                FROM lafrips_pedidos_heo
                WHERE id_order = $id_order
                AND id_product = $id_product
                AND id_product_attribute = $id_product_attribute";

                if ($tabla_heo = Db::getInstance()->getRow($sql_tabla_heo)) {
                    //el producto y pedido ya se encuentran, si además api_ok = 1 es que ya se generó la llamada API, ignoramos la línea, si se quisiera pedir otra vez u otras cantidades para el mismo producto se hará a mano
                    if ($tabla_heo['api_ok']) {
                        continue;
                        
                    } else {
                        //no fue pedido a la API, comparamos cantidad a pedir y hacemos update si es diferente. En caso de ser prepedido se modificará pero igualmente no se generará en FTP 
                        if ($unidades != $tabla_heo['unidades']) {                           

                            $sql_update_tabla_heo = "UPDATE lafrips_pedidos_heo
                            SET                            
                            unidades = $unidades,                            
                            comentario = CONCAT(comentario, ' | Linea existia: ftp 0 a 0, unidades ".$tabla_heo['unidades']." a $unidades - ', DATE_FORMAT(NOW(),'%d-%m-%Y %H:%i:%s')),
                            date_upd = NOW()
                            WHERE id_order = $id_order 
                            AND id_product = $id_product
                            AND id_product_attribute = $id_product_attribute";

                            Db::getInstance()->execute($sql_update_tabla_heo);
                            
                        } else {
                            continue;
                        }   
                    } 
                    
                } else {
                    //hacemos insert
                    $sql_insert = "INSERT INTO lafrips_pedidos_heo
                    (id_order, id_product, id_product_attribute, referencia_heo, unidades, product_name, ean, referencia_prestashop, date_order_accepted, date_add)
                    VALUES
                    (
                        $id_order, $id_product, $id_product_attribute, '$referencia_heo', $unidades, '$product_name', '$ean', '$referencia_prestashop', '$date_order_accepted', NOW()
                    )";

                    Db::getInstance()->execute($sql_insert);

                }                
            }

            //ahora tenemos en lafrips_pedidos_heo los productos con api_ok 0 y los datos correspondientes para realizar la llamada api. Si eran productos ya pedidos y con api_ok=1, se ignorarán en la sql de la función de llamar a la api
            if (self::setHeoAPI($id_order)) {
                return;
            } else {
                return;
            }
        }   

        return;
    }

    public static function setHeoAPI($id_order) {        
        $mensaje = "";
        $error_archivo = "";
        $ids_pedidos_heo = array();

        //sacamos la info necesaria. Ponemos forzado el id_supplier y la referencia de Heo como referencia_proveedor dado que la info será enviada a una función general común en Herramientas.php (setMensajePedido) que se podrá usar para otros proveedores.
        $sql_info_api = "SELECT id_pedidos_heo, 4 AS id_supplier, id_product, id_product_attribute, product_name, referencia_prestashop, referencia_heo AS referencia_proveedor, unidades, pedido_manual, id_empleado
        FROM lafrips_pedidos_heo
        WHERE api_ok = 0        
        AND error = 0
        AND id_order = $id_order";

        if ($info_api = Db::getInstance()->executeS($sql_info_api)) {
            //preparamos el json para la API. "notice" es un mensaje para Heo de modo que por ahora no utilizamos, en orderReference ponemos nuestro id de pedido, productNumber es la referencia de Heo y orderItemReference nuestra referencia de producto.
            //"notice" es un mensaje para Heo, de momento lo ignoramos
            /*
            {
                "orderReference": "string",
                "notice": "string",
                "items": [
                    {
                    "productNumber": "string",
                    "quantity": 0,
                    "orderItemReference": "string"
                    }
                ]
            }
            */           
            
            // Construcción del array base
            $data = [
                "orderReference" => (string)$id_order,    
                "notice" => "",            
                "items" => []
            ];

            //en lugar de psarles la referencia de prestashop le pasamos id+atributo
            foreach($info_api AS $info) {
                $data["items"][] = [
                    "productNumber" => $info["referencia_proveedor"],
                    "quantity" => (int)$info["unidades"],
                    "orderItemReference" => $info["id_product"].'_'.$info["id_product_attribute"]
                ];                

                //marcador que indica que el pedido se creó manualmente y por tanto no existe pedido de cliente, 0 es no manual, 1 es manual, cuando salgamos del foreach de info_api la variable seguirá teniendo el valor para luego. Igual para id_empleado que utilizaremos para enviar email al empleado que haya realizado el pedido
                $pedido_manual = $info['pedido_manual'];
                $id_empleado = $info['id_empleado'];                

                //almacenamos cada id de la tabla para marcar api_ok = 1 si el proceso es correcto
                $ids_pedidos_heo[] = $info['id_pedidos_heo'];
            }

            // Llamada API
            $json_data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $resultado = self::callAPIHeo($json_data);

            $mensaje_resultado = $resultado['mensaje'];
            $respuesta_api = $resultado['respuesta_api'];
            
            //si no hubo errores actualizamos las tablas
            if ($resultado['success']) {             
                foreach ($ids_pedidos_heo AS $id_pedidos_heo) {
                    $sql_update = "UPDATE lafrips_pedidos_heo
                                SET api_ok = 1,
                                    respuesta_api = '".pSQL($respuesta_api)."',
                                    date_upd = NOW()
                                WHERE id_pedidos_heo = $id_pedidos_heo";
                    Db::getInstance()->execute($sql_update);
                }

                // Marcar productos como solicitados en lafrips_productos_vendidos_sin_stock (solo si no es manual)
                //marcamos solicitado para que los pedidos no aparezcan para generar pedidos de materiales en productos vendidos sin stock
                if (!$pedido_manual) {
                    foreach($info_api AS $info) {
                        $sql_update = "UPDATE lafrips_productos_vendidos_sin_stock
                                    SET solicitado = 1,
                                        id_employee_solicitado = 44,
                                        date_solicitado = NOW(),
                                        checked = 1,
                                        date_checked = NOW(),
                                        id_employee = 44,
                                        date_upd = NOW()
                                    WHERE id_order = $id_order
                                    AND id_product = ".$info['id_product']."
                                    AND id_product_attribute = ".$info['id_product_attribute'];
                        Db::getInstance()->execute($sql_update);
                    }
                }

                // Añadir mensaje interno en pedido (solo si no es manual)
                if (!$pedido_manual) {
                    if (!HerramientasVentaSinStock::setMensajePedido($id_order, $info_api)) {
                        $error_archivo .= "WARNING - Error mensaje interno para pedido - ";
                        $mensaje .= "<br><br>Error añadiendo mensaje interno de compra a pedido";
                    }
                }
                         
                
            } else {
                $error_archivo .= "ERROR en petición API - ";

                if ($respuesta_api === null) {
                    $respuesta_api = "Sin respuesta de API (".$mensaje_resultado.")";
                } else {
                    $respuesta_api = is_array($respuesta_api) 
                        ? json_encode($respuesta_api, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
                        : (string)$respuesta_api;
                }

                 // Error API → marcar error = 1, api_ok sigue en 0
                foreach ($ids_pedidos_heo AS $id_pedidos_heo) {
                    $sql_update = "UPDATE lafrips_pedidos_heo
                                SET error = 1,
                                    respuesta_api = '".pSQL($respuesta_api)."',
                                    comentario = CONCAT(comentario, ' | Error: API no aceptó pedido - ', '".pSQL($mensaje_resultado)."', ' - ', DATE_FORMAT(NOW(),'%d-%m-%Y %H:%i:%s')),
                                    date_upd = NOW()
                                WHERE id_pedidos_heo = $id_pedidos_heo";
                    Db::getInstance()->execute($sql_update);
                }

                //hacemos update a lafrips_productos_vendidos_sin_stock para marcar como revisado 0
                //también quitamos solicitado para que sea evidente en la lista de productos vendidos sin stock que no se ha pedido
                if (!$pedido_manual) {
                    foreach($info_api AS $info) {
                        $sql_update = "UPDATE lafrips_productos_vendidos_sin_stock
                                    SET solicitado = 0,
                                        checked = 0,
                                        date_upd = NOW()
                                    WHERE id_order = $id_order
                                    AND id_product = ".$info['id_product']."
                                    AND id_product_attribute = ".$info['id_product_attribute'];
                        Db::getInstance()->execute($sql_update);
                    }
                } 
            }            
            
            $empleados = array(
                array('nombre' => 'Sergio', 'email' => 'sergio@lafrikileria.com')
            );

            if ($pedido_manual) {               

                //si el pedido es manual enviamos a Lorena también
                $empleados[] = array('nombre' => 'Lorena', 'email' => 'lorena@lafrikileria.com');

                $employee = new Employee($id_empleado);

                $mensaje .= "<br>Pedido manual generado por ".$employee->firstname." ".$employee->lastname;

                //obtenemos nombre y email de empleado, si no es sergio ni Lorena, lo añadimos a $empleados para email
                if (($id_empleado != 22) && ($id_empleado != 4)) {
                    $empleados[] = array('nombre' => $employee->firstname, 'email' => $employee->email);            
                   
                } 

                $es_manual = "MANUAL (".$employee->firstname.") ";

            } else {
                $es_manual = "";                   
                
            }     

            $mensaje .= json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            $mensaje .= "<br><br>".$mensaje_resultado."<br><br>Respuesta API:<br>".$respuesta_api;

            if (self::$test) {
                $mensaje .= "<br><br>PEDIDO TEST";
                $error_archivo .= " - TEST - ";
            }

            //enviamos email de aviso con mensaje, dependiendo de si hay error
            $info = []; 
            $info['{archivo_expediciones}'] = $error_archivo.'Pedido '.$es_manual.$id_order.' a HEO '.date("Y-m-d H:i:s");
            $info['{errores}'] = $mensaje;
            $info['asunto'] = $error_archivo.'Pedido '.$es_manual.$id_order.' realizado a HEO '.date("Y-m-d H:i:s");

            // $empleados = array(
            //     array('nombre' => 'Sergio', 'email' => 'sergio@lafrikileria.com')
            // );

            self::enviaEmail($info, $empleados);

            if (!$resultado['success']) {
                //este return en caso de error solo interpreta el false para los pedidos manuales, los pedidos normales no leen el return
                return false;
            }
    
            return true;            

        } 
        return true;
        
    }

    //función para preparar un pedido a Heo manual, es decir, el pedido no existe en Prestashop y por tanto no hay cliente, ni datos de productos vendidos sin stock, etc. Se llama desde AdminPedidosManualesProveedor.php. No tenemos id_order que se generará en función de la tabla pedidos_heo, recibimos un array con datos de producto y unidades a solicitar, puede ser un pedido de un producto o varios. En este punto ya deben estar validados los productos.
    public static function gestionHeoManual($info_pedido) {
        //sacamos el id de empleado
        $id_empleado = Context::getContext()->employee->id; 

        //creamos un pedido de cliente ficticio ya que hay que enviarselo a Heo. El número lo sacamos de la tabla lafrips_pedidos_heo, recogiendo el mayor id_order de un pedido manual, y sumándole 1. Empezamos desde 1000000. Sacamos el id:
        $sql_id_order = 'SELECT MAX(id_order) FROM lafrips_pedidos_heo WHERE pedido_manual = 1';
        $id_order = (int)Db::getInstance()->getValue($sql_id_order);
        $id_order = $id_order ? $id_order + 1 : 1000000;        
        
        //recorremos $info_pedido como array, puede contener un producto o varios, y por cada uno meteremos los datos en lafrips_pedidos_heo
        foreach ($info_pedido AS $info_producto) {
            $referencia_heo = pSQL($info_producto['referencia']);
            $unidades = (int)$info_producto['unidades'];

            //buscar id_product e id_product_attribute, ean referencia prestashop. 
            //11/02/2025 cambiamos la forma de sacar el nombre porque para productos con más de un tipo de atributo saca una línea por tipo
            //IFNULL(CONCAT(pla.name, " : ", CONCAT(agl.name, " - ", atl.name)), pla.name) AS nombre,
            //IFNULL(CONCAT(pla.name, " : ", GROUP_CONCAT(DISTINCT agl.name, " - ", atl.name order by agl.name SEPARATOR ", ")), pla.name) as nombre
            $sql_producto_en_prestashop = 'SELECT psu.id_product AS id_product, psu.id_product_attribute AS id_product_attribute, IFNULL(pat.reference, pro.reference) AS referencia_prestashop,            
            IFNULL(
                CONCAT(
                    pla.name, " : ",
                    GROUP_CONCAT(
                        DISTINCT CONCAT(agl.name, " - ", atl.name)
                        ORDER BY agl.name SEPARATOR ", "
                    )
                ),
                pla.name
            ) AS nombre,
            IFNULL(pat.ean13, pro.ean13) AS ean13
            FROM lafrips_product_supplier psu
            JOIN lafrips_product pro ON psu.id_product = pro.id_product
            JOIN lafrips_product_lang pla ON psu.id_product = pla.id_product AND pla.id_lang = 1 
            LEFT JOIN lafrips_product_attribute pat ON pat.id_product = psu.id_product AND pat.id_product_attribute = psu.id_product_attribute
            LEFT JOIN lafrips_product_attribute_combination pac ON pac.id_product_attribute = pat.id_product_attribute
            LEFT JOIN lafrips_attribute atr ON atr.id_attribute = pac.id_attribute
            LEFT JOIN lafrips_attribute_lang atl ON atl.id_attribute = atr.id_attribute AND atl.id_lang = 1
            LEFT JOIN lafrips_attribute_group_lang agl ON agl.id_attribute_group = atr.id_attribute_group AND agl.id_lang = 1
            WHERE psu.id_supplier = 4
            AND psu.product_supplier_reference = "'.$referencia_heo.'"';

            $producto_en_prestashop = Db::getInstance()->executeS($sql_producto_en_prestashop);

            if (!$producto_en_prestashop || (count($producto_en_prestashop) > 1)) {
                //no se encuentra producto o la referencia corresponde a más de uno, en principio javascript no habría permitido llegar hasta aquí. devolvemos false
                return false;
            }

            $id_product = $producto_en_prestashop[0]['id_product'];
            $id_product_attribute = $producto_en_prestashop[0]['id_product_attribute'];
            $referencia_prestashop = pSQL($producto_en_prestashop[0]['referencia_prestashop']);
            $ean = pSQL($producto_en_prestashop[0]['ean13']);
            $product_name = pSQL($producto_en_prestashop[0]['nombre']);             

            //hacemos insert
            $sql_insert = "INSERT INTO lafrips_pedidos_heo
            (id_order, id_product, id_product_attribute, referencia_heo, unidades, product_name, ean, referencia_prestashop, pedido_manual, date_pedido_manual, id_empleado, date_add)
            VALUES
            (
                $id_order, $id_product, $id_product_attribute, '$referencia_heo', $unidades, '$product_name', '$ean', '$referencia_prestashop', 1, NOW(), $id_empleado, NOW()
            )";

            Db::getInstance()->execute($sql_insert);
            
        }

        //ahora tenemos en lafrips_pedidos_heo los productos con api_ok 0 y los ids correspondientes para generar el archivo.
        if (self::setHeoAPI($id_order) == false) {
            return false;
        } else {
            return true;
        }          
    }

    public static function callApiHeo($json_data) {
        //1 obtener credenciales
        if (self::getCredentials() == false) {
            return [
                "success" => false,
                "mensaje" => "Error obteniendo las credenciales de la API Heo",
                "respuesta_api" => null
            ];            
        }

        $url = rtrim(self::$api_credentials['url'], '/').'/v1/orders';
        $username = self::$api_credentials['user_name'];
        $password = self::$api_credentials['password'];

            // // Construir texto del log
            // $log_msg = "[HeoAPI DEBUG] ".date("Y-m-d H:i:s").PHP_EOL.
            //         "URL: $url".PHP_EOL.
            //         "Username: $username".PHP_EOL.
            //         "Password: $password".PHP_EOL.
            //         "-------------------------".PHP_EOL;

            // // Guardar en fichero (ejemplo en var/logs/heo_api.log dentro de PrestaShop)
            // $log_file = dirname(__FILE__).'/../heo_api.log';
            // file_put_contents($log_file, $log_msg, FILE_APPEND);

        //2 iniciar cURL
        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $json_data,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json'
        ),
        CURLOPT_USERPWD => $username.':'.$password, // Basic Auth, en lugar de hacer Authorization: Basic base64(user:pass)
        ));

        //3 ejecutar
        $response = curl_exec($curl);

        // 4. Comprobar errores cURL
        if ($response === false) {
            $error = curl_error($curl);
            curl_close($curl);

            return [
                "success" => false,
                "mensaje" => "Error en cURL: $error",
                "respuesta_api" => null
            ];
        }

        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        // 5. Decodificar respuesta JSON
        $decoded = json_decode($response, true);

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            return [
                "success" => false,
                "mensaje" => "Respuesta de API no es JSON válido (HTTP $httpcode)",
                "respuesta_api" => $response
            ];
        }

        // 6. Evaluar éxito en función del contenido devuelto
        if (isset($decoded['status']) && $decoded['status'] === 'SUCCESS') {
            return [
                "success" => true,
                "mensaje" => $decoded['message'] ?? "Pedido aceptado sin mensaje",
                "respuesta_api" => $response
            ];
        } elseif (isset($decoded['errorCode'])) {
            return [
                "success" => false,
                "mensaje" => $decoded['errorMessage'] ?? "Error API sin detalle",
                "respuesta_api" => $response
            ];
        } else {
            return [
                "success" => false,
                "mensaje" => "Respuesta API desconocida (HTTP $httpcode)",
                "respuesta_api" => $response
            ];
        }
    }

    public static function getCredentials() {
        if (self::$test) {
            $secrets_json = file_get_contents(dirname(__FILE__).'/../secrets/api_heo_test.json');
        } else {
            $secrets_json = file_get_contents(dirname(__FILE__).'/../secrets/api_heo.json');
        }        

        if ($secrets_json == false) {   
            return false;
        }

        //almacenamos decodificado como array asociativo (segundo parámetro true, si no sería un objeto)
        self::$api_credentials = json_decode($secrets_json, true); 

        return true;             
        
    }

    public static function enviaEmail($info, $empleados) {
        foreach ($empleados AS $empleado) {
            $info['{firstname}'] = $empleado['nombre'];

            @Mail::Send(
                1,
                'aviso_error_expedicion_cerda', //plantilla
                Mail::l($info['asunto'], 1),
                $info,
                $empleado['email'],
                $empleado['nombre'],
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
    
}
