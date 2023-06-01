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

//todas las funciones para trabajar con pedidos Disfrazzes de manera estática

class Disfrazzes
{

    //función que recibe los productos de un pedido para Disfrazzes, los mete a la tabla de productos o hace update y vuelve a la función del switch para llamar a la función que finalmente hará la llamada a la aPI de Disfrazzes
    //aquí traigo id_order porque lo quiero tener en la tabla por comodidad, pero me hubiera bastado con id_dropshipping_disfrazzes
    //el último parámetro $check_eliminados (true por defecto) indica si hay que llamar a la función checkProductosDisfrazzesEliminados() para detectar si existen productos en la tabla que no vienen en info_productos. Esto se utiliza cuando llamamos a la función por haber añadido un producto desde el back office y solo queremos añadirlo, y no traemos más que ese producto en info_productos, de modo que no queremos marcar eliminados el resto
    public static function productosProveedorDisfrazzes($info_productos, $id_order, $id_dropshipping_disfrazzes, $check_eliminados = true) {
        //por cada producto del proveedor dropshipping lo metemos en su tabla si no estaba, hacemos update o dejamos igual si estaba
        foreach ($info_productos AS $info) {
            if ($existe = Disfrazzes::checkTablaDropshippingDisfrazzesProductos($id_dropshipping_disfrazzes, $info)) {
                //la función nos devuelve un array, el primer campo contiene insert si no encuentra el producto y hay que insertarlo, ok si no hay que hacer nada y update si volvemos a meter todos los datos porque algo es diferente. En el segundo campo del array viene el id de DropshippingDisfrazzesProductos donde está el producto para hacer update
                if ($existe[0] == 'insert') {
                    //sacamos product_id y variant_id para disfrazzes
                    $product_supplier_reference = $info['product_supplier_reference'];
                    $referencia_disfrazzes = explode("_", $product_supplier_reference);
                    $product_id = (int)$referencia_disfrazzes[0];
                    $variant_id = (int)$referencia_disfrazzes[1];

                    $sql_insert_lafrips_dropshipping_disfrazzes_productos = 'INSERT INTO lafrips_dropshipping_disfrazzes_productos
                    (id_dropshipping_disfrazzes, id_order, id_order_detail, id_product, id_product_attribute, product_supplier_reference, product_quantity, product_name, product_reference, product_id, variant_id, date_add) 
                    VALUES 
                    ('.$id_dropshipping_disfrazzes.',            
                    '.$id_order.',
                    '.$info['id_order_detail'].',
                    '.$info['id_product'].',
                    '.$info['id_product_attribute'].',
                    "'.$product_supplier_reference.'", 
                    '.$info['product_quantity'].',
                    "'.$info['product_name'].'", 
                    "'.$info['product_reference'].'", 
                    '.$product_id.',
                    '.$variant_id.',
                    NOW())';

                    Db::getInstance()->executeS($sql_insert_lafrips_dropshipping_disfrazzes_productos);

                    continue;
        
                } elseif ($existe[0] == 'update') {
                    //Algún campo del pedido, que ya tenemos, es diferente o estaba eliminado. hacemos update sobre la línea con el id que hemos recibido en el segundo campo del array $existe. 
                    //sacamos product_id y variant_id para disfrazzes
                    $product_supplier_reference = $info['product_supplier_reference'];
                    $referencia_disfrazzes = explode("_", $product_supplier_reference);
                    $product_id = (int)$referencia_disfrazzes[0];
                    $variant_id = (int)$referencia_disfrazzes[1];

                    $sql_update_lafrips_dropshipping_disfrazzes_productos = 'UPDATE lafrips_dropshipping_disfrazzes_productos
                    SET
                    id_order_detail = '.$info['id_order_detail'].',
                    product_supplier_reference = "'.$product_supplier_reference.'", 
                    product_quantity = '.$info['product_quantity'].', 
                    product_reference = "'.$info['product_reference'].'", 
                    product_id = '.$product_id.',
                    variant_id = '.$variant_id.',
                    eliminado = 0,
                    date_upd = NOW()
                    WHERE id_dropshipping_disfrazzes_productos = '.(int)$existe[1];
        
                    Db::getInstance()->executeS($sql_update_lafrips_dropshipping_disfrazzes_productos); 
        
                    continue;
        
                } elseif ($existe[0] == 'ok') {
                    continue;
        
                } else {
                    $error = 'Error chequeando tabla Dropshipping Disfrazzes para producto anadido';
    
                    Productosvendidossinstock::insertDropshippingLog($error, $id_pedido, 161, null, null, null);
                }  
            } else {
                //hay algún error
                return null;
            }    
        }

        //tenemos que comprobar si en la tabla existen productos asignados al pedido que ya no están en el pedido por haber sido eliminados, y se marcan como eliminados. 
        if ($check_eliminados) {
            Disfrazzes::checkProductosDisfrazzesEliminados($id_order, $info_productos);
        }        

        return true;
        
    }

    //función que comprueba si un pedido ya existe para lafrips_dropshipping_disfrazzes o lo inserta    
    public static function checkTablaDropshippingDisfrazzes($id_order, $id_dropshipping) {
        $sql_existe_pedido_disfrazzes = 'SELECT id_dropshipping_disfrazzes
        FROM lafrips_dropshipping_disfrazzes 
        WHERE id_order = '.$id_order.'
        AND id_dropshipping = '.$id_dropshipping;

        if ($id_dropshipping_disfrazzes = Db::getInstance()->getValue($sql_existe_pedido_disfrazzes)) {
            return $id_dropshipping_disfrazzes;
        } else {
            //no existe, insertamos
            $sql_insert_pedido_disfrazzes = 'INSERT INTO lafrips_dropshipping_disfrazzes
            (id_dropshipping, id_order, date_add) 
            VALUES 
            ('.$id_dropshipping.',
            '.$id_order.',
            NOW())';
            Db::getInstance()->executeS($sql_insert_pedido_disfrazzes);

            return Db::getInstance()->Insert_ID();
        } 
    }

    //función que comprueba si un producto / pedido ya existe para lafrips_dropshipping_disfrazzes_productos    
    public static function checkTablaDropshippingDisfrazzesProductos($id_dropshipping_disfrazzes, $info) {
        $sql_busca_producto = 'SELECT id_dropshipping_disfrazzes_productos 
        FROM lafrips_dropshipping_disfrazzes_productos 
        WHERE id_dropshipping_disfrazzes = '.$id_dropshipping_disfrazzes.'
        AND id_product = '.$info['id_product'].'
        AND id_product_attribute = '.$info['id_product_attribute'];

        $busca_producto = Db::getInstance()->ExecuteS($sql_busca_producto);

        $return_action = 'insert';
        $return_id_dropshipping_disfrazzes_productos = 0;

        //si no encuentra el producto, devolvemos la orden insert para insertarlo. Si encuentra pero marcado eliminado, o algún dato es diferente, devolvemos update con el id_dropshipping_disfrazzes_productos, y 'ok' y el id si coinciden
        if (count($busca_producto) > 0) {
            foreach ($busca_producto AS $producto) {    //solo debería aparecer una vez, el foreach no dar más de una vuelta             
                //encontrado producto, comprobamos si los datos coinciden, si si, devolvemos 'ok' y el id, si no, devolvemos 'update' e id . Si está eliminado la select no dará resultado y se hará update                   
                $referencia_disfrazzes = explode("_", $info['product_supplier_reference']);
                $product_id = $referencia_disfrazzes[0];
                $variant_id = $referencia_disfrazzes[1];

                $sql_mismos_datos = 'SELECT id_dropshipping_disfrazzes_productos 
                FROM lafrips_dropshipping_disfrazzes_productos 
                WHERE id_dropshipping_disfrazzes_productos = '.$producto['id_dropshipping_disfrazzes_productos'].'
                AND id_order_detail = '.$info['id_order_detail'].'
                AND id_product = '.$info['id_product'].'
                AND id_product_attribute = '.$info['id_product_attribute'].'
                AND product_supplier_reference = "'.$info['product_supplier_reference'].'"
                AND product_quantity = '.$info['product_quantity'].',                    
                AND product_reference = "'.$info['product_reference'].'"
                AND product_id = '.$product_id.'
                AND variant_id = '.$variant_id.'
                AND eliminado = 0';
            
                $mismos_datos = Db::getInstance()->executeS($sql_mismos_datos);

                //la consulta solo devolverá resultado si coinciden todos los AND de la select
                if ($mismos_datos[0]['id_dropshipping_disfrazzes_productos'] && ($mismos_datos[0]['id_dropshipping_disfrazzes_productos'] == $producto['id_dropshipping_disfrazzes_productos'])) {
                    //los datos coinciden
                    $return_action = 'ok';
                    $return_id_dropshipping_disfrazzes_productos = $producto['id_dropshipping_disfrazzes_productos'];

                } else {
                    //los datos no coinciden
                    $return_action = 'update';
                    $return_id_dropshipping_disfrazzes_productos = $producto['id_dropshipping_disfrazzes_productos'];
                }                
            }
        } 

        return array($return_action, $return_id_dropshipping_disfrazzes_productos);     
    }

    //función si, teniendo los productos actuales de disfrazzes en un pedido, hay alguno más asigando a este en la tabla, para marcarlo como eliminado
    public static function checkProductosDisfrazzesEliminados($id_order, $info_productos) {
        //de info productos sacamos por cada uno su id_product e id_product_attribute y los añadimos a un update que marcará eliminado a los productos que  no coincidan con esos ids pero estén para el id_order
        //esta consulta se forma así:
        // SELECT id_dropshipping_disfrazzes_productos
        //     FROM frikileria_bd_test.lafrips_dropshipping_disfrazzes_productos
        //     WHERE NOT (id_product = 23908 AND id_product_attribute = 40083)
        //         AND NOT (id_product = 8683 AND id_product_attribute = 0)
        //     AND id_order = 320365
        // AND eliminado = 0

        $sql_productos_eliminados = 'UPDATE lafrips_dropshipping_disfrazzes_productos
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

    //función que llama a la API disfrazzes para hacer el pedido. Si la API falla o devuelve error, marcaremos el lafrips_dropshipping el pedido como error_api y date_error_api. Un proceso cron comprobará los pedidos cada 5 minutos, los que tengan error_api y date_error_api sea de hace menos de 3 horas, los volverá a intentar pedir. Si en la última petición tampoco se consigue, enviará email de aviso a nosotros para comprobar pedido manualmente
    public static function apiDisfrazzesSolicitud($id_lafrips_dropshipping, $id_employee) {
        //prueba, enviar email
        //preparamos los parámetros para la llamada, info del pedido y de los productos. Tenemos el id de la tabla dropshipping del pedido
        //el email usamos tienda@lafrikileria.com en lugar del del cliente para todos, y enviamos el id_customer en su lugar

        //sacamos la info del pedido
        $sql_info_order = 'SELECT dro.date_add AS fecha, dro.id_customer AS id_customer, dro.id_order AS id_order, dra.firstname AS firstname, dra.lastname AS lastname, dra.phone AS phone, dra.company AS company,
        dra.address1 AS address1, dra.postcode AS postcode, dra.city AS city, dra.country AS country, dra.envio_almacen AS envio_almacen,
        drp.id_dropshipping_disfrazzes AS id_dropshipping_disfrazzes
        FROM lafrips_dropshipping dro
        JOIN lafrips_dropshipping_address dra ON dra.id_dropshipping_address = dro.id_dropshipping_address
        JOIN lafrips_dropshipping_disfrazzes drp ON drp.id_dropshipping = dro.id_dropshipping
        WHERE dro.id_dropshipping = '.$id_lafrips_dropshipping;

        $info_order = Db::getInstance()->executeS($sql_info_order); 

        if ($info_order[0]['envio_almacen']) {
            $date_add = $info_order[0]['fecha'];
            $id_customer = $info_order[0]['id_customer'];
            $id_order = $info_order[0]['id_order'];
            $firstname = 'La Frikilería';
            $lastname = 'La Frikilería';
            $phone = '941123004';
            $company = 'La Frikilería';                        
            $address1 = 'Calle Las Balsas 20 - PI Cantabria - Junto GLS';
            $postcode = '26009';
            $city = 'Logroño';            
        } else {
            $date_add = $info_order[0]['fecha'];
            $id_customer = $info_order[0]['id_customer'];
            $id_order = $info_order[0]['id_order'];
            $firstname = $info_order[0]['firstname'];
            $lastname = $info_order[0]['lastname'];
            $phone = $info_order[0]['phone'];
            $company = $info_order[0]['company'];
            $address1 = $info_order[0]['address1'];
            $postcode = $info_order[0]['postcode'];
            $city = $info_order[0]['city'];
        }        

        //sacamos la info de los productos del pedido
        $sql_info_productos = 'SELECT id_order_detail, id_product, product_id, variant_id, product_quantity
        FROM lafrips_dropshipping_disfrazzes_productos         
        WHERE eliminado = 0
        AND id_dropshipping_disfrazzes = '.$info_order[0]['id_dropshipping_disfrazzes'];

        $info_productos = Db::getInstance()->executeS($sql_info_productos); 
        
        $lines = array();

        foreach ($info_productos AS $info_producto) {
            $price = Product::getPriceStatic($info_producto['id_product'], false, 0, 2); //precio, sin tax, sin atributo (impacto), 2 decimales

            $producto = array(
                "marketplace_row_id" => $info_producto['id_order_detail'],
                "product_id" => $info_producto['product_id'],
                "variant_id" => $info_producto['variant_id'],
                "quantity" => $info_producto['product_quantity'],
                "expected_price" => $price
            );

            $lines[] = $producto;
        }


        $country_ISO2 = 'ES';

        $parameters = array(
            "date" => $date_add,
            "marketplace_order_id" => $id_order,
            "label_content" => "",
            "address" => array(
                "email" => $id_customer, 
                "name" => $firstname,
                "surname" => $lastname,
                "phone" => $phone,
                "company" => $company,
                "address" => $address1,
                "floor" => "",
                "zip_code" => $postcode,
                "city" => $city,
                "country_ISO2" => $country_ISO2
            ),
            "lines" => $lines
        );

        $array_json_parameters = json_encode($parameters);

        // $data = array(
        //   "login" => "XXXXXXX",
        //   "pass" => "XXXXXX",
        //   "parameters" => $array_json_parameters
        // );

        //01/06/2023 modificamos para meter API keys en secrets/api_disfrazzes.json
        $secrets_json = file_get_contents(dirname(__FILE__).'/../secrets/api_disfrazzes.json');
        
        $secrets = json_decode($secrets_json, true);

        //sacamos las credenciales de la API
        $login = $secrets['login'];
        $pass = $secrets['pass'];

        $data = array(
            "login" => $login,
            "pass" => $pass,
            "parameters" => $array_json_parameters
        );

        $url = http_build_query($data);
    
        $endpoint_test = 'https://zzdevapi.disfrazzes.com/method/register_order';
        $endpoint_produccion = 'https://api.disfrazzes.com/method/register_order';

        $curl = curl_init();
    
        curl_setopt_array($curl, array(
        CURLOPT_URL => $endpoint_produccion,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $url,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/x-www-form-urlencoded'
        ),
        ));

        $mensaje = '<br>Petición:<br>'.$array_json_parameters.'<br><br>';

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

            $error = 'Error haciendo petición a API Disfrazzes - Excepción:<br>'.$exception.'<br>Exception thrown in '.$file.' on line '.$line.': [Code '.$code.']';

            // $error = 'Error haciendo petición a API Disfrazzes - Excepción: '.$e;

            Productosvendidossinstock::insertDropshippingLog(pSQL($error), $id_order, 161, null, null, $id_lafrips_dropshipping);            

            $mensaje .= '<br> - Excepción capturada llamando a API: '.$exception;
            
        }

        if ($response) {
            //obtenemos el http code
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE); 

            curl_close($curl);

            $mensaje .= '<br>Http Response Code= '.$http_code.'<br><br>';
          
            $mensaje .= '<br>Respuesta:<br>'.$response.'<br><br>';

            //pasamos el JSON de respuesta a un objeto PHP. Introduciremos el resultado en cada línea de producto de lafrips_dropshipping_disfrazzes            
            $response_decode = json_decode($response); 
            
            //preparamos los json para el insert (esto lo podré  quitar cuando esté todo bien probado)
            $array_json_parameters = pSQL($array_json_parameters);
            $response = pSQL($response);

            $response_decode_result = (int)$response_decode->result;
            $response_decode_data_delivery_date = $response_decode->data->delivery_date;
            $response_decode_msg = $response_decode->msg;
            $response_decode_data_disfrazzes_id = (int)$response_decode->data->disfrazzes_id;
            $response_decode_data_disfrazzes_reference = $response_decode->data->disfrazzes_reference;

            //insertamos resultados sobre pedido global, el id no se puede meter directo del array usando las dobles comillas para la sql
            $id_dropshipping_disfrazzes = $info_order[0]['id_dropshipping_disfrazzes'];
            //para insertar el json string hay que hacerlo así
            $sql_update_response = "UPDATE lafrips_dropshipping_disfrazzes
            SET
            api_call_parameters = '$array_json_parameters', 
            api_call_response = '$response', 
            api_call_http_code = $http_code,
            response_result = $response_decode_result,
            response_delivery_date = '$response_decode_data_delivery_date',
            response_msg = '$response_decode_msg',
            disfrazzes_id = $response_decode_data_disfrazzes_id,
            disfrazzes_reference = '$response_decode_data_disfrazzes_reference',
            status_name = 'Solicitado ok',                     
            date_upd = NOW()
            WHERE id_dropshipping_disfrazzes = $id_dropshipping_disfrazzes";

            // $mensaje .= '<br>sql_update_response:<br>'.$sql_update_response.'<br><br>';

            Db::getInstance()->executeS($sql_update_response); 

            //insertamos resultados sobre cada producto
            foreach ($response_decode->data->lines_result AS $line_result) {                    
                $line_result_result = (int)$line_result->result;
                $line_result_msg = $line_result->msg;
                $line_result_quantity_accepted = (int)$line_result->quantity_accepted;
                $line_result_row_id = (int)$line_result->row_id;
                $line_result_marketplace_row_id = (int)$line_result->marketplace_row_id;

                $sql_update_response_productos = "UPDATE lafrips_dropshipping_disfrazzes_productos
                SET                
                variant_result = $line_result_result,
                variant_msg = '$line_result_msg',
                variant_quantity_accepted = $line_result_quantity_accepted,
                variant_row_id = $line_result_row_id,          
                date_upd = NOW()
                WHERE id_order_detail = $line_result_marketplace_row_id
                AND id_dropshipping_disfrazzes = $id_dropshipping_disfrazzes";

                // $mensaje .= '<br>sql_update_response_productos:<br>'.$sql_update_response_productos.'<br><br>';
                Db::getInstance()->executeS($sql_update_response_productos); 

            }

            //hacemos update a lafrips_dropshipping con procesado, error o id_employee manual
            if ($id_employee) {
                $sql_manual = ' id_employee_manual = '.$id_employee.',
                date_manual = NOW(),';
            } else {
                $sql_manual = '';
            }

            $revisar = 0;

            //si el pedido está procesado, nos aseguramos de quitar marcadores de error si los hubiera
            if ($response_decode_result == 1 || $response_decode_result == 2) {
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
                $cuentas = array('sergio@lafrikileria.com','beatriz@lafrikileria.com','alberto@lafrikileria.com');

                $aviso = 'El pedido '.$id_order.' que contiene productos de Disfrazzes ha sido solicitado correctamente pero la API lo ha rechazado, quedando sin aceptar por su sistema y en espera de revisarlo en Prestashop - '.date("Y-m-d_His");

                Productosvendidossinstock::enviaEmail($cuentas, $aviso, 'Disfrazzes', $id_order);
            }

        } else {
            //no hay respuesta pero cerramos igualmente
            curl_close($curl);

            $error_api = 1;

            $mensaje .= '<br>No obtenida respuesta<br><br>';

            $array_json_parameters = pSQL($array_json_parameters);
           
            $sql_update_no_response = "UPDATE lafrips_dropshipping_disfrazzes
            SET
            api_call_parameters = '$array_json_parameters',  
            api_call_response = 'no response',     
            status_name = 'Solicitado no ok',     
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

            Productosvendidossinstock::enviaEmail($cuentas, $mensaje, 'Disfrazzes', $id_order);

            return false;
        }

        $cuentas = array('sergio@lafrikileria.com');

        Productosvendidossinstock::enviaEmail($cuentas, $mensaje, 'Disfrazzes', $id_order);

        return true;

    }

    //función que llama a la API disfrazzes para comprobar el estado del pedido/pedidos. Recibe un array con los/el id de disfrazzes. Hará la llamada con los pedidos que haya y actualizará los pedidos que haya. Así sirve la misma función para una llamada desde el back office de un solo pedido pulsando Estado como para una actualización de varios pedidos
    public static function apiDisfrazzesStatus($ids_lafrips_dropshipping) {      
        //primero el array de ids lo convertimos en una cadena para la select, los ids separados por coma
        $ids_dropshipping = implode(',', $ids_lafrips_dropshipping);
        //sacamos el id de pedido de Disfrazzes del pedido o pedidos
        $sql_disfrazzes_ids = 'SELECT id_dropshipping, disfrazzes_id
        FROM lafrips_dropshipping_disfrazzes
        WHERE id_dropshipping IN ('.$ids_dropshipping.')';

        $disfrazzes_ids = Db::getInstance()->executeS($sql_disfrazzes_ids);

        if (count($disfrazzes_ids) < 1) {
            return false;
        }   

        $order_ids = array();
        //creamos un array donde guardamos la correspondencia id_dropshipping de Prestashop a disfrazzes_id, para después asegurarnos de que los datos devueltos por la API los actualizamos en el pedido correcto, ya que aparentemente, se pueden crear pedidos con nuestro mismo id_order varias veces en su API, evitamos posibles errores (que no deberían darse...). Ponemos un array con el id de disfrazzes como key y id_dropshipping como value
        $combinacion_presta_disfrazzes = array();

        foreach ($disfrazzes_ids AS $disfrazzes_id) {
            $order_ids[] = $disfrazzes_id['disfrazzes_id'];
            $combinacion_presta_disfrazzes[$disfrazzes_id['disfrazzes_id']] = $disfrazzes_id['id_dropshipping'];
        }   
        
        //nos aseguramos de no haber enviado duplicados 
        $order_ids = array_unique($order_ids);
        $combinacion_presta_disfrazzes = array_unique($combinacion_presta_disfrazzes);

        //preparamos parámetros llamada
        $array = array(
            "order_ids" => $order_ids
        ); 
        
        $array_json = json_encode($array);

        // $data = array(
        //     "login" => "XXXXXXX",
        //     "pass" => "XXXXXXXx",
        //     "parameters" => $array_json
        // );

        //01/06/2023 modificamos para meter API keys en secrets/api_disfrazzes.json
        $secrets_json = file_get_contents(dirname(__FILE__).'/../secrets/api_disfrazzes.json');
        
        $secrets = json_decode($secrets_json, true);

        //sacamos las credenciales de la API
        $login = $secrets['login'];
        $pass = $secrets['pass'];

        $data = array(
            "login" => $login,
            "pass" => $pass,
            "parameters" => $array_json
        );
          
        $url = http_build_query($data);
    
        $endpoint_test = 'https://zzdevapi.disfrazzes.com/method/get_orders_status';
        $endpoint_produccion = 'https://api.disfrazzes.com/method/get_orders_status';

        $curl = curl_init();
    
        curl_setopt_array($curl, array(
        CURLOPT_URL => $endpoint_produccion,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $url,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/x-www-form-urlencoded'
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

            $error = 'Error haciendo petición estados a API Disfrazzes, ids dropshipping '.$ids_dropshipping.' - Excepción:<br>'.$exception.'<br>Exception thrown in '.$file.' on line '.$line.': [Code '.$code.']';  

            // $error = 'Error haciendo petición estados a API Disfrazzes, ids dropshipping '.$ids_dropshipping.' - Excepción: '.$e;

            Productosvendidossinstock::insertDropshippingLog(pSQL($error), null, 161);

            return false;
            
        }

        if ($response) {
            curl_close($curl);

            //pasamos el JSON de respuesta a un objeto PHP. 
            $response_decode = json_decode($response); 

            $response_decode_result = (int)$response_decode->result;

            //si result es correcto continuamos - HAY QUE SABER POSIBLES RESULTADOS, solo sé que 1 es correcto
            if ($response_decode_result !== 1) {
                $error = 'Error en petición estados a API Disfrazzes, ids dropshipping = '.$ids_dropshipping.' - response_decode_result = '.$response_decode_result;

                Productosvendidossinstock::insertDropshippingLog($error, null, 161);

                //marcamos error en lafrips_disfrazzes si en $ids_dropshipping hay un entero, es decir, solo se solicita el estado de un pedido
                if (is_int((int)$ids_dropshipping) && (int)$ids_dropshipping != 0) {
                    $sql_update_error = 'UPDATE lafrips_dropshipping_disfrazzes
                    SET
                    error = 1,                                  
                    date_upd = NOW()
                    WHERE id_dropshipping = '.(int)$ids_dropshipping;

                    Db::getInstance()->executeS($sql_update_error); 
                }

                return false;
            }

            foreach ($response_decode->data->array AS $order) {   
                $disfrazzes_order_id = (int)$order->order_id;
                $order_status_id = (int)$order->status->id;
                $order_status_name = $order->status->name;

                //por cada pedido en la respuesta tengo que hacer update en lafrips_dropshipping_disfrazzes en su pedido. Para asegurarnos, sacamos la correspondencia a id_dropshipping desde el array rellenado arriba $combinacion_presta_disfrazzes, cuyo value es id_dropshipping el key disfrazzes_id
                $id_dropshipping = $combinacion_presta_disfrazzes[$disfrazzes_order_id];

                //si el estado es 4 está enviado, sacamos los datos para actualizar lafrips_dropshipping_disfrazzes y marcamos finalizado en lafrips_dropshipping
                //1 - Pendiente, 2 - Esperando stock, 34 - Recibido proveedor, 20 - Procesando pedido, 31 - Listo para enviar, 10- Anulado
                if ($order_status_id == 4) {
                    $order_tracking_date = $order->tracking->date;
                    $order_tracking_agencia = $order->tracking->name;
                    $order_tracking_url = $order->tracking->url;
                    $order_tracking_id = $order->tracking->id_exp;

                    $sql_enviado = ' transportista = "'.$order_tracking_agencia.'",
                    date_expedicion = "'.$order_tracking_date.'",
                    tracking = "'.$order_tracking_id.'",
                    url_tracking = "'.$order_tracking_url.'", ';

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
                } elseif ($order_status_id == 10) {
                    //devuelve como estado anulado (supuestamente por cliente)
                    $sql_enviado = '';
                    
                    $sql_update_cancelado = 'UPDATE lafrips_dropshipping
                    SET                    
                    cancelado = 1, 
                    date_cancelado = NOW(),                                                   
                    date_upd = NOW()
                    WHERE id_dropshipping = '.$id_dropshipping;

                    Db::getInstance()->executeS($sql_update_cancelado);

                    $error = 'Pedido anulado desde Disfrazzes';

                    Productosvendidossinstock::insertDropshippingLog($error, null, 161, null, null, $id_dropshipping);

                } else {
                    $sql_enviado = '';
                }                
                
                //también vienen los datos de producto de expedición, de momento no repasamos

                $sql_update_response = 'UPDATE lafrips_dropshipping_disfrazzes
                SET
                status_id = '.$order_status_id.',
                status_name = "'.$order_status_name.'",
                '.$sql_enviado.'                                  
                date_upd = NOW()
                WHERE id_dropshipping = '.$id_dropshipping.'
                AND disfrazzes_id = '.$disfrazzes_order_id;

                Db::getInstance()->executeS($sql_update_response);                 

            }

        } else {
            //no hay respuesta pero cerramos igualmente
            curl_close($curl);
        }           

        return true;

    }
    
}
