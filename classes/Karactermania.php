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

//todas las funciones para trabajar con pedidos Karactermanía de manera estática

class Karactermania
{
    //función que dado un id_order busca sus productos de Karactermanía en lafrips_productos_vendidos_sin_stock y genera el archivo necesario en el servidor FTP 
    //obtenemos de productos vendidos sin stock la info de la venta y la insertamos en lafrips_pedidos_karactermania si no existe de antemano. Si existe comprobamos cantidades y si son diferentes las pedimos (si ya fue pedido pedimos la diferencia si es superior) Después, si no está marcado como ya pedido, generamos el csv correspondiente al pedido. Finalmente comprobamos si en el pedido original hay más productos vendidos sin stock, marcamos estos como revisados y si no hay ninguno más pasamos el pedido a Completando Pedido. ESTO SE HARä EN PROCESO HORARIO PARA TODOS LOS PROVEEDORES
    //comprobamos cada producto para saber si tiene  categoría prepedido 121, almacenado en lafrips_productos_vendidos_sin_stock, si la tienen no se solicitan por ftp
    public static function gestionKaractermania($id_order) {
        $sql_productos_vendidos_sin_stock = "SELECT * FROM lafrips_productos_vendidos_sin_stock WHERE id_order_detail_supplier = 53 AND id_order = $id_order";

        $productos_karactermania = Db::getInstance()->ExecuteS($sql_productos_vendidos_sin_stock);

        if(count($productos_karactermania) > 0){ 
            foreach($productos_karactermania as $producto){
                $id_product = $producto['id_product'];
                $id_product_attribute = $producto['id_product_attribute'];
                $product_name = pSQL($producto['product_name']);
                $referencia_prestashop = $producto['product_reference'];
                $referencia_karactermania = $producto['product_supplier_reference'];
                $unidades = $producto['product_quantity'];
                $prepedido = $producto['prepedido'];
                $date_original = $producto['date_add'];

                $ean = HerramientasVentaSinStock::getEan($id_product, $id_product_attribute) ? HerramientasVentaSinStock::getEan($id_product, $id_product_attribute) : "";

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
            if (Karactermania::setKaractermaniaFTP($id_order)) {
                return;
            } else {
                return;
            }
        }   

        return;
    }

    public static function setKaractermaniaFTP($id_order) {
        $error_ftp = 0;
        $mensaje = "";
        $info_pedido = "";
        $ids_pedidos_karactermania = array();

        //sacamos la info necesaria. Ponemos forzado el id_supplier y la referencei de karactermania como referencia_proveedor dado que la info será enviada a una función general común en Herramientas.php (setMensajePedido) que se podrá usar para otros proveedores
        $sql_info_ftp = "SELECT id_pedidos_karactermania, 53 AS id_supplier, id_product, id_product_attribute, product_name, referencia_prestashop, referencia_karactermania AS referencia_proveedor, unidades, pedido_manual, id_empleado
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
                $linea_csv = array($id_cliente, $info['referencia_proveedor'], $info['unidades']);
                fputcsv($file, $linea_csv, $delimiter);

                $info_pedido .= $id_cliente.';'.$info['referencia_proveedor'].';'.$info['unidades'].'<br>';
                $mensaje .= $id_cliente.';'.$info['referencia_proveedor'].';'.$info['unidades'].'<br>';

                //marcador que indica que el pedido se creó manualmente y por tanto no existe pedido de cliente, 0 es no manual, 1 es manual, cuando salgamos del foreach de infoftp la variable seguirá teniendo el valor para luego. Igual para id_empleado que utilizaremos para enviar email al empleado que haya realizado el pedido
                $pedido_manual = $info['pedido_manual'];
                $id_empleado = $info['id_empleado'];

                //hacemos update a lafrips_productos_vendidos_sin_stock para marcar como revisado y a lafrips_pedidos_karactermania para marcar ftp a 1, si el pedido es manual no existe en la tabla y pasamos
                //16/11/2023 marcamos solicitado para que los pedidos no aparezcan para generar pedidos de materiales en productos vendiso sin stock
                if (!$pedido_manual) {
                    $sql_update_productos_vendidos_sin_stock = "UPDATE lafrips_productos_vendidos_sin_stock
                    SET
                    solicitado = 1,
                    id_employee_solicitado = 44,
                    date_solicitado = NOW(),                                                      
                    checked = 1,
                    date_checked = NOW(),
                    id_employee = 44, 
                    date_upd = NOW()  
                    WHERE id_order = $id_order
                    AND id_product = ".$info['id_product']."
                    AND id_product_attribute = ".$info['id_product_attribute'];

                    Db::getInstance()->execute($sql_update_productos_vendidos_sin_stock);
                }
                

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
            $secrets_json = file_get_contents(dirname(__FILE__).'/../secrets/ftp_karactermania.json');
            
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
                    //FTP_ASCII para texto, FTP_BINARY otros archivos, excel por ejemplo
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
                //quitamos revisado de lafrips_productos_vendidos_sin_stock, si no es manual
                if (!$pedido_manual) {
                    foreach($info_ftp AS $info) {    
                        //hacemos update a lafrips_productos_vendidos_sin_stock para marcar como revisado 0
                        //16/11/2023 también quitamos solicitado para que sea evidente en la lista de productos vendidos sin stock que no se ha pedido
                        $sql_update_productos_vendidos_sin_stock = "UPDATE lafrips_productos_vendidos_sin_stock
                        SET          
                        solicitado = 0,
                        id_employee_solicitado = 44,
                        date_solicitado = NOW(),                                            
                        checked = 0,
                        date_checked = NOW(),
                        id_employee = 44, 
                        date_upd = NOW() 
                        WHERE id_order = $id_order
                        AND id_product = ".$info['id_product']."
                        AND id_product_attribute = ".$info['id_product_attribute'];
        
                        Db::getInstance()->execute($sql_update_productos_vendidos_sin_stock);
                    }       
                }
                
            } else {
                //si no ha habido errores metemos un mensaje CustomerMessage dentro del pedido sobre el pedido a Karactermanía y después comprobamos si el pedido contiene algún otro producto vendido sin stock que no esté revisado. Si lo tiene no hacemos nada más, si no lo tiene cambiamos el estado a Completando Pedido
                //primero ponemos el mensaje al pedido
                //solo pedidos no manuales
                if (!$pedido_manual) {
                    if (!$id_customer_thread = HerramientasVentaSinStock::setMensajePedido($id_order, $info_ftp)) {
                        $error_archivo .= "WARNING - Error mensaje interno para pedido - ";
                        $mensaje .= "<br><br>Error añadiendo mensaje interno de compra a pedido";
                    }
                }                

                //ahora procesamos si hay que cambiar de estado - LO PONEMOS EN PROCESO PROGRAMADO PARA TODOS PROVEEDORES Y PEDIDOS
                // if (!HerramientasVentaSinStock::checkCambioEsperandoProductos($id_order, $id_customer_thread)) {
                //     $error_archivo .= "WARNING - Error cambiando estado pedido - ";
                //     $mensaje .= "<br><br>Error cambiando estado de pedido a Esperando Productos";
                // }
            }

            //cerramos conexión
            ftp_close($ftp_connection);

            // $sergio = array('nombre' => 'Sergio', 'email' => 'sergio@lafrikileria.com');            

            $empleados = array(
                array('nombre' => 'Sergio', 'email' => 'sergio@lafrikileria.com')
            );

            if ($pedido_manual) {               

                //si el pedido es manual enviamos a Lorena también
                $empleados[] = array('nombre' => 'Lorena', 'email' => 'lorena@lafrikileria.com');

                $employee = new Employee($id_empleado);

                $mensaje .= "<br><br>Pedido manual generado por ".$employee->firstname." ".$employee->lastname;

                //obtenemos nombre y email de empleado, si no es sergio ni Lorena, lo añadimos a $empleados para email
                if (($id_empleado != 22) && ($id_empleado != 4)) {
                    $empleados[] = array('nombre' => $employee->firstname, 'email' => $employee->email);            
                   
                } 

                $es_manual = "MANUAL (".$employee->firstname.") ";

            } else {
                $es_manual = "";                   
                
            }      

            //enviamos email de aviso con mensaje, dependiendo de si hay error
            $info = []; 
            $info['{archivo_expediciones}'] = $error_archivo.'Pedido '.$es_manual.$id_order.' a Karactermanía '.date("Y-m-d H:i:s");
            $info['{errores}'] = $mensaje;
            $info['asunto'] = $error_archivo.'Pedido '.$es_manual.$id_order.' realizado a Karactermanía '.date("Y-m-d H:i:s");

            // $empleados = array(
            //     array('nombre' => 'Sergio', 'email' => 'sergio@lafrikileria.com')
            // );

            Karactermania::enviaEmail($info, $empleados);

            if ($error_ftp || !$encontrado) {
                //este return en caso de error solo interpreta el false para los pedidos manuales, los pedidos normales no leen el return
                return false;
            }
    
            return true;            

        } 
        
        //quito este colector de error, si el producto era prepedido no saldría en el resultado de la consulta y se consideraría error
        // else {
        //     //no se obtuvieron datos de pedido de Karactermanía para ese id_order, manual o de cliente, enviamos email de error a sergio@lafrikileria.com
        //     //enviamos email de aviso con mensaje, dependiendo de si hay error
        //     $info = [];   
        //     $info['{archivo_expediciones}'] = 'ERROR procesando pedido Karactermanía, id_order '.$id_order.' no encontrado en tabla pedidos_karactermania '.date("Y-m-d H:i:s");
        //     $info['{errores}'] = 'Error inexplicable, el pedido creado no está donde debería';
        //     $info['asunto'] = 'ERROR procesando pedido Karactermanía, id_order '.$id_order.' no encontrado '.date("Y-m-d H:i:s");

        //     $empleados = array(
        //         array('nombre' => 'Sergio', 'email' => 'sergio@lafrikileria.com')
        //     );

        //     Karactermania::enviaEmail($info, $empleados);

        //     return false;
        // }

        return true;
        
    }

    //función para preparar un pedido a Karactermanía manual, es decir, el pedido no existe en Prestashop y por tanto no hay cliente, ni datos de productos vendidos sin stock, etc. Se llama desde AdminPedidosManualesProveedor.php. No tenemos id_order que se generará en función de la tabla pedidosKaractermania, recibimos un array con datos de producto y unidades a solicitar, puede ser un pedido de un producto o varios. En este punto ya deben estar validados los productos.
    public static function gestionKaractermaniaManual($info_pedido) {
        //sacamos le id de empleado
        $id_empleado = Context::getContext()->employee->id; 

        //creamos un pedido de cliente ficticio ya que hay que enviarselo a Karactermanía. El número lo sacamos de la tabla lafrips_pedidos_karactermania, recogiendo el mayor id_order de un pedido manual, y sumándole 1. Empezamos desde 2000000. Sacamos el id:
        $sql_id_order = 'SELECT MAX(id_order) FROM lafrips_pedidos_karactermania WHERE pedido_manual = 1';
        $id_order = Db::getInstance()->getValue($sql_id_order) + 1;
        
        //recorremos $info_pedido como array, puede contener un producto ovarios, y por cada uno meteremos los datos en lafrips_pedidos_karactermania
        foreach ($info_pedido AS $info_producto) {
            $referencia = $info_producto['referencia'];
            $unidades = $info_producto['unidades'];

            //buscar id_product e id_product_attribute, ean referencia prestashop. 
            $sql_producto_en_prestashop = 'SELECT psu.id_product AS id_product, psu.id_product_attribute AS id_product_attribute, IFNULL(pat.reference, pro.reference) AS referencia_prestashop,
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
            WHERE psu.id_supplier = 53
            AND psu.product_supplier_reference = "'.$referencia.'"';

            $producto_en_prestashop = Db::getInstance()->executeS($sql_producto_en_prestashop);

            if (!$producto_en_prestashop || (count($producto_en_prestashop) > 1)) {
                //no se encuentra producto o la referencia corresponde a más de uno, en principio javascript no habría permitido llegar hasta aquí. devolvemos false
                return false;
            }

            $id_product = $producto_en_prestashop[0]['id_product'];
            $id_product_attribute = $producto_en_prestashop[0]['id_product_attribute'];
            $referencia_prestashop = $producto_en_prestashop[0]['referencia_prestashop'];
            $product_name = pSQL($producto_en_prestashop[0]['nombre']);
            $ean = $producto_en_prestashop[0]['ean13'];  

            //hacemos insert
            $sql_insert = "INSERT INTO lafrips_pedidos_karactermania
            (id_order, id_product, id_product_attribute, referencia_karactermania, unidades, product_name, ean, referencia_prestashop, pedido_manual, id_empleado, date_original, date_add)
            VALUES
            (
                $id_order, $id_product, $id_product_attribute, '$referencia', $unidades, '$product_name', '$ean', '$referencia_prestashop', 1, $id_empleado, NOW(), NOW()
            )";

            Db::getInstance()->execute($sql_insert);   
        }

        //ahora tenemos en lafrips_pedidos_karactermania los productos con ftp 0 y los ids correspondientes para generar el archivo.
        if (Karactermania::setKaractermaniaFTP($id_order) == false) {
            return false;
        } else {
            return true;
        }           

        return;
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
