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

//todas las funciones para trabajar con pedidos Globomatik de manera estática

class Globomatik
{

    //función que recibe los productos de un pedido para Globomatik, los mete a la tabla de productos o hace update y vuelve a la función del switch para llamar a la función que finalmente hará la llamada a la aPI de Globomatik
    //aquí traigo id_order porque lo quiero tener en la tabla por comodidad, pero me hubiera bastado con id_dropshipping_globomatik
    //el último parámetro $check_eliminados (true por defecto) indica si hay que llamar a la función checkProductosGlobomatikEliminados() para detectar si existen productos en la tabla que no vienen en info_productos. Esto se utiliza cuando llamamos a la función por haber añadido un producto desde el back office y solo queremos añadirlo, y no traemos más que ese producto en info_productos, de modo que no queremos marcar eliminados el resto
    public static function productosProveedorGlobomatik($info_productos, $id_order, $id_dropshipping_globomatik, $check_eliminados = true) {
        //por cada producto del proveedor dropshipping lo metemos en su tabla si no estaba, hacemos update o dejamos igual si estaba
        foreach ($info_productos AS $info) {
            if ($existe = Globomatik::checkTablaDropshippingGlobomatikProductos($id_dropshipping_globomatik, $info)) {
                //la función nos devuelve un array, el primer campo contiene insert si no encuentra el producto y hay que insertarlo, ok si no hay que hacer nada y update si volvemos a meter todos los datos porque algo es diferente. En el segundo campo del array viene el id de DropshippingGlobomatikProductos donde está el producto para hacer update
                if ($existe[0] == 'insert') {        
                    $sql_insert_lafrips_dropshipping_globomatik_productos = 'INSERT INTO lafrips_dropshipping_globomatik_productos
                    (id_dropshipping_globomatik, id_order, id_order_detail, id_product, id_product_attribute, globomatik_sku, product_quantity, product_name, product_reference, date_add) 
                    VALUES 
                    ('.$id_dropshipping_globomatik.',            
                    '.$id_order.',
                    '.$info['id_order_detail'].',
                    '.$info['id_product'].',
                    '.$info['id_product_attribute'].',
                    "'.$info['product_supplier_reference'].'", 
                    '.$info['product_quantity'].',
                    "'.$info['product_name'].'", 
                    "'.$info['product_reference'].'",                     
                    NOW())';

                    Db::getInstance()->executeS($sql_insert_lafrips_dropshipping_globomatik_productos);

                    continue;
        
                } elseif ($existe[0] == 'update') {
                    //Algún campo del pedido, que ya tenemos, es diferente o estaba eliminado. hacemos update sobre la línea con el id que hemos recibido en el segundo campo del array $existe.                  

                    $sql_update_lafrips_dropshipping_globomatik_productos = 'UPDATE lafrips_dropshipping_globomatik_productos
                    SET
                    id_order_detail = '.$info['id_order_detail'].',
                    globomatik_sku = "'.$info['product_supplier_reference'].'", 
                    product_quantity = '.$info['product_quantity'].', 
                    product_reference = "'.$info['product_reference'].'",                     
                    eliminado = 0,
                    date_upd = NOW()
                    WHERE id_dropshipping_globomatik_productos = '.(int)$existe[1];
        
                    Db::getInstance()->executeS($sql_update_lafrips_dropshipping_globomatik_productos); 
        
                    continue;
        
                } elseif ($existe[0] == 'ok') {
                    continue;
        
                } else {
                    $error = 'Error chequeando tabla Dropshipping Globomatik para producto anadido';
    
                    Productosvendidossinstock::insertDropshippingLog($error, $id_order, 156, null, null, null);
                }  
            } else {
                //hay algún error
                return null;
            }    
        }

        //tenemos que comprobar si en la tabla existen productos asignados al pedido que ya no están en el pedido por haber sido eliminados, y se marcan como eliminados. 
        if ($check_eliminados) {
            Globomatik::checkProductosGlobomatikEliminados($id_order, $info_productos);
        }        

        return true;
        
    }

    //función que comprueba si un pedido ya existe para lafrips_dropshipping_globomatik o lo inserta    
    public static function checkTablaDropshippingGlobomatik($id_order, $id_dropshipping) {
        $sql_existe_pedido_globomatik = 'SELECT id_dropshipping_globomatik
        FROM lafrips_dropshipping_globomatik 
        WHERE id_order = '.$id_order.'
        AND id_dropshipping = '.$id_dropshipping;

        if ($id_dropshipping_globomatik = Db::getInstance()->getValue($sql_existe_pedido_globomatik)) {
            return $id_dropshipping_globomatik;
        } else {
            //no existe, insertamos
            $sql_insert_pedido_globomatik = 'INSERT INTO lafrips_dropshipping_globomatik
            (id_dropshipping, id_order, date_add) 
            VALUES 
            ('.$id_dropshipping.',
            '.$id_order.',
            NOW())';
            Db::getInstance()->executeS($sql_insert_pedido_globomatik);

            return Db::getInstance()->Insert_ID();
        } 
    }

    //función que comprueba si un producto / pedido ya existe para lafrips_dropshipping_globomatik_productos    
    public static function checkTablaDropshippingGlobomatikProductos($id_dropshipping_globomatik, $info) {
        $sql_busca_producto = 'SELECT id_dropshipping_globomatik_productos 
        FROM lafrips_dropshipping_globomatik_productos 
        WHERE id_dropshipping_globomatik = '.$id_dropshipping_globomatik.'
        AND id_product = '.$info['id_product'].'
        AND id_product_attribute = '.$info['id_product_attribute'];

        $busca_producto = Db::getInstance()->ExecuteS($sql_busca_producto);

        $return_action = 'insert';
        $return_id_dropshipping_globomatik_productos = 0;

        //si no encuentra el producto, devolvemos la orden insert para insertarlo. Si encuentra pero marcado eliminado, o algún dato es diferente, devolvemos update con el id_dropshipping_globomatik_productos, y 'ok' y el id si coinciden
        if (count($busca_producto) > 0) {
            foreach ($busca_producto AS $producto) {    //solo debería aparecer una vez, el foreach no dar más de una vuelta             
                //encontrado producto, comprobamos si los datos coinciden, si si, devolvemos 'ok' y el id, si no, devolvemos 'update' e id . Si está eliminado la select no dará resultado y se hará update     

                $sql_mismos_datos = 'SELECT id_dropshipping_globomatik_productos 
                FROM lafrips_dropshipping_globomatik_productos 
                WHERE id_dropshipping_globomatik_productos = '.$producto['id_dropshipping_globomatik_productos'].'
                AND id_order_detail = '.$info['id_order_detail'].'
                AND id_product = '.$info['id_product'].'
                AND id_product_attribute = '.$info['id_product_attribute'].'
                AND globomatik_sku = "'.$info['product_supplier_reference'].'"
                AND product_quantity = '.$info['product_quantity'].',                    
                AND product_reference = "'.$info['product_reference'].'"                
                AND eliminado = 0';
            
                $mismos_datos = Db::getInstance()->executeS($sql_mismos_datos);

                //la consulta solo devolverá resultado si coinciden todos los AND de la select
                if ($mismos_datos[0]['id_dropshipping_globomatik_productos'] && ($mismos_datos[0]['id_dropshipping_globomatik_productos'] == $producto['id_dropshipping_globomatik_productos'])) {
                    //los datos coinciden
                    $return_action = 'ok';
                    $return_id_dropshipping_globomatik_productos = $producto['id_dropshipping_globomatik_productos'];

                } else {
                    //los datos no coinciden
                    $return_action = 'update';
                    $return_id_dropshipping_globomatik_productos = $producto['id_dropshipping_globomatik_productos'];
                }                
            }
        } 

        return array($return_action, $return_id_dropshipping_globomatik_productos);     
    }

    //función si, teniendo los productos actuales de globomatik en un pedido, hay alguno más asigando a este en la tabla, para marcarlo como eliminado
    public static function checkProductosGlobomatikEliminados($id_order, $info_productos) {
        //de info productos sacamos por cada uno su id_product e id_product_attribute y los añadimos a un update que marcará eliminado a los productos que  no coincidan con esos ids pero estén para el id_order
        //esta consulta se forma así:
        // SELECT id_dropshipping_globomatik_productos
        //     FROM frikileria_bd_test.lafrips_dropshipping_globomatik_productos
        //     WHERE NOT (id_product = 23908 AND id_product_attribute = 40083)
        //         AND NOT (id_product = 8683 AND id_product_attribute = 0)
        //     AND id_order = 320365
        // AND eliminado = 0

        $sql_productos_eliminados = 'UPDATE lafrips_dropshipping_globomatik_productos
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

    //función que llama a la API globomatik para hacer el pedido. Si la API falla o devuelve error, marcaremos el lafrips_dropshipping el pedido como error_api y date_error_api. Un proceso cron comprobará los pedidos cada 5 minutos, los que tengan error_api y date_error_api sea de hace menos de 3 horas, los volverá a intentar pedir. Si en la última petición tampoco se consigue, enviará email de aviso a nosotros para comprobar pedido manualmente
    public static function apiGlobomatikSolicitud($id_lafrips_dropshipping, $id_employee) {        
        //preparamos los parámetros para la llamada, info del pedido y de los productos. Tenemos el id de la tabla dropshipping del pedido
        //el email usamos tienda@lafrikileria.com en lugar del del cliente para todos, y enviamos el id_customer en su lugar

        //sacamos la info del pedido
        $sql_info_order = 'SELECT dro.id_order AS id_order, dra.firstname AS firstname, dra.lastname AS lastname, dra.phone AS phone, dra.company AS company, dra.provincia AS provincia,
        dra.address1 AS address1, dra.postcode AS postcode, dra.city AS city, dra.country AS country, dra.envio_almacen AS envio_almacen,
        drg.id_dropshipping_globomatik AS id_dropshipping_globomatik
        FROM lafrips_dropshipping dro
        JOIN lafrips_dropshipping_address dra ON dra.id_dropshipping_address = dro.id_dropshipping_address
        JOIN lafrips_dropshipping_globomatik drg ON drg.id_dropshipping = dro.id_dropshipping
        WHERE dro.id_dropshipping = '.$id_lafrips_dropshipping;

        $info_order = Db::getInstance()->executeS($sql_info_order); 

        if ($info_order[0]['envio_almacen']) {     
            $dropshipping = 0;       
            $codPedidoCliente = $info_order[0]['id_order'];
            $envioANombre = "EUROPE CANARIAS SPAIN D. C. EXC. PARA FANS SL"; 
            $envioADireccion = "C/ LAS BALSAS 20 (NAVE GLS) - PI Cantabria";  
            $envioACp = "26009";
            $envioAPoblacion = "Logroño";  
            $envioAProvincia = "La Rioja";           
            $envioATelefono = "941123004";
            $envioAAtencion = "La Frikilería"; 
            $tipoAlbaran = 1; //que nos llegue albarán a nosotros
                    
        } else {
            $dropshipping = 1;  
            $codPedidoCliente = $info_order[0]['id_order'];
            $envioANombre = $info_order[0]['firstname'].' '.$info_order[0]['lastname']; 
            $envioADireccion = $info_order[0]['address1'];  
            $envioACp = $info_order[0]['postcode'];
            $envioAPoblacion = $info_order[0]['city'];  
            $envioAProvincia = $info_order[0]['provincia'];           
            $envioATelefono = $info_order[0]['phone'];
            //ctype_space(string) devuelve true si string tiene algo y ese algo es/son todo espacios vacíos, y false si no hay nada o lo que hay no son todo espacios vacíos
            if (($info_order[0]['company'] != '') && !ctype_space($info_order[0]['company'])) {
                $envioAAtencion = trim($info_order[0]['company']); 
            } else {
                $envioAAtencion = $envioANombre; 
            }
            $tipoAlbaran = 0; //A CLIENTE NO ENVIAMOS
        }        

        //sacamos la info de los productos del pedido
        $sql_info_productos = 'SELECT id_product, globomatik_sku, product_quantity
        FROM lafrips_dropshipping_globomatik_productos         
        WHERE eliminado = 0
        AND id_dropshipping_globomatik = '.$info_order[0]['id_dropshipping_globomatik'];

        $info_productos = Db::getInstance()->executeS($sql_info_productos); 
        
        //array que contiene los productos y es un parámetro que debe ir en formato json
        $productos = array();
        $total_weight = 0;

        foreach ($info_productos AS $info_producto) {
            //La API de globomatik admite un parámetro "comentario" no required sobre el producto, y otro si required "montaje" que en principio para nosotros siempre va a ser 0

            //si el peso del paquete pasa de 50kg hay que pedir transporte pallet o rechaza el pedido, así que tenemos que calcular el peso
            $product = new Product((int)$info_producto['id_product']);
            $weight = $product->weight;
            $total_weight += $weight*(int)$info_producto['product_quantity'];

            $producto = array(
                "sku" => $info_producto['globomatik_sku'],
                "qty" => $info_producto['product_quantity'],
                "montaje" => 0                
            );

            $productos[] = $producto;
        }

        $products_json = json_encode($productos);

        /* 381 creo que es GLS 14 y 58 GLS 24
            Códigos transportistas pallets: 2000 transaher es más barato?
                1060:DACHSER 1090:PALLEX 888:DHL 537:CABRERO 2070:DACHSER 
                EUROPA 902: Agencia propia
            Códigos transportistas paquetería:
                902: Agencia propia 354:SEUR 58:ASM 741:TNT EXPORT 3000:MRW 
                381:ASM 218:UPS STANDARD 214:UPS EXPRESS 999:DHL 1061: DACHSER 
                47:ASM CANARIAS 370:SEUR AEREO
        */

        if ($total_weight >= 50) {
            $codTransporte = "2000";  //Transaher ¿?
        } else {
            $codTransporte = "58";   //GLS 24¿?
        }

        // $tipoAlbaran = 0; // int , 0 – sin albarán, 1 – albarán valorado de Globomatik, 2 – albarán sin valorar de Globomatik, 3 – albarán valorado de cliente, 4 – sin valorar de cliente
        $tipoEmbalaje = 0;  //int, 0 neutro, 1 globomatik, 2 personalizado

        //existe otro parámetro no obligatorio codPedidoClienteFinal que se puede utilizr si necesitamos otro id auxiliar para el pedido (pej. si queremos dar unos ids especificos al cliente que compra mucho..)
        $parametros = array(
            "products" => $products_json,
            "codPedidoCliente" => $codPedidoCliente, // string id_order prestashop 
            "dropshipping" => $dropshipping, // int 0 no -> entrega en almacén¿? 1 si -> entrega cliente¿?
            "envioANombre" => $envioANombre,  //string , no admite cosas como AND, ELSE, SELECT, INSERT, DELETE, REPLACE, DROP, WHERE ¿?¿?
            "envioADireccion" => $envioADireccion,
            "envioACp" => $envioACp,
            "envioAPoblacion" => $envioAPoblacion,
            "envioAProvincia" => $envioAProvincia,
            "envioATelefono" => $envioATelefono,
            "envioAAtencion" => $envioAAtencion, //a quién entregar, si no hay nada reptimos nombre cliente
            "codTransporte" => $codTransporte,  // string, hay una lista ¿?¿?
            "tipoAlbaran" => $tipoAlbaran, // int , hay varios diferentes, con o sin precio etc, es lo que va en el paquete, puede no llevar
            "tipoEmbalaje" => $tipoEmbalaje,
            "observaciones" => "Pedido ".$codPedidoCliente." La Frikilería" 
        );

        $body = http_build_query($parametros);        
    
        $endpoint = 'https://webservice.globomatik.com/api/v2/orders';

        //01/06/2023 modificamos para meter API keys en secrets/api_globomatik.json
        $secrets_json = file_get_contents(dirname(__FILE__).'/../secrets/api_globomatik.json');
        
        $secrets = json_decode($secrets_json, true);

        //sacamos las credenciales de la API
        $key = $secrets['key'];
        $test_key = $secrets['test_key'];

        $curl = curl_init();
    
        curl_setopt_array($curl, array(
        CURLOPT_URL => $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => array(
            'x-globomatik-key: '.$key,
            'Content-Type: application/x-www-form-urlencoded'
        ),
        ));

        $mensaje = '<br>Petición:<br>'.json_encode($parametros).'<br><br>';

        $error_api = 0;

        try {
            //ejecutamos cURL
            $response = curl_exec($curl);            

            //si ha ocurrido algún error, lo capturamos
            if(curl_errno($curl)){
                throw new Exception(curl_error($curl));
            }
        }
        catch (Exception $e) {     
            $error_api = 1;

            $exception = $e->getMessage();
            $file = $e->getFile();
            $line = $e->getLine(); 
            $code = $e->getCode();

            $error = 'Error haciendo petición a API Globomatik - Excepción:<br>'.$exception.'<br>Exception thrown in '.$file.' on line '.$line.': [Code '.$code.']';

            // $error = 'Error haciendo petición a API Globomatik - Excepción: '.$e;

            Productosvendidossinstock::insertDropshippingLog(pSQL($error), $codPedidoCliente, 156, null, null, $id_lafrips_dropshipping);            

            $mensaje .= '<br> - Excepción capturada llamando a API: '.$exception;
            
        }

        if ($response) {
            //obtenemos el http code
            $http_code = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE); 

            curl_close($curl);

            $mensaje .= '<br>Http Response Code= '.$http_code.'<br><br>';

            if ($http_code > 299 || $http_code < 200) {
                $mensaje .= '<br>ERROR EN PETICIÓN<nr><br><br>';
                $error_api = 1;
            }
          
            $mensaje .= '<br>Respuesta:<br>'.$response.'<br><br>';

            //pasamos el JSON de respuesta a un objeto PHP.
            $response_decode = json_decode($response); 
            
            //preparamos los parámetros de la api para insertar en lafrips_dropshipping_globomatik
            $api_body = pSQL($body);
            $response = pSQL($response);

            if (!$error_api) {
                $response_decode_order = $response_decode->order;
                $response_decode_shipping = $response_decode->shipping;
            } else {
                $response_decode_order = "Error api call";
                $response_decode_shipping = 0;
            }      

            //insertamos resultados sobre pedido global, el id no se puede meter directo del array usando las dobles comillas para la sql
            $id_dropshipping_globomatik = $info_order[0]['id_dropshipping_globomatik'];
            //para insertar el json string hay que hacerlo así
            $sql_update_response = "UPDATE lafrips_dropshipping_globomatik
            SET
            api_call_http_build_query = '$api_body', 
            api_call_response = '$response', 
            api_call_http_code = $http_code,
            globomatik_order_reference = '$response_decode_order',
            tipo_albaran = $tipoAlbaran,
            tipo_embalaje = $tipoEmbalaje,
            cod_transporte = '$codTransporte',
            shipping_cost = $response_decode_shipping,                                
            date_upd = NOW()
            WHERE id_dropshipping_globomatik = $id_dropshipping_globomatik";

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

                $aviso = 'El pedido '.$codPedidoCliente.' que contiene productos de Globomatik ha sido solicitado correctamente pero la API lo ha rechazado, quedando sin aceptar por su sistema y en espera de revisarlo en Prestashop - '.date("Y-m-d_His");

                Productosvendidossinstock::enviaEmail($cuentas, $aviso, 'Globomatik', $codPedidoCliente);
            }

        } else {
            //obtenemos el http code
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE); 

            //cerramos aún sin response
            curl_close($curl);

            $mensaje .= '<br>Http Response Code= '.$http_code.'<br><br>';

            $error_api = 1;

            $mensaje .= '<br>No obtenida respuesta<br><br>';

            $api_body = pSQL($body);
           
            $sql_update_no_response = "UPDATE lafrips_dropshipping_globomatik
            SET
            api_call_http_build_query = '$api_body', 
            api_call_response = 'no response',
            api_call_http_code = $http_code,               
            error = 1,
            date_upd = NOW()
            WHERE id_dropshipping = $id_lafrips_dropshipping";

            Db::getInstance()->executeS($sql_update_no_response); 
            
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

            Productosvendidossinstock::enviaEmail($cuentas, $mensaje, 'Globomatik', $codPedidoCliente);

            return false;
        }

        $cuentas = array('sergio@lafrikileria.com');

        Productosvendidossinstock::enviaEmail($cuentas, $mensaje, 'Globomatik', $codPedidoCliente);

        return true;

    }

    //función que llama a la API Globomatik para comprobar el estado del pedido, solo uno por llamada. Hará la llamada actualizará el pedido. Solo devuelve un id de estado, un txt, el id para Globomatik y el shipping cost, y si se ha enviado, el tracking y url de seguimiento
    /*
        status = -1 -> statusTxt = No existe el pedido
        status = 0 -> statusTxt = Pedido en revision
        status = 1 -> statusTxt = Pedido en preparacion almacen
        status = 2 -> statusTxt = Pedido finalizado almacen
        status = 3 -> statusTxt = Pedido facturado -> Tracking = 22024314 -> ESTE ESTADO PUEDE NO LLEVAR TRACKING

        Pedido enviado
        [status] => 3
        [statusTxt] => Pedido facturado
        [Tracking] => 22024314
        [URL] => http://www.asmred.com/extranet/public/ExpedicionASM.aspx?codigo=22024314&cpDst=27191
        [order] => PV22024314
        [shipping] => 0.00
    */
    public static function apiGlobomatikStatus($id_dropshipping) {           
        //sacamos el id de pedido de Globomatik del pedido, que para esta llamada es el que hemos usado como id order de cliente, es decir, el id_order de prestashop.
        $sql_id_globomatik = 'SELECT id_order
        FROM lafrips_dropshipping_globomatik
        WHERE id_dropshipping = '.$id_dropshipping;
    
        if (!$id_globomatik = Db::getInstance()->getValue($sql_id_globomatik)) {
            return false;
        }   

        //la llamada a API es un GET a https://webservice.globomatik.com/api/v2/orders/IDPEDIDO/status

        $endpoint = 'https://webservice.globomatik.com/api/v2/orders/';

        //preparamos url
        $url = $endpoint.$id_globomatik.'/status';

        //01/06/2023 modificamos para meter API keys en secrets/api_globomatik.json
        $secrets_json = file_get_contents(dirname(__FILE__).'/../secrets/api_globomatik.json');
        
        $secrets = json_decode($secrets_json, true);

        //sacamos las credenciales de la API
        $key = $secrets['key'];
        $test_key = $secrets['test_key'];

        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => true, 
        CURLOPT_HTTPHEADER => array(    
            'x-globomatik-key: '.$key
        ),
        ));

        try {
            //ejecutamos cURL
            $response = curl_exec($curl);

            //si ha ocurrido algún error, lo capturamos
            if(curl_errno($curl)){
                throw new Exception(curl_error($curl));
            }
        }
        catch (Exception $e) {     
            $exception = $e->getMessage();
            $file = $e->getFile();
            $line = $e->getLine(); 
            $code = $e->getCode();

            $error = 'Error haciendo petición estados a API Globomatik, id dropshipping '.$id_dropshipping.' - Excepción:<br>'.$exception.'<br>Exception thrown in '.$file.' on line '.$line.': [Code '.$code.']';

            // $error = 'Error haciendo petición estados a API Globomatik, id dropshipping '.$id_dropshipping.' - Excepción: '.$e;

            Productosvendidossinstock::insertDropshippingLog(pSQL($error), null, 156);

            return false;
            
        }

        if ($response) {
            //obtenemos el http code
            $http_code = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE); 

            curl_close($curl);

            //pasamos el JSON de respuesta a un objeto PHP. 
            $response_decode = json_decode($response); 
            $response_status = $response_decode->status;
            $response_status_txt = $response_decode->statusTxt;

            if ($http_code > 299 || $http_code < 200) {
                $error = 'Error en petición estados a API Globomatik, id dropshipping '.$id_dropshipping.' - Http Code = '.$http_code.' - Response status = '.$response_status_txt;

                Productosvendidossinstock::insertDropshippingLog($error, null, 156);

                //marcamos error en lafrips_dropshipping_globomatik                
                $sql_update_error = 'UPDATE lafrips_dropshipping_globomatik
                SET
                error = 1,                                  
                date_upd = NOW()
                WHERE id_dropshipping = '.$id_dropshipping;

                Db::getInstance()->executeS($sql_update_error);                 

                return false;
            }

            //siel código http es 200 ok seguimos
            //comprobamos si existe tracking y url en respuesta
            $response_tracking = '';
            $response_tracking_url = '';
            if ($response_decode->Tracking) {
                $response_tracking = $response_decode->Tracking;
            }
            if ($response_decode->URL) {
                $response_tracking_url = $response_decode->URL;
            }

            $sql_enviado = '';

            //no sabemos exactamente los valores posibles de los códigos status, pero sé que 0 es "recibido", -1 es pedido no encontrado, y parece que 3 es enviado, pero si hay url_tracking lo considero enviado, en algún caso no ha venido tracking
            if ($response_tracking_url) {
                $sql_enviado = ' tracking = "'.$response_tracking.'",                
                url_tracking = "'.$response_tracking_url.'", ';

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
            } elseif ($response_status == -1) {
                //no encontrado pedido, marcamos error     
                $sql_update_cancelado = 'UPDATE lafrips_dropshipping
                SET                    
                error = 1,                                                                    
                date_upd = NOW()
                WHERE id_dropshipping = '.$id_dropshipping;

                Db::getInstance()->executeS($sql_update_cancelado);

                $error = 'Pedido Globomatik id dropshipping '.$id_dropshipping.' no encontrado al consultar estado';

                Productosvendidossinstock::insertDropshippingLog($error, null, 156, null, null, $id_dropshipping);

            }     

            $sql_update_response = 'UPDATE lafrips_dropshipping_globomatik
            SET
            status_id = '.$response_status.',
            status_txt = "'.$response_status_txt.'",
            '.$sql_enviado.'                                  
            date_upd = NOW()
            WHERE id_dropshipping = '.$id_dropshipping;

            Db::getInstance()->executeS($sql_update_response);            

        } else {
            //no hay respuesta pero cerramos igualmente
            curl_close($curl);
        }           

        return true;

    }
    
}
