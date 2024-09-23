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

//todas las funciones para trabajar con pedidos Dmi de manera estática

class Dmi
{

    //función que recibe los productos de un pedido para Dmi, los mete a la tabla de productos o hace update y vuelve a la función del switch para llamar a la función que finalmente hará la llamada a la aPI de Dmi
    //aquí traigo id_order porque lo quiero tener en la tabla por comodidad, pero me hubiera bastado con id_dropshipping_dmi
    //el último parámetro $check_eliminados (true por defecto) indica si hay que llamar a la función checkProductosDmiEliminados() para detectar si existen productos en la tabla que no vienen en info_productos. Esto se utiliza cuando llamamos a la función por haber añadido un producto desde el back office y solo queremos añadirlo, y no traemos más que ese producto en info_productos, de modo que no queremos marcar eliminados el resto
    public static function productosProveedorDmi($info_productos, $id_order, $id_dropshipping_dmi, $check_eliminados = true) {
        //por cada producto del proveedor dropshipping lo metemos en su tabla si no estaba, hacemos update o dejamos igual si estaba
        foreach ($info_productos AS $info) {
            if ($existe = Dmi::checkTablaDropshippingDmiProductos($id_dropshipping_dmi, $info)) {
                //la función nos devuelve un array, el primer campo contiene insert si no encuentra el producto y hay que insertarlo, ok si no hay que hacer nada y update si volvemos a meter todos los datos porque algo es diferente. En el segundo campo del array viene el id de DropshippingDmiProductos donde está el producto para hacer update
                if ($existe[0] == 'insert') {        
                    $sql_insert_lafrips_dropshipping_dmi_productos = 'INSERT INTO lafrips_dropshipping_dmi_productos
                    (id_dropshipping_dmi, id_order, id_order_detail, id_product, id_product_attribute, dmi_sku, product_quantity, product_name, product_reference, date_add) 
                    VALUES 
                    ('.$id_dropshipping_dmi.',            
                    '.$id_order.',
                    '.$info['id_order_detail'].',
                    '.$info['id_product'].',
                    '.$info['id_product_attribute'].',
                    "'.$info['product_supplier_reference'].'", 
                    '.$info['product_quantity'].',
                    "'.$info['product_name'].'", 
                    "'.$info['product_reference'].'",                     
                    NOW())';

                    Db::getInstance()->executeS($sql_insert_lafrips_dropshipping_dmi_productos);

                    continue;
        
                } elseif ($existe[0] == 'update') {
                    //Algún campo del pedido, que ya tenemos, es diferente o estaba eliminado. hacemos update sobre la línea con el id que hemos recibido en el segundo campo del array $existe.                  

                    $sql_update_lafrips_dropshipping_dmi_productos = 'UPDATE lafrips_dropshipping_dmi_productos
                    SET
                    id_order_detail = '.$info['id_order_detail'].',
                    dmi_sku = "'.$info['product_supplier_reference'].'", 
                    product_quantity = '.$info['product_quantity'].', 
                    product_reference = "'.$info['product_reference'].'",                     
                    eliminado = 0,
                    date_upd = NOW()
                    WHERE id_dropshipping_dmi_productos = '.(int)$existe[1];
        
                    Db::getInstance()->executeS($sql_update_lafrips_dropshipping_dmi_productos); 
        
                    continue;
        
                } elseif ($existe[0] == 'ok') {
                    continue;
        
                } else {
                    $error = 'Error chequeando tabla Dropshipping Dmi para producto anadido';
    
                    Productosvendidossinstock::insertDropshippingLog($error, $id_order, 160, null, null, null);
                }  
            } else {
                //hay algún error
                return null;
            }    
        }

        //tenemos que comprobar si en la tabla existen productos asignados al pedido que ya no están en el pedido por haber sido eliminados, y se marcan como eliminados. 
        if ($check_eliminados) {
            Dmi::checkProductosDmiEliminados($id_order, $info_productos);
        }        

        return true;
        
    }

    //función que comprueba si un pedido ya existe para lafrips_dropshipping_dmi o lo inserta    
    public static function checkTablaDropshippingDmi($id_order, $id_dropshipping) {
        $sql_existe_pedido_dmi = 'SELECT id_dropshipping_dmi
        FROM lafrips_dropshipping_dmi
        WHERE id_order = '.$id_order.'
        AND id_dropshipping = '.$id_dropshipping;

        if ($id_dropshipping_dmi = Db::getInstance()->getValue($sql_existe_pedido_dmi)) {
            return $id_dropshipping_dmi;
        } else {
            //no existe, insertamos
            $sql_insert_pedido_dmi = 'INSERT INTO lafrips_dropshipping_dmi
            (id_dropshipping, id_order, date_add) 
            VALUES 
            ('.$id_dropshipping.',
            '.$id_order.',
            NOW())';
            Db::getInstance()->executeS($sql_insert_pedido_dmi);

            return Db::getInstance()->Insert_ID();
        } 
    }

    //función que comprueba si un producto / pedido ya existe para lafrips_dropshipping_dmi_productos    
    public static function checkTablaDropshippingDmiProductos($id_dropshipping_dmi, $info) {
        $sql_busca_producto = 'SELECT id_dropshipping_dmi_productos 
        FROM lafrips_dropshipping_dmi_productos 
        WHERE id_dropshipping_dmi = '.$id_dropshipping_dmi.'
        AND id_product = '.$info['id_product'].'
        AND id_product_attribute = '.$info['id_product_attribute'];

        $busca_producto = Db::getInstance()->ExecuteS($sql_busca_producto);

        $return_action = 'insert';
        $return_id_dropshipping_dmi_productos = 0;

        //si no encuentra el producto, devolvemos la orden insert para insertarlo. Si encuentra pero marcado eliminado, o algún dato es diferente, devolvemos update con el id_dropshipping_dmi_productos, y 'ok' y el id si coinciden
        if (count($busca_producto) > 0) {
            foreach ($busca_producto AS $producto) {    //solo debería aparecer una vez, el foreach no dar más de una vuelta             
                //encontrado producto, comprobamos si los datos coinciden, si si, devolvemos 'ok' y el id, si no, devolvemos 'update' e id . Si está eliminado la select no dará resultado y se hará update     

                $sql_mismos_datos = 'SELECT id_dropshipping_dmi_productos 
                FROM lafrips_dropshipping_dmi_productos 
                WHERE id_dropshipping_dmi_productos = '.$producto['id_dropshipping_dmi_productos'].'
                AND id_order_detail = '.$info['id_order_detail'].'
                AND id_product = '.$info['id_product'].'
                AND id_product_attribute = '.$info['id_product_attribute'].'
                AND dmi_sku = "'.$info['product_supplier_reference'].'"
                AND product_quantity = '.$info['product_quantity'].',                    
                AND product_reference = "'.$info['product_reference'].'"                
                AND eliminado = 0';
            
                $mismos_datos = Db::getInstance()->executeS($sql_mismos_datos);

                //la consulta solo devolverá resultado si coinciden todos los AND de la select
                if ($mismos_datos[0]['id_dropshipping_dmi_productos'] && ($mismos_datos[0]['id_dropshipping_dmi_productos'] == $producto['id_dropshipping_dmi_productos'])) {
                    //los datos coinciden
                    $return_action = 'ok';
                    $return_id_dropshipping_dmi_productos = $producto['id_dropshipping_dmi_productos'];

                } else {
                    //los datos no coinciden
                    $return_action = 'update';
                    $return_id_dropshipping_dmi_productos = $producto['id_dropshipping_dmi_productos'];
                }                
            }
        } 

        return array($return_action, $return_id_dropshipping_dmi_productos);     
    }

    //función si, teniendo los productos actuales de dmi en un pedido, hay alguno más asignado a este en la tabla, para marcarlo como eliminado
    public static function checkProductosDmiEliminados($id_order, $info_productos) {
        //de info productos sacamos por cada uno su id_product e id_product_attribute y los añadimos a un update que marcará eliminado a los productos que  no coincidan con esos ids pero estén para el id_order
        //esta consulta se forma así:
        // SELECT id_dropshipping_dmi_productos
        //     FROM frikileria_bd_test.lafrips_dropshipping_dmi_productos
        //     WHERE NOT (id_product = 23908 AND id_product_attribute = 40083)
        //         AND NOT (id_product = 8683 AND id_product_attribute = 0)
        //     AND id_order = 320365
        // AND eliminado = 0

        $sql_productos_eliminados = 'UPDATE lafrips_dropshipping_dmi_productos
        SET
        eliminado = 1,        
        date_eliminado = NOW(),
        date_upd = NOW()
        WHERE NOT ';
        $contador = 0;
        foreach ($info_productos AS $producto) {
            if (!$contador) {
                $sql_productos_eliminados .= '(id_product = '.$producto['id_product'].' AND id_product_attribute = '.$producto['id_product_attribute'].') ';
            } else {
                $sql_productos_eliminados .= 'AND NOT (id_product = '.$producto['id_product'].' AND id_product_attribute = '.$producto['id_product_attribute'].') ';
            }
            $contador++;
        }
        $sql_productos_eliminados .= 'AND eliminado = 0 AND id_order = '.$id_order;

        Db::getInstance()->Execute($sql_productos_eliminados);

        return;
    }

    //función que llama a la API dmi para hacer el pedido. Si la API falla o devuelve error, marcaremos el lafrips_dropshipping el pedido como error_api y date_error_api. ¿?¿?Un proceso cron comprobará los pedidos cada 5 minutos, los que tengan error_api y date_error_api sea de hace menos de 3 horas, los volverá a intentar pedir. Si en la última petición tampoco se consigue, enviará email de aviso a nosotros para comprobar pedido manualmente
    public static function apiDmiSolicitud($id_lafrips_dropshipping, $id_employee) {        
        //preparamos los parámetros para la llamada, info del pedido y de los productos. Tenemos el id de la tabla dropshipping del pedido
        //el email usamos tienda@lafrikileria.com en lugar del del cliente para todos

        //sacamos la info del pedido
        $sql_info_order = 'SELECT dro.id_order AS id_order, dra.firstname AS firstname, dra.lastname AS lastname, dra.phone AS phone, dra.company AS company, dra.provincia AS provincia,
        dra.address1 AS address1, dra.postcode AS postcode, dra.city AS city, dra.country AS country, dra.envio_almacen AS envio_almacen,
        ddm.id_dropshipping_dmi AS id_dropshipping_dmi
        FROM lafrips_dropshipping dro
        JOIN lafrips_dropshipping_address dra ON dra.id_dropshipping_address = dro.id_dropshipping_address
        JOIN lafrips_dropshipping_dmi ddm ON ddm.id_dropshipping = dro.id_dropshipping
        WHERE dro.id_dropshipping = '.$id_lafrips_dropshipping;

        $info_order = Db::getInstance()->executeS($sql_info_order); 

        if ($info_order[0]['envio_almacen']) {     
            $dropshipping = 0;       
            $codped = $info_order[0]['id_order'];
            $nombre = "EUROPE CANARIAS SPAIN D. C. EXC. PARA FANS SL"; 
            $calle = "C/ LAS BALSAS 20 (NAVE GLS) - PI Cantabria";  
            $cp = "26009";
            $ciudad = "Logroño";  
            $provincia = "La Rioja";           
            $tlf = "941123004";            
                    
        } else {
            $dropshipping = 1;  
            $codped = $info_order[0]['id_order'];
            $nombre = $info_order[0]['firstname'].' '.$info_order[0]['lastname']; 
            $calle = $info_order[0]['address1'];  
            $cp = $info_order[0]['postcode'];
            $ciudad = $info_order[0]['city'];  
            $provincia = $info_order[0]['provincia'];           
            $tlf = $info_order[0]['phone'];
            if ($info_order[0]['company'] != '') {
                $calle = $calle.' - '.$info_order[0]['company']; 
            }
            
        }        

        //sacamos la info de los productos del pedido
        $sql_info_productos = 'SELECT id_product, dmi_sku, product_quantity
        FROM lafrips_dropshipping_dmi_productos         
        WHERE eliminado = 0
        AND id_dropshipping_dmi = '.$info_order[0]['id_dropshipping_dmi'];

        $info_productos = Db::getInstance()->executeS($sql_info_productos); 
        
        //la función soap nuevo_pedido_str nos permite crear las líneas de pedido como una simple cadena
        $lineas_pedido = '';
        $total_pedido = 0;

        foreach ($info_productos AS $info_producto) {           
            //aunque luego lo recalculan y no tiene importancia, el webservice nos pide el price el producto y el total del pedido, lo calculamos de todos modos
            //generamos el string de las líneas de pedido, formato codart|cantidad_str|precio_str#codart|cantidad_str|precio_str etc            
            // $lineas_pedido = "PE6032031|1|1.20#MM5225686|2|2.38";
            // $lineas_pedido = "GA9038245|1|13.05";
            //los productos se separan con #
            $product = new Product((int)$info_producto['id_product']);
            $price = $product->wholesale_price;
            $total_pedido += $price*(int)$info_producto['product_quantity'];

            //si ya hemos metido algún producto, separamos con #
            if ($lineas_pedido !== '') {
                $lineas_pedido .= '#';
            }

            $lineas_pedido .= $info_producto['dmi_sku'].'|'.$info_producto['product_quantity'].'|'.$price;
            
        }


        $servicio = "http://www.dmi.es/sw/pedidos.asmx"; //url del servicio
        $url = $servicio."?WSDL"; //Es necesario poner esto para corregir un pequeño bug de PHP, es para que “entienda” que somos el cliente y la respuesta sea correcta

        $error_api = 0;
        $mensaje = '';
        $json_params = '';
        $json_response = '';
        try {
            //Conectamos con DMI pasando el parámetro trace=1 para poder obtener los response headers y poder sacar el http code
            //ponemos exceptions true para pòder recoger excepciones, por dfefeceto creo que soap lo tiene en false
            $client = @new SoapClient(
                $url, 
                array(
                    'trace' => 1,
                    'exceptions' => true,
                )
            ); 
        } catch (Exception $e) {            
            $error_api = 1;

            $error = 'Error haciendo conexión SOAP a DMI - Excepción: '.$e->getMessage();

            Productosvendidossinstock::insertDropshippingLog(pSQL($error), $codped, 160, null, null, $id_lafrips_dropshipping);            

            $mensaje .= '<br> - Excepción capturada conectando a API: '.$e->getMessage();
        }
        //hay conexión, hacemos llamada a la función deseada, NUEVO_PEDIDO_STR() con los parámetros requeridos
        try {         
            //01/06/2023 modificamos para meter API keys en secrets/api_dmi.json
            $secrets_json = file_get_contents(dirname(__FILE__).'/../secrets/api_dmi.json');
            
            $secrets = json_decode($secrets_json, true);

            //sacamos las credenciales de la API
            $usuario = $secrets['usuario'];   
            $password = $secrets['password'];    

            $params = array(); //Parámetros de la llamada 
            
            $params['USUARIO'] = $usuario;
            $params['PASSWORD'] = $password;

            $params['CODPED'] = $codped; //nuestro número de pedido
            $params['CATALOGO'] = "DMI"; //campo "indiferente"
            $params['CALLE'] = $calle;
            $params['NUMERO'] = "";
            $params['PISO'] = "";
            $params['CIUDAD'] = $ciudad;  
            $params['CP'] = $cp;
            $params['provincia'] = $provincia;
            $params['tlf'] = $tlf;
            $params['nombre'] = $nombre;
            $params['Usuario_Registrado'] = "1"; //siempre 1
            $params['LineasDelPedido'] = $lineas_pedido;
            //22/05/2024 modificamos validacion del pedido a true y no "true" y recogidaendmi a flase y no "False"
            $params['Validacion_del_Pedido'] = true; //siempre true
            $params['EmailDelCliente'] = "tienda@lafrikileria.com";  //nuestro email
            $params['ComentarioDelPedido'] = "";
            $params['RecogidaEnDMI'] = false;
            $params['TotalPedido'] = $total_pedido; //lo recalculan así que es indiferente, hacemos calculo con nuestro coste
            $params['tipo_transaccion'] = "";  //permite bloquear un pedido, poniendo "bloquear", si por ejemplo tiene pago transferencia, hasta estar seguro del pago, lo usamos para pruebas (¿?no funciona¿?)

            $json_params = json_encode($params);

            $mensaje .= '<br>Petición:<br>'.$json_params.'<br><br>';

            $result = $client->NUEVO_PEDIDO_STR($params);

        } catch (Exception $e) { 
            $error_api = 1;

            $error = 'Error en llamada a función del servicio SOAP de DMI - Excepción: '.$e;

            Productosvendidossinstock::insertDropshippingLog($error, $codped, 160, null, null, $id_lafrips_dropshipping);            

            $mensaje .= '<br> - Excepción capturada en llamada a función del servicio SOAP de DMI: '.$e;

            
            //obtenemos el http code (200, 404, etc) de la cadena devuelta al pedir last response headers, hay que hacer un preg_match para limpiar la cadena
            $responseHeaders = $client->__getLastResponseHeaders(); //aquí ya hay response headers con la respuesta        

            preg_match("/HTTP\/\d\.\d\s*\K[\d]+/", $responseHeaders, $matches); //sacamos el httpcode del string de respuesta

            $http_code = $matches[0];

            if (!$http_code) {
                $http_code = 0;
            }

            $ws_params_json = pSQL($json_params);
            $id_dropshipping_dmi = $info_order[0]['id_dropshipping_dmi'];
            //para insertar el json string hay que hacerlo así
            $sql_update_no_response = "UPDATE lafrips_dropshipping_dmi
            SET
            ws_params_json = '$ws_params_json', 
            ws_response_json = 'no response', 
            response_http_code = $http_code,            
            nombre_direccion = '$nombre',                                       
            date_upd = NOW()
            WHERE id_dropshipping_dmi = $id_dropshipping_dmi";    

            Db::getInstance()->executeS($sql_update_no_response); 
            
        }

        $json_response = json_encode($result);

        //obtenemos el http code (200, 404, etc) de la cadena devuelta al pedir last response headers, hay que hacer un preg_match para limpiar la cadena
        $responseHeaders = $client->__getLastResponseHeaders(); //aquí ya hay response headers con la respuesta        

        preg_match("/HTTP\/\d\.\d\s*\K[\d]+/", $responseHeaders, $matches); //sacamos el httpcode del string de respuesta

        $http_code = $matches[0];

        $mensaje .= '<br>Http Response Code= '.$http_code.'<br><br>';        

        if ($http_code > 299 || $http_code < 200) {
            $mensaje .= '<br>ERROR EN PETICIÓN<br><br>';
            $error_api = 1;
        }
      
        $mensaje .= '<br>Respuesta:<br>'.$json_response.'<br><br>';

        $ws_respuesta = $result->NUEVO_PEDIDO_STRResult; //si todo es correcto será la referencia del pedido para DMI, si no, mensaje error¿?
        
        //preparamos los parámetros y respuesta de la api para insertar en lafrips_dropshipping_dmi
        $ws_params_json = pSQL($json_params);
        $ws_response_json = pSQL($json_response);

        //insertamos resultados sobre pedido global, el id no se puede meter directo del array usando las dobles comillas para la sql
        $id_dropshipping_dmi = $info_order[0]['id_dropshipping_dmi'];
        //para insertar el json string hay que hacerlo así
        $sql_update_response = "UPDATE lafrips_dropshipping_dmi
        SET
        ws_params_json = '$ws_params_json', 
        ws_response_json = '$ws_response_json', 
        response_http_code = $http_code,
        ws_respuesta = '$ws_respuesta',
        nombre_direccion = '$nombre',                                       
        date_upd = NOW()
        WHERE id_dropshipping_dmi = $id_dropshipping_dmi";

        // $mensaje .= '<br>sql_update_response:<br>'.$sql_update_response.'<br><br>';

        Db::getInstance()->executeS($sql_update_response);             

        //hacemos update a lafrips_dropshipping con procesado, error o id_employee manual
        if ($id_employee) {
            $sql_manual = ' id_employee_manual = '.$id_employee.',
            date_manual = NOW(),';
        } else {
            $sql_manual = '';
        }

        $revisar = 0;

        //si el pedido está procesado, nos aseguramos de quitar marcadores de error si los hubiera
        if (!$error_api) {
            $sql_procesado = ' procesado = 1, error = 0, error_api = 0, ';
        } else {
            $sql_procesado = ' procesado = 0, ';
            $revisar = 1;
        }

        $sql_update_dropshipping = 'UPDATE lafrips_dropshipping
        SET
        '.$sql_procesado.'           
        '.$sql_manual.'
        date_upd = NOW()
        WHERE id_dropshipping = '.$id_lafrips_dropshipping;

        Db::getInstance()->executeS($sql_update_dropshipping);      
        
        //si la llamada a la api ha funcionado pero no se ha aceptado el pedido (falta de stock u otro error) debemos avisar a algún usuario para que lo revise cuanto antes, ya que no se volverá a hacer solicitud
        if ($revisar) {
            // $cuentas = array('sergio@lafrikileria.com','beatriz@lafrikileria.com','alberto@lafrikileria.com');
            $cuentas = array('sergio@lafrikileria.com');

            $aviso = 'El pedido '.$codped.' que contiene productos de DMI ha sido solicitado correctamente pero la API lo ha rechazado, quedando sin aceptar por su sistema y en espera de revisarlo en Prestashop - '.date("Y-m-d_His");

            Productosvendidossinstock::enviaEmail($cuentas, $aviso, 'DMI', $codped);
        }
        
        //si tenemos un error api (hubo excepción o no ha habido response) comprobamos si ya lo tenía o lo marcamos en lafrips_dropshipping
        if ($error_api) {
            //hacemos update a lafrips_dropshipping con error, error_api, o id_employee manual
            if ($id_employee) {
                $sql_manual = ' id_employee_manual = '.$id_employee.',
                date_manual = NOW(),';
            } else {
                $sql_manual = '';
            }

            //comprobamos si ya tiene error_api en el update, para no pisar date_error_api, que es lo que establece si volver a llamar a api. Si ya tenía error_api no lo volvemos a marcar y respetamos date_error_api inicial
            $sql_update_dropshipping = 'UPDATE lafrips_dropshipping
            SET
            procesado = 0,
            error = 1,
            error_api = 1,
            date_error_api = NOW(),          
            '.$sql_manual.'
            date_upd = NOW()
            WHERE error_api = 0
            AND id_dropshipping = '.$id_lafrips_dropshipping;

            Db::getInstance()->executeS($sql_update_dropshipping); 

            $cuentas = array('sergio@lafrikileria.com');

            Productosvendidossinstock::enviaEmail($cuentas, $mensaje, 'DMI', $codped);

            return false;
        }

        $cuentas = array('sergio@lafrikileria.com');

        Productosvendidossinstock::enviaEmail($cuentas, $mensaje, 'DMI', $codped);

        return true;

    }

    //función que llama a la API DMI para comprobar el estado del pedido. A 24/06/2022 no sé qué posibles respuestas hay. Ejemplo de facturado:
    /*
        CODIGO_PEDIDO: WEB_2022062218313461
        ESTADO: FACTURADO
        NUM_FACTURA: VFR22027388
        FECHA_FACTURA: 23/06/2022
        LINK_EXPEDICION: http://www.asmred.com/extranet/public/ExpedicionASM.aspx?codigo=61651102674072&cpDst=24001
        TRANSPORTISTA: GLS SPAIN
        EXPEDICION_DMI: 22100398220623
    */
    public static function apiDmiStatus($id_dropshipping) {           
        //sacamos el id de pedido de Dmi del pedido, que estaría almacenado si el pedido es correcto, en ws_respuesta
        $sql_id_dmi = 'SELECT ws_respuesta
        FROM lafrips_dropshipping_dmi
        WHERE id_dropshipping = '.$id_dropshipping;
    
        if (!$id_dmi = Db::getInstance()->getValue($sql_id_dmi)) {
            return false;
        }   

        //es una solicitud Soap al servicio http://promociones.dmi.es/ws/pedidos.asmx, con el id de pedido y el id de cliente como parámetros. Preparamos la solicitud

        $servicio="http://promociones.dmi.es/ws/pedidos.asmx"; //url del servicio
        $url=$servicio."?WSDL"; //Es necesario poner esto para corregir un pequeño bug de PHP, es para que “entienda” que somos el cliente y la respuesta sea correcta

        try {
            //Conectamos con DMI pasando el parámetro trace=1 para poder obtener los response headers y poder sacar el http code
            //ponemos exceptions true para pòder recoger excepciones, por dfefeceto creo que soap lo tiene en false
            $client = @new SoapClient(
                $url, 
                array(
                    'trace' => 1,
                    'exceptions' => true,
                )
            ); 
        } catch (Exception $e) {            
            $error = 'Error haciendo petición estados a WS DMI, id dropshipping '.$id_dropshipping.' - Excepción: '.$e->getMessage();

            Productosvendidossinstock::insertDropshippingLog(pSQL($error), null, 160);

            return false;
        }

        $codigo_pedido = '';
        $estado = '';
        $num_factura = '';
        $fecha_factura = '';
        $url_tracking = '';
        $transportista = '';
        $expedicion_dmi = '';
        //establecida conexión, pedimos los datos
        try {           
            //01/06/2023 modificamos para meter API keys en secrets/api_dmi.json
            $secrets_json = file_get_contents(dirname(__FILE__).'/../secrets/api_dmi.json');
            
            $secrets = json_decode($secrets_json, true);

            //sacamos las credenciales de la API
            $codigo_cliente = $secrets['codigo_cliente'];            
        
            $params=array(); //Parámetros de la llamada    
            $params['CODIGO_PEDIDO'] = $id_dmi;
            $params['CODIGO_CLIENTE'] = $codigo_cliente;     
            
            //hacemos petición
            $result = $client->Estado($params); 

            //obtenemos el http code (200, 404, etc) de la cadena devuelta al pedir last response headers, hay que hacer un preg_match para limpiar la cadena
            $responseHeaders = $client->__getLastResponseHeaders(); //aquí ya hay response headers con la respuesta        

            preg_match("/HTTP\/\d\.\d\s*\K[\d]+/", $responseHeaders, $matches); //sacamos el httpcode del string de respuesta

            $http_code = $matches[0];            

            if ($http_code > 299 || $http_code < 200) {
                $error = 'Error en petición estados a WS Dmi, id dropshipping '.$id_dropshipping.' - Http Code = '.$http_code;

                Productosvendidossinstock::insertDropshippingLog($error, null, 160);

                //marcamos error en lafrips_dropshipping_dmi                
                $sql_update_error = 'UPDATE lafrips_dropshipping_dmi
                SET
                error = 1,                                  
                date_upd = NOW()
                WHERE id_dropshipping = '.$id_dropshipping;

                Db::getInstance()->executeS($sql_update_error);                 

                return false;
            }

            //extraemos los datos de la respuesta
            $datos = $result->ESTADOResult->any;

            //cargamos como xml la cadena
            $xml = simplexml_load_string($datos) or die("Error: Cannot create object");

            //asignamos los valores recibidos a las variables para guardarlas. No sabemos si existirán los parámetros, depende del estado del pedido. Estos son los devueltos si el pedido ha sido expedido
            $codigo_pedido = $xml->PEDIDO->CODIGO_PEDIDO;
            $estado = $xml->PEDIDO->ESTADO;
            $num_factura = $xml->PEDIDO->NUM_FACTURA;
            $fecha_factura = $xml->PEDIDO->FECHA_FACTURA;
            $url_tracking = $xml->PEDIDO->LINK_EXPEDICION;
            $transportista = $xml->PEDIDO->TRANSPORTISTA;
            $expedicion_dmi = $xml->PEDIDO->EXPEDICION_DMI;   

            if ($fecha_factura) {
                $fecha_factura = str_replace("/", "-", $fecha_factura);
            }

            $sql_enviado = '';
            //no sabemos exactamente los valores posibles de estado, pero posiblemente estén No encontrado, Bloqueado Cliente, Recibido, En preparación, Facturado. Pero no sé si vienen con separación, mayúsculas, etc. Si hay tracking lo considero enviado, si no no viene tracking
            if ($url_tracking && $expedicion_dmi) {
                $sql_enviado = ' estado = "'.$estado.'",                
                num_factura = "'.$num_factura.'",
                fecha_factura = "'.$fecha_factura.'",
                url_tracking = "'.$url_tracking.'",
                transportista = "'.$transportista.'",
                expedicion_dmi = "'.$expedicion_dmi.'",
                ';

                //si está procesado nos aseguramos de limpiar marcadores de error si los hay
                $sql_update_finalizado = 'UPDATE lafrips_dropshipping
                SET
                error = 0,
                error_api = 0,
                procesado = 1,
                finalizado = 1,                                                    
                date_upd = NOW()
                WHERE id_dropshipping = '.$id_dropshipping;

                Db::getInstance()->executeS($sql_update_finalizado);

            } elseif ($codigo_pedido && $estado != 'FACTURADO') {
                $sql_enviado = ' estado = "'.$estado.'",                
                num_factura = "'.$num_factura.'",
                fecha_factura = "'.$fecha_factura.'",
                url_tracking = "'.$url_tracking.'",
                transportista = "'.$transportista.'",
                expedicion_dmi = "'.$expedicion_dmi.'",
                ';

                $sql_update_pendiente = 'UPDATE lafrips_dropshipping
                SET
                error = 0,
                error_api = 0,
                procesado = 1,
                finalizado = 0,                                                    
                date_upd = NOW()
                WHERE id_dropshipping = '.$id_dropshipping;

                Db::getInstance()->executeS($sql_update_pendiente);

            } elseif (!$codigo_pedido) {
                //no encontrado pedido, marcamos error     
                $sql_update_error = 'UPDATE lafrips_dropshipping
                SET                    
                error = 1,                                                                    
                date_upd = NOW()
                WHERE id_dropshipping = '.$id_dropshipping;

                Db::getInstance()->executeS($sql_update_error);

                $error = 'Pedido Dmi id dropshipping '.$id_dropshipping.' no encontrado al consultar estado';

                Productosvendidossinstock::insertDropshippingLog($error, null, 160, null, null, $id_dropshipping);

            }     

            $sql_update_response = 'UPDATE lafrips_dropshipping_dmi
            SET            
            '.$sql_enviado.'                                  
            date_upd = NOW()
            WHERE id_dropshipping = '.$id_dropshipping;

            Db::getInstance()->executeS($sql_update_response);            
        
            
        } catch (Exception $e) { 
            $error = 'Error haciendo petición estados a WS DMI, id dropshipping '.$id_dropshipping.' - Excepción: '.$e;

            Productosvendidossinstock::insertDropshippingLog($error, null, 160);

            return false;
        }

        return true;

    }
    
}
